<?php
// RandomForest.php
// Implements a bagging ensemble of decision trees for classification.

declare(strict_types=1);

namespace App\ML\Models;

use App\ML\EstimatorInterface;

// RandomForest trains multiple decision trees on bootstrap samples of the data,
// each split considers a random subset of features which introduces diversity among trees
class RandomForest implements EstimatorInterface
{
    // @var array<int, array<string, mixed>>
    private array $trees = [];

    private int $numTrees;

    private int $maxDepth;

    private int $minSamplesSplit;

    private float $subsample;

    private float $featureFraction;

    public function __construct(
        int $numTrees = 100,
        int $maxDepth = 5,
        int $minSamplesSplit = 2,
        float $subsample = 0.8,
        float $featureFraction = 0.8
    ) {
        $this->numTrees = max(1, $numTrees);
        $this->maxDepth = max(1, $maxDepth);
        $this->minSamplesSplit = max(2, $minSamplesSplit);
        $this->subsample = max(0.1, min(1.0, $subsample));
        $this->featureFraction = max(0.1, min(1.0, $featureFraction));
    }

    public function fit(array $X, array $y, ?array $sampleWeights = null): void
    {
        $nSamples = count($X);
        if ($nSamples === 0) {
            $this->trees = [];
            return;
        }
        $nFeatures = count($X[0]);
        $weights = $sampleWeights ?? array_fill(0, $nSamples, 1.0);
        $this->trees = [];
        $sampleSize = max($this->minSamplesSplit, (int) round($this->subsample * $nSamples));
        for ($t = 0; $t < $this->numTrees; $t++) {
            $indices = $this->bootstrapIndices($nSamples, $sampleSize);
            $tree = $this->buildTree($X, $y, $weights, $indices, 0, $nFeatures);
            $this->trees[] = $tree;
        }
    }

