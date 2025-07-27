<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CurrencyRateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class CurrencyController extends AbstractController
{
    private CurrencyRateService $currencyRateService;
    private LoggerInterface $logger;

    public function __construct(
        CurrencyRateService $currencyRateService,
        LoggerInterface $logger
    ) {
        $this->currencyRateService = $currencyRateService;
        $this->logger = $logger;
    }

    /**
     * GET /api/currencies/current
     * Returns current exchange rates for all supported currencies
     */
    public function getCurrentRates(): JsonResponse
    {
        try {
            $rates = $this->currencyRateService->getCurrentRates();

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
}