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
    // @return array{recall: float[], precision: float[], pr_auc: float}
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
        $area += ($rec - $lastRec) * (($prec + $lastPrec) / 2.0);
        return ['recall' => $recalls, 'precision' => $precisions, 'pr_auc' => $area];
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