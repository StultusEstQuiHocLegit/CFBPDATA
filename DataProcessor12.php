<?php
// DataProcessor12.php
// Logistic regression analysis for the 2024 bankruptcy subset.

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

define('BANKRUPT_FILE_2024', __DIR__ . '/financials_subset.csv');
define('SOLVENT_FILE_2024',  __DIR__ . '/financials_solvent_subset.csv');

const SELECTED_FEATURES_2024 = [
    'CFO_Liabilities',
    'AIExpectedLikelihoodOfBankruptcyAnnualReportStrongerModelGPT',
    'LongTermDebtNoncurrent',
    'CFO_DebtService',
    'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPT',
    'PaymentsOfDividends',
    'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROKExplanation',
    'NoncurrentAssets',
    'Goodwill',
    'CommonStockValue',
    'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPTExplanation',
    'ShortTermBorrowings',
    'PreferredStockDividendsAndOtherAdjustments',
    'RepaymentsOfDebt',
    'IntangibleAssetsNetExcludingGoodwill',
    'NetIncomeLoss',
    'AIExpectedLikelihoodOfBankruptcyAnnualReport',
    'EarningsPerShareDiluted',
    'AIExpectedLikelihoodOfBankruptcyBase',
    'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROK',
    'SellingGeneralAndAdministrativeExpense',
    'NetCashProvidedByUsedInFinancingActivities',
    'SolvencyIndicatingWordsCount',
    'BankruptcyIndicatingWordsCount',
    'TotalCharactersCount',
    'CurrentRatio',
    'ProceedsFromIssuanceOfDebt',
    'DepreciationAndAmortization',
    'AIExpectedLikelihoodOfBankruptcyAnnualReportMoreCharacters',
    'AIExpectedLikelihoodOfBankruptcyAnnualReportStrongerModelGPTExplanation'
];

const DIFF_FEATURES_2024 = [
    'CFO_Liabilities',
    'LongTermDebtNoncurrent',
    'CFO_DebtService',
    'NoncurrentAssets',
    'Goodwill',
    'CommonStockValue',
    'NetIncomeLoss',
    'PaymentsOfDividends',
    'ShortTermBorrowings',
    'RepaymentsOfDebt',
    'SolvencyIndicatingWordsCount',
    'BankruptcyIndicatingWordsCount',
    'AIExpectedLikelihoodOfBankruptcyAnnualReport',
    'AIExpectedLikelihoodOfBankruptcyBase'
];

define('COEF_PLOT_FILE_2024', __DIR__ . '/coef_bar_subset.png');
define('DIFF_PLOT_FILE_2024', __DIR__ . '/diff_bar_subset.png');

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}

function logmsg(string $msg): void {
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

function read_csv_assoc(string $path): array {
    $rows = [];
    if (!file_exists($path)) {
        throw new RuntimeException("CSV file not found: $path");
    }
    if (!($fh = fopen($path, 'r'))) {
        throw new RuntimeException("Cannot open $path");
    }
    $headers = null;
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if ($row === null) {
            continue;
        }
        $nonEmpty = false;
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') {
                $nonEmpty = true;
                break;
            }
        }
        if (!$nonEmpty) {
            continue;
        }
        if ($headers === null) {
            $headers = array_map('trim', $row);
            continue;
        }
        $assoc = [];
        foreach ($headers as $i => $h) {
            if ($h === '' || $h === null) {
                continue;
            }
            $assoc[$h] = $row[$i] ?? null;
        }
        if (!empty($assoc)) {
            $rows[] = $assoc;
        }
    }
    fclose($fh);
    return $rows;
}

function col_to_floats(array $rows, string $key): array {
    $out = [];
    foreach ($rows as $r) {
        $val = isset($r[$key]) ? trim((string)$r[$key]) : '';
        if ($val === '' || strcasecmp($val, 'NA') === 0 || strcasecmp($val, 'null') === 0) {
            $out[] = NAN;
        } else {
            $f = (float)$val;
            $out[] = is_finite($f) ? $f : NAN;
        }
    }
    return $out;
}

