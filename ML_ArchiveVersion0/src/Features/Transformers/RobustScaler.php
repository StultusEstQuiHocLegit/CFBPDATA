<?php
// RobustScaler.php
// Scales numeric features using the median and IQR to reduce the influence of outliers.

declare(strict_types=1);

namespace App\Features\Transformers;

use App\Features\Transformer;

// RobustScaler subtracts the median and divides by the interquartile range (IQR) for each numeric column,
// when IQR is zero, the feature is left unscaled, scaling is applied after imputation
class RobustScaler implements Transformer
{
    // @var array<string, float>
    private array $medians = [];
    // @var array<string, float>
    private array $iqr = [];

    public function fit(array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        // Identify numeric columns
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
                $this->medians[$col] = 0.0;
                $this->iqr[$col] = 1.0;
            } else {
                sort($values);
                $n = count($values);
                $mid = intdiv($n, 2);
                $this->medians[$col] = ($n % 2 === 0) ? (($values[$mid - 1] + $values[$mid]) / 2.0) : $values[$mid];
                $q1Index = (int)floor(0.25 * ($n - 1));
                $q3Index = (int)ceil(0.75 * ($n - 1));
                $q1 = $values[$q1Index];
                $q3 = $values[$q3Index];
                $iqr = $q3 - $q1;
                $this->iqr[$col] = ($iqr == 0.0) ? 1.0 : $iqr;
            }
        }
    }

    public function transform(array $rows): array
    {
        foreach ($rows as $i => $row) {
            foreach ($this->medians as $col => $median) {
                $v = $row[$col] ?? null;
                if ($v === null || !is_numeric($v)) {
                    continue;
                }
                $rows[$i][$col] = ((float)$v - $median) / $this->iqr[$col];
            }
        }
        return $rows;
    }

    public function save(string $path): void
    {
        file_put_contents($path, serialize([
            'medians' => $this->medians,
            'iqr' => $this->iqr,
        ]));
    }

    public static function load(string $path): Transformer
    {
        $data = unserialize(file_get_contents($path));
        $obj = new self();
        $obj->medians = $data['medians'];
        $obj->iqr = $data['iqr'];
        return $obj;
    }
}