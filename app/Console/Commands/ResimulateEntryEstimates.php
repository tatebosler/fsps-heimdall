<?php

namespace App\Console\Commands;

use App\Helpers\DateHelpers;
use App\Helpers\EntryTimeEstimator;
use App\Models\Channel;
use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

#[Signature('app:resimulate-entry-estimates {date? : Target date in Y-m-d format}')]
#[Description('Replay distribution/clear events for a sale day and rebuild entry estimates')]
class ResimulateEntryEstimates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $targetDate = $this->resolveTargetDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $psYear = DateHelpers::psYearForDate($targetDate);
        $weekday = $targetDate->dayOfWeekIso;

        $dayChannelIds = Channel::where(function ($query) use ($psYear, $weekday) {
            $query
                ->whereLike('id', "{$psYear}{$weekday}__")
                ->orWhereLike('id', "{$psYear}9{$weekday}_");
        })->pluck('id')->map(fn ($id) => (int) $id)->all();

        $channelsWithEvents = Channel::where(function ($query) use ($psYear, $weekday) {
            $query
                ->whereLike('id', "{$psYear}{$weekday}__")
                ->orWhereLike('id', "{$psYear}9{$weekday}_");
        })->where(function ($query) {
            $query->whereNotNull('distribution_started_at')
                ->orWhereNotNull('cleared_at');
        })->get();

        $events = [];
        foreach ($channelsWithEvents as $channel) {
            if ($channel->distribution_started_at !== null) {
                $events[] = [
                    'channel_id' => (int) $channel->id,
                    'type' => 'distribution_started_at',
                    'at' => $channel->distribution_started_at->copy(),
                ];
            }

            if ($channel->cleared_at !== null) {
                $events[] = [
                    'channel_id' => (int) $channel->id,
                    'type' => 'cleared_at',
                    'at' => $channel->cleared_at->copy(),
                ];
            }
        }

        usort($events, function (array $left, array $right): int {
            $timeComparison = $left['at']->getTimestamp() <=> $right['at']->getTimestamp();
            if ($timeComparison !== 0) {
                return $timeComparison;
            }

            $leftPriority = $left['type'] === 'distribution_started_at' ? 0 : 1;
            $rightPriority = $right['type'] === 'distribution_started_at' ? 0 : 1;
            $priorityComparison = $leftPriority <=> $rightPriority;
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return $left['channel_id'] <=> $right['channel_id'];
        });

        $eventChannelIds = $channelsWithEvents->pluck('id')->map(fn ($id) => (int) $id)->all();

        try {
            DB::transaction(function () use ($dayChannelIds, $eventChannelIds, $events): void {
                if ($dayChannelIds !== []) {
                    Channel::whereIn('id', $dayChannelIds)->update([
                        'estimated_entry_at' => null,
                        'original_estimated_entry_at' => null,
                    ]);

                    Estimate::whereIn('channel_id', $dayChannelIds)->delete();
                }

                if ($eventChannelIds !== []) {
                    Channel::whereIn('id', $eventChannelIds)->update([
                        'distribution_started_at' => null,
                        'cleared_at' => null,
                    ]);
                }

                foreach ($events as $event) {
                    Carbon::setTestNow($event['at']);

                    $channel = Channel::firstOrCreate(['id' => $event['channel_id']]);
                    $channel->{$event['type']} = $event['at'];
                    $channel->save();

                    EntryTimeEstimator::estimateEntryTimes();
                }
            });
        } catch (Throwable $exception) {
            Carbon::setTestNow();
            $this->error('Re-simulation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        Carbon::setTestNow();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Target date', $targetDate->toDateString()],
                ['Channels with events', (string) count($eventChannelIds)],
                ['Replay events', (string) count($events)],
            ]
        );

        $this->info('Re-simulation complete.');

        return self::SUCCESS;
    }

    private function resolveTargetDate(): Carbon
    {
        $argument = $this->argument('date');
        if ($argument === null || $argument === '') {
            return now()->startOfDay();
        }

        try {
            $targetDate = Carbon::createFromFormat('Y-m-d', (string) $argument, config('app.timezone'));
        } catch (Throwable) {
            throw new InvalidArgumentException('Invalid date. Use Y-m-d format, for example 2026-05-07.');
        }

        if (! $targetDate || $targetDate->format('Y-m-d') !== $argument) {
            throw new InvalidArgumentException('Invalid date. Use Y-m-d format, for example 2026-05-07.');
        }

        return $targetDate->startOfDay();
    }
}
