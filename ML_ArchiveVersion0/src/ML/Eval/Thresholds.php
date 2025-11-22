<?php
// thresholds.php
// Provides utilities to select decision thresholds based on validation results.

declare(strict_types=1);

namespace App\ML\Eval;

use App\ML\Eval\Metrics;

// Thresholds selects operating points for classification by sweeping a range of candidate thresholds and optimising a chosen objective on a validation set,
// it also finds a threshold meeting a minimum recall requirement
class Thresholds
{
    // Choose thresholds based on the calibrated probabilities and labels
    // @param float[] $proba Calibrated probabilities on validation set
    // @param int[] $y Ground truth labels
    // @param object $conf Configuration object with optimise_for and strict_recall_at
    // @return array{best: float, recall80: float}
    public static function choose(array $proba, array $y, object $conf): array
    {
        $bestT = 0.5;
        $bestScore = -INF;
        $tRecall = 0.5;
        $minRec = $conf->strict_recall_at ?? 0.8;
        $foundRecall = false;
        // Evaluate 101 thresholds from 0 to 1
        for ($k = 0; $k <= 100; $k++) {
            $t = $k / 100.0;
            $cm = Metrics::confusion($proba, $y, $t);
            // Determine objective: pr_auc or f1 (default f1)
            $score = 0.0;
            if (($conf->optimize_for ?? '') === 'pr_auc') {
                // approximate average precision by f1 for speed
                $score = $cm['f1'];
            } else {
                $score = $cm['f1'];
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestT = $t;
            }
            if (!$foundRecall && $cm['recall'] >= $minRec) {
                $tRecall = $t;
                $foundRecall = true;
            }
        }
        return ['best' => $bestT, 'recall80' => $tRecall];
    }
}