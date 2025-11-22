<?php

declare(strict_types=1);

namespace App\Tests\Features\Transformers;

use App\Features\Transformers\RobustScaler;
use PHPUnit\Framework\TestCase;

class RobustScalerTest extends TestCase
{
    public function testScalesValuesWhenFirstRowIsNull(): void
    {
        $training = [
            ['metric' => null],
            ['metric' => 1000],
            ['metric' => -1000],
        ];

        $scaler = new RobustScaler();
        $scaler->fit($training);

        $rows = [
            ['metric' => null],
            ['metric' => 5000],
        ];

        $transformed = $scaler->transform($rows);

        $this->assertNull($transformed[0]['metric']);
        $this->assertSame(2.5, $transformed[1]['metric']);
    }
}
