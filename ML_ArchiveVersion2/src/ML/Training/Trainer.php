<?php
// trainer.php
// Orchestrates model training, including class weighting and hyperparameter search.

declare(strict_types=1);

namespace App\ML\Training;

use App\ML\Models\GradientBoosting;
use App\ML\Models\LogisticRegression;
use App\ML\Models\RandomForest;
use App\ML\EstimatorInterface;
use App\ML\Eval\Metrics;

// Trainer exposes helper methods for training estimators with class weights and optional hyperparameter tuning,
// it currently implements a simple grid search over L2 regularisation for logistic regression
class Trainer
{
    // Compute inverse frequency sample weights,
    // positive samples receive higher weights when the dataset is imbalanced,
    // returns an array of weights aligned with y
    // @param int[] $y
    // @return float[]
    public static function classWeights(array $y): array
    {
        $n = count($y);
        $pos = array_sum($y);
        $neg = $n - $pos;
        $wPos = ($pos > 0) ? ($n / (2.0 * $pos)) : 1.0;
        $wNeg = ($neg > 0) ? ($n / (2.0 * $neg)) : 1.0;
        $weights = [];
        foreach ($y as $yi) {
            $weights[] = ($yi === 1) ? $wPos : $wNeg;
        }
        return $weights;
    }

    // Train a model based on the provided configuration,
    // supports logistic regression and random forest (wraps logistic),
    // performs simple grid search on L2 regularisation if specified,
    // returns the trained estimator
    // @param array<int,array<float>> $Xtr
    // @param int[] $ytr
    // @param array<int,array<float>> $Xva
    // @param int[] $yva
    // @param float[] $weights
    // @param object $modelConf
    // @return array{model: EstimatorInterface, hyperparameters: array<string, mixed>}
    public static function train(array $Xtr, array $ytr, array $Xva, array $yva, array $weights, object $modelConf): array
    {
        $options = self::normaliseConfig($modelConf);

        $type = $options['type'] ?? 'logistic_regression';
        if ($type === 'random_forest') {
            $model = new RandomForest();
            $model->fit($Xtr, $ytr, $weights);
            return [
                'model' => $model,
                'hyperparameters' => [
                    'model_type' => 'random_forest',
                ],
            ];
        }
        if ($type === 'gradient_boosting') {
            $gbConf = $options['gradient_boosting'] ?? [];
            $numTrees = isset($gbConf['num_trees']) ? (int) $gbConf['num_trees'] : 50;
            $learningRate = isset($gbConf['learning_rate']) ? (float) $gbConf['learning_rate'] : 0.1;
            $maxDepth = isset($gbConf['max_depth']) ? (int) $gbConf['max_depth'] : 1;
            $model = new GradientBoosting($numTrees, $learningRate, $maxDepth);
            $model->fit($Xtr, $ytr, $weights);
            return [
                'model' => $model,
                'hyperparameters' => [
                    'model_type' => 'gradient_boosting',
                    'num_trees' => $model->getNumTrees(),
                    'learning_rate' => $model->getLearningRate(),
                    'max_depth' => $maxDepth,
                ],
            ];
        }
        // logistic regression: grid search on L2
        $lambda = $options['l2'] ?? 1.0;
        $learningRate = $options['learning_rate'] ?? 0.1;
        $iterations = $options['iterations'] ?? 200;
        $maxGradNorm = $options['max_grad_norm'] ?? null;
        $earlyStoppingPatience = $options['early_stopping_patience'] ?? 5;
        $earlyStoppingMinDelta = $options['early_stopping_min_delta'] ?? 1e-4;

        $grid = $options['l2_grid'] ?? [0.001, 0.01, 0.1, 1.0, 10.0];
        if (!in_array($lambda, $grid, true)) {
            array_unshift($grid, $lambda);
        }

        $bestModel = null;
        $bestScore = -INF;
        $validationWeights = self::classWeights($yva);
        foreach ($grid as $l2) {
            $lr = new LogisticRegression($l2, $iterations, $learningRate, $maxGradNorm, $earlyStoppingPatience, $earlyStoppingMinDelta);
            $lr->setValidationData($Xva, $yva, $validationWeights);
            $lr->fit($Xtr, $ytr, $weights);
            $proba = $lr->predictProba($Xva);
            $pr = Metrics::prCurve($proba, $yva);
            $score = $pr['pr_auc'];
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestModel = $lr;
            }
        }

        if ($bestModel === null) {
            $bestModel = new LogisticRegression($lambda, $iterations, $learningRate, $maxGradNorm, $earlyStoppingPatience, $earlyStoppingMinDelta);
            $bestModel->setValidationData($Xva, $yva, $validationWeights);
            $bestModel->fit($Xtr, $ytr, $weights);
        }

        $minBins = $options['min_probability_bins'] ?? 3;
        $binRetryFactor = $options['l2_bin_retry_factor'] ?? 10.0;
        if ($binRetryFactor <= 1.0) {
            $binRetryFactor = 10.0;
        }
        $maxBinRetries = $options['max_bin_retries'] ?? 2;

        $attempt = 0;
        while ($attempt <= $maxBinRetries) {
            $proba = $bestModel->predictProba($Xva);
            $uniqueProba = array_values(array_unique($proba, SORT_NUMERIC));
            if (count($uniqueProba) >= $minBins) {
                break;
            }
            $attempt++;
            if ($attempt > $maxBinRetries) {
                break;
            }
            $lambda = max(1e-6, $lambda / $binRetryFactor);
            $bestModel = new LogisticRegression($lambda, $iterations, $learningRate, $maxGradNorm, $earlyStoppingPatience, $earlyStoppingMinDelta);
            $bestModel->setValidationData($Xva, $yva, $validationWeights);
            $bestModel->fit($Xtr, $ytr, $weights);
        }

        return [
            'model' => $bestModel,
            'hyperparameters' => [
                'model_type' => 'logistic_regression',
                'selected_l2' => $bestModel->getLambda(),
            ],
        ];
    }

    // Normalise the configuration object into an associative array so that missing
    // keys can fall back to sensible defaults without triggering Config::__get()
    // exceptions, config instances implement JsonSerializable, therefore they can
    // safely be converted back to their underlying arrays
    // @param object $modelConf
    // @return array<string,mixed>
    private static function normaliseConfig(object $modelConf): array
    {
        if ($modelConf instanceof \JsonSerializable) {
            $data = $modelConf->jsonSerialize();
            if (is_array($data)) {
                return $data;
            }
        }

        if ($modelConf instanceof \Traversable) {
            return iterator_to_array($modelConf);
        }

        if (method_exists($modelConf, '__toArray')) {
            // @var mixed $converted
            $converted = $modelConf->__toArray();
            if (is_array($converted)) {
                return $converted;
            }
        }

        // @var array<string,mixed> $properties
        $properties = get_object_vars($modelConf);

        return $properties;
    }
}
