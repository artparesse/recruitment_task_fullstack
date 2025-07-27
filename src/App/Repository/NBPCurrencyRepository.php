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
}