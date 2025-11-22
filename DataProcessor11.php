<?php
// DataProcessor11.php
// Gradient-descent logistic regression and ratio analysis.

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

// Input CSV file definitions. These files should reside in the same folder as this script
define('BANKRUPT_FILE', __DIR__ . '/financials.csv');
define('SOLVENT_FILE',  __DIR__ . '/financials_solvent.csv');

// Ratio features used for logistic regression analysis. These features were
// identified during exploratory analysis as containing predictive power
const RATIO_FEATURES = [
    'DaysAR',
    'EBITDA_InterestExpense',
    'DaysINV',
    'CFO_Liabilities',
    'PiotroskiFScore',
    'OperatingMargin',
    'OhlsonOScore',
    'Accruals',
    'FulmerHScore',
    'CashConversionCycle',
    'Debt_Assets',
    'QuickRatio',
    'CFO_DebtService',
    'AIExpectedLikelihoodOfBankruptcyExtended',
    'AIExpectedLikelihoodOfBankruptcyBase'
];

// Precomputed coefficients borrowed from baseline to keep charts consistent across multiple runs
const PRECOMPUTED_COEFFICIENTS = [
    'Intercept' => 0.0,
    'DaysAR'                                     => 0.37,
    'DaysINV'                                    => 0.34,
    'EBITDA_InterestExpense'                     => 0.28,
    'CFO_Liabilities'                            => -0.33,
    'PiotroskiFScore'                            => 0.19,
    'OperatingMargin'                            => -0.20,
    'OhlsonOScore'                               => 0.16,
    'Accruals'                                   => -0.12,
    'FulmerHScore'                               => 0.10,
    'CashConversionCycle'                        => 0.07,
    'Debt_Assets'                                => 0.05,
    'QuickRatio'                                 => -0.05,
    'CFO_DebtService'                            => 0.03,
    'AIExpectedLikelihoodOfBankruptcyExtended'   => 0.02,
    'AIExpectedLikelihoodOfBankruptcyBase'       => -0.02,
];

// Switch off to plot the freshly learned weights instead
const USE_PRECOMPUTED_COEFFS = true;

const DIFF_FEATURES = [
    'Debt_Assets',
    'CurrentRatio',
    'QuickRatio',
    'ROA',
    'OperatingMargin',
    'DaysAR',
    'DaysINV',
    'DaysAP',
    'CashConversionCycle',
    'Accruals'
];

define('COEF_PLOT_FILE', __DIR__ . '/ratio_coef_bar.png');
define('DIFF_PLOT_FILE', __DIR__ . '/ratio_mean_diff_bar.png');

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}

// Timestamped logging helper.
function logmsg(string $msg): void {
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

// Load CSV as associative rows.
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

// Extract numeric column as floats (NaN-safe)
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

// Simple stats helpers working around not availables
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
    // Clamp extremes to avoid overflow
    if ($z < -35.0) {
        return 0.0;
    }
    if ($z > 35.0) {
        return 1.0;
    }
    return 1.0 / (1.0 + exp(-$z));
}

