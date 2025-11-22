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

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRAMANN CFDATA</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="container">
    <h1>Machine Learning Application (Regression)</h1>
    <div class="card">
        <h2>Train Model</h2>
        <div id="trainProgressContainer" class="progress-container hidden">
            <div class="progress-label">Model training</div>
            <div class="progress-track">
                <div id="trainProgressBar" class="progress-bar"></div>
            </div>
            <div id="trainProgressStatus" class="progress-status">Preparing…</div>
        </div>
        <form id="trainForm" method="post">
            <input type="hidden" name="action" value="train">
            <pre id="trainLogs" class="train-log hidden"></pre>
            <button type="submit">train</button>
        </form>
    </div>







    <div class="card">
        <h2>Model</h2>
        <?php
        $metadata = load_json_file(__DIR__ . '/../models/metadata.json');
        $metrics = load_json_file(__DIR__ . '/../reports/metrics.json');
        $calibrator = load_serialized_file(__DIR__ . '/../models/calibrator.bin');
        $modelInfo = load_serialized_file(__DIR__ . '/../models/model.bin');

        $snapshot = $metadata['timestamp'] ?? 'unknown';
        $validationYear = $metadata['config']['split']['valid_year'] ?? 'N/A';
        $testYear = $metadata['config']['split']['test_year'] ?? 'N/A';
        $weightingRaw = $metadata['config']['class_weighting'] ?? null;
        $weighting = is_string($weightingRaw) ? str_replace('_', '-', $weightingRaw) : 'unknown weighting';
        $calibrationRaw = $metadata['config']['calibration'] ?? 'calibration';
        $calibrationMethod = is_string($calibrationRaw) ? strtolower($calibrationRaw) : 'calibration';

        $optimizeForKey = $metadata['config']['thresholds']['optimize_for'] ?? null;
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

        $bestThreshold = $metrics['thresholds']['best'] ?? null;
        $strictRecallTarget = $metadata['config']['thresholds']['strict_recall_at'] ?? null;
        $strictKey = null;
        if (is_numeric($strictRecallTarget)) {
            $strictKey = 'recall' . (int) round((float) $strictRecallTarget * 100);
        }
        $strictThreshold = $strictKey && isset($metrics['thresholds'][$strictKey]) ? $metrics['thresholds'][$strictKey] : null;
        $sameThreshold = is_numeric($bestThreshold) && is_numeric($strictThreshold) ? abs((float) $bestThreshold - (float) $strictThreshold) < 1e-9 : false;

        $bestConfusion = $metrics['confusion_best'] ?? [];

        $bestTP = isset($bestConfusion['TP']) ? (string) $bestConfusion['TP'] : 'N/A';
        $bestFP = isset($bestConfusion['FP']) ? (string) $bestConfusion['FP'] : 'N/A';
        $bestTN = isset($bestConfusion['TN']) ? (string) $bestConfusion['TN'] : 'N/A';
        $bestFN = isset($bestConfusion['FN']) ? (string) $bestConfusion['FN'] : 'N/A';
        $bestPrecision = format_number($bestConfusion['precision'] ?? null);
        $bestRecall = format_number($bestConfusion['recall'] ?? null);
        $bestF1 = format_number($bestConfusion['f1'] ?? null);

        $strictRecallText = is_numeric($strictRecallTarget) ? format_number($strictRecallTarget, 2) : 'N/A';
        $strictThresholdText = is_numeric($strictThreshold) ? format_number($strictThreshold) : 'N/A';
        $thresholdSummary = $sameThreshold
            ? 'matches the same threshold'
            : (is_numeric($strictThreshold) ? 'uses threshold ' . $strictThresholdText : 'has no available threshold');

        $lambda = format_number($modelInfo['lambda'] ?? null);
        $iterations = isset($modelInfo['iterations']) && is_numeric($modelInfo['iterations']) ? (string) (int) $modelInfo['iterations'] : 'N/A';
        $learningRate = format_number($modelInfo['learningRate'] ?? null);
        $bias = format_number($modelInfo['bias'] ?? null);
        ?>
        <p><strong>Logistic regression bankruptcy classifier</strong> trained on grouped annual filings (validation year <?php echo htmlspecialchars((string) $validationYear); ?>, test year <?php echo htmlspecialchars((string) $testYear); ?>) with <?php echo htmlspecialchars((string) $weighting); ?> class weighting and <?php echo htmlspecialchars((string) $calibrationMethod); ?> calibration. Latest model snapshot: <code><?php echo htmlspecialchars((string) $snapshot); ?></code>.</p>
        <ul>
            <li>Optimisation target: <?php echo htmlspecialchars((string) $optimisationTarget); ?> (PR AUC = <?php echo htmlspecialchars($prAuc); ?>, ROC AUC = <?php echo htmlspecialchars($rocAuc); ?>, Brier score = <?php echo htmlspecialchars($brier); ?>).</li>
            <li>Calibration thresholds (<?php echo htmlspecialchars((string) $calibrationMethod); ?>): probability grid <?php echo htmlspecialchars($probabilityGridText); ?> -&gt; calibrated scores <?php echo htmlspecialchars($calibratedScoresText); ?>.</li>
            <li>"Best" decision point yields TP=<?php echo htmlspecialchars($bestTP); ?>, FP=<?php echo htmlspecialchars($bestFP); ?>, TN=<?php echo htmlspecialchars($bestTN); ?>, FN=<?php echo htmlspecialchars($bestFN); ?> (precision <?php echo htmlspecialchars($bestPrecision); ?>, recall <?php echo htmlspecialchars($bestRecall); ?>, F1 <?php echo htmlspecialchars($bestF1); ?>), strict recall at <?php echo htmlspecialchars($strictRecallText); ?> <?php echo htmlspecialchars($thresholdSummary); ?>.</li>
            <li>L2 regularisation λ=<?php echo htmlspecialchars($lambda); ?>, <?php echo htmlspecialchars($iterations); ?> gradient-descent epochs, learning rate <?php echo htmlspecialchars($learningRate); ?>. Bias = <?php echo htmlspecialchars($bias); ?>.</li>
        </ul>
        <details class="info-panel">
            <summary>Training metadata &amp; feature order</summary>
            <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../models/metadata.json')); ?>
            </pre>
        </details>
        <details class="info-panel">
            <summary>Evaluation metrics (validation/test)</summary>
            <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../reports/metrics.json')); ?>
            </pre>
        </details>
        <details class="info-panel">
            <summary>Calibration curve (isotonic)</summary>
            <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../models/calibrator.bin', true)); ?>
            </pre>
        </details>
        <details class="info-panel">
            <summary>Model coefficients (bias, λ, iterations, learning rate, per-feature weights)</summary>
            <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../models/model.bin', true)); ?>
            </pre>
        </details>
        <details class="info-panel">
            <summary>Preprocessing pipeline</summary>
            <p>Pipeline order: winsorisation -> median imputation (+missingness flags) -> robust scaling -> one-hot encoding.</p>
            <details class="info-panel nested">
                <summary>Winsorizer parameters & cutoffs</summary>
                <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../models/preprocessor.bin.step0', true)); ?>
                </pre>
            </details>
            <details class="info-panel nested">
                <summary>Imputer medians & indicator mapping</summary>
                <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../models/preprocessor.bin.step1', true)); ?>
                </pre>
            </details>
            <details class="info-panel nested">
                <summary>Robust scaler medians & IQRs</summary>
                <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../models/preprocessor.bin.step2', true)); ?>
                </pre>
            </details>
            <details class="info-panel nested">
                <summary>One-hot encoder categories</summary>
                <pre>
<?php echo htmlspecialchars(render_pretty_json(__DIR__ . '/../models/preprocessor.bin.step3', true)); ?>
                </pre>
            </details>
        </details>
    </div>










    <br><br><br><br><br>
    <div class="card">
        <h2>Predict</h2>
        <form id="predictForm" method="post" enctype="multipart/form-data">
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
<script src="assets/app.js"></script>
</body>
</html>