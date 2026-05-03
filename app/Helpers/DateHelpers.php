<?php

namespace App\Helpers;

use Carbon\Carbon;
use DateTimeInterface;

class DateHelpers
{
    public static function psYearForDate($date): int
    {
        $yearZero = config('ps.year_zero');
        $year = $date->year;

        return $year - $yearZero;
    }

    public static function calendarYearForPsYear(int|string $psYear): int
    {
        $yearZero = config('ps.year_zero');

        return $yearZero + (int) $psYear;
    }

    public static function psAnchorDateForCalendarYear(int|string $year): Carbon
    {
        $date = Carbon::create((int) $year, config('ps.anchor.month'), 1);
        $weekday_occurrences_observed = (int) ($date->dayName === config('ps.anchor.weekday'));
        while ($weekday_occurrences_observed < config('ps.anchor.occurrence_in_month')) {
            $date->addWeek();
            $weekday_occurrences_observed++;
        }
        while ($date->dayName !== config('ps.anchor.weekday')) {
            $date->subDay();
        }

        return $date;
    }

    public static function psDayForCalendarYear(int|string $year, int|string $day): Carbon
    {
        $anchor = self::psAnchorDateForCalendarYear($year);
        $target_weekday_number = (int) $day;

        while ($anchor->dayOfWeekIso !== $target_weekday_number) {
            config('ps.anchor.anchor_to') === 'end' ? $anchor->subDay() : $anchor->addDay();
        }

        return $anchor;
    }

    public static function isPlantSaleOpenOnDate(DateTimeInterface $date): bool
    {
        $dateToCheck = Carbon::instance($date);
        $plantSaleDay = self::psDayForCalendarYear($dateToCheck->year, $dateToCheck->dayOfWeekIso);
        $hours = config('ps.hours.'.$dateToCheck->format('l'));

        if (! is_array($hours) || ! isset($hours['open'], $hours['close'])) {
            return false;
        }

        return $dateToCheck->isSameDay($plantSaleDay);
    }
}
