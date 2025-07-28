<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ConfigurationService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class NBPCurrencyRepository implements CurrencyRepositoryInterface
{
    private Client $httpClient;
    private ConfigurationService $config;
    private LoggerInterface $logger;

    public function __construct(
        Client $httpClient,
        ConfigurationService $config,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get current exchange rates for all currencies from NBP API
     * Strategy: tables/A/today → tables/A → individual rates
     */
    public function getCurrentRates(): array
    {
        // Strategy 1: Try tables/A/today endpoint
        try {
            $todayUrl = $this->config->getNbpApiUrl('tables_today_url');
            $response = $this->httpClient->get($todayUrl, ['timeout' => 10]);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data && isset($data[0]['rates'])) {
                $this->logger->info('NBP API: Successfully fetched from tables/today endpoint');
                return $this->extractRatesFromTableResponse($data[0]['rates']);
            }

            throw new \RuntimeException('Invalid response from tables/today endpoint');

        } catch (\Exception $e) {
            $this->logger->warning('NBP API: tables/today endpoint failed', [
                'error' => $e->getMessage()
            ]);
        }

        // Strategy 2: Fallback to tables/A endpoint
        try {
            $tablesUrl = $this->config->getNbpApiUrl('tables_url');
            $response = $this->httpClient->get($tablesUrl, ['timeout' => 10]);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data && isset($data[0]['rates'])) {
                $this->logger->info('NBP API: Successfully fetched from tables endpoint (fallback)');
                return $this->extractRatesFromTableResponse($data[0]['rates']);
            }

            throw new \RuntimeException('Invalid response from tables endpoint');

        } catch (\Exception $e) {
            $this->logger->warning('NBP API: tables endpoint failed', [
                'error' => $e->getMessage()
            ]);
        }

        // Strategy 3: Individual rates
        try {
            $this->logger->warning('NBP API: Using individual rates fallback strategy');
            return $this->getCurrentRatesIndividual();
        } catch (\Exception $e) {
            $this->logger->error('NBP API: Individual rates strategy failed', [
                'error' => $e->getMessage()
            ]);
        }

        $this->logger->error('NBP API: All strategies failed');
        return [];
    }

    /**
     * Get current exchange rate for a specific currency from NBP API
     */
    public function getCurrentRate(string $currency): ?float
    {
        try {
            $rateUrl = str_replace(
                '{currency}',
                strtolower($currency),
                $this->config->getNbpApiUrl('rates_url')
            );

            $response = $this->httpClient->get($rateUrl, ['timeout' => 10]);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data && isset($data['rates'][0]['mid'])) {
                $this->logger->info("NBP API: Successfully fetched rate for {$currency}");
                return (float) $data['rates'][0]['mid'];
            }

            throw new \RuntimeException("Invalid response for {$currency}");

        } catch (\Exception $e) {
            $this->logger->error("NBP API: Failed to fetch rate for {$currency}", [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Extract rates from NBP table response and filter supported currencies
     */
    private function extractRatesFromTableResponse(array $rates): array
    {
        $result = [];
        $supportedCurrencies = $this->config->getSupportedCurrencies();

        foreach ($rates as $rate) {
            $currency = $rate['code'];
            if (in_array($currency, $supportedCurrencies)) {
                $result[$currency] = (float) $rate['mid'];
            }
        }

        return $result;
    }

    /**
     * Fallback method: fetch rates individually for each supported currency
     */
    private function getCurrentRatesIndividual(): array
    {
        $result = [];
        $supportedCurrencies = $this->config->getSupportedCurrencies();

        foreach ($supportedCurrencies as $currency) {
            $rate = $this->getCurrentRate($currency);
            if ($rate !== null) {
                $result[$currency] = $rate;
            }
        }

        if (empty($result)) {
            $this->logger->error('NBP API: No individual rates could be fetched');
            throw new \RuntimeException('Unable to fetch any currency rates from NBP API');
        }

        $this->logger->info('NBP API: Successfully fetched individual rates', [
            'currencies_fetched' => count($result)
        ]);

        return $result;
    }

    /**
     * Get historical exchange rates for a specific currency within date range
     */
    public function getHistoricalRates(string $currency, \DateTime $fromDate, \DateTime $toDate): array
    {
        // Strategy 1: Try tables/A/{startDate}/{endDate} for specific date range
        try {
            $startDateStr = $fromDate->format('Y-m-d');
            $endDateStr = $toDate->format('Y-m-d');

            $url = str_replace(
                ['{startDate}', '{endDate}'],
                [$startDateStr, $endDateStr],
                $this->config->getNbpApiUrl('historical_tables_url')
            );

            $response = $this->httpClient->get($url, ['timeout' => 10]);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data && is_array($data) && !empty($data)) {
                $this->logger->info("NBP API: Successfully fetched historical data for {$currency} from {$startDateStr} to {$endDateStr}");
                return $this->extractHistoricalRatesFromTables($data, $currency);
            }

            throw new \RuntimeException("Invalid response for historical tables data");

        } catch (\Exception $e) {
            $this->logger->warning("NBP API: Historical tables endpoint failed for {$currency}", [
                'error' => $e->getMessage(),
                'fromDate' => $fromDate->format('Y-m-d'),
                'toDate' => $toDate->format('Y-m-d')
            ]);
        }

        // Strategy 2: Fallback to single currency historical endpoint
        try {
            // Calculate days difference for last/X endpoint
            $daysDiff = $fromDate->diff($toDate)->days + 1;

            $url = str_replace(
                ['{currency}', '{count}'],
                [strtolower($currency), (string) $daysDiff],
                $this->config->getNbpApiUrl('historical_rates_url')
            );

            $response = $this->httpClient->get($url, ['timeout' => 10]);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data && isset($data['rates']) && is_array($data['rates'])) {
                $this->logger->info("NBP API: Successfully fetched historical rates for {$currency} (single currency fallback)");
                return $this->extractHistoricalRatesFromSingleCurrency($data['rates']);
            }

            throw new \RuntimeException("Invalid response for single currency historical data");

        } catch (\Exception $e) {
            $this->logger->error("NBP API: All historical strategies failed for {$currency}", [
                'error' => $e->getMessage(),
                'fromDate' => $fromDate->format('Y-m-d'),
                'toDate' => $toDate->format('Y-m-d')
            ]);
        }

        return [];
    }

    /**
     * Get historical exchange rates for a specific currency (N days from reference date)
     */
    public function getLastDaysRates(string $currency, \DateTime $referenceDate, int $daysCount): array
    {
        // Strategy 1: Try to get data for specific date range using tables endpoint
        try {
            // Calculate date range: N business days back from reference date
            $endDate = clone $referenceDate;
            $startDate = clone $referenceDate;
            $startDate->modify("-{$daysCount} days"); // Approximate, will be adjusted by NBP API

            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');

            $url = str_replace(
                ['{startDate}', '{endDate}'],
                [$startDateStr, $endDateStr],
                $this->config->getNbpApiUrl('historical_tables_url')
            );

            $response = $this->httpClient->get($url, ['timeout' => 10]);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data && is_array($data) && !empty($data)) {
                $this->logger->info("NBP API: Successfully fetched tables for date range {$startDateStr} to {$endDateStr} for {$currency}");
                $rates = $this->extractHistoricalRatesFromTables($data, $currency);

                // Filter to get only N days from reference date
                $filteredRates = $this->filterRatesByDateRange($rates, $referenceDate, $daysCount);
                return $filteredRates;
            }

            throw new \RuntimeException("Invalid response for date range {$startDateStr} to {$endDateStr}");

        } catch (\Exception $e) {
            $this->logger->warning("NBP API: Date range tables endpoint failed for {$currency}", [
                'error' => $e->getMessage(),
                'referenceDate' => $referenceDate->format('Y-m-d'),
                'daysCount' => $daysCount
            ]);
        }

        // Strategy 2: Fallback to individual date queries for each day
        try {
            $rates = [];
            $currentDate = clone $referenceDate;

            // Get rates for each day going back
            for ($i = 0; $i < $daysCount; $i++) {
                $dateStr = $currentDate->format('Y-m-d');

                $url = str_replace(
                    ['{currency}', '{date}'],
                    [strtolower($currency), $dateStr],
                    $this->config->getNbpApiUrl('historical_rate_date_url')
                );

                try {
                    $response = $this->httpClient->get($url, ['timeout' => 5]);
                    $data = json_decode($response->getBody()->getContents(), true);

                    if ($data && isset($data['rates'][0]['mid'])) {
                        $rates[] = [
                            'date' => $dateStr,
                            'rate' => (float) $data['rates'][0]['mid']
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip this date if no data available
                    $this->logger->debug("NBP API: No data for {$currency} on {$dateStr}");
                }

                $currentDate->modify('-1 day');
            }

            if (!empty($rates)) {
                $this->logger->info("NBP API: Successfully fetched individual dates for {$currency}", [
                    'rates_count' => count($rates),
                    'referenceDate' => $referenceDate->format('Y-m-d')
                ]);

                // Sort by date ascending
                usort($rates, function ($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });

                return $rates;
            }

            throw new \RuntimeException("No data found for {$currency} in date range");

        } catch (\Exception $e) {
            $this->logger->error("NBP API: All strategies failed for {$currency}", [
                'error' => $e->getMessage(),
                'referenceDate' => $referenceDate->format('Y-m-d'),
                'daysCount' => $daysCount
            ]);
        }

        return [];
    }

    /**
     * Extract historical rates from tables response data
     */
    private function extractHistoricalRatesFromTables(array $tablesData, string $currency): array
    {
        $rates = [];

        foreach ($tablesData as $table) {
            if (!isset($table['effectiveDate']) || !isset($table['rates'])) {
                continue;
            }

            $date = $table['effectiveDate'];

            foreach ($table['rates'] as $rate) {
                if ($rate['code'] === $currency) {
                    $rates[] = [
                        'date' => $date,
                        'rate' => (float) $rate['mid']
                    ];
                    break;
                }
            }
        }

        // Sort by date ascending
        usort($rates, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $rates;
    }

    /**
     * Extract historical rates from single currency response data
     */
    private function extractHistoricalRatesFromSingleCurrency(array $ratesData): array
    {
        $rates = [];

        foreach ($ratesData as $rate) {
            if (!isset($rate['effectiveDate']) || !isset($rate['mid'])) {
                continue;
            }

            $rates[] = [
                'date' => $rate['effectiveDate'],
                'rate' => (float) $rate['mid']
            ];
        }

        // Sort by date ascending
        usort($rates, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $rates;
    }

    /**
     * Filter historical rates to include only the specified number of days from the reference date
     */
    private function filterRatesByDateRange(array $rates, \DateTime $referenceDate, int $daysCount): array
    {
        // Sort rates by date descending (newest first)
        usort($rates, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Take only the first N rates (closest to reference date)
        return array_slice($rates, 0, $daysCount);
    }
}