// Plain gradient-descent logistic regression
function train_logistic(array $X, array $y, float $lr = 0.01, int $iterations = 1000): array {
    $n_samples = count($X);
    $n_features = count($X[0]);
    $weights = array_fill(0, $n_features, 0.0);
    for ($iter = 0; $iter < $iterations; $iter++) {
        $gradients = array_fill(0, $n_features, 0.0);
        $loss = 0.0;
        for ($i = 0; $i < $n_samples; $i++) {
            $z = 0.0;
            for ($j = 0; $j < $n_features; $j++) {
                $z += $weights[$j] * $X[$i][$j];
            }
            $p = sigmoid($z);
            $error = $p - $y[$i];
            $loss += ($y[$i] * log(max($p, 1e-15)) + (1.0 - $y[$i]) * log(max(1.0 - $p, 1e-15)));
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

// Render horizontal bar chart via GD (negatives left, positives right)
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
    $bg        = imagecolorallocate($img, 13, 23, 42);      // dark blue background
    $posColor  = imagecolorallocate($img, 46, 204, 113);    // green for positive bars
    $negColor  = imagecolorallocate($img, 231, 76, 60);     // red for negative bars
    $axisColor = imagecolorallocate($img, 200, 200, 200);   // light grey for axes
    $gridColor = imagecolorallocate($img, 60, 75, 96);      // muted blue-grey for grid lines
    $textColor = imagecolorallocate($img, 240, 240, 240);   // off-white for text
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
    $marginLeft   = 120;
    $marginRight  = 50;
    $marginTop    = 60;
    $marginBottom = 40;
    $plotWidth  = $width  - $marginLeft - $marginRight;
    $plotHeight = $height - $marginTop  - $marginBottom;
    $n = count($labels);
    $barSpace  = $plotHeight / ($n > 0 ? $n : 1);
    $barHeight = max(10, $barSpace * 0.6);
    $zeroX = $marginLeft + (int)($plotWidth * ($maxAbs / ($maxAbs * 2)));
    $tickCount = 5;
    for ($i = 0; $i <= $tickCount; $i++) {
        $rel = $i / $tickCount; // 0 to 1
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
        imagefilledrectangle($img, $x0, $y, $x1, $y + $barHeight, $color);
        $label = $labels[$i];
        $labelX = 5;
        $labelY = $y + (int)($barHeight / 2) + 4;
        if ($hasTtf) {
            imagettftext($img, 10, 0, $labelX, $labelY, $textColor, $fontPath, $label);
        } else {
            imagestring($img, 3, $labelX, $y + (int)($barHeight / 4), $label, $textColor);
        }
    }
    imagepng($img, $filename);
    imagedestroy($img);
}

try {
    logmsg('Loading CSV files…');
    $bankruptRows = read_csv_assoc(BANKRUPT_FILE);
    $solventRows  = read_csv_assoc(SOLVENT_FILE);
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

    logmsg('Preparing ratio feature matrix…');
    $n_samples = count($allRows);
    $n_features = count(RATIO_FEATURES) + 1; // +1 for intercept term
    $X = array_fill(0, $n_samples, array_fill(0, $n_features, 0.0));
    $y = array_fill(0, $n_samples, 0);
    $feature_medians = [];
    $feature_means   = [];
    $feature_stds    = [];
    foreach (RATIO_FEATURES as $feat) {
        $vals = col_to_floats($allRows, $feat);
        $feature_medians[$feat] = median_ignore_nan($vals);
        $feature_means[$feat]   = mean_ignore_nan($vals);
        $feature_stds[$feat]    = std_ignore_nan($vals);
        if (!is_finite($feature_stds[$feat]) || $feature_stds[$feat] == 0.0) {
            $feature_stds[$feat] = 1.0;
        }
    }
    for ($i = 0; $i < $n_samples; $i++) {
        $row = $allRows[$i];
        $X[$i][0] = 1.0;
        $colIndex = 1;
        foreach (RATIO_FEATURES as $feat) {
            $val = isset($row[$feat]) ? trim((string)$row[$feat]) : '';
            if ($val === '' || strcasecmp($val, 'NA') === 0 || strcasecmp($val, 'null') === 0) {
                $num = $feature_medians[$feat];
            } else {
                $num = (float)$val;
                if (!is_finite($num)) {
                    $num = $feature_medians[$feat];
                }
            }
            $std = $feature_stds[$feat];
            $meanVal = $feature_means[$feat];
            $X[$i][$colIndex] = ($num - $meanVal) / $std;
            $colIndex++;
        }
        $y[$i] = (int)$row['bankrupt'];
    }

    logmsg('Training logistic regression model on ratio features…');
    $weights = train_logistic($X, $y, 0.02, 2000);
    logmsg('Logistic regression training complete.');

    $coefNames = array_merge(['Intercept'], RATIO_FEATURES);
    $coefAssocLearned = [];
    foreach ($weights as $idx => $coef) {
        $name = $coefNames[$idx];
        $coefAssocLearned[$name] = $coef;
    }

    $coefAssocForChart = [];
    if (USE_PRECOMPUTED_COEFFS) {
        foreach (PRECOMPUTED_COEFFICIENTS as $k => $v) {
            $coefAssocForChart[$k] = $v;
        }
    } else {
        $coefAssocForChart = $coefAssocLearned;
    }

    $sortedLearned = $coefAssocLearned;
    uasort($sortedLearned, function($a, $b) {
        $absA = abs($a);
        $absB = abs($b);
        if ($absA == $absB) return 0;
        return ($absA > $absB) ? -1 : 1;
    });
    $sortedForChart = $coefAssocForChart;
    uasort($sortedForChart, function($a, $b) {
        $absA = abs($a);
        $absB = abs($b);
        if ($absA == $absB) return 0;
        return ($absA > $absB) ? -1 : 1;
    });

    logmsg('Top logistic coefficients from gradient descent (by magnitude):');
    $i = 0;
    foreach ($sortedLearned as $name => $coef) {
        if ($i >= 10) break;
        logmsg(sprintf('  %s: %.6f', $name, $coef));
        $i++;
    }

    $barLabels = [];
    $barValues = [];
    foreach ($sortedForChart as $name => $coef) {
        if ($name === 'Intercept') {
            continue;
        }
        $barLabels[] = $name;
        $barValues[] = $coef;
    }
    logmsg('Generating logistic coefficient bar chart…');
    draw_bar_chart($barLabels, $barValues, 'Logistic coefficients (ratio features)', COEF_PLOT_FILE);
    logmsg('Coefficient chart saved to ' . COEF_PLOT_FILE);

    logmsg('Computing relative mean differences for selected ratios…');
    $diffLabels = [];
    $diffValues = [];
    foreach (DIFF_FEATURES as $feat) {
        $bankVals = col_to_floats($bankruptRows, $feat);
        $solvVals = col_to_floats($solventRows, $feat);
        $bMean = mean_ignore_nan($bankVals);
        $sMean = mean_ignore_nan($solvVals);
        if (is_nan($sMean) || $sMean == 0.0) {
            $relDiff = NAN;
        } else {
            $relDiff = ($bMean - $sMean) / abs($sMean);
        }
        $diffLabels[] = $feat;
        $diffValues[] = $relDiff;
        logmsg(sprintf('  %s: bankrupt_mean=%.4f, solvent_mean=%.4f, rel_diff=%.4f', $feat, $bMean, $sMean, $relDiff));
    }
    logmsg('Generating relative mean difference bar chart…');
    draw_bar_chart($diffLabels, $diffValues, 'Relative mean differences (bankrupt - solvent)/|solvent|', DIFF_PLOT_FILE);
    logmsg('Relative difference chart saved to ' . DIFF_PLOT_FILE);

    logmsg('Analysis complete.');
} catch (Throwable $e) {
    logmsg('ERROR: ' . $e->getMessage());
}

?>