<?php
// DataUpdaterAndCleaner0.php
// Updates CSVs with 10-K/A amendments and cleans outliers using SEC EDGAR company facts.

ini_set('display_errors','1');
error_reporting(E_ALL);
ini_set('memory_limit', '2048M');
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);

const USER_AGENT            = 'CFDataBot/1.0 ([email protected])';
const SUBMISSION_ENDPOINT   = 'https://data.sec.gov/submissions/';
const FINANCIAL_CSV_FILE    = __DIR__ . '/financials.csv';
const FINANCIAL_CSV_SOLVENT = __DIR__ . '/financials_solvent.csv';
const FINANCIAL_SUBSET      = __DIR__ . '/financials_subset.csv';
const FINANCIAL_SUBSET_SOLV = __DIR__ . '/financials_solvent_subset.csv';
const REPORTS_CSV_FILE      = __DIR__ . '/reports.csv';
const REPORTS_CSV_SOLVENT   = __DIR__ . '/reports_solvent.csv';
const REPORTS_SUBSET        = __DIR__ . '/reports_subset.csv';
const REPORTS_SUBSET_SOLV   = __DIR__ . '/reports_solvent_subset.csv';
const MAX_RETRIES           = 5;
const BACKOFF_BASE_MS       = 500;
const POLITE_USLEEP         = 200000;
const CACHE_DIR             = __DIR__ . '/memory/cache';
const CACHE_TTL_SECONDS     = 86400; // 24 hours
const FACTS_CONCURRENCY     = 16;
const FILINGS_CONCURRENCY   = 16;
const XBRL_INDEX_CONCURRENCY = 8;
const XBRL_INSTANCE_CONCURRENCY = 4;

const FINANCIAL_FIELD_MAP = [
    'assets' => 'Assets',
    'CurrentAssets' => 'AssetsCurrent',
    'NoncurrentAssets' => 'AssetsNoncurrent',
    'liabilities' => 'Liabilities',
    'CurrentLiabilities' => 'LiabilitiesCurrent',
    'NoncurrentLiabilities' => 'LiabilitiesNoncurrent',
    'LiabilitiesAndStockholdersEquity' => 'LiabilitiesAndStockholdersEquity',
    'equity' => 'StockholdersEquity',
    'CommonStockValue' => 'CommonStockValue',
    'RetainedEarningsAccumulatedDeficit' => 'RetainedEarningsAccumulatedDeficit',
    'AccumulatedOtherComprehensiveIncomeLoss' => 'AccumulatedOtherComprehensiveIncomeLoss',
    'MinorityInterest' => 'MinorityInterest',
    'revenues' => 'Revenues',
    'SalesRevenueNet' => 'SalesRevenueNet',
    'CostOfGoodsSold' => 'CostOfGoodsSold',
    'GrossProfit' => 'GrossProfit',
    'OperatingExpenses' => 'OperatingExpenses',
    'SellingGeneralAndAdministrativeExpense' => 'SellingGeneralAndAdministrativeExpense',
    'ResearchAndDevelopmentExpense' => 'ResearchAndDevelopmentExpense',
    'OperatingIncomeLoss' => 'OperatingIncomeLoss',
    'InterestExpense' => 'InterestExpense',
    'IncomeBeforeIncomeTaxes' => 'IncomeBeforeIncomeTaxes',
    'IncomeTaxExpenseBenefit' => 'IncomeTaxExpenseBenefit',
    'NetIncomeLoss' => 'NetIncomeLoss',
    'PreferredStockDividendsAndOtherAdjustments' => 'PreferredStockDividendsAndOtherAdjustments',
    'NetIncomeLossAvailableToCommonStockholdersBasic' => 'NetIncomeLossAvailableToCommonStockholdersBasic',
    'EarningsPerShareBasic' => 'EarningsPerShareBasic',
    'EarningsPerShareDiluted' => 'EarningsPerShareDiluted',
    'WeightedAverageNumberOfSharesOutstandingBasic' => 'WeightedAverageNumberOfSharesOutstandingBasic',
    'WeightedAverageNumberOfDilutedSharesOutstanding' => 'WeightedAverageNumberOfDilutedSharesOutstanding',
    'NetCashProvidedByUsedInOperatingActivities' => 'NetCashProvidedByUsedInOperatingActivities',
    'NetCashProvidedByUsedInInvestingActivities' => 'NetCashProvidedByUsedInInvestingActivities',
    'NetCashProvidedByUsedInFinancingActivities' => 'NetCashProvidedByUsedInFinancingActivities',
    'CashAndCashEquivalentsPeriodIncreaseDecrease' => 'CashAndCashEquivalentsPeriodIncreaseDecrease',
    'CashAndCashEquivalentsAtCarryingValue' => 'CashAndCashEquivalentsAtCarryingValue',
    'PaymentsToAcquirePropertyPlantAndEquipment' => 'PaymentsToAcquirePropertyPlantAndEquipment',
    'ProceedsFromIssuanceOfCommonStock' => 'ProceedsFromIssuanceOfCommonStock',
    'PaymentsOfDividends' => 'PaymentsOfDividends',
    'RepaymentsOfDebt' => 'RepaymentsOfDebt',
    'ProceedsFromIssuanceOfDebt' => 'ProceedsFromIssuanceOfDebt',
    'DepreciationAndAmortization' => 'DepreciationAndAmortization',
    'InventoryNet' => 'InventoryNet',
    'AccountsReceivableNetCurrent' => 'AccountsReceivableNetCurrent',
    'AccountsPayableCurrent' => 'AccountsPayableCurrent',
    'Goodwill' => 'Goodwill',
    'IntangibleAssetsNetExcludingGoodwill' => 'IntangibleAssetsNetExcludingGoodwill',
    'PropertyPlantAndEquipmentNet' => 'PropertyPlantAndEquipmentNet',
    'LongTermDebtNoncurrent' => 'LongTermDebtNoncurrent',
    'ShortTermBorrowings' => 'ShortTermBorrowings',
    'IncomeTaxesPayableCurrent' => 'IncomeTaxesPayableCurrent',
    'EntityRegistrantName' => 'EntityRegistrantName',
    'EntityCentralIndexKey' => 'EntityCentralIndexKey',
    'TradingSymbol' => 'TradingSymbol',
    'EntityIncorporationStateCountryCode' => 'EntityIncorporationStateCountryCode',
    'EntityFilerCategory' => 'EntityFilerCategory',
    'DocumentPeriodEndDate' => 'DocumentPeriodEndDate',
    'DocumentFiscalPeriodFocus' => 'DocumentFiscalPeriodFocus',
    'DocumentFiscalYearFocus' => 'DocumentFiscalYearFocus',
    'DocumentType' => 'DocumentType',
    'AmendmentFlag' => 'AmendmentFlag',
    'CurrentFiscalYearEndDate' => 'CurrentFiscalYearEndDate',
];

