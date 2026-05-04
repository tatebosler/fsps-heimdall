<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public int $nextGroup = 0;
    public ?Carbon $lastDistributedAt = null;

    public function mount()
    {
        $psYear = DateHelpers::psYearForDate(now());
        $weekday = date('N');
        $lastDistributed = Channel::whereLike('id', "{$psYear}{$weekday}__")->whereNotNull('distribution_started_at')->latest('distribution_started_at')->orderBy('id', 'desc')->first();

        if ($lastDistributed) {
            $this->nextGroup = ($lastDistributed->id % 100) + 1;
            $this->lastDistributedAt = $lastDistributed->distribution_started_at;
            Cache::set('entry-distributing', $this->nextGroup - 1);
        } else {
            $todayConfig = config('ps.group_zero');
            $this->nextGroup = (int) !in_array(date('l'), $todayConfig);
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
        if (in_array(date('l'), $todayConfig) and $this->nextGroup === 1) {
            $groupZeroChannelId = sprintf('%s%s%02d', $psYear, $weekday, 0);
            $groupZeroChannel = Channel::firstOrCreate(['id' => $groupZeroChannelId]);

            if ($groupZeroChannel->distribution_started_at === null) {
                $groupZeroChannel->update(['distribution_started_at' => $now]);
            }
        }

        Cache::set('entry-distributing', $this->nextGroup ?? 1);
        if (! $this->nextGroup) {
            $this->nextGroup = 2;
        } else {
            $this->nextGroup++;
        }
        $this->lastDistributedAt = $now;
    }
};
?>

<div class="p-4 s:px-8 space-y-4">
    <h1>Wristband Booth Dashboard</h1>

    <livewire:entry-status hide-actions />

    <div>
        <button class="dark:bg-{{ config('ps.colors.' . date('l')) }}-800 hover:dark:bg-{{ config('ps.colors.' . date('l')) }}-700 active:dark:bg-{{ config('ps.colors.' . date('l')) }}-600 bg-{{ config('ps.colors.' . date('l')) }}-300 hover:bg-{{ config('ps.colors.' . date('l')) }}-200 active:bg-{{ config('ps.colors.' . date('l')) }}-100 px-3 py-6 rounded-xl w-full block text-4xl font-bold" wire:click="distribute">
            Start distributing group {{ $nextGroup }}
        </button>
        @if ($nextGroup > 1)
            <div class="mx-4 py-2 px-3 bg-gray-200 dark:bg-gray-800 rounded-b text-center max-sm:text-xs">
                <p>Group {{ $nextGroup - 1 }} distribution started {{ $lastDistributedAt->diffForHumans() }}</p>
            </div>
        @endif
    </div>
</div>
