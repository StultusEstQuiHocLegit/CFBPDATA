<?php
// OneHotEncoder.php
// Encodes categorical variables into one-hot binary columns.

declare(strict_types=1);

namespace App\Features\Transformers;

use App\Features\Transformer;

// OneHotEncoder creates binary indicator columns for each unique value of specified categorical features,
// unknown categories at transform time are encoded as all zeros, original categorical columns are dropped
class OneHotEncoder implements Transformer
{
    // @var string[]
    private array $categorical;
    // @var array<string, array<string, string>>
    private array $mapping = [];

    // @param string[] $categorical List of column names to encode
    public function __construct(array $categorical)
    {
        $this->categorical = $categorical;
    }

    public function fit(array $rows): void
    {
        foreach ($this->categorical as $col) {
            $categories = [];
            foreach ($rows as $row) {
                $val = $row[$col] ?? null;
                if ($val !== null && $val !== '') {
                    $categories[(string)$val] = true;
                }
            }
            // Create consistent column names
            $cols = [];
            foreach (array_keys($categories) as $cat) {
                // Sanitize category into a safe column name
                $safe = preg_replace('/[^A-Za-z0-9_]+/', '_', (string)$cat);
                $cols[$cat] = $col . '_' . strtolower($safe);
            }
            $this->mapping[$col] = $cols;
        }
    }

    public function transform(array $rows): array
    {
        foreach ($rows as $i => $row) {
            foreach ($this->mapping as $col => $map) {
                $val = $row[$col] ?? null;
                // initialize all new columns to 0
                foreach ($map as $cat => $newCol) {
                    $rows[$i][$newCol] = 0;
                }
                if ($val !== null && array_key_exists((string)$val, $map)) {
                    $newName = $map[(string)$val];
                    $rows[$i][$newName] = 1;
                }
                // remove original column
                unset($rows[$i][$col]);
            }
        }
        return $rows;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'categorical' => $this->categorical,
            'mapping' => $this->mapping,
        ]));
    }

    public static function load(string $path): Transformer
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self($data['categorical']);
        $obj->mapping = $data['mapping'];
        return $obj;
    }
}