$runningInCli = (php_sapi_name() === 'cli');
if (!$runningInCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!doctype html><meta charset='utf-8'><style>body{background:#000;color:#0f0;font:14px/1.4 monospace;padding:16px}</style><pre>";
}
logmsg('Starting EDGAR amendment update and cleaning…');

function logmsg(string $msg): void {
    $ts = date('H:i:s');
    echo "[$ts] $msg\n";
    flush();
}

function ensureCacheDir(): void {
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0777, true);
    }
}

function cachePath(string $key): string {
    return CACHE_DIR . '/' . md5($key) . '.json';
}

function cacheRead(string $key): ?array {
    $path = cachePath($key);
    if (!is_file($path)) return null;
    if ((time() - filemtime($path)) > CACHE_TTL_SECONDS) return null;
    $raw = file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return (is_array($data) && array_key_exists('body', $data) && array_key_exists('status', $data)) ? $data : null;
}

function cacheWrite(string $key, array $payload): void {
    ensureCacheDir();
    file_put_contents(cachePath($key), json_encode($payload));
}

function http_get(string $url, string $accept, int $retries = MAX_RETRIES): array {
    $attempt = 0; $status = 0;
    while (true) {
        $attempt++;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . USER_AGENT,
                'Accept: ' . $accept,
                'Accept-Encoding: gzip, deflate'
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($status === 200 && $response !== false) return [$response, $status];
        if ($attempt >= $retries) return [null, $status];
        $delay = BACKOFF_BASE_MS * (2 ** ($attempt-1));
        usleep($delay * 1000);
    }
}

function fetchJsonWithCache(string $url, string $accept = 'application/json'): array {
    $cached = cacheRead($url);
    if ($cached && ($cached['status'] ?? 0) === 200) {
        return $cached;
    }
    [$body, $status] = http_get($url, $accept);
    $payload = ['body' => $body, 'status' => $status];
    if ($status === 200 && $body !== null) {
        cacheWrite($url, $payload);
    }
    return $payload;
}

