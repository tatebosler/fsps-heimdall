<?php

namespace App\Helpers;

use Carbon\Carbon;
use DateTimeInterface;

class DateHelpers
{
    public static function psYearForDate(DateTimeInterface $date): int
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

    public static function nextWristbandDistributionStart(DateTimeInterface $date, int $daysAhead = 7): ?Carbon
    {
        $reference = Carbon::instance($date);

        for ($offset = 0; $offset <= $daysAhead; $offset++) {
            $candidateDate = $reference->copy()->addDays($offset);

            if (! self::isPlantSaleOpenOnDate($candidateDate)) {
                continue;
            }

            $hours = config('ps.hours.'.$candidateDate->format('l'));

            if (! is_array($hours) || ! isset($hours['wristbands'])) {
                continue;
            }

            $wristbandStart = $candidateDate->copy()->setTimeFromTimeString($hours['wristbands']);

            if ($wristbandStart->greaterThan($reference)) {
                return $wristbandStart;
            }
        }

        return null;
    }

    public static function saleHasJustClosed(DateTimeInterface $date, int $minutesAfterClose = 15): bool
    {
        $dateToCheck = Carbon::instance($date);

        if (! self::isPlantSaleOpenOnDate($dateToCheck)) {
            return false;
        }

        $hours = config('ps.hours.'.$dateToCheck->format('l'));

        if (! is_array($hours) || ! isset($hours['close'])) {
            return false;
        }

        $saleClose = self::psDayForCalendarYear($dateToCheck->year, $dateToCheck->dayOfWeekIso)
            ->setTimeFromTimeString($hours['close']);

        return $dateToCheck->betweenIncluded($saleClose, $saleClose->copy()->addMinutes($minutesAfterClose));
    }

    public static function dayStringToNumber(string $day): int
    {
        return match (strtolower($day)) {
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
            default => throw new \InvalidArgumentException("Invalid day string: {$day}"),
        };
    }

    public static function dayNumberToString(int $day): string
    {
        return match ($day) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => throw new \InvalidArgumentException("Invalid day number: {$day}"),
        };
    }
}
