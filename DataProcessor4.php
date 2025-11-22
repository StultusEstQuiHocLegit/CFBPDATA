<?php
// DataProcessor4.php
// Compares AI bankruptcy signals across bankrupt and solvent subsets.

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

define('BANKRUPT_CSV_FILE', __DIR__ . '/financials_subset.csv');
define('SOLVENT_CSV_FILE', __DIR__ . '/financials_solvent_subset.csv');

const NUMERIC_SCORE_COLUMNS = [
    'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPT'      => 'GPT-Base',
    'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGPT'  => 'GPT-Extended',
    'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROK'     => 'GROK-Base',
    'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROK' => 'GROK-Extended',
];

const EXPLANATION_COLUMNS = [
    'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPT'      => 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPTExplanation',
    'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGPT'  => 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGPTExplanation',
    'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROK'     => 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROKExplanation',
    'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROK' => 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROKExplanation',
];

const FOCUS_TERMS = ['positive','negative','leverage','liquidity','risk','altman','ohlson','coverage','cash','loss','profit','roa','tlta','current','ratio'];

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}

logmsg('Starting AI bankruptcy comparison…');

$bankruptRows = read_csv_assoc(BANKRUPT_CSV_FILE);
$solventRows = read_csv_assoc(SOLVENT_CSV_FILE);

logmsg('Loaded ' . count($bankruptRows) . ' bankrupt row(s) and ' . count($solventRows) . ' solvent row(s).');

$results = build_results($bankruptRows, $solventRows);

logmsg('Analysis ready. Detailed results follow.');
log_results($results);
logmsg('All comparisons complete.');

function logmsg(string $msg): void {
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

function log_results(array $results): void {
    foreach ($results as $key => $value) {
        if (is_array($value)) {
            logmsg($key . ':');
            log_array($value, 1);
        } else {
            logmsg($key . ': ' . format_scalar($value));
        }
    }
}

function log_array(array $data, int $indentLevel): void {
    $prefix = str_repeat('  ', $indentLevel);
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (empty($value)) {
                logmsg($prefix . $key . ': (empty)');
            } else {
                logmsg($prefix . $key . ':');
                log_array($value, $indentLevel + 1);
            }
        } else {
            logmsg($prefix . $key . ': ' . format_scalar($value));
        }
    }
}

