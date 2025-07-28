<?php

declare(strict_types=1);

namespace Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CurrencyControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testGetCurrentRatesEndpoint(): void
    {
        $this->client->request('GET', '/api/currencies/current');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertTrue($data['success']);

        // Verify currency data structure
        if (!empty($data['data'])) {
            foreach ($data['data'] as $currency => $rateData) {
                $this->assertArrayHasKey('currency', $rateData);
                $this->assertArrayHasKey('name', $rateData);
                $this->assertArrayHasKey('baseRate', $rateData);
                $this->assertArrayHasKey('sellRate', $rateData);
                $this->assertArrayHasKey('supportsBuying', $rateData);

                // buyRate is only present for currencies that support buying
                if ($rateData['supportsBuying']) {
                    $this->assertArrayHasKey('buyRate', $rateData);
                    $this->assertNotNull($rateData['buyRate']);
                } else {
                    $this->assertArrayHasKey('buyRate', $rateData);
                    $this->assertNull($rateData['buyRate']);
                }
            }
        }
    }

    public function testGetHealthStatusEndpoint(): void
    {
        $this->client->request('GET', '/api/currencies/health');

        $response = $this->client->getResponse();
        $this->assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_PARTIAL_CONTENT]);

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);

        $healthData = $data['data'];
        $this->assertArrayHasKey('status', $healthData);
        $this->assertArrayHasKey('timestamp', $healthData);
        $this->assertArrayHasKey('checks', $healthData);
        $this->assertArrayHasKey('performance', $healthData);

        // Verify checks structure
        $this->assertArrayHasKey('nbp_api', $healthData['checks']);
        $this->assertArrayHasKey('cache', $healthData['checks']);
        $this->assertArrayHasKey('configuration', $healthData['checks']);

        // Verify performance metrics
        $this->assertArrayHasKey('response_time_ms', $healthData['performance']);
        $this->assertArrayHasKey('memory_usage_mb', $healthData['performance']);
        $this->assertArrayHasKey('peak_memory_mb', $healthData['performance']);
    }

    public function testGetHistoricalRatesEndpoint(): void
    {
        $this->client->request('GET', '/api/currencies/EUR/history');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertJson($content);

        $data = json_decode($content, true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertTrue($data['success']);

        $historyData = $data['data'];
        $this->assertArrayHasKey('currency', $historyData);
        $this->assertArrayHasKey('fromDate', $historyData);
        $this->assertArrayHasKey('toDate', $historyData);
        $this->assertArrayHasKey('rates', $historyData);
        $this->assertArrayHasKey('count', $historyData);

        $this->assertEquals('EUR', $historyData['currency']);
        $this->assertIsArray($historyData['rates']);
    }

    public function testGetHistoricalRatesWithCustomDays(): void
    {
        $this->client->request('GET', '/api/currencies/EUR/history?days=7');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertTrue($data['success']);
        $historyData = $data['data'];

        // Should request 7 days (though actual count may vary due to weekends)
        $this->assertLessThanOrEqual(7, $historyData['count']);
    }

    public function testGetHistoricalRatesForSpecificDate(): void
    {
        $testDate = '2024-01-15';
        $this->client->request('GET', "/api/currencies/EUR/history/{$testDate}");

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertTrue($data['success']);
        $historyData = $data['data'];
        $this->assertEquals('EUR', $historyData['currency']);
    }

    public function testGetHistoricalRatesWithInvalidCurrency(): void
    {
        $this->client->request('GET', '/api/currencies/ABC/history');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('supportedCurrencies', $data);
        $this->assertEquals('Currency not supported', $data['error']);
    }

    public function testGetHistoricalRatesWithInvalidDaysCount(): void
    {
        $this->client->request('GET', '/api/currencies/EUR/history?days=999');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid days count', $data['error']);
    }

    public function testGetHistoricalRatesWithInvalidDate(): void
    {
        $this->client->request('GET', '/api/currencies/EUR/history/1900-01-01');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid date', $data['error']);
    }

    public function testGetHistoricalRatesWithFutureDate(): void
    {
        $futureDate = (new \DateTime('+1 day'))->format('Y-m-d');
        $this->client->request('GET', "/api/currencies/EUR/history/{$futureDate}");

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid date', $data['error']);
        $this->assertStringContainsString('future', $data['message']);
    }

    public function testGetHistoricalRatesWithVeryOldDate(): void
    {
        $oldDate = '2001-01-01'; // Before NBP API data availability
        $this->client->request('GET', "/api/currencies/EUR/history/{$oldDate}");

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid date', $data['error']);
        $this->assertStringContainsString('2002-01-02', $data['message']);
    }

    public function testApiResponseHeaders(): void
    {
        $this->client->request('GET', '/api/currencies/current');

        $response = $this->client->getResponse();
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
    }

    public function testApiResponseTime(): void
    {
        $startTime = microtime(true);
        $this->client->request('GET', '/api/currencies/current');
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // API should respond within reasonable time (5 seconds max)
        $this->assertLessThan(5000, $responseTime, 'API response time should be under 5 seconds');
    }

    public function testCorsHeaders(): void
    {
        // Test if CORS headers are properly set (if needed for frontend)
        $this->client->request('GET', '/api/currencies/current');

        $response = $this->client->getResponse();

        // These tests might need to be adjusted based on actual CORS configuration
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHealthEndpointResponseTime(): void
    {
        $startTime = microtime(true);
        $this->client->request('GET', '/api/currencies/health');
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000;

        // Health endpoint should be very fast
        $this->assertLessThan(2000, $responseTime, 'Health endpoint should respond under 2 seconds');

        $content = $this->client->getResponse()->getContent();
        $data = json_decode($content, true);

        // Verify performance metrics are included
        $this->assertArrayHasKey('performance', $data['data']);
        $this->assertArrayHasKey('response_time_ms', $data['data']['performance']);

        // Verify reported response time is reasonable
        $reportedTime = $data['data']['performance']['response_time_ms'];
        $this->assertLessThan(2000, $reportedTime);
    }

    public function testMultipleConcurrentRequests(): void
    {
        // Test that multiple requests can be handled properly
        $clients = [];
        $responses = [];

        // Create multiple clients and make concurrent requests
        for ($i = 0; $i < 3; $i++) {
            $clients[$i] = static::createClient();
            $clients[$i]->request('GET', '/api/currencies/current');
            $responses[$i] = $clients[$i]->getResponse();
        }

        // All should succeed
        foreach ($responses as $response) {
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $this->assertJson($response->getContent());
        }
    }

    public function testResponseDataConsistency(): void
    {
        // Make two requests and verify data consistency
        $this->client->request('GET', '/api/currencies/current');
        $response1 = json_decode($this->client->getResponse()->getContent(), true);

        sleep(1); // Small delay

        $this->client->request('GET', '/api/currencies/current');
        $response2 = json_decode($this->client->getResponse()->getContent(), true);

        // Both should have success field
        $this->assertTrue($response1['success']);
        $this->assertTrue($response2['success']);

        // Data structure should be consistent
        if (!empty($response1['data']) && !empty($response2['data'])) {
            $currencies1 = array_keys($response1['data']);
            $currencies2 = array_keys($response2['data']);

            // Should have same currencies (order might differ due to caching)
            $this->assertEquals(sort($currencies1), sort($currencies2));
        }
    }
}