<?php

namespace App\Models;

use App\Helpers\DateHelpers;
use Carbon\Carbon;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ps_year', 'first_name', 'last_name', 'email', 'phone', 'zip', 'shifts', 'group_zero', 'serial'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    public static function availablePsYears(): array
    {
        return static::query()
            ->select('ps_year')
            ->distinct()
            ->orderByDesc('ps_year')
            ->pluck('ps_year')
            ->map(static fn (int $psYear): int => $psYear)
            ->all();
    }

    protected $casts = [
        'shifts' => 'array',
        'group_zero' => 'boolean',
        'sent_at' => 'datetime',
        'scanned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function getCalendarYear(): int
    {
        return DateHelpers::calendarYearForPsYear($this->ps_year);
    }

    public function getYearAttribute(): int
    {
        return $this->getCalendarYear();
    }

    public function getSerialNumberAttribute(): ?string
    {
        return $this->serial;
    }

    public function getPriorityAttribute(): bool
    {
        return (bool) $this->group_zero;
    }

    public function getDisplayName(): ?string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);

        if (empty($parts)) {
            return null;
        }

        return implode(' ', $parts);
    }

    /**
     * Returns the Group Zero priority designation for this ticket.
     *
     * @return 'shift_start'|'shift_end'|'manual'|'none'
     */
    public function priorityDesignation(): string
    {
        if (! $this->group_zero) {
            return 'none';
        }

        /** @var array<int, array{job: string, start: string, end: string}> $shifts */
        $shifts = $this->shifts ?? [];

        /** @var array<string, array{shift_start_timestamps?: list<array{0: string, 1: string}>, shift_end_timestamps?: list<array{0: string, 1: string}>}> $groupZeroConfig */
        $groupZeroConfig = config('ps.group_zero', []);

        foreach ($shifts as $shift) {
            $start = Carbon::parse($shift['start']);
            $dayName = $start->format('l');
            $dayConfig = $groupZeroConfig[$dayName] ?? [];
            $startTime = $start->format('H:i');

            foreach ($dayConfig['shift_start_timestamps'] ?? [] as [$from, $to]) {
                if ($startTime >= $from && $startTime <= $to) {
                    return 'shift_start';
                }
            }
        }

        foreach ($shifts as $shift) {
            $end = Carbon::parse($shift['end']);
            $start = Carbon::parse($shift['start']);
            $dayName = $start->format('l');
            $dayConfig = $groupZeroConfig[$dayName] ?? [];
            $endTime = $end->format('H:i');

            foreach ($dayConfig['shift_end_timestamps'] ?? [] as [$from, $to]) {
                if ($endTime >= $from && $endTime <= $to) {
                    return 'shift_end';
                }
            }
        }

        return 'manual';
    }
}