function http_get_json(string $url, ?int &$status = null, bool $useCache = true): ?array {
    if ($useCache) {
        $result = fetchJsonWithCache($url, 'application/json');
        $body = $result['body'] ?? null;
        $status = $result['status'] ?? 0;
    } else {
        [$body, $status] = http_get($url, 'application/json');
    }
    if ($status !== 200 || $body === null) return null;
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function multiFetchJson(array $requests, string $accept = 'application/json', int $concurrency = 8, int $maxRetries = 3, int $baseDelayMs = 500): array {
    $results = [];
    if (!$requests) return $results;

    $pending = [];
    foreach ($requests as $req) {
        $pending[] = [
            'key' => $req['key'] ?? '',
            'url' => $req['url'] ?? '',
            'attempt' => 0,
            'notBefore' => microtime(true),
        ];
    }

    $mh = curl_multi_init();
    $handles = [];
    $inflight = [];

    $addHandle = function(array $req) use ($mh, &$handles, &$inflight, $accept): void {
        $req['attempt']++;
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . USER_AGENT,
                'Accept: ' . $accept,
                'Accept-Encoding: gzip, deflate'
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HEADER => true,
        ]);
        curl_setopt($ch, CURLOPT_PRIVATE, $req['key']);
        $handles[(int)$ch] = $ch;
        $inflight[(int)$ch] = $req;
        curl_multi_add_handle($mh, $ch);
    };

    $retryableStatuses = [429, 500, 502, 503, 504];

    $nextReadyIndex = function(array &$pending, float $now): ?int {
        foreach ($pending as $idx => $req) {
            if ($req['notBefore'] <= $now) {
                return $idx;
            }
        }
        return null;
    };

    while ($pending || $handles) {
        $now = microtime(true);
        while (count($handles) < $concurrency && ($idx = $nextReadyIndex($pending, $now)) !== null) {
            $req = $pending[$idx];
            array_splice($pending, $idx, 1);
            $addHandle($req);
        }

        if ($handles) {
            curl_multi_exec($mh, $running);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $key = curl_getinfo($ch, CURLINFO_PRIVATE);
                $response = curl_multi_getcontent($ch);
                if ($response === false) {
                    $response = '';
                }
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE) ?: 0;
                $headerStr = substr($response, 0, $headerSize);
                $body = substr($response, $headerSize);
                $attemptedReq = $inflight[(int)$ch] ?? ['attempt' => 1, 'url' => ''];
                $attempt = $attemptedReq['attempt'] ?? 1;
                $url = $attemptedReq['url'] ?? '';

                $retryAfter = null;
                if ($headerStr) {
                    $lines = preg_split('/\r?\n/', trim($headerStr));
                    foreach ($lines as $line) {
                        if (stripos($line, 'Retry-After:') === 0) {
                            $retryAfter = trim(substr($line, strlen('Retry-After:')));
                            break;
                        }
                    }
                }

                $shouldRetry = in_array($status, $retryableStatuses, true) || $body === '' || $body === null;
                if ($shouldRetry && $attempt < $maxRetries) {
                    $delayMs = (int)($baseDelayMs * (2 ** ($attempt - 1)));
                    $delayMs += random_int(0, (int)($baseDelayMs / 2));
                    if ($status === 429) {
                        if (is_numeric($retryAfter)) {
                            $delayMs = max($delayMs, (int)($retryAfter * 1000));
                        } else {
                            $delayMs = max($delayMs, 1500);
                        }
                    }
                    $nextAttempt = $attempt + 1;
                    logmsg("  Retrying $url (status $status, attempt $nextAttempt/$maxRetries)…");
                    $pending[] = [
                        'key' => $key,
                        'url' => $url,
                        'attempt' => $attempt,
                        'notBefore' => microtime(true) + ($delayMs / 1000),
                    ];
                } else {
                    if ($shouldRetry && $attempt >= $maxRetries) {
                        logmsg("  Giving up on $url after $maxRetries attempts (status $status).");
                    }
                    $results[$key] = ['body' => $body, 'status' => $status];
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($handles[(int)$ch], $inflight[(int)$ch]);
            }
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } else {
            $nextAt = null;
            foreach ($pending as $req) {
                $nextAt = ($nextAt === null) ? $req['notBefore'] : min($nextAt, $req['notBefore']);
            }
            if ($nextAt !== null) {
                $sleepUs = max(0, (int)(($nextAt - microtime(true)) * 1e6));
                if ($sleepUs > 0) usleep(min($sleepUs, 500000));
            }
        }
    }

    curl_multi_close($mh);
    return $results;
}

function fetchCompanyFilingsBatch(array $ciks, int $concurrency = FILINGS_CONCURRENCY): array {
    $requests = [];
    $raw = [];
    $urlByKey = [];
    foreach ($ciks as $cik) {
        if (!$cik) continue;
        $cikPadded = str_pad($cik, 10, '0', STR_PAD_LEFT);
        $url = SUBMISSION_ENDPOINT . 'CIK' . $cikPadded . '.json';
        $urlByKey[$cik] = $url;
        $cached = cacheRead($url);
        if ($cached && ($cached['status'] ?? 0) === 200) {
            $raw[$cik] = $cached;
            continue;
        }
        $requests[] = [
            'key' => $cik,
            'url' => $url,
        ];
    }
    if ($requests) {
        $fetched = multiFetchJson($requests, 'application/json', $concurrency, MAX_RETRIES, BACKOFF_BASE_MS);
        foreach ($fetched as $key => $result) {
            if (($result['status'] ?? 0) === 200 && isset($urlByKey[$key])) {
                cacheWrite($urlByKey[$key], $result);
            }
        }
        $raw = $raw + $fetched;
    }
    $filings = [];
    foreach ($raw as $key => $result) {
        $data = json_decode($result['body'] ?? '', true);
        $recent = is_array($data) ? ($data['filings']['recent'] ?? []) : [];
        $list = [];
        if (!empty($recent['form'])) {
            $count = count($recent['form']);
            for ($i = 0; $i < $count; $i++) {
                $list[] = [
                    'form' => $recent['form'][$i] ?? '',
                    'filingDate' => $recent['filingDate'][$i] ?? '',
                    'reportDate' => $recent['reportDate'][$i] ?? '',
                    'accessionNumber' => $recent['accessionNumber'][$i] ?? '',
                    'primaryDocument' => $recent['primaryDocument'][$i] ?? '',
                ];
            }
        }
        $filings[$key] = [
            'filings' => $list,
            'status' => $result['status'] ?? 0,
        ];
        unset($raw[$key]);
    }
    return $filings;
}

