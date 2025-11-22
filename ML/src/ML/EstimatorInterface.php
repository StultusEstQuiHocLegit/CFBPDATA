<?php
// EstimatorInterface.php
// Defines a common interface for ML estimators to fit and predict probabilities.

declare(strict_types=1);

namespace App\ML;

// EstimatorInterface describes the minimal set of methods required by any machine learning estimator used in this project,
// estimators must support weighted fitting, probability prediction, and serialization,
// predictions return the probability of the positive class for each input row
interface EstimatorInterface
{
    // Fit the model on the training data X and binary labels y,
    // optional sample weights can be provided to combat class imbalance
    // @param array<int,array<float>> $X
    // @param array<int,int> $y
    // @param array<int,float>|null $sampleWeights
    public function fit(array $X, array $y, ?array $sampleWeights = null): void;

    // Predict probabilities for each row in X,
    // the returned array contains floating‑point values in [0,1] representing P(y=1)
    // @param array<int,array<float>> $X
    // @return array<int,float>
    public function predictProba(array $X): array;

    // Persist the model to disk
    public function save(string $path): void;

    // Restore a model from disk
    public static function load(string $path): self;
}