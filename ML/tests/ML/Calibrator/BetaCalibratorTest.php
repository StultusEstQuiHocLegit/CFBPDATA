<?php

declare(strict_types=1);

namespace App\Tests\ML\Calibrator;

use App\ML\Calibrator\Beta;
use PHPUnit\Framework\TestCase;

class BetaCalibratorTest extends TestCase
{
    public function testBetaCalibrationImprovesLogLoss(): void
    {
        $proba = [0.05, 0.1, 0.2, 0.8, 0.9, 0.95];
        $labels = [0, 0, 0, 1, 1, 1];

        $calibrator = new Beta(0.05, 500);
        $paramsBefore = $calibrator->getParameters();
        $calibrator->fit($proba, $labels);
        $paramsAfter = $calibrator->getParameters();

        $this->assertNotEquals($paramsBefore['a'], $paramsAfter['a']);
        $this->assertNotEquals($paramsBefore['b'], $paramsAfter['b']);

        $calibrated = $calibrator->predict($proba);
        foreach ($calibrated as $value) {
            $this->assertGreaterThanOrEqual(0.0, $value);
            $this->assertLessThanOrEqual(1.0, $value);
        }

        $logLossBefore = $this->logLoss($proba, $labels);
        $logLossAfter = $this->logLoss($calibrated, $labels);
        $this->assertLessThanOrEqual($logLossAfter, $logLossBefore + 1e-6);

        $path = sys_get_temp_dir() . '/beta_calibrator.bin';
        $calibrator->save($path);
        $loaded = Beta::load($path);
        $this->assertEquals($calibrated, $loaded->predict($proba));
    }

    // @param float[] $proba
    // @param int[] $labels
    private function logLoss(array $proba, array $labels): float
    {
        $loss = 0.0;
        $n = count($proba);
        for ($i = 0; $i < $n; $i++) {
            $p = max(1e-15, min(1 - 1e-15, $proba[$i]));
            $y = $labels[$i];
            $loss += -($y * log($p) + (1 - $y) * log(1 - $p));
        }
        return $loss / max(1, $n);
    }
}
