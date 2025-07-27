<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CurrencyRepositoryInterface;

class CurrencyRateService
{
    private CurrencyRepositoryInterface $currencyRepository;
    private ConfigurationService $config;

    public function __construct(
        CurrencyRepositoryInterface $currencyRepository,
        ConfigurationService $config
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->config = $config;
    }

    /**
     * Get current rates for all supported currencies with buy/sell margins applied
     * 
     * @return array Array of currency data with structure:
     *               [['currency' => 'EUR', 'base' => 4.50, 'buy' => 4.35, 'sell' => 4.61], ...]
     */
    public function getCurrentRates(): array
    {
        $baseRates = $this->currencyRepository->getCurrentRates();
        $result = [];

        foreach ($this->config->getSupportedCurrencies() as $currency) {
            if (!isset($baseRates[$currency])) {
                continue; // Skip currencies not available from NBP
            }

            $baseRate = $baseRates[$currency];
            $currencyData = $this->calculateMarginRates($currency, $baseRate);
            $result[] = $currencyData;
        }

        return $result;
    }

    /**
     * Calculate buy and sell rates with margins applied for specific currency
     * 
     * @param string $currency Currency code
     * @param float $baseRate Base rate from NBP
     * @return array Array with structure: ['currency' => 'EUR', 'base' => 4.50, 'buy' => 4.35, 'sell' => 4.61]
     */
    public function calculateMarginRates(string $currency, float $baseRate): array
    {
        if ($baseRate <= 0) {
            throw new \InvalidArgumentException("Base rate must be positive, got: {$baseRate}");
        }

        $buyMargin = $this->config->getBuyMargin($currency);
        $sellMargin = $this->config->getSellMargin($currency);

        $currencyData = [
            'currency' => $currency,
            'name' => $this->config->getCurrencyName($currency),
            'base' => round($baseRate, 4),
            'buy' => null,
            'sell' => round($baseRate + $sellMargin, 4)
        ];

        // Only calculate buy rate if currency supports buying
        if ($buyMargin !== null) {
            $currencyData['buy'] = round($baseRate + $buyMargin, 4);
        }

        return $currencyData;
    }

    /**
     * Get available currencies that have current rates from NBP
     * 
     * @return array List of currency codes that are available
     */
    public function getAvailableCurrencies(): array
    {
        try {
            $baseRates = $this->currencyRepository->getCurrentRates();
            $supportedCurrencies = $this->config->getSupportedCurrencies();

            return array_intersect($supportedCurrencies, array_keys($baseRates));
        } catch (\Exception $e) {
            // Return empty array if NBP API is not available
            return [];
        }
    }

    /**
     * Check if specific currency is supported and available
     * 
     * @param string $currency Currency code
     * @return bool True if currency is supported and has current rate
     */
    public function isCurrencyAvailable(string $currency): bool
    {
        $availableCurrencies = $this->getAvailableCurrencies();
        return in_array($currency, $availableCurrencies);
    }
}