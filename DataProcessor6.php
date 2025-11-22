<?php
// DataProcessor6.php
// Adds textual indicators from annual reports to financial subset CSV files.

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

define('REPORTS_SUBSET_FILE', __DIR__ . '/reports_subset.csv');
define('REPORTS_SOLVENT_SUBSET_FILE', __DIR__ . '/reports_solvent_subset.csv');
define('FINANCIALS_SUBSET_FILE', __DIR__ . '/financials_subset.csv');
define('FINANCIALS_SOLVENT_SUBSET_FILE', __DIR__ . '/financials_solvent_subset.csv');

define('BANKRUPTCY_WORDS', [
    'substantial doubt',
    'going concern',
    'going-concern',
    'ability to continue as a going concern',
    'going concern uncertainty',
    'going concern warning',
    'going concern qualification',
    'material uncertainty',
    'adverse conditions and events',
    'doubt about our ability to continue',
    'substantial doubt exists',
    'significant doubt exists',
    'unable to meet obligations as they become due',
    'inability to meet obligations',
    'recurring losses',
    'negative operating cash flows',
    'working capital deficit',
    'working capital deficiency',
    'substantial working capital deficit',
    'liquidity crisis',
    'severe liquidity',
    'liquidity constraints',
    'refinancing risk',
    'covenant breach',
    'covenant default',
    'event of default',
    'payment default',
    'missed interest payment',
    'missed debt payment',
    'arrears of interest',
    'delinquent interest',
    'non-compliance with financial covenants',
    'not in compliance with covenants',
    'waiver of covenants',
    'forbearance agreement',
    'restructuring support agreement',
    'debt restructuring',
    'debt-for-equity swap',
    'asset sales required to fund operations',
    'court-supervised',
    'reorganization plan',
    'plan of reorganization',
    'prepackaged plan',
    'pre-negotiated plan',
    'debtor-in-possession',
    'DIP financing',
    'petition for relief',
    'voluntary petition',
    'bankruptcy',
    'insolvency',
    'insolvent',
    'chapter 11',
    'chapter 7',
    'liquidation',
    'wind-down',
    'cease operations',
    'discontinue operations',
    'delisting notice',
    'noncompliance with listing requirements',
    'acceleration of indebtedness',
    'cross-default',
    'limited cash runway',
    'going concern basis may not be appropriate',
    'going concern disclosure',
]);

define('SOLVENCY_WORDS', [
    'adequate liquidity',
    'ample liquidity',
    'robust liquidity',
    'healthy liquidity',
    'liquidity remains strong',
    'strong liquidity position',
    'strong cash position',
    'sufficient cash resources',
    'financial flexibility',
    'ample financial flexibility',
    'positive operating cash flow',
    'net cash provided by operating activities',
    'free cash flow',
    'positive free cash flow',
    'cash flow generation',
    'strong cash generation',
    'operating cash flows will fund',
    'sufficient liquidity to meet obligations',
    'ability to meet obligations as they become due',
    'we believe our cash flows will be sufficient',
    'sufficient to meet our obligations for at least the next 12 months',
    'liquidity sources include',
    'access to capital markets',
    'continued access to capital markets',
    'investment grade',
    'investment-grade credit rating',
    'credit rating upgrade',
    'positive outlook',
    'stable outlook',
    'in compliance with financial covenants',
    'covenant compliance',
    'no covenant breaches',
    'significant covenant headroom',
    'headroom under covenants',
    'no material debt maturities until',
    'no significant debt maturities until',
    'extended debt maturities',
    'refinanced on favorable terms',
    'committed revolving credit facility',
    'unused revolving credit facility',
    'undrawn revolver',
    'repaid debt',
    'reduced leverage',
    'deleveraging',
    'net leverage declined',
    'interest coverage of',
    'strong balance sheet',
    'solid balance sheet',
    'sustained profitability',
    'consistent profitability',
    'profitable operations',
    'net income',
    'earnings growth',
    'maintained dividend',
    'increased dividend',
    'share repurchases',
    'return of capital',
    'positive working capital',
    'working capital surplus',
    'unqualified audit opinion',
    'no going concern uncertainty',
    'no substantial doubt exists',
    'cash from operations exceeded capital expenditures',
    'net cash from operating activities increased',
    'committed credit facilities',
    'diversified funding sources',
    'well-capitalized',
    'capital ratios above regulatory requirements',
]);

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}

logmsg('Starting annual report textual indicator processing…');

