<?php
// CSVReader.php
// Reads CSV files into arrays and DataFrames with minimal validation.

declare(strict_types=1);

namespace App\IO;

use App\Data\DataFrame;

// CSVReader provides simple static methods to load CSV files,
// files must contain a header row defining column names,
// all subsequent rows are converted to associative arrays keyed by header names,
// strings are not converted to numbers automatically; the downstream code may cast values
class CSVReader
{
    // Load a CSV file into a DataFrame
    // @param string $path
    public static function load(string $path): DataFrame
    {
        if (!is_readable($path)) {
            throw new \RuntimeException("Cannot read CSV: $path");
        }
        $rows = [];
        if (($handle = fopen($path, 'r')) === false) {
            throw new \RuntimeException("Failed to open CSV: $path");
        }
        $header = null;
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($header === null) {
                $header = $data;
                continue;
            }
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }
        fclose($handle);
        return DataFrame::fromRows($rows);
    }

    // Load the bankrupt and solvent CSVs as DataFrames,
    // this helper just returns a tuple of (positive, negative) DataFrames, it does not apply labels
    // @param string $posPath
    // @param string $negPath
    // @return array{0:DataFrame,1:DataFrame}
    public static function loadPair(string $posPath, string $negPath): array
    {
        $pos = self::load($posPath);
        $neg = self::load($negPath);
        return [$pos, $neg];
    }
}