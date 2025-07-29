<?php

declare(strict_types=1);

namespace Tests\Mocks;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class MockNBPApiClient
{
    /**
     * Load fixture data from JSON file
     */
    private static function loadFixture(string $filename): array
    {
        $path = __DIR__ . '/../fixtures/' . $filename;

        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture not found: {$filename}");
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in fixture: {$filename} - " . json_last_error_msg());
        }

        return $data;
    }

    public static function createSuccessfulClient(): Client
    {
        $mock = new MockHandler([
            // tables/A/today response
            new Response(200, [], json_encode(self::getTodayTableResponse())),
            // tables/A response (fallback)
            new Response(200, [], json_encode(self::getTableResponse())),
            // rates/A/{currency} response
            new Response(200, [], json_encode(self::getSingleRateResponse())),
            // additional rates/A/{currency} response for subsequent calls
            new Response(200, [], json_encode(self::getSingleRateResponse())),
            // historical tables response
            new Response(200, [], json_encode(self::getHistoricalTableResponse())),
            // additional responses for subsequent calls
            new Response(200, [], json_encode(self::getSingleRateResponse())),
            new Response(200, [], json_encode(self::getSingleRateResponse())),
        ]);

        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public static function createFailingTodayClient(): Client
    {
        $mock = new MockHandler([
            // tables/A/today fails (404)
            new Response(404, [], json_encode(self::loadFixture('nbp_error_404.json'))),
            // tables/A succeeds (fallback)
            new Response(200, [], json_encode(self::getTableResponse())),
        ]);

        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public static function createCompletelyFailingClient(): Client
    {
        $mock = new MockHandler([
            new RequestException('Connection timeout', new Request('GET', 'test')),
            new RequestException('Connection timeout', new Request('GET', 'test')),
            new RequestException('Connection timeout', new Request('GET', 'test')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public static function createHistoricalDataClient(): Client
    {
        $mock = new MockHandler([
            // Historical tables response
            new Response(200, [], json_encode(self::getHistoricalTableResponse())),
            // Single currency historical response
            new Response(200, [], json_encode(self::getSingleCurrencyHistoricalResponse())),
        ]);

        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public static function getTodayTableResponse(): array
    {
        return self::loadFixture('nbp_current_rates_today.json');
    }

    public static function getTableResponse(): array
    {
        return self::loadFixture('nbp_current_rates_today.json');
    }

    public static function getSingleRateResponse(): array
    {
        return self::loadFixture('nbp_single_currency_usd.json');
    }

    public static function getHistoricalTableResponse(): array
    {
        return self::loadFixture('nbp_historical_14_days.json');
    }

    public static function getSingleCurrencyHistoricalResponse(): array
    {
        // Extract first 3 rates from historical data and format for single currency response
        $historicalData = self::loadFixture('nbp_historical_14_days.json');
        $firstThreeEntries = array_slice($historicalData, 0, 3);
        
        // Convert to single currency format using USD data
        $usdRates = [];
        foreach ($firstThreeEntries as $entry) {
            foreach ($entry['rates'] as $rate) {
                if ($rate['code'] === 'USD') {
                    $usdRates[] = [
                        'no' => $entry['no'],
                        'effectiveDate' => $entry['effectiveDate'],
                        'mid' => $rate['mid']
                    ];
                    break;
                }
            }
        }
        
        return [
            'table' => 'A',
            'currency' => 'dolar amerykański',
            'code' => 'USD',
            'rates' => $usdRates
        ];
    }

    /**
     * Create client that returns 400 error (limit exceeded)
     */
    public static function createLimitExceededClient(): Client
    {
        $mock = new MockHandler([
            new Response(400, [], json_encode(self::loadFixture('nbp_error_400_limit.json')))
        ]);

        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    /**
     * Create client that returns 404 error
     */
    public static function create404Client(): Client
    {
        $mock = new MockHandler([
            new Response(404, [], json_encode(self::loadFixture('nbp_error_404.json')))
        ]);

        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }
}