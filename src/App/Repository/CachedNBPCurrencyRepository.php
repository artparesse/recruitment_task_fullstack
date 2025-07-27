<?php

declare(strict_types=1);

namespace App\Repository;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class CachedNBPCurrencyRepository implements CurrencyRepositoryInterface
{
    private NBPCurrencyRepository $repository;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        NBPCurrencyRepository $repository,
        CacheInterface $nbpApiCache,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->cache = $nbpApiCache;
        $this->logger = $logger;
    }

    /**
     * Get current exchange rates for all currencies from NBP API with caching
     * Strategy: Cache → tables/A/today → tables/A → individual rates → Cached fallback
     */
    public function getCurrentRates(): array
    {
        $today = date('Y-m-d');

        // Strategy 1: Try cache first, then tables/A/today endpoint
        try {
            $cacheKey = "nbp_table_today_{$today}";

            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($today) {
                // TTL is configured at pool level in cache.yaml

                $this->logger->info('NBP API: Fetching fresh data from tables/today endpoint');
                $rates = $this->repository->getCurrentRates();

                if (empty($rates)) {
                    throw new \RuntimeException('No rates returned from repository');
                }

                $this->logger->info('NBP API: Successfully fetched and cached from tables/today endpoint', [
                    'currencies_count' => count($rates),
                    'cache_key' => $item->getKey()
                ]);

                return $rates;
            });

        } catch (\Exception $e) {
            $this->logger->warning('NBP API: tables/today endpoint failed or cache miss', [
                'error' => $e->getMessage(),
                'cache_key' => "nbp_table_today_{$today}"
            ]);
        }

        // Strategy 2: Fallback to tables/A endpoint with cache
        try {
            $cacheKey = "nbp_table_a_{$today}";

            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($today) {
                // TTL is configured at pool level in cache.yaml

                $this->logger->info('NBP API: Fetching fresh data from tables endpoint (fallback)');
                $rates = $this->repository->getCurrentRates();

                if (empty($rates)) {
                    throw new \RuntimeException('No rates returned from repository');
                }

                $this->logger->info('NBP API: Successfully fetched and cached from tables endpoint (fallback)', [
                    'currencies_count' => count($rates),
                    'cache_key' => $item->getKey()
                ]);

                return $rates;
            });

        } catch (\Exception $e) {
            $this->logger->warning('NBP API: tables endpoint failed or cache miss', [
                'error' => $e->getMessage(),
                'cache_key' => "nbp_table_a_{$today}"
            ]);
        }

        // Strategy 3: Individual rates with cache
        try {
            $this->logger->warning('NBP API: Using individual rates fallback strategy');
            return $this->getCurrentRatesIndividualCached();
        } catch (\Exception $e) {
            $this->logger->error('NBP API: Individual rates strategy failed', [
                'error' => $e->getMessage()
            ]);
        }

        // Strategy 4: Cache fallback - try to get any cached data from previous days
        return $this->getCachedFallback();
    }

    /**
     * Get current exchange rate for a specific currency from NBP API with caching
     */
    public function getCurrentRate(string $currency): ?float
    {
        $today = date('Y-m-d');
        $cacheKey = "nbp_rate_{$currency}_{$today}";

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($currency) {
                // TTL is configured at pool level in cache.yaml

                $this->logger->info("NBP API: Fetching fresh rate for {$currency}");
                $rate = $this->repository->getCurrentRate($currency);

                if ($rate === null) {
                    throw new \RuntimeException("No rate returned for {$currency}");
                }

                $this->logger->info("NBP API: Successfully fetched and cached rate for {$currency}", [
                    'rate' => $rate,
                    'cache_key' => $item->getKey()
                ]);

                return $rate;
            });

        } catch (\Exception $e) {
            $this->logger->error("NBP API: Failed to fetch rate for {$currency}", [
                'error' => $e->getMessage(),
                'cache_key' => $cacheKey
            ]);

            // Try to get cached value from previous days as fallback
            return $this->getCachedRateFallback($currency);
        }
    }

    /**
     * Cached version of individual rates fallback
     */
    private function getCurrentRatesIndividualCached(): array
    {
        $result = [];
        $supportedCurrencies = ['EUR', 'USD', 'CZK', 'IDR', 'BRL']; // From config

        foreach ($supportedCurrencies as $currency) {
            $rate = $this->getCurrentRate($currency); // This method is already cached
            if ($rate !== null) {
                $result[$currency] = $rate;
            }
        }

        if (empty($result)) {
            throw new \RuntimeException('Unable to fetch any currency rates from NBP API (cached)');
        }

        return $result;
    }

    /**
     * Fallback to cached data from previous days when all NBP strategies fail
     */
    private function getCachedFallback(): array
    {
        $this->logger->warning('NBP API: Trying cached fallback from previous days');

        // Try cache from last 3 days
        for ($i = 1; $i <= 3; $i++) {
            $fallbackDate = date('Y-m-d', strtotime("-{$i} days"));

            // Try table cache first
            $cacheKey = "nbp_table_today_{$fallbackDate}";
            $cachedData = $this->cache->get($cacheKey, function () {
                return null;
            });

            if ($cachedData !== null) {
                $this->logger->info("NBP API: Using cached fallback data from {$fallbackDate}", [
                    'cache_key' => $cacheKey,
                    'fallback_days' => $i
                ]);
                return $cachedData;
            }

            // Try tables/A cache
            $cacheKey = "nbp_table_a_{$fallbackDate}";
            $cachedData = $this->cache->get($cacheKey, function () {
                return null;
            });

            if ($cachedData !== null) {
                $this->logger->info("NBP API: Using cached fallback data from {$fallbackDate}", [
                    'cache_key' => $cacheKey,
                    'fallback_days' => $i
                ]);
                return $cachedData;
            }
        }

        $this->logger->error('NBP API: No cached fallback data available');
        return [];
    }

    /**
     * Fallback to cached rate from previous days for specific currency
     */
    private function getCachedRateFallback(string $currency): ?float
    {
        // Try cache from last 3 days
        for ($i = 1; $i <= 3; $i++) {
            $fallbackDate = date('Y-m-d', strtotime("-{$i} days"));
            $cacheKey = "nbp_rate_{$currency}_{$fallbackDate}";

            $cachedRate = $this->cache->get($cacheKey, function () {
                return null;
            });

            if ($cachedRate !== null) {
                $this->logger->info("NBP API: Using cached fallback rate for {$currency} from {$fallbackDate}", [
                    'cache_key' => $cacheKey,
                    'fallback_days' => $i,
                    'rate' => $cachedRate
                ]);
                return $cachedRate;
            }
        }

        $this->logger->warning("NBP API: No cached fallback rate available for {$currency}");
        return null;
    }
}