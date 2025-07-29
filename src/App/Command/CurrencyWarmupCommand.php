<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CurrencyRateService;
use App\Service\ConfigurationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

class CurrencyWarmupCommand extends Command
{
    protected static $defaultName = 'currency:warmup';
    protected static $defaultDescription = 'Warm up currency cache by pre-loading rates from NBP API';

    private CurrencyRateService $currencyService;
    private ConfigurationService $config;
    private CacheInterface $nbpApiCache;

    public function __construct(
        CurrencyRateService $currencyService,
        ConfigurationService $config,
        CacheInterface $nbpApiCache
    ) {
        parent::__construct();
        $this->currencyService = $currencyService;
        $this->config = $config;
        $this->nbpApiCache = $nbpApiCache;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force cache refresh by clearing existing cache first'
            )
            ->addOption(
                'pool',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Specific cache pool to warm up (nbp_api or all)',
                'all'
            )
            ->setHelp(
                'This command pre-loads the currency cache with data from NBP API.' . PHP_EOL .
                'This improves response times for the first requests after cache expiry.' . PHP_EOL . PHP_EOL .
                'Examples:' . PHP_EOL .
                '  currency:warmup                    # Warm up all caches' . PHP_EOL .
                '  currency:warmup --force            # Clear and warm up all caches' . PHP_EOL .
                '  currency:warmup --pool=nbp_api     # Warm up only NBP API cache'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $pool = $input->getOption('pool');

        $io->title('Currency Cache Warmup - Simplified Architecture');

        if ($force) {
            $io->section('Clearing existing cache...');
            $this->clearCache($pool, $io);
        }

        $io->section('Warming up cache...');

        $startTime = microtime(true);
        $stats = [
            'nbp_api_calls' => 0,
            'currency_calculations' => 0,
            'errors' => []
        ];

        try {
            // Warm up configuration cache
            if ($pool === 'all' || $pool === 'config') {
                $io->text('Loading configuration...');
                $supportedCurrencies = $this->config->getSupportedCurrencies();
                $io->text(sprintf('✓ Configuration loaded: %d currencies', count($supportedCurrencies)));
            }

            // Warm up NBP API cache (simplified - only one level)
            if ($pool === 'all' || $pool === 'nbp_api') {
                $io->text('Fetching current rates from NBP API...');

                try {
                    $rates = $this->currencyService->getCurrentRates();
                    $stats['currency_calculations'] = count($rates);
                    $stats['nbp_api_calls'] = 1; // One call for all currencies via tables endpoint

                    $io->text(sprintf('✓ Currency rates cached: %d currencies', count($rates)));

                    // Display fetched rates
                    if ($output->isVerbose()) {
                        $io->table(
                            ['Currency', 'Base Rate', 'Buy Rate', 'Sell Rate'],
                            array_map(function ($rate) {
                                return [
                                    $rate['currency'],
                                    number_format($rate['baseRate'], 4),
                                    $rate['buyRate'] ? number_format($rate['buyRate'], 4) : 'N/A',
                                    number_format($rate['sellRate'], 4)
                                ];
                            }, $rates)
                        );
                    }

                } catch (\Exception $e) {
                    $stats['errors'][] = 'Currency rates: ' . $e->getMessage();
                    $io->error('Failed to fetch currency rates: ' . $e->getMessage());
                }

                // Warm up available currencies cache
                try {
                    $availableCurrencies = $this->currencyService->getAvailableCurrencies();
                    $io->text(sprintf('✓ Available currencies cached: %d currencies', count($availableCurrencies)));
                } catch (\Exception $e) {
                    $stats['errors'][] = 'Available currencies: ' . $e->getMessage();
                    $io->warning('Failed to cache available currencies: ' . $e->getMessage());
                }
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Summary
            $io->section('Warmup Summary');
            $io->definitionList(
                ['Cache Pool' => $pool],
                ['Duration' => $duration . ' ms'],
                ['NBP API Calls' => $stats['nbp_api_calls']],
                ['Currency Calculations' => $stats['currency_calculations']],
                ['Errors' => count($stats['errors'])],
                ['Architecture' => 'Simplified (Repository-level cache only)']
            );

            if (!empty($stats['errors'])) {
                $io->warning('Warmup completed with errors:');
                foreach ($stats['errors'] as $error) {
                    $io->text('• ' . $error);
                }
                return 1;
            }

            $io->success('Cache warmup completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $io->error('Cache warmup failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function clearCache(string $pool, SymfonyStyle $io): void
    {
        try {
            if ($pool === 'all' || $pool === 'nbp_api') {
                // Clear specific cache keys rather than entire pool
                $today = date('Y-m-d');
                $this->nbpApiCache->delete("nbp_table_today_{$today}");
                $this->nbpApiCache->delete("nbp_table_a_{$today}");
                $io->text('✓ NBP API cache cleared');
            }

        } catch (\Exception $e) {
            $io->warning('Failed to clear some cache pools: ' . $e->getMessage());
        }
    }
}