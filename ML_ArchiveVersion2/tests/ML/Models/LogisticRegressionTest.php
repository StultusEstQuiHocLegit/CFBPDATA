<?php

declare(strict_types=1);

namespace App\Tests\ML\Models;

use App\Features\Transformers\Preprocessor;
use App\ML\Calibrator\Isotonic;
use App\ML\Models\GradientBoosting;
use App\ML\Models\LogisticRegression;
use App\ML\Training\Trainer;
use App\Persistence\ModelStore;
use PHPUnit\Framework\TestCase;

class LogisticRegressionTest extends TestCase
{
    public function testGradientClippingAndEarlyStoppingPreventProbabilityCollapse(): void
    {
        $Xtr = [
            [1200.0, 0.5],
            [950.0, 0.1],
            [0.02, -0.4],
            [0.05, -0.6],
        ];
        $ytr = [1, 1, 0, 0];

        $Xva = [
            [1100.0, 0.3],
            [0.03, -0.2],
        ];
        $yva = [1, 0];

        $model = new LogisticRegression(0.5, 800, 0.8, 5.0, 5, 1e-4);
        $model->setValidationData($Xva, $yva);
        $model->fit($Xtr, $ytr, array_fill(0, count($Xtr), 1.0));

        $proba = $model->predictProba(array_merge($Xtr, $Xva));
        $unique = array_unique($proba, SORT_NUMERIC);

        $this->assertGreaterThan(2, count($unique), 'Safeguards should avoid collapsed probability bins.');
        foreach ($proba as $p) {
            $this->assertGreaterThan(0.0, $p);
            $this->assertLessThan(1.0, $p);
        }
    }

    public function testSelectedLambdaPersistsThroughSaveAndLoad(): void
    {
        $trainRows = [
            ['company_id' => 1, 'fiscal_year' => 2020, 'label' => 0, 'ratio' => 0.2],
            ['company_id' => 1, 'fiscal_year' => 2021, 'label' => 1, 'ratio' => 0.8],
            ['company_id' => 2, 'fiscal_year' => 2020, 'label' => 0, 'ratio' => 0.3],
            ['company_id' => 2, 'fiscal_year' => 2021, 'label' => 0, 'ratio' => 0.4],
        ];
        $validRows = [
            ['company_id' => 3, 'fiscal_year' => 2020, 'label' => 0, 'ratio' => 0.25],
            ['company_id' => 3, 'fiscal_year' => 2021, 'label' => 1, 'ratio' => 0.9],
        ];
        $pre = new Preprocessor(['winsorize' => ['lower' => 0.0, 'upper' => 1.0]], []);
        $pre->fit($trainRows);
        $Xtr = $pre->transform($trainRows);
        $Xva = $pre->transform($validRows);
        $ytr = array_map(static fn($row) => $row['label'], $trainRows);
        $yva = array_map(static fn($row) => $row['label'], $validRows);

        $weights = Trainer::classWeights($ytr);
        $modelConf = (object) [
            'type' => 'logistic_regression',
            'l2' => 0.1,
            'l2_grid' => [0.1, 0.5],
            'iterations' => 200,
            'learning_rate' => 0.1,
        ];
        $result = Trainer::train($Xtr, $ytr, $Xva, $yva, $weights, $modelConf);
        $model = $result['model'];
        $hyper = $result['hyperparameters'];
        $this->assertArrayHasKey('selected_l2', $hyper);

        $probaVaRaw = $model->predictProba($Xva);
        $calibrator = Isotonic::fit($probaVaRaw, $yva);

        $metrics = [
            'calibration' => ['type' => 'isotonic'],
            'selected_l2' => $hyper['selected_l2'],
        ];
        $baseDir = sys_get_temp_dir() . '/ml_modelstore_' . uniqid('', true);
        ModelStore::saveAll($model, $pre, $calibrator, (object)[], $metrics, $hyper, Isotonic::class, $baseDir);

        $reloaded = LogisticRegression::load($baseDir . '/models/model.bin');
        $meta = json_decode((string) file_get_contents($baseDir . '/models/metadata.json'), true);

        $this->assertEqualsWithDelta($hyper['selected_l2'], $reloaded->getLambda(), 1e-9);
        $this->assertEqualsWithDelta($hyper['selected_l2'], $meta['selected_l2'], 1e-9);

        $this->removeDir($baseDir);
    }

    public function testGradientBoostingSerialisationRoundTrip(): void
    {
        $X = [
            [0.0],
            [1.0],
            [2.0],
            [3.0],
        ];
        $y = [0, 0, 1, 1];
        $model = new GradientBoosting(10, 0.2, 1);
        $model->fit($X, $y);
        $proba = $model->predictProba($X);

        $tmp = tempnam(sys_get_temp_dir(), 'gb');
        if ($tmp === false) {
            $this->fail('Failed to allocate temporary file for gradient boosting test.');
        }
        $model->save($tmp);
        $loaded = GradientBoosting::load($tmp);
        $this->assertEqualsWithDelta($proba[0], $loaded->predictProba([$X[0]])[0], 1e-9);
        $this->assertEqualsWithDelta($proba[3], $loaded->predictProba([$X[3]])[0], 1e-9);
        @unlink($tmp);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->removeDir($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
