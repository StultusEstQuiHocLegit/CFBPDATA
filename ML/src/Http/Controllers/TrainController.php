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
use App\ML\Calibrator\Platt;
use App\ML\Calibrator\Beta;
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
        $metricTarget = strtolower((string) ($cfg->cross_validation->metric ?? $cfg->thresholds->optimize_for ?? 'pr_auc'));
        if ($metricTarget !== 'roc_auc') {
            $metricTarget = 'pr_auc';
        }
        $trainingResult = null;
        $folds = [];
        if (($cfg->cross_validation->enabled ?? false) === true) {
            $numFolds = (int) ($cfg->cross_validation->num_folds ?? 3);
            $folds = Splitter::temporalFolds($splits->train, $numFolds, 'fiscal_year', 'company_id');
        }
        if (!empty($folds)) {
            $this->logger->info('Cross-validating model over ' . count($folds) . ' folds…');
            $trainingResult = Trainer::crossValidateAndTrain($Xtr, $ytr, $folds, $cfg->model, $metricTarget, $weights);
        } else {
            $this->logger->info('Training model…');
            $trainingResult = Trainer::train($Xtr, $ytr, $Xva, $yva, $weights, $cfg->model, $metricTarget);
        }
        $model = $trainingResult['model'];
        $trainedHyper = $trainingResult['hyperparameters'];
        // Validation predictions and calibration
        $probaVaRaw = $model->predictProba($Xva);
        $calibrationType = strtolower((string) ($cfg->calibration ?? 'isotonic'));
        $calibratorClass = Isotonic::class;
        if ($calibrationType === 'platt') {
            $calibratorClass = Platt::class;
            $calibrator = $calibratorClass::fit($probaVaRaw, $yva);
            $probaVa = $calibrator->apply($probaVaRaw);
        } elseif ($calibrationType === 'beta') {
            $betaConf = $cfg->beta ?? (object) [];
            $learningRate = isset($betaConf->learning_rate) ? (float) $betaConf->learning_rate : 0.01;
            $iterations = isset($betaConf->iterations) ? (int) $betaConf->iterations : 1000;
            $calibrator = new Beta($learningRate, $iterations);
            $calibrator->fit($probaVaRaw, $yva);
            $probaVa = $calibrator->apply($probaVaRaw);
            $calibratorClass = Beta::class;
        } else {
            if ($calibrationType !== 'isotonic') {
                return Json::error('Unknown calibration type: ' . $cfg->calibration);
            }
            $calibratorClass = Isotonic::class;
            $calibrator = $calibratorClass::fit($probaVaRaw, $yva);
            $probaVa = $calibrator->apply($probaVaRaw);
        }
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

        $thresholdConf = $cfg->thresholds;
        $costFP = isset($thresholdConf->cost_false_positive) ? (float) $thresholdConf->cost_false_positive : 1.0;
        $costFN = isset($thresholdConf->cost_false_negative) ? (float) $thresholdConf->cost_false_negative : 1.0;
        $betaVal = isset($thresholdConf->beta) ? (float) $thresholdConf->beta : 1.0;
        $betaSq = $betaVal * $betaVal;

        $formatPoint = static function (float $threshold, array $cm, float $costFP, float $costFN, float $betaSq, ?int $index = null) {
            $point = [
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
            $point['expected_cost'] = $costFP * $cm['FP'] + $costFN * $cm['FN'];
            $precision = $cm['precision'];
            $recall = $cm['recall'];
            $den = $betaSq * $precision + $recall;
            $point['f_beta'] = $den > 0.0 ? ((1 + $betaSq) * $precision * $recall / $den) : 0.0;
            if ($index !== null) {
                $point['threshold_index'] = $index;
            }
            return $point;
        };

        $thresholdSummary = [
            'primary' => [
                'threshold' => $primaryThreshold,
                'expected_cost' => $operatingPointsVal['primary']['expected_cost'] ?? null,
                'f_beta' => $operatingPointsVal['primary']['f_beta'] ?? null,
            ],
            'f1_max' => [
                'threshold' => $operatingPointsVal['f1_max']['threshold'],
                'expected_cost' => $operatingPointsVal['f1_max']['expected_cost'] ?? null,
                'f_beta' => $operatingPointsVal['f1_max']['f_beta'] ?? null,
            ],
            'recall_target' => [
                'threshold' => $recallTargetThreshold,
                'target_recall' => $targetRecall,
                'expected_cost' => $operatingPointsVal['recall_target']['expected_cost'] ?? null,
                'f_beta' => $operatingPointsVal['recall_target']['f_beta'] ?? null,
            ],
            'best' => $primaryThreshold,
        ];
        if (abs($targetRecall - 0.8) < 1e-6) {
            $thresholdSummary['recall80'] = $recallTargetThreshold;
        }

        $operatingPointsTest = [
            'primary' => $formatPoint(
                $primaryThreshold,
                $cmPrimaryTest,
                $costFP,
                $costFN,
                $betaSq,
                $operatingPointsVal['primary']['threshold_index'] ?? null
            ),
            'f1_max' => $formatPoint(
                $operatingPointsVal['f1_max']['threshold'],
                $cmF1Test,
                $costFP,
                $costFN,
                $betaSq,
                $operatingPointsVal['f1_max']['threshold_index'] ?? null
            ),
            'recall_target' => $formatPoint(
                $recallTargetThreshold,
                $cmStrictTest,
                $costFP,
                $costFN,
                $betaSq,
                $operatingPointsVal['recall_target']['threshold_index'] ?? null
            ),
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
            'calibration' => [
                'type' => $calibrationType,
            ],
            'hyperparameters' => $trainedHyper,
        ];
        $metrics['model_type'] = $trainedHyper['model_type'] ?? ($cfg->model->type ?? 'logistic_regression');
        if (isset($trainedHyper['selected_l2'])) {
            $metrics['selected_l2'] = $trainedHyper['selected_l2'];
        }
        if (($trainedHyper['model_type'] ?? '') === 'gradient_boosting') {
            $metrics['num_trees'] = $trainedHyper['selected_num_trees'] ?? null;
            $metrics['learning_rate'] = $trainedHyper['selected_learning_rate'] ?? null;
            if (isset($trainedHyper['selected_max_depth'])) {
                $metrics['max_depth'] = $trainedHyper['selected_max_depth'];
            }
        }
        if (($trainedHyper['model_type'] ?? '') === 'random_forest') {
            $metrics['num_trees'] = $trainedHyper['selected_num_trees'] ?? ($metrics['num_trees'] ?? null);
            if (isset($trainedHyper['selected_max_depth'])) {
                $metrics['max_depth'] = $trainedHyper['selected_max_depth'];
            }
        }
        if (isset($trainedHyper['cv_metric'])) {
            $metrics['cv_metric'] = $trainedHyper['cv_metric'];
        }
        if (isset($trainedHyper['cv_per_fold'])) {
            $metrics['cv_per_fold'] = $trainedHyper['cv_per_fold'];
        }
        if (isset($trainedHyper['cv_folds'])) {
            $metrics['cv_folds'] = $trainedHyper['cv_folds'];
        }
        $this->logger->info('Evaluation on test set complete');
        // Persist artefacts
        ModelStore::saveAll(
            $model,
            $preprocessor,
            $calibrator,
            $cfg,
            $metrics,
            $trainedHyper,
            $calibratorClass
        );
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
