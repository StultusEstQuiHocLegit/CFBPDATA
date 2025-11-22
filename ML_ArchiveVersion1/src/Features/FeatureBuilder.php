<?php
// FeatureBuilder.php
// Constructs engineered features from raw financial columns according to config.

declare(strict_types=1);

namespace App\Features;

use App\Data\DataFrame;
use InvalidArgumentException;

// FeatureBuilder computes derived features such as ratios, trends and logs based on a configuration,
// it accepts a DataFrame with raw financial columns and returns a new DataFrame where the engineered features are appended,
// missing values are propagated as null, trend and volatility are computed over a rolling window of up to 3 years for each company
class FeatureBuilder
{
    private const BLOCKED_RAW_COLUMNS = [
        'EntityRegistrantName',
        'EntityCentralIndexKey',
        'TradingSymbol',
        'DocumentPeriodEndDate',
        'DocumentFiscalPeriodFocus',
        'DocumentFiscalYearFocus',
        'DocumentType',
        'AmendmentFlag',
        'CurrentFiscalYearEndDate',
    ];

    private const DOMAIN_BOUNDS = [
        'current_ratio' => [0.0, 5.0],
        'quick_ratio' => [0.0, 5.0],
        'CurrentRatio' => [0.0, 5.0],
        'QuickRatio' => [0.0, 5.0],
        'debt_to_assets' => [0.0, 5.0],
        'TL_TA' => [0.0, 5.0],
        'Debt_Assets' => [0.0, 5.0],
        'WC_TA' => [-2.0, 2.0],
        'roa' => [-1.0, 1.0],
        'ROA' => [-1.0, 1.0],
        'operating_margin' => [-1.0, 1.0],
        'OperatingMargin' => [-1.0, 1.0],
        'EBIT_InterestExpense' => [-5.0, 10.0],
        'EBITDA_InterestExpense' => [-5.0, 10.0],
        'CFO_Liabilities' => [-2.0, 2.0],
        'CFO_DebtService' => [-2.0, 2.0],
        'Accruals' => [-2.0, 2.0],
        'AltmanZPrime' => [-10.0, 10.0],
    ];

    // @var array<string, mixed>
    private array $config;