function mean_ignore_nan(array $arr): float {
    $sum = 0.0;
    $count = 0;
    foreach ($arr as $v) {
        if (is_nan($v) || !is_finite($v)) {
            continue;
        }
        $sum += $v;
        $count++;
    }
    return $count > 0 ? $sum / $count : NAN;
}

function median_ignore_nan(array $arr): float {
    $filtered = [];
    foreach ($arr as $v) {
        if (!is_nan($v) && is_finite($v)) {
            $filtered[] = $v;
        }
    }
    $n = count($filtered);
    if ($n === 0) {
        return NAN;
    }
    sort($filtered);
    $mid = intdiv($n, 2);
    if ($n % 2 === 1) {
        return $filtered[$mid];
    }
    return ($filtered[$mid - 1] + $filtered[$mid]) / 2.0;
}

function std_ignore_nan(array $arr): float {
    $filtered = [];
    foreach ($arr as $v) {
        if (!is_nan($v) && is_finite($v)) {
            $filtered[] = $v;
        }
    }
    $n = count($filtered);
    if ($n === 0) {
        return NAN;
    }
    $mean = array_sum($filtered) / $n;
    $var = 0.0;
    foreach ($filtered as $v) {
        $diff = $v - $mean;
        $var += $diff * $diff;
    }
    return sqrt($var / $n);
}

function sigmoid(float $z): float {
    if ($z < -35.0) {
        return 0.0;
    }
    if ($z > 35.0) {
        return 1.0;
    }
    return 1.0 / (1.0 + exp(-$z));
}

function train_logistic(array $X, array $y, float $lr = 0.01, int $iterations = 1000): array {
    $n_samples = count($X);
    $n_features = count($X[0]);
    $weights = array_fill(0, $n_features, 0.0);
    for ($iter = 0; $iter < $iterations; $iter++) {
        $gradients = array_fill(0, $n_features, 0.0);
        for ($i = 0; $i < $n_samples; $i++) {
            $z = 0.0;
            for ($j = 0; $j < $n_features; $j++) {
                $z += $weights[$j] * $X[$i][$j];
            }
            $p = sigmoid($z);
            $error = $p - $y[$i];
            for ($j = 0; $j < $n_features; $j++) {
                $gradients[$j] += $error * $X[$i][$j];
            }
        }
        for ($j = 0; $j < $n_features; $j++) {
            $weights[$j] -= $lr * ($gradients[$j] / $n_samples);
        }
    }
    return $weights;
}

function find_available_font(): ?string {
    static $checked = false;
    static $font = null;
    if ($checked) {
        return $font;
    }
    $checked = true;
    $candidates = [
        __DIR__ . '/arial.ttf',
        __DIR__ . '/fonts/arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== null && @is_readable($candidate)) {
            $font = $candidate;
            break;
        }
    }
    return $font;
}

