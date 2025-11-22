<?php
// Beta.php
// Implements beta calibration for probability outputs using gradient descent optimisation.

declare(strict_types=1);

namespace App\ML\Calibrator;

// Beta calibration applies a logistic transformation of the log-odds of predicted probabilities,
// the mapping is parameterised by a, b, c which are optimised by minimising log-loss on the validation set
class Beta
{
    private const EPS = 1e-15;

    private float $a = 1.0;

    private float $b = 1.0;

    private float $c = 0.0;

    private float $learningRate;

    private int $iterations;

    public function __construct(float $learningRate = 0.01, int $iterations = 1000)
    {
        $this->learningRate = $learningRate;
        $this->iterations = max(1, $iterations);
    }

    // Fit the beta calibrator parameters using gradient descent on the validation log-loss
    // @param float[] $proba
    // @param int[] $y
    public function fit(array $proba, array $y): void
    {
        $n = count($proba);
        if ($n === 0) {
            return;
        }
        $y = array_map(static fn($v) => (int) $v, $y);
        for ($iter = 0; $iter < $this->iterations; $iter++) {
            $gradA = 0.0;
            $gradB = 0.0;
            $gradC = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $p = max(self::EPS, min(1.0 - self::EPS, (float) $proba[$i]));
                $logP = log($p + self::EPS);
                $logOneMinus = log(1.0 - $p + self::EPS);
                $z = $this->a * $logP + $this->b * $logOneMinus + $this->c;
                if ($z > 20.0) {
                    $z = 20.0;
                } elseif ($z < -20.0) {
                    $z = -20.0;
                }
                $hat = 1.0 / (1.0 + exp(-$z));
                $diff = $hat - $y[$i];
                $gradA += $diff * $logP;
                $gradB += $diff * $logOneMinus;
                $gradC += $diff;
            }
            $scale = $this->learningRate / max(1, $n);
            $updateA = $scale * $gradA;
            $updateB = $scale * $gradB;
            $updateC = $scale * $gradC;
            $this->a -= $updateA;
            $this->b -= $updateB;
            $this->c -= $updateC;
            $maxUpdate = max(abs($updateA), abs($updateB), abs($updateC));
            if ($maxUpdate < 1e-7) {
                break;
            }
        }
    }

    // Calibrate probabilities using the fitted beta parameters
    // @param float[] $proba
    // @return float[]
    public function predict(array $proba): array
    {
        $calibrated = [];
        foreach ($proba as $value) {
            $p = max(self::EPS, min(1.0 - self::EPS, (float) $value));
            $logP = log($p + self::EPS);
            $logOneMinus = log(1.0 - $p + self::EPS);
            $z = $this->a * $logP + $this->b * $logOneMinus + $this->c;
            if ($z > 20.0) {
                $z = 20.0;
            } elseif ($z < -20.0) {
                $z = -20.0;
            }
            $calibrated[] = 1.0 / (1.0 + exp(-$z));
        }
        return $calibrated;
    }

    // Alias used by existing calibrators
    // @param float[] $proba
    // @return float[]
    public function apply(array $proba): array
    {
        return $this->predict($proba);
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'a' => $this->a,
            'b' => $this->b,
            'c' => $this->c,
            'learningRate' => $this->learningRate,
            'iterations' => $this->iterations,
        ]));
    }

    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self((float) ($data['learningRate'] ?? 0.01), (int) ($data['iterations'] ?? 1000));
        $obj->a = (float) ($data['a'] ?? 1.0);
        $obj->b = (float) ($data['b'] ?? 1.0);
        $obj->c = (float) ($data['c'] ?? 0.0);
        return $obj;
    }

    // Expose parameters for testing and reporting
    // @return array{a: float, b: float, c: float}
    public function getParameters(): array
    {
        return ['a' => $this->a, 'b' => $this->b, 'c' => $this->c];
    }
}
