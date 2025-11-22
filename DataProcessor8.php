<?php
// DataProcessor8.php
// Adds negation-aware weighted textual indicators from annual report HTML filings to financial subset CSV files.

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

define('REPORTS_SUBSET_FILE', __DIR__ . '/reports_subset.csv');
define('REPORTS_SOLVENT_SUBSET_FILE', __DIR__ . '/reports_solvent_subset.csv');
define('FINANCIALS_SUBSET_FILE', __DIR__ . '/financials_subset.csv');
define('FINANCIALS_SOLVENT_SUBSET_FILE', __DIR__ . '/financials_solvent_subset.csv');

define('ADVANCED_COLUMNS_ROBUST', [
    'BankruptcyIndicatingWordsCountAdvancedRobust',
    'SolvencyIndicatingWordsCountAdvancedRobust',
]);

define('NEGATIVE_WEIGHTS', [
    'bankruptcy'    => 2.0,
    'insolvency'    => 2.0,
    'liquidation'   => 2.0,
    'default'       => 2.0,
    'restated'      => 1.5,
    'restatement'   => 1.5,
    'restate'       => 1.5,
    'claims'        => 1.0,
    'litigation'    => 1.5,
    'discontinued'  => 1.0,
    'termination'   => 1.0,
    'terminated'    => 1.0,
    'restructuring' => 1.0,
    'unable'        => 1.0,
    'critical'      => 1.0,
    'penalties'     => 1.0,
    'penalty'       => 1.0,
    'unpaid'        => 1.0,
    'investigation' => 1.0,
    'investigations'=> 1.0,
    'misstatement'  => 1.5,
    'misconduct'    => 2.0,
    'forfeiture'    => 1.5,
    'serious'       => 1.0,
    'allegedly'     => 1.0,
    'noncompliance' => 1.5,
    'deterioration' => 1.0,
    'felony'        => 2.0,
    'loss'          => 1.0,
    'losses'        => 1.0,
    'impairment'    => 1.0,
    'adverse'       => 1.0,
    'against'       => 1.0,
    'risk'          => 0.5,
    'fraud'         => 2.0,
    'bankrupt'      => 2.0,
    'crisis'        => 1.5,
    'claim'         => 1.0,
    'unanticipated' => 1.5,
]);

define('POSITIVE_WEIGHTS', [
    'achieve'     => 1.0,
    'achieved'    => 1.0,
    'attain'      => 1.0,
    'attained'    => 1.0,
    'efficient'   => 1.0,
    'efficiency'  => 1.0,
    'improve'     => 1.0,
    'improved'    => 1.0,
    'improvement' => 1.0,
    'profitable'  => 1.0,
    'profit'      => 1.0,
    'profits'     => 1.0,
    'upturn'      => 1.0,
    'growth'      => 1.0,
    'growing'     => 1.0,
    'increase'    => 0.5,
    'increased'   => 0.5,
    'success'     => 1.0,
    'successful'  => 1.0,
    'robust'      => 1.0,
    'strong'      => 1.0,
    'record'      => 0.5,
    'advantage'   => 1.0,
    'benefit'     => 1.0,
    'benefits'    => 1.0,
]);

define('NEGATION_WORDS', [
    'no', 'not', "weren't", "wasn't", "don't", "didn't", 'never', 'nothing', 'nowhere', 'none', 'neither', 'nobody', 'nor',
    "isn't", "aren't", "haven't", "hasn't", "won't", "wouldn't", "can't", "couldn't", "shouldn't", "mustn't", "ain't",
    'without', 'hardly', 'lack'
]);

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}

logmsg('Starting negation-aware weighted textual indicator processing from HTML filings…');

logmsg('Reading report CSV files …');
[$reportHeader1, $reportRows1] = read_csv(REPORTS_SUBSET_FILE);
[$reportHeader2, $reportRows2] = read_csv(REPORTS_SOLVENT_SUBSET_FILE);

$items = collect_report_items($reportRows1, 1);
$items = array_merge($items, collect_report_items($reportRows2, 2));

if (empty($items)) {
    logmsg('No annual reports found to process.');
    logmsg('Done.');
    exit;
}

logmsg('Processing ' . count($items) . ' annual report(s)…');

