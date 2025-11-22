<?php
// Platt.php
// Implements Platt scaling (logistic calibration) for probability outputs.

declare(strict_types=1);

namespace App\ML\Calibrator;

// Platt fits a logistic function mapping raw model scores to calibrated probabilities,
// parameters are optimised using a few Newton-Raphson steps on the log-likelihood of the validation set
class Platt
{
    private float $a;

    private float $b;

    private function __construct(float $a, float $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    // @param float[] $scores
    // @param int[] $y
    public static function fit(array $scores, array $y): self
    {
        $n = count($scores);
        if ($n === 0) {
            return new self(1.0, 0.0);
        }
        $pos = array_sum($y);
        $neg = $n - $pos;
        $a = 0.0;
        $b = log(($pos + 1.0) / ($neg + 1.0));

        for ($iter = 0; $iter < 50; $iter++) {
            $gradA = 0.0;
            $gradB = 0.0;
            $hAA = 0.0;
            $hAB = 0.0;
            $hBB = 0.0;
            foreach ($scores as $idx => $score) {
                $s = $a * $score + $b;
                // Clamp to avoid overflow
                if ($s > 20.0) {
                    $s = 20.0;
                } elseif ($s < -20.0) {
                    $s = -20.0;
                }
                $p = 1.0 / (1.0 + exp(-$s));
                $diff = $p - $y[$idx];
                $gradA += $diff * $score;
                $gradB += $diff;
                $w = $p * (1.0 - $p);
                $hAA += $w * $score * $score;
                $hAB += $w * $score;
                $hBB += $w;
            }
            $hAA += 1e-6;
            $hBB += 1e-6;
            $det = $hAA * $hBB - $hAB * $hAB;
            if (abs($det) < 1e-12) {
                break;
            }
            $deltaA = ($gradA * $hBB - $gradB * $hAB) / $det;
            $deltaB = ($gradB * $hAA - $gradA * $hAB) / $det;
            $a -= $deltaA;
            $b -= $deltaB;
            if (max(abs($deltaA), abs($deltaB)) < 1e-6) {
                break;
            }
        }

        return new self($a, $b);
    }

    // @param float[] $scores
    // @return float[]
    public function apply(array $scores): array
    {
        $calibrated = [];
        foreach ($scores as $score) {
            $s = $this->a * $score + $this->b;
            if ($s > 20.0) {
                $s = 20.0;
            } elseif ($s < -20.0) {
                $s = -20.0;
            }
            $calibrated[] = 1.0 / (1.0 + exp(-$s));
        }
        return $calibrated;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'a' => $this->a,
            'b' => $this->b,
        ]));
    }

    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $a = isset($data['a']) ? (float) $data['a'] : 1.0;
        $b = isset($data['b']) ? (float) $data['b'] : 0.0;
        return new self($a, $b);
    }
}
