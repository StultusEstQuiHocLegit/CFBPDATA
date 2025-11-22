<?php
// Imputer.php
// Fills missing numeric values with the median and adds missingness indicators.

declare(strict_types=1);

namespace App\Features\Transformers;

use App\Features\Transformer;

// Imputer replaces nulls in numeric columns with the median value computed on the training set,
// it also appends a binary indicator column for each imputed feature to mark missingness,
// this helps models capture missingness patterns, only numeric columns are imputed
class Imputer implements Transformer
{
    // @var array<string, float>
    private array $medians = [];
    // @var array<string, string>
    private array $indicatorNames = [];

    public function fit(array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        $numericCols = [];
        foreach ($rows[0] as $name => $value) {
            if (is_numeric($value) || $value === null || $value === '') {
                $numericCols[] = $name;
            }
        }
        foreach ($numericCols as $col) {
            $values = [];
            foreach ($rows as $row) {
                $v = $row[$col];
                if ($v !== null && $v !== '' && is_numeric($v)) {
                    $values[] = (float)$v;
                }
            }
            if (empty($values)) {
                $this->medians[$col] = 0.0;
            } else {
                sort($values);
                $n = count($values);
                $mid = intdiv($n, 2);
                $this->medians[$col] = ($n % 2 === 0) ? (($values[$mid - 1] + $values[$mid]) / 2.0) : $values[$mid];
            }
            $this->indicatorNames[$col] = $col . '_missing';
        }
    }

    public function transform(array $rows): array
    {
        foreach ($rows as $i => $row) {
            foreach ($this->medians as $col => $median) {
                $v = $row[$col] ?? null;
                $missing = false;
                if ($v === null || $v === '' || !is_numeric($v)) {
                    $rows[$i][$col] = $median;
                    $missing = true;
                } else {
                    $rows[$i][$col] = (float)$v;
                }
                $rows[$i][$this->indicatorNames[$col]] = $missing ? 1 : 0;
            }
        }
        return $rows;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'medians' => $this->medians,
            'indicatorNames' => $this->indicatorNames,
        ]));
    }

    public static function load(string $path): Transformer
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self();
        $obj->medians = $data['medians'];
        $obj->indicatorNames = $data['indicatorNames'];
        return $obj;
    }
}