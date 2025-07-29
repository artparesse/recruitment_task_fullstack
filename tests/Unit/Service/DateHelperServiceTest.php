<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\DateHelperService;
use PHPUnit\Framework\TestCase;
use Tests\Traits\CurrencyTestTrait;

class DateHelperServiceTest extends TestCase
{
    use CurrencyTestTrait;

    private DateHelperService $service;

    protected function setUp(): void
    {
        $this->service = new DateHelperService();
    }

    public function testIsBusinessDayReturnsTrueForMonday(): void
    {
        $monday = $this->createTestDate('2024-01-15'); // Monday
        $this->assertTrue($this->service->isBusinessDay($monday));
        $this->assertBusinessDay($monday);
    }

    public function testIsBusinessDayReturnsTrueForFriday(): void
    {
        $friday = $this->createTestDate('2024-01-19'); // Friday
        $this->assertTrue($this->service->isBusinessDay($friday));
        $this->assertBusinessDay($friday);
    }

    public function testIsBusinessDayReturnsFalseForSaturday(): void
    {
        $saturday = $this->createTestDate('2024-01-20'); // Saturday
        $this->assertFalse($this->service->isBusinessDay($saturday));
        $this->assertWeekend($saturday);
    }

    public function testIsBusinessDayReturnsFalseForSunday(): void
    {
        $sunday = $this->createTestDate('2024-01-21'); // Sunday
        $this->assertFalse($this->service->isBusinessDay($sunday));
        $this->assertWeekend($sunday);
    }

    public function testGetBusinessDaysExcludesWeekends(): void
    {
        // Start from Friday (2024-01-19), get 5 business days back
        $fromDate = $this->createTestDate('2024-01-19'); // Friday
        $businessDays = $this->service->getBusinessDays($fromDate, 5);

        $this->assertCount(5, $businessDays);

        // Should include: Fri 19, Thu 18, Wed 17, Tue 16, Mon 15
        // Should exclude weekends (Sat 13, Sun 14)
        foreach ($businessDays as $date) {
            $this->assertBusinessDay($date);
        }

        // Verify chronological order (oldest first)
        $this->assertEquals('2024-01-15', $businessDays[0]->format('Y-m-d')); // Monday
        $this->assertEquals('2024-01-16', $businessDays[1]->format('Y-m-d')); // Tuesday
        $this->assertEquals('2024-01-17', $businessDays[2]->format('Y-m-d')); // Wednesday
        $this->assertEquals('2024-01-18', $businessDays[3]->format('Y-m-d')); // Thursday
        $this->assertEquals('2024-01-19', $businessDays[4]->format('Y-m-d')); // Friday
    }

    public function testGetBusinessDaysReturnsCorrectCount(): void
    {
        $fromDate = $this->createTestDate('2024-01-15'); // Monday

        // Test different counts
        $this->assertCount(1, $this->service->getBusinessDays($fromDate, 1));
        $this->assertCount(3, $this->service->getBusinessDays($fromDate, 3));
        $this->assertCount(10, $this->service->getBusinessDays($fromDate, 10));
    }

    public function testGetLastBusinessDayFromMonday(): void
    {
        $monday = $this->createTestDate('2024-01-15'); // Monday
        $lastBusinessDay = $this->service->getLastBusinessDay($monday);

        $this->assertEquals('2024-01-15', $lastBusinessDay->format('Y-m-d')); // Same day (Monday)
        $this->assertBusinessDay($lastBusinessDay);
    }

    public function testGetLastBusinessDayFromSaturday(): void
    {
        $saturday = $this->createTestDate('2024-01-20'); // Saturday
        $lastBusinessDay = $this->service->getLastBusinessDay($saturday);

        $this->assertEquals('2024-01-19', $lastBusinessDay->format('Y-m-d')); // Previous Friday
        $this->assertBusinessDay($lastBusinessDay);
    }

    public function testGetLastBusinessDayFromSunday(): void
    {
        $sunday = $this->createTestDate('2024-01-21'); // Sunday
        $lastBusinessDay = $this->service->getLastBusinessDay($sunday);

        $this->assertEquals('2024-01-19', $lastBusinessDay->format('Y-m-d')); // Previous Friday
        $this->assertBusinessDay($lastBusinessDay);
    }

    public function testGetBusinessDaysBackRange(): void
    {
        $referenceDate = $this->createTestDate('2024-01-19'); // Friday
        $result = $this->service->getBusinessDaysBackRange($referenceDate, 5);

        $this->assertArrayHasKey('startDate', $result);
        $this->assertArrayHasKey('endDate', $result);
        $this->assertArrayHasKey('businessDays', $result);
        $this->assertArrayHasKey('daysCount', $result);

        $this->assertEquals(5, $result['daysCount']);
        $this->assertCount(5, $result['businessDays']);

        // End date should be the reference date (or last business day before it)
        $this->assertEquals('2024-01-19', $result['endDate']->format('Y-m-d'));

        // Start date should be first business day in the range
        $this->assertEquals('2024-01-15', $result['startDate']->format('Y-m-d'));

        // All days should be business days
        foreach ($result['businessDays'] as $date) {
            $this->assertBusinessDay($date);
        }
    }

