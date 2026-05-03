<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Entry Status')] class extends Component
{
    public ?int $distributing = null;

    public ?int $clearing = null;

    public ?int $newtMinutes = null;

    public function mount(): void
    {
        $this->loadStatus();
    }

    public function loadStatus(): void
    {
        $distributing = Cache::get('entry-distributing');
        $this->distributing = $distributing !== null ? (int) $distributing : null;

        $clearing = Cache::get('entry-clearing');
        $this->clearing = $clearing !== null ? (int) $clearing : null;

        $newt = Cache::get('entry-newt-minutes');
        $this->newtMinutes = $newt !== null ? (int) $newt : null;
    }

    #[Computed]
    public function color(): string
    {
        $colors = config('ps.entry_status_colors', []);

        return $colors[now()->dayOfWeek] ?? $colors['default'] ?? 'purple';
    }

    #[Computed]
    public function firstGroup(): int
    {
        $zeroGroupDays = config('ps.entry_zero_group_days', [4]);

        return in_array(now()->dayOfWeek, $zeroGroupDays) ? 0 : 1;
    }

    #[Computed]
    public function waitTime(): ?string
    {
        if ($this->saleStatus() !== 'pre-opening' && $this->saleStatus() !== 'on-bands') {
            return null;
        }

        if ($this->newtMinutes === null) {
            return null;
        }

        return match (true) {
            $this->newtMinutes <= 10 => 'a few minutes',
            $this->newtMinutes <= 25 => 'about 20 minutes',
            $this->newtMinutes <= 40 => 'about 30 minutes',
            $this->newtMinutes <= 75 => 'about an hour',
            $this->newtMinutes <= 105 => 'about 1½ hours',
            $this->newtMinutes <= 135 => 'about 2 hours',
            $this->newtMinutes <= 165 => 'about 2½ hours',
            $this->newtMinutes <= 195 => 'about 3 hours',
            $this->newtMinutes <= 225 => 'about 3½ hours',
            $this->newtMinutes <= 255 => 'about 4 hours',
            default => 'over 4 hours',
        };
    }

    #[Computed]
    public function clearingDisplay(): ?string
    {
        if ($this->saleStatus() !== 'on-bands' || $this->clearing === null) {
            return null;
        }

        $firstGroup = $this->firstGroup;

        if ($this->clearing <= $firstGroup) {
            return (string) $this->clearing;
        }

        return $firstGroup . ' – ' . $this->clearing;
    }

    public function saleStatus(): string
    {
        return match (true) {
            $this->distributing === null && $this->clearing === null => 'closed',
            $this->distributing !== null && $this->clearing === null => 'pre-opening',
            $this->distributing !== null && $this->clearing !== null => 'on-bands',
            $this->distributing === null && $this->clearing !== null => 'off-bands',
            default => 'closed',
        };
    }
};
?>

<div wire:poll.5s="loadStatus">
    @switch($this->saleStatus())
        @case('closed')
            <div class="bg-gray-600 text-yellow-200 rounded-xl p-6 flex flex-col items-center text-2xl text-center">
                <span class="fas fa-ban text-6xl my-2"></span>
                <span>The sale is currently closed</span>
            </div>
        @break

        @default
            <div class="bg-{{ $this->color() }}-200 text-{{ $this->color() }}-950 dark:bg-{{ $this->color() }}-800 dark:text-{{ $this->color() }}-50 rounded-2xl">
                <div class="bg-{{ $this->color() }}-400 dark:bg-{{ $this->color() }}-600 rounded-t-2xl px-2 py-1 flex items-center">
                    <span>Current Status</span>
                    <div class="ml-auto flex items-center align-center">
                        <span class="fas fa-circle absolute"></span>
                        <span class="fas fa-circle animate-ping relative"></span>
                    </div>
                    <span class="font-bold mx-1">LIVE</span>
                </div>
                @if ($this->saleStatus() === 'off-bands')
                    <div class="p-4 flex items-center">
                        <span class="fas fa-check text-4xl mr-4"></span>
                        <div>
                            <p class="text-2xl font-semibold">There's no wait to shop!</p>
                            <p>Wristbands are no longer required to enter the Plant Sale. Come on in!</p>
                        </div>
                    </div>
                @else
                    <div class="flex items-center bg-{{ $this->color() }}-100 dark:bg-{{ $this->color() }}-900">
                        <div class="p-2 text-xl">Currently distributing wristband group</div>
                        <div class="bg-{{ $this->color() }}-300 dark:bg-{{ $this->color() }}-700 ml-auto w-40 shrink-0 text-center text-4xl font-black py-4">{{ $this->distributing }}</div>
                    </div>
                    @if ($this->saleStatus() === 'on-bands')
                        <div class="flex items-center mt-1 bg-{{ $this->color() }}-100 dark:bg-{{ $this->color() }}-900">
                            <div class="p-2 text-xl">Currently welcoming customers in {{ $this->clearing === $this->firstGroup() ? 'group' : 'groups' }}</div>
                            <div class="bg-{{ $this->color() }}-300 dark:bg-{{ $this->color() }}-700 ml-auto w-40 shrink-0 text-center text-4xl font-black py-4">{{ $this->clearingDisplay() }}</div>
                        </div>
                    @endif
                    @if ($this->waitTime() === null)
                        <div class="flex flex-col text-center items-center p-2">
                            <p>This data updates automatically every few seconds &mdash; no need to refresh.</p>
                        </div>
                    @else
                        <div class="flex flex-col text-center items-center p-2">
                            <p>Customers arriving at the Plant Sale right now can expect to wait</p>
                            <p class="text-3xl font-bold my-2">{{ $this->waitTime() }}</p>
                            <p>Wait times fluctuate throughout the day.</p>
                            <p>If you're not at the sale yet, your wait time will likely be different when you arrive.</p>
                        </div>
                    @endif
                @endif
            </div>
        @break
    @endswitch
</div>