function fetchCompanyFactsBatch(array $ciks, int $concurrency = FACTS_CONCURRENCY): array {
    $requests = [];
    $raw = [];
    $urlByKey = [];
    foreach ($ciks as $cik) {
        if (!$cik) continue;
        $cikPadded = str_pad($cik, 10, '0', STR_PAD_LEFT);
        $url = 'https://data.sec.gov/api/xbrl/companyfacts/CIK' . $cikPadded . '.json';
        $urlByKey[$cik] = $url;
        $cached = cacheRead($url);
        if ($cached && ($cached['status'] ?? 0) === 200) {
            $raw[$cik] = $cached;
            continue;
        }
        $requests[] = [
            'key' => $cik,
            'url' => $url,
        ];
    }
    if ($requests) {
        $fetched = multiFetchJson($requests, 'application/json', $concurrency, MAX_RETRIES, BACKOFF_BASE_MS);
        foreach ($fetched as $key => $result) {
            if (($result['status'] ?? 0) === 200 && isset($urlByKey[$key])) {
                cacheWrite($urlByKey[$key], $result);
            }
        }
        $raw = $raw + $fetched;
    }
    $facts = [];
    foreach ($raw as $key => $result) {
        $data = json_decode($result['body'] ?? '', true);
        $facts[$key] = [
            'facts' => is_array($data) ? ($data['facts'] ?? []) : [],
            'status' => $result['status'] ?? 0,
        ];
        unset($raw[$key]);
    }
    return $facts;
}

function getCompanyFilings(string $cik, ?int &$status = null): array {
    if (!$cik) { $status = 0; return []; }
    $cikPadded = str_pad($cik, 10, '0', STR_PAD_LEFT);
    $url = SUBMISSION_ENDPOINT . 'CIK' . $cikPadded . '.json';
    $status = 0;
    $data = http_get_json($url, $status);
    if (!$data) return [];
    $recent = $data['filings']['recent'] ?? [];
    $filings = [];
    if (!empty($recent['form'])) {
        $count = count($recent['form']);
        for ($i = 0; $i < $count; $i++) {
            $filings[] = [
                'form' => $recent['form'][$i] ?? '',
                'filingDate' => $recent['filingDate'][$i] ?? '',
                'reportDate' => $recent['reportDate'][$i] ?? '',
                'accessionNumber' => $recent['accessionNumber'][$i] ?? '',
                'primaryDocument' => $recent['primaryDocument'][$i] ?? '',
            ];
        }
    }
    return $filings;
}

function buildReportUrl(string $cik, string $acc, string $doc): string {
    $cikTrim = ltrim($cik, '0');
    return "https://www.sec.gov/Archives/edgar/data/$cikTrim/$acc/$doc";
}

function getCompanyFacts(string $cik, ?int &$status = null): array {
    if (!$cik) { $status = 0; return []; }
    $cikPadded = str_pad($cik, 10, '0', STR_PAD_LEFT);
    $url = 'https://data.sec.gov/api/xbrl/companyfacts/CIK' . $cikPadded . '.json';
    $status = 0;
    $data = http_get_json($url, $status);
    return $data['facts'] ?? [];
}

function getFilingIndex(string $cik, string $accession, ?int &$status = null): ?array {
    $cikTrim = ltrim($cik, '0');
    $url = "https://www.sec.gov/Archives/edgar/data/$cikTrim/$accession/index.json";
    $status = 0;
    return http_get_json($url, $status);
}

function findInstanceFile(array $index): ?string {
    $items = $index['directory']['item'] ?? [];
    $best = null;
    foreach ($items as $item) {
        $type = strtoupper($item['type'] ?? '');
        $name = $item['name'] ?? '';
        $desc = strtoupper($item['description'] ?? '');
        if ($type === 'EX-101.INS') return $name;
        if (!$best && (stripos($desc, 'INS') !== false || preg_match('/\.xml$/i', $name))) {
            $best = $name;
        }
    }
    return $best;
}

function extractXbrlFactsFromInstance(string $xml, array $conceptsWanted): array {
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc) return [];
    $namespaces = $doc->getDocNamespaces(true);
    $xbrliNs = $namespaces['xbrli'] ?? 'http://www.xbrl.org/2003/instance';
    $contexts = [];
    foreach ($doc->children($xbrliNs) as $el) {
        if ($el->getName() !== 'context') continue;
        $id = (string)$el['id'];
        $endDate = '';
        if (isset($el->period->endDate)) {
            $endDate = (string)$el->period->endDate;
        } elseif (isset($el->period->instant)) {
            $endDate = (string)$el->period->instant;
        }
        if ($id && $endDate) {
            $contexts[$id] = $endDate;
        }
    }
    $conceptLookup = array_fill_keys(array_values($conceptsWanted), true);
    $facts = [];
    $precisions = [];
    foreach ($namespaces as $ns) {
        if ($ns === $xbrliNs) continue;
        foreach ($doc->children($ns) as $fact) {
            $concept = $fact->getName();
            if (!isset($conceptLookup[$concept])) continue;
            $contextRef = (string)$fact['contextRef'];
            if (!$contextRef || !isset($contexts[$contextRef])) continue;
            $year = (int)substr($contexts[$contextRef], 0, 4);
            if (!$year) continue;
            $val = sanitizeNumeric(trim((string)$fact));
            if ($val === '') continue;
            $decimals = $fact['decimals'] ?? null;
            $dec = is_numeric((string)$decimals) ? (float)$decimals : null;
            $currentDec = $precisions[$year][$concept] ?? null;
            if (!isset($facts[$year][$concept]) || ($dec !== null && ($currentDec === null || $dec < $currentDec))) {
                $facts[$year][$concept] = $val;
                if ($dec !== null) {
                    $precisions[$year][$concept] = $dec;
                }
            }
        }
    }
    return $facts;
}

