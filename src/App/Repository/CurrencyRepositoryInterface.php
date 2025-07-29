<?php

declare(strict_types=1);

namespace App\Repository;

interface CurrencyRepositoryInterface
{
    /**
     * Get current exchange rates for all currencies
     */
    public function getCurrentRates(): array;

    /**
     * Get current exchange rate for a specific currency
     */
    public function getCurrentRate(string $currency): ?float;

    /**
     * Get historical exchange rates for a specific currency within date range
     */
    public function getHistoricalRates(string $currency, \DateTime $fromDate, \DateTime $toDate): array;

    /**
     * Get last N days exchange rates for a specific currency
     */
    public function getLastDaysRates(string $currency, \DateTime $referenceDate, int $daysCount): array;
}