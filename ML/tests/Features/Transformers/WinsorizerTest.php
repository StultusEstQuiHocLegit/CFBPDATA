<?php

declare(strict_types=1);

namespace App\Tests\Features\Transformers;

use App\Features\Transformers\Winsorizer;
use PHPUnit\Framework\TestCase;

class WinsorizerTest extends TestCase
{
    public function testClipsValuesWhenFirstRowIsNull(): void
    {
        $training = [
            ['metric' => null],
            ['metric' => 1000],
            ['metric' => -1000],
        ];

        $winsorizer = new Winsorizer(0.01, 0.99);
        $winsorizer->fit($training);

        $rows = [
            ['metric' => null],
            ['metric' => 5000],
            ['metric' => -5000],
        ];

        $transformed = $winsorizer->transform($rows);

        $this->assertNull($transformed[0]['metric']);
        $this->assertSame(1000.0, $transformed[1]['metric']);
        $this->assertSame(-1000.0, $transformed[2]['metric']);
    }
}
