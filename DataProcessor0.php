<?php
// DataProcessor0.php
// Adds financial ratios and distress scores to financials.csv and financials_solvent.csv.

ini_set('display_errors','1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

define('FINANCIAL_CSV_FILE', __DIR__ . '/financials.csv');
define('FINANCIAL_CSV_FILE_SOLVENT', __DIR__ . '/financials_solvent.csv');
define('FINANCIAL_CSV_FILE_SUBSET', __DIR__ . '/financials_subset.csv');
define('FINANCIAL_CSV_FILE_SOLVENT_SUBSET', __DIR__ . '/financials_solvent_subset.csv');
define('SAFE_DIV_MIN_DEN', 1e-3);

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}
logmsg('Starting financial data processing…');

function logmsg(string $msg): void {
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

function logwarn(string $msg): void {
    logmsg("NOTE: $msg");
}

function read_csv(string $file): array {
    $rows = [];
    if (!($fh = fopen($file, 'r'))) return [[], []];
    $header = fgetcsv($fh);
    while (($data = fgetcsv($fh)) !== false) {
        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = $data[$i] ?? '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return [$header, $rows];
}

function write_csv(string $file, array $header, array $rows): void {
    $fh = fopen($file, 'w');
    fputcsv($fh, $header);
    foreach ($rows as $row) {
        $line = [];
        foreach ($header as $col) {
            $line[] = $row[$col] ?? '';
        }
        fputcsv($fh, $line);
    }
    fclose($fh);
}

function sanitizeMetric($value, float $limit = 1e9): string|float {
    if ($value === '' || $value === null) return '';
    if (!is_numeric($value)) return '';
    $v = (float)$value;
    if (!is_finite($v)) return '';
    if (abs($v) < 1e-12) return 0.0;
    if (abs($v) > $limit) return '';
    return $v;
}

function safeDiv(float|int|string $num, float|int|string $den, float $limit = 1e9, ?float $minDen = null): string|float {
    if ($num === '' || $den === '' || $num === null || $den === null) return '';
    if (!is_numeric($num) || !is_numeric($den)) return '';
    $min = $minDen ?? SAFE_DIV_MIN_DEN;
    if ($min <= 0) $min = SAFE_DIV_MIN_DEN;
    if (abs((float)$den) < $min) return '';
    return sanitizeMetric(((float)$num) / ((float)$den), $limit);
}

function avg(float $a, float $b): float {
    return ($a + $b) / 2;
}

function sanitizeRowMetrics(array &$row): void {
    $limits = [
        'TL_TA' => 5,
        'Debt_Assets' => 5,
        'EBIT_InterestExpense' => 1e3,
        'EBITDA_InterestExpense' => 1e3,
        'CFO_Liabilities' => 1e3,
        'CFO_DebtService' => 1e3,
        'CurrentRatio' => 1e3,
        'QuickRatio' => 1e3,
        'WC_TA' => 5,
        'ROA' => 5,
        'OperatingMargin' => 5,
        'DaysAR' => 1000,
        'DaysINV' => 1000,
        'DaysAP' => 1000,
        'CashConversionCycle' => 2000,
        'Accruals' => 50,
        'DividendOmission' => 1,
        'DebtIssuanceSpike' => 1,
        'DebtRepaymentSpike' => 1,
        'AltmanZPrime' => 50,
        'AltmanZDoublePrime' => 50,
        'OhlsonOScore' => 50,
        'OhlsonOScoreProb' => 1,
        'ZmijewskiXScore' => 50,
        'SpringateSScore' => 50,
        'TafflerZScore' => 50,
        'FulmerHScore' => 50,
        'GroverGScore' => 50,
        'BeneishMScore' => 20,
        'PiotroskiFScore' => 9,
    ];
    foreach ($limits as $k => $limit) {
        if (array_key_exists($k, $row)) {
            $before = $row[$k];
            $row[$k] = sanitizeMetric($row[$k], $limit);
            if ($before !== '' && $row[$k] === '' && isset($row['CIK'])) {
                $yr = $row['year'] ?? 'unknown';
                logwarn("CIK {$row['CIK']} year $yr: sanitized $k from $before");
            }
        }
    }
    if (isset($row['OhlsonOScoreProb']) && $row['OhlsonOScoreProb'] !== '') {
        $prob = (float)$row['OhlsonOScoreProb'];
        if (!is_finite($prob) || $prob < 0 || $prob > 1) {
            $yr = $row['year'] ?? 'unknown';
            logwarn("CIK {$row['CIK']} year $yr: invalid OhlsonOScoreProb $prob reset to empty");
            $row['OhlsonOScoreProb'] = '';
        }
    }
}

function process_file(string $file): array {
    logmsg("Processing $file …");
    [$header, $rows] = read_csv($file);
    $newCols = [
        'TL_TA','Debt_Assets','EBIT_InterestExpense','EBITDA_InterestExpense','CFO_Liabilities',
        'CFO_DebtService','CurrentRatio','QuickRatio','WC_TA','ROA','OperatingMargin',
        'DaysAR','DaysINV','DaysAP','CashConversionCycle','Accruals','DividendOmission',
        'DebtIssuanceSpike','DebtRepaymentSpike','AltmanZPrime','AltmanZDoublePrime',
        'OhlsonOScore','OhlsonOScoreProb','ZmijewskiXScore','SpringateSScore','TafflerZScore',
        'FulmerHScore','GroverGScore','BeneishMScore','PiotroskiFScore'
    ];
    foreach ($newCols as $c) {
        if (!in_array($c, $header, true)) $header[] = $c;
    }
    $rowsByCik = [];
    foreach ($rows as $r) $rowsByCik[$r['CIK']][] = $r;
    $outRows = [];
    foreach ($rowsByCik as $cik => $grp) {
        usort($grp, fn($a,$b) => intval($a['year']) <=> intval($b['year']));
        $prev = null;
        foreach ($grp as $row) {
            $ca = (float)($row['CurrentAssets'] ?? 0);
            $cl = (float)($row['CurrentLiabilities'] ?? 0);
            $assets = (float)($row['assets'] ?? 0);
            $liabilities = (float)($row['liabilities'] ?? 0);
            $inventory = (float)($row['InventoryNet'] ?? 0);
            $ltDebt = (float)($row['LongTermDebtNoncurrent'] ?? 0);
            $stBorrow = (float)($row['ShortTermBorrowings'] ?? 0);
            $operIncome = ($row['OperatingIncomeLoss'] !== '' ? (float)$row['OperatingIncomeLoss'] : ((float)$row['NetIncomeLoss'] + (float)$row['InterestExpense'] + (float)$row['IncomeTaxExpenseBenefit']));
            $dep = (float)($row['DepreciationAndAmortization'] ?? 0);
            $interest = (float)($row['InterestExpense'] ?? 0);
            $cfo = (float)($row['NetCashProvidedByUsedInOperatingActivities'] ?? 0);
            $sales = (float)($row['SalesRevenueNet'] ?? 0);
            $cogs = (float)($row['CostOfGoodsSold'] ?? 0);
            $ar = (float)($row['AccountsReceivableNetCurrent'] ?? 0);
            $ap = (float)($row['AccountsPayableCurrent'] ?? 0);
            $ni = (float)($row['NetIncomeLoss'] ?? 0);
            $retEarn = (float)($row['RetainedEarningsAccumulatedDeficit'] ?? 0);
            $equity = (float)($row['equity'] ?? 0);
            $ffo = $ni + $dep;
            $incomeBeforeTax = (float)($row['IncomeBeforeIncomeTaxes'] ?? 0);
            $proceedsDebt = (float)($row['ProceedsFromIssuanceOfDebt'] ?? 0);
            $repayDebt = (float)($row['RepaymentsOfDebt'] ?? 0);
            $divPaid = (float)($row['PaymentsOfDividends'] ?? 0);
            $wc = $ca - $cl;
            $totalDebt = $ltDebt + $stBorrow;
            $quickAssets = $ca - $inventory;

            $row['TL_TA'] = safeDiv($liabilities,$assets);
            $row['Debt_Assets'] = safeDiv($ltDebt+$stBorrow,$assets);
            $row['EBIT_InterestExpense'] = safeDiv($operIncome,$interest);
            $row['EBITDA_InterestExpense'] = safeDiv($operIncome+$dep,$interest);
            $row['CFO_Liabilities'] = safeDiv($cfo,$liabilities);
            $row['CFO_DebtService'] = safeDiv($cfo,$interest+$repayDebt);
            $row['CurrentRatio'] = safeDiv($ca,$cl);
            $row['QuickRatio'] = safeDiv($quickAssets,$cl);
            $row['WC_TA'] = safeDiv($wc,$assets);
            $row['ROA'] = safeDiv($ni,$assets);
            $row['OperatingMargin'] = safeDiv($operIncome,$sales);
            $row['DaysAR'] = safeDiv(365*$ar,$sales);
            $row['DaysINV'] = safeDiv(365*$inventory,$cogs);
            $row['DaysAP'] = safeDiv(365*$ap,$cogs);
            $row['CashConversionCycle'] = ($row['DaysAR']!=='' && $row['DaysINV']!=='' && $row['DaysAP']!=='') ? $row['DaysAR'] + $row['DaysINV'] - $row['DaysAP'] : '';
            $avgAssets = ($prev && $prev['assets']!=='') ? avg($assets,(float)$prev['assets']) : 0;
            $row['Accruals'] = ($avgAssets>SAFE_DIV_MIN_DEN) ? safeDiv($ni - $cfo,$avgAssets) : '';
            $row['DividendOmission'] = ($prev && (float)$prev['PaymentsOfDividends']>0 && $divPaid<=0) ? 1 : 0;
            $row['DebtIssuanceSpike'] = ($prev && (float)$prev['ProceedsFromIssuanceOfDebt']>0 && $proceedsDebt>=3*(float)$prev['ProceedsFromIssuanceOfDebt']) ? 1 : 0;
            $row['DebtRepaymentSpike'] = ($prev && (float)$prev['RepaymentsOfDebt']>0 && $repayDebt>=3*(float)$prev['RepaymentsOfDebt']) ? 1 : 0;

            $X1 = safeDiv($wc,$assets);
            $X2 = safeDiv($retEarn,$assets);
            $X3 = safeDiv($operIncome,$assets);
            $X4 = safeDiv($equity,$liabilities);
            $X5 = safeDiv($sales,$assets);
            if ($X1!=='' && $X2!=='' && $X3!=='' && $X4!=='' && $X5!=='') {
                $row['AltmanZPrime'] = 0.717*(float)$X1 + 0.847*(float)$X2 + 3.107*(float)$X3 + 0.420*(float)$X4 + 0.998*(float)$X5;
                $row['AltmanZDoublePrime'] = 6.56*(float)$X1 + 3.26*(float)$X2 + 6.72*(float)$X3 + 1.05*(float)$X4;
            } else {
                $row['AltmanZPrime'] = '';
                $row['AltmanZDoublePrime'] = '';
            }

            $canOhlson = ($assets > SAFE_DIV_MIN_DEN && $ca > SAFE_DIV_MIN_DEN && $cl > SAFE_DIV_MIN_DEN && $liabilities > SAFE_DIV_MIN_DEN);
            if ($canOhlson) {
                $TA=$assets; $TL=$liabilities; $WC=$wc; $CL=$cl; $CA=$ca;
                $size = $TA>SAFE_DIV_MIN_DEN ? -0.407*log($TA) : '';
                $tl_ta = 6.03*safeDiv($TL,$TA);
                $wc_ta = -1.43*safeDiv($WC,$TA);
                $cl_ca = 0.0757*safeDiv($CL,$CA);
                $tl_gt_ta = ($TL>$TA)? -1.72 : 0;
                $ni_ta = -2.37*safeDiv($ni,$TA);
                $ffo_tl = -1.83*safeDiv($ffo,$TL);
                $neg_earn = ($prev && (float)$prev['NetIncomeLoss']<0 && $ni<0) ? 0.285 : 0;
                $deltaDen = ($prev) ? (abs($ni)+abs((float)$prev['NetIncomeLoss'])) : 0;
                $delta_ni = ($prev && $deltaDen>SAFE_DIV_MIN_DEN) ? -0.521*(($ni-(float)$prev['NetIncomeLoss'])/$deltaDen) : 0;
                if ($size!=='' && $tl_ta!=='' && $wc_ta!=='' && $cl_ca!=='' && $ni_ta!=='' && $ffo_tl!=='') {
                    $T = -1.32 + $size + $tl_ta + $wc_ta + $cl_ca + $tl_gt_ta + $ni_ta + $ffo_tl + $neg_earn + $delta_ni;
                    if (is_finite($T)) {
                        $row['OhlsonOScore'] = $T;
                        $prob = 1 / (1 + exp(-$T));
                        if (!is_finite($prob)) {
                            $row['OhlsonOScoreProb'] = '';
                        } else {
                            $row['OhlsonOScoreProb'] = max(0.0, min(1.0, $prob));
                        }
                    } else {
                        $row['OhlsonOScore'] = '';
                        $row['OhlsonOScoreProb'] = '';
                        logwarn("CIK {$row['CIK']} year {$row['year']}: Ohlson score non-finite");
                    }
                } else {
                    $row['OhlsonOScore']='';
                    $row['OhlsonOScoreProb']='';
                }
            } else {
                $row['OhlsonOScore']='';
                $row['OhlsonOScoreProb']='';
            }

            $zm_ni_ta = safeDiv($ni,$assets);
            $zm_tl_ta = safeDiv($liabilities,$assets);
            $zm_ca_cl = safeDiv($ca,$cl);
            if ($zm_ni_ta!=='' && $zm_tl_ta!=='' && $zm_ca_cl!=='') {
                $row['ZmijewskiXScore'] = -4.3 - 4.5*(float)$zm_ni_ta + 5.7*(float)$zm_tl_ta + 0.004*(float)$zm_ca_cl;
            } else {
                $row['ZmijewskiXScore'] = '';
            }

            $A = safeDiv($wc,$assets);
            $B = safeDiv($operIncome,$assets);
            $C = safeDiv($incomeBeforeTax,$cl);
            $D = safeDiv($sales,$assets);
            $row['SpringateSScore'] = ($A!=='' && $B!=='' && $C!=='' && $D!=='') ? 1.03*(float)$A + 3.07*(float)$B + 0.66*(float)$C + 0.40*(float)$D : '';
            $x1 = safeDiv($incomeBeforeTax,$cl);
            $x2 = safeDiv($ca,$liabilities);
            $x3 = safeDiv($cl,$assets);
            $dailyOpEx = ($sales - $incomeBeforeTax - $dep)/365;
            $x4 = ($dailyOpEx>SAFE_DIV_MIN_DEN) ? safeDiv($quickAssets - $cl, $dailyOpEx, 1e9, max(SAFE_DIV_MIN_DEN, abs($dailyOpEx))) : '';
            if ($dailyOpEx<=SAFE_DIV_MIN_DEN) {
                logwarn("CIK {$row['CIK']} year {$row['year']}: skipped Taffler x4 due to tiny/negative daily operating expense");
            }
            $row['TafflerZScore'] = ($x1!=='' && $x2!=='' && $x3!=='' && $x4!=='') ? 3.20 + 12.18*(float)$x1 + 2.50*(float)$x2 - 10.68*(float)$x3 + 0.029*(float)$x4 : '';
            $avgRetEarn = $prev ? avg($retEarn,(float)$prev['RetainedEarningsAccumulatedDeficit']) : $retEarn;
            $avgAssets2 = $prev ? avg($assets,(float)$prev['assets']) : $assets;
            $avgTotalDebt = $prev ? avg($totalDebt,(float)$prev['LongTermDebtNoncurrent']+(float)$prev['ShortTermBorrowings']) : $totalDebt;
            $X1 = safeDiv($avgRetEarn,$avgAssets2);
            $X2 = safeDiv($sales,$avgAssets2);
            $X3 = safeDiv($operIncome,$equity);
            $X4 = safeDiv($cfo,$avgTotalDebt);
            $X5 = safeDiv($avgTotalDebt,$equity);
            $X6 = safeDiv($cl,$avgAssets2);
            $tangible = $assets - (float)($row['Goodwill'] ?? 0) - (float)($row['IntangibleAssetsNetExcludingGoodwill'] ?? 0);
            $tangiblePrev = $prev ? ((float)$prev['assets'] - (float)$prev['Goodwill'] - (float)$prev['IntangibleAssetsNetExcludingGoodwill']) : $tangible;
            $avgTangible = $prev ? avg($tangible,$tangiblePrev) : $tangible;
            $X7 = $avgTangible>SAFE_DIV_MIN_DEN ? log($avgTangible) : '';
            $X8 = ($avgTotalDebt>SAFE_DIV_MIN_DEN) ? safeDiv($wc,$avgTotalDebt) : '';
            $X9 = ($operIncome>0 && abs($interest)>SAFE_DIV_MIN_DEN) ? safeDiv(log($operIncome),$interest) : '';
            $row['FulmerHScore'] = ($X1!=='' && $X2!=='' && $X3!=='' && $X4!=='' && $X5!=='' && $X6!=='' && $X7!=='' && $X8!=='' && $X9!=='') ? (5.528*(float)$X1 + 0.212*(float)$X2 + 0.73*(float)$X3 + 1.27*(float)$X4 -0.12*(float)$X5 + 2.335*(float)$X6 + 0.575*(float)$X7 + 1.083*(float)$X8 + 0.894*(float)$X9 -6.075) : '';
            $roa = safeDiv($ni,$assets);
            $row['GroverGScore'] = ($A!=='' && $B!=='' && $roa!=='') ? 1.650*(float)$A + 3.404*(float)$B - 0.016*(float)$roa + 0.057 : '';
            if ($prev) {
                $ar_prev = (float)$prev['AccountsReceivableNetCurrent'];
                $sales_prev = (float)$prev['SalesRevenueNet'];
                $dsri = ($sales!=0 && $sales_prev!=0) ? safeDiv(safeDiv($ar,$sales), safeDiv($ar_prev,$sales_prev)) : '';
                $gmi = ($sales_prev!=0 && $sales!=0) ? safeDiv(($sales_prev-(float)$prev['CostOfGoodsSold'])/$sales_prev, ($sales-$cogs)/$sales) : '';
                $ppent = (float)$row['PropertyPlantAndEquipmentNet'];
                $ppent_prev = (float)$prev['PropertyPlantAndEquipmentNet'];
                $aqi = ($assets!=0 && (float)$prev['assets']!=0) ? safeDiv(1-($ca+$ppent)/$assets,1-((float)$prev['CurrentAssets']+$ppent_prev)/(float)$prev['assets']) : '';
                $sgi = ($sales_prev!=0) ? safeDiv($sales,$sales_prev) : '';
                $dep_prev = (float)$prev['DepreciationAndAmortization'];
                $depi = (($ppent_prev+$dep_prev)!=0 && ($ppent+$dep)!=0) ? safeDiv($dep_prev/($ppent_prev+$dep_prev), $dep/($ppent+$dep)) : '';
                $sgai = ($sales_prev!=0 && $sales!=0) ? safeDiv((float)$row['SellingGeneralAndAdministrativeExpense']/$sales, (float)$prev['SellingGeneralAndAdministrativeExpense']/$sales_prev) : '';
                $lvgi = ($assets!=0 && (float)$prev['assets']!=0) ? safeDiv(($cl+$ltDebt)/$assets, ((float)$prev['CurrentLiabilities']+(float)$prev['LongTermDebtNoncurrent'])/(float)$prev['assets']) : '';
                $tata = $assets!=0 ? safeDiv($ni-$cfo,$assets) : '';
                if ($dsri!=='' && $gmi!=='' && $aqi!=='' && $sgi!=='' && $depi!=='' && $sgai!=='' && $tata!=='' && $lvgi!=='') {
                    $row['BeneishMScore'] = -4.84 + 0.92*(float)$dsri + 0.528*(float)$gmi + 0.404*(float)$aqi + 0.892*(float)$sgi + 0.115*(float)$depi - 0.172*(float)$sgai + 4.679*(float)$tata - 0.327*(float)$lvgi;
                } else {
                    $row['BeneishMScore'] = '';
                }
            } else {
                $row['BeneishMScore'] = '';
            }
            $f = 0;
            if ($assets>0 && $ni>0) $f++;
            if ($cfo>0) $f++;
            if ($prev) {
                $roa_prev = safeDiv((float)$prev['NetIncomeLoss'], (float)$prev['assets']);
                $roa_curr = safeDiv($ni,$assets);
                if ($roa_prev!=='' && $roa_curr!=='' && $roa_curr>$roa_prev) $f++;
            }
            if ($cfo > $ni) $f++;
            if ($prev) {
                $lev_prev = safeDiv((float)$prev['LongTermDebtNoncurrent'], (float)$prev['assets']);
                $lev_curr = safeDiv($ltDebt,$assets);
                if ($lev_prev!=='' && $lev_curr!=='' && $lev_curr < $lev_prev) $f++;
                $cr_prev = safeDiv((float)$prev['CurrentAssets'], (float)$prev['CurrentLiabilities']);
                $cr_curr = safeDiv($ca,$cl);
                if ($cr_prev!=='' && $cr_curr!=='' && $cr_curr > $cr_prev) $f++;
                $shares_prev = (float)$prev['WeightedAverageNumberOfSharesOutstandingBasic'];
                $shares_curr = (float)$row['WeightedAverageNumberOfSharesOutstandingBasic'];
                if ($shares_curr <= $shares_prev) $f++;
                $gm_prev = $sales_prev!=0 ? safeDiv((float)$prev['GrossProfit'],$sales_prev) : '';
                $gm_curr = $sales!=0 ? safeDiv((float)$row['GrossProfit'],$sales) : '';
                if ($gm_prev!=='' && $gm_curr!=='' && $gm_curr>$gm_prev) $f++;
                $at_prev = safeDiv($sales_prev,(float)$prev['assets']);
                $at_curr = safeDiv($sales,$assets);
                if ($at_prev!=='' && $at_curr!=='' && $at_curr>$at_prev) $f++;
            }
            $row['PiotroskiFScore'] = max(0, min(9, (int)$f));

            sanitizeRowMetrics($row);
            foreach ($newCols as $colName) {
                if (isset($row[$colName]) && $row[$colName] !== '' && !is_finite((float)$row[$colName])) {
                    logwarn("CIK {$row['CIK']} year {$row['year']}: dropping non-finite $colName value {$row[$colName]}");
                    $row[$colName] = '';
                }
            }
            $prev = $row;
            $outRows[] = $row;
        }
    }
    write_csv($file,$header,$outRows);
    logmsg("Processed " . count($outRows) . " row(s). Done.");
    return [$header, $outRows];
}

function sync_subset(array $sourceRows, string $subsetFile): void {
    if (!file_exists($subsetFile)) {
        logmsg("Skipped synchronizing $subsetFile (file not found).");
        return;
    }
    [$header, $rows] = read_csv($subsetFile);
    $index = [];
    foreach ($sourceRows as $r) {
        $index[$r['CIK']][$r['year']] = $r;
    }
    $changes = 0;
    foreach ($rows as &$row) {
        $cik = $row['CIK'] ?? '';
        $year = $row['year'] ?? '';
        if (isset($index[$cik][$year])) {
            foreach ($row as $col => $val) {
                if (array_key_exists($col, $index[$cik][$year])) {
                    $newVal = $index[$cik][$year][$col] ?? '';
                    if ($newVal !== $val) {
                        $row[$col] = $newVal;
                        $changes++;
                    }
                }
            }
        }
    }
    write_csv($subsetFile, $header, $rows);
    logmsg("Synchronized $changes value(s) into $subsetFile.");
}

[$headerMain, $rowsMain] = process_file(FINANCIAL_CSV_FILE);
[$headerSolvent, $rowsSolvent] = process_file(FINANCIAL_CSV_FILE_SOLVENT);

sync_subset($rowsMain, FINANCIAL_CSV_FILE_SUBSET);
sync_subset($rowsSolvent, FINANCIAL_CSV_FILE_SOLVENT_SUBSET);
logmsg('All files processed.');
?>