function fetchXbrlInstanceFacts(string $cik, string $accession, array $concepts): array {
    $indexStatus = 0;
    $index = getFilingIndex($cik, $accession, $indexStatus);
    if (!$index || $indexStatus !== 200) return [];
    $instanceFile = findInstanceFile($index);
    if (!$instanceFile) return [];
    $cikTrim = ltrim($cik, '0');
    $url = "https://www.sec.gov/Archives/edgar/data/$cikTrim/$accession/$instanceFile";
    [$body, $status] = http_get($url, 'application/xml, text/xml;q=0.9, */*;q=0.8');
    if ($status !== 200 || $body === null) return [];
    return extractXbrlFactsFromInstance($body, $concepts);
}

function sanitizeNumeric($value, float $limit = 1e15) {
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return '';
    $v = (float)$value;
    if (!is_finite($v)) return '';
    if (abs($v) > $limit) return '';
    if (abs($v) < 1e-9) return 0.0;
    return $v;
}

function chooseBestFact(array $items, $current): array {
    $best = ['val' => null, 'form' => '', 'filed' => '', 'priority' => -1];
    foreach ($items as $item) {
        $val = sanitizeNumeric($item['val'] ?? null);
        if ($val === '') continue;
        $form = $item['form'] ?? '';
        $filed = $item['filed'] ?? '';
        $priority = ($form === '10-K/A') ? 2 : (($form === '10-K') ? 1 : 0);
        if ($priority === 0) continue;
        if ($priority > $best['priority'] ?? -1) {
            $best = ['val' => $val, 'form' => $form, 'filed' => $filed, 'priority' => $priority];
        } elseif (($best['priority'] ?? 0) === $priority) {
            if (strcmp((string)$filed, (string)$best['filed']) >= 0) {
                $best = ['val' => $val, 'form' => $form, 'filed' => $filed, 'priority' => $priority];
            }
        }
    }
    if ($best['val'] !== null) return $best;
    return ['val' => $current, 'form' => '', 'filed' => '', 'priority' => 0];
}

function findFactValue(array $facts, string $concept, int $year, $current) {
    foreach ($facts as $taxonomy => $concepts) {
        if (!isset($concepts[$concept]['units'])) continue;
        foreach ($concepts[$concept]['units'] as $unit => $items) {
            $yearItems = array_filter($items, fn($i) => (int)($i['fy'] ?? 0) === $year);
            if (!$yearItems) continue;
            $best = chooseBestFact($yearItems, $current);
            if ($best['val'] !== '' && $best['val'] !== null) return $best['val'];
        }
    }
    return $current;
}

function chooseAnnualReportLink(array $filings, int $year, string $cik, string $current): string {
    $best10k = '';
    $best10ka = '';
    foreach ($filings as $f) {
        $form = strtoupper($f['form'] ?? '');
        if ($form !== '10-K' && $form !== '10-K/A') continue;
        $date = $f['reportDate'] ?: $f['filingDate'];
        if (!$date) continue;
        if ((int)substr($date, 0, 4) !== $year) continue;
        $acc = str_replace('-', '', $f['accessionNumber'] ?? '');
        $doc = $f['primaryDocument'] ?? '';
        if (!$acc || !$doc) continue;
        $url = buildReportUrl($cik, $acc, $doc);
        if ($form === '10-K' && !$best10k) {
            $best10k = $url;
        } elseif ($form === '10-K/A' && !$best10ka) {
            $best10ka = $url;
        }
    }
    if ($best10k) return $best10k;
    if ($current) return $current;
    return $best10ka;
}

function chooseFilingForYear(array $filings, int $year): ?array {
    $best = null;
    foreach ($filings as $f) {
        $form = strtoupper($f['form'] ?? '');
        if ($form !== '10-K' && $form !== '10-K/A') continue;
        $date = $f['reportDate'] ?: $f['filingDate'];
        if (!$date || (int)substr($date, 0, 4) !== $year) continue;
        if (!$best || $form === '10-K/A') {
            $best = $f;
            if ($form === '10-K/A') break;
        }
    }
    return $best;
}

