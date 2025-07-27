<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CurrencyRateService;
use App\Service\CachedCurrencyRateService;
use App\Service\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class CurrencyController extends AbstractController
{
    private CurrencyRateService $currencyRateService;
    private CachedCurrencyRateService $cachedCurrencyRateService;
    private ConfigurationService $config;
    private LoggerInterface $logger;

    public function __construct(
        CurrencyRateService $currencyRateService,
        CachedCurrencyRateService $cachedCurrencyRateService,
        ConfigurationService $config,
        LoggerInterface $logger
    ) {
        $this->currencyRateService = $currencyRateService;
        $this->cachedCurrencyRateService = $cachedCurrencyRateService;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * GET /api/currencies/current
     * Returns current exchange rates for all supported currencies
     */
    public function getCurrentRates(): JsonResponse
    {
        try {
            // Use cached service for better performance
            $rates = $this->cachedCurrencyRateService->getCurrentRates();

            if (empty($rates)) {
                $this->logger->warning('No currency rates available from NBP API');
                return new JsonResponse([
                    'error' => 'No currency rates currently available',
                    'message' => 'NBP API might be temporarily unavailable'
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            $this->logger->info('Successfully returned current currency rates', [
                'currencies_count' => count($rates)
            ]);

            return new JsonResponse([
                'success' => true,
                'data' => $rates
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch current currency rates', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return new JsonResponse([
                'error' => 'Unable to fetch currency rates',
                'message' => 'Internal server error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/currencies/health
     * Returns health status of NBP API and cache system
     */
    public function getHealthStatus(): JsonResponse
    {
        $startTime = microtime(true);
        $healthData = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'checks' => []
        ];

        // Check NBP API availability
        try {
            $availableCurrencies = $this->currencyRateService->getAvailableCurrencies();
            $healthData['checks']['nbp_api'] = [
                'status' => 'healthy',
                'available_currencies' => count($availableCurrencies),
                'currencies' => $availableCurrencies
            ];
        } catch (\Exception $e) {
            $healthData['checks']['nbp_api'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $healthData['status'] = 'degraded';
        }

        // Check cache system
        try {
            $cacheStats = $this->cachedCurrencyRateService->getCacheStats();
            $healthData['checks']['cache'] = [
                'status' => 'healthy',
                'stats' => $cacheStats
            ];
        } catch (\Exception $e) {
            $healthData['checks']['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $healthData['status'] = 'degraded';
        }

        // Check configuration
        try {
            $supportedCurrencies = $this->config->getSupportedCurrencies();
            $healthData['checks']['configuration'] = [
                'status' => 'healthy',
                'supported_currencies' => count($supportedCurrencies),
                'currencies' => $supportedCurrencies
            ];
        } catch (\Exception $e) {
            $healthData['checks']['configuration'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $healthData['status'] = 'error';
        }

        // Performance metrics
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $healthData['performance'] = [
            'response_time_ms' => $responseTime,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ];

        // Determine HTTP status code based on health
        $httpStatus = match ($healthData['status']) {
            'ok' => Response::HTTP_OK,
            'degraded' => Response::HTTP_PARTIAL_CONTENT,
            'error' => Response::HTTP_SERVICE_UNAVAILABLE,
            default => Response::HTTP_INTERNAL_SERVER_ERROR
        };

        $this->logger->info('Health check completed', [
            'status' => $healthData['status'],
            'response_time_ms' => $responseTime,
            'checks' => array_keys($healthData['checks'])
        ]);

        return new JsonResponse([
            'success' => $healthData['status'] === 'ok',
            'data' => $healthData
        ], $httpStatus);
    }

    /**
     * Get historical rates for a currency (last 14 days from today)
     * Supports ?days=N query parameter for custom days count
     * 
     * @Route("/api/currencies/{currency}/history", methods={"GET"})
     */
    public function getHistoricalRates(string $currency, Request $request): JsonResponse
    {
        try {
            // Validate currency
            if (!$this->cachedCurrencyRateService->isCurrencyAvailable($currency)) {
                return new JsonResponse([
                    'error' => 'Currency not supported',
                    'message' => "Currency '{$currency}' is not supported by our exchange service.",
                    'supportedCurrencies' => $this->cachedCurrencyRateService->getAvailableCurrencies()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get days count from query parameter (default: 14)
            $daysCount = (int) $request->query->get('days', CurrencyRateService::DEFAULT_HISTORICAL_DAYS);

            // Validate days count
            if ($daysCount <= 0 || $daysCount > 93) {
                return new JsonResponse([
                    'error' => 'Invalid days count',
                    'message' => "Days count must be between 1 and 93 (NBP API limit), got: {$daysCount}."
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get historical rates (default: today as reference)
            $collection = $this->cachedCurrencyRateService->getHistoricalRates($currency, null, $daysCount);

            $this->logger->info("Historical rates retrieved for {$currency}", [
                'rates_count' => $collection->getCount(),
                'reference_date' => 'today',
                'days_count' => $daysCount
            ]);

            return new JsonResponse([
                'success' => true,
                'data' => $collection->toArray()
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning("Invalid request for historical rates", [
                'currency' => $currency,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Invalid request',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error("Failed to get historical rates for {$currency}", [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Service temporarily unavailable',
                'message' => 'Unable to retrieve historical currency rates. Please try again later.',
                'currency' => $currency
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Get historical rates for a currency (14 days before specific date)
     * Supports ?days=N query parameter for custom days count
     * 
     * @Route("/api/currencies/{currency}/history/{date}", methods={"GET"})
     */
    public function getHistoricalRatesForDate(string $currency, string $date, Request $request): JsonResponse
    {
        try {
            // Validate currency
            if (!$this->cachedCurrencyRateService->isCurrencyAvailable($currency)) {
                return new JsonResponse([
                    'error' => 'Currency not supported',
                    'message' => "Currency '{$currency}' is not supported by our exchange service.",
                    'supportedCurrencies' => $this->cachedCurrencyRateService->getAvailableCurrencies()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate and parse date
            $referenceDate = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$referenceDate) {
                return new JsonResponse([
                    'error' => 'Invalid date format',
                    'message' => "Date '{$date}' is invalid. Expected format: Y-m-d (e.g., '2024-01-15')."
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get days count from query parameter (default: 14)
            $daysCount = (int) $request->query->get('days', CurrencyRateService::DEFAULT_HISTORICAL_DAYS);

            // Validate days count
            if ($daysCount <= 0 || $daysCount > 93) {
                return new JsonResponse([
                    'error' => 'Invalid days count',
                    'message' => "Days count must be between 1 and 93 (NBP API limit), got: {$daysCount}."
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get historical rates for specific reference date
            $collection = $this->cachedCurrencyRateService->getHistoricalRates($currency, $referenceDate, $daysCount);

            $this->logger->info("Historical rates retrieved for {$currency} with reference date", [
                'rates_count' => $collection->getCount(),
                'reference_date' => $date,
                'days_count' => $daysCount
            ]);

            return new JsonResponse([
                'success' => true,
                'data' => $collection->toArray()
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning("Invalid request for historical rates with date", [
                'currency' => $currency,
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Invalid request',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error("Failed to get historical rates for {$currency} with date {$date}", [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Service temporarily unavailable',
                'message' => 'Unable to retrieve historical currency rates. Please try again later.',
                'currency' => $currency,
                'date' => $date
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}