function logmsg(string $msg): void {
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

function read_csv(string $file): array {
    $rows = [];
    if (!($fh = fopen($file, 'r'))) return [[], []];
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

function fetch_report(string $url): string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; DataProcessor6/1.0; +https://example.com)');
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

function html_to_text(string $html): string {
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

function normalize_cik(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    $normalized = ltrim($value, '0');
    if ($normalized === '') {
        $normalized = '0';
    }
    return $normalized;
}

function normalize_year(?string $value): string {
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

function make_financial_key(?string $cik, ?string $year): string {
    $normCik = normalize_cik($cik);
    $normYear = normalize_year($year);
    if ($normCik === '' || $normYear === '') return '';
    return $normCik . '|' . $normYear;
}

function text_length(string $text): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    return strlen($text);
}

function safe_strtolower(string $text): string {
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }
    return strtolower($text);
}

function count_indicator_words(string $text, array $words): int {
    $count = 0;
    $lowerTextCache = null;
    foreach ($words as $word) {
        $word = trim($word);
        if ($word === '') continue;
        $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
        $matches = [];
        $result = preg_match_all($pattern, $text, $matches);
        if ($result === false) {
            if ($lowerTextCache === null) {
                $lowerTextCache = safe_strtolower($text);
            }
            $lowerWord = safe_strtolower($word);
            $count += substr_count($lowerTextCache, $lowerWord);
        } else {
            $count += count($matches[0] ?? []);
        }
    }
    return $count;
}

function build_possible_financial_keys(array $row): array {
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

function apply_metrics_to_rows(array &$rows, array $metricsMap, array $newColumns): array {
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

logmsg('Reading report CSV files …');
[$reportHeader1, $reportRows1] = read_csv(REPORTS_SUBSET_FILE);
[$reportHeader2, $reportRows2] = read_csv(REPORTS_SOLVENT_SUBSET_FILE);

$items = [];
foreach ($reportRows1 as $idx => $row) {
    $link = trim($row['AnnualReportLink'] ?? '');
    if ($link === '') continue;
    $rawCik = (string)($row['CIK'] ?? '');
    $rawYear = (string)($row['year'] ?? '');
    $items[] = [
        'source' => 1,
        'index' => $idx,
        'CIK' => $rawCik,
        'year' => $rawYear,
        'normalized_cik' => normalize_cik($rawCik),
        'normalized_year' => normalize_year($rawYear),
        'normalized_key' => make_financial_key($rawCik, $rawYear),
        'link' => $link,
    ];
}
foreach ($reportRows2 as $idx => $row) {
    $link = trim($row['AnnualReportLink'] ?? '');
    if ($link === '') continue;
    $rawCik = (string)($row['CIK'] ?? '');
    $rawYear = (string)($row['year'] ?? '');
    $items[] = [
        'source' => 2,
        'index' => $idx,
        'CIK' => $rawCik,
        'year' => $rawYear,
        'normalized_cik' => normalize_cik($rawCik),
        'normalized_year' => normalize_year($rawYear),
        'normalized_key' => make_financial_key($rawCik, $rawYear),
        'link' => $link,
    ];
}

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
    $charCount = text_length($text);
    $bankruptcyCount = count_indicator_words($text, BANKRUPTCY_WORDS);
    $solvencyCount = count_indicator_words($text, SOLVENCY_WORDS);
    logmsg('  -> characters: ' . $charCount . ', bankruptcy words: ' . $bankruptcyCount . ', solvency words: ' . $solvencyCount . '.');
    $processedItems[] = $item + [
        'characters' => $charCount,
        'bankruptcy_words' => $bankruptcyCount,
        'solvency_words' => $solvencyCount,
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
        'BankruptcyIndicatingWordsCount' => (string)$row['bankruptcy_words'],
        'SolvencyIndicatingWordsCount' => (string)$row['solvency_words'],
        'TotalCharactersCount' => (string)$row['characters'],
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

$newColumns = [
    'BankruptcyIndicatingWordsCount',
    'SolvencyIndicatingWordsCount',
    'TotalCharactersCount',
];
foreach ($newColumns as $col) {
    if (!in_array($col, $finHeader1, true)) $finHeader1[] = $col;
    if (!in_array($col, $finHeader2, true)) $finHeader2[] = $col;
}

[$updated1, $overwrittenCells1] = apply_metrics_to_rows($finRows1, $metricsMap1, $newColumns);
[$updated2, $overwrittenCells2] = apply_metrics_to_rows($finRows2, $metricsMap2, $newColumns);

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