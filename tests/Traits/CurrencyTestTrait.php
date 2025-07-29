<?php

declare(strict_types=1);

namespace Tests\Traits;

trait CurrencyTestTrait
{
    /**
     * Assert that currency rate structure is valid
     * Updated to match actual API response structure
     */
    protected function assertValidCurrencyRateStructure(array $rate, string $currency): void
    {
        $this->assertArrayHasKey('currency', $rate);
        $this->assertArrayHasKey('name', $rate);
        $this->assertArrayHasKey('baseRate', $rate);
        $this->assertArrayHasKey('buyRate', $rate);
        $this->assertArrayHasKey('sellRate', $rate);
        $this->assertArrayHasKey('supportsBuying', $rate);

        $this->assertEquals($currency, $rate['currency']);
        $this->assertIsString($rate['name']);
        $this->assertIsFloat($rate['baseRate']);
        $this->assertIsFloat($rate['sellRate']);
        $this->assertIsBool($rate['supportsBuying']);

        // Buy rate can be null for some currencies
        if ($rate['buyRate'] !== null) {
            $this->assertIsFloat($rate['buyRate']);
        }
    }

    /**
     * Assert margin calculation for Tier 1 currencies (EUR, USD)
     */
    protected function assertTier1Margins(array $rate, float $expectedBaseRate): void
    {
        $this->assertEquals($expectedBaseRate, $rate['baseRate']);
        $this->assertEquals($expectedBaseRate - 0.15, $rate['buyRate']);
        $this->assertEquals($expectedBaseRate + 0.11, $rate['sellRate']);
        $this->assertTrue($rate['supportsBuying']);
    }

    /**
     * Assert margin calculation for Tier 2 currencies (CZK, IDR, BRL)
     */
    protected function assertTier2Margins(array $rate, float $expectedBaseRate): void
    {
        $this->assertEquals($expectedBaseRate, $rate['baseRate']);
        $this->assertNull($rate['buyRate']); // No buying for these currencies
        $this->assertEquals($expectedBaseRate + 0.2, $rate['sellRate']);
        $this->assertFalse($rate['supportsBuying']);
    }

    /**
     * Assert that historical rate DTO structure is valid
     */
    protected function assertValidHistoricalRateDTO($dto): void
    {
        $this->assertInstanceOf(\App\DTO\HistoricalRateDTO::class, $dto);

        $array = $dto->toArray();
        $this->assertArrayHasKey('date', $array);
        $this->assertArrayHasKey('baseRate', $array); // camelCase jak w rzeczywistej implementacji
        $this->assertArrayHasKey('buyRate', $array);  // camelCase jak w rzeczywistej implementacji
        $this->assertArrayHasKey('sellRate', $array); // camelCase jak w rzeczywistej implementacji

        $this->assertIsString($array['date']);
        $this->assertIsFloat($array['baseRate']);
        $this->assertIsFloat($array['sellRate']);

        // Buy rate can be null
        if ($array['buyRate'] !== null) {
            $this->assertIsFloat($array['buyRate']);
        }
    }

    /**
     * Assert that historical rates collection DTO structure is valid
     */
    protected function assertValidHistoricalRatesCollectionDTO($collection): void
    {
        $this->assertInstanceOf(\App\DTO\HistoricalRatesCollectionDTO::class, $collection);

        $array = $collection->toArray();
        $this->assertArrayHasKey('currency', $array);
        $this->assertArrayHasKey('fromDate', $array); // camelCase jak w rzeczywistej implementacji
        $this->assertArrayHasKey('toDate', $array);   // camelCase jak w rzeczywistej implementacji
        $this->assertArrayHasKey('rates', $array);
        $this->assertArrayHasKey('count', $array);

        $this->assertIsString($array['currency']);
        $this->assertIsString($array['fromDate']);
        $this->assertIsString($array['toDate']);
        $this->assertIsArray($array['rates']);
        $this->assertIsInt($array['count']);

        // Validate each rate in collection
        foreach ($array['rates'] as $rate) {
            $this->assertArrayHasKey('date', $rate);
            $this->assertArrayHasKey('baseRate', $rate); // camelCase jak w rzeczywistej implementacji
            $this->assertArrayHasKey('buyRate', $rate);  // camelCase jak w rzeczywistej implementacji
            $this->assertArrayHasKey('sellRate', $rate); // camelCase jak w rzeczywistej implementacji
        }
    }

    /**
     * Create test DateTime object
     */
    protected function createTestDate(string $date): \DateTime
    {
        return \DateTime::createFromFormat('Y-m-d', $date);
    }

    /**
     * Assert that date is a business day (Monday-Friday)
     */
    protected function assertBusinessDay(\DateTime $date): void
    {
        $dayOfWeek = (int) $date->format('N'); // 1 = Monday, 7 = Sunday
        $this->assertTrue(
            $dayOfWeek >= 1 && $dayOfWeek <= 5,
            "Date {$date->format('Y-m-d')} is not a business day"
        );
    }

    /**
     * Assert that date is a weekend (Saturday-Sunday)
     */
    protected function assertWeekend(\DateTime $date): void
    {
        $dayOfWeek = (int) $date->format('N'); // 1 = Monday, 7 = Sunday
        $this->assertTrue(
            $dayOfWeek >= 6 && $dayOfWeek <= 7,
            "Date {$date->format('Y-m-d')} is not a weekend"
        );
    }

    /**
     * Create mock configuration for testing
     */
    protected function getMockConfiguration(): array
    {
        return [
            'currencies' => [
                'EUR' => [
                    'name' => 'Euro',
                    'buy_margin' => -0.15,
                    'sell_margin' => 0.11
                ],
                'USD' => [
                    'name' => 'US Dollar',
                    'buy_margin' => -0.15,
                    'sell_margin' => 0.11
                ],
                'CZK' => [
                    'name' => 'Czech Koruna',
                    'buy_margin' => null,
                    'sell_margin' => 0.2
                ],
                'IDR' => [
                    'name' => 'Indonesian Rupiah',
                    'buy_margin' => null,
                    'sell_margin' => 0.2
                ],
                'BRL' => [
                    'name' => 'Brazilian Real',
                    'buy_margin' => null,
                    'sell_margin' => 0.2
                ]
            ]
        ];
    }
}