<?php
// Trainer.php
// Orchestrates model training, including class weighting, hyperparameter search and cross-validation.

declare(strict_types=1);

namespace App\ML\Training;

use App\ML\EstimatorInterface;
use App\ML\Models\GradientBoosting;
use App\ML\Models\LogisticRegression;
use App\ML\Models\RandomForest;
use App\ML\Eval\Metrics;

class Trainer
{
    // Compute inverse-frequency class weights
    // @param int[] $y
    // @return float[]
    public static function classWeights(array $y): array
    {
        $n = count($y);
        if ($n === 0) {
            return [];
        }
        $pos = array_sum($y);
        $neg = $n - $pos;
        $wPos = ($pos > 0) ? ($n / (2.0 * $pos)) : 1.0;
        $wNeg = ($neg > 0) ? ($n / (2.0 * $neg)) : 1.0;
        $weights = [];
        foreach ($y as $label) {
            $weights[] = ($label === 1) ? $wPos : $wNeg;
        }
        return $weights;
    }

    // Train a model using a single validation split
    // @param array<int, array<float>> $Xtr
    // @param int[] $ytr
    // @param array<int, array<float>> $Xva
    // @param int[] $yva
    // @param float[] $weights
    // @param object $modelConf
    // @return array{model: EstimatorInterface, hyperparameters: array<string, mixed>}
    public static function train(
        array $Xtr,
        array $ytr,
        array $Xva,
        array $yva,
        array $weights,
        object $modelConf,
        string $metric = 'pr_auc'
    ): array {
        $options = self::normaliseConfig($modelConf);
        $type = strtolower((string) ($options['type'] ?? 'logistic_regression'));
        if ($type === 'random_forest') {
            return self::trainEnsemble($type, $Xtr, $ytr, $Xva, $yva, $weights, $options, $metric);
        }
        if ($type === 'gradient_boosting') {
            return self::trainEnsemble($type, $Xtr, $ytr, $Xva, $yva, $weights, $options, $metric);
        }
        return self::trainLogistic($Xtr, $ytr, $Xva, $yva, $weights, $options, $metric);
    }

    // Perform grouped temporal cross-validation and train the best model on all data
    // @param array<int, array<float>> $X
    // @param int[] $y
    // @param array<int, array<string, mixed>> $folds
    // @param object $modelConf
    // @return array{model: EstimatorInterface, hyperparameters: array<string, mixed>}
    public static function crossValidateAndTrain(
        array $X,
        array $y,
        array $folds,
        object $modelConf,
        string $metric = 'pr_auc',
        ?array $sampleWeights = null
    ): array {
        $options = self::normaliseConfig($modelConf);
        $type = strtolower((string) ($options['type'] ?? 'logistic_regression'));
        $grid = self::hyperparameterGrid($type, $options);
        $bestParams = null;
        $bestScore = -INF;
        $bestPerFold = [];
        foreach ($grid as $params) {
            $scores = [];
            foreach ($folds as $fold) {
                $trainIdx = $fold['train'] ?? [];
                $validIdx = $fold['valid'] ?? [];
                if (empty($trainIdx) || empty($validIdx)) {
                    continue;
                }
                $Xtrain = self::subsetRows($X, $trainIdx);
                $ytrain = self::subsetRows($y, $trainIdx);
                $Xvalid = self::subsetRows($X, $validIdx);
                $yvalid = self::subsetRows($y, $validIdx);
                $foldWeights = self::classWeights($ytrain);
                $model = self::instantiateModel($type, $params, $options);
                if ($model instanceof LogisticRegression) {
                    $validationWeights = self::classWeights($yvalid);
                    $model->setValidationData($Xvalid, $yvalid, $validationWeights);
                }
                $model->fit($Xtrain, $ytrain, $foldWeights);
                $proba = $model->predictProba($Xvalid);
                $scores[] = self::metricScore($yvalid, $proba, $metric);
            }
            if (empty($scores)) {
                continue;
            }
            $avgScore = array_sum($scores) / count($scores);
            if ($avgScore > $bestScore) {
                $bestScore = $avgScore;
                $bestParams = $params;
                $bestPerFold = $scores;
            }
        }
        if ($bestParams === null) {
            $bestParams = $grid[0];
            $bestPerFold = [];
        }
        $fullWeights = $sampleWeights ?? self::classWeights($y);
        if ($type === 'logistic_regression') {
            $finalOptions = $options;
            $logisticFinal = self::configSection($options, 'logistic_regression');
            $selectedL2 = $bestParams['l2'] ?? ($logisticFinal['l2'] ?? ($options['l2'] ?? 0.1));
            $logisticFinal['l2'] = $selectedL2;
            $logisticFinal['l2_grid'] = [$selectedL2];
            $finalOptions['logistic_regression'] = $logisticFinal;
            $result = self::trainLogistic($X, $y, $X, $y, $fullWeights, $finalOptions, $metric, false);
            $hyper = $result['hyperparameters'];
            $hyper['cv_metric'] = $bestScore;
            $hyper['cv_per_fold'] = $bestPerFold;
            $hyper['cv_folds'] = count($folds);
            return [
                'model' => $result['model'],
                'hyperparameters' => $hyper,
            ];
        }
        $model = self::instantiateModel($type, $bestParams, $options);
        $model->fit($X, $y, $fullWeights);
        $hyperparameters = self::formatHyperparameters($type, $bestParams, $options);
        $hyperparameters['cv_metric'] = $bestScore;
        $hyperparameters['cv_per_fold'] = $bestPerFold;
        $hyperparameters['cv_folds'] = count($folds);
        return [
            'model' => $model,
            'hyperparameters' => $hyperparameters,
        ];
    }