function format_scalar($value): string {
    if (is_float($value)) {
        if (!is_finite($value)) {
            return 'NaN';
        }
        $formatted = number_format($value, 4, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        if ($formatted === '' || $formatted === '-0') {
            $formatted = '0';
        }
        return $formatted;
    }
    if (is_int($value)) {
        return (string)$value;
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($value === null) {
        return 'null';
    }
    return (string)$value;
}

function build_results(array $bankruptRows, array $solventRows): array {
    logmsg('Computing per-model metrics…');
    $out = [];
    foreach (NUMERIC_SCORE_COLUMNS as $numCol => $label) {
        $pos = col_as_floats($bankruptRows, $numCol);
        $neg = col_as_floats($solventRows, $numCol);

        $metrics = compute_metrics($pos, $neg, 50.0);
        $rwBankrupt = right_vs_wrong($bankruptRows, $numCol, true, EXPLANATION_COLUMNS[$numCol], FOCUS_TERMS);
        $rwSolvent = right_vs_wrong($solventRows, $numCol, false, EXPLANATION_COLUMNS[$numCol], FOCUS_TERMS);

        $exBankrupt = explain_stats(array_column($bankruptRows, EXPLANATION_COLUMNS[$numCol]), FOCUS_TERMS);
        $exSolvent = explain_stats(array_column($solventRows, EXPLANATION_COLUMNS[$numCol]), FOCUS_TERMS);

        $out[$label] = [
            'means' => [
                'bankrupt_mean' => mean($pos),
                'solvent_mean' => mean($neg),
            ],
            'AUC' => $metrics['auc'],
            'acc@50' => $metrics['acc'],
            'TPR@50' => $metrics['tpr'],
            'TNR@50' => $metrics['tnr'],
            'bal_acc' => $metrics['bal_acc'],
            'bankrupt_right_wrong' => $rwBankrupt,
            'solvent_right_wrong' => $rwSolvent,
            'explanations' => [
                'bankrupt' => $exBankrupt,
                'solvent' => $exSolvent,
            ],
        ];
    }

    logmsg('Computing cross-model correlations…');
    $out['correlations'] = compute_correlations($bankruptRows, $solventRows);

    return $out;
}

function read_csv_assoc(string $path): array {
    $rows = [];
    if (!($fh = fopen($path, 'r'))) {
        throw new RuntimeException("Cannot open $path");
    }
    $headers = null;
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (row_is_empty($row)) {
            continue;
        }

        if ($headers === null) {
            $headers = $row;
            continue;
        }

        $assoc = [];
        foreach ($headers as $i => $h) {
            if ($h === null) {
                continue;
            }
            $key = trim((string)$h);
            if ($key === '') {
                continue;
            }
            $assoc[$key] = $row[$i] ?? null;
        }

        if (!empty($assoc)) {
            $rows[] = $assoc;
        }
    }

    fclose($fh);
    return $rows;
}

function row_is_empty($row): bool {
    if ($row === null) {
        return true;
    }
    foreach ($row as $value) {
        if ($value === null) {
            continue;
        }
        if (trim((string)$value) !== '') {
            return false;
        }
    }
    return true;
}

function col_as_floats(array $rows, string $key): array {
    $out = [];
    foreach ($rows as $r) {
        $value = isset($r[$key]) ? trim((string)$r[$key]) : '';
        if ($value === '' || strcasecmp($value, 'NA') === 0 || strcasecmp($value, 'null') === 0) {
            $out[] = NAN;
        } else {
            $out[] = (float)$value;
        }
    }
    return array_values(array_filter($out, fn($x) => is_finite($x)));
}

function mean(array $arr): float {
    $n = count($arr);
    if ($n === 0) {
        return NAN;
    }
    return array_sum($arr) / $n;
}

function compute_metrics(array $pos, array $neg, float $threshold): array {
    $auc = auc_mann_whitney($pos, $neg);
    $tp = 0;
    $fn = 0;
    $tn = 0;
    $fp = 0;
    foreach ($pos as $score) {
        if ($score >= $threshold) {
            $tp++;
        } else {
            $fn++;
        }
    }
    foreach ($neg as $score) {
        if ($score < $threshold) {
            $tn++;
        } else {
            $fp++;
        }
    }
    $total = count($pos) + count($neg);
    $acc = $total > 0 ? ($tp + $tn) / $total : NAN;
    $tpr = count($pos) > 0 ? $tp / count($pos) : NAN;
    $tnr = count($neg) > 0 ? $tn / count($neg) : NAN;
    $bal = ($tpr + $tnr) / 2.0;
    return ['auc' => $auc, 'acc' => $acc, 'tpr' => $tpr, 'tnr' => $tnr, 'bal_acc' => $bal];
}

function auc_mann_whitney(array $pos, array $neg): float {
    $scores = [];
    foreach ($pos as $s) {
        $scores[] = ['s' => $s, 'y' => 1];
    }
    foreach ($neg as $s) {
        $scores[] = ['s' => $s, 'y' => 0];
    }
    usort($scores, function ($a, $b) {
        if ($a['s'] == $b['s']) return 0;
        return ($a['s'] < $b['s']) ? -1 : 1;
    });

    $n = count($scores);
    if ($n === 0) {
        return NAN;
    }
    $ranks = array_fill(0, $n, 0.0);
    $i = 0;
    while ($i < $n) {
        $j = $i;
        while ($j + 1 < $n && $scores[$j + 1]['s'] == $scores[$i]['s']) {
            $j++;
        }
        $avg = ($i + $j + 2) / 2.0;
        for ($k = $i; $k <= $j; $k++) {
            $ranks[$k] = $avg;
        }
        $i = $j + 1;
    }

    $rankSumPos = 0.0;
    $nPos = 0;
    $nNeg = 0;
    for ($k = 0; $k < $n; $k++) {
        if ($scores[$k]['y'] == 1) {
            $rankSumPos += $ranks[$k];
            $nPos++;
        } else {
            $nNeg++;
        }
    }
    if ($nPos === 0 || $nNeg === 0) {
        return NAN;
    }
    return ($rankSumPos - $nPos * ($nPos + 1) / 2.0) / ($nPos * $nNeg);
}

function right_vs_wrong(array $rows, string $numCol, bool $isBankrupt, string $explCol, array $terms): array {
    $correct = [];
    $wrong = [];
    foreach ($rows as $r) {
        if (!isset($r[$numCol])) {
            continue;
        }
        $score = (float)$r[$numCol];
        $predictedBankrupt = ($score >= 50.0);
        $truth = $isBankrupt;
        $ok = ($predictedBankrupt === $truth);
        $text = $r[$explCol] ?? '';
        if ($ok) {
            $correct[] = ['s' => $score, 't' => $text];
        } else {
            $wrong[] = ['s' => $score, 't' => $text];
        }
    }

    $meanCorrect = mean(array_map(fn($x) => $x['s'], $correct));
    $meanWrong = mean(array_map(fn($x) => $x['s'], $wrong));
    $termsCorrect = count_terms(array_map(fn($x) => $x['t'], $correct), $terms);
    $termsWrong = count_terms(array_map(fn($x) => $x['t'], $wrong), $terms);

    return [
        'correct_n' => count($correct),
        'wrong_n' => count($wrong),
        'mean_correct' => $meanCorrect,
        'mean_wrong' => $meanWrong,
        'terms_correct' => $termsCorrect,
        'terms_wrong' => $termsWrong,
    ];
}

function explain_stats(array $texts, array $terms): array {
    $n = count($texts);
    $sum = 0;
    foreach ($texts as $t) {
        $t = trim((string)$t);
        if ($t === '') {
            continue;
        }
        $sum += count(preg_split('/\s+/', $t));
    }
    $avgWords = $n > 0 ? $sum / $n : 0.0;
    return [
        'avg_words' => $avgWords,
        'term_counts' => count_terms($texts, $terms),
    ];
}

function count_terms(array $texts, array $terms): array {
    $joined = mb_strtolower(implode(' ', array_map(fn($t) => (string)$t, $texts)), 'UTF-8');
    $counts = [];
    foreach ($terms as $term) {
        $counts[$term] = substr_count($joined, $term);
    }
    return $counts;
}

function compute_correlations(array $bankruptRows, array $solventRows): array {
    return [
        'Base_bankrupt_GPT_vs_GROK' => pearson(
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPT'),
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROK')
        ),
        'Base_solvent_GPT_vs_GROK' => pearson(
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPT'),
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROK')
        ),
        'Extended_bankrupt_GPT_vs_GROK' => pearson(
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGPT'),
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROK')
        ),
        'Extended_solvent_GPT_vs_GROK' => pearson(
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGPT'),
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROK')
        ),
        'GPT_bankrupt_Base_vs_Extended' => pearson(
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPT'),
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGPT')
        ),
        'GPT_solvent_Base_vs_Extended' => pearson(
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGPT'),
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGPT')
        ),
        'GROK_bankrupt_Base_vs_Extended' => pearson(
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROK'),
            col_as_floats($bankruptRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROK')
        ),
        'GROK_solvent_Base_vs_Extended' => pearson(
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyBaseStrongerModelGROK'),
            col_as_floats($solventRows, 'AIExpectedLikelihoodOfBankruptcyExtendedStrongerModelGROK')
        ),
    ];
}

function pearson(array $a, array $b): float {
    $n = min(count($a), count($b));
    if ($n === 0) {
        return NAN;
    }

    $xa = [];
    $xb = [];
    for ($i = 0; $i < $n; $i++) {
        $va = (float)$a[$i];
        $vb = (float)$b[$i];
        if (!is_finite($va) || !is_finite($vb)) {
            continue;
        }
        $xa[] = $va;
        $xb[] = $vb;
    }

    $n = count($xa);
    if ($n === 0) {
        return NAN;
    }

    $ma = mean($xa);
    $mb = mean($xb);
    $num = 0.0;
    $da = 0.0;
    $db = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $aa = $xa[$i] - $ma;
        $bb = $xb[$i] - $mb;
        $num += $aa * $bb;
        $da += $aa * $aa;
        $db += $bb * $bb;
    }
    $den = sqrt($da) * sqrt($db);
    if ($den == 0.0) {
        return NAN;
    }
    return $num / $den;
}
?>