<?php
// thresholds.php
// Provides utilities to select decision thresholds based on validation results.

declare(strict_types=1);

namespace App\ML\Eval;

use App\ML\Eval\Metrics;

// Thresholds selects operating points for classification by sweeping a range of candidate thresholds and optimising a chosen objective on a validation set.
class Thresholds
{
    // Choose thresholds based on the calibrated probabilities and labels
    // @param float[] $proba Calibrated probabilities on validation set
    // @param int[] $y Ground truth labels
    // @param object $conf Configuration object with optimise_for and strict_recall_at
    // @return array{primary: array<string,mixed>, f1_max: array<string,mixed>, recall_target: array<string,mixed>}
    public static function choose(array $proba, array $y, object $conf): array
    {
        $optimizeFor = $conf->optimize_for ?? 'f1';
        $targetRecall = $conf->strict_recall_at ?? 0.8;
        $eps = 1e-9;

        $candidates = self::candidateThresholds($proba);
        $points = [];
        foreach ($candidates as $idx => $threshold) {
            $cm = Metrics::confusion($proba, $y, $threshold);
            $point = self::formatPoint($threshold, $cm);
            $point['threshold_index'] = $idx;
            $points[] = $point;
        }

        if (empty($points)) {
            $fallback = self::formatPoint(0.5, Metrics::confusion($proba, $y, 0.5));
            $fallback['target_recall'] = $targetRecall;
            return [
                'primary' => $fallback,
                'f1_max' => $fallback,
                'recall_target' => $fallback,
            ];
        }

        $f1Max = $points[0];
        foreach ($points as $point) {
            if (
                $point['f1'] > $f1Max['f1'] + $eps ||
                (abs($point['f1'] - $f1Max['f1']) <= $eps && (
                    $point['precision'] > $f1Max['precision'] + $eps ||
                    (abs($point['precision'] - $f1Max['precision']) <= $eps && $point['threshold'] > $f1Max['threshold'] + $eps)
                ))
            ) {
                $f1Max = $point;
            }
        }

        $strictPoint = null;
        $strictIndex = null;
        foreach ($points as $point) {
            if ($point['recall'] + $eps < $targetRecall) {
                continue;
            }
            if ($strictPoint === null) {
                $strictPoint = $point;
                $strictIndex = $point['threshold_index'] ?? null;
                continue;
            }
            if (
                $point['threshold'] > $strictPoint['threshold'] + $eps ||
                (abs($point['threshold'] - $strictPoint['threshold']) <= $eps && (
                    $point['precision'] > $strictPoint['precision'] + $eps ||
                    (abs($point['precision'] - $strictPoint['precision']) <= $eps && $point['f1'] > $strictPoint['f1'] + $eps)
                ))
            ) {
                $strictPoint = $point;
                $strictIndex = $point['threshold_index'] ?? null;
            }
        }
        if ($strictPoint === null) {
            $strictPoint = $points[0];
            $strictIndex = $strictPoint['threshold_index'] ?? null;
            foreach ($points as $point) {
                if (
                    $point['recall'] > $strictPoint['recall'] + $eps ||
                    (abs($point['recall'] - $strictPoint['recall']) <= $eps && $point['threshold'] < $strictPoint['threshold'] - $eps)
                ) {
                    $strictPoint = $point;
                    $strictIndex = $point['threshold_index'] ?? null;
                }
            }
        }
        $strictPoint['target_recall'] = $targetRecall;
        $strictPoint['threshold_index'] = $strictIndex;

        $primary = $f1Max;
        if ($optimizeFor === 'pr_auc') {
            $primary = self::bestByAveragePrecision($proba, $y, $points, $eps);
        }

        return [
            'primary' => $primary,
            'f1_max' => $f1Max,
            'recall_target' => $strictPoint,
        ];
    }

    // @param float[] $proba
    // @return float[]
    private static function candidateThresholds(array $proba): array
    {
        $unique = array_values(array_unique(array_map(static function ($p) {
            return max(0.0, min(1.0, (float) $p));
        }, $proba), SORT_NUMERIC));
        $unique[] = 0.0;
        $unique[] = 1.0;
        $unique = array_values(array_unique($unique, SORT_NUMERIC));
        sort($unique);
        return $unique;
    }

    // @param float $threshold
    // @param array{TP:int, FP:int, TN:int, FN:int, precision:float, recall:float, f1:float} $cm
    // @return array<string, mixed>
    private static function formatPoint(float $threshold, array $cm): array
    {
        return [
            'threshold' => max(0.0, min(1.0, $threshold)),
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
    }

    // @param float[] $proba
    // @param int[] $y
    // @param array<int, array<string, mixed>> $points
    private static function bestByAveragePrecision(array $proba, array $y, array $points, float $eps): array
    {
        $curve = Metrics::prCurve($proba, $y);
        $prevRec = 0.0;
        $prevPrec = 1.0;
        $bestScore = -INF;
        $best = $points[0];
        foreach ($curve['thresholds'] as $idx => $threshold) {
            $rec = $curve['recall'][$idx];
            $prec = $curve['precision'][$idx];
            $deltaRec = $rec - $prevRec;
            if ($deltaRec < 0) {
                $deltaRec = 0.0;
            }
            $avgPrecision = $deltaRec > 0 ? ($prec + $prevPrec) / 2.0 : $prec;
            $matching = self::findNearestPoint($points, $threshold);
            if (
                $avgPrecision > $bestScore + $eps ||
                (abs($avgPrecision - $bestScore) <= $eps && (
                    $matching['precision'] > $best['precision'] + $eps ||
                    (abs($matching['precision'] - $best['precision']) <= $eps && $matching['f1'] > $best['f1'] + $eps)
                ))
            ) {
                $bestScore = $avgPrecision;
                $best = $matching;
            }
            $prevRec = $rec;
            $prevPrec = $prec;
        }
        return $best;
    }

    // @param array<int, array<string, mixed>> $points
    // @return array<string, mixed>
    private static function findNearestPoint(array $points, float $threshold): array
    {
        $closest = $points[0];
        $bestDiff = abs($points[0]['threshold'] - $threshold);
        foreach ($points as $point) {
            $diff = abs($point['threshold'] - $threshold);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $closest = $point;
            }
        }
        return $closest;
    }
}
