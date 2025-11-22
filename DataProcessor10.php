<?php
// DataProcessor10.php
// Retrieves annual reports from subset CSV files, sends them to the AI, and stores
// the resulting bankruptcy likelihood estimates and explanations back into the
// financial subset CSVs.

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

require_once __DIR__ . '/config.php';

define('REPORTS_SUBSET_FILE', __DIR__ . '/reports_subset.csv');
define('REPORTS_SOLVENT_SUBSET_FILE', __DIR__ . '/reports_solvent_subset.csv');
define('FINANCIALS_SUBSET_FILE', __DIR__ . '/financials_subset.csv');
define('FINANCIALS_SOLVENT_SUBSET_FILE', __DIR__ . '/financials_solvent_subset.csv');

define('BATCH_SIZE', 5);
define('MAX_REPORT_CHARS', 100000);

$TESTING_MODE = false; // Set to true to only run for one batch to test or set to false to process full dataset

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}

logmsg('Starting annual report AI processing…');

function logmsg(string $msg): void {
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

function read_csv(string $file): array {
    $rows = [];
    if (!($fh = fopen($file, 'r'))) return [[], []];
    $header = fgetcsv($fh, 0, ',', '"', '\\');
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
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; DataProcessor5/1.0; +https://example.com)');
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
    return $resp;
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

function truncate_text(string $text, int $limit): string {
    if (strlen($text) <= $limit) return $text;
    $truncated = substr($text, 0, $limit);
    $lastBreak = strrpos($truncated, "\n");
    if ($lastBreak !== false && $lastBreak > $limit - 200) {
        $truncated = substr($truncated, 0, $lastBreak);
    }
    return rtrim($truncated) . "\n… [truncated]";
}

function normalize_explanation(?string $text): string {
    if ($text === null) return '';
    $text = trim($text);
    if ($text === '') return '';
    $text = preg_replace('/\s+/u', ' ', $text);
    if ($text === null) return '';
    if (strlen($text) > 1000) {
        $text = substr($text, 0, 1000) . '…';
    }
    return $text;
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

function call_openai_reports(array $batchRows, string $apiKey, int $batchNumber): ?array {
    $expectedCount = count($batchRows);
    if ($expectedCount === 0) return [];
    logmsg('  Calling AI for batch ' . $batchNumber . ' with ' . $expectedCount . ' report(s)…');

    $sections = [];
    foreach ($batchRows as $idx => $row) {
        $sections[] = '### REPORT ' . ($idx + 1) . "\n" .
            'CIK: ' . $row['CIK'] . "\n" .
            'Year: ' . $row['year'] . "\n" .
            "Report:\n" . $row['text'];
    }
    $inputText = implode("\n\n-----\n\n", $sections);

    $systemPrompt = <<<EOD
# ROLE
You are a financial analyst.

# TASK
For each annual report excerpt you receive, estimate the expected likelihood that the company will go bankrupt within the next year.
Score each report on a scale from 0 (very unlikely) to 100 (very likely) and summarize the main drivers behind your assessment in one short sentence.

# FORMAT
There are exactly $expectedCount report(s). Respond with a compact JSON array that has exactly $expectedCount object(s) in the same order as the reports. Each object must contain two keys:
- "score": the bankruptcy likelihood on the 0-100 scale (number)
- "explanation": a concise string (maximum 300 characters) describing the key reasoning.
Example: [{"score": 42, "explanation": "High leverage but improving cash flow."}]
Return only valid JSON with no additional commentary.
EOD;

    $payload = [
        'model' => 'gpt-5-mini',
        'input' => [
            [
                'role' => 'system',
                'content' => [ ['type' => 'input_text', 'text' => $systemPrompt] ]
            ],
            [
                'role' => 'user',
                'content' => [ ['type' => 'input_text', 'text' => $inputText] ]
            ]
        ],
        'reasoning' => ['effort' => 'minimal'],
        'text' => ['verbosity' => 'low']
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($resp === false) {
        logmsg('  cURL error during AI call: ' . curl_error($ch));
        curl_close($ch);
        logmsg('  AI HTTP status: ' . $status);
        return null;
    }
    curl_close($ch);

    logmsg('  Raw AI response: ' . $resp);

    if ($status < 200 || $status >= 300) {
        logmsg('  HTTP error ' . $status . ': ' . $resp);
        return null;
    }
    $data = json_decode($resp, true);
    if ($data === null) {
        logmsg('  JSON decode error: ' . json_last_error_msg());
        return null;
    }
    if (isset($data['error'])) {
        $err = $data['error'];
        $msg = is_array($err) ? ($err['message'] ?? json_encode($err)) : $err;
        logmsg('  API error: ' . $msg);
        return null;
    }
    $content = '';
    if (isset($data['output_text'])) {
        $content = $data['output_text'];
    } elseif (isset($data['output'])) {
        foreach ($data['output'] as $chunk) {
            if (($chunk['type'] ?? '') === 'message' && isset($chunk['content'])) {
                foreach ($chunk['content'] as $c) {
                    if (isset($c['text'])) {
                        $content = $c['text'];
                        break 2;
                    }
                }
            }
        }
    } elseif (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
    } elseif (isset($data['choices'][0]['text'])) {
        $content = $data['choices'][0]['text'];
    }
    $content = trim($content);
    if ($content === '') {
        logmsg('  Empty response content.');
        return null;
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
        }
    }

    $results = [];
    if (is_array($decoded)) {
        foreach ($decoded as $entry) {
            $score = null;
            $explanation = '';
            if (is_array($entry)) {
                if (isset($entry['score']) && is_numeric($entry['score'])) {
                    $score = (float)$entry['score'];
                } elseif (isset($entry[0]) && is_numeric($entry[0])) {
                    $score = (float)$entry[0];
                }
                if (isset($entry['explanation'])) {
                    $rawExplanation = $entry['explanation'];
                    if (is_array($rawExplanation)) {
                        $rawExplanation = json_encode($rawExplanation);
                    }
                    $explanation = normalize_explanation((string)$rawExplanation);
                } elseif (isset($entry[1]) && !is_array($entry[1])) {
                    $explanation = normalize_explanation((string)$entry[1]);
                }
            } elseif (is_numeric($entry)) {
                $score = (float)$entry;
            }
            $results[] = [
                'score' => $score,
                'explanation' => $explanation,
            ];
        }
    } else {
        logmsg('  Warning: expected JSON array; falling back to numeric parse.');
        $parts = preg_split('/[\s,]+/', $content);
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (!is_numeric($p)) continue;
            $results[] = [
                'score' => (float)$p,
                'explanation' => '',
            ];
        }
    }

    $resultCount = count($results);
    if ($resultCount !== $expectedCount) {
        logmsg('  Parsed ' . $resultCount . ' result(s) (expected ' . $expectedCount . ').');
        if ($resultCount > $expectedCount) {
            $results = array_slice($results, 0, $expectedCount);
        } else {
            while (count($results) < $expectedCount) {
                $results[] = ['score' => null, 'explanation' => ''];
            }
        }
    } else {
        logmsg('  Parsed ' . $resultCount . ' result(s).');
    }

    return $results;
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
        'text' => '',
        'score' => null,
        'explanation' => '',
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
        'text' => '',
        'score' => null,
        'explanation' => '',
    ];
}

if (empty($items)) {
    logmsg('No annual reports found to process.');
    logmsg('Done.');
    exit;
}

if ($TESTING_MODE) {
    $randomKey = array_rand($items);
    $items = [$items[$randomKey]];
    logmsg('Testing mode enabled: processing one random annual report.');
}

logmsg('Fetching ' . count($items) . ' annual report(s)…');
$validIndices = [];
foreach ($items as $idx => &$item) {
    $displayCik = $item['CIK'] !== '' ? $item['CIK'] : $item['normalized_cik'];
    $displayYear = $item['year'] !== '' ? $item['year'] : $item['normalized_year'];
    logmsg('  Downloading report for CIK ' . $displayCik . ' (' . $displayYear . ')…');
    $html = fetch_report($item['link']);
    if ($html === '') {
        logmsg('  Skipping CIK ' . $item['CIK'] . ' due to download failure.');
        continue;
    }
    $text = html_to_text($html);
    if ($text === '') {
        logmsg('  Empty text after parsing for CIK ' . $item['CIK'] . '.');
        continue;
    }
    $origLen = strlen($text);
    $text = truncate_text($text, MAX_REPORT_CHARS);
    if (strlen($text) !== $origLen) {
        logmsg('  Truncated report for CIK ' . $item['CIK'] . ' to ' . strlen($text) . ' characters.');
    }
    $item['text'] = $text;
    $validIndices[] = $idx;
}
unset($item);

if (empty($validIndices)) {
    logmsg('No reports yielded usable text. Aborting.');
    logmsg('Done.');
    exit;
}

shuffle($validIndices);

$batchNumber = 1;
for ($offset = 0; $offset < count($validIndices); $offset += BATCH_SIZE) {
    $slice = array_slice($validIndices, $offset, BATCH_SIZE);
    $batchRows = [];
    foreach ($slice as $idx) {
        $item = $items[$idx];
        $batchRows[] = [
            'CIK' => $item['CIK'] !== '' ? $item['CIK'] : $item['normalized_cik'],
            'year' => $item['year'] !== '' ? $item['year'] : $item['normalized_year'],
            'text' => $item['text'],
        ];
    }
    $aiResults = call_openai_reports($batchRows, $apiKey, $batchNumber);
    if ($aiResults === null) {
        logmsg('  AI call failed for batch ' . $batchNumber . '. Leaving scores empty.');
    } else {
        foreach ($slice as $pos => $itemIdx) {
            $result = $aiResults[$pos] ?? null;
            if (is_array($result)) {
                $items[$itemIdx]['score'] = $result['score'] ?? null;
                $items[$itemIdx]['explanation'] = normalize_explanation($result['explanation'] ?? '');
            }
        }
    }
    if ($TESTING_MODE) break;
    $batchNumber++;
}

$processed = array_filter($items, fn($it) => $it['score'] !== null && $it['score'] !== '');
logmsg('Received scores for ' . count($processed) . ' report(s).');

if ($TESTING_MODE) {
    foreach ($processed as $row) {
        $msg = '  Test score for CIK ' . $row['CIK'] . ' (' . $row['year'] . '): ' . $row['score'];
        $explanation = normalize_explanation($row['explanation'] ?? '');
        if ($explanation !== '') {
            $msg .= ' -> ' . $explanation;
        }
        logmsg($msg);
    }
    logmsg('Testing mode active, not writing any CSV updates.');
    logmsg('Done.');
    exit;
}

if (empty($processed)) {
    logmsg('No AI scores to write. Done.');
    exit;
}

logmsg('Reading financial subset CSV files …');
[$finHeader1, $finRows1] = read_csv(FINANCIALS_SUBSET_FILE);
[$finHeader2, $finRows2] = read_csv(FINANCIALS_SOLVENT_SUBSET_FILE);

$colName = 'AIExpectedLikelihoodOfBankruptcyAnnualReportMoreCharacters';
$colExplanation = 'AIExpectedLikelihoodOfBankruptcyAnnualReportMoreCharactersExplanation';
if (!in_array($colName, $finHeader1, true)) $finHeader1[] = $colName;
if (!in_array($colName, $finHeader2, true)) $finHeader2[] = $colName;
if (!in_array($colExplanation, $finHeader1, true)) $finHeader1[] = $colExplanation;
if (!in_array($colExplanation, $finHeader2, true)) $finHeader2[] = $colExplanation;

$scoreMap1 = [];
$scoreMap2 = [];
foreach ($processed as $row) {
    if (!is_numeric($row['score'])) continue;
    $key = $row['normalized_key'] ?? make_financial_key($row['CIK'] ?? '', $row['year'] ?? '');
    if ($key === '') {
        $fallbackKey = make_financial_key($row['normalized_cik'] ?? '', $row['normalized_year'] ?? '');
        $key = $fallbackKey;
    }
    if ($key === '') continue;
    $mapped = [
        'score' => $row['score'],
        'explanation' => normalize_explanation($row['explanation'] ?? ''),
    ];
    if ($row['source'] === 1) {
        $scoreMap1[$key] = $mapped;
    } else {
        $scoreMap2[$key] = $mapped;
    }
}

$updated1 = 0;
foreach ($finRows1 as &$row) {
    $possibleKeys = [
        make_financial_key($row['CIK'] ?? '', $row['year'] ?? ''),
    ];
    if (!empty($row['EntityCentralIndexKey'] ?? '')) {
        $possibleKeys[] = make_financial_key($row['EntityCentralIndexKey'], $row['year'] ?? '');
    }
    foreach ($possibleKeys as $key) {
        if ($key === '' || !isset($scoreMap1[$key])) continue;
        $row[$colName] = $scoreMap1[$key]['score'];
        $row[$colExplanation] = $scoreMap1[$key]['explanation'];
        $updated1++;
        break;
    }
}
unset($row);

$updated2 = 0;
foreach ($finRows2 as &$row) {
    $possibleKeys = [
        make_financial_key($row['CIK'] ?? '', $row['year'] ?? ''),
    ];
    if (!empty($row['EntityCentralIndexKey'] ?? '')) {
        $possibleKeys[] = make_financial_key($row['EntityCentralIndexKey'], $row['year'] ?? '');
    }
    foreach ($possibleKeys as $key) {
        if ($key === '' || !isset($scoreMap2[$key])) continue;
        $row[$colName] = $scoreMap2[$key]['score'];
        $row[$colExplanation] = $scoreMap2[$key]['explanation'];
        $updated2++;
        break;
    }
}
unset($row);

$totalScores = count($scoreMap1) + count($scoreMap2);
$totalUpdated = $updated1 + $updated2;
if ($totalScores > $totalUpdated) {
    logmsg('  Warning: ' . ($totalScores - $totalUpdated) . ' AI result(s) did not match any financial subset row.');
}

logmsg('Writing updated financial subset CSV files …');
write_csv(FINANCIALS_SUBSET_FILE, $finHeader1, $finRows1);
write_csv(FINANCIALS_SOLVENT_SUBSET_FILE, $finHeader2, $finRows2);

logmsg('Updated ' . $updated1 . ' bankrupt subset row(s) and ' . $updated2 . ' solvent subset row(s).');
logmsg('Done.');