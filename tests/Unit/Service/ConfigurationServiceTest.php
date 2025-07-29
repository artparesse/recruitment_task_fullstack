<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\ConfigurationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ConfigurationServiceTest extends TestCase
{
    private ConfigurationService $service;
    private string $testConfigPath;

    protected function setUp(): void
    {
        // Reset static cache before each test
        $this->resetStaticCache();

        // Create temporary test config directory and file
        $this->testConfigPath = sys_get_temp_dir() . '/test_project_' . uniqid();
        mkdir($this->testConfigPath . '/config', 0777, true);
        $this->createTestConfigFile($this->testConfigPath . '/config/currency.yaml');

        $this->service = new ConfigurationService($this->testConfigPath);
    }

    protected function tearDown(): void
    {
        // Reset static cache after each test
        $this->resetStaticCache();

        // Clean up temporary directory
        if (is_dir($this->testConfigPath)) {
            if (file_exists($this->testConfigPath . '/config/currency.yaml')) {
                unlink($this->testConfigPath . '/config/currency.yaml');
            }
            rmdir($this->testConfigPath . '/config');
            rmdir($this->testConfigPath);
        }
    }

    private function resetStaticCache(): void
    {
        // Use reflection to reset static cache
        $reflection = new \ReflectionClass(ConfigurationService::class);
        $staticCacheProperty = $reflection->getProperty('staticCache');
        $staticCacheProperty->setAccessible(true);
        $staticCacheProperty->setValue(null);
    }

    private function createTestConfigFile(string $filePath): void
    {
        $config = [
            'nbp_api' => [
                'tables_today_url' => '/api/exchangerates/tables/A/today/?format=json',
                'tables_url' => '/api/exchangerates/tables/A/?format=json',
                'rates_url' => '/api/exchangerates/rates/A/{currency}?format=json',
                'historical_last_url' => '/api/exchangerates/tables/A/last/{count}?format=json',
                'historical_tables_url' => '/api/exchangerates/tables/A/{startDate}/{endDate}?format=json',
                'historical_rates_url' => '/api/exchangerates/rates/A/{currency}/last/{count}?format=json',
                'historical_rate_date_url' => '/api/exchangerates/rates/A/{currency}/{date}?format=json'
            ],
            'cache' => [
                'ttl' => [
                    'nbp_api' => 3600,
                    'currency_rates' => 3600,
                    'config' => 7200
                ],
                'pools' => [
                    'nbp_api' => 'nbp_api.cache',
                    'currency' => 'currency.cache',
                    'config' => 'config.cache'
                ]
            ],
            'currencies' => [
                'EUR' => [
                    'name' => 'Euro',
                    'buy_margin' => -0.15,
                    'sell_margin' => 0.11
                ],
                'USD' => [
                    'name' => 'US Dollar',
                    'buy_margin' => -0.15,
                    'sell_margin' => 0.11
                ],
                'CZK' => [
                    'name' => 'Czech Koruna',
                    'buy_margin' => null,
                    'sell_margin' => 0.2
                ],
                'IDR' => [
                    'name' => 'Indonesian Rupiah',
                    'buy_margin' => null,
                    'sell_margin' => 0.2
                ],
                'BRL' => [
                    'name' => 'Brazilian Real',
                    'buy_margin' => null,
                    'sell_margin' => 0.2
                ]
            ]
        ];

        file_put_contents($filePath, Yaml::dump($config));
    }

    public function testGetSupportedCurrenciesFromYaml(): void
    {
        $currencies = $this->service->getSupportedCurrencies();

        $expected = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
        $this->assertEquals($expected, $currencies);
    }

    public function testGetBuyMarginForTier1Currency(): void
    {
        $eurBuyMargin = $this->service->getBuyMargin('EUR');
        $usdBuyMargin = $this->service->getBuyMargin('USD');

        $this->assertEquals(-0.15, $eurBuyMargin);
        $this->assertEquals(-0.15, $usdBuyMargin);
    }

    public function testGetBuyMarginForTier2Currency(): void
    {
        $czkBuyMargin = $this->service->getBuyMargin('CZK');
        $idrBuyMargin = $this->service->getBuyMargin('IDR');
        $brlBuyMargin = $this->service->getBuyMargin('BRL');

        $this->assertNull($czkBuyMargin);
        $this->assertNull($idrBuyMargin);
        $this->assertNull($brlBuyMargin);
    }

    public function testGetSellMarginForTier1Currency(): void
    {
        $eurSellMargin = $this->service->getSellMargin('EUR');
        $usdSellMargin = $this->service->getSellMargin('USD');

        $this->assertEquals(0.11, $eurSellMargin);
        $this->assertEquals(0.11, $usdSellMargin);
    }

    public function testGetSellMarginForTier2Currency(): void
    {
        $czkSellMargin = $this->service->getSellMargin('CZK');
        $idrSellMargin = $this->service->getSellMargin('IDR');
        $brlSellMargin = $this->service->getSellMargin('BRL');

        $this->assertEquals(0.2, $czkSellMargin);
        $this->assertEquals(0.2, $idrSellMargin);
        $this->assertEquals(0.2, $brlSellMargin);
    }

    public function testGetMarginForUnsupportedCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency GBP is not supported');

        $this->service->getBuyMargin('GBP');
    }

    public function testGetSellMarginForUnsupportedCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency GBP is not supported');

        $this->service->getSellMargin('GBP');
    }

    public function testGetNbpApiUrl(): void
    {
        $todayUrl = $this->service->getNbpApiUrl('tables_today_url');
        $ratesUrl = $this->service->getNbpApiUrl('rates_url');

        $this->assertEquals('/api/exchangerates/tables/A/today/?format=json', $todayUrl);
        $this->assertEquals('/api/exchangerates/rates/A/{currency}?format=json', $ratesUrl);
    }

    public function testGetNbpApiUrlWithInvalidKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NBP API endpoint invalid_key not found in configuration');

        $this->service->getNbpApiUrl('invalid_key');
    }

    public function testSupportsBuyingForTier1Currency(): void
    {
        $this->assertTrue($this->service->supportsBuying('EUR'));
        $this->assertTrue($this->service->supportsBuying('USD'));
    }

    public function testSupportsBuyingForTier2Currency(): void
    {
        $this->assertFalse($this->service->supportsBuying('CZK'));
        $this->assertFalse($this->service->supportsBuying('IDR'));
        $this->assertFalse($this->service->supportsBuying('BRL'));
    }

    public function testYamlConfigurationLoadsOnce(): void
    {
        // First call - loads from file
        $currencies1 = $this->service->getSupportedCurrencies();

        // Modify file content
        file_put_contents($this->testConfigPath . '/config/currency.yaml', Yaml::dump(['currencies' => ['GBP' => ['name' => 'British Pound']]]));

        // Second call - should return cached result (not modified content)
        $currencies2 = $this->service->getSupportedCurrencies();

        // Should be the same (cached)
        $this->assertEquals($currencies1, $currencies2);
        $this->assertNotContains('GBP', $currencies2);
    }

    public function testConfigurationLazyLoading(): void
    {
        // Create service but don't call any methods yet
        $service = new ConfigurationService($this->testConfigPath);

        // Verify file exists before we start
        $this->assertFileExists($this->testConfigPath . '/config/currency.yaml');

        // First access should load the configuration
        $currencies = $service->getSupportedCurrencies();
        $this->assertIsArray($currencies);
        $this->assertNotEmpty($currencies);

        // Second access should use cached configuration
        $currencies2 = $service->getSupportedCurrencies();
        $this->assertEquals($currencies, $currencies2);
    }

    public function testGetCurrencyNameReturnsCorrectName(): void
    {
        $eurName = $this->service->getCurrencyName('EUR');
        $usdName = $this->service->getCurrencyName('USD');
        $czkName = $this->service->getCurrencyName('CZK');

        $this->assertEquals('Euro', $eurName);
        $this->assertEquals('US Dollar', $usdName);
        $this->assertEquals('Czech Koruna', $czkName);
    }

    public function testGetCurrencyNameForUnsupportedCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency GBP is not supported');

        $this->service->getCurrencyName('GBP');
    }

    public function testFileNotFoundHandling(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found');

        $service = new ConfigurationService('/nonexistent/path');
        // Trigger configuration loading by calling a method
        $service->getSupportedCurrencies();
    }

    public function testInvalidYamlHandling(): void
    {
        // Create invalid YAML file
        $invalidProjectPath = sys_get_temp_dir() . '/invalid_project_' . uniqid();
        mkdir($invalidProjectPath . '/config', 0777, true);
        $invalidYamlPath = $invalidProjectPath . '/config/currency.yaml';
        file_put_contents($invalidYamlPath, "invalid: yaml: content: [unclosed");

        try {
            $this->expectException(\Exception::class); // Any parsing exception

            $service = new ConfigurationService($invalidProjectPath);
            // Trigger configuration loading by calling a method  
            $service->getSupportedCurrencies();
        } finally {
            unlink($invalidYamlPath);
            rmdir($invalidProjectPath . '/config');
            rmdir($invalidProjectPath);
        }
    }

    public function testMultipleServiceInstancesUseSeparateCache(): void
    {
        // Reset static cache first
        $this->resetStaticCache();

        // Create second config directory with different content
        $secondProjectPath = sys_get_temp_dir() . '/test_project2_' . uniqid();
        mkdir($secondProjectPath . '/config', 0777, true);
        $secondConfigPath = $secondProjectPath . '/config/currency.yaml';

        $secondConfig = [
            'nbp_api' => [
                'tables_today_url' => '/api/exchangerates/tables/A/today/?format=json'
            ],
            'currencies' => [
                'GBP' => [
                    'name' => 'British Pound',
                    'buy_margin' => -0.10,
                    'sell_margin' => 0.15
                ]
            ]
        ];
        file_put_contents($secondConfigPath, Yaml::dump($secondConfig));

        try {
            // Force reset static cache between instances
            $this->resetStaticCache();
            $service2 = new ConfigurationService($secondProjectPath);

            $currencies1 = $this->service->getSupportedCurrencies();

            // Reset static cache again before second service
            $this->resetStaticCache();
            $currencies2 = $service2->getSupportedCurrencies();

            $this->assertNotEquals($currencies1, $currencies2);
            $this->assertContains('EUR', $currencies1);
            $this->assertContains('GBP', $currencies2);
            $this->assertNotContains('GBP', $currencies1);
            $this->assertNotContains('EUR', $currencies2);

        } finally {
            unlink($secondConfigPath);
            rmdir($secondProjectPath . '/config');
            rmdir($secondProjectPath);
        }
    }

    public function testGetAllConfiguration(): void
    {
        // Test method that might return all configuration
        $currencies = $this->service->getSupportedCurrencies();
        $this->assertIsArray($currencies);
        $this->assertCount(5, $currencies);

        // Test NBP API URLs
        $urls = ['tables_today_url', 'tables_url', 'rates_url', 'historical_last_url'];
        foreach ($urls as $urlKey) {
            $url = $this->service->getNbpApiUrl($urlKey);
            $this->assertIsString($url);
            $this->assertStringStartsWith('/api/', $url);
        }

        // Test currency support methods
        $this->assertTrue($this->service->supportsBuying('EUR'));
        $this->assertFalse($this->service->supportsBuying('CZK'));
    }
}