$processedItems = [];
foreach ($items as $item) {
    $displayCik = $item['CIK'] !== '' ? $item['CIK'] : $item['normalized_cik'];
    $displayYear = $item['year'] !== '' ? $item['year'] : $item['normalized_year'];
    logmsg('  Downloading report for CIK ' . $displayCik . ' (' . $displayYear . ')…');
    $html = fetch_report($item['link']);
    if ($html === '') {
        logmsg('  Skipping due to download failure.');
        continue;
    }
    $text = html_to_text($html);
    if ($text === '') {
        logmsg('  Empty text after parsing, skipping.');
        continue;
    }
    [$negScore, $posScore] = compute_scores_from_text($text, NEGATIVE_WEIGHTS, POSITIVE_WEIGHTS, NEGATION_WORDS);
    logmsg('  -> negation-aware weighted bankruptcy words: ' . format_score($negScore) . ', weighted solvency words: ' . format_score($posScore) . '.');
    $processedItems[] = $item + [
        'neg_score' => format_score($negScore),
        'pos_score' => format_score($posScore),
    ];
}

if (empty($processedItems)) {
    logmsg('No reports yielded usable metrics. Aborting.');
    logmsg('Done.');
    exit;
}

$metricsMap1 = [];
$metricsMap2 = [];
foreach ($processedItems as $row) {
    $key = $row['normalized_key'];
    if ($key === '') {
        $key = make_financial_key($row['normalized_cik'] ?? '', $row['normalized_year'] ?? '');
    }
    if ($key === '') {
        $key = make_financial_key($row['CIK'] ?? '', $row['year'] ?? '');
    }
    if ($key === '') continue;
    $metrics = [
        'BankruptcyIndicatingWordsCountAdvancedRobust' => (string)$row['neg_score'],
        'SolvencyIndicatingWordsCountAdvancedRobust' => (string)$row['pos_score'],
    ];
    if ($row['source'] === 1) {
        $metricsMap1[$key] = $metrics;
    } else {
        $metricsMap2[$key] = $metrics;
    }
}

if (empty($metricsMap1) && empty($metricsMap2)) {
    logmsg('No metrics matched to financial keys. Nothing to update.');
    logmsg('Done.');
    exit;
}

logmsg('Reading financial subset CSV files …');
[$finHeader1, $finRows1] = read_csv(FINANCIALS_SUBSET_FILE);
[$finHeader2, $finRows2] = read_csv(FINANCIALS_SOLVENT_SUBSET_FILE);

foreach (ADVANCED_COLUMNS_ROBUST as $col) {
    if (!in_array($col, $finHeader1, true)) $finHeader1[] = $col;
    if (!in_array($col, $finHeader2, true)) $finHeader2[] = $col;
}

[$updated1, $overwrittenCells1] = apply_metrics_to_rows($finRows1, $metricsMap1, ADVANCED_COLUMNS_ROBUST);
[$updated2, $overwrittenCells2] = apply_metrics_to_rows($finRows2, $metricsMap2, ADVANCED_COLUMNS_ROBUST);

$expectedTotal = count($metricsMap1) + count($metricsMap2);
$actualTotal = $updated1 + $updated2;
if ($expectedTotal > $actualTotal) {
    logmsg('  Warning: ' . ($expectedTotal - $actualTotal) . ' metric set(s) did not match any financial subset row.');
}

$overwrittenTotal = $overwrittenCells1 + $overwrittenCells2;
if ($overwrittenTotal > 0) {
    logmsg('Overwrote existing metric values in ' . $overwrittenTotal . ' cell(s).');
}

logmsg('Writing updated financial subset CSV files …');
write_csv(FINANCIALS_SUBSET_FILE, $finHeader1, $finRows1);
write_csv(FINANCIALS_SOLVENT_SUBSET_FILE, $finHeader2, $finRows2);

logmsg('Updated ' . $updated1 . ' bankrupt subset row(s) and ' . $updated2 . ' solvent subset row(s).');
logmsg('Done.');

