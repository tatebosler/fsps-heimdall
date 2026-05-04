<?php

use App\Helpers\DateHelpers;
use App\Helpers\EntryTimeEstimator;
use App\Models\Channel;
use App\Notifications\GroupCleared;
use App\Notifications\NextGroup;
use App\Notifications\OffBands;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public int $nextGroup = 0;
    public ?int $lastCleared = 0;
    public ?int $lastNotified = 0;
    public ?Carbon $lastClearedAt = null;

    public function mount()
    {
        $this->updateStatus();
    }

    public function updateStatus()
    {
        $psYear = DateHelpers::psYearForDate(now());
        $weekday = date('N');
        $nextToClear = Channel::whereLike('id', "{$psYear}{$weekday}__")->whereNull('cleared_at')->orderBy('id', 'asc')->first();
        $lastCleared = Channel::where(function ($query) use ($psYear, $weekday) {
            $query->whereLike('id', "{$psYear}{$weekday}__")->orWhereLike('id', "{$psYear}9{$weekday}0");
        })->whereNotNull('cleared_at')->orderBy('id', 'desc')->first();

        if ($nextToClear) {
            $this->nextGroup = ($nextToClear->id % 100);
            if ($lastCleared) {
                $this->lastCleared = $lastCleared->id % 100;
                $this->lastClearedAt = $lastCleared->cleared_at;
                Cache::set('entry-clearing', $this->nextGroup - 1);
            } else {
                Cache::forget('entry-clearing');
            }
        } else if ($lastCleared and $lastCleared->isSpecial()) {
            $this->lastCleared = -1;
            $this->nextGroup = -3;
            $this->lastClearedAt = $lastCleared->cleared_at;
            Cache::forget('entry-distributing');
        } else if ($lastCleared) {
            $this->lastCleared = $lastCleared->id % 100;
            $this->nextGroup = -1;
            $this->lastClearedAt = $lastCleared->cleared_at;
        } else {
            $this->nextGroup = -2;
            Cache::forget('entry-clearing');
        }
    }

    public function clearGroup()
    {
        $channelIdSuffix = '999';
        if ($this->nextGroup === -2) {
            return;
        } else if ($this->nextGroup === -1) {
            $channelIdSuffix = '9' . date('N') . '0';
        } else {
            $channelIdSuffix = date('N') . str_pad($this->nextGroup, 2, '0', STR_PAD_LEFT);
        }
        $psYear = DateHelpers::psYearForDate(now());
        $channelId = "{$psYear}{$channelIdSuffix}";
        $channel = Channel::firstOrCreate(['id' => $channelId]);
        if (!$channel) {
            return;
        }

        if ($channel->cleared_at === null) {
            $clearedAt = now();
            $channel->cleared_at = $clearedAt;
            $this->lastCleared = $this->nextGroup;
            $this->lastClearedAt = $clearedAt;
            $channel->save();
        }

        EntryTimeEstimator::estimateEntryTimes();
        if ($this->nextGroup < 0) {
            Cache::forget('entry-distributing');
            Cache::forget('entry-newt-minutes');
        } else {
            Cache::set('entry-clearing', $this->nextGroup);
        }

        // Get the correct subscribers for the notification.
        // If clearing off-bands or group 0, notify the channel we just cleared. Otherwise, get the next channel.
        // Always notify xx9y9 and xx999.

        $firehoseSubscribers = Channel::firstOrCreate(['id' => "{$psYear}999"])->subscribers;
        $dayFirehoseSubscribers = Channel::firstOrCreate(['id' => "{$psYear}9" . date('N') . "9"])->subscribers;

        if ($channel->id % 100 === 0) {
            $clearedSubscribers = $channel->subscribers;
            $clearedSubscribers->merge($firehoseSubscribers)
                ->merge($dayFirehoseSubscribers)
                ->unique('id')
                ->each(function ($subscriber) use ($channel) {
                    $subscriber->notify(new GroupCleared($channel));
                });

            $nextChannel = Channel::find($channel->id + 1);
            if ($nextChannel) {
                $subscribers = $nextChannel->subscribers;
                $subscribers->merge($firehoseSubscribers)
                    ->merge($dayFirehoseSubscribers)
                    ->unique('id')
                    ->each(function ($subscriber) use ($channel) {
                        $subscriber->notify(new NextGroup($channel));
                    });
                $this->lastNotified = $clearedSubscribers->unique('id')->count() + $subscribers->unique('id')->count();
            } else {
                $this->lastNotified = $clearedSubscribers->unique('id')->count();
            }
        } else if ($channel->isSpecial()) {
            $clearedSubscribers = $channel->subscribers;
            $clearedSubscribers->merge($firehoseSubscribers)
                ->merge($dayFirehoseSubscribers)
                ->unique('id')
                ->each(function ($subscriber) use ($channel) {
                    $subscriber->notify(new OffBands($channel));
                });
            $this->lastNotified = $clearedSubscribers->unique('id')->count();
        } else {
            $nextChannel = Channel::find($channel->id + 1);
            if ($nextChannel) {
                $subscribers = $nextChannel->subscribers;
                $subscribers->merge($firehoseSubscribers)
                    ->merge($dayFirehoseSubscribers)
                    ->unique('id')
                    ->each(function ($subscriber) use ($channel) {
                        $subscriber->notify(new NextGroup($channel));
                    });
                $this->lastNotified = $subscribers->unique('id')->count();
            } else {
                $this->lastNotified = 0;
            }
        }

        $this->updateStatus();
        $this->modal('cleared')->show();
    }
};
?>

