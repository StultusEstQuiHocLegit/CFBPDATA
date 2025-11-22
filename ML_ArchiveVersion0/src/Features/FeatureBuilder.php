<?php
// FeatureBuilder.php
// Constructs engineered features from raw financial columns according to config.

declare(strict_types=1);

namespace App\Features;

use App\Data\DataFrame;

// FeatureBuilder computes derived features such as ratios, trends and logs based on a configuration,
// it accepts a DataFrame with raw financial columns and returns a new DataFrame where the engineered features are appended,
// missing values are propagated as null, trend and volatility are computed over a rolling window of up to 3 years for each company
class FeatureBuilder
{
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
        // Precompute grouping by company for trend computations
        $companyGroups = [];
        foreach ($rows as $i => $row) {
            $cid = $row[$idField];
            $companyGroups[$cid][] = $i;
        }
        // Prepare arrays to store computed feature columns
        foreach ($this->config['ratios'] as $feat) {
            $features[$feat] = array_fill(0, count($rows), null);
        }
        foreach (['levels','trends','volatility'] as $section) {
            foreach ($this->config[$section] as $featName) {
                $suffix = substr($section, 0, 4); // lev, tren, vola
                $key = $featName . '_' . $suffix;
                $features[$key] = array_fill(0, count($rows), null);
            }
        }
        foreach ($this->config['log_features'] as $lf) {
            $features['ln_' . $lf] = array_fill(0, count($rows), null);
        }
        // Indicator features initialised to zero
        $indicatorNames = ['DividendOmission', 'DebtIssuanceSpike', 'DebtRepaymentSpike'];
        foreach ($indicatorNames as $ind) {
            $features[$ind] = array_fill(0, count($rows), 0);
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
            $eps = 1e-6;
            // current ratio
            $features['current_ratio'][$i] = ($ca !== null && $cl !== null && $cl != 0.0) ? $ca / ($cl ?: $eps) : null;
            // quick ratio
            $features['quick_ratio'][$i] = ($ca !== null && $inv !== null && $cl !== null && $cl != 0.0)
                ? (($ca - $inv) / ($cl ?: $eps)) : null;
            // debt to assets
            $features['debt_to_assets'][$i] = ($liab !== null && $assets !== null && $assets != 0.0)
                ? $liab / ($assets ?: $eps) : null;
            // debt to equity
            $equity = ($assets !== null && $liab !== null) ? $assets - $liab : null;
            $features['debt_to_equity'][$i] = ($liab !== null && $equity !== null && $equity != 0.0)
                ? $liab / (($equity != 0.0) ? $equity : $eps) : null;
            // profitability ratios
            $features['net_margin'][$i] = ($ni !== null && $rev !== null && $rev != 0.0) ? $ni / ($rev ?: $eps) : null;
            $features['roa'][$i] = ($ni !== null && $assets !== null && $assets != 0.0) ? $ni / ($assets ?: $eps) : null;
            $features['roe'][$i] = ($ni !== null && $equity !== null && $equity != 0.0) ? $ni / (($equity != 0.0) ? $equity : $eps) : null;
            // efficiency
            $features['asset_turnover'][$i] = ($rev !== null && $assets !== null && $assets != 0.0) ? $rev / ($assets ?: $eps) : null;
            $features['inventory_turnover'][$i] = ($cogs !== null && $inv !== null && $inv != 0.0) ? $cogs / ($inv ?: $eps) : null;
            // cash ratios
            $features['cash_ratio'][$i] = ($cash !== null && $cl !== null && $cl != 0.0) ? $cash / ($cl ?: $eps) : null;
            $features['operating_margin'][$i] = ($ebit !== null && $rev !== null && $rev != 0.0) ? $ebit / ($rev ?: $eps) : null;

            // Extended ratios based on provided formulas
            // Total liabilities to assets (TL_TA)
            $features['TL_TA'][$i] = ($liab !== null && $assets !== null && $assets != 0.0) ? $liab / ($assets ?: $eps) : null;
            // Debt to assets: long and short term debt / assets
            $lt = self::toFloat($row['LongTermDebtNoncurrent'] ?? null);
            $st = self::toFloat($row['ShortTermBorrowings'] ?? null);
            $debt = null;
            if ($lt !== null || $st !== null) {
                $debt = ($lt ?: 0.0) + ($st ?: 0.0);
            }
            $features['Debt_Assets'][$i] = ($debt !== null && $assets !== null && $assets != 0.0) ? $debt / ($assets ?: $eps) : null;
            // EBIT / Interest Expense
            $interest = self::toFloat($row['InterestExpense'] ?? null);
            $features['EBIT_InterestExpense'][$i] = ($ebit !== null && $interest !== null && $interest != 0.0) ? $ebit / ($interest ?: $eps) : null;
            // EBITDA / Interest Expense
            $da = self::toFloat($row['DepreciationAndAmortization'] ?? null);
            $ebitda = ($ebit !== null && $da !== null) ? ($ebit + $da) : null;
            $features['EBITDA_InterestExpense'][$i] = ($ebitda !== null && $interest !== null && $interest != 0.0) ? $ebitda / ($interest ?: $eps) : null;
            // Cash flow to liabilities
            $cfo = self::toFloat($row['NetCashProvidedByUsedInOperatingActivities'] ?? null);
            $features['CFO_Liabilities'][$i] = ($cfo !== null && $liab !== null && $liab != 0.0) ? $cfo / ($liab ?: $eps) : null;
            // Cash flow to interest and debt service
            $debtService = null;
            $repay = self::toFloat($row['RepaymentsOfDebt'] ?? null);
            if ($interest !== null || $repay !== null) {
                $debtService = ($interest ?: 0.0) + ($repay ?: 0.0);
            }
            $features['CFO_DebtService'][$i] = ($cfo !== null && $debtService !== null && $debtService != 0.0) ? $cfo / ($debtService ?: $eps) : null;
            // Working capital to assets
            $features['WC_TA'][$i] = ($ca !== null && $cl !== null && $assets !== null && $assets != 0.0)
                ? (($ca - $cl) / ($assets ?: $eps)) : null;
            // CurrentRatio & QuickRatio duplicates using InventoryNet
            $inventoryNet = self::toFloat($row['InventoryNet'] ?? null);
            $features['CurrentRatio'][$i] = ($ca !== null && $cl !== null && $cl != 0.0) ? $ca / ($cl ?: $eps) : null;
            $features['QuickRatio'][$i] = ($ca !== null && $inventoryNet !== null && $cl !== null && $cl != 0.0)
                ? (($ca - $inventoryNet) / ($cl ?: $eps)) : null;
            // WC_TA computed above
            // ROA duplicate using NetIncomeLoss
            $niLoss = self::toFloat($row['NetIncomeLoss'] ?? null);
            $features['ROA'][$i] = ($niLoss !== null && $assets !== null && $assets != 0.0) ? $niLoss / ($assets ?: $eps) : null;
            // OperatingMargin using OperatingIncomeLoss / SalesRevenueNet
            $salesNet = self::toFloat($row['SalesRevenueNet'] ?? null);
            $features['OperatingMargin'][$i] = ($ebit !== null && $salesNet !== null && $salesNet != 0.0) ? $ebit / ($salesNet ?: $eps) : null;
            // DaysAR
            $ar = self::toFloat($row['AccountsReceivableNetCurrent'] ?? null);
            $features['DaysAR'][$i] = ($ar !== null && $salesNet !== null && $salesNet != 0.0) ? 365.0 * $ar / ($salesNet ?: $eps) : null;
            // DaysINV
            $invNet = self::toFloat($row['InventoryNet'] ?? null);
            $cogs2 = self::toFloat($row['CostOfGoodsSold'] ?? null);
            $features['DaysINV'][$i] = ($invNet !== null && $cogs2 !== null && $cogs2 != 0.0) ? 365.0 * $invNet / ($cogs2 ?: $eps) : null;
            // DaysAP
            $ap = self::toFloat($row['AccountsPayableCurrent'] ?? null);
            $features['DaysAP'][$i] = ($ap !== null && $cogs2 !== null && $cogs2 != 0.0) ? 365.0 * $ap / ($cogs2 ?: $eps) : null;
            // CashConversionCycle
            $dAR = $features['DaysAR'][$i];
            $dINV = $features['DaysINV'][$i];
            $dAP = $features['DaysAP'][$i];
            if ($dAR !== null && $dINV !== null && $dAP !== null) {
                $features['CashConversionCycle'][$i] = $dAR + $dINV - $dAP;
            } else {
                $features['CashConversionCycle'][$i] = null;
            }
            // Accruals: (NetIncomeLoss - CFO) / assets
            $features['Accruals'][$i] = ($niLoss !== null && $cfo !== null && $assets !== null && $assets != 0.0)
                ? (($niLoss - $cfo) / ($assets ?: $eps)) : null;
            // log features
            foreach ($this->config['log_features'] as $lf) {
                $val = self::toFloat($row[$lf] ?? null);
                $features['ln_' . $lf][$i] = ($val !== null && $val > 0.0) ? log($val) : null;
            }
        }
        // Level features (identity of variables at time t)
        foreach ($this->config['levels'] as $featName) {
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
            foreach ($this->config['trends'] as $featName) {
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
                    // compute slope via simple linear regression on indices [0..n-1]
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
                        $features[$keyVol][$idx] = $std;
                    } else {
                        // insufficient history; keep null
                        $features[$keyTrend][$idx] = null;
                        $features[$keyVol][$idx] = null;
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
                if ($prevDividend !== null && $prevDividend > 0.0 && ($div === null || $div == 0.0)) {
                    $features['DividendOmission'][$idx] = 1;
                }
                // Debt issuance spike indicator
                if ($prevIssue !== null && $prevIssue > 0.0 && $issue !== null && $issue >= 3.0 * $prevIssue) {
                    $features['DebtIssuanceSpike'][$idx] = 1;
                }
                // Debt repayment spike indicator
                if ($prevRepay !== null && $prevRepay > 0.0 && $repay !== null && $repay >= 3.0 * $prevRepay) {
                    $features['DebtRepaymentSpike'][$idx] = 1;
                }
                $prevDividend = $div;
                $prevIssue = $issue;
                $prevRepay = $repay;
            }
        }
        // Copy raw columns specified in config
        if (isset($this->config['raw_columns']) && is_array($this->config['raw_columns'])) {
            foreach ($this->config['raw_columns'] as $rawCol) {
                $features[$rawCol] = [];
                foreach ($rows as $i => $row) {
                    $features[$rawCol][$i] = $row[$rawCol] ?? null;
                }
            }
        }
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