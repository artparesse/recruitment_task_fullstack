<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class CachedCurrencyRateService
{
    private CurrencyRateService $currencyRateService;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        CurrencyRateService $currencyRateService,
        CacheInterface $currencyCache,
        LoggerInterface $logger
    ) {
        $this->currencyRateService = $currencyRateService;
        $this->cache = $currencyCache;
        $this->logger = $logger;
    }

    /**
     * Get current rates with caching of calculated margins
     * Cache TTL: configured in cache.yaml (1 hour)
     */
    public function getCurrentRates(): array
    {
        $today = date('Y-m-d');
        $cacheKey = "currency_rates_{$today}";

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) {
                // TTL is configured at pool level in cache.yaml
                $startTime = microtime(true);
                $rates = $this->currencyRateService->getCurrentRates();
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                $this->logger->info('Currency rates calculated and cached', [
                    'cache_key' => $item->getKey(),
                    'currencies_count' => count($rates),
                    'calculation_time_ms' => $duration
                ]);

                return $rates;
            });

        } catch (\Exception $e) {
            $this->logger->error('Failed to get cached currency rates', [
                'error' => $e->getMessage(),
                'cache_key' => $cacheKey
            ]);

            // Fallback to non-cached service
            return $this->currencyRateService->getCurrentRates();
        }
    }

    /**
     * Calculate margin rates for specific currency with caching
     */
    public function calculateMarginRates(string $currency, float $baseRate): array
    {
        $today = date('Y-m-d');
        $cacheKey = "margin_calculation_{$currency}_{$baseRate}_{$today}";

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($currency, $baseRate) {
                // TTL is configured at pool level in cache.yaml
                $result = $this->currencyRateService->calculateMarginRates($currency, $baseRate);

                $this->logger->debug('Margin calculation cached', [
                    'currency' => $currency,
                    'base_rate' => $baseRate,
                    'cache_key' => $item->getKey()
                ]);

                return $result;
            });

        } catch (\Exception $e) {
            $this->logger->error('Failed to get cached margin calculation', [
                'currency' => $currency,
                'base_rate' => $baseRate,
                'error' => $e->getMessage()
            ]);

            // Fallback to non-cached calculation
            return $this->currencyRateService->calculateMarginRates($currency, $baseRate);
        }
    }

    /**
     * Get available currencies with caching
     */
    public function getAvailableCurrencies(): array
    {
        $cacheKey = "available_currencies_" . date('Y-m-d_H'); // Cache per hour

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) {
                // TTL is configured at pool level in cache.yaml
                $currencies = $this->currencyRateService->getAvailableCurrencies();

                $this->logger->info('Available currencies cached', [
                    'currencies' => $currencies,
                    'cache_key' => $item->getKey()
                ]);

                return $currencies;
            });

        } catch (\Exception $e) {
            $this->logger->error('Failed to get cached available currencies', [
                'error' => $e->getMessage()
            ]);

            return $this->currencyRateService->getAvailableCurrencies();
        }
    }

    /**
     * Check if specific currency is available with caching
     */
    public function isCurrencyAvailable(string $currency): bool
    {
        $availableCurrencies = $this->getAvailableCurrencies();
        return in_array($currency, $availableCurrencies);
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(): array
    {
        $today = date('Y-m-d');
        $stats = [
            'cache_keys_checked' => [
                "currency_rates_{$today}",
                "available_currencies_" . date('Y-m-d_H')
            ],
            'cache_pool' => 'currency.cache',
            'ttl_configured_at' => 'pool_level_in_cache.yaml',
            'ttl_seconds' => 3600  // From cache.yaml default_lifetime
        ];

        // Check if main cache key exists
        try {
            $mainCacheKey = "currency_rates_{$today}";
            $cached = $this->cache->get($mainCacheKey, function () {
                return null;
            });
            $stats['main_cache_hit'] = $cached !== null;
        } catch (\Exception $e) {
            $stats['main_cache_hit'] = false;
            $stats['cache_error'] = $e->getMessage();
        }

        return $stats;
    }
}