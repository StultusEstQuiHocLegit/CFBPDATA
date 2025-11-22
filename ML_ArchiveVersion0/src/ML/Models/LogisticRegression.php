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

    public function __construct(float $lambda = 1.0, int $iterations = 200, float $learningRate = 0.1)
    {
        $this->lambda = $lambda;
        $this->iterations = $iterations;
        $this->learningRate = $learningRate;
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
        // Gradient descent loop
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
            // add L2 penalty gradients
            for ($j = 0; $j < $nFeatures; $j++) {
                $gradW[$j] += $this->lambda * 2.0 * $this->weights[$j];
            }
            // update weights
            for ($j = 0; $j < $nFeatures; $j++) {
                $this->weights[$j] -= $this->learningRate * $gradW[$j] / $nSamples;
            }
            $this->bias -= $this->learningRate * $gradB / $nSamples;
        }
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
        ]));
    }

    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self($data['lambda'], $data['iterations'], $data['learningRate']);
        $obj->weights = $data['weights'];
        $obj->bias = $data['bias'];
        return $obj;
    }
}