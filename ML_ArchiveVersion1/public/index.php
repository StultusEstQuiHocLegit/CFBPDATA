<?php
// index.php
// Entry point for the bankruptcy prediction dashboard and actions.

declare(strict_types=1);

use App\Util\Logger;
use App\Http\Controllers\TrainController;
use App\Http\Controllers\PredictController;
use App\Http\Responses\Json;

// Use local autoloader instead of composer's vendor autoloader
require __DIR__ . '/../autoload.php';

// Load a model artefact and return a pretty-printed JSON representation for display
// @param string $path, absolute filesystem path to the artefact
// @param bool $isSerialized, whether the artefact is stored as a PHP-serialised structure.

function render_pretty_json(string $path, bool $isSerialized = false): string
{
    if (!is_readable($path)) {
        return 'File unavailable: ' . basename($path);
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return 'Unable to read: ' . basename($path);
    }

    if ($isSerialized) {
        $decoded = @unserialize($raw);
        if ($decoded === false && $raw !== 'b:0;') {
            return 'Unable to unserialize: ' . basename($path);
        }
    } else {
        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }
    }

    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: (string) $raw;
}

// Load and decode a JSON file into an associative array
// @param string $path
// @return array
function load_json_file(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return [];
    }

    $decoded = json_decode($contents, true);

    return is_array($decoded) ? $decoded : [];
}

// Load and decode a PHP-serialised file into an associative array
// @param string $path
// @return array
function load_serialized_file(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return [];
    }

    $decoded = @unserialize($contents);

    return is_array($decoded) ? $decoded : [];
}