    // @param array<string, mixed> $config
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    // Build features for the provided DataFrame,
    // the returned DataFrame contains the original identifier, time and label columns alongside engineered features,
    // raw columns remain untouched to avoid accidental leakage into models
    public function build(DataFrame $df): DataFrame
    {
        $rows = $df->toRows();
        $idField = 'company_id';
        $timeField = 'fiscal_year';
        $labelField = 'label';

        $features = [];
        $ratioNames = self::normaliseList($this->config['ratios'] ?? []);
        $levelNames = self::normaliseList($this->config['levels'] ?? []);
        $trendNames = self::normaliseList($this->config['trends'] ?? []);
        $volatilityNames = self::normaliseList($this->config['volatility'] ?? []);
        $logNames = self::normaliseList($this->config['log_features'] ?? []);
        $extraNames = self::normaliseList($this->config['extra_features'] ?? []);

        $ratioLookup = array_flip($ratioNames);
        $volLookup = array_flip($volatilityNames);
        $extraLookup = array_flip($extraNames);

        // Precompute grouping by company for trend computations
        $companyGroups = [];
        foreach ($rows as $i => $row) {
            $cid = $row[$idField];
            $companyGroups[$cid][] = $i;
        }
        // Prepare arrays to store computed feature columns
        foreach ($ratioNames as $feat) {
            $features[$feat] = array_fill(0, count($rows), null);
        }
        foreach ($levelNames as $featName) {
            $features[$featName . '_leve'] = array_fill(0, count($rows), null);
        }
        foreach ($trendNames as $featName) {
            $features[$featName . '_tren'] = array_fill(0, count($rows), null);
        }
        foreach ($volatilityNames as $featName) {
            $features[$featName . '_vola'] = array_fill(0, count($rows), null);
        }
        foreach ($logNames as $lf) {
            $features['ln_' . $lf] = array_fill(0, count($rows), null);
        }
        foreach ($extraNames as $extra) {
            $initial = in_array($extra, ['DividendOmission', 'DebtIssuanceSpike', 'DebtRepaymentSpike'], true) ? 0 : null;
            $features[$extra] = array_fill(0, count($rows), $initial);
        }
        // Ratio computations
        foreach ($rows as $i => $row) {
            // Map incoming column names from the raw CSV to the generic variables used in ratio computations,
            // many financial statement fields have multiple possible names; prefer the upper-case versions present in the SEC-derived CSVs,
            // if an alias does not exist, the value will remain null and the ratio will be null
            $ca = self::toFloat($row['CurrentAssets'] ?? $row['current_assets'] ?? null);
            $cl = self::toFloat($row['CurrentLiabilities'] ?? $row['current_liabilities'] ?? null);
            // inventory: prefer InventoryNet then inventory
            $inv = self::toFloat($row['InventoryNet'] ?? $row['inventory'] ?? null);
            // cash: prefer CashAndCashEquivalentsAtCarryingValue then cash
            $cash = self::toFloat($row['CashAndCashEquivalentsAtCarryingValue'] ?? $row['cash'] ?? null);
            // total assets and liabilities
            $assets = self::toFloat($row['assets'] ?? $row['assets_total'] ?? null);
            $liab = self::toFloat($row['liabilities'] ?? $row['liabilities_total'] ?? null);
            // revenue: prefer SalesRevenueNet then revenues
            $rev = self::toFloat($row['SalesRevenueNet'] ?? $row['revenues'] ?? $row['revenue'] ?? null);
            // cost of goods sold
            $cogs = self::toFloat($row['CostOfGoodsSold'] ?? $row['cogs'] ?? null);
            // EBIT: prefer OperatingIncomeLoss then ebit or operating_income
            $ebit = self::toFloat($row['OperatingIncomeLoss'] ?? $row['ebit'] ?? $row['operating_income'] ?? null);
            // net income
            $ni = self::toFloat($row['NetIncomeLoss'] ?? $row['net_income'] ?? null);
            // denominators epsilon to avoid division by zero
            // current ratio
            if (isset($ratioLookup['current_ratio'])) {
                $features['current_ratio'][$i] = self::safeDivide($ca, $cl);
            }
            // quick ratio
            if (isset($ratioLookup['quick_ratio'])) {
                $numerator = ($ca !== null && $inv !== null) ? ($ca - $inv) : null;
                $features['quick_ratio'][$i] = self::safeDivide($numerator, $cl);
            }
            // debt to assets
            if (isset($ratioLookup['debt_to_assets'])) {
                $features['debt_to_assets'][$i] = self::safeDivide($liab, $assets);
            }
            // debt to equity
            $equity = ($assets !== null && $liab !== null) ? $assets - $liab : null;
            if (isset($ratioLookup['debt_to_equity'])) {
                $features['debt_to_equity'][$i] = self::safeDivide($liab, $equity);
            }
            // profitability ratios
            if (isset($ratioLookup['net_margin'])) {
                $features['net_margin'][$i] = self::safeDivide($ni, $rev);
            }
            if (isset($ratioLookup['roa'])) {
                $features['roa'][$i] = self::safeDivide($ni, $assets);
            }
            if (isset($ratioLookup['roe'])) {
                $features['roe'][$i] = self::safeDivide($ni, $equity);
            }
            // efficiency
            if (isset($ratioLookup['asset_turnover'])) {
                $features['asset_turnover'][$i] = self::safeDivide($rev, $assets);
            }
            if (isset($ratioLookup['inventory_turnover'])) {
                $features['inventory_turnover'][$i] = self::safeDivide($cogs, $inv);
            }
            // cash ratios
            if (isset($ratioLookup['cash_ratio'])) {
                $features['cash_ratio'][$i] = self::safeDivide($cash, $cl);
            }
            if (isset($ratioLookup['operating_margin'])) {
                $features['operating_margin'][$i] = self::safeDivide($ebit, $rev);
            }

            // Extended ratios based on provided formulas
            // Total liabilities to assets (TL_TA)
            if (isset($extraLookup['TL_TA'])) {
                $features['TL_TA'][$i] = self::safeDivide($liab, $assets);
            }
            // Debt to assets: long and short term debt / assets
            $lt = self::toFloat($row['LongTermDebtNoncurrent'] ?? null);
            $st = self::toFloat($row['ShortTermBorrowings'] ?? null);
            $debt = null;
            if ($lt !== null || $st !== null) {
                $debt = ($lt ?: 0.0) + ($st ?: 0.0);
            }
            if (isset($extraLookup['Debt_Assets'])) {
                $features['Debt_Assets'][$i] = self::safeDivide($debt, $assets);
            }
            // EBIT / Interest Expense
            $interest = self::toFloat($row['InterestExpense'] ?? null);
            if (isset($extraLookup['EBIT_InterestExpense'])) {
                $features['EBIT_InterestExpense'][$i] = self::safeDivide($ebit, $interest);
            }
            // EBITDA / Interest Expense
            $da = self::toFloat($row['DepreciationAndAmortization'] ?? null);
            $ebitda = ($ebit !== null && $da !== null) ? ($ebit + $da) : null;
            if (isset($extraLookup['EBITDA_InterestExpense'])) {
                $features['EBITDA_InterestExpense'][$i] = self::safeDivide($ebitda, $interest);
            }
            // Cash flow to liabilities
            $cfo = self::toFloat($row['NetCashProvidedByUsedInOperatingActivities'] ?? null);
            if (isset($extraLookup['CFO_Liabilities'])) {
                $features['CFO_Liabilities'][$i] = self::safeDivide($cfo, $liab);
            }
            // Cash flow to interest and debt service
            $debtService = null;
            $repay = self::toFloat($row['RepaymentsOfDebt'] ?? null);
            if ($interest !== null || $repay !== null) {
                $debtService = ($interest ?: 0.0) + ($repay ?: 0.0);
            }
            if (isset($extraLookup['CFO_DebtService'])) {
                $features['CFO_DebtService'][$i] = self::safeDivide($cfo, $debtService);
            }
            // Working capital to assets
            if (isset($extraLookup['WC_TA'])) {
                $wc = ($ca !== null && $cl !== null) ? ($ca - $cl) : null;
                $features['WC_TA'][$i] = self::safeDivide($wc, $assets);
            }
            // CurrentRatio & QuickRatio duplicates using InventoryNet
            $inventoryNet = self::toFloat($row['InventoryNet'] ?? null);
            if (isset($extraLookup['CurrentRatio'])) {
                $features['CurrentRatio'][$i] = self::safeDivide($ca, $cl);
            }
            if (isset($extraLookup['QuickRatio'])) {
                $numerator = ($ca !== null && $inventoryNet !== null) ? ($ca - $inventoryNet) : null;
                $features['QuickRatio'][$i] = self::safeDivide($numerator, $cl);
            }
            // WC_TA computed above
            // ROA duplicate using NetIncomeLoss
            $niLoss = self::toFloat($row['NetIncomeLoss'] ?? null);
            if (isset($extraLookup['ROA'])) {
                $features['ROA'][$i] = self::safeDivide($niLoss, $assets);
            }
            // OperatingMargin using OperatingIncomeLoss / SalesRevenueNet
            $salesNet = self::toFloat($row['SalesRevenueNet'] ?? null);
            if (isset($extraLookup['OperatingMargin'])) {
                $features['OperatingMargin'][$i] = self::safeDivide($ebit, $salesNet);
            }
            // DaysAR
            $ar = self::toFloat($row['AccountsReceivableNetCurrent'] ?? null);
            if (isset($extraLookup['DaysAR'])) {
                $ratio = self::safeDivide($ar, $salesNet);
                $features['DaysAR'][$i] = $ratio !== null ? 365.0 * $ratio : null;
            }
            // DaysINV
            $invNet = self::toFloat($row['InventoryNet'] ?? null);
            $cogs2 = self::toFloat($row['CostOfGoodsSold'] ?? null);
            if (isset($extraLookup['DaysINV'])) {
                $ratio = self::safeDivide($invNet, $cogs2);
                $features['DaysINV'][$i] = $ratio !== null ? 365.0 * $ratio : null;
            }
            // DaysAP
            $ap = self::toFloat($row['AccountsPayableCurrent'] ?? null);
            if (isset($extraLookup['DaysAP'])) {
                $ratio = self::safeDivide($ap, $cogs2);
                $features['DaysAP'][$i] = $ratio !== null ? 365.0 * $ratio : null;
            }
            // CashConversionCycle
            if (isset($extraLookup['CashConversionCycle'])) {
                $dAR = $features['DaysAR'][$i] ?? null;
                $dINV = $features['DaysINV'][$i] ?? null;
                $dAP = $features['DaysAP'][$i] ?? null;
                if ($dAR !== null && $dINV !== null && $dAP !== null) {
                    $features['CashConversionCycle'][$i] = $dAR + $dINV - $dAP;
                } else {
                    $features['CashConversionCycle'][$i] = null;
                }
            }
            // Accruals: (NetIncomeLoss - CFO) / assets
            if (isset($extraLookup['Accruals'])) {
                $num = ($niLoss !== null && $cfo !== null) ? ($niLoss - $cfo) : null;
                $features['Accruals'][$i] = self::safeDivide($num, $assets);
            }
            // log features
            foreach ($logNames as $lf) {
                $val = self::toFloat($row[$lf] ?? null);
                $features['ln_' . $lf][$i] = ($val !== null && $val > 0.0) ? log($val) : null;
            }
        }
        // Level features (identity of variables at time t)
        foreach ($levelNames as $featName) {
            $col = array_map([self::class, 'toFloat'], array_column($rows, $featName));
            foreach ($col as $i => $val) {
                $features[$featName . '_leve'][$i] = $val;
            }
        }
        // Trend and volatility features per company
        foreach ($companyGroups as $cid => $indices) {
            // sort indices by time ascending
            usort($indices, function ($a, $b) use ($rows, $timeField) {
                return (int)$rows[$a][$timeField] <=> (int)$rows[$b][$timeField];
            });
            foreach ($trendNames as $featName) {
                $keyTrend = $featName . '_tren';
                $keyVol   = $featName . '_vola';
                // compute over sliding window of up to 3 entries
                $values = [];
                foreach ($indices as $idx) {
                    $val = self::toFloat($rows[$idx][$featName] ?? null);
                    $values[] = $val;
                    // maintain last three values
                    $window = array_slice($values, -3);
                    $n = count($window);
                    // compute slope via simple linear regression on indices [0...n-1]
                    if ($n >= 2) {
                        $xs = range(0, $n - 1);
                        $meanX = array_sum($xs) / $n;
                        $meanY = 0.0;
                        $countY = 0;
                        foreach ($window as $v) {
                            $meanY += ($v ?? 0.0);
                            $countY++;
                        }
                        $meanY /= ($countY > 0 ? $countY : 1);
                        $num = 0.0;
                        $den = 0.0;
                        foreach ($window as $j => $v) {
                            $y = $v ?? $meanY;
                            $num += ($xs[$j] - $meanX) * ($y - $meanY);
                            $den += ($xs[$j] - $meanX) ** 2;
                        }
                        $slope = ($den != 0.0) ? ($num / $den) : 0.0;
                        $features[$keyTrend][$idx] = $slope;
                        // volatility
                        $mean = $meanY;
                        $sumSq = 0.0;
                        foreach ($window as $y) {
                            $valy = $y ?? $mean;
                            $sumSq += ($valy - $mean) ** 2;
                        }
                        $std = sqrt($sumSq / $n);
                        if (isset($volLookup[$featName])) {
                            $features[$keyVol][$idx] = $std;
                        }
                    } else {
                        // insufficient history; keep null
                        $features[$keyTrend][$idx] = null;
                        if (isset($volLookup[$featName])) {
                            $features[$keyVol][$idx] = null;
                        }
                    }
                }
            }
            // Compute indicator features requiring previous year information
            $prevDividend = null;
            $prevIssue = null;
            $prevRepay = null;
            foreach ($indices as $idx) {
                $div = self::toFloat($rows[$idx]['PaymentsOfDividends'] ?? null);
                $issue = self::toFloat($rows[$idx]['ProceedsFromIssuanceOfDebt'] ?? null);
                $repay = self::toFloat($rows[$idx]['RepaymentsOfDebt'] ?? null);
                // Dividend omission indicator
                if (isset($extraLookup['DividendOmission']) && $prevDividend !== null && $prevDividend > 0.0 && ($div === null || $div == 0.0)) {
                    $features['DividendOmission'][$idx] = 1;
                }
                // Debt issuance spike indicator
                if (isset($extraLookup['DebtIssuanceSpike']) && $prevIssue !== null && $prevIssue > 0.0 && $issue !== null && $issue >= 3.0 * $prevIssue) {
                    $features['DebtIssuanceSpike'][$idx] = 1;
                }
                // Debt repayment spike indicator
                if (isset($extraLookup['DebtRepaymentSpike']) && $prevRepay !== null && $prevRepay > 0.0 && $repay !== null && $repay >= 3.0 * $prevRepay) {
                    $features['DebtRepaymentSpike'][$idx] = 1;
                }
                $prevDividend = $div;
                $prevIssue = $issue;
                $prevRepay = $repay;
            }
        }
        // Copy raw columns specified in config
        if (isset($this->config['raw_columns']) && is_array($this->config['raw_columns'])) {
            $rawColumns = array_values(array_filter($this->config['raw_columns'], fn($col) => is_string($col)));
            $rawColumns = array_values(array_unique($rawColumns));
            $disallowed = array_values(array_intersect($rawColumns, self::BLOCKED_RAW_COLUMNS));
            if (!empty($disallowed)) {
                throw new InvalidArgumentException('Disallowed raw columns requested: ' . implode(', ', $disallowed));
            }
            foreach ($rawColumns as $rawCol) {
                $features[$rawCol] = [];
                foreach ($rows as $i => $row) {
                    $features[$rawCol][$i] = $row[$rawCol] ?? null;
                }
            }
        }
        self::applyDomainBounds($features);

        // Assemble new DataFrame with id, time, label and features
        $newCols = [
            $idField => array_column($rows, $idField),
            $timeField => array_column($rows, $timeField),
            $labelField => array_column($rows, $labelField),
        ];
        foreach ($features as $name => $col) {
            $newCols[$name] = $col;
        }
        return DataFrame::fromColumns($newCols);
    }

