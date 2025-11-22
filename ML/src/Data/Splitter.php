<?php
// splitter.php
// Splits a dataset into train/validation/test partitions by company and year.

declare(strict_types=1);

namespace App\Data;

use App\Data\DataFrame;

// Splitter partitions a labeled DataFrame into train, validation and test sets based on year cutoffs and company grouping rules,
// no company will appear across multiple splits,
// test takes precedence over validation, which in turn takes precedence over train
class Splitter
{
    // Partition the provided DataFrame according to the year cutoffs defined in the configuration,
    // all rows for a company are assigned to the same partition based on the latest year present in that company,
    // companies with rows on or after the test year go to the test set, companies with rows on the validation year go to the validation set, the rest go to the training set
    // @param DataFrame $df
    // @param object $splitConf An object with properties test_year and valid_year
    // @return object {train: DataFrame, valid: DataFrame, test: DataFrame}
    public static function byYearAndCompany(DataFrame $df, object $splitConf): object
    {
        $idCol = $df->col('company_id');
        $yearCol = array_map('intval', $df->col('fiscal_year'));
        $indicesTrain = [];
        $indicesValid = [];
        $indicesTest  = [];
        $companyIndices = [];
        // group row indices by company
        foreach ($idCol as $idx => $cid) {
            $companyIndices[$cid][] = $idx;
        }
        foreach ($companyIndices as $cid => $rows) {
            // determine the latest year for this company
            $years = [];
            foreach ($rows as $i) {
                $years[] = $yearCol[$i];
            }
            $maxYear = max($years);
            if ($maxYear >= $splitConf->test_year) {
                foreach ($rows as $i) {
                    $indicesTest[] = $i;
                }
            } elseif ($maxYear >= $splitConf->valid_year) {
                foreach ($rows as $i) {
                    $indicesValid[] = $i;
                }
            } else {
                foreach ($rows as $i) {
                    $indicesTrain[] = $i;
                }
            }
        }
        return (object) [
            'train' => $df->selectRows($indicesTrain),
            'valid' => $df->selectRows($indicesValid),
            'test'  => $df->selectRows($indicesTest),
        ];
    }

    // Build temporal folds where each validation fold is a single year and training contains prior companies
    // @return array<int, array<string, array<int, int>|int>>
    public static function temporalFolds(DataFrame $df, int $numFolds, string $timeField, string $idField): array
    {
        if ($numFolds <= 0 || $df->height() === 0) {
            return [];
        }
        $years = array_map('intval', $df->col($timeField));
        $ids = $df->col($idField);
        $companyRows = [];
        $companyMaxYear = [];
        foreach ($ids as $idx => $cid) {
            $companyRows[$cid]['indices'][] = $idx;
            $year = $years[$idx];
            $companyRows[$cid]['years'][] = $year;
            if (!isset($companyMaxYear[$cid]) || $year > $companyMaxYear[$cid]) {
                $companyMaxYear[$cid] = $year;
            }
        }
        $uniqueYears = array_values(array_unique($years));
        sort($uniqueYears);
        if (count($uniqueYears) <= 1) {
            return [];
        }
        array_pop($uniqueYears);
        if (empty($uniqueYears)) {
            return [];
        }
        $foldYears = array_slice($uniqueYears, -min($numFolds, count($uniqueYears)));
        $folds = [];
        foreach ($foldYears as $validationYear) {
            $trainIdx = [];
            $validIdx = [];
            foreach ($companyRows as $cid => $data) {
                $maxYear = $companyMaxYear[$cid];
                if ($maxYear === $validationYear) {
                    $validIdx = array_merge($validIdx, $data['indices']);
                } elseif ($maxYear < $validationYear) {
                    $trainIdx = array_merge($trainIdx, $data['indices']);
                }
            }
            if (empty($trainIdx) || empty($validIdx)) {
                continue;
            }
            sort($trainIdx);
            sort($validIdx);
            $folds[] = [
                'train' => $trainIdx,
                'valid' => $validIdx,
                'validation_year' => $validationYear,
            ];
        }
        return $folds;
    }
}