function draw_bar_chart(array $labels, array $values, string $title, string $filename, int $width = 800, int $height = 500): void {
    if (!function_exists('imagecreatetruecolor')) {
        logmsg('GD library is not available; cannot generate image ' . $filename);
        return;
    }
    $img = imagecreatetruecolor($width, $height);
    $bg        = imagecolorallocate($img, 13, 23, 42);      // dark blue
    $posColor  = imagecolorallocate($img, 46, 204, 113);    // green
    $negColor  = imagecolorallocate($img, 231, 76, 60);     // red
    $axisColor = imagecolorallocate($img, 200, 200, 200);   // light grey
    $gridColor = imagecolorallocate($img, 60, 75, 96);      // muted blue-grey
    $textColor = imagecolorallocate($img, 240, 240, 240);   // off‑white
    imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $bg);
    $fontPath = find_available_font();
    $hasTtf = function_exists('imagettftext') && $fontPath !== null;
    $maxAbs = 0.0;
    foreach ($values as $v) {
        $abs = abs($v);
        if ($abs > $maxAbs) {
            $maxAbs = $abs;
        }
    }
    if ($maxAbs <= 0.0) {
        $maxAbs = 1.0;
    }
    $marginLeft   = 160;
    $marginRight  = 50;
    $marginTop    = 60;
    $marginBottom = 40;
    $plotWidth  = $width  - $marginLeft - $marginRight;
    $plotHeight = $height - $marginTop  - $marginBottom;
    $n = count($labels);
    $barSpace  = $plotHeight / ($n > 0 ? $n : 1);
    $barHeight = max(10.0, $barSpace * 0.6);
    $barHeightPx = max(1, (int)round($barHeight));
    $zeroX = $marginLeft + (int)($plotWidth * ($maxAbs / ($maxAbs * 2)));
    $tickCount = 5;
    for ($i = 0; $i <= $tickCount; $i++) {
        $rel = $i / $tickCount;
        $x = $marginLeft + (int)($rel * $plotWidth);
        imageline($img, $x, $marginTop, $x, $marginTop + $plotHeight, $gridColor);
        $value = round((-1.0 * $maxAbs) + 2.0 * $maxAbs * $rel, 2);
        $labelText = sprintf('%.2f', $value);
        if ($hasTtf) {
            $bbox = imagettfbbox(8, 0, $fontPath, $labelText);
            if ($bbox !== false) {
                $textWidth = $bbox[2] - $bbox[0];
                imagettftext($img, 8, 0, $x - (int)($textWidth / 2), $marginTop + $plotHeight + 14, $textColor, $fontPath, $labelText);
                continue;
            }
        }
        imagestring($img, 2, $x - 10, $marginTop + $plotHeight + 4, $labelText, $textColor);
    }
    imageline($img, $zeroX, $marginTop, $zeroX, $marginTop + $plotHeight, $axisColor);
    // Title
    $titleY = 20;
    $titleX = $marginLeft;
    if ($hasTtf) {
        imagettftext($img, 14, 0, $titleX, $titleY + 14, $textColor, $fontPath, $title);
    } else {
        imagestring($img, 5, $titleX, 5, $title, $textColor);
    }
    for ($i = 0; $i < $n; $i++) {
        $v = $values[$i];
        $barLength = ($v / ($maxAbs * 2)) * $plotWidth;
        $y = $marginTop + (int)($barSpace * $i + ($barSpace - $barHeight) / 2);
        $x0 = $zeroX;
        $x1 = $zeroX + (int)$barLength;
        $color = ($v >= 0) ? $posColor : $negColor;
        if ($x1 < $x0) {
            [$x0, $x1] = [$x1, $x0];
        }
        imagefilledrectangle($img, $x0, $y, $x1, $y + $barHeightPx, $color);
        $label = $labels[$i];
        $labelX = 5;
        $labelY = $y + (int)($barHeightPx / 2) + 4;
        if ($hasTtf) {
            imagettftext($img, 10, 0, $labelX, $labelY, $textColor, $fontPath, $label);
        } else {
            imagestring($img, 3, $labelX, $y + (int)($barHeightPx / 4), $label, $textColor);
        }
    }
    imagepng($img, $filename);
    imagedestroy($img);
}

