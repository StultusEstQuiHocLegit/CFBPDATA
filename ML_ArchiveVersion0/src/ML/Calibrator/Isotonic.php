<?php
// isotonic.php
// Implements a simple isotonic regression calibrator for probability outputs.

declare(strict_types=1);

namespace App\ML\Calibrator;

// The Isotonic class fits a non-decreasing stepwise function mapping raw probabilities to calibrated probabilities using the pool adjacent violators algorithm,
// this improves probability calibration on validation data, the function is stored as pairs of (threshold, value) and applied by linear interpolation
class Isotonic
{
    // @var float[] Sorted thresholds
    private array $thresholds = [];
    // @var float[] Fitted outputs for each threshold
    private array $values = [];

    // Fit the isotonic calibrator using raw probabilities and binary labels
    // @param float[] $proba
    // @param int[] $y
    // @return self
    public static function fit(array $proba, array $y): self
    {
        $n = count($proba);
        if ($n === 0) {
            return new self();
        }
        // sort by predicted probability
        $indices = range(0, $n - 1);
        usort($indices, function ($a, $b) use ($proba) {
            return $proba[$a] <=> $proba[$b];
        });
        $sortedP = [];
        $sortedY = [];
        foreach ($indices as $i) {
            $sortedP[] = $proba[$i];
            $sortedY[] = $y[$i];
        }
        // initialize blocks: each point is a block with sum y and count
        $blocks = [];
        for ($i = 0; $i < $n; $i++) {
            $blocks[] = [
                'sum' => $sortedY[$i],
                'count' => 1,
                'minP' => $sortedP[$i],
                'maxP' => $sortedP[$i],
            ];
            // merge adjacent blocks if monotonicity violated
            $m = count($blocks);
            while ($m >= 2) {
                $last = $blocks[$m - 1];
                $prev = $blocks[$m - 2];
                $avgLast = $last['sum'] / $last['count'];
                $avgPrev = $prev['sum'] / $prev['count'];
                if ($avgPrev <= $avgLast) {
                    break;
                }
                // merge
                $merged = [
                    'sum' => $prev['sum'] + $last['sum'],
                    'count' => $prev['count'] + $last['count'],
                    'minP' => $prev['minP'],
                    'maxP' => $last['maxP'],
                ];
                array_splice($blocks, $m - 2, 2, [$merged]);
                $m = count($blocks);
            }
        }
        // build thresholds and values arrays from blocks
        $thresholds = [];
        $values = [];
        foreach ($blocks as $block) {
            $avg = $block['sum'] / $block['count'];
            $thresholds[] = $block['maxP'];
            $values[] = $avg;
        }
        // Ensure thresholds start at 0 and end at 1
        if (reset($thresholds) > 0.0) {
            array_unshift($thresholds, 0.0);
            array_unshift($values, $values[0]);
        }
        if (end($thresholds) < 1.0) {
            $lastVal = end($values);
            $thresholds[] = 1.0;
            $values[] = $lastVal;
        }
        $iso = new self();
        $iso->thresholds = $thresholds;
        $iso->values = $values;
        return $iso;
    }

    // Apply the isotonic calibrator to raw probabilities,
    // for each probability, find the enclosing thresholds and linearly interpolate the calibrated value
    // @param float[] $proba
    // @return float[]
    public function apply(array $proba): array
    {
        $calibrated = [];
        $nThresh = count($this->thresholds);
        foreach ($proba as $p) {
            // clamp to [0,1]
            $p = max(0.0, min(1.0, $p));
            // find interval
            $val = $this->values[0];
            for ($i = 1; $i < $nThresh; $i++) {
                if ($p <= $this->thresholds[$i]) {
                    $t0 = $this->thresholds[$i - 1];
                    $t1 = $this->thresholds[$i];
                    $v0 = $this->values[$i - 1];
                    $v1 = $this->values[$i];
                    if ($t1 == $t0) {
                        $val = $v1;
                    } else {
                        $ratio = ($p - $t0) / ($t1 - $t0);
                        $val = $v0 + ($v1 - $v0) * $ratio;
                    }
                    break;
                }
            }
            $calibrated[] = $val;
        }
        return $calibrated;
    }

    // Persist calibrator to disk
    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'thresholds' => $this->thresholds,
            'values' => $this->values,
        ]));
    }

    // Load a calibrator from disk
    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self();
        $obj->thresholds = $data['thresholds'];
        $obj->values = $data['values'];
        return $obj;
    }
}