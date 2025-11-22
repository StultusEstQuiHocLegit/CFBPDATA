<?php

declare(strict_types=1);

namespace App\Tests\Features;

use App\Data\DataFrame;
use App\Features\FeatureBuilder;
use PHPUnit\Framework\TestCase;

class FeatureBuilderExtraTest extends TestCase
{
    public function testExtendedRatiosTrendsAndDomainBounds(): void
    {
        $config = json_decode((string) file_get_contents(__DIR__ . '/../../config/features.json'), true, 512, JSON_THROW_ON_ERROR);
        $builder = new FeatureBuilder($config);

        $rows = [
            [
                'company_id' => 10,
                'fiscal_year' => 2020,
                'label' => 0,
                'assets' => 200,
                'liabilities' => 120,
                'CurrentAssets' => 120,
                'CurrentLiabilities' => 60,
                'InventoryNet' => 20,
                'CashAndCashEquivalentsAtCarryingValue' => 40,
                'SalesRevenueNet' => 150,
                'CostOfGoodsSold' => 90,
                'OperatingIncomeLoss' => 35,
                'NetIncomeLoss' => 30,
                'NetCashProvidedByUsedInOperatingActivities' => 20,
                'InterestExpense' => 5,
                'DepreciationAndAmortization' => 10,
                'LongTermDebtNoncurrent' => 70,
                'ShortTermBorrowings' => 10,
                'AccountsReceivableNetCurrent' => 45,
                'AccountsPayableCurrent' => 30,
                'RepaymentsOfDebt' => 4,
                'ProceedsFromIssuanceOfDebt' => 6,
                'PaymentsOfDividends' => 8,
            ],
            [
                'company_id' => 10,
                'fiscal_year' => 2021,
                'label' => 0,
                'assets' => 220,
                'liabilities' => 130,
                'CurrentAssets' => 140,
                'CurrentLiabilities' => 65,
                'InventoryNet' => 22,
                'CashAndCashEquivalentsAtCarryingValue' => 60,
                'SalesRevenueNet' => 180,
                'CostOfGoodsSold' => 110,
                'OperatingIncomeLoss' => 40,
                'NetIncomeLoss' => 36,
                'NetCashProvidedByUsedInOperatingActivities' => 25,
                'InterestExpense' => 6,
                'DepreciationAndAmortization' => 12,
                'LongTermDebtNoncurrent' => 75,
                'ShortTermBorrowings' => 12,
                'AccountsReceivableNetCurrent' => 50,
                'AccountsPayableCurrent' => 32,
                'RepaymentsOfDebt' => 5,
                'ProceedsFromIssuanceOfDebt' => 8,
                'PaymentsOfDividends' => 10,
            ],
            [
                'company_id' => 10,
                'fiscal_year' => 2022,
                'label' => 1,
                'assets' => 250,
                'liabilities' => 150,
                'CurrentAssets' => 200,
                'CurrentLiabilities' => 30,
                'InventoryNet' => 25,
                'CashAndCashEquivalentsAtCarryingValue' => 200,
                'SalesRevenueNet' => 100,
                'CostOfGoodsSold' => 60,
                'OperatingIncomeLoss' => 20,
                'NetIncomeLoss' => 200,
                'NetCashProvidedByUsedInOperatingActivities' => -10,
                'InterestExpense' => 8,
                'DepreciationAndAmortization' => 14,
                'LongTermDebtNoncurrent' => 80,
                'ShortTermBorrowings' => 15,
                'AccountsReceivableNetCurrent' => 40,
                'AccountsPayableCurrent' => 28,
                'RepaymentsOfDebt' => 6,
                'ProceedsFromIssuanceOfDebt' => 4,
                'PaymentsOfDividends' => 0,
            ],
        ];

        $df = DataFrame::fromRows($rows);
        $featureDf = $builder->build($df);

        $netMargin = $featureDf->col('net_margin');
        $this->assertEqualsWithDelta(0.2, (float) $netMargin[0], 1e-6);
        $this->assertEqualsWithDelta(0.2, (float) $netMargin[1], 1e-6);
        $this->assertEquals(1.0, (float) $netMargin[2], 'Net margin should be clipped to domain bounds.');

        $assetTurnover = $featureDf->col('asset_turnover');
        $this->assertEqualsWithDelta(0.75, (float) $assetTurnover[0], 1e-6);

        $inventoryTurnover = $featureDf->col('inventory_turnover');
        $this->assertEqualsWithDelta(4.5, (float) $inventoryTurnover[0], 1e-6);

        $cashRatio = $featureDf->col('cash_ratio');
        $this->assertEqualsWithDelta(0.6666667, (float) $cashRatio[0], 1e-5);
        $this->assertEquals(5.0, (float) $cashRatio[2], 'Cash ratio should respect clipping to 5.0.');

        $debtToEquity = $featureDf->col('debt_to_equity');
        $this->assertEqualsWithDelta(1.5, (float) $debtToEquity[0], 1e-6);

        $netTrend = $featureDf->col('net_margin_tren');
        $this->assertNull($netTrend[0]);
        $this->assertEqualsWithDelta(0.0, (float) $netTrend[1], 1e-6);
        $this->assertEqualsWithDelta(0.8, (float) $netTrend[2], 1e-6);

        $netVol = $featureDf->col('net_margin_vola');
        $this->assertNull($netVol[0]);
        $this->assertNull($netVol[1]);
        $this->assertEqualsWithDelta(0.37712, (float) $netVol[2], 1e-4);

        $accruals = $featureDf->col('Accruals');
        $currentRatio = $featureDf->col('current_ratio');
        $liquidityAccruals = $featureDf->col('liquidity_accruals');
        $this->assertEqualsWithDelta((float) $currentRatio[0] * (float) $accruals[0], (float) $liquidityAccruals[0], 1e-6);

        $daysAR = $featureDf->col('DaysAR');
        $this->assertEqualsWithDelta(109.5, (float) $daysAR[0], 1e-6);

        $this->assertLessThanOrEqual(1.0, (float) $featureDf->col('roa_tren')[2]);
        $this->assertLessThanOrEqual(2.0, (float) $featureDf->col('cash_ratio_vola')[2]);
    }
}