try {
    logmsg('Loading 2024 subset CSV files…');
    $bankruptRows = read_csv_assoc(BANKRUPT_FILE_2024);
    $solventRows  = read_csv_assoc(SOLVENT_FILE_2024);
    logmsg('Loaded ' . count($bankruptRows) . ' bankrupt row(s) and ' . count($solventRows) . ' solvent row(s).');

    $allRows = [];
    foreach ($bankruptRows as $r) {
        $r['bankrupt'] = 1;
        $allRows[] = $r;
    }
    foreach ($solventRows as $r) {
        $r['bankrupt'] = 0;
        $allRows[] = $r;
    }

    logmsg('Computing means, medians and standard deviations for selected features…');
    $feature_medians = [];
    $feature_means   = [];
    $feature_stds    = [];
    foreach (SELECTED_FEATURES_2024 as $feat) {
        $vals = col_to_floats($allRows, $feat);
        $median = median_ignore_nan($vals);
        $mean   = mean_ignore_nan($vals);
        $std    = std_ignore_nan($vals);
        if (!is_finite($std) || $std == 0.0) {
            $std = 1.0;
        }
        $feature_medians[$feat] = $median;
        $feature_means[$feat]   = $mean;
        $feature_stds[$feat]    = $std;
    }

    logmsg('Preparing feature matrix for logistic regression…');
    $n_samples = count($allRows);
    $n_features = count(SELECTED_FEATURES_2024) + 1; // +1 for intercept
    $X = array_fill(0, $n_samples, array_fill(0, $n_features, 0.0));
    $y = array_fill(0, $n_samples, 0);
    for ($i = 0; $i < $n_samples; $i++) {
        $row = $allRows[$i];
        // intercept
        $X[$i][0] = 1.0;
        $colIndex = 1;
        foreach (SELECTED_FEATURES_2024 as $feat) {
            $val = isset($row[$feat]) ? trim((string)$row[$feat]) : '';
            if ($val === '' || strcasecmp($val, 'NA') === 0 || strcasecmp($val, 'null') === 0) {
                $num = $feature_medians[$feat];
            } else {
                $num = (float)$val;
                if (!is_finite($num)) {
                    $num = $feature_medians[$feat];
                }
            }
            // normalise
            $std = $feature_stds[$feat];
            $meanVal = $feature_means[$feat];
            $X[$i][$colIndex] = ($num - $meanVal) / $std;
            $colIndex++;
        }
        $y[$i] = (int)$row['bankrupt'];
    }

    logmsg('Training logistic regression model (gradient descent)…');
    $weights = train_logistic($X, $y, 0.02, 2500);
    logmsg('Training complete.');

    $coefNames = array_merge(['Intercept'], SELECTED_FEATURES_2024);
    $coefAssocLearned = [];
    foreach ($weights as $idx => $coef) {
        $name = $coefNames[$idx] ?? ('feat' . $idx);
        $coefAssocLearned[$name] = $coef;
    }

    $sortedLearned = $coefAssocLearned;
    uasort($sortedLearned, function($a, $b) {
        $absA = abs($a);
        $absB = abs($b);
        if ($absA == $absB) return 0;
        return ($absA > $absB) ? -1 : 1;
    });

    logmsg('Top logistic coefficients for 2024 subset (by magnitude):');
    $shown = 0;
    foreach ($sortedLearned as $name => $coef) {
        if ($name === 'Intercept') continue;
        logmsg(sprintf('  %s: %.6f', $name, $coef));
        $shown++;
        if ($shown >= 15) break;
    }

    $barLabels = [];
    $barValues = [];
    foreach ($sortedLearned as $name => $coef) {
        if ($name === 'Intercept') {
            continue;
        }
        $barLabels[] = $name;
        $barValues[] = $coef;
    }
    logmsg('Rendering coefficient bar chart…');
    draw_bar_chart($barLabels, $barValues, '2024 Logistic Coefficients (selected features)', COEF_PLOT_FILE_2024);
    logmsg('Coefficient chart saved to ' . COEF_PLOT_FILE_2024);

    logmsg('Computing relative mean differences…');
    $diffLabels = [];
    $diffValues = [];
    foreach (DIFF_FEATURES_2024 as $feat) {
        $bankVals = col_to_floats($bankruptRows, $feat);
        $solvVals = col_to_floats($solventRows,  $feat);
        $bMean = mean_ignore_nan($bankVals);
        $sMean = mean_ignore_nan($solvVals);
        if (is_nan($bMean) || is_nan($sMean) || $sMean == 0.0) {
            $relDiff = NAN;
        } else {
            $relDiff = ($bMean - $sMean) / abs($sMean);
        }
        $diffLabels[] = $feat;
        $diffValues[] = $relDiff;
        logmsg(sprintf('  %s: bankrupt_mean=%.4f, solvent_mean=%.4f, rel_diff=%.4f', $feat, $bMean, $sMean, $relDiff));
    }
    logmsg('Rendering relative mean difference bar chart…');
    draw_bar_chart($diffLabels, $diffValues, 'Relative mean differences (bankrupt - solvent)/|solvent|', DIFF_PLOT_FILE_2024);
    logmsg('Relative difference chart saved to ' . DIFF_PLOT_FILE_2024);

    logmsg('Analysis complete.');
} catch (Throwable $e) {
    logmsg('ERROR: ' . $e->getMessage());
}
?>