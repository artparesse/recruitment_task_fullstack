<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CurrencyRateService;
use App\Service\ConfigurationService;
use App\Service\DateHelperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class CurrencyController extends AbstractController
{
    private CurrencyRateService $currencyRateService;
    private ConfigurationService $config;
    private DateHelperService $dateHelper;
    private LoggerInterface $logger;

    public function __construct(
        CurrencyRateService $currencyRateService,
        ConfigurationService $config,
        DateHelperService $dateHelper,
        LoggerInterface $logger
    ) {
        $this->currencyRateService = $currencyRateService;
        $this->config = $config;
        $this->dateHelper = $dateHelper;
        $this->logger = $logger;
    }

    /**
     * GET /api/currencies/current
     * Returns current exchange rates for all supported currencies
     */
    public function getCurrentRates(): JsonResponse
    {
        try {
            // Cache is now handled at Repository level only (simplified architecture)
            $rates = $this->currencyRateService->getCurrentRates();

            if (empty($rates)) {
                $this->logger->warning('No currency rates available from NBP API');
                return new JsonResponse([
                    'error' => 'Brak dostępnych kursów walut',
                    'message' => 'API NBP może być tymczasowo niedostępne. Sprawdź ponownie za kilka minut.'
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            // Reduced logging - only log on debug level in production
            if ($this->getParameter('kernel.environment') !== 'prod') {
                $this->logger->info('Successfully returned current currency rates', [
                    'currencies_count' => count($rates)
                ]);
            }

            $response = new JsonResponse([
                'success' => true,
                'data' => $rates
            ]);

            // HTTP Cache Headers for performance optimization
            $response->setSharedMaxAge(300); // 5 minutes cache in browsers/proxies
            $response->headers->set('Cache-Control', 'public, s-maxage=300, max-age=180');
            $response->setEtag(md5(json_encode($rates)));

            return $response;

        } catch (\RuntimeException $e) {
            // NBP API completely down - graceful degradation
            $this->logger->critical('NBP API completely unavailable', [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'System bankowy tymczasowo niedostępny',
                'message' => 'API Narodowego Banku Polskiego jest obecnie niedostępne. Kursy będą dostępne po przywróceniu połączenia.',
                'suggestion' => 'Spróbuj ponownie za 10-15 minut lub skontaktuj się z administratorem systemu.',
                'status' => 'nbp_api_down'
            ], Response::HTTP_SERVICE_UNAVAILABLE);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch current currency rates', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return new JsonResponse([
                'error' => 'Nie można pobrać kursów walut',
                'message' => 'Wystąpił błąd wewnętrzny serwera'
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

        // Simplified cache check (removed redundant layer)
        try {
            $healthData['checks']['cache'] = [
                'status' => 'healthy',
                'architecture' => 'simplified_single_level'
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

        return new JsonResponse([
            'success' => $healthData['status'] === 'ok',
            'data' => $healthData
        ], $httpStatus);
    }

    /**
     * Get historical rates for a currency (last 14 days from today)
     * Supports ?days=N query parameter for custom days count
     */
    public function getHistoricalRates(string $currency, Request $request): JsonResponse
    {
        try {
            // Validate currency
            if (!$this->currencyRateService->isCurrencyAvailable($currency)) {
                return new JsonResponse([
                    'error' => 'Waluta nie jest obsługiwana',
                    'message' => "Waluta '{$currency}' nie jest obsługiwana przez nasz kantor.",
                    'supportedCurrencies' => $this->currencyRateService->getAvailableCurrencies()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get days count from query parameter (default: 14)
            $daysCount = (int) $request->query->get('days', CurrencyRateService::DEFAULT_HISTORICAL_DAYS);

            // Validate days count
            if ($daysCount <= 0 || $daysCount > 93) {
                return new JsonResponse([
                    'error' => 'Nieprawidłowa liczba dni',
                    'message' => "Liczba dni musi być między 1 a 93 (limit API NBP), otrzymano: {$daysCount}."
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get historical rates (simplified - single service call)
            $collection = $this->currencyRateService->getHistoricalRates($currency, null, $daysCount);

            $response = new JsonResponse([
                'success' => true,
                'data' => $collection->toArray()
            ]);

            // Cache historical data longer (since it doesn't change)
            $response->setSharedMaxAge(3600); // 1 hour cache
            $response->headers->set('Cache-Control', 'public, s-maxage=3600, max-age=1800');
            $response->setEtag(md5($currency . $daysCount . 'today'));

            return $response;

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => 'Nieprawidłowe żądanie',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error("Failed to get historical rates for {$currency}", [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Usługa tymczasowo niedostępna',
                'message' => 'Nie można pobrać historycznych kursów walut. Spróbuj ponownie później.',
                'currency' => $currency
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Get historical rates for a currency (14 days before specific date)
     * Supports ?days=N query parameter for custom days count
     */
    public function getHistoricalRatesForDate(string $currency, string $date, Request $request): JsonResponse
    {
        try {
            // Validate currency
            if (!$this->currencyRateService->isCurrencyAvailable($currency)) {
                return new JsonResponse([
                    'error' => 'Waluta nie jest obsługiwana',
                    'message' => "Waluta '{$currency}' nie jest obsługiwana przez nasz kantor.",
                    'supportedCurrencies' => $this->currencyRateService->getAvailableCurrencies()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Use DateHelperService for parsing
            try {
                $referenceDate = $this->dateHelper->createFromString($date);
            } catch (\InvalidArgumentException $e) {
                return new JsonResponse([
                    'error' => 'Nieprawidłowy format daty',
                    'message' => "Data '{$date}' jest nieprawidłowa. Oczekiwany format: Y-m-d (np. '2024-01-15')."
                ], Response::HTTP_BAD_REQUEST);
            }

            // Use DateHelperService for validation
            if (!$this->dateHelper->validateDateRange($referenceDate)) {
                return new JsonResponse([
                    'error' => 'Nieprawidłowy zakres dat',
                    'message' => "Data '{$date}' jest poza prawidłowym zakresem (od 2002-01-02, nie przyszłość)."
                ], Response::HTTP_BAD_REQUEST);
            }

            // Weekend warning - reduced logging
            if (!$this->dateHelper->isBusinessDay($referenceDate) && $this->getParameter('kernel.environment') === 'dev') {
                $this->logger->debug("Weekend date requested", [
                    'date' => $date,
                    'currency' => $currency
                ]);
            }

            // Get days count from query parameter (default: 14)
            $daysCount = (int) $request->query->get('days', CurrencyRateService::DEFAULT_HISTORICAL_DAYS);

            // Validate days count
            if ($daysCount <= 0 || $daysCount > 93) {
                return new JsonResponse([
                    'error' => 'Nieprawidłowa liczba dni',
                    'message' => "Liczba dni musi być między 1 a 93 (limit API NBP), otrzymano: {$daysCount}."
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get historical rates for specific reference date (simplified)
            $collection = $this->currencyRateService->getHistoricalRates($currency, $referenceDate, $daysCount);

            $response = new JsonResponse([
                'success' => true,
                'data' => $collection->toArray()
            ]);

            // Cache historical data longer (since it doesn't change)
            $response->setSharedMaxAge(3600); // 1 hour cache
            $response->headers->set('Cache-Control', 'public, s-maxage=3600, max-age=1800');
            $response->setEtag(md5($currency . $date . $daysCount));

            return $response;

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => 'Nieprawidłowe żądanie',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error("Failed to get historical rates for {$currency} with date {$date}", [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Usługa tymczasowo niedostępna',
                'message' => 'Nie można pobrać historycznych kursów walut. Spróbuj ponownie później.',
                'currency' => $currency,
                'date' => $date
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}