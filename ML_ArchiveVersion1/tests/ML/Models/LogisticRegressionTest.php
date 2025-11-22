<?php

declare(strict_types=1);

namespace App\Tests\ML\Models;

use App\ML\Models\LogisticRegression;
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
}
