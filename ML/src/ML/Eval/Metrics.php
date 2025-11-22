<?php
// metrics.php
// Provides a suite of evaluation metrics for binary classification.

declare(strict_types=1);

namespace App\ML\Eval;

// Metrics offers static functions to compute evaluation metrics such as precision/recall, PR-AUC, ROC-AUC, Brier score and calibration,
// these functions operate on arrays of predicted probabilities and ground truth binary labels
class Metrics
{
    // Compute Precision-Recall curve and its area under the curve,
    // returns pairs of recall and precision values, along with the PR-AUC
    // @param float[] $proba
    // @param int[] $y
    // @return array{recall: float[], precision: float[], thresholds: float[], pr_auc: float}
    public static function prCurve(array $proba, array $y): array
    {
        $n = count($proba);
        $indices = range(0, $n - 1);
        // sort probabilities descending
        usort($indices, function ($a, $b) use ($proba) {
            return $proba[$b] <=> $proba[$a];
        });
        $tp = 0;
        $fp = 0;
        $fn = array_sum($y);
        $prevProba = null;
        $recalls = [];
        $precisions = [];
        $thresholds = [];
        $lastRec = 0.0;
        $lastPrec = 1.0;
        $area = 0.0;
        foreach ($indices as $rank => $i) {
            if ($prevProba !== null && $proba[$i] != $prevProba) {
                // compute recall and precision at this threshold
                $rec = ($tp + $fn == 0) ? 0.0 : $tp / ($tp + $fn);
                $prec = ($tp + $fp == 0) ? 1.0 : $tp / ($tp + $fp);
                $recalls[] = $rec;
                $precisions[] = $prec;
                $thresholds[] = (float)$prevProba;
                // area using trapezoid rule
                $area += ($rec - $lastRec) * (($prec + $lastPrec) / 2.0);
                $lastRec = $rec;
                $lastPrec = $prec;
            }
            // update counts
            if ($y[$i] === 1) {
                $tp++;
                $fn--;
            } else {
                $fp++;
            }
            $prevProba = $proba[$i];
        }
        // final point
        $rec = ($tp + $fn == 0) ? 0.0 : $tp / ($tp + $fn);
        $prec = ($tp + $fp == 0) ? 1.0 : $tp / ($tp + $fp);
        $recalls[] = $rec;
        $precisions[] = $prec;
        $thresholds[] = 0.0;
        $area += ($rec - $lastRec) * (($prec + $lastPrec) / 2.0);
        return ['recall' => $recalls, 'precision' => $precisions, 'thresholds' => $thresholds, 'pr_auc' => $area];
    }

    // Compute ROC curve and AUC
    // @param float[] $proba
    // @param int[] $y
    // @return array{fpr: float[], tpr: float[], roc_auc: float}
    public static function rocCurve(array $proba, array $y): array
    {
        $n = count($proba);
        $indices = range(0, $n - 1);
        usort($indices, function ($a, $b) use ($proba) {
            return $proba[$b] <=> $proba[$a];
        });
        $p = array_sum($y);
        $nNeg = $n - $p;
        $tp = 0;
        $fp = 0;
        $prevProba = null;
        $tprs = [];
        $fprs = [];
        $lastTpr = 0.0;
        $lastFpr = 0.0;
        $area = 0.0;
        foreach ($indices as $i) {
            $tpOld = $tp;
            $fpOld = $fp;
            if ($y[$i] === 1) {
                $tp++;
            } else {
                $fp++;
            }
            // compute only when probability changes
            if ($prevProba === null || $proba[$i] != $prevProba) {
                $tpr = ($p == 0) ? 0.0 : $tpOld / $p;
                $fpr = ($nNeg == 0) ? 0.0 : $fpOld / $nNeg;
                $tprs[] = $tpr;
                $fprs[] = $fpr;
                $area += ($fpr - $lastFpr) * (($tpr + $lastTpr) / 2.0);
                $lastTpr = $tpr;
                $lastFpr = $fpr;
                $prevProba = $proba[$i];
            }
        }
        // final point at (1,1)
        $tprs[] = 1.0;
        $fprs[] = 1.0;
        $area += (1.0 - $lastFpr) * ((1.0 + $lastTpr) / 2.0);
        return ['fpr' => $fprs, 'tpr' => $tprs, 'roc_auc' => $area];
    }

    // Compute the Brier score (mean squared error of probabilities vs labels)
    // @param float[] $proba
    // @param int[] $y
    public static function brier(array $proba, array $y): float
    {
        $n = count($proba);
        if ($n === 0) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($proba as $i => $p) {
            $sum += ($p - $y[$i]) ** 2;
        }
        return $sum / $n;
    }

    // Compute reliability curve bins by partitioning the probability range into equally sized quantile buckets (by count),
    // returns average predicted vs. observed bankruptcy rate per bin so calibration quality can be visualised
    // @param float[] $proba
    // @param int[] $y
    // @param int $bins
    // @return array<int, array{bin:int, lower:float, upper:float, count:int, avg_pred:float, emp_rate:float}>
    public static function reliabilityCurve(array $proba, array $y, int $bins = 10): array
    {
        $n = count($proba);
        if ($n === 0 || $bins <= 0) {
            return [];
        }
        $paired = [];
        foreach ($proba as $i => $p) {
            $prob = max(0.0, min(1.0, (float) $p));
            $label = isset($y[$i]) ? (int) $y[$i] : 0;
            $paired[] = ['p' => $prob, 'y' => $label];
        }
        usort($paired, static function ($a, $b) {
            if ($a['p'] === $b['p']) {
                return 0;
            }
            return ($a['p'] < $b['p']) ? -1 : 1;
        });
        $binSize = (int) ceil($n / $bins);
        $result = [];
        for ($b = 0; $b < $bins; $b++) {
            $start = $b * $binSize;
            if ($start >= $n) {
                break;
            }
            $end = min($n, ($b + 1) * $binSize);
            $slice = array_slice($paired, $start, $end - $start);
            $count = count($slice);
            if ($count === 0) {
                continue;
            }
            $sumPred = 0.0;
            $sumObs = 0.0;
            foreach ($slice as $row) {
                $sumPred += $row['p'];
                $sumObs += $row['y'];
            }
            $result[] = [
                'bin' => $b + 1,
                'lower' => $slice[0]['p'],
                'upper' => $slice[$count - 1]['p'],
                'count' => $count,
                'avg_pred' => $sumPred / $count,
                'emp_rate' => $sumObs / $count,
            ];
        }
        return $result;
    }

    // Compute confusion matrix at a given threshold and derived metrics
    // @param float[] $proba
    // @param int[] $y
    // @param float $threshold
    // @return array{TP:int, FP:int, TN:int, FN:int, precision:float, recall:float, f1:float}
    public static function confusion(array $proba, array $y, float $threshold): array
    {
        $tp = $fp = $tn = $fn = 0;
        foreach ($proba as $i => $p) {
            $pred = ($p >= $threshold) ? 1 : 0;
            if ($pred === 1 && $y[$i] === 1) {
                $tp++;
            } elseif ($pred === 1 && $y[$i] === 0) {
                $fp++;
            } elseif ($pred === 0 && $y[$i] === 1) {
                $fn++;
            } else {
                $tn++;
            }
        }
        $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0.0;
        $recall    = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;
        $f1        = ($precision + $recall) > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0;
        return [
            'TP' => $tp,
            'FP' => $fp,
            'TN' => $tn,
            'FN' => $fn,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $f1,
        ];
    }
}
