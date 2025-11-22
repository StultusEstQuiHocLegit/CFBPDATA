<?php
// RandomForest.php
// A minimal placeholder for a Random Forest classifier using bagged decision stumps to maybe try later on.

declare(strict_types=1);

namespace App\ML\Models;

use App\ML\EstimatorInterface;

// RandomForest is a placeholder implementation of a random forest classifier,
// to keep the project self-contained and focused on the logistic baseline,
// this class simply wraps a logistic regression model and exposes the EstimatorInterface,
// future iterations could replace this with a true ensemble of decision trees
class RandomForest implements EstimatorInterface
{
    // @var LogisticRegression
    private LogisticRegression $lr;

    public function __construct()
    {
        $this->lr = new LogisticRegression(1.0, 100, 0.1);
    }

    public function fit(array $X, array $y, ?array $sampleWeights = null): void
    {
        $this->lr->fit($X, $y, $sampleWeights);
    }

    public function predictProba(array $X): array
    {
        return $this->lr->predictProba($X);
    }

    public function save(string $path): void
    {
        $this->lr->save($path);
    }

    public static function load(string $path): self
    {
        $obj = new self();
        $obj->lr = LogisticRegression::load($path);
        return $obj;
    }
}