function logmsg(string $msg): void
{
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

function read_csv(string $file): array
{
    $rows = [];
    if (!($fh = fopen($file, 'r'))) {
        return [[], []];
    }
    $header = fgetcsv($fh, 0, ',', '"', '\\');
    if ($header === false) {
        fclose($fh);
        return [[], []];
    }
    while (($data = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = $data[$i] ?? '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return [$header, $rows];
}

function write_csv(string $file, array $header, array $rows): void
{
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

function fetch_report(string $url): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; FurtherWordCountExperiments/1.0; +https://example.com)');
    curl_setopt($ch, CURLOPT_ENCODING, '');
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($resp === false) {
        logmsg('  cURL error while fetching report: ' . curl_error($ch));
        curl_close($ch);
        return '';
    }
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        logmsg('  HTTP status ' . $status . ' when fetching report.');
        return '';
    }
    return $resp !== false ? $resp : '';
}

function html_to_text(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
    $html = preg_replace('#<(table|thead|tbody|tfoot|tr|td|th)\b[^>]*>#i', "\n", $html);
    $html = preg_replace('#<(br|p|div|li|ul|ol|h[1-6])\b[^>]*>#i', "\n", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r/", '', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    $text = preg_replace('/[\t ]+/', ' ', $text);
    $text = preg_replace('/\n[ \t]+/', "\n", $text);
    return trim($text);
}

function normalize_cik(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    $normalized = ltrim($value, '0');
    if ($normalized === '') {
        $normalized = '0';
    }
    return $normalized;
}

function normalize_year(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/\b(\d{4})\b/', $value, $matches)) {
        return $matches[1];
    }
    if (is_numeric($value)) {
        $intYear = (int)$value;
        return $intYear === 0 ? '' : (string)$intYear;
    }
    return $value;
}

function make_financial_key(?string $cik, ?string $year): string
{
    $normCik = normalize_cik($cik);
    $normYear = normalize_year($year);
    if ($normCik === '' || $normYear === '') return '';
    return $normCik . '|' . $normYear;
}

function build_possible_financial_keys(array $row): array
{
    $keys = [];
    $keys[] = make_financial_key($row['CIK'] ?? '', $row['year'] ?? '');
    if (isset($row['EntityCentralIndexKey'])) {
        $keys[] = make_financial_key($row['EntityCentralIndexKey'], $row['year'] ?? '');
    }
    $keys = array_filter($keys, function ($value) {
        return $value !== '';
    });
    return array_values(array_unique($keys));
}

function collect_report_items(array $reportRows, int $source): array
{
    $items = [];
    foreach ($reportRows as $idx => $row) {
        $link = trim($row['AnnualReportLink'] ?? '');
        if ($link === '') continue;
        $rawCik = (string)($row['CIK'] ?? '');
        $rawYear = (string)($row['year'] ?? '');
        $items[] = [
            'source' => $source,
            'index' => $idx,
            'CIK' => $rawCik,
            'year' => $rawYear,
            'normalized_cik' => normalize_cik($rawCik),
            'normalized_year' => normalize_year($rawYear),
            'normalized_key' => make_financial_key($rawCik, $rawYear),
            'link' => $link,
        ];
    }
    return $items;
}

function tokens_from_text(string $text): array
{
    $lower = safe_strtolower($text);
    $lower = str_replace(["\u{2019}", "\u{2018}"], "'", $lower);
    $tokens = preg_split("/[^a-z']+/u", $lower, -1, PREG_SPLIT_NO_EMPTY);
    if ($tokens === false) {
        return [];
    }
    return $tokens;
}

function compute_scores_from_text(string $text, array $negativeWeights, array $positiveWeights, array $negationWords): array
{
    $tokens = tokens_from_text($text);
    $negatedPositions = [];
    foreach ($tokens as $idx => $token) {
        if (in_array($token, $negationWords, true)) {
            if (isset($tokens[$idx + 1])) {
                $negatedPositions[$idx + 1] = true;
            }
            if (isset($tokens[$idx + 2])) {
                $negatedPositions[$idx + 2] = true;
            }
        }
    }

    $negScore = 0.0;
    $posScore = 0.0;
    foreach ($tokens as $idx => $token) {
        if (isset($negatedPositions[$idx])) {
            continue;
        }
        if (isset($negativeWeights[$token])) {
            $negScore += $negativeWeights[$token];
        }
        if (isset($positiveWeights[$token])) {
            $posScore += $positiveWeights[$token];
        }
    }
    return [$negScore, $posScore];
}

function format_score(float $score): string
{
    $formatted = number_format($score, 4, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}

function apply_metrics_to_rows(array &$rows, array $metricsMap, array $newColumns): array
{
    $updated = 0;
    $overwrittenCells = 0;
    foreach ($rows as &$row) {
        $possibleKeys = build_possible_financial_keys($row);
        foreach ($possibleKeys as $key) {
            if (!isset($metricsMap[$key])) {
                continue;
            }
            foreach ($newColumns as $col) {
                $newValue = (string)($metricsMap[$key][$col] ?? '');
                $currentValue = array_key_exists($col, $row) ? (string)$row[$col] : '';
                if ($currentValue !== $newValue) {
                    if ($currentValue !== '') {
                        $overwrittenCells++;
                    }
                    $row[$col] = $newValue;
                } else {
                    $row[$col] = $newValue;
                }
            }
            $updated++;
            break;
        }
    }
    unset($row);
    return [$updated, $overwrittenCells];
}

function safe_strtolower(string $text): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }
    return strtolower($text);
}