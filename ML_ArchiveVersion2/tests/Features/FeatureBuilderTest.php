<?php

declare(strict_types=1);

namespace App\Tests\Features;

use App\Data\DataFrame;
use App\Features\FeatureBuilder;
use App\Features\Transformers\Preprocessor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FeatureBuilderTest extends TestCase
{
    public function testIdentifiersAreExcludedFromPreprocessedFeatures(): void
    {
        $config = json_decode((string)file_get_contents(__DIR__ . '/../../config/features.json'), true);
        $builder = new FeatureBuilder($config);

        $rows = [
            [
                'company_id' => 1,
                'fiscal_year' => 2022,
                'label' => 0,
                'assets' => 1000,
                'CurrentAssets' => 400,
                'NoncurrentAssets' => 600,
                'liabilities' => 600,
                'CurrentLiabilities' => 200,
                'NoncurrentLiabilities' => 400,
                'equity' => 400,
                'SalesRevenueNet' => 500,
                'revenues' => 500,
                'CostOfGoodsSold' => 300,
                'OperatingIncomeLoss' => 80,
                'NetIncomeLoss' => 60,
                'CashAndCashEquivalentsAtCarryingValue' => 100,
                'NetCashProvidedByUsedInOperatingActivities' => 120,
                'NetCashProvidedByUsedInInvestingActivities' => -50,
                'NetCashProvidedByUsedInFinancingActivities' => 20,
                'CashAndCashEquivalentsPeriodIncreaseDecrease' => 90,
                'PaymentsOfDividends' => 30,
                'RepaymentsOfDebt' => 20,
                'ProceedsFromIssuanceOfDebt' => 10,
                'InterestExpense' => 10,
                'DepreciationAndAmortization' => 15,
                'LongTermDebtNoncurrent' => 300,
                'ShortTermBorrowings' => 50,
                'InventoryNet' => 100,
                'AccountsReceivableNetCurrent' => 90,
                'AccountsPayableCurrent' => 70,
                'EntityIncorporationStateCountryCode' => 'DE',
                'EntityFilerCategory' => 'LargeAcceleratedFiler',
                'EntityRegistrantName' => 'Example Corp',
                'EntityCentralIndexKey' => '0001234567',
                'TradingSymbol' => 'EXM',
                'DocumentFiscalYearFocus' => 'FY',
                'CurrentFiscalYearEndDate' => '12-31',
            ],
            [
                'company_id' => 1,
                'fiscal_year' => 2023,
                'label' => 1,
                'assets' => 1100,
                'CurrentAssets' => 420,
                'NoncurrentAssets' => 680,
                'liabilities' => 650,
                'CurrentLiabilities' => 210,
                'NoncurrentLiabilities' => 440,
                'equity' => 450,
                'SalesRevenueNet' => 520,
                'revenues' => 520,
                'CostOfGoodsSold' => 320,
                'OperatingIncomeLoss' => 70,
                'NetIncomeLoss' => 50,
                'CashAndCashEquivalentsAtCarryingValue' => 120,
                'NetCashProvidedByUsedInOperatingActivities' => 140,
                'NetCashProvidedByUsedInInvestingActivities' => -40,
                'NetCashProvidedByUsedInFinancingActivities' => 30,
                'CashAndCashEquivalentsPeriodIncreaseDecrease' => 100,
                'PaymentsOfDividends' => 35,
                'RepaymentsOfDebt' => 22,
                'ProceedsFromIssuanceOfDebt' => 8,
                'InterestExpense' => 12,
                'DepreciationAndAmortization' => 18,
                'LongTermDebtNoncurrent' => 320,
                'ShortTermBorrowings' => 55,
                'InventoryNet' => 110,
                'AccountsReceivableNetCurrent' => 95,
                'AccountsPayableCurrent' => 75,
                'EntityIncorporationStateCountryCode' => 'CA',
                'EntityFilerCategory' => 'AcceleratedFiler',
                'EntityRegistrantName' => 'Example Corp',
                'EntityCentralIndexKey' => '0001234567',
                'TradingSymbol' => 'EXM',
                'DocumentFiscalYearFocus' => 'FY',
                'CurrentFiscalYearEndDate' => '12-31',
            ],
        ];

        $df = DataFrame::fromRows($rows);
        $featureDf = $builder->build($df);

        $preprocessor = new Preprocessor(['winsorize' => ['lower' => 0.0, 'upper' => 1.0]], $config['categorical']);
        $preprocessor->fit($featureDf->toRows());
        $featureNames = $preprocessor->getFeatureNames();

        $this->assertContains('current_ratio', $featureNames);
        $this->assertContains('debt_to_assets', $featureNames);
        $this->assertNotContains('EntityRegistrantName', $featureNames);
        $this->assertNotContains('EntityCentralIndexKey', $featureNames);
        $this->assertNotContains('TradingSymbol', $featureNames);
        $this->assertNotContains('DocumentFiscalYearFocus', $featureNames);
        $this->assertNotContains('CurrentFiscalYearEndDate', $featureNames);

        $currentRatio = $featureDf->col('current_ratio');
        $currentTrend = $featureDf->col('current_ratio_tren');
        $this->assertNull($currentTrend[0]);
        $this->assertEqualsWithDelta(0.0, (float) $currentTrend[1], 1e-9);

        $currentVol = $featureDf->col('current_ratio_vola');
        $this->assertNull($currentVol[0]);
        $this->assertEqualsWithDelta(0.0, (float) $currentVol[1], 1e-9);

        $interaction = $featureDf->col('leverage_profitability');
        $this->assertCount(2, $interaction);
        $this->assertNotNull($interaction[0]);
        $this->assertNotNull($interaction[1]);
    }

    public function testDisallowedRawColumnsTriggerException(): void
    {
        $config = json_decode((string)file_get_contents(__DIR__ . '/../../config/features.json'), true);
        $config['raw_columns'][] = 'EntityRegistrantName';
        $builder = new FeatureBuilder($config);

        $df = DataFrame::fromRows([
            [
                'company_id' => 1,
                'fiscal_year' => 2022,
                'label' => 0,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $builder->build($df);
    }
}

