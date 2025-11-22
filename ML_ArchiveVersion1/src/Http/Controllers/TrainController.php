<?php
// TrainController.php
// Handles HTTP requests to train the bankruptcy prediction model.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\Json;
use App\Config\Config;
use App\IO\CSVReader;
use App\Data\DataFrame;
use App\Data\Splitter;
use App\Features\FeatureBuilder;
use App\Features\Transformers\Preprocessor;
use App\ML\Training\Trainer;
use App\ML\Calibrator\Isotonic;
use App\ML\Eval\Metrics;
use App\ML\Eval\Thresholds;
use App\Persistence\ModelStore;
use App\Util\Logger;

class TrainController
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    // Handle the training action, expects optional config file path in POST,
    // returns JSON with metrics and log entries
    public function handle(array $post, array $files): Json
    {
        // Relax the memory limit because training requires working with
        // multiple large dataframes simultaneously. Shared hosts often cap the
        // limit at 128M which is insufficient for the merged dataset.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
        $this->logger->info('Starting training…');
        try {
            // Determine project root relative to this controller (src/Http/Controllers)
            $root = dirname(__DIR__, 3);
            $configPath = $post['config'] ?? $root . '/config/default.json';
            $cfg = Config::fromFile($configPath);
        } catch (\Throwable $e) {
            return Json::error('Failed to load configuration: ' . $e->getMessage());
        }
        // Load CSVs
        $root = dirname(__DIR__, 3);
        try {
            $posPath = $this->resolvePath($cfg->paths->bankrupt_csv, $root);
            $negPath = $this->resolvePath($cfg->paths->solvent_csv, $root);
            [$dfPos, $dfNeg] = CSVReader::loadPair($posPath, $negPath);
        } catch (\Throwable $e) {
            return Json::error('Failed to load CSVs: ' . $e->getMessage());
        }
        $this->logger->info('Loaded ' . $dfPos->height() . ' bankrupt rows and ' . $dfNeg->height() . ' solvent rows');
        // Create labels: positive file -> last year = 1, earlier = 0; negative file -> 0.
        // We also normalise the raw identifier and time columns into a unified
        // `company_id` and `fiscal_year` so that downstream components can rely
        // on these names regardless of the original CSV schema. The schema
        // fields are specified in the configuration (e.g. idpk/year).
        $posIdCol = $dfPos->col($cfg->schema->id);
        $posYearCol = array_map(
            static fn($v) => $v !== null ? (int) $v : null,
            $dfPos->col($cfg->schema->time)
        );
        $lastYear = [];
        foreach ($posIdCol as $idx => $cidRaw) {
            $yearRaw = $posYearCol[$idx] ?? null;
            if ($cidRaw === null || $yearRaw === null) {
                continue;
            }
            if (!isset($lastYear[$cidRaw]) || $yearRaw > $lastYear[$cidRaw]) {
                $lastYear[$cidRaw] = $yearRaw;
            }
        }
        $labelsPos = [];
        foreach ($posIdCol as $idx => $cidRaw) {
            $yearRaw = $posYearCol[$idx] ?? null;
            $labelsPos[$idx] = ($cidRaw !== null && $yearRaw !== null && $yearRaw === ($lastYear[$cidRaw] ?? null)) ? 1 : 0;
        }
        $dfPos = $dfPos
            ->withColumn('company_id', $posIdCol)
            ->withColumn('fiscal_year', $posYearCol)
            ->withColumn('label', $labelsPos);
        $negIdCol = $dfNeg->col($cfg->schema->id);
        $negYearCol = array_map(
            static fn($v) => $v !== null ? (int) $v : null,
            $dfNeg->col($cfg->schema->time)
        );
        $labelsNeg = array_fill(0, $dfNeg->height(), 0);
        $dfNeg = $dfNeg
            ->withColumn('company_id', $negIdCol)
            ->withColumn('fiscal_year', $negYearCol)
            ->withColumn('label', $labelsNeg);
        $df = $dfPos->concat($dfNeg);
        // sanity check: ensure canonical id/time columns are populated
        try {
            $ids = $df->col('company_id');
            $years = $df->col('fiscal_year');
        } catch (\Throwable $e) {
            return Json::error('Missing expected columns: company_id or fiscal_year');
        }
        if (in_array(null, $ids, true) || in_array(null, $years, true)) {
            return Json::error('Missing company_id or fiscal_year values');
        }
        // Split into train/valid/test
        $splits = Splitter::byYearAndCompany($df, $cfg->split);
        $this->logger->info('Split into train=' . $splits->train->height() . ', valid=' . $splits->valid->height() . ', test=' . $splits->test->height());
        // Feature engineering
        $featConfigPath = $root . '/config/features.json';
        $featConfigRaw = @file_get_contents($featConfigPath);
        if ($featConfigRaw === false) {
            return Json::error('Failed to read feature configuration');
        }
        $featConfig = json_decode($featConfigRaw, true);
        if (!is_array($featConfig)) {
            return Json::error('Invalid feature configuration JSON');
        }
        $builder = new FeatureBuilder($featConfig);
        $dfTrainFeat = $builder->build($splits->train);
        $dfValidFeat = $builder->build($splits->valid);
        $dfTestFeat  = $builder->build($splits->test);
        $this->logger->info('Feature engineering complete');
        // Preprocessing
        $categorical = $featConfig['categorical'] ?? [];
        $preprocessor = new Preprocessor($cfg->preprocess, $categorical);
        $preprocessor->fit($dfTrainFeat->toRows());
        $Xtr = $preprocessor->transform($dfTrainFeat->toRows());
        $Xva = $preprocessor->transform($dfValidFeat->toRows());
        $Xte = $preprocessor->transform($dfTestFeat->toRows());
        $ytr = $dfTrainFeat->y();
        $yva = $dfValidFeat->y();
        $yte = $dfTestFeat->y();
        $this->logger->info('Preprocessing and vectorisation complete');
        // Compute class weights
        $weights = Trainer::classWeights($ytr);
        // Train model
        $this->logger->info('Training model…');
        $model = Trainer::train($Xtr, $ytr, $Xva, $yva, $weights, $cfg->model);
        // Validation predictions and calibration
        $probaVaRaw = $model->predictProba($Xva);
        $calibrator = Isotonic::fit($probaVaRaw, $yva);
        $probaVa = $calibrator->apply($probaVaRaw);
        // Choose thresholds
        $operatingPointsVal = Thresholds::choose($probaVa, $yva, $cfg->thresholds);
        $primaryThreshold = $operatingPointsVal['primary']['threshold'];
        $recallTargetThreshold = $operatingPointsVal['recall_target']['threshold'];
        $targetRecall = $operatingPointsVal['recall_target']['target_recall'] ?? ($cfg->thresholds->strict_recall_at ?? 0.8);
        $this->logger->info(sprintf(
            'Selected thresholds: primary=%.3f, f1_max=%.3f, recall≥%.2f => %.3f',
            $primaryThreshold,
            $operatingPointsVal['f1_max']['threshold'],
            $targetRecall,
            $recallTargetThreshold
        ));
        // Evaluate on test set
        $probaTestRaw = $model->predictProba($Xte);
        $probaTest = $calibrator->apply($probaTestRaw);
        $pr = Metrics::prCurve($probaTest, $yte);
        $roc = Metrics::rocCurve($probaTest, $yte);
        $brier = Metrics::brier($probaTest, $yte);
        $validationReliability = Metrics::reliabilityCurve($probaVa, $yva);
        $testReliability = Metrics::reliabilityCurve($probaTest, $yte);
        $this->logger->info('Validation reliability bins: ' . $this->renderReliabilitySummary($validationReliability));
        $this->logger->info('Test reliability bins: ' . $this->renderReliabilitySummary($testReliability));

        $cmPrimaryTest = Metrics::confusion($probaTest, $yte, $primaryThreshold);
        $cmF1Test = Metrics::confusion($probaTest, $yte, $operatingPointsVal['f1_max']['threshold']);
        $cmStrictTest = Metrics::confusion($probaTest, $yte, $recallTargetThreshold);

        $formatPoint = static function (float $threshold, array $cm) {
            return [
                'threshold' => $threshold,
                'precision' => $cm['precision'],
                'recall' => $cm['recall'],
                'f1' => $cm['f1'],
                'support' => [
                    'tp' => $cm['TP'],
                    'fp' => $cm['FP'],
                    'tn' => $cm['TN'],
                    'fn' => $cm['FN'],
                ],
            ];
        };

        $thresholdSummary = [
            'primary' => $primaryThreshold,
            'f1_max' => $operatingPointsVal['f1_max']['threshold'],
            'recall_target' => $recallTargetThreshold,
            'best' => $primaryThreshold,
        ];
        if (abs($targetRecall - 0.8) < 1e-6) {
            $thresholdSummary['recall80'] = $recallTargetThreshold;
        }

        $operatingPointsTest = [
            'primary' => $formatPoint($primaryThreshold, $cmPrimaryTest),
            'f1_max' => $formatPoint($operatingPointsVal['f1_max']['threshold'], $cmF1Test),
            'recall_target' => $formatPoint($recallTargetThreshold, $cmStrictTest),
        ];
        $operatingPointsTest['recall_target']['target_recall'] = $targetRecall;

        $metrics = [
            'pr_auc' => $pr['pr_auc'],
            'roc_auc' => $roc['roc_auc'],
            'brier' => $brier,
            'thresholds' => $thresholdSummary,
            'operating_points' => [
                'validation' => $operatingPointsVal,
                'test' => $operatingPointsTest,
            ],
            'reliability' => [
                'validation' => $validationReliability,
                'test' => $testReliability,
            ],
            'confusion_best' => $cmPrimaryTest,
            'confusion_strict' => $cmStrictTest,
        ];
        $this->logger->info('Evaluation on test set complete');
        // Persist artefacts
        ModelStore::saveAll($model, $preprocessor, $calibrator, $cfg, $metrics);
        $this->logger->info('Model and artefacts saved');
        // Build response
        $logEntries = $this->logger->flush();
        return Json::success([
            'metrics' => $metrics,
            'logs' => $logEntries,
        ]);
    }

    // Render a compact summary of reliability bins for logging purposes
    // @param array<int, array<string, float|int>> $bins
    private function renderReliabilitySummary(array $bins): string
    {
        if (empty($bins)) {
            return 'n/a';
        }
        $parts = [];
        foreach ($bins as $bin) {
            $lower = isset($bin['lower']) ? (float) $bin['lower'] : 0.0;
            $upper = isset($bin['upper']) ? (float) $bin['upper'] : 0.0;
            $avgPred = isset($bin['avg_pred']) ? (float) $bin['avg_pred'] : 0.0;
            $emp = isset($bin['emp_rate']) ? (float) $bin['emp_rate'] : 0.0;
            $parts[] = sprintf('[%.2f-%.2f]=%.2f→%.2f', $lower, $upper, $avgPred, $emp);
        }
        return implode(', ', $parts);
    }

    private function resolvePath(string $path, string $root): string
    {
        if ($path === '') {
            throw new \RuntimeException('Empty path provided');
        }
        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\/]#', $path) === 1) {
            return $path;
        }
        return $root . '/' . ltrim($path, '/');
    }
}