    public function predictProba(array $X): array
    {
        $nTrees = count($this->trees);
        if ($nTrees === 0) {
            return array_fill(0, count($X), 0.5);
        }
        $proba = [];
        foreach ($X as $row) {
            $sum = 0.0;
            foreach ($this->trees as $tree) {
                $sum += $this->traverse($tree, $row);
            }
            $proba[] = $sum / $nTrees;
        }
        return $proba;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'trees' => $this->trees,
            'numTrees' => $this->numTrees,
            'maxDepth' => $this->maxDepth,
            'minSamplesSplit' => $this->minSamplesSplit,
            'subsample' => $this->subsample,
            'featureFraction' => $this->featureFraction,
        ]));
    }

    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $model = new self(
            (int) ($data['numTrees'] ?? 100),
            (int) ($data['maxDepth'] ?? 5),
            (int) ($data['minSamplesSplit'] ?? 2),
            (float) ($data['subsample'] ?? 0.8),
            (float) ($data['featureFraction'] ?? 0.8)
        );
        $model->trees = $data['trees'] ?? [];
        return $model;
    }

    // @param array<int, array<string, mixed>> $tree
    private function traverse(array $tree, array $row): float
    {
        if (!empty($tree['is_leaf'])) {
            return (float) ($tree['probability'] ?? 0.5);
        }
        $feature = (int) $tree['feature'];
        $threshold = (float) $tree['threshold'];
        $value = (float) ($row[$feature] ?? 0.0);
        if ($value <= $threshold) {
            return $this->traverse($tree['left'], $row);
        }
        return $this->traverse($tree['right'], $row);
    }

    // @param array<int, array<float>> $X
    // @param int[] $y
    // @param float[] $weights
    // @param int[] $indices
    // @return array<string, mixed>
    private function buildTree(array $X, array $y, array $weights, array $indices, int $depth, int $nFeatures): array
    {
        $totalWeight = 0.0;
        $positiveWeight = 0.0;
        foreach ($indices as $idx) {
            $w = $weights[$idx] ?? 1.0;
            $totalWeight += $w;
            if ($y[$idx] === 1) {
                $positiveWeight += $w;
            }
        }
        $prob = $totalWeight > 0.0 ? $positiveWeight / $totalWeight : 0.5;
        if (
            $depth >= $this->maxDepth ||
            count($indices) < $this->minSamplesSplit ||
            $prob <= 0.0 ||
            $prob >= 1.0
        ) {
            return [
                'is_leaf' => true,
                'probability' => max(0.0, min(1.0, $prob)),
            ];
        }
        $numFeatures = max(1, (int) ceil($this->featureFraction * $nFeatures));
        $featureCandidates = $this->sampleFeatureSubset($nFeatures, $numFeatures);
        $bestImpurity = INF;
        $bestSplit = null;
        foreach ($featureCandidates as $feature) {
            $values = [];
            foreach ($indices as $idx) {
                $values[] = (float) $X[$idx][$feature];
            }
            $thresholds = $this->candidateThresholds($values);
            if (empty($thresholds)) {
                continue;
            }
            foreach ($thresholds as $threshold) {
                $split = $this->evaluateSplit($X, $y, $weights, $indices, $feature, $threshold);
                if ($split === null) {
                    continue;
                }
                if ($split['impurity'] < $bestImpurity) {
                    $bestImpurity = $split['impurity'];
                    $bestSplit = $split;
                }
            }
        }
        if ($bestSplit === null) {
            return [
                'is_leaf' => true,
                'probability' => max(0.0, min(1.0, $prob)),
            ];
        }
        return [
            'is_leaf' => false,
            'feature' => $bestSplit['feature'],
            'threshold' => $bestSplit['threshold'],
            'left' => $this->buildTree($X, $y, $weights, $bestSplit['left_indices'], $depth + 1, $nFeatures),
            'right' => $this->buildTree($X, $y, $weights, $bestSplit['right_indices'], $depth + 1, $nFeatures),
        ];
    }

    // @param array<int, array<float>> $X
    // @param int[] $y
    // @param float[] $weights
    // @param int[] $indices
    // @return array<string, mixed>|null
    private function evaluateSplit(array $X, array $y, array $weights, array $indices, int $feature, float $threshold): ?array
    {
        $left = [];
        $right = [];
        $leftWeight = 0.0;
        $rightWeight = 0.0;
        $leftPos = 0.0;
        $rightPos = 0.0;
        foreach ($indices as $idx) {
            $value = (float) $X[$idx][$feature];
            $w = $weights[$idx] ?? 1.0;
            if ($value <= $threshold) {
                $left[] = $idx;
                $leftWeight += $w;
                if ($y[$idx] === 1) {
                    $leftPos += $w;
                }
            } else {
                $right[] = $idx;
                $rightWeight += $w;
                if ($y[$idx] === 1) {
                    $rightPos += $w;
                }
            }
        }
        if ($leftWeight <= 0.0 || $rightWeight <= 0.0) {
            return null;
        }
        if (count($left) < 1 || count($right) < 1) {
            return null;
        }
        $totalWeight = $leftWeight + $rightWeight;
        $giniLeft = $this->gini($leftPos, $leftWeight);
        $giniRight = $this->gini($rightPos, $rightWeight);
        $impurity = ($leftWeight / $totalWeight) * $giniLeft + ($rightWeight / $totalWeight) * $giniRight;
        return [
            'feature' => $feature,
            'threshold' => $threshold,
            'left_indices' => $left,
            'right_indices' => $right,
            'impurity' => $impurity,
        ];
    }

    private function gini(float $positiveWeight, float $totalWeight): float
    {
        if ($totalWeight <= 0.0) {
            return 0.0;
        }
        $p = $positiveWeight / $totalWeight;
        $q = 1.0 - $p;
        return 1.0 - ($p * $p + $q * $q);
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

    // @return int[]
    private function bootstrapIndices(int $nSamples, int $sampleSize): array
    {
        $indices = [];
        for ($i = 0; $i < $sampleSize; $i++) {
            $indices[] = random_int(0, $nSamples - 1);
        }
        return $indices;
    }

    // @return int[]
    private function sampleFeatureSubset(int $nFeatures, int $numFeatures): array
    {
        $indices = range(0, $nFeatures - 1);
        shuffle($indices);
        return array_slice($indices, 0, max(1, min($numFeatures, $nFeatures)));
    }
}