<div class="p-4 sm:px-8 space-y-4" wire:poll="updateStatus">
    <h1>Tower Dashboard</h1>

    <livewire:entry-status hide-actions />

    <div>
        @if ($nextGroup === -3)
            <div class="dark:bg-{{ config('ps.colors.' . date('l')) }}-950 bg-{{ config('ps.colors.' . date('l')) }}-50 px-3 py-6 rounded-xl w-full block text-4xl font-bold text-center">
                You're off wristbands!
            </div>
        @elseif ($nextGroup === -2)
            <div class="dark:bg-{{ config('ps.colors.' . date('l')) }}-950 bg-{{ config('ps.colors.' . date('l')) }}-50 px-3 py-6 rounded-xl w-full block text-4xl font-bold text-center">
                Waiting for wristband distribution to start
            </div>
        @elseif ($nextGroup === -1)
            <button class="dark:bg-{{ config('ps.colors.' . date('l')) }}-800 hover:dark:bg-{{ config('ps.colors.' . date('l')) }}-700 active:dark:bg-{{ config('ps.colors.' . date('l')) }}-600 bg-{{ config('ps.colors.' . date('l')) }}-300 hover:bg-{{ config('ps.colors.' . date('l')) }}-200 active:bg-{{ config('ps.colors.' . date('l')) }}-100 px-3 py-6 rounded-xl w-full block text-4xl font-bold" wire:click="clearGroup">
                Go off wristbands
            </button>
        @else
            <button class="dark:bg-{{ config('ps.colors.' . date('l')) }}-800 hover:dark:bg-{{ config('ps.colors.' . date('l')) }}-700 active:dark:bg-{{ config('ps.colors.' . date('l')) }}-600 bg-{{ config('ps.colors.' . date('l')) }}-300 hover:bg-{{ config('ps.colors.' . date('l')) }}-200 active:bg-{{ config('ps.colors.' . date('l')) }}-100 px-3 py-6 rounded-xl w-full block text-4xl font-bold" wire:click="clearGroup">
                Clear group {{ $nextGroup }}
            </button>
        @endif
        @if ($lastClearedAt !== null and $nextGroup > -3)
            <div class="mx-4 py-2 px-3 bg-gray-200 dark:bg-gray-800 rounded-b text-center max-sm:text-xs" wire:poll>
                <p>Group {{ $lastCleared }} cleared {{ $lastClearedAt->diffForHumans() }}</p>
            </div>
        @elseif ($nextGroup === -3 and $lastClearedAt !== null)
            <div class="mx-4 py-2 px-3 bg-gray-200 dark:bg-gray-800 rounded-b text-center max-sm:text-xs" wire:poll>
                <p>Off-bands declared {{ $lastClearedAt->diffForHumans() }}</p>
            </div>
        @endif
        <flux:modal :dismissible="false" name="cleared" class="md:w-120 flex flex-col items-center">
            <span class="fas fa-circle-check text-8xl text-green-500"></span>
            @if ($lastCleared === -1)
                <h2 class="mt-4">Off-bands declared!</h2>
            @else
                <h2 class="mt-4">Group {{ $lastCleared }} cleared!</h2>
            @endif
            <p class="mt-2 mb-4">{{ $lastNotified }} text messages have been sent.</p>
            <flux:modal.close>
                <flux:button type="button" variant="primary">Continue</flux:button>
            </flux:modal.close>
        </flux:modal>
    </div>

    <livewire:status-board show-original-estimates />
</div>
