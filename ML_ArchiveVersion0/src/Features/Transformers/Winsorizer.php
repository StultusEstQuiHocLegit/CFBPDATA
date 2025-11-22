<?php
// Winsorizer.php
// Clips numeric features to specified quantile bounds to reduce outliers.

declare(strict_types=1);

namespace App\Features\Transformers;

use App\Features\Transformer;

// Winsorizer computes quantile cutoffs for each numeric column and clips values outside of these bounds,
// this reduces the influence of extreme outliers, cutoffs are configured per the lower and upper quantiles
class Winsorizer implements Transformer
{
    // @var float
    private float $lower;
    // @var float
    private float $upper;
    // @var array<string, array{low:float, high:float}>
    private array $cutoffs = [];

    public function __construct(float $lower = 0.01, float $upper = 0.99)
    {
        $this->lower = $lower;
        $this->upper = $upper;
    }

    public function fit(array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        // Determine numeric columns by inspecting first row
        $numericCols = [];
        foreach ($rows[0] as $name => $value) {
            if (is_numeric($value)) {
                $numericCols[] = $name;
            }
        }
        foreach ($numericCols as $col) {
            $values = [];
            foreach ($rows as $row) {
                $v = $row[$col];
                if (is_numeric($v)) {
                    $values[] = (float)$v;
                }
            }
            if (empty($values)) {
                $this->cutoffs[$col] = ['low' => 0.0, 'high' => 0.0];
                continue;
            }
            sort($values);
            $n = count($values);
            $lowIndex = (int)floor($this->lower * ($n - 1));
            $highIndex = (int)ceil($this->upper * ($n - 1));
            $this->cutoffs[$col] = [
                'low' => $values[$lowIndex],
                'high' => $values[$highIndex],
            ];
        }
    }

    public function transform(array $rows): array
    {
        foreach ($rows as $i => $row) {
            foreach ($this->cutoffs as $col => $bounds) {
                $v = $row[$col] ?? null;
                if ($v === null || !is_numeric($v)) {
                    continue;
                }
                $val = (float)$v;
                if ($val < $bounds['low']) {
                    $val = $bounds['low'];
                } elseif ($val > $bounds['high']) {
                    $val = $bounds['high'];
                }
                $rows[$i][$col] = $val;
            }
        }
        return $rows;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'lower' => $this->lower,
            'upper' => $this->upper,
            'cutoffs' => $this->cutoffs,
        ]));
    }

    public static function load(string $path): Transformer
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self($data['lower'], $data['upper']);
        $obj->cutoffs = $data['cutoffs'];
        return $obj;
    }
}