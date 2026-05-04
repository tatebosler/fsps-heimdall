<?php

namespace App\Helpers;

use App\Models\Channel;
use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EntryTimeEstimator
{
    public static function estimateEntryTimes(): void
    {
        $psYear = DateHelpers::psYearForDate(now());
        $weekday = date('N');
        $channels = Channel::whereLike('id', "{$psYear}{$weekday}__")->orderBy('id', 'asc')->get();
        [$pendingChannels, $clearedChannels] = $channels->partition(fn ($channel) => $channel->cleared_at === null);

        $clearRate = 60 * (config('ps.historical_clear_rates')[date('l')] ?? 7.5);
        if ($clearedChannels->count() >= 6) {
            $clearRateSample = $clearedChannels->take(-6)->pluck('cleared_at');
            $lastCleared = null;
            $clearRateSampleData = [];
            foreach ($clearRateSample as $clearedAt) {
                if (!$lastCleared) {
                    $lastCleared = $clearedAt;
                    continue;
                }
                $clearRateSampleData[] = $clearedAt->diffInSeconds($lastCleared);
                $lastCleared = $clearedAt;
            }
            $clearRate = round(collect($clearRateSampleData)->avg());
        }

        $lastCleared = $clearedChannels->last()?->cleared_at;
        if (! $lastCleared) {
            $lastCleared = DateHelpers::psDayForCalendarYear(date('Y'), date('N'))->setTimeFromTimeString(config('ps.hours.'.date('l').'.open'));
        }
        $firstGroupOfToday = (int) !array_key_exists(date('l'), config('ps.group_zero'));
        foreach ($pendingChannels as $channel) {
            Log::info("Estimating entry time for channel {$channel->id}");
            if ($channel->id % 100 === $firstGroupOfToday) {
                $estimatedEntryAt = $lastCleared->copy();
            } else {
                $estimatedEntryAt = $lastCleared->copy()->addSeconds($clearRate);
            }
            if (! $channel->original_estimated_entry_at) {
                $channel->original_estimated_entry_at = $estimatedEntryAt;
            }
            $channel->estimated_entry_at = $estimatedEntryAt;
            $channel->save();
            $lastCleared = $estimatedEntryAt;

            Estimate::create([
                'channel_id' => $channel->id,
                'estimated_entry_at' => $estimatedEntryAt,
            ]);
        }

        if ($pendingChannels->count()) {
            $currentArrivals = $pendingChannels->last()->refresh();
            Cache::set('entry-newt-minutes', $currentArrivals->distribution_started_at->diffInMinutes($currentArrivals->estimated_entry_at));
        }
    }

    public static function getEstimate(int $group): Carbon
    {
        $psYear = DateHelpers::psYearForDate(now());
        $weekday = date('N');
        $clearedChannels = Channel::whereLike('id', "{$psYear}{$weekday}__")->whereNotNull('cleared_at')->orderBy('id', 'asc')->get();

        $clearRate = 60 * (config('ps.historical_clear_rates')[date('l')] ?? 7.5);
        if ($clearedChannels->count() >= 6) {
            $clearRateSample = $clearedChannels->take(-6)->pluck('cleared_at');
            $lastCleared = null;
            $clearRateSampleData = [];
            foreach ($clearRateSample as $clearedAt) {
                if (!$lastCleared) {
                    $lastCleared = $clearedAt;
                    continue;
                }
                $clearRateSampleData[] = $clearedAt->diffInSeconds($lastCleared);
                $lastCleared = $clearedAt;
            }
            $clearRate = round(collect($clearRateSampleData)->avg());
        }

        $lastClearedChannel = $clearedChannels->last();
        if ($lastClearedChannel) {
            $lastCleared = $lastClearedChannel->cleared_at;
            $lastClearedGroup = $lastClearedChannel->id % 100;
        } else {
            $lastCleared = DateHelpers::psDayForCalendarYear(date('Y'), date('N'))->setTimeFromTimeString(config('ps.hours.'.date('l').'.open'));
            $lastClearedGroup = (int) !array_key_exists(date('l'), config('ps.group_zero'));
        }

        return $lastCleared->copy()->addSeconds($clearRate * ($group - $lastClearedGroup));
    }

    public static function getHypotheticalEstimates(int $day): array
    {
        $weekday = DateHelpers::dayNumberToString($day);
        $psYear = DateHelpers::psYearForDate(now());
        [$pendingChannels, $clearedChannels] = Channel::whereLike('id', "{$psYear}{$day}__")->orderBy('id', 'asc')->get()->partition(fn ($channel) => $channel->cleared_at === null);
        $historicalMax = config('ps.historical_group_counts')[$weekday] ?? 0;
        $lastClearedChannel = $clearedChannels->last();

        $clearRate = 60 * (config('ps.historical_clear_rates')[$weekday] ?? 7.5);
        if ($clearedChannels->count() >= 6) {
            $clearRateSample = $clearedChannels->take(-6)->pluck('cleared_at');
            $lastCleared = null;
            $clearRateSampleData = [];
            foreach ($clearRateSample as $clearedAt) {
                if (!$lastCleared) {
                    $lastCleared = $clearedAt;
                    continue;
                }
                $clearRateSampleData[] = $clearedAt->diffInSeconds($lastCleared);
                $lastCleared = $clearedAt;
            }
            $clearRate = round(collect($clearRateSampleData)->avg());
        }

        $lastClearedChannel = $clearedChannels->last();
        $lastPendingChannel = $pendingChannels->last();
        if ($clearedChannels->count()) {
            $lastCleared = $lastClearedChannel->cleared_at;
            $lastClearedGroup = $lastClearedChannel->id % 100;
            $startGroup = $lastClearedGroup + 1;
        } else {
            $lastCleared = DateHelpers::psDayForCalendarYear(date('Y'), $day)->setTimeFromTimeString(config('ps.hours.'.$weekday.'.open'));
            $lastClearedGroup = (int) !array_key_exists($weekday, config('ps.group_zero'));
            if ($lastPendingChannel) {
                $startGroup = ($lastPendingChannel->id % 100) + 1;
            } else {
                $startGroup = $lastClearedGroup;
            }
        }

        $returnTimes = [];

        if ($startGroup === (int) !array_key_exists($weekday, config('ps.group_zero'))) {
            $returnTimes[] = [
                'group' => $startGroup,
                'estimated_entry_at' => $lastCleared->copy(),
            ];
        }

        while ($startGroup % 5 !== 0 or $startGroup === 0) {
            $startGroup++;
        }
        for ($group = $startGroup; $group <= $historicalMax; $group += 5) {
            $returnTimes[] = [
                'group' => $group,
                'estimated_entry_at' => $lastCleared->copy()->addSeconds($clearRate * ($group - $lastClearedGroup)),
            ];
        }
        return $returnTimes;
    }
}
