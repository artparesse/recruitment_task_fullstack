<?php

declare(strict_types=1);

namespace App\Repository;

interface CurrencyRepositoryInterface
{
    /**
     * Get current exchange rates for all currencies from NBP API
     * 
     * @return array Array of currency rates: ['EUR' => 4.50, 'USD' => 4.20, ...]
     */
    public function getCurrentRates(): array;

    /**
     * Get current exchange rate for a specific currency from NBP API  
     * 
     * @param string $currency Currency code (e.g., 'EUR', 'USD')
     * @return float|null Exchange rate or null if not found
     */
    public function getCurrentRate(string $currency): ?float;
}