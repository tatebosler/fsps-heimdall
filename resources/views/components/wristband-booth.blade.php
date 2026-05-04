<?php

use App\Helpers\DateHelpers;
use App\Helpers\EntryTimeEstimator;
use App\Models\Channel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Wristband Booth Dashboard')] class extends Component
{
    public int $nextGroup = 0;
    public ?Carbon $lastDistributedAt = null;
    public ?Carbon $estimatedEntryTime = null;

    public function mount()
    {
        $this->updateStatus();
    }

    public function updateStatus()
    {
        $psYear = DateHelpers::psYearForDate(now());
        $weekday = date('N');
        $lastDistributed = Channel::whereLike('id', "{$psYear}{$weekday}__")->whereNotNull('distribution_started_at')->latest('distribution_started_at')->orderBy('id', 'desc')->first();
        $lastCleared = Channel::whereLike('id', "{$psYear}{$weekday}__")->orWhereLike('id', "{$psYear}9{$weekday}0")->whereNotNull('cleared_at')->latest('cleared_at')->orderBy('id', 'desc')->first();

        if ($lastCleared and $lastCleared->isSpecial()) {
            $this->nextGroup = -1;
            Cache::forget('entry-distributing');
            Cache::forget('entry-newt-minutes');
        } else if ($lastDistributed) {
            $this->nextGroup = ($lastDistributed->id % 100) + 1;
            $this->lastDistributedAt = $lastDistributed->distribution_started_at;
            Cache::set('entry-distributing', $this->nextGroup - 1);
        } else {
            $this->nextGroup = 1;
            Cache::forget('entry-distributing');
        }
    }

    public function distribute()
    {
        $psYear = DateHelpers::psYearForDate(now());
        $weekday = date('N');
        $channelId = sprintf('%s%s%02d', $psYear, $weekday, $this->nextGroup);
        $channel = Channel::firstOrCreate(['id' => $channelId]);

        $now = now();

        if ($channel->distribution_started_at === null) {
            $channel->update(['distribution_started_at' => $now]);
        }

        $todayConfig = config('ps.group_zero');
        if (array_key_exists(date('l'), $todayConfig) and $this->nextGroup === 1) {
            $groupZeroChannelId = sprintf('%s%s%02d', $psYear, $weekday, 0);
            $groupZeroChannel = Channel::firstOrCreate(['id' => $groupZeroChannelId]);

            if ($groupZeroChannel->distribution_started_at === null) {
                $groupZeroChannel->update(['distribution_started_at' => $now]);
            }
        }

        EntryTimeEstimator::estimateEntryTimes();

        Cache::set('entry-distributing', $this->nextGroup ?? 1);
        if (! $this->nextGroup) {
            $this->nextGroup = 2;
        } else {
            $this->nextGroup++;
        }
        $this->lastDistributedAt = $now;
        $channel->refresh();
        $this->estimatedEntryTime = $channel->estimated_entry_at;
        $this->modal('distribution-started')->show();
    }
};
?>

<div class="p-4 sm:px-8 space-y-4">
    <h1>Wristband Booth Dashboard</h1>

    <livewire:entry-status hide-actions />

    <div>
        @if ($nextGroup === -1)
            <div class="dark:bg-{{ config('ps.colors.' . date('l')) }}-950 bg-{{ config('ps.colors.' . date('l')) }}-50 px-3 py-6 rounded-xl w-full block text-4xl font-bold text-center">
                You're off wristbands!
            </div>
        @else
            <button class="dark:bg-{{ config('ps.colors.' . date('l')) }}-800 hover:dark:bg-{{ config('ps.colors.' . date('l')) }}-700 active:dark:bg-{{ config('ps.colors.' . date('l')) }}-600 bg-{{ config('ps.colors.' . date('l')) }}-300 hover:bg-{{ config('ps.colors.' . date('l')) }}-200 active:bg-{{ config('ps.colors.' . date('l')) }}-100 px-3 py-6 rounded-xl w-full block text-4xl font-bold" wire:click="distribute">
                Start distributing group {{ $nextGroup }}
            </button>
            @if ($nextGroup > 1)
                <div class="mx-4 py-2 px-3 bg-gray-200 dark:bg-gray-800 rounded-b text-center max-sm:text-xs" wire:poll>
                    <p>Group {{ $nextGroup - 1 }} distribution started {{ $lastDistributedAt->diffForHumans() }}</p>
                </div>
            @endif
        @endif
        <flux:modal :dismissible="false" name="distribution-started" class="md:w-120 flex flex-col items-center">
            <span class="fas fa-circle-check text-8xl text-green-500"></span>
            <h2 class="mt-4">Distribution started for group {{ $nextGroup - 1 }}</h2>
            <p class="mt-2 mb-4">Estimated entry time: <strong>{{ optional($estimatedEntryTime)->format('g:i A') }}</strong> ({{ optional($estimatedEntryTime)->diffForHumans() }})</p>
            <flux:modal.close>
                <flux:button type="button" variant="primary">Continue</flux:button>
            </flux:modal.close>
        </flux:modal>
    </div>

    <livewire:status-board show-original-estimates />
</div>
