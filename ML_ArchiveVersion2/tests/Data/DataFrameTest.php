<?php

declare(strict_types=1);

namespace App\Tests\Data;

use App\Data\DataFrame;
use PHPUnit\Framework\TestCase;

final class DataFrameTest extends TestCase
{
    public function testFromRowsIncludesColumnsIntroducedLater(): void
    {
        $rows = [
            ['a' => 1, 'b' => 2],
            ['a' => 3, 'c' => 4],
        ];

        $df = DataFrame::fromRows($rows);

        $this->assertSame(['a', 'b', 'c'], $df->columns());
        $this->assertSame([1, 3], $df->col('a'));
        $this->assertSame([2, null], $df->col('b'));
        $this->assertSame([null, 4], $df->col('c'));
    }
}