// Format a numeric value using a fixed precision while trimming trailing zeros
// @param float|int|string|null $value
// @param int $precision
function format_number($value, int $precision = 4): string
{
    if (!is_numeric($value)) {
        return 'N/A';
    }

    $formatted = number_format((float) $value, $precision, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}

// Gather the data needed to render the "Model" card
// @return array<string, mixed>
function get_model_card_data(): array
{
    $root = __DIR__ . '/../';
    $metadata = load_json_file($root . 'models/metadata.json');
    $metrics = load_json_file($root . 'reports/metrics.json');
    $calibrator = load_serialized_file($root . 'models/calibrator.bin');
    $modelInfo = load_serialized_file($root . 'models/model.bin');

    $config = is_array($metadata['config'] ?? null) ? $metadata['config'] : [];
    $split = is_array($config['split'] ?? null) ? $config['split'] : [];
    $thresholdConfig = is_array($config['thresholds'] ?? null) ? $config['thresholds'] : [];

    $snapshot = (string) ($metadata['timestamp'] ?? 'unknown');
    $validationYear = $split['valid_year'] ?? 'N/A';
    $testYear = $split['test_year'] ?? 'N/A';
    $weightingRaw = $config['class_weighting'] ?? null;
    $weighting = is_string($weightingRaw) ? str_replace('_', '-', $weightingRaw) : 'unknown weighting';
    $calibrationRaw = $config['calibration'] ?? 'calibration';
    $calibrationMethod = is_string($calibrationRaw) ? strtolower($calibrationRaw) : 'calibration';

    $optimizeForKey = $thresholdConfig['optimize_for'] ?? null;
    $optimizeLabelMap = [
        'pr_auc' => 'precision-recall AUC',
        'roc_auc' => 'ROC AUC',
        'brier' => 'Brier score',
    ];
    $optimisationTarget = $optimizeLabelMap[$optimizeForKey] ?? ($optimizeForKey ? strtoupper(str_replace('_', ' ', (string) $optimizeForKey)) : 'Unknown objective');

    $prAuc = format_number($metrics['pr_auc'] ?? null);
    $rocAuc = format_number($metrics['roc_auc'] ?? null);
    $brier = format_number($metrics['brier'] ?? null);

    $probabilityGrid = [];
    if (!empty($calibrator['thresholds']) && is_array($calibrator['thresholds'])) {
        foreach ($calibrator['thresholds'] as $value) {
            $probabilityGrid[] = format_number($value);
        }
    }

    $calibratedScores = [];
    if (!empty($calibrator['values']) && is_array($calibrator['values'])) {
        foreach ($calibrator['values'] as $value) {
            $calibratedScores[] = format_number($value);
        }
    }
    $probabilityGridText = $probabilityGrid ? '[' . implode(', ', $probabilityGrid) . ']' : 'unavailable';
    $calibratedScoresText = $calibratedScores ? '[' . implode(', ', $calibratedScores) . ']' : 'unavailable';

    $thresholds = is_array($metrics['thresholds'] ?? null) ? $metrics['thresholds'] : [];
    $primaryThreshold = $thresholds['primary'] ?? ($thresholds['best'] ?? null);
    $strictRecallTarget = $thresholdConfig['strict_recall_at'] ?? null;
    $recallThreshold = $thresholds['recall_target'] ?? ($thresholds['recall80'] ?? null);

    $operatingPoints = is_array($metrics['operating_points'] ?? null) ? $metrics['operating_points'] : [];
    $validationPoints = is_array($operatingPoints['validation'] ?? null) ? $operatingPoints['validation'] : [];
    $testPoints = is_array($operatingPoints['test'] ?? null) ? $operatingPoints['test'] : [];

    $targetRecall = $strictRecallTarget;
    if (isset($validationPoints['recall_target']['target_recall'])) {
        $targetRecall = $validationPoints['recall_target']['target_recall'];
    }

    $primaryPoint = $testPoints['primary'] ?? null;
    $strictPoint = $testPoints['recall_target'] ?? null;
    if ($strictPoint === null && isset($metrics['confusion_strict'])) {
        $confusionStrict = is_array($metrics['confusion_strict']) ? $metrics['confusion_strict'] : [];
        $strictPoint = [
            'threshold' => $recallThreshold,
            'precision' => $confusionStrict['precision'] ?? null,
            'recall' => $confusionStrict['recall'] ?? null,
            'f1' => $confusionStrict['f1'] ?? null,
            'support' => [
                'tp' => $confusionStrict['TP'] ?? null,
                'fp' => $confusionStrict['FP'] ?? null,
                'tn' => $confusionStrict['TN'] ?? null,
                'fn' => $confusionStrict['FN'] ?? null,
            ],
        ];
    }
    if ($primaryPoint === null && isset($metrics['confusion_best'])) {
        $confusionBest = is_array($metrics['confusion_best']) ? $metrics['confusion_best'] : [];
        $primaryPoint = [
            'threshold' => $primaryThreshold,
            'precision' => $confusionBest['precision'] ?? null,
            'recall' => $confusionBest['recall'] ?? null,
            'f1' => $confusionBest['f1'] ?? null,
            'support' => [
                'tp' => $confusionBest['TP'] ?? null,
                'fp' => $confusionBest['FP'] ?? null,
                'tn' => $confusionBest['TN'] ?? null,
                'fn' => $confusionBest['FN'] ?? null,
            ],
        ];
    }

    $primarySupport = is_array($primaryPoint['support'] ?? null) ? $primaryPoint['support'] : [];
    $primaryTP = isset($primarySupport['tp']) ? (string) $primarySupport['tp'] : 'N/A';
    $primaryFP = isset($primarySupport['fp']) ? (string) $primarySupport['fp'] : 'N/A';
    $primaryTN = isset($primarySupport['tn']) ? (string) $primarySupport['tn'] : 'N/A';
    $primaryFN = isset($primarySupport['fn']) ? (string) $primarySupport['fn'] : 'N/A';
    $primaryPrecision = format_number($primaryPoint['precision'] ?? null);
    $primaryRecall = format_number($primaryPoint['recall'] ?? null);
    $primaryF1 = format_number($primaryPoint['f1'] ?? null);

    $strictSupport = is_array($strictPoint['support'] ?? null) ? $strictPoint['support'] : [];
    $strictTP = isset($strictSupport['tp']) ? (string) $strictSupport['tp'] : 'N/A';
    $strictFP = isset($strictSupport['fp']) ? (string) $strictSupport['fp'] : 'N/A';
    $strictTN = isset($strictSupport['tn']) ? (string) $strictSupport['tn'] : 'N/A';
    $strictFN = isset($strictSupport['fn']) ? (string) $strictSupport['fn'] : 'N/A';
    $strictPrecisionVal = format_number($strictPoint['precision'] ?? null);
    $strictRecallVal = format_number($strictPoint['recall'] ?? null);
    $strictF1Val = format_number($strictPoint['f1'] ?? null);

    $primaryThresholdText = is_numeric($primaryThreshold) ? format_number($primaryThreshold) : 'N/A';
    $strictRecallText = is_numeric($targetRecall) ? format_number((float) $targetRecall, 2) : 'N/A';
    $strictThresholdText = is_numeric($recallThreshold) ? format_number($recallThreshold) : 'N/A';
    $sameThreshold = is_numeric($primaryThreshold) && is_numeric($recallThreshold)
        ? abs((float) $primaryThreshold - (float) $recallThreshold) < 1e-9
        : false;
    $thresholdSummary = $sameThreshold
        ? 'shares the primary threshold'
        : (is_numeric($recallThreshold) ? 'uses threshold ' . $strictThresholdText : 'has no available threshold');

    $reliability = is_array($metrics['reliability'] ?? null) ? $metrics['reliability'] : [];
    $reliabilityTest = is_array($reliability['test'] ?? null) ? $reliability['test'] : [];
    $reliabilitySummary = [];
    foreach ($reliabilityTest as $idx => $bin) {
        if ($idx >= 5) {
            break;
        }
        $reliabilitySummary[] = sprintf(
            '[%s-%s]=%s->%s',
            format_number($bin['lower'] ?? null, 2),
            format_number($bin['upper'] ?? null, 2),
            format_number($bin['avg_pred'] ?? null, 2),
            format_number($bin['emp_rate'] ?? null, 2)
        );
    }
    $reliabilitySummaryText = $reliabilitySummary ? implode(', ', $reliabilitySummary) : 'unavailable';

    $lambda = format_number($modelInfo['lambda'] ?? null);
    $iterations = isset($modelInfo['iterations']) && is_numeric($modelInfo['iterations']) ? (string) (int) $modelInfo['iterations'] : 'N/A';
    $learningRate = format_number($modelInfo['learningRate'] ?? null);
    $bias = format_number($modelInfo['bias'] ?? null);

    return [
        'snapshot' => $snapshot,
        'validationYear' => (string) $validationYear,
        'testYear' => (string) $testYear,
        'weighting' => $weighting,
        'calibrationMethod' => $calibrationMethod,
        'optimisationTarget' => $optimisationTarget,
        'prAuc' => $prAuc,
        'rocAuc' => $rocAuc,
        'brier' => $brier,
        'probabilityGridText' => $probabilityGridText,
        'calibratedScoresText' => $calibratedScoresText,
        'primaryThresholdText' => $primaryThresholdText,
        'primaryTP' => $primaryTP,
        'primaryFP' => $primaryFP,
        'primaryTN' => $primaryTN,
        'primaryFN' => $primaryFN,
        'primaryPrecision' => $primaryPrecision,
        'primaryRecall' => $primaryRecall,
        'primaryF1' => $primaryF1,
        'strictRecallText' => $strictRecallText,
        'thresholdSummary' => $thresholdSummary,
        'strictTP' => $strictTP,
        'strictFP' => $strictFP,
        'strictTN' => $strictTN,
        'strictFN' => $strictFN,
        'strictPrecision' => $strictPrecisionVal,
        'strictRecall' => $strictRecallVal,
        'strictF1' => $strictF1Val,
        'reliabilitySummaryText' => $reliabilitySummaryText,
        'lambda' => $lambda,
        'iterations' => $iterations,
        'learningRate' => $learningRate,
        'bias' => $bias,
        'metadataPretty' => render_pretty_json($root . 'models/metadata.json'),
        'metricsPretty' => render_pretty_json($root . 'reports/metrics.json'),
        'calibratorPretty' => render_pretty_json($root . 'models/calibrator.bin', true),
        'modelPretty' => render_pretty_json($root . 'models/model.bin', true),
        'preprocessorStep0' => render_pretty_json($root . 'models/preprocessor.bin.step0', true),
        'preprocessorStep1' => render_pretty_json($root . 'models/preprocessor.bin.step1', true),
        'preprocessorStep2' => render_pretty_json($root . 'models/preprocessor.bin.step2', true),
        'preprocessorStep3' => render_pretty_json($root . 'models/preprocessor.bin.step3', true),
    ];
}

// Render the HTML content for the "Model" card
// @param array<string, mixed> $data
function render_model_card_inner(array $data): string
{
    ob_start();
    ?>
    <h2>Model</h2>
    <p><strong>Logistic regression bankruptcy classifier</strong> trained on grouped annual filings (validation year <?php echo htmlspecialchars($data['validationYear']); ?>, test year <?php echo htmlspecialchars($data['testYear']); ?>) with <?php echo htmlspecialchars($data['weighting']); ?> class weighting and <?php echo htmlspecialchars($data['calibrationMethod']); ?> calibration. Latest model snapshot: <code><?php echo htmlspecialchars($data['snapshot']); ?></code>.</p>
    <ul>
        <li>Optimisation target: <?php echo htmlspecialchars($data['optimisationTarget']); ?> (PR AUC = <?php echo htmlspecialchars($data['prAuc']); ?>, ROC AUC = <?php echo htmlspecialchars($data['rocAuc']); ?>, Brier score = <?php echo htmlspecialchars($data['brier']); ?>).</li>
        <li>Calibration thresholds (<?php echo htmlspecialchars($data['calibrationMethod']); ?>): probability grid <?php echo htmlspecialchars($data['probabilityGridText']); ?> -&gt; calibrated scores <?php echo htmlspecialchars($data['calibratedScoresText']); ?>.</li>
        <li>Primary decision point (threshold <?php echo htmlspecialchars($data['primaryThresholdText']); ?>) yields TP=<?php echo htmlspecialchars($data['primaryTP']); ?>, FP=<?php echo htmlspecialchars($data['primaryFP']); ?>, TN=<?php echo htmlspecialchars($data['primaryTN']); ?>, FN=<?php echo htmlspecialchars($data['primaryFN']); ?> (precision <?php echo htmlspecialchars($data['primaryPrecision']); ?>, recall <?php echo htmlspecialchars($data['primaryRecall']); ?>, F1 <?php echo htmlspecialchars($data['primaryF1']); ?>). Strict recall <?php echo htmlspecialchars($data['strictRecallText']); ?> <?php echo htmlspecialchars($data['thresholdSummary']); ?> with TP=<?php echo htmlspecialchars($data['strictTP']); ?>, FP=<?php echo htmlspecialchars($data['strictFP']); ?>, TN=<?php echo htmlspecialchars($data['strictTN']); ?>, FN=<?php echo htmlspecialchars($data['strictFN']); ?> (precision <?php echo htmlspecialchars($data['strictPrecision']); ?>, recall <?php echo htmlspecialchars($data['strictRecall']); ?>, F1 <?php echo htmlspecialchars($data['strictF1']); ?>).</li>
        <li>Calibration reliability (test deciles): <?php echo htmlspecialchars($data['reliabilitySummaryText']); ?>.</li>
        <li>L2 regularisation λ=<?php echo htmlspecialchars($data['lambda']); ?>, <?php echo htmlspecialchars($data['iterations']); ?> gradient-descent epochs, learning rate <?php echo htmlspecialchars($data['learningRate']); ?>. Bias = <?php echo htmlspecialchars($data['bias']); ?>.</li>
    </ul>
    <details class="info-panel">
        <summary>Training metadata &amp; feature order</summary>
        <pre>
<?php echo htmlspecialchars($data['metadataPretty']); ?>
        </pre>
    </details>
    <details class="info-panel">
        <summary>Evaluation metrics (validation/test)</summary>
        <pre>
<?php echo htmlspecialchars($data['metricsPretty']); ?>
        </pre>
    </details>
    <details class="info-panel">
        <summary>Calibration curve (isotonic)</summary>
        <pre>
<?php echo htmlspecialchars($data['calibratorPretty']); ?>
        </pre>
    </details>
    <details class="info-panel">
        <summary>Model coefficients (bias, λ, iterations, learning rate, per-feature weights)</summary>
        <pre>
<?php echo htmlspecialchars($data['modelPretty']); ?>
        </pre>
    </details>
    <details class="info-panel">
        <summary>Preprocessing pipeline</summary>
        <p>Pipeline order: winsorisation -&gt; median imputation (+missingness flags) -&gt; robust scaling -&gt; one-hot encoding.</p>
        <details class="info-panel nested">
            <summary>Winsorizer parameters &amp; cutoffs</summary>
            <pre>
<?php echo htmlspecialchars($data['preprocessorStep0']); ?>
            </pre>
        </details>
        <details class="info-panel nested">
            <summary>Imputer medians &amp; indicator mapping</summary>
            <pre>
<?php echo htmlspecialchars($data['preprocessorStep1']); ?>
            </pre>
        </details>
        <details class="info-panel nested">
            <summary>Robust scaler medians &amp; IQRs</summary>
            <pre>
<?php echo htmlspecialchars($data['preprocessorStep2']); ?>
            </pre>
        </details>
        <details class="info-panel nested">
            <summary>One-hot encoder categories</summary>
            <pre>
<?php echo htmlspecialchars($data['preprocessorStep3']); ?>
            </pre>
        </details>
    </details>
    <?php
    return (string) ob_get_clean();
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Wrap processing in a try/catch so that unexpected errors return JSON
    $action = $_POST['action'] ?? '';
    $logger = new Logger();
    try {
        switch ($action) {
            case 'train':
                $controller = new TrainController($logger);
                $response = $controller->handle($_POST, $_FILES);
                $response->send();
                break;
            case 'predict':
                $controller = new PredictController($logger);
                $response = $controller->handle($_POST, $_FILES);
                $response->send();
                break;
            case 'model_summary':
                $html = render_model_card_inner(get_model_card_data());
                Json::success(['html' => $html])->send();
                break;
            default:
                Json::error('Unknown action')->send();
                break;
        }
    } catch (\Throwable $e) {
        // Log the exception and return an error response
        $logger->error('Unhandled exception: ' . $e->getMessage());
        Json::error('Server error: ' . $e->getMessage())->send();
    }
    exit;
}

$modelCardData = get_model_card_data();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRAMANN CFDATA</title>
<?php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$assetBase = rtrim($scriptDir, '/');
$assetBase = $assetBase === '' ? '' : $assetBase;
$formAction = ($assetBase === '' ? '' : $assetBase) . '/index.php';
?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase); ?>/assets/app.css">
</head>
<body>
<div class="container">
    <h1>Machine Learning Application (Regression)</h1>
    <div class="card">
        <h2>Train Model</h2>
        <div id="trainProgressContainer" class="progress-container hidden" hidden>
            <div class="progress-label">Model training</div>
            <div class="progress-track">
                <div id="trainProgressBar" class="progress-bar"></div>
            </div>
            <div id="trainProgressStatus" class="progress-status">Preparing…</div>
        </div>
        <form id="trainForm" method="post" action="<?php echo htmlspecialchars($formAction); ?>" data-endpoint="<?php echo htmlspecialchars($formAction); ?>">
            <input type="hidden" name="action" value="train">
            <pre id="trainLogs" class="train-log hidden" hidden></pre>
            <button type="submit">train</button>
        </form>
    </div>







    <div class="card" id="modelCard">
        <?php echo render_model_card_inner($modelCardData); ?>
    </div>










    <br><br><br><br><br>
    <div class="card">
        <h2>Predict</h2>
        <form id="predictForm" method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($formAction); ?>" data-endpoint="<?php echo htmlspecialchars($formAction); ?>">
            <input type="hidden" name="action" value="predict">
            <input type="file" name="file" accept=".csv" required>
            <button type="submit">predict</button>
        </form>
    </div>
    <div class="card">
        <h2>Log Console</h2>
        <pre id="logConsole" class="log-console"></pre>
    </div>
    <div class="card">
        <h2>Results</h2>
        <div id="results"></div>
    </div>
</div>
<script src="<?php echo htmlspecialchars($assetBase); ?>/assets/app.js" defer></script>
</body>
</html>
