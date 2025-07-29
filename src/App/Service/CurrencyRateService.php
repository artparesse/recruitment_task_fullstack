<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CurrencyRepositoryInterface;
use App\DTO\HistoricalRateDTO;
use App\DTO\HistoricalRatesCollectionDTO;

class CurrencyRateService
{
    public const DEFAULT_HISTORICAL_DAYS = 14;

    private CurrencyRepositoryInterface $currencyRepository;
    private ConfigurationService $config;
    private DateHelperService $dateHelper;

    public function __construct(
        CurrencyRepositoryInterface $currencyRepository,
        ConfigurationService $config,
        DateHelperService $dateHelper
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->config = $config;
        $this->dateHelper = $dateHelper;
    }

    /**
     * Get current rates for all supported currencies with calculated margins
     */
    public function getCurrentRates(): array
    {
        $nbpRates = $this->currencyRepository->getCurrentRates();
        $result = [];

        foreach ($nbpRates as $currency => $baseRate) {
            if ($this->isCurrencyAvailable($currency)) {
                $result[$currency] = $this->calculateMarginRates($currency, $baseRate);
            }
        }

        return $result;
    }

    /**
     * Calculate buy and sell rates with margins for a specific currency
     */
    public function calculateMarginRates(string $currency, float $baseRate): array
    {
        $buyMargin = $this->config->getBuyMargin($currency);
        $sellMargin = $this->config->getSellMargin($currency);

        $result = [
            'currency' => $currency,
            'name' => $this->config->getCurrencyName($currency),
            'baseRate' => $baseRate,
            'sellRate' => $baseRate + $sellMargin,
            'supportsBuying' => $this->config->supportsBuying($currency)
        ];

        if ($buyMargin !== null) {
            $result['buyRate'] = $baseRate + $buyMargin;
        } else {
            $result['buyRate'] = null;
        }

        return $result;
    }

    /**
     * Get available currencies that are supported by the kantor
     */
    public function getAvailableCurrencies(): array
    {
        return $this->config->getSupportedCurrencies();
    }

    /**
     * Check if currency is available for exchange
     */
    public function isCurrencyAvailable(string $currency): bool
    {
        return in_array($currency, $this->getAvailableCurrencies());
    }

    /**
     * Get historical rates for a specific currency with applied margins (default: 14 days)
     */
    public function getHistoricalRates(string $currency, ?\DateTime $referenceDate = null, int $daysCount = self::DEFAULT_HISTORICAL_DAYS): HistoricalRatesCollectionDTO
    {
        if ($referenceDate === null) {
            $referenceDate = new \DateTime();
        }

        // Validate currency
        if (!$this->isCurrencyAvailable($currency)) {
            throw new \InvalidArgumentException("Currency {$currency} is not supported");
        }

        // Validate date range
        if (!$this->dateHelper->validateDateRange($referenceDate)) {
            throw new \InvalidArgumentException("Date {$referenceDate->format('Y-m-d')} is out of valid range (from 2002-01-02, not future)");
        }

        // Validate days count
        if ($daysCount <= 0 || $daysCount > 93) {
            throw new \InvalidArgumentException("Days count must be between 1 and 93 (NBP API limit), got: {$daysCount}");
        }

        // NBP API /last/{count} automatically excludes weekends - no need for manual calculation
        $rawRates = $this->currencyRepository->getLastDaysRates($currency, $referenceDate, $daysCount);

        // Convert to DTO with applied margins
        $historicalRates = [];
        $actualStartDate = null;
        $actualEndDate = null;

        foreach ($rawRates as $rateData) {
            $date = new \DateTime($rateData['date']);
            $baseRate = $rateData['rate'];

            // Track actual date range from NBP API response
            if ($actualStartDate === null || $date < $actualStartDate) {
                $actualStartDate = $date;
            }
            if ($actualEndDate === null || $date > $actualEndDate) {
                $actualEndDate = $date;
            }

            $buyMargin = $this->config->getBuyMargin($currency);
            $sellMargin = $this->config->getSellMargin($currency);

            $buyRate = $buyMargin !== null ? $baseRate + $buyMargin : null;
            $sellRate = $baseRate + $sellMargin;

            $historicalRates[] = new HistoricalRateDTO($date, $baseRate, $buyRate, $sellRate);
        }

        // Use actual dates from NBP API response (they already exclude weekends)
        $startDate = $actualStartDate ?? $referenceDate;
        $endDate = $actualEndDate ?? $referenceDate;

        return new HistoricalRatesCollectionDTO($currency, $startDate, $endDate, $historicalRates);
    }

    /**
     * Get historical rates for a specific date range
     */
    public function getHistoricalRatesForDateRange(string $currency, \DateTime $fromDate, \DateTime $toDate): HistoricalRatesCollectionDTO
    {
        // Validate currency
        if (!$this->isCurrencyAvailable($currency)) {
            throw new \InvalidArgumentException("Currency {$currency} is not supported");
        }

        // Validate date range
        if (!$this->dateHelper->validateDateRange($fromDate) || !$this->dateHelper->validateDateRange($toDate)) {
            throw new \InvalidArgumentException("Date range is out of valid range (from 2002-01-02, not future)");
        }

        if ($fromDate > $toDate) {
            throw new \InvalidArgumentException("From date cannot be later than to date");
        }

        // Get raw historical rates from repository
        $rawRates = $this->currencyRepository->getHistoricalRates($currency, $fromDate, $toDate);

        // Convert to DTO with applied margins
        $historicalRates = [];
        foreach ($rawRates as $rateData) {
            $date = new \DateTime($rateData['date']);
            $baseRate = $rateData['rate'];

            $buyMargin = $this->config->getBuyMargin($currency);
            $sellMargin = $this->config->getSellMargin($currency);

            $buyRate = $buyMargin !== null ? $baseRate + $buyMargin : null;
            $sellRate = $baseRate + $sellMargin;

            $historicalRates[] = new HistoricalRateDTO($date, $baseRate, $buyRate, $sellRate);
        }

        return new HistoricalRatesCollectionDTO($currency, $fromDate, $toDate, $historicalRates);
    }
}