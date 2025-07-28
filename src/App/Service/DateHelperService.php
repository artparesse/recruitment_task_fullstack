<?php

declare(strict_types=1);

namespace App\Service;

class DateHelperService
{
    /**
     * Get business days going back from a reference date
     */
    public function getBusinessDays(\DateTime $fromDate, int $days): array
    {
        $businessDays = [];
        $currentDate = clone $fromDate;

        while (count($businessDays) < $days) {
            if ($this->isBusinessDay($currentDate)) {
                $businessDays[] = clone $currentDate;
            }
            $currentDate->modify('-1 day');
        }

        return array_reverse($businessDays); // Return chronological order
    }

    /**
     * Check if a date is a business day (Monday-Friday)
     */
    public function isBusinessDay(\DateTime $date): bool
    {
        $dayOfWeek = (int) $date->format('N'); // 1 = Monday, 7 = Sunday
        return $dayOfWeek >= 1 && $dayOfWeek <= 5; // Monday to Friday
    }

    /**
     * Get the last business day before or on the given date
     */
    public function getLastBusinessDay(\DateTime $date): \DateTime
    {
        $lastBusinessDay = clone $date;

        while (!$this->isBusinessDay($lastBusinessDay)) {
            $lastBusinessDay->modify('-1 day');
        }

        return $lastBusinessDay;
    }

    /**
     * Calculate date range for N business days back from reference date
     */
    public function getBusinessDaysBackRange(\DateTime $referenceDate, int $daysCount): array
    {
        $businessDays = $this->getBusinessDays($referenceDate, $daysCount);

        if (empty($businessDays)) {
            throw new \RuntimeException("Could not calculate {$daysCount} business days range");
        }

        return [
            'startDate' => reset($businessDays), // First business day
            'endDate' => end($businessDays),     // Last business day (closest to reference)
            'businessDays' => $businessDays,
            'daysCount' => $daysCount
        ];
    }

    /**
     * Calculate date range for 14 business days back from reference date
     * @deprecated Use getBusinessDaysBackRange() instead
     */
    public function get14DaysBackRange(\DateTime $referenceDate): array
    {
        return $this->getBusinessDaysBackRange($referenceDate, 14);
    }

    /**
     * Create DateTime from string (Y-m-d format)
     */
    public function createFromString(string $dateString): \DateTime
    {
        $date = \DateTime::createFromFormat('Y-m-d', $dateString);

        if (!$date) {
            throw new \InvalidArgumentException("Invalid date format: {$dateString}. Expected Y-m-d format.");
        }

        return $date;
    }

    /**
     * Validate date is not in the future and not too old (max 1 year back)
     */
    public function validateDateRange(\DateTime $date): bool
    {
        $now = new \DateTime();
        $nbpDataStart = new \DateTime('2002-01-02'); // NBP API data availability start

        // Date cannot be in the future
        if ($date > $now) {
            return false;
        }

        // Date cannot be older than NBP API data availability start
        if ($date < $nbpDataStart) {
            return false;
        }

        return true;
    }
}