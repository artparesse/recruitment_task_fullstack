<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\CurrencyRateService;
use App\Repository\CurrencyRepositoryInterface;
use App\Service\ConfigurationService;
use App\Service\DateHelperService;
use App\DTO\HistoricalRateDTO;
use App\DTO\HistoricalRatesCollectionDTO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Traits\CurrencyTestTrait;

class CurrencyRateServiceTest extends TestCase
{
    use CurrencyTestTrait;

    private CurrencyRateService $service;
    /** @var CurrencyRepositoryInterface&MockObject */
    private MockObject $mockRepository;
    /** @var ConfigurationService&MockObject */
    private MockObject $mockConfig;
    /** @var DateHelperService&MockObject */
    private MockObject $mockDateHelper;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(CurrencyRepositoryInterface::class);
        $this->mockConfig = $this->createMock(ConfigurationService::class);
        $this->mockDateHelper = $this->createMock(DateHelperService::class);

        $this->service = new CurrencyRateService(
            $this->mockRepository,
            $this->mockConfig,
            $this->mockDateHelper
        );
    }

    public function testCalculateMarginRatesForTier1Currencies(): void
    {
        // Test EUR (Tier 1 currency with buy and sell margins)
        $this->mockConfig->expects($this->once())
            ->method('getBuyMargin')
            ->with('EUR')
            ->willReturn(-0.15);

        $this->mockConfig->expects($this->once())
            ->method('getSellMargin')
            ->with('EUR')
            ->willReturn(0.11);

        $this->mockConfig->expects($this->once())
            ->method('getCurrencyName')
            ->with('EUR')
            ->willReturn('Euro');

        $this->mockConfig->expects($this->once())
            ->method('supportsBuying')
            ->with('EUR')
            ->willReturn(true);

        $result = $this->service->calculateMarginRates('EUR', 4.3456);

        $expected = [
            'currency' => 'EUR',
            'name' => 'Euro',
            'baseRate' => 4.3456,
            'sellRate' => 4.4556, // 4.3456 + 0.11
            'supportsBuying' => true,
            'buyRate' => 4.1956 // 4.3456 - 0.15
        ];

        // Use assertEqualsWithDelta for floating point comparison
        $this->assertEquals($expected['currency'], $result['currency']);
        $this->assertEquals($expected['name'], $result['name']);
        $this->assertEquals($expected['baseRate'], $result['baseRate']);
        $this->assertEqualsWithDelta($expected['sellRate'], $result['sellRate'], 0.0001);
        $this->assertEquals($expected['supportsBuying'], $result['supportsBuying']);
        $this->assertEqualsWithDelta($expected['buyRate'], $result['buyRate'], 0.0001);
    }

    public function testCalculateMarginRatesForTier2Currencies(): void
    {
        // Test CZK (Tier 2 currency - no buying, only selling)
        $this->mockConfig->expects($this->once())
            ->method('getBuyMargin')
            ->with('CZK')
            ->willReturn(null);

        $this->mockConfig->expects($this->once())
            ->method('getSellMargin')
            ->with('CZK')
            ->willReturn(0.2);

        $this->mockConfig->expects($this->once())
            ->method('getCurrencyName')
            ->with('CZK')
            ->willReturn('Czech Koruna');

        $this->mockConfig->expects($this->once())
            ->method('supportsBuying')
            ->with('CZK')
            ->willReturn(false);

        $result = $this->service->calculateMarginRates('CZK', 0.1789);

        $expected = [
            'currency' => 'CZK',
            'name' => 'Czech Koruna',
            'baseRate' => 0.1789,
            'sellRate' => 0.3789, // 0.1789 + 0.2
            'supportsBuying' => false,
            'buyRate' => null
        ];

        $this->assertEquals($expected['currency'], $result['currency']);
        $this->assertEquals($expected['name'], $result['name']);
        $this->assertEquals($expected['baseRate'], $result['baseRate']);
        $this->assertEqualsWithDelta($expected['sellRate'], $result['sellRate'], 0.0001);
        $this->assertEquals($expected['supportsBuying'], $result['supportsBuying']);
        $this->assertNull($result['buyRate']);
    }

    public function testGetCurrentRatesReturnsCorrectStructure(): void
    {
        $mockRates = [
            'EUR' => 4.3456,
            'USD' => 4.0123,
            'CZK' => 0.1789
        ];

        $this->mockRepository->expects($this->once())
            ->method('getCurrentRates')
            ->willReturn($mockRates);

        // Mock isCurrencyAvailable for each currency
        $this->mockConfig->method('getSupportedCurrencies')
            ->willReturn(['EUR', 'USD', 'CZK', 'IDR', 'BRL']);

        // Mock calculateMarginRates dependencies for each currency
        $this->mockConfig->method('getBuyMargin')
            ->willReturnMap([
                ['EUR', -0.15],
                ['USD', -0.15],
                ['CZK', null]
            ]);

        $this->mockConfig->method('getSellMargin')
            ->willReturnMap([
                ['EUR', 0.11],
                ['USD', 0.11],
                ['CZK', 0.2]
            ]);

        $this->mockConfig->method('getCurrencyName')
            ->willReturnMap([
                ['EUR', 'Euro'],
                ['USD', 'US Dollar'],
                ['CZK', 'Czech Koruna']
            ]);

        $this->mockConfig->method('supportsBuying')
            ->willReturnMap([
                ['EUR', true],
                ['USD', true],
                ['CZK', false]
            ]);

        $result = $this->service->getCurrentRates();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        // Test EUR structure (Tier 1)
        $this->assertArrayHasKey('EUR', $result);
        $eurData = $result['EUR'];
        $this->assertEquals('EUR', $eurData['currency']);
        $this->assertEquals('Euro', $eurData['name']);
        $this->assertEquals(4.3456, $eurData['baseRate']);
        $this->assertTrue($eurData['supportsBuying']);
        $this->assertIsFloat($eurData['buyRate']);
        $this->assertIsFloat($eurData['sellRate']);

        // Test CZK structure (Tier 2 - no buying)
        $this->assertArrayHasKey('CZK', $result);
        $czkData = $result['CZK'];
        $this->assertEquals('CZK', $czkData['currency']);
        $this->assertEquals('Czech Koruna', $czkData['name']);
        $this->assertEquals(0.1789, $czkData['baseRate']);
        $this->assertFalse($czkData['supportsBuying']);
        $this->assertNull($czkData['buyRate']);
        $this->assertIsFloat($czkData['sellRate']);
    }

    public function testGetCurrentRatesHandlesEmptyRepository(): void
    {
        $this->mockRepository->expects($this->once())
            ->method('getCurrentRates')
            ->willReturn([]);

        $result = $this->service->getCurrentRates();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCalculateMarginRatesWithZeroBaseRate(): void
    {
        $this->mockConfig->method('getBuyMargin')->willReturn(-0.15);
        $this->mockConfig->method('getSellMargin')->willReturn(0.11);
        $this->mockConfig->method('getCurrencyName')->willReturn('Euro');
        $this->mockConfig->method('supportsBuying')->willReturn(true);

        $result = $this->service->calculateMarginRates('EUR', 0.0);

        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('Euro', $result['name']);
        $this->assertEquals(0.0, $result['baseRate']);
        $this->assertEquals(0.11, $result['sellRate']);
        $this->assertTrue($result['supportsBuying']);
        $this->assertEquals(-0.15, $result['buyRate']);
    }

    public function testCalculateMarginRatesWithNegativeBaseRate(): void
    {
        $this->mockConfig->method('getBuyMargin')->willReturn(-0.15);
        $this->mockConfig->method('getSellMargin')->willReturn(0.11);
        $this->mockConfig->method('getCurrencyName')->willReturn('Euro');
        $this->mockConfig->method('supportsBuying')->willReturn(true);

        // Edge case: negative base rate
        $result = $this->service->calculateMarginRates('EUR', -1.0);

        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('Euro', $result['name']);
        $this->assertEquals(-1.0, $result['baseRate']);
        $this->assertEquals(-0.89, $result['sellRate']);
        $this->assertTrue($result['supportsBuying']);
        $this->assertEquals(-1.15, $result['buyRate']);
    }

    public function testGetAvailableCurrencies(): void
    {
        $expectedCurrencies = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];

        $this->mockConfig->expects($this->once())
            ->method('getSupportedCurrencies')
            ->willReturn($expectedCurrencies);

        $result = $this->service->getAvailableCurrencies();

        $this->assertEquals($expectedCurrencies, $result);
    }

    public function testIsCurrencyAvailable(): void
    {
        $supportedCurrencies = ['EUR', 'USD', 'CZK'];

        $this->mockConfig->expects($this->exactly(3))
            ->method('getSupportedCurrencies')
            ->willReturn($supportedCurrencies);

        $this->assertTrue($this->service->isCurrencyAvailable('EUR'));
        $this->assertTrue($this->service->isCurrencyAvailable('USD'));
        $this->assertFalse($this->service->isCurrencyAvailable('GBP'));
    }

    public function testGetHistoricalRatesReturns14Days(): void
    {
        $referenceDate = new \DateTime('2024-01-15');
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-01-15');

        // Mock supported currencies
        $this->mockConfig->method('getSupportedCurrencies')
            ->willReturn(['EUR', 'USD', 'CZK']);

        // Mock date range validation
        $this->mockDateHelper->expects($this->once())
            ->method('validateDateRange')
            ->with($referenceDate)
            ->willReturn(true);

        // Mock business days calculation
        $this->mockDateHelper->expects($this->once())
            ->method('getBusinessDaysBackRange')
            ->with($referenceDate, 14)
            ->willReturn([
                'startDate' => $startDate,
                'endDate' => $endDate,
                'businessDays' => [$startDate, $endDate],
                'daysCount' => 14
            ]);

        // Mock repository data
        $mockRawRates = [
            ['date' => '2024-01-01', 'rate' => 4.3200],
            ['date' => '2024-01-02', 'rate' => 4.3300],
            ['date' => '2024-01-15', 'rate' => 4.3456]
        ];

        $this->mockRepository->expects($this->once())
            ->method('getLastDaysRates')
            ->with('EUR', $referenceDate, 14)
            ->willReturn($mockRawRates);

        // Mock margins
        $this->mockConfig->method('getBuyMargin')->with('EUR')->willReturn(-0.15);
        $this->mockConfig->method('getSellMargin')->with('EUR')->willReturn(0.11);

        $result = $this->service->getHistoricalRates('EUR', $referenceDate, 14);

        $this->assertValidHistoricalRatesCollectionDTO($result);
        $this->assertEquals('EUR', $result->getCurrency());
        $this->assertEquals(3, $result->getCount());

        $rates = $result->getRates();
        $this->assertCount(3, $rates);

        // Check first rate with margins applied
        $firstRate = $rates[0];
        $this->assertValidHistoricalRateDTO($firstRate);
        $this->assertEquals(4.3200, $firstRate->getBaseRate());
        $this->assertEqualsWithDelta(4.1700, $firstRate->getBuyRate(), 0.0001); // 4.3200 - 0.15
        $this->assertEqualsWithDelta(4.4300, $firstRate->getSellRate(), 0.0001); // 4.3200 + 0.11
    }

    public function testGetHistoricalRatesWithCustomDate(): void
    {
        $customDate = new \DateTime('2023-12-15');

        $this->mockConfig->method('getSupportedCurrencies')
            ->willReturn(['EUR']);

        $this->mockDateHelper->method('validateDateRange')
            ->willReturn(true);

        $this->mockDateHelper->method('getBusinessDaysBackRange')
            ->willReturn([
                'startDate' => new \DateTime('2023-12-01'),
                'endDate' => $customDate,
                'businessDays' => [],
                'daysCount' => 14
            ]);

        $this->mockRepository->method('getLastDaysRates')
            ->willReturn([]);

        $this->mockConfig->method('getBuyMargin')->willReturn(-0.15);
        $this->mockConfig->method('getSellMargin')->willReturn(0.11);

        $result = $this->service->getHistoricalRates('EUR', $customDate, 14);

        $this->assertValidHistoricalRatesCollectionDTO($result);
        $this->assertEquals('EUR', $result->getCurrency());
    }

    public function testGetHistoricalRatesHandlesUnsupportedCurrency(): void
    {
        $this->mockConfig->method('getSupportedCurrencies')
            ->willReturn(['EUR', 'USD']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency GBP is not supported');

        $this->service->getHistoricalRates('GBP');
    }

    public function testGetHistoricalRatesHandlesInvalidDateRange(): void
    {
        $futureDate = new \DateTime('+1 day');

        $this->mockConfig->method('getSupportedCurrencies')
            ->willReturn(['EUR']);

        $this->mockDateHelper->expects($this->once())
            ->method('validateDateRange')
            ->with($futureDate)
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Date ' . $futureDate->format('Y-m-d') . ' is out of valid range');

        $this->service->getHistoricalRates('EUR', $futureDate);
    }

    public function testGetHistoricalRatesHandlesInvalidDaysCount(): void
    {
        $this->mockConfig->method('getSupportedCurrencies')
            ->willReturn(['EUR']);

        // Mock date validation to pass, so we can test days count validation
        $this->mockDateHelper->method('validateDateRange')
            ->willReturn(true);

        // Test days count too high
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Days count must be between 1 and 93');

        // Use past date to avoid date validation error
        $pastDate = new \DateTime('2024-01-15');
        $this->service->getHistoricalRates('EUR', $pastDate, 100);
    }

    public function testGetHistoricalRatesAppliesMargins(): void
    {
        $referenceDate = new \DateTime('2024-01-15');

        $this->mockConfig->method('getSupportedCurrencies')
            ->willReturn(['EUR']);

        $this->mockDateHelper->method('validateDateRange')
            ->willReturn(true);

        $this->mockDateHelper->method('getBusinessDaysBackRange')
            ->willReturn([
                'startDate' => new \DateTime('2024-01-14'),
                'endDate' => $referenceDate,
                'businessDays' => [],
                'daysCount' => 2
            ]);

        $mockRawRates = [
            ['date' => '2024-01-14', 'rate' => 4.3000],
            ['date' => '2024-01-15', 'rate' => 4.3456]
        ];

        $this->mockRepository->method('getLastDaysRates')
            ->willReturn($mockRawRates);

        // Mock margins for EUR
        $this->mockConfig->method('getBuyMargin')->with('EUR')->willReturn(-0.15);
        $this->mockConfig->method('getSellMargin')->with('EUR')->willReturn(0.11);

        $result = $this->service->getHistoricalRates('EUR', $referenceDate, 2);

        $rates = $result->getRates();
        $this->assertCount(2, $rates);

        // Check margins are correctly applied
        $rate1 = $rates[0];
        $this->assertEquals(4.3000, $rate1->getBaseRate());
        $this->assertEqualsWithDelta(4.1500, $rate1->getBuyRate(), 0.0001); // 4.3000 - 0.15
        $this->assertEqualsWithDelta(4.4100, $rate1->getSellRate(), 0.0001); // 4.3000 + 0.11

        $rate2 = $rates[1];
        $this->assertEquals(4.3456, $rate2->getBaseRate());
        $this->assertEqualsWithDelta(4.1956, $rate2->getBuyRate(), 0.0001); // 4.3456 - 0.15
        $this->assertEqualsWithDelta(4.4556, $rate2->getSellRate(), 0.0001); // 4.3456 + 0.11
    }
}