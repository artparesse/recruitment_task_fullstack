<?php

declare(strict_types=1);

namespace App\DTO;

class HistoricalRateDTO
{
    private \DateTime $date;
    private float $baseRate;
    private ?float $buyRate;
    private float $sellRate;

    public function __construct(
        \DateTime $date,
        float $baseRate,
        ?float $buyRate,
        float $sellRate
    ) {
        $this->date = $date;
        $this->baseRate = $baseRate;
        $this->buyRate = $buyRate;
        $this->sellRate = $sellRate;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function getBaseRate(): float
    {
        return $this->baseRate;
    }

    public function getBuyRate(): ?float
    {
        return $this->buyRate;
    }

    public function getSellRate(): float
    {
        return $this->sellRate;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'baseRate' => $this->baseRate,
            'buyRate' => $this->buyRate,
            'sellRate' => $this->sellRate
        ];
    }
}