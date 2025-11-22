<?php

declare(strict_types=1);

namespace App\Tests\ML\Eval;

use App\ML\Eval\Thresholds;
use PHPUnit\Framework\TestCase;

class ThresholdsTest extends TestCase
{
    public function testChoosePrefersAveragePrecisionAndHighestStrictRecallThreshold(): void
    {
        $proba = [0.92, 0.85, 0.8, 0.7, 0.55, 0.4, 0.35, 0.2];
        $labels = [0, 1, 0, 1, 1, 0, 1, 0];
        $conf = (object) [
            'optimize_for' => 'pr_auc',
            'strict_recall_at' => 0.6,
        ];

        $result = Thresholds::choose($proba, $labels, $conf);

        $this->assertArrayHasKey('primary', $result);
        $this->assertArrayHasKey('recall_target', $result);
        $this->assertEqualsWithDelta(0.55, $result['primary']['threshold'], 1e-9, 'Best threshold should maximise average precision segment.');
        $this->assertEqualsWithDelta(0.55, $result['recall_target']['threshold'], 1e-9, 'Strict recall threshold should pick the highest viable cut-off.');
        $this->assertGreaterThanOrEqual(0.6, $result['recall_target']['recall']);
    }
}
