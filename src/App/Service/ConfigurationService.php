<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class ConfigurationService
{
    private array $config;
    private string $configPath;

    public function __construct(string $projectDir)
    {
        $this->configPath = $projectDir . '/config/currency.yaml';
        $this->loadConfiguration();
    }

    /**
     * Load configuration from YAML file
     */
    private function loadConfiguration(): void
    {
        if (!file_exists($this->configPath)) {
            throw new \RuntimeException("Configuration file not found: {$this->configPath}");
        }

        $this->config = Yaml::parseFile($this->configPath);

        if (!isset($this->config['currencies']) || !isset($this->config['nbp_api'])) {
            throw new \RuntimeException("Invalid configuration format in {$this->configPath}");
        }
    }

    /**
     * Get list of supported currencies
     * 
     * @return array Array of currency codes: ['EUR', 'USD', 'CZK', 'IDR', 'BRL']
     */
    public function getSupportedCurrencies(): array
    {
        return array_keys($this->config['currencies']);
    }

    /**
     * Get buy margin for specific currency in PLN
     * 
     * @param string $currency Currency code
     * @return float|null Buy margin in PLN or null if currency doesn't support buying
     */
    public function getBuyMargin(string $currency): ?float
    {
        if (!isset($this->config['currencies'][$currency])) {
            throw new \InvalidArgumentException("Currency {$currency} is not supported");
        }

        return $this->config['currencies'][$currency]['buy_margin'];
    }

    /**
     * Get sell margin for specific currency in PLN
     * 
     * @param string $currency Currency code  
     * @return float Sell margin in PLN
     */
    public function getSellMargin(string $currency): float
    {
        if (!isset($this->config['currencies'][$currency])) {
            throw new \InvalidArgumentException("Currency {$currency} is not supported");
        }

        return $this->config['currencies'][$currency]['sell_margin'];
    }

    /**
     * Get currency name
     * 
     * @param string $currency Currency code
     * @return string Full currency name
     */
    public function getCurrencyName(string $currency): string
    {
        if (!isset($this->config['currencies'][$currency])) {
            throw new \InvalidArgumentException("Currency {$currency} is not supported");
        }

        return $this->config['currencies'][$currency]['name'];
    }

    /**
     * Get NBP API URL for specific endpoint
     * 
     * @param string $endpoint Endpoint key from config
     * @return string Complete URL
     */
    public function getNbpApiUrl(string $endpoint): string
    {
        if (!isset($this->config['nbp_api'][$endpoint])) {
            throw new \InvalidArgumentException("NBP API endpoint {$endpoint} not found in configuration");
        }

        return $this->config['nbp_api'][$endpoint];
    }

    /**
     * Check if currency supports buying (has buy margin)
     * 
     * @param string $currency Currency code
     * @return bool True if currency can be bought
     */
    public function supportsBuying(string $currency): bool
    {
        return $this->getBuyMargin($currency) !== null;
    }
}