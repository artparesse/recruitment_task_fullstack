<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CurrencyRateService;
use App\Service\CachedCurrencyRateService;
use App\Service\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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

            return new JsonResponse($rates);

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
        $httpStatus = match($healthData['status']) {
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
        
        return new JsonResponse($healthData, $httpStatus);
    }
}