<?php
// GradientBoosting.php
// Implements a simple gradient boosting classifier with decision stumps.

declare(strict_types=1);

namespace App\ML\Models;

use App\ML\EstimatorInterface;

// GradientBoosting fits an ensemble of depth-1 decision trees (stumps) using
// gradient boosting on the logistic loss, each stump predicts a residual based on
// a single feature threshold and the ensemble aggregates their contributions to
// produce calibrated probabilities through the logistic link function
class GradientBoosting implements EstimatorInterface
{
    // @var array<int, array{feature: int, threshold: float, left: float, right: float}>
    private array $trees = [];

    // @var float[]
    private array $treeWeights = [];

    private int $numTrees;

    private float $learningRate;

    private int $maxDepth;

    private float $initialScore = 0.0;

    public function __construct(int $numTrees = 50, float $learningRate = 0.1, int $maxDepth = 1)
    {
        $this->numTrees = max(1, $numTrees);
        $this->learningRate = max(1e-6, $learningRate);
        $this->maxDepth = max(1, $maxDepth);
    }

    public function fit(array $X, array $y, ?array $sampleWeights = null): void
    {
        $nSamples = count($X);
        if ($nSamples === 0) {
            $this->trees = [];
            $this->treeWeights = [];
            $this->initialScore = 0.0;
            return;
        }
        $nFeatures = count($X[0]);
        $weights = $sampleWeights ?? array_fill(0, $nSamples, 1.0);

        $pos = array_sum($y);
        $neg = $nSamples - $pos;
        $prior = ($pos + 0.5) / ($neg + 0.5);
        $this->initialScore = log($prior);

        $this->trees = [];
        $this->treeWeights = [];

        $rawScores = array_fill(0, $nSamples, $this->initialScore);

        $featureColumns = [];
        for ($j = 0; $j < $nFeatures; $j++) {
            $featureColumns[$j] = array_column($X, $j);
        }

        for ($t = 0; $t < $this->numTrees; $t++) {
            $residuals = [];
            for ($i = 0; $i < $nSamples; $i++) {
                $p = 1.0 / (1.0 + exp(-$rawScores[$i]));
                $residuals[$i] = $y[$i] - $p;
            }

            $bestTree = null;
            $bestLoss = INF;

            for ($featureIdx = 0; $featureIdx < $nFeatures; $featureIdx++) {
                $column = $featureColumns[$featureIdx];
                $thresholds = self::candidateThresholds($column);
                if (count($thresholds) === 0) {
                    continue;
                }
                foreach ($thresholds as $threshold) {
                    $leftStats = self::accumulateStats($column, $residuals, $weights, $threshold, true);
                    $rightStats = self::accumulateStats($column, $residuals, $weights, $threshold, false);
                    if ($leftStats['weight'] <= 0.0 || $rightStats['weight'] <= 0.0) {
                        continue;
                    }
                    $leftValue = $leftStats['sum'] / $leftStats['weight'];
                    $rightValue = $rightStats['sum'] / $rightStats['weight'];
                    $loss = $leftStats['sq'] - $leftStats['sum'] * $leftValue + $rightStats['sq'] - $rightStats['sum'] * $rightValue;
                    if ($loss < $bestLoss) {
                        $bestLoss = $loss;
                        $bestTree = [
                            'feature' => $featureIdx,
                            'threshold' => $threshold,
                            'left' => $leftValue,
                            'right' => $rightValue,
                        ];
                    }
                }
            }

            if ($bestTree === null) {
                break;
            }

            $this->trees[] = $bestTree;
            $this->treeWeights[] = $this->learningRate;

            $featureIdx = $bestTree['feature'];
            $threshold = $bestTree['threshold'];
            $leftValue = $bestTree['left'];
            $rightValue = $bestTree['right'];

            for ($i = 0; $i < $nSamples; $i++) {
                $value = (float) $featureColumns[$featureIdx][$i];
                $leaf = ($value <= $threshold) ? $leftValue : $rightValue;
                $rawScores[$i] += $this->learningRate * $leaf;
            }
        }
    }

    public function predictProba(array $X): array
    {
        $proba = [];
        foreach ($X as $row) {
            $score = $this->initialScore;
            foreach ($this->trees as $idx => $tree) {
                $featureValue = (float) ($row[$tree['feature']] ?? 0.0);
                $leaf = ($featureValue <= $tree['threshold']) ? $tree['left'] : $tree['right'];
                $score += $this->treeWeights[$idx] * $leaf;
            }
            $proba[] = 1.0 / (1.0 + exp(-$score));
        }
        return $proba;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'trees' => $this->trees,
            'weights' => $this->treeWeights,
            'numTrees' => $this->numTrees,
            'learningRate' => $this->learningRate,
            'maxDepth' => $this->maxDepth,
            'initialScore' => $this->initialScore,
        ]));
    }

    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $model = new self($data['numTrees'] ?? 0, $data['learningRate'] ?? 0.1, $data['maxDepth'] ?? 1);
        $model->trees = $data['trees'] ?? [];
        $model->treeWeights = $data['weights'] ?? [];
        $model->initialScore = $data['initialScore'] ?? 0.0;
        return $model;
    }

    public function getNumTrees(): int
    {
        return $this->numTrees;
    }

    public function getLearningRate(): float
    {
        return $this->learningRate;
    }

    // @param float[] $column
    // @return float[]
    private static function candidateThresholds(array $column): array
    {
        $values = array_map(static fn($v) => (float) $v, $column);
        $unique = array_values(array_unique($values, SORT_NUMERIC));
        sort($unique, SORT_NUMERIC);
        $thresholds = [];
        $n = count($unique);
        if ($n <= 1) {
            return $thresholds;
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $thresholds[] = ($unique[$i] + $unique[$i + 1]) / 2.0;
        }
        return $thresholds;
    }

    // @param float[] $column
    // @param float[] $residuals
    // @param float[] $weights
    // @return array{sum: float, weight: float, sq: float}
    private static function accumulateStats(array $column, array $residuals, array $weights, float $threshold, bool $isLeft): array
    {
        $sum = 0.0;
        $weight = 0.0;
        $sumSq = 0.0;
        $n = count($column);
        for ($i = 0; $i < $n; $i++) {
            $value = (float) $column[$i];
            $belongsLeft = $value <= $threshold;
            if ($belongsLeft !== $isLeft) {
                continue;
            }
            $w = $weights[$i] ?? 1.0;
            $r = $residuals[$i];
            $sum += $w * $r;
            $sumSq += $w * $r * $r;
            $weight += $w;
        }
        return ['sum' => $sum, 'weight' => $weight, 'sq' => $sumSq];
    }
}
