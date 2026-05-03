<?php

use App\Helpers\DateHelpers;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

test('calendarYearForPsYear returns the correct calendar year for a given PS year', function () {
    // Arrange
    $yearZero = 1989; // This should match the 'year_zero' value in config/ps.php
    $psYear = 10;
    $expectedCalendarYear = $yearZero + $psYear;

    // Act
    $calendarYear = DateHelpers::calendarYearForPsYear($psYear);

    // Assert
    expect($calendarYear)->toBe($expectedCalendarYear);
});

test('psYearForDate returns the correct PS year for a given date', function () {
    // Arrange
    $yearZero = 1989; // This should match the 'year_zero' value in config/ps.php
    $date = Carbon::create($yearZero + 5, 1, 1); // January 1st of the year corresponding to PS year 5
    $expectedPsYear = 5;

    // Act
    $psYear = DateHelpers::psYearForDate($date);

    // Assert
    expect($psYear)->toBe($expectedPsYear);
});

test('psAnchorDateForCalendarYear returns the correct date for calendar year 2026', function () {
    // Arrange
    $date = Carbon::create(2026, 6, 1);
    $expectedAnchor = Carbon::create(2026, 5, 10);

    // Act
    $psAnchorDate = DateHelpers::psAnchorDateForCalendarYear(2026);

    // Assert
    expect($psAnchorDate->month)->toBe($expectedAnchor->month);
    expect($psAnchorDate->day)->toBe($expectedAnchor->day);
});

test('psDayForCalendarYear returns the correct date for calendar year 2026 and weekday 4 (Thursday)', function () {
    // Arrange
    $expectedDate = Carbon::create(2026, 5, 7);

    // Act
    $psDayDate = DateHelpers::psDayForCalendarYear(2026, 4);

    // Assert
    expect($psDayDate->month)->toBe($expectedDate->month);
    expect($psDayDate->day)->toBe($expectedDate->day);
});

test('isPlantSaleOpenOnDate returns true for the PS day when given a Carbon date', function () {
    $date = Carbon::create(2026, 5, 7, 23, 59, 59);

    expect(DateHelpers::isPlantSaleOpenOnDate($date))->toBeTrue();
});

test('isPlantSaleOpenOnDate returns true for the PS day when given a CarbonImmutable date', function () {
    $date = CarbonImmutable::create(2026, 5, 8, 0, 0, 0);

    expect(DateHelpers::isPlantSaleOpenOnDate($date))->toBeTrue();
});

test('isPlantSaleOpenOnDate returns true for the PS day when given a native DateTime date', function () {
    $date = new DateTime('2026-05-09 12:34:56');

    expect(DateHelpers::isPlantSaleOpenOnDate($date))->toBeTrue();
});

test('isPlantSaleOpenOnDate returns false when date is not the PS date for its weekday', function () {
    $date = Carbon::create(2026, 5, 14, 9, 0, 0);

    expect(DateHelpers::isPlantSaleOpenOnDate($date))->toBeFalse();
});

test('isPlantSaleOpenOnDate returns false when the weekday has no configured open and close hours', function () {
    $date = Carbon::create(2026, 5, 11, 10, 0, 0);

    expect(DateHelpers::isPlantSaleOpenOnDate($date))->toBeFalse();
});

test('ps weekday calculations use calendar year for 2025-12-31 when ISO year is 2026', function () {
    $date = Carbon::create(2025, 12, 31);

    expect($date->year)->toBe(2025);
    expect($date->isoWeekYear)->toBe(2026);

    $psDayDate = DateHelpers::psDayForCalendarYear($date->year, $date->dayOfWeekIso);

    expect($psDayDate->toDateString())->toBe('2025-05-07');
    expect($psDayDate->year)->toBe($date->year);
});

test('ps weekday calculations use calendar year for 2027-01-01 when ISO year is 2026', function () {
    $date = Carbon::create(2027, 1, 1);

    expect($date->year)->toBe(2027);
    expect($date->isoWeekYear)->toBe(2026);

    $psDayDate = DateHelpers::psDayForCalendarYear($date->year, $date->dayOfWeekIso);

    expect($psDayDate->toDateString())->toBe('2027-05-07');
    expect($psDayDate->year)->toBe($date->year);
});