    // @param array<int, array<float>>|int[] $data
    // @param int[] $indices
    // @return array<int, mixed>
    private static function subsetRows(array $data, array $indices): array
    {
        $subset = [];
        foreach ($indices as $idx) {
            $subset[] = $data[$idx];
        }
        return $subset;
    }

    // @param array<int, array<float>> $Xtr
    // @param int[] $ytr
    // @param array<int, array<float>> $Xva
    // @param int[] $yva
    // @param float[] $weights
    // @param array<string, mixed> $options
    // @param string $metric
    // @param bool $useValidation
    // @return array{model: EstimatorInterface, hyperparameters: array<string, mixed>}
    private static function trainLogistic(
        array $Xtr,
        array $ytr,
        array $Xva,
        array $yva,
        array $weights,
        array $options,
        string $metric,
        bool $useValidation = true
    ): array {
        $logisticOptions = self::configSection($options, 'logistic_regression');
        $lambda = (float) ($logisticOptions['l2'] ?? $options['l2'] ?? 0.1);
        $iterations = (int) ($logisticOptions['iterations'] ?? $options['iterations'] ?? 200);
        $learningRate = (float) ($logisticOptions['learning_rate'] ?? $options['learning_rate'] ?? 0.1);
        $maxGradNorm = $logisticOptions['max_grad_norm'] ?? $options['max_grad_norm'] ?? null;
        $earlyStoppingPatience = (int) ($logisticOptions['early_stopping_patience'] ?? $options['early_stopping_patience'] ?? 5);
        $earlyStoppingMinDelta = (float) ($logisticOptions['early_stopping_min_delta'] ?? $options['early_stopping_min_delta'] ?? 1e-4);
        $grid = $logisticOptions['l2_grid'] ?? ($options['l2_grid'] ?? [$lambda]);
        if (!is_array($grid)) {
            $grid = [$grid];
        }
        if (!in_array($lambda, $grid, true)) {
            $grid[] = $lambda;
        }
        $bestModel = null;
        $bestScore = -INF;
        $bestLambda = $lambda;
        $validationWeights = self::classWeights($yva);
        foreach ($grid as $candidate) {
            $lr = new LogisticRegression(
                (float) $candidate,
                $iterations,
                $learningRate,
                is_numeric($maxGradNorm) ? (float) $maxGradNorm : null,
                $earlyStoppingPatience,
                $earlyStoppingMinDelta
            );
            if ($useValidation) {
                $lr->setValidationData($Xva, $yva, $validationWeights);
            }
            $lr->fit($Xtr, $ytr, $weights);
            if ($useValidation) {
                $proba = $lr->predictProba($Xva);
                $score = self::metricScore($yva, $proba, $metric);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestModel = $lr;
                    $bestLambda = (float) $candidate;
                }
            } else {
                $bestModel = $lr;
                $bestLambda = (float) $candidate;
                break;
            }
        }
        if ($bestModel === null) {
            $bestModel = new LogisticRegression($lambda, $iterations, $learningRate, is_numeric($maxGradNorm) ? (float) $maxGradNorm : null, $earlyStoppingPatience, $earlyStoppingMinDelta);
            if ($useValidation) {
                $bestModel->setValidationData($Xva, $yva, $validationWeights);
            }
            $bestModel->fit($Xtr, $ytr, $weights);
        }
        $minBins = (int) ($logisticOptions['min_probability_bins'] ?? $options['min_probability_bins'] ?? 3);
        $binRetryFactor = (float) ($logisticOptions['l2_bin_retry_factor'] ?? $options['l2_bin_retry_factor'] ?? 10.0);
        if ($binRetryFactor <= 1.0) {
            $binRetryFactor = 10.0;
        }
        $maxBinRetries = (int) ($logisticOptions['max_bin_retries'] ?? $options['max_bin_retries'] ?? 2);
        $attempt = 0;
        while ($useValidation && $attempt <= $maxBinRetries) {
            $proba = $bestModel->predictProba($Xva);
            $uniqueProba = array_values(array_unique($proba, SORT_NUMERIC));
            if (count($uniqueProba) >= $minBins) {
                break;
            }
            $attempt++;
            if ($attempt > $maxBinRetries) {
                break;
            }
            $bestLambda = max(1e-6, $bestLambda / $binRetryFactor);
            $bestModel = new LogisticRegression(
                $bestLambda,
                $iterations,
                $learningRate,
                is_numeric($maxGradNorm) ? (float) $maxGradNorm : null,
                $earlyStoppingPatience,
                $earlyStoppingMinDelta
            );
            $bestModel->setValidationData($Xva, $yva, $validationWeights);
            $bestModel->fit($Xtr, $ytr, $weights);
        }
        return [
            'model' => $bestModel,
            'hyperparameters' => [
                'model_type' => 'logistic_regression',
                'selected_l2' => $bestLambda,
            ],
        ];
    }

    // @param string $type
    // @param array<int, array<float>> $Xtr
    // @param int[] $ytr
    // @param array<int, array<float>> $Xva
    // @param int[] $yva
    // @param float[] $weights
    // @param array<string, mixed> $options
    // @param string $metric
    // @return array{model: EstimatorInterface, hyperparameters: array<string, mixed>}
    private static function trainEnsemble(
        string $type,
        array $Xtr,
        array $ytr,
        array $Xva,
        array $yva,
        array $weights,
        array $options,
        string $metric
    ): array {
        $grid = self::hyperparameterGrid($type, $options);
        $bestModel = null;
        $bestScore = -INF;
        $bestParams = $grid[0];
        foreach ($grid as $params) {
            $model = self::instantiateModel($type, $params, $options);
            $model->fit($Xtr, $ytr, $weights);
            $proba = $model->predictProba($Xva);
            $score = self::metricScore($yva, $proba, $metric);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestModel = $model;
                $bestParams = $params;
            }
        }
        if ($bestModel === null) {
            $bestModel = self::instantiateModel($type, $bestParams, $options);
            $bestModel->fit($Xtr, $ytr, $weights);
        }
        $hyper = self::formatHyperparameters($type, $bestParams, $options);
        $hyper['validation_metric'] = $bestScore;
        return [
            'model' => $bestModel,
            'hyperparameters' => $hyper,
        ];
    }

    // @param string $type
    // @param array<string, mixed> $params
    // @param array<string, mixed> $options
    private static function instantiateModel(string $type, array $params, array $options): EstimatorInterface
    {
        if ($type === 'random_forest') {
            $rf = self::configSection($options, 'random_forest');
            return new RandomForest(
                (int) ($params['num_trees'] ?? $rf['num_trees'] ?? 100),
                (int) ($params['max_depth'] ?? $rf['max_depth'] ?? 5),
                (int) ($params['min_samples_split'] ?? $rf['min_samples_split'] ?? 2),
                (float) ($params['subsample'] ?? $rf['subsample'] ?? 0.8),
                (float) ($params['feature_fraction'] ?? $rf['feature_fraction'] ?? 0.8)
            );
        }
        if ($type === 'gradient_boosting') {
            $gb = self::configSection($options, 'gradient_boosting');
            return new GradientBoosting(
                (int) ($params['num_trees'] ?? $gb['num_trees'] ?? 100),
                (float) ($params['learning_rate'] ?? $gb['learning_rate'] ?? 0.1),
                (int) ($params['max_depth'] ?? $gb['max_depth'] ?? 3)
            );
        }
        $logistic = self::configSection($options, 'logistic_regression');
        return new LogisticRegression(
            (float) ($params['l2'] ?? $logistic['l2'] ?? $options['l2'] ?? 0.1),
            (int) ($logistic['iterations'] ?? $options['iterations'] ?? 200),
            (float) ($logistic['learning_rate'] ?? $options['learning_rate'] ?? 0.1),
            isset($logistic['max_grad_norm']) ? (float) $logistic['max_grad_norm'] : (isset($options['max_grad_norm']) ? (float) $options['max_grad_norm'] : null),
            (int) ($logistic['early_stopping_patience'] ?? $options['early_stopping_patience'] ?? 5),
            (float) ($logistic['early_stopping_min_delta'] ?? $options['early_stopping_min_delta'] ?? 1e-4)
        );
    }

    // @param string $type
    // @param array<string, mixed> $options
    // @return array<int, array<string, mixed>>
    private static function hyperparameterGrid(string $type, array $options): array
    {
        if ($type === 'random_forest') {
            $rf = self::configSection($options, 'random_forest');
            $numTrees = $rf['num_trees_grid'] ?? [$rf['num_trees'] ?? 100];
            if (!is_array($numTrees)) {
                $numTrees = [$numTrees];
            }
            $maxDepth = $rf['max_depth_grid'] ?? [$rf['max_depth'] ?? 5];
            if (!is_array($maxDepth)) {
                $maxDepth = [$maxDepth];
            }
            $minSplit = $rf['min_samples_split_grid'] ?? [$rf['min_samples_split'] ?? 2];
            if (!is_array($minSplit)) {
                $minSplit = [$minSplit];
            }
            $subsample = $rf['subsample'] ?? 0.8;
            $featureFraction = $rf['feature_fraction'] ?? 0.8;
            $grid = [];
            foreach ($numTrees as $n) {
                foreach ($maxDepth as $depth) {
                    foreach ($minSplit as $split) {
                        $grid[] = [
                            'num_trees' => (int) $n,
                            'max_depth' => (int) $depth,
                            'min_samples_split' => (int) $split,
                            'subsample' => (float) $subsample,
                            'feature_fraction' => (float) $featureFraction,
                        ];
                    }
                }
            }
            return $grid;
        }
        if ($type === 'gradient_boosting') {
            $gb = self::configSection($options, 'gradient_boosting');
            $numTrees = $gb['num_trees_grid'] ?? [$gb['num_trees'] ?? 100];
            if (!is_array($numTrees)) {
                $numTrees = [$numTrees];
            }
            $learningRates = $gb['learning_rate_grid'] ?? [$gb['learning_rate'] ?? 0.1];
            if (!is_array($learningRates)) {
                $learningRates = [$learningRates];
            }
            $maxDepth = $gb['max_depth_grid'] ?? [$gb['max_depth'] ?? 3];
            if (!is_array($maxDepth)) {
                $maxDepth = [$maxDepth];
            }
            $grid = [];
            foreach ($numTrees as $n) {
                foreach ($learningRates as $lr) {
                    foreach ($maxDepth as $depth) {
                        $grid[] = [
                            'num_trees' => (int) $n,
                            'learning_rate' => (float) $lr,
                            'max_depth' => (int) $depth,
                        ];
                    }
                }
            }
            return $grid;
        }
        $logistic = self::configSection($options, 'logistic_regression');
        $l2Grid = $logistic['l2_grid'] ?? ($options['l2_grid'] ?? [$logistic['l2'] ?? $options['l2'] ?? 0.1]);
        if (!is_array($l2Grid)) {
            $l2Grid = [$l2Grid];
        }
        $grid = [];
        foreach ($l2Grid as $lambda) {
            $grid[] = ['l2' => (float) $lambda];
        }
        return $grid;
    }

    // @param string $type
    // @param array<string, mixed> $params
    // @param array<string, mixed> $options
    // @return array<string, mixed>
    private static function formatHyperparameters(string $type, array $params, array $options): array
    {
        $result = ['model_type' => $type];
        if ($type === 'random_forest') {
            $rf = self::configSection($options, 'random_forest');
            $result['selected_num_trees'] = (int) ($params['num_trees'] ?? $rf['num_trees'] ?? 100);
            $result['selected_max_depth'] = (int) ($params['max_depth'] ?? $rf['max_depth'] ?? 5);
            $result['selected_min_samples_split'] = (int) ($params['min_samples_split'] ?? $rf['min_samples_split'] ?? 2);
            $result['selected_subsample'] = (float) ($params['subsample'] ?? $rf['subsample'] ?? 0.8);
            $result['selected_feature_fraction'] = (float) ($params['feature_fraction'] ?? $rf['feature_fraction'] ?? 0.8);
            return $result;
        }
        if ($type === 'gradient_boosting') {
            $gb = self::configSection($options, 'gradient_boosting');
            $result['selected_num_trees'] = (int) ($params['num_trees'] ?? $gb['num_trees'] ?? 100);
            $result['selected_learning_rate'] = (float) ($params['learning_rate'] ?? $gb['learning_rate'] ?? 0.1);
            $result['selected_max_depth'] = (int) ($params['max_depth'] ?? $gb['max_depth'] ?? 3);
            return $result;
        }
        $logistic = self::configSection($options, 'logistic_regression');
        $result['selected_l2'] = (float) ($params['l2'] ?? $logistic['l2'] ?? $options['l2'] ?? 0.1);
        return $result;
    }

    // @param int[] $y
    // @param float[] $proba
    private static function metricScore(array $y, array $proba, string $metric): float
    {
        if ($metric === 'roc_auc') {
            $roc = Metrics::rocCurve($proba, $y);
            return (float) ($roc['roc_auc'] ?? 0.0);
        }
        $pr = Metrics::prCurve($proba, $y);
        return (float) ($pr['pr_auc'] ?? 0.0);
    }

    // @param object $modelConf
    // @return array<string, mixed>
    private static function normaliseConfig(object $modelConf): array
    {
        if ($modelConf instanceof \JsonSerializable) {
            $data = $modelConf->jsonSerialize();
            $converted = self::convertToArray($data);
            if (is_array($converted)) {
                return $converted;
            }
        }
        if ($modelConf instanceof \Traversable) {
            return self::convertToArray(iterator_to_array($modelConf));
        }
        if (method_exists($modelConf, '__toArray')) {
            $converted = $modelConf->__toArray();
            $converted = self::convertToArray($converted);
            if (is_array($converted)) {
                return $converted;
            }
        }
        return self::convertToArray(get_object_vars($modelConf));
    }

    // Recursively convert configuration objects into arrays
    // @param mixed $value
    // @return mixed
    private static function convertToArray($value)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = self::convertToArray($item);
            }
            return $result;
        }
        if (is_object($value)) {
            return self::convertToArray(get_object_vars($value));
        }
        return $value;
    }

    // Safely extract a configuration subsection, handling array/stdClass interchangeably
    // @param array<string, mixed> $options
    // @return array<string, mixed>
    private static function configSection(array $options, string $section): array
    {
        if (!array_key_exists($section, $options)) {
            return [];
        }
        $value = self::convertToArray($options[$section]);
        return is_array($value) ? $value : [];
    }
}