function readCsv(string $file): array {
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

function writeCsv(string $file, array $header, array $rows): void {
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

function cleanAgainstExisting($new, $old) {
    if ($new === '' || $new === null) return $old;
    if ($old === '' || $old === null) return $new;
    if (!is_numeric($new) || !is_numeric($old)) return $new;
    $newF = (float)$new; $oldF = (float)$old;
    if (!is_finite($newF)) return $old;
    $ratio = ($oldF != 0.0) ? abs($newF)/abs($oldF) : INF;
    if ($ratio > 1e4 && abs($newF) > 1e6) {
        $scaled = $newF / 1000;
        $scaledRatio = ($oldF != 0.0) ? abs($scaled)/abs($oldF) : $ratio;
        if ($scaledRatio < $ratio) return $scaled;
        return $old;
    }
    if ($ratio < 1e-4 && abs($oldF) > 1e6) {
        $scaled = $newF * 1000;
        $scaledRatio = abs($scaled)/abs($oldF);
        if ($scaledRatio < 1) return $scaled;
    }
    return $newF;
}

function buildXbrlFactsForYears(string $cik, array $years, array $filings): array {
    $years = array_values(array_unique(array_map('intval', $years)));
    if (!$years) return [];

    $factsByYear = [];
    $filingsByYear = [];
    foreach ($years as $year) {
        $filing = chooseFilingForYear($filings, $year);
        if ($filing) {
            $filingsByYear[$year] = $filing;
        }
    }
    if (!$filingsByYear) return [];

    $indexRequests = [];
    $indexResponses = [];
    $indexUrlByAccession = [];
    foreach ($filingsByYear as $year => $filing) {
        $accession = str_replace('-', '', $filing['accessionNumber'] ?? '');
        if (!$accession) continue;
        $cikTrim = ltrim($cik, '0');
        $indexUrl = "https://www.sec.gov/Archives/edgar/data/$cikTrim/$accession/index.json";
        $indexUrlByAccession[$accession] = $indexUrl;
        $cached = cacheRead($indexUrl);
        if ($cached && ($cached['status'] ?? 0) === 200) {
            $indexResponses[$accession] = $cached;
            continue;
        }
        $indexRequests[] = ['key' => $accession, 'url' => $indexUrl];
    }

    if ($indexRequests) {
        logmsg("  Fetching " . count($indexRequests) . " XBRL index file(s) for CIK $cik in parallel…");
        $fetchedIndexes = multiFetchJson($indexRequests, 'application/json', XBRL_INDEX_CONCURRENCY, MAX_RETRIES, BACKOFF_BASE_MS);
        foreach ($fetchedIndexes as $key => $result) {
            if (($result['status'] ?? 0) === 200 && isset($indexUrlByAccession[$key])) {
                cacheWrite($indexUrlByAccession[$key], $result);
            }
        }
        $indexResponses = $indexResponses + $fetchedIndexes;
    }

    $instanceRequests = [];
    $instanceResponses = [];
    $instanceUrlByAccession = [];
    foreach ($filingsByYear as $year => $filing) {
        $accession = str_replace('-', '', $filing['accessionNumber'] ?? '');
        if (!$accession) continue;
        $indexResponse = $indexResponses[$accession] ?? null;
        $indexStatus = $indexResponse['status'] ?? 0;
        if (!$indexResponse || $indexStatus !== 200) {
            logmsg("  No valid index.json for CIK $cik, accession $accession (status $indexStatus).");
            continue;
        }
        $indexData = json_decode($indexResponse['body'] ?? '', true);
        $instanceFile = is_array($indexData) ? findInstanceFile($indexData) : null;
        if (!$instanceFile) {
            logmsg("  Could not locate XBRL instance file in index.json for CIK $cik, accession $accession.");
            continue;
        }
        $cikTrim = ltrim($cik, '0');
        $instanceUrl = "https://www.sec.gov/Archives/edgar/data/$cikTrim/$accession/$instanceFile";
        $instanceUrlByAccession[$accession] = $instanceUrl;
        $cached = cacheRead($instanceUrl);
        if ($cached && ($cached['status'] ?? 0) === 200) {
            $instanceResponses[$accession] = $cached;
            continue;
        }
        $instanceRequests[] = ['key' => $accession, 'url' => $instanceUrl];
    }

    if ($instanceRequests) {
        usleep(POLITE_USLEEP);
        logmsg("  Fetching " . count($instanceRequests) . " XBRL instance file(s) for CIK $cik in parallel…");
        $fetchedInstances = multiFetchJson($instanceRequests, 'application/xml, text/xml;q=0.9, */*;q=0.8', XBRL_INSTANCE_CONCURRENCY, MAX_RETRIES, BACKOFF_BASE_MS);
        foreach ($fetchedInstances as $key => $result) {
            if (($result['status'] ?? 0) === 200 && isset($instanceUrlByAccession[$key])) {
                cacheWrite($instanceUrlByAccession[$key], $result);
            }
        }
        $instanceResponses = $instanceResponses + $fetchedInstances;
    }

    foreach ($filingsByYear as $year => $filing) {
        $accession = str_replace('-', '', $filing['accessionNumber'] ?? '');
        if (!$accession) continue;
        $instanceBody = $instanceResponses[$accession]['body'] ?? null;
        $status = $instanceResponses[$accession]['status'] ?? 0;
        if (!$instanceBody || $status !== 200) {
            logmsg("  Failed to fetch instance XBRL for CIK $cik, accession $accession (status $status).");
            continue;
        }
        $instanceFacts = extractXbrlFactsFromInstance($instanceBody, FINANCIAL_FIELD_MAP);
        if (!$instanceFacts) {
            logmsg("  No XBRL facts extracted for CIK $cik, accession $accession.");
        }
        foreach ($instanceFacts as $fy => $conceptFacts) {
            foreach ($conceptFacts as $concept => $val) {
                if (!isset($factsByYear[$fy][$concept])) {
                    $factsByYear[$fy][$concept] = $val;
                }
            }
        }
    }

    if ($instanceRequests) {
        usleep(POLITE_USLEEP);
    }

    return $factsByYear;
}

function applyFactsToRows(array &$rows, string $cik, array $facts, array $xbrlFacts, int &$updated, int &$cleaned): void {
    $factFilled = [];
    $xbrlFilled = [];
    foreach ($rows as &$row) {
        if (($row['CIK'] ?? '') !== $cik) continue;
        $year = (int)($row['year'] ?? 0);
        if (!$year) continue;
        foreach (FINANCIAL_FIELD_MAP as $col => $concept) {
            $current = $row[$col] ?? '';
            $val = findFactValue($facts, $concept, $year, $current);
            // Prefer SEC company facts as the primary source, and only fall back to XBRL instance-derived values when the
            // primary source is missing, null, or unchanged from the current CSV value
            $usedXbrl = false;
            if (($val === $current || $val === '' || $val === null) && isset($xbrlFacts[$year][$concept])) {
                $val = $xbrlFacts[$year][$concept];
                $usedXbrl = true;
            }
            $val = sanitizeNumeric($val);
            $cleanVal = cleanAgainstExisting($val, $current);
            if ($cleanVal !== $current) {
                $row[$col] = $cleanVal;
                $updated++;
                if ($usedXbrl) {
                    $xbrlFilled[$year] = ($xbrlFilled[$year] ?? 0) + 1;
                } else {
                    $factFilled[$year] = ($factFilled[$year] ?? 0) + 1;
                }
            }
            if ($cleanVal === '' && $current !== '') {
                $cleaned++;
            }
        }
    }
    unset($row);
    foreach (array_unique(array_merge(array_keys($factFilled), array_keys($xbrlFilled))) as $yr) {
        $factCount = $factFilled[$yr] ?? 0;
        $xbrlCount = $xbrlFilled[$yr] ?? 0;
        if ($factCount || $xbrlCount) {
            logmsg("  Applied $factCount field(s) from company facts and $xbrlCount from XBRL for CIK $cik, year $yr.");
        }
    }
}

function updateFinancialRowsStreaming(array &$rowsMain, array &$rowsSolvent, array $ciks): void {
    $total = count($ciks);
    $i = 0;
    $updated = 0;
    $cleaned = 0;
    $yearsByCik = [];
    foreach ([$rowsMain, $rowsSolvent] as $rows) {
        foreach ($rows as $r) {
            $cik = $r['CIK'] ?? '';
            $year = (int)($r['year'] ?? 0);
            if ($cik && $year) {
                $yearsByCik[$cik][$year] = true;
            }
        }
    }
    $batchSize = 50;
    $chunks = array_chunk($ciks, $batchSize);
    foreach ($chunks as $chunkIndex => $chunk) {
        $start = $chunkIndex * $batchSize + 1;
        $end = $start + count($chunk) - 1;
        logmsg("Prefetching company facts for CIKs $start-$end of $total (batch size $batchSize)…");
        $factResponses = fetchCompanyFactsBatch($chunk);
        logmsg("Prefetching filing lists for CIKs $start-$end of $total (batch size $batchSize)…");
        $filingResponses = fetchCompanyFilingsBatch($chunk);
        foreach ($chunk as $cik) {
            $i++;
            logmsg("Processing CIK $cik ($i/$total)…");
            $facts = $factResponses[$cik]['facts'] ?? [];
            $factStatus = $factResponses[$cik]['status'] ?? 0;
            $filingStatus = $filingResponses[$cik]['status'] ?? 0;
            if ($factStatus === 429) {
                logmsg("  Company facts rate-limited for CIK $cik (429); will rely on XBRL fallback.");
            } elseif ($factStatus !== 200) {
                logmsg("  Company facts unavailable for CIK $cik (status $factStatus); attempting XBRL fallback.");
            }
            $filings = $filingResponses[$cik]['filings'] ?? [];
            if ($filingStatus === 429) {
                logmsg("  Filings list rate-limited for CIK $cik (429); will attempt single-request fallback.");
                if (!$filings) {
                    usleep(POLITE_USLEEP);
                    $singleStatus = 0;
                    $fallbackFilings = getCompanyFilings($cik, $singleStatus);
                    if ($fallbackFilings) {
                        $filings = $fallbackFilings;
                        logmsg("  Retrieved filings via single-request fallback for CIK $cik (status $singleStatus).");
                    } elseif ($singleStatus === 429) {
                        logmsg("  Filings list still rate-limited for CIK $cik on fallback (429).");
                    } else {
                        logmsg("  No filings found for CIK $cik (status $singleStatus)");
                    }
                }
            } elseif (!$filings && $filingStatus !== 429) {
                if ($filingStatus) {
                    $statusMsg = " (status $filingStatus)";
                    logmsg("  No filings found for CIK $cik$statusMsg");
                }
            }
            $years = array_keys($yearsByCik[$cik] ?? []);
            $xbrlFacts = $filings ? buildXbrlFactsForYears($cik, $years, $filings) : [];
            applyFactsToRows($rowsMain, $cik, $facts, $xbrlFacts, $updated, $cleaned);
            applyFactsToRows($rowsSolvent, $cik, $facts, $xbrlFacts, $updated, $cleaned);
            unset($factResponses[$cik], $filingResponses[$cik]);
        }
        unset($factResponses, $filingResponses);
    }
    logmsg("Updated $updated field(s); cleaned $cleaned suspect value(s).");
}

function buildFilingsCache(array $ciks): array {
    $cache = [];
    $total = count($ciks);
    $batchSize = 100;
    $chunks = array_chunk($ciks, $batchSize);
    foreach ($chunks as $chunkIndex => $chunk) {
        $start = $chunkIndex * $batchSize + 1;
        $end = $start + count($chunk) - 1;
        logmsg("Prefetching filings for CIKs $start-$end of $total (batch size $batchSize)…");
        $responses = fetchCompanyFilingsBatch($chunk);
        foreach ($chunk as $cik) {
            $filings = $responses[$cik]['filings'] ?? [];
            if (!$filings) {
                $status = $responses[$cik]['status'] ?? 0;
                if ($status && $status !== 429) {
                    logmsg("  No filings found for CIK $cik" . ($status ? " (status $status)" : ''));
                }
            }
            $cache[$cik] = $filings;
            unset($responses[$cik]);
        }
        unset($responses);
    }
    return $cache;
}

function syncSubsets(array $sourceRows, string $subsetFile): void {
    if (!file_exists($subsetFile)) return;
    [$header, $rows] = readCsv($subsetFile);
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
                if (array_key_exists($col, FINANCIAL_FIELD_MAP)) {
                    $newVal = $index[$cik][$year][$col] ?? '';
                    if ($newVal !== $val) {
                        $row[$col] = $newVal;
                        $changes++;
                    }
                }
            }
        }
    }
    writeCsv($subsetFile, $header, $rows);
    logmsg("Synchronized $changes value(s) into $subsetFile.");
}

