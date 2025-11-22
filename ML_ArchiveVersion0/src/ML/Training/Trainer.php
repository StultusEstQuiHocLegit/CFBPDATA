<?php
// trainer.php
// Orchestrates model training, including class weighting and hyperparameter search.

declare(strict_types=1);

namespace App\ML\Training;

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
    public static function train(array $Xtr, array $ytr, array $Xva, array $yva, array $weights, object $modelConf): EstimatorInterface
    {
        $options = self::normaliseConfig($modelConf);

        $type = $options['type'] ?? 'logistic_regression';
        if ($type === 'random_forest') {
            $model = new RandomForest();
            $model->fit($Xtr, $ytr, $weights);
            return $model;
        }
        // logistic regression: grid search on L2
        $lambda = $modelConf->l2 ?? 1.0;
        $candidates = [$lambda];
        // If search space provided, include a few orders of magnitude
        $grid = [0.001, 0.01, 0.1, 1.0, 10.0];
        // evaluate each candidate
        $bestModel = null;
        $bestScore = -INF;
        foreach ($grid as $l2) {
            $lr = new LogisticRegression($l2, 200, 0.1);
            $lr->fit($Xtr, $ytr, $weights);
            $proba = $lr->predictProba($Xva);
            $pr = Metrics::prCurve($proba, $yva);
            $score = $pr['pr_auc'];
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestModel = $lr;
            }
        }
        return $bestModel ?? new LogisticRegression($lambda, 200, 0.1);
    }
}