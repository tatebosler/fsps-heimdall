<?php

namespace App\Models;

use App\Helpers\DateHelpers;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['id'])]
class Channel extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelFactory> */
    use HasFactory;

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isSpecial(): bool
    {
        $third_from_end_digit = floor($this->id / 100) % 10;
        return $third_from_end_digit === 9;
    }

    public function getPsYear(): int
    {
        return floor($this->id / 1000);
    }

    public function getDescription(): string
    {
        if ($this->isSpecial()) {
            $suffix = config('ps.special_channel_suffixes.'.str_pad($this->id % 100, 2, '0', STR_PAD_LEFT));
            $year = DateHelpers::calendarYearForPsYear($this->getPsYear());
            return "{$suffix} ({$year})";
        } else {
            $date = DateHelpers::psDayForCalendarYear(DateHelpers::calendarYearForPsYear($this->getPsYear()), floor($this->id / 100) % 10);
            $group = $this->id % 100;
            return "{$date->dayName} Group {$group} ({$date->year})";
        }
    }
}
