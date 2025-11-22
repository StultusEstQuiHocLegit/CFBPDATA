<?php
// ModelStore.php
// Handles saving and loading of model artefacts and reports.

declare(strict_types=1);

namespace App\Persistence;

use App\ML\EstimatorInterface;
use App\Features\Transformers\Preprocessor;
use App\ML\Calibrator\Isotonic;

// ModelStore centralises persistence of the trained model, preprocessor, calibrator and related metadata,
// it writes files into the predefined directories under `models/` and `reports/` to ensure reproducibility
class ModelStore
{
    public static function saveAll(
        EstimatorInterface $model,
        Preprocessor $pre,
        Isotonic $cal,
        object $config,
        array $metrics
    ): void {
        // ensure directories exist
        $base = dirname(__DIR__, 2);
        $modelsDir = $base . '/models';
        $reportsDir = $base . '/reports';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0775, true);
        }
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0775, true);
        }
        // save model, preprocessor, calibrator
        $model->save($modelsDir . '/model.bin');
        $pre->save($modelsDir . '/preprocessor.bin');
        $cal->save($modelsDir . '/calibrator.bin');
        // save metadata
        $meta = [
            'timestamp' => date('c'),
            'config' => $config,
            'feature_names' => $pre->getFeatureNames(),
        ];
        file_put_contents($modelsDir . '/metadata.json', json_encode($meta, JSON_PRETTY_PRINT));
        // save metrics
        file_put_contents($reportsDir . '/metrics.json', json_encode($metrics, JSON_PRETTY_PRINT));
    }
}