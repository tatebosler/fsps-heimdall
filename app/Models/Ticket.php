<?php

namespace App\Models;

use App\Helpers\DateHelpers;
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
}
