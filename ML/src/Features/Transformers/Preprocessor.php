<?php
// Preprocessor.php
// Composes multiple transformers into a single fittable pipeline producing matrices.

declare(strict_types=1);

namespace App\Features\Transformers;

use App\Features\Transformer;

// Preprocessor orchestrates a sequence of transformers to produce a numeric matrix ready for model consumption,
// it remembers the order of feature columns produced on training and uses it for all subsequent transforms
class Preprocessor
{
    // @var Transformer[]
    private array $steps;
    // @var string[]
    private array $featureNames = [];

    // Create a preprocessor chain from configuration,
    // currently supports winsorize, impute, robust scale and one-hot encoding,
    // additional transformers can be inserted by extending this constructor
    // @param array<string,mixed> $preConf
    // @param string[] $categorical
    public function __construct($preConf, array $categorical)
    {
        // Normalize config to associative array
        if (is_object($preConf)) {
            // convert to array recursively
            $preConf = json_decode(json_encode($preConf), true);
        }
        $this->steps = [];
        // Step order matters: winsorize -> impute -> scale -> encode
        $lower = $preConf['winsorize']['lower'] ?? 0.01;
        $upper = $preConf['winsorize']['upper'] ?? 0.99;
        $this->steps[] = new Winsorizer((float)$lower, (float)$upper);
        $this->steps[] = new Imputer();
        // Use robust scaler by default
        $this->steps[] = new RobustScaler();
        if (!empty($categorical)) {
            $this->steps[] = new OneHotEncoder($categorical);
        }
    }

    // Fit all transformers on the training rows and record feature names
    // @param array<int,array<string,mixed>> $rows
    public function fit(array $rows): void
    {
        $current = $rows;
        foreach ($this->steps as $transformer) {
            $transformer->fit($current);
            $current = $transformer->transform($current);
        }
        // Determine feature names excluding id/time/label after full transform
        if (!empty($current)) {
            $keys = array_keys($current[0]);
            // remove id/time/label
            $this->featureNames = array_values(array_filter($keys, function ($k) {
                return !in_array($k, ['company_id', 'fiscal_year', 'label'], true);
            }));
        }
    }

    // Transform rows through all steps and return a numeric matrix (rows x features) consistent with the feature order determined during fit
    // @param array<int,array<string,mixed>> $rows
    // @return array<int,array<float>>
    public function transform(array $rows): array
    {
        $current = $rows;
        foreach ($this->steps as $transformer) {
            $current = $transformer->transform($current);
        }
        $matrix = [];
        foreach ($current as $row) {
            $vec = [];
            foreach ($this->featureNames as $feat) {
                $val = $row[$feat] ?? 0.0;
                $vec[] = is_numeric($val) ? (float)$val : 0.0;
            }
            $matrix[] = $vec;
        }
        return $matrix;
    }

    // Get the list of feature names used for the matrix in order
    // @return string[]
    public function getFeatureNames(): array
    {
        return $this->featureNames;
    }

    // Serialize the preprocessor by serializing each step and storing the feature names
    public function save(string $path): void
    {
        $data = [
            'featureNames' => $this->featureNames,
            'steps' => [],
        ];
        foreach ($this->steps as $index => $step) {
            $stepPath = $path . '.step' . $index;
            $step->save($stepPath);
            $data['steps'][] = ['class' => get_class($step), 'path' => $stepPath];
        }
        file_put_contents($path, serialize($data));
    }

    // Restore a preprocessor from a file path
    public static function load(string $path): self
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self([], []);
        $obj->featureNames = $data['featureNames'];
        $obj->steps = [];
        foreach ($data['steps'] as $stepInfo) {
            // @var class-string<Transformer> $class
            $class = $stepInfo['class'];
            $obj->steps[] = $class::load($stepInfo['path']);
        }
        return $obj;
    }
}