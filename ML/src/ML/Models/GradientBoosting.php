<?php
// GradientBoosting.php
// Implements gradient boosted decision trees with configurable depth.

declare(strict_types=1);

namespace App\ML\Models;

use App\ML\EstimatorInterface;

// GradientBoosting fits shallow regression trees to the negative gradient of the logistic loss
class GradientBoosting implements EstimatorInterface
{
    // @var array<int, array<string, mixed>>
    private array $trees = [];

    // @var float[]
    private array $treeWeights = [];

    private int $numTrees;

    private float $learningRate;

    private int $maxDepth;

    private int $minSamplesSplit;

    private float $initialScore = 0.0;

    public function __construct(int $numTrees = 100, float $learningRate = 0.1, int $maxDepth = 3, int $minSamplesSplit = 2)
    {
        $this->numTrees = max(1, $numTrees);
        $this->learningRate = max(1e-6, $learningRate);
        $this->maxDepth = max(1, $maxDepth);
        $this->minSamplesSplit = max(2, $minSamplesSplit);
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
        $prior = ($pos + 0.5) / max(0.5, $neg + 0.5);
        $this->initialScore = log($prior);
        $this->trees = [];
        $this->treeWeights = [];
        $rawScores = array_fill(0, $nSamples, $this->initialScore);
        for ($iter = 0; $iter < $this->numTrees; $iter++) {
            $residuals = [];
            for ($i = 0; $i < $nSamples; $i++) {
                $p = 1.0 / (1.0 + exp(-$rawScores[$i]));
                $residuals[$i] = $y[$i] - $p;
            }
            $tree = $this->buildTree($X, $residuals, $weights, range(0, $nSamples - 1), 0, $nFeatures);
            $this->trees[] = $tree;
            $this->treeWeights[] = $this->learningRate;
            for ($i = 0; $i < $nSamples; $i++) {
                $prediction = $this->predictTree($tree, $X[$i]);
                $rawScores[$i] += $this->learningRate * $prediction;
            }
        }
    }

    public function predictProba(array $X): array
    {
        $proba = [];
        foreach ($X as $row) {
            $score = $this->initialScore;
            foreach ($this->trees as $idx => $tree) {
                $score += $this->treeWeights[$idx] * $this->predictTree($tree, $row);
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
            'minSamplesSplit' => $this->minSamplesSplit,
            'initialScore' => $this->initialScore,
        ]));
    }

    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $model = new self(
            (int) ($data['numTrees'] ?? 0),
            (float) ($data['learningRate'] ?? 0.1),
            (int) ($data['maxDepth'] ?? 3),
            (int) ($data['minSamplesSplit'] ?? 2)
        );
        $model->trees = $data['trees'] ?? [];
        $model->treeWeights = $data['weights'] ?? [];
        $model->initialScore = (float) ($data['initialScore'] ?? 0.0);
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

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    // @param array<int, array<float>> $X
    // @param float[] $targets
    // @param float[] $weights
    // @param int[] $indices
    // @return array<string, mixed>
    private function buildTree(array $X, array $targets, array $weights, array $indices, int $depth, int $nFeatures): array
    {
        $sumWeight = 0.0;
        $sumResidual = 0.0;
        $sumResidualSq = 0.0;
        foreach ($indices as $idx) {
            $w = $weights[$idx] ?? 1.0;
            $r = $targets[$idx];
            $sumWeight += $w;
            $sumResidual += $w * $r;
            $sumResidualSq += $w * $r * $r;
        }
        $value = $sumWeight > 0.0 ? $sumResidual / $sumWeight : 0.0;
        if ($depth >= $this->maxDepth || count($indices) < $this->minSamplesSplit) {
            return [
                'is_leaf' => true,
                'value' => $value,
            ];
        }
        $bestLoss = INF;
        $bestSplit = null;
        for ($feature = 0; $feature < $nFeatures; $feature++) {
            $values = [];
            foreach ($indices as $idx) {
                $values[] = (float) $X[$idx][$feature];
            }
            $thresholds = $this->candidateThresholds($values);
            if (empty($thresholds)) {
                continue;
            }
            foreach ($thresholds as $threshold) {
                $split = $this->evaluateRegressionSplit($X, $targets, $weights, $indices, $feature, $threshold);
                if ($split === null) {
                    continue;
                }
                if ($split['loss'] < $bestLoss) {
                    $bestLoss = $split['loss'];
                    $bestSplit = $split;
                }
            }
        }
        if ($bestSplit === null) {
            return [
                'is_leaf' => true,
                'value' => $value,
            ];
        }
        return [
            'is_leaf' => false,
            'feature' => $bestSplit['feature'],
            'threshold' => $bestSplit['threshold'],
            'left' => $this->buildTree($X, $targets, $weights, $bestSplit['left_indices'], $depth + 1, $nFeatures),
            'right' => $this->buildTree($X, $targets, $weights, $bestSplit['right_indices'], $depth + 1, $nFeatures),
        ];
    }

    // @param float[] $values
    // @return float[]
    private function candidateThresholds(array $values): array
    {
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

    // @param array<int, array<float>> $X
    // @param float[] $targets
    // @param float[] $weights
    // @param int[] $indices
    // @return array<string, mixed>|null
    private function evaluateRegressionSplit(array $X, array $targets, array $weights, array $indices, int $feature, float $threshold): ?array
    {
        $left = [];
        $right = [];
        $leftWeight = 0.0;
        $rightWeight = 0.0;
        $leftSum = 0.0;
        $rightSum = 0.0;
        $leftSq = 0.0;
        $rightSq = 0.0;
        foreach ($indices as $idx) {
            $value = (float) $X[$idx][$feature];
            $w = $weights[$idx] ?? 1.0;
            $target = $targets[$idx];
            if ($value <= $threshold) {
                $left[] = $idx;
                $leftWeight += $w;
                $leftSum += $w * $target;
                $leftSq += $w * $target * $target;
            } else {
                $right[] = $idx;
                $rightWeight += $w;
                $rightSum += $w * $target;
                $rightSq += $w * $target * $target;
            }
        }
        if ($leftWeight <= 0.0 || $rightWeight <= 0.0) {
            return null;
        }
        if (count($left) < 1 || count($right) < 1) {
            return null;
        }
        $leftLoss = $leftSq - ($leftSum * $leftSum) / $leftWeight;
        $rightLoss = $rightSq - ($rightSum * $rightSum) / $rightWeight;
        $loss = $leftLoss + $rightLoss;
        return [
            'feature' => $feature,
            'threshold' => $threshold,
            'left_indices' => $left,
            'right_indices' => $right,
            'loss' => $loss,
        ];
    }

    private function predictTree(array $tree, array $row): float
    {
        if (!empty($tree['is_leaf'])) {
            return (float) ($tree['value'] ?? 0.0);
        }
        $feature = (int) $tree['feature'];
        $threshold = (float) $tree['threshold'];
        $value = (float) ($row[$feature] ?? 0.0);
        if ($value <= $threshold) {
            return $this->predictTree($tree['left'], $row);
        }
        return $this->predictTree($tree['right'], $row);
    }
}