function updateReportRows(array $rows, array $filingsCache): array {
    $replaced = 0;
    foreach ($rows as &$row) {
        $cik = $row['CIK'] ?? '';
        $year = (int)($row['year'] ?? 0);
        if (!$cik || !$year) continue;
        $filings = $filingsCache[$cik] ?? [];
        $current = $row['AnnualReportLink'] ?? '';
        $new = chooseAnnualReportLink($filings, $year, $cik, $current);
        if ($new && $new !== $current) {
            $row['AnnualReportLink'] = $new;
            $replaced++;
        }
    }
    logmsg("Replaced $replaced annual report link(s) with original 10-K versions.");
    return $rows;
}

function syncReportSubsets(array $sourceRows, string $subsetFile): void {
    if (!file_exists($subsetFile)) return;
    [$header, $rows] = readCsv($subsetFile);
    $index = [];
    foreach ($sourceRows as $r) {
        $index[$r['CIK']][$r['year']] = $r['AnnualReportLink'] ?? '';
    }
    $changes = 0;
    foreach ($rows as &$row) {
        $cik = $row['CIK'] ?? '';
        $year = $row['year'] ?? '';
        if (isset($index[$cik][$year])) {
            $newVal = $index[$cik][$year];
            if ($newVal && $newVal !== ($row['AnnualReportLink'] ?? '')) {
                $row['AnnualReportLink'] = $newVal;
                $changes++;
            }
        }
    }
    writeCsv($subsetFile, $header, $rows);
    logmsg("Synchronized $changes annual report link(s) into $subsetFile.");
}

