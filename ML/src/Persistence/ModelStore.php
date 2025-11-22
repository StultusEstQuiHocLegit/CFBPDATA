<?php
// ModelStore.php
// Handles saving and loading of model artefacts and reports.

declare(strict_types=1);

namespace App\Persistence;

use App\ML\EstimatorInterface;
use App\Features\Transformers\Preprocessor;

// ModelStore centralises persistence of the trained model, preprocessor, calibrator and related metadata,
// it writes files into the predefined directories under `models/` and `reports/` to ensure reproducibility
class ModelStore
{
    public static function saveAll(
        EstimatorInterface $model,
        Preprocessor $pre,
        object $cal,
        object $config,
        array $metrics,
        array $hyperparameters,
        string $calibratorClass,
        ?string $baseDir = null
    ): void {
        // ensure directories exist
        $base = $baseDir ?? dirname(__DIR__, 2);
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
        if (!method_exists($cal, 'save')) {
            throw new \RuntimeException('Calibrator does not implement save().');
        }
        $cal->save($modelsDir . '/calibrator.bin');
        // save metadata
        $meta = [
            'timestamp' => date('c'),
            'config' => $config,
            'feature_names' => $pre->getFeatureNames(),
            'hyperparameters' => $hyperparameters,
            'calibration' => [
                'class' => $calibratorClass,
                'type' => $metrics['calibration']['type'] ?? null,
            ],
        ];
        if (isset($metrics['selected_l2'])) {
            $meta['selected_l2'] = $metrics['selected_l2'];
        }
        if (isset($metrics['num_trees'])) {
            $meta['num_trees'] = $metrics['num_trees'];
        }
        if (isset($metrics['learning_rate'])) {
            $meta['learning_rate'] = $metrics['learning_rate'];
        }
        if (isset($metrics['max_depth'])) {
            $meta['max_depth'] = $metrics['max_depth'];
        }
        file_put_contents($modelsDir . '/metadata.json', json_encode($meta, JSON_PRETTY_PRINT));
        // save metrics
        file_put_contents($reportsDir . '/metrics.json', json_encode($metrics, JSON_PRETTY_PRINT));
    }
}