    // Apply domain-specific clipping to stabilise extreme ratios before scaling
    // @param array<string, array<int, float|null>> $features
    private static function applyDomainBounds(array &$features): void
    {
        foreach (self::DOMAIN_BOUNDS as $name => [$low, $high]) {
            if (!isset($features[$name])) {
                continue;
            }
            foreach ($features[$name] as $idx => $value) {
                if ($value === null) {
                    continue;
                }
                if ($value < $low) {
                    $features[$name][$idx] = $low;
                } elseif ($value > $high) {
                    $features[$name][$idx] = $high;
                }
            }
        }
    }

    // Normalise a list-like config entry to a deduplicated array of strings
    // @param mixed $value
    // @return string[]
    private static function normaliseList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = array_values(array_filter($value, fn($v) => is_string($v) && $v !== ''));
        return array_values(array_unique($items));
    }

    // Safely divide two numbers, guarding against tiny denominators that would
    // otherwise explode ratios; returns null when the denominator is effectively zero
    private static function safeDivide(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null) {
            return null;
        }
        if (abs($denominator) < 1e-6) {
            return null;
        }
        return $numerator / $denominator;
    }

    // Cast numeric strings to floats; return null if not numeric
    // @param mixed $v
    private static function toFloat($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float)$v;
        }
        return null;
    }
}