    public function testCreateFromStringWithValidDate(): void
    {
        $dateString = '2024-01-15';
        $date = $this->service->createFromString($dateString);

        $this->assertInstanceOf(\DateTime::class, $date);
        $this->assertEquals($dateString, $date->format('Y-m-d'));
    }

    public function testCreateFromStringWithInvalidDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: invalid-date. Expected Y-m-d format.');

        $this->service->createFromString('invalid-date');
    }

    public function testCreateFromStringWithInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: 15-01-2024. Expected Y-m-d format.');

        $this->service->createFromString('15-01-2024'); // Wrong format (should be Y-m-d)
    }

    public function testValidateDateRangeWithValidDate(): void
    {
        $validDate = $this->createTestDate('2024-01-15');
        $this->assertTrue($this->service->validateDateRange($validDate));
    }

    public function testValidateDateRangeWithFutureDate(): void
    {
        $futureDate = new \DateTime('+1 day');
        $this->assertFalse($this->service->validateDateRange($futureDate));
    }

    public function testValidateDateRangeWithVeryOldDate(): void
    {
        $veryOldDate = $this->createTestDate('2001-01-01'); // Before NBP API data start
        $this->assertFalse($this->service->validateDateRange($veryOldDate));
    }

    public function testValidateDateRangeWithNBPStartDate(): void
    {
        $nbpStartDate = $this->createTestDate('2002-01-02'); // NBP API data start
        $this->assertTrue($this->service->validateDateRange($nbpStartDate));
    }

    public function testValidateDateRangeWithToday(): void
    {
        $today = new \DateTime();
        $this->assertTrue($this->service->validateDateRange($today));
    }

    public function testGet14DaysBackRangeIsDeprecated(): void
    {
        // Test the deprecated method still works but uses the new method internally
        $referenceDate = $this->createTestDate('2024-01-19');
        $result = $this->service->get14DaysBackRange($referenceDate);

        $this->assertArrayHasKey('startDate', $result);
        $this->assertArrayHasKey('endDate', $result);
        $this->assertArrayHasKey('businessDays', $result);
        $this->assertArrayHasKey('daysCount', $result);

        $this->assertEquals(14, $result['daysCount']);
        $this->assertCount(14, $result['businessDays']);
    }

    public function testGetBusinessDaysBackRangeThrowsExceptionForZeroDays(): void
    {
        $referenceDate = $this->createTestDate('2024-01-15');

        // This should work (edge case but valid)
        $result = $this->service->getBusinessDaysBackRange($referenceDate, 1);
        $this->assertCount(1, $result['businessDays']);
    }

    public function testGetBusinessDaysSpansMultipleWeeks(): void
    {
        $fromDate = $this->createTestDate('2024-01-19'); // Friday
        $businessDays = $this->service->getBusinessDays($fromDate, 10);

        $this->assertCount(10, $businessDays);

        // Should span multiple weeks and exclude weekends
        foreach ($businessDays as $date) {
            $this->assertBusinessDay($date);
        }

        // Should go back to: Fri 19, Thu 18, Wed 17, Tue 16, Mon 15, 
        // Fri 12, Thu 11, Wed 10, Tue 9, Mon 8
        $this->assertEquals('2024-01-08', $businessDays[0]->format('Y-m-d')); // Oldest (Monday)
        $this->assertEquals('2024-01-19', $businessDays[9]->format('Y-m-d')); // Newest (Friday)
    }

    public function testBusinessDayCalculationConsistency(): void
    {
        // Test that getBusinessDays and getBusinessDaysBackRange are consistent
        $referenceDate = $this->createTestDate('2024-01-19'); // Friday
        $daysCount = 7;

        $directBusinessDays = $this->service->getBusinessDays($referenceDate, $daysCount);
        $rangeResult = $this->service->getBusinessDaysBackRange($referenceDate, $daysCount);

        $this->assertCount($daysCount, $directBusinessDays);
        $this->assertCount($daysCount, $rangeResult['businessDays']);

        // Both methods should return the same business days
        for ($i = 0; $i < $daysCount; $i++) {
            $this->assertEquals(
                $directBusinessDays[$i]->format('Y-m-d'),
                $rangeResult['businessDays'][$i]->format('Y-m-d')
            );
        }
    }
}