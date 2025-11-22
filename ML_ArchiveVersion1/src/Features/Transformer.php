<?php
// Transformer.php
// Interface for fit/transform serializable feature transformers.

declare(strict_types=1);

namespace App\Features;

// Transformer defines a stateful preprocessor component that can be fitted on training data and applied to new samples,
// implementations must return transformed data arrays with the same number of rows as input,
// fit should derive any necessary statistics; transform should apply them,
// transformers should implement save() and static load() for persistence
interface Transformer
{
    // Fit the transformer on the training matrix,
    // the matrix is represented as an array of rows, where each row is an associative array of feature names to values,
    // transformers may inspect only the columns they need
    // @param array<int,array<string,mixed>> $rows
    public function fit(array $rows): void;

    // Transform the rows according to the fitted state,
    // should output an array of rows with possibly additional or altered columns
    // @param array<int,array<string,mixed>> $rows
    // @return array<int,array<string,mixed>>
    public function transform(array $rows): array;

    // Serialize the internal state to a file path
    // @param string $path
    public function save(string $path): void;

    // Load a transformer from a file path
    // @param string $path
    public static function load(string $path): self;
}