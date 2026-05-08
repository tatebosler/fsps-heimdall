<?php

use App\Helpers\DateHelpers;
use App\Helpers\EntryTimeEstimator;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public $switchable = false;
    public $includeFutureGroups = false;
    public $showOriginalEstimates = false;
    public $channels = [];
    public string $date = '';

    public function mount()
    {
        $this->date = date('l');
        $this->updateChannels();
    }

    public function updated()
    {
        $this->updateChannels();
    }

    public function updateChannels()
    {
        $psYear = DateHelpers::psYearForDate(now());
        $weekday = DateHelpers::dayStringToNumber($this->date);
        $channels = Channel::whereLike('id', "{$psYear}{$weekday}__")->whereNotNull('distribution_started_at')->get();
        $offBands = Channel::whereLike('id', "{$psYear}9{$weekday}0")->first();
        if ($offBands and $offBands->cleared_at !== null) {
            $channels->push($offBands);
        } else if ($this->includeFutureGroups) {
            $futureChannels = EntryTimeEstimator::getHypotheticalEstimates($weekday);
            foreach ($futureChannels as $data) {
                $channelId = $data['group'] + ($psYear * 1000) + ($weekday * 100);
                if ($channels->contains(fn (Channel $existingChannel) => $existingChannel->id === $channelId)) {
                    continue;
                }

                $ch = new Channel();
                $ch->id = $channelId;
                $ch->estimated_entry_at = $data['estimated_entry_at'];
                $channels->push($ch);
            }
        }
        $this->channels = $channels->sortBy('id')->all();
    }
};
?>

<div wire:poll.10s="updateChannels">
    @if ($switchable)
        <div class="my-2 flex flex-wrap items-center">
            <div class="text-xl">Switch day</div>
            <div class="ml-auto sm:ml-6 flex flex-wrap items-center space-x-1">
                @foreach (array_keys(config('ps.hours')) as $d)
                    @if ($d === $date)
                        <div class="bg-{{ config('ps.colors')[$d] }}-200 text-{{ config('ps.colors')[$d] }}-800 dark:bg-{{ config('ps.colors')[$d] }}-800 dark:text-{{ config('ps.colors')[$d] }}-200 font-bold px-2 py-1 rounded-lg text-sm">
                            {{ $d }}
                        </div>
                    @else
                        <button wire:click="$set('date', '{{ $d }}')" class="dark:text-{{ config('ps.colors')[$d] }}-100 cursor-pointer hover:dark:bg-{{ config('ps.colors')[$d] }}-900 text-{{ config('ps.colors')[$d] }}-900 cursor-pointer hover:bg-{{ config('ps.colors')[$d] }}-100 px-2 py-1 rounded-lg text-sm">
                            {{ $d }}
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
    <table class="w-full border-separate border-spacing-y-1">
        <thead>
            <tr>
                <th class="w-16 sm:min-w-20 uppercase text-sm font-light text-gray-300 tracking-widest">Group</th>
                <th class="sm:w-1/2 uppercase text-sm font-light text-gray-300 tracking-widest">Status</th>
                <th class="hidden sm:table-cell sm:w-1/2 uppercase text-sm font-light text-gray-300 tracking-widest">Total wait time</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($channels as $channel)
                <tr wire:key="{{ $channel->id }}">
                    @if ($channel->isSpecial())
                        <td class="dark:bg-{{ config('ps.colors')[$date] }}-600 dark:text-{{ config('ps.colors')[$date] }}-100 text-{{ config('ps.colors')[$date] }}-900 bg-{{ config('ps.colors')[$date] }}-400 text-3xl sm:text-4xl text-center h-16 font-black">
                            ALL
                        </td>
                        <td class="sm:text-xl px-4">Wristbands no longer required effective {{ $channel->cleared_at->format('g:i a') }}</td>
                        <td></td>
                    @elseif ($channel->cleared_at)
                        <td class="dark:bg-{{ config('ps.colors')[$date] }}-600 dark:text-{{ config('ps.colors')[$date] }}-100 text-{{ config('ps.colors')[$date] }}-900 bg-{{ config('ps.colors')[$date] }}-400 text-3xl sm:text-4xl text-center h-16 font-black">
                            {{ $channel->id % 100 }}
                        </td>
                        <td class="sm:text-xl px-4">Admitted at {{ $channel->cleared_at->format('g:i a') }}</td>
                        <td class="hidden sm:table-cell">
                            {{ $channel->cleared_at->diffAsCarbonInterval($channel->distribution_started_at)->cascade()->forHumans(['parts' => 2]) }}
                        </td>
                    @elseif ($channel->distribution_started_at)
                        <td class="dark:bg-{{ config('ps.colors')[$date] }}-800 dark:text-{{ config('ps.colors')[$date] }}-100 text-{{ config('ps.colors')[$date] }}-900 bg-{{ config('ps.colors')[$date] }}-200 text-3xl sm:text-4xl text-center h-16 font-black">
                            {{ $channel->id % 100 }}
                        </td>
                        @if ($channel->estimated_entry_at)
                            <td class="sm:text-xl px-4 leading-5 sm:leading-5">
                                <span>Estimated entry time: {{ $channel->estimated_entry_at->format('g:i a') }}</span>
                                <br>
                                @if ($showOriginalEstimates and optional($channel->original_estimated_entry_at)->diffInMinutes($channel->estimated_entry_at) >= 1)
                                    <span class="text-xs leading-3">Original estimate: {{ $channel->original_estimated_entry_at->format('g:i a') }}</span>
                                @endif
                            </td>
                        @else
                            <td class="text-xl px-4">Pending admission</td>
                        @endif
                        <td class="hidden sm:table-cell">
                            {{ $channel->distribution_started_at->diffAsCarbonInterval()->cascade()->forHumans(['parts' => 2]) }}
                        </td>
                    @else
                        <td class="dark:bg-{{ config('ps.colors')[$date] }}-950 dark:text-{{ config('ps.colors')[$date] }}-100 text-{{ config('ps.colors')[$date] }}-900 text-{{ config('ps.colors')[$date] }}-50 text-3xl sm:text-4xl text-center h-16 font-black">
                            {{ $channel->id % 100 }}
                        </td>
                        <td class="sm:text-xl px-4">Estimated entry time: {{ optional($channel->estimated_entry_at)->format('g:i a') }}</td>
                        <td class="hidden sm:table-cell">
                            <em class="text-gray-400">Wristbands for this group have not yet been distributed</em>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="hidden sm:table-cell text-center">
                        <em class="text-gray-400">There are no active {{ $date }} wristband groups at the moment.</em>
                    </td>
                    <td colspan="2" class="sm:hidden text-center">
                        <em class="text-gray-400">There are no active {{ $date }} wristband groups at the moment.</em>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
