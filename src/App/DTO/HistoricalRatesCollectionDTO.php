<?php

declare(strict_types=1);

namespace App\DTO;

class HistoricalRatesCollectionDTO
{
    private string $currency;
    private \DateTime $fromDate;
    private \DateTime $toDate;
    private array $rates; // Array of HistoricalRateDTO

    public function __construct(
        string $currency,
        \DateTime $fromDate,
        \DateTime $toDate,
        array $rates = []
    ) {
        $this->currency = $currency;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->rates = $rates;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getFromDate(): \DateTime
    {
        return $this->fromDate;
    }

    public function getToDate(): \DateTime
    {
        return $this->toDate;
    }

    public function getRates(): array
    {
        return $this->rates;
    }

    public function addRate(HistoricalRateDTO $rate): void
    {
        $this->rates[] = $rate;
    }

    public function getCount(): int
    {
        return count($this->rates);
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'fromDate' => $this->fromDate->format('Y-m-d'),
            'toDate' => $this->toDate->format('Y-m-d'),
            'count' => $this->getCount(),
            'rates' => array_map(function (HistoricalRateDTO $rate) {
                return $rate->toArray();
            }, $this->rates)
        ];
    }
}