<?php
// PredictController.php
// Handles HTTP requests to generate predictions on uploaded data.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\Json;
use App\Config\Config;
use App\IO\CSVReader;
use App\Data\DataFrame;
use App\Features\FeatureBuilder;
use App\Features\Transformers\Preprocessor;
use App\ML\Calibrator\Isotonic;
use App\ML\Models\LogisticRegression;
use App\ML\Models\RandomForest;
use App\Util\Logger;

class PredictController
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function handle(array $post, array $files): Json
    {
        $this->logger->info('Starting prediction…');
        // Load persisted artefacts
        $base = dirname(__DIR__, 3);
        $modelsDir = $base . '/models';
        $reportsDir = $base . '/reports';
        try {
            $metaPath = $modelsDir . '/metadata.json';
            if (!file_exists($metaPath)) {
                return Json::error('Model metadata not found; train the model first');
            }
            $meta = json_decode(file_get_contents($metaPath), true);
            $modelType = $meta['config']['model']['type'] ?? 'logistic_regression';
            if ($modelType === 'random_forest') {
                $model = RandomForest::load($modelsDir . '/model.bin');
            } else {
                $model = LogisticRegression::load($modelsDir . '/model.bin');
            }
            $pre = Preprocessor::load($modelsDir . '/preprocessor.bin');
            $cal = Isotonic::load($modelsDir . '/calibrator.bin');
            $metrics = json_decode(file_get_contents($reportsDir . '/metrics.json'), true);
            $th = $metrics['thresholds'] ?? ['best' => 0.5, 'recall80' => 0.5];
        } catch (\Throwable $e) {
            return Json::error('Failed to load artefacts: ' . $e->getMessage());
        }
        // Read input file (CSV) if provided
        if (!isset($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
            return Json::error('No input file uploaded');
        }
        $tmpPath = $files['file']['tmp_name'];
        try {
            $dfInput = CSVReader::load($tmpPath);
        } catch (\Throwable $e) {
            return Json::error('Failed to read input: ' . $e->getMessage());
        }
        // Build features using same feature config. Before feeding rows to the
        // feature builder, normalise identifier and time fields into
        // `company_id` and `fiscal_year` columns, this mirrors the logic in
        // TrainController so that feature engineering and downstream code can
        // rely on these canonical names, the raw identifier/time fields may be
        // named differently (for example: idpk/year), so copy whichever exists
        $root = dirname(__DIR__, 3);
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
        $rows = $dfInput->toRows();
        foreach ($rows as &$r) {
            // ensure a label column exists for the builder; default to 0
            $r['label'] = $r['label'] ?? 0;
            // copy id/time into canonical names if present
            if (!isset($r['company_id'])) {
                $r['company_id'] = $r['company_id'] ?? $r['idpk'] ?? $r['CIK'] ?? null;
            }
            if (!isset($r['fiscal_year'])) {
                $r['fiscal_year'] = $r['fiscal_year'] ?? $r['year'] ?? $r['DocumentFiscalYearFocus'] ?? null;
            }
        }
        $dfFeat = $builder->build(DataFrame::fromRows($rows));
        // Transform via preprocessor
        $X = $pre->transform($dfFeat->toRows());
        // Predict raw and calibrated probabilities
        $probaRaw = $model->predictProba($X);
        $proba = $cal->apply($probaRaw);
        // Assign risk buckets
        $results = [];
        $bestT = $th['best'] ?? 0.5;
        $recT = $th['recall80'] ?? 0.5;
        $featureNames = $pre->getFeatureNames();
        foreach ($proba as $i => $p) {
            $bucket = 'Low-risk';
            if ($p >= $recT) {
                $bucket = 'Monitor';
            }
            if ($p >= $bestT) {
                $bucket = 'High-risk';
            }
            // compute simple contribution: product of weights and values if logistic
            $contrib = [];
            if ($model instanceof LogisticRegression) {
                $ref = new \ReflectionClass($model);
                if ($ref->hasProperty('weights')) {
                    $prop = $ref->getProperty('weights');
                    $prop->setAccessible(true);
                    $weightsVal = $prop->getValue($model);
                    $rowVec = $X[$i];
                    foreach ($weightsVal as $j => $w) {
                        $contrib[$featureNames[$j]] = $w * $rowVec[$j];
                    }
                    arsort($contrib);
                    $contrib = array_slice($contrib, 0, 3, true);
                }
            }
            $results[] = [
                'company_id' => $dfFeat->col('company_id')[$i] ?? null,
                'fiscal_year' => $dfFeat->col('fiscal_year')[$i] ?? null,
                'p_default_12m' => $p,
                'risk_bucket' => $bucket,
                'top_features' => $contrib,
            ];
        }
        $this->logger->info('Predicted ' . count($results) . ' rows');
        $logEntries = $this->logger->flush();
        return Json::success([
            'results' => $results,
            'logs' => $logEntries,
        ]);
    }
}