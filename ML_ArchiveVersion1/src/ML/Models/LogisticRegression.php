<?php
// LogisticRegression.php
// Implements a simple L2-regularised logistic regression classifier.

declare(strict_types=1);

namespace App\ML\Models;

use App\ML\EstimatorInterface;

// LogisticRegression is a binary classifier that models the probability of bankruptcy via a logistic link function,
// it is trained using batch gradient descent with L2 regularisation,
// this implementation keeps things simple and deterministic, exposing minimal tuning knobs
class LogisticRegression implements EstimatorInterface
{
    // @var float[] Model weights
    private array $weights = [];
    // @var float Bias term
    private float $bias = 0.0;
    // @var float Regularisation strength
    private float $lambda;
    // @var int Number of iterations
    private int $iterations;
    // @var float Learning rate
    private float $learningRate;
    // @var float|null Maximum gradient norm for clipping
    private ?float $maxGradNorm;
    // @var int Early stopping patience
    private int $earlyStoppingPatience;
    // @var float Minimum decrease in validation loss to reset patience
    private float $earlyStoppingMinDelta;
    // @var array<int,array<float>>|null Validation feature matrix
    private ?array $validationX = null;
    // @var array<int,int>|null Validation labels
    private ?array $validationY = null;
    // @var array<int,float>|null Validation sample weights
    private ?array $validationWeights = null;

    public function __construct(
        float $lambda = 1.0,
        int $iterations = 200,
        float $learningRate = 0.1,
        ?float $maxGradNorm = null,
        int $earlyStoppingPatience = 5,
        float $earlyStoppingMinDelta = 1e-4
    ) {
        $this->lambda = $lambda;
        $this->iterations = $iterations;
        $this->learningRate = $learningRate;
        $this->maxGradNorm = $maxGradNorm;
        $this->earlyStoppingPatience = max(1, $earlyStoppingPatience);
        $this->earlyStoppingMinDelta = $earlyStoppingMinDelta;
    }

    public function setValidationData(array $X, array $y, ?array $weights = null): void
    {
        $this->validationX = $X;
        $this->validationY = $y;
        $this->validationWeights = $weights;
    }

    public function fit(array $X, array $y, ?array $sampleWeights = null): void
    {
        $nSamples = count($X);
        if ($nSamples === 0) {
            return;
        }
        $nFeatures = count($X[0]);
        // initialise weights and bias to zeros
        $this->weights = array_fill(0, $nFeatures, 0.0);
        $this->bias = 0.0;
        // normalise sample weights
        if ($sampleWeights === null) {
            $sampleWeights = array_fill(0, $nSamples, 1.0);
        }
        $totalWeight = array_sum($sampleWeights);
        if ($totalWeight <= 0.0) {
            $totalWeight = (float) $nSamples;
        }
        $normalisation = $totalWeight > 0.0 ? $totalWeight : (float) max(1, $nSamples);
        // Gradient descent loop
        $bestValLoss = null;
        $bestWeights = null;
        $bestBias = null;
        $patienceCounter = 0;

        for ($iter = 0; $iter < $this->iterations; $iter++) {
            $gradW = array_fill(0, $nFeatures, 0.0);
            $gradB = 0.0;
            $loss = 0.0;
            for ($i = 0; $i < $nSamples; $i++) {
                $xi = $X[$i];
                $yi = $y[$i];
                $wi = $sampleWeights[$i];
                $z = $this->bias;
                for ($j = 0; $j < $nFeatures; $j++) {
                    $z += $this->weights[$j] * $xi[$j];
                }
                $p = 1.0 / (1.0 + exp(-$z));
                $error = $p - $yi;
                // accumulate gradients weighted
                for ($j = 0; $j < $nFeatures; $j++) {
                    $gradW[$j] += $wi * $error * $xi[$j];
                }
                $gradB += $wi * $error;
                // compute loss for monitoring (optional)
                $loss += -$wi * ($yi * log($p + 1e-15) + (1 - $yi) * log(1 - $p + 1e-15));
            }
            $loss /= $normalisation;
            // add L2 penalty gradients
            for ($j = 0; $j < $nFeatures; $j++) {
                $gradW[$j] += $this->lambda * 2.0 * $this->weights[$j];
            }
            if ($this->maxGradNorm !== null) {
                $normSq = $gradB * $gradB;
                for ($j = 0; $j < $nFeatures; $j++) {
                    $normSq += $gradW[$j] * $gradW[$j];
                }
                $norm = sqrt($normSq);
                if ($norm > 1e-12 && $norm > $this->maxGradNorm) {
                    $scale = $this->maxGradNorm / $norm;
                    for ($j = 0; $j < $nFeatures; $j++) {
                        $gradW[$j] *= $scale;
                    }
                    $gradB *= $scale;
                }
            }
            // update weights
            for ($j = 0; $j < $nFeatures; $j++) {
                $this->weights[$j] -= $this->learningRate * $gradW[$j] / $normalisation;
            }
            $this->bias -= $this->learningRate * $gradB / $normalisation;

            if ($this->validationX !== null && $this->validationY !== null) {
                $valLoss = $this->logLoss($this->validationX, $this->validationY, $this->validationWeights);
                if ($bestValLoss === null || $valLoss < $bestValLoss - $this->earlyStoppingMinDelta) {
                    $bestValLoss = $valLoss;
                    $bestWeights = $this->weights;
                    $bestBias = $this->bias;
                    $patienceCounter = 0;
                } else {
                    $patienceCounter++;
                    if ($patienceCounter >= $this->earlyStoppingPatience) {
                        if ($bestWeights !== null) {
                            $this->weights = $bestWeights;
                            $this->bias = $bestBias;
                        }
                        break;
                    }
                }
            }
        }
    }

    private function logLoss(array $X, array $y, ?array $weights = null): float
    {
        $loss = 0.0;
        $n = count($X);
        if ($n === 0) {
            return 0.0;
        }
        if ($weights === null) {
            $weights = array_fill(0, $n, 1.0);
        }
        $weightSum = 0.0;
        $nFeatures = count($this->weights);
        for ($i = 0; $i < $n; $i++) {
            $z = $this->bias;
            $xi = $X[$i];
            for ($j = 0; $j < $nFeatures; $j++) {
                $z += $this->weights[$j] * $xi[$j];
            }
            $p = 1.0 / (1.0 + exp(-$z));
            $yi = $y[$i];
            $wi = $weights[$i] ?? 1.0;
            $weightSum += $wi;
            $loss += -$wi * ($yi * log($p + 1e-15) + (1 - $yi) * log(1 - $p + 1e-15));
        }
        if ($weightSum <= 0.0) {
            $weightSum = (float) $n;
        }
        return $loss / $weightSum;
    }

    public function predictProba(array $X): array
    {
        $proba = [];
        $nFeatures = count($this->weights);
        foreach ($X as $row) {
            $z = $this->bias;
            for ($j = 0; $j < $nFeatures; $j++) {
                $z += $this->weights[$j] * $row[$j];
            }
            $proba[] = 1.0 / (1.0 + exp(-$z));
        }
        return $proba;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'weights' => $this->weights,
            'bias' => $this->bias,
            'lambda' => $this->lambda,
            'iterations' => $this->iterations,
            'learningRate' => $this->learningRate,
            'maxGradNorm' => $this->maxGradNorm,
            'earlyStoppingPatience' => $this->earlyStoppingPatience,
            'earlyStoppingMinDelta' => $this->earlyStoppingMinDelta,
        ]));
    }

    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self(
            $data['lambda'],
            $data['iterations'],
            $data['learningRate'],
            $data['maxGradNorm'] ?? null,
            $data['earlyStoppingPatience'] ?? 5,
            $data['earlyStoppingMinDelta'] ?? 1e-4
        );
        $obj->weights = $data['weights'];
        $obj->bias = $data['bias'];
        return $obj;
    }

    public function getLambda(): float
    {
        return $this->lambda;
    }
}