[$headerMain, $rowsMain] = readCsv(FINANCIAL_CSV_FILE);
[$headerSolvent, $rowsSolvent] = readCsv(FINANCIAL_CSV_SOLVENT);
[$headerReports, $rowsReports] = readCsv(REPORTS_CSV_FILE);
[$headerReportsSolvent, $rowsReportsSolvent] = readCsv(REPORTS_CSV_SOLVENT);

$ciks = [];
foreach ([$rowsMain, $rowsSolvent] as $rows) {
    foreach ($rows as $r) {
        $cik = $r['CIK'] ?? '';
        if ($cik && !in_array($cik, $ciks, true)) $ciks[] = $cik;
    }
}

updateFinancialRowsStreaming($rowsMain, $rowsSolvent, $ciks);
writeCsv(FINANCIAL_CSV_FILE, $headerMain, $rowsMain);
writeCsv(FINANCIAL_CSV_SOLVENT, $headerSolvent, $rowsSolvent);

$filingCiks = [];
foreach ([$rowsReports, $rowsReportsSolvent] as $rows) {
    foreach ($rows as $r) {
        $cik = $r['CIK'] ?? '';
        if ($cik && !in_array($cik, $filingCiks, true)) $filingCiks[] = $cik;
    }
}

$filingsCache = buildFilingsCache($filingCiks);
$rowsReports = updateReportRows($rowsReports, $filingsCache);
$rowsReportsSolvent = updateReportRows($rowsReportsSolvent, $filingsCache);
writeCsv(REPORTS_CSV_FILE, $headerReports, $rowsReports);
writeCsv(REPORTS_CSV_SOLVENT, $headerReportsSolvent, $rowsReportsSolvent);

if (file_exists(FINANCIAL_SUBSET)) {
    syncSubsets($rowsMain, FINANCIAL_SUBSET);
} else {
    logmsg('Skipped synchronizing financials_subset.csv (file not found).');
}

if (file_exists(FINANCIAL_SUBSET_SOLV)) {
    syncSubsets($rowsSolvent, FINANCIAL_SUBSET_SOLV);
} else {
    logmsg('Skipped synchronizing financials_solvent_subset.csv (file not found).');
}

if (file_exists(REPORTS_SUBSET)) {
    syncReportSubsets($rowsReports, REPORTS_SUBSET);
} else {
    logmsg('Skipped synchronizing reports_subset.csv (file not found).');
}

if (file_exists(REPORTS_SUBSET_SOLV)) {
    syncReportSubsets($rowsReportsSolvent, REPORTS_SUBSET_SOLV);
} else {
    logmsg('Skipped synchronizing reports_solvent_subset.csv (file not found).');
}

logmsg('Done updating and cleaning datasets.');
if (!$runningInCli) {
    echo "</pre>";
}
?>