<?php
// DataFrame.php
// Provides a minimal columnar DataFrame abstraction for tabular data.

declare(strict_types=1);

namespace App\Data;

// A simple DataFrame holding named columns as arrays,
// the DataFrame is immutable, methods that subset or transform return new instances,
// it stores each column as a sequential array of typed values,
// missing values should be represented by null,
// the DataFrame provides convenience accessors for common operations
class DataFrame
{
    // @var array<string, array>
    private array $columns;

    // @var int
    private int $nRows;

    // Construct a DataFrame from an associative array of columns,
    // all columns must have the same number of rows,
    // the constructor is private to enforce the use of named constructors
    // @param array<string, array> $columns
    private function __construct(array $columns)
    {
        if (empty($columns)) {
            $this->columns = [];
            $this->nRows = 0;
            return;
        }
        // Validate that all columns have the same length
        $lengths = array_map('count', $columns);
        $uniqueLengths = array_unique($lengths);
        if (count($uniqueLengths) > 1) {
            throw new \InvalidArgumentException('All columns must have the same length');
        }
        $this->columns = $columns;
        $this->nRows = reset($uniqueLengths);
    }

    // Create a DataFrame from an array of associative row arrays,
    // this method infers column names from the keys of the first row,
    // all rows must have consistent keys
    // @param array<int, array<string,mixed>> $rows
    public static function fromRows(array $rows): self
    {
        if (empty($rows)) {
            return new self([]);
        }
        $columns = [];
        $firstKeys = array_keys($rows[0]);
        foreach ($firstKeys as $key) {
            $columns[$key] = [];
        }
        foreach ($rows as $row) {
            foreach ($firstKeys as $key) {
                $columns[$key][] = $row[$key] ?? null;
            }
        }
        return new self($columns);
    }

    // Create a DataFrame directly from an associative array of columns
    // @param array<string,array> $columns
    public static function fromColumns(array $columns): self
    {
        return new self($columns);
    }

    // Return the number of rows in this DataFrame
    public function height(): int
    {
        return $this->nRows;
    }

    // Return the column names
    // @return string[]
    public function columns(): array
    {
        return array_keys($this->columns);
    }

    // Get the array backing a column
    // @param string $name
    // @return array
    public function col(string $name): array
    {
        if (!array_key_exists($name, $this->columns)) {
            throw new \InvalidArgumentException("Unknown column: $name");
        }
        return $this->columns[$name];
    }

    // Return a new DataFrame selecting a subset of columns
    // @param string[] $names
    public function selectColumns(array $names): self
    {
        $selected = [];
        foreach ($names as $name) {
            $selected[$name] = $this->col($name);
        }
        return new self($selected);
    }

    // Return a new DataFrame containing only the specified rows
    // @param int[] $rowIndices
    public function selectRows(array $rowIndices): self
    {
        $selected = [];
        foreach ($this->columns as $name => $col) {
            $selected[$name] = [];
            foreach ($rowIndices as $i) {
                $selected[$name][] = $col[$i];
            }
        }
        return new self($selected);
    }

    // Add or replace a column and return a new DataFrame instance
    // @param string $name
    // @param array $values
    public function withColumn(string $name, array $values): self
    {
        if ($this->nRows !== count($values)) {
            throw new \InvalidArgumentException('Column length mismatch');
        }
        $newCols = $this->columns;
        $newCols[$name] = $values;
        return new self($newCols);
    }

    // Convert the DataFrame back to an array of rows
    // @return array<int, array<string,mixed>>
    public function toRows(): array
    {
        $rows = [];
        for ($i = 0; $i < $this->nRows; $i++) {
            $row = [];
            foreach ($this->columns as $name => $col) {
                $row[$name] = $col[$i];
            }
            $rows[] = $row;
        }
        return $rows;
    }

    // Extract the label vector assuming a column named 'label'
    // @return int[]
    public function y(): array
    {
        return array_map('intval', $this->col('label'));
    }

    // Merge two DataFrames of identical schema by concatenating rows,
    // column order and names must match
    public function concat(self $other): self
    {
        $newCols = [];
        foreach ($this->columns as $name => $col) {
            if (!array_key_exists($name, $other->columns)) {
                throw new \InvalidArgumentException("Cannot concat dataframes with mismatched columns");
            }
            $newCols[$name] = array_merge($col, $other->columns[$name]);
        }
        return new self($newCols);
    }

    // Return distinct values from a column
    // @param string $name
    // @return array<mixed>
    public function unique(string $name): array
    {
        return array_values(array_unique($this->col($name)));
    }

    // Shuffle rows randomly using optional seed, returns new DataFrame
    public function shuffle(?int $seed = null): self
    {
        $indices = range(0, $this->nRows - 1);
        if ($seed !== null) {
            mt_srand($seed);
        }
        shuffle($indices);
        return $this->selectRows($indices);
    }
}