<?php

use App\Helpers\DateHelpers;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Entry Status')] class extends Component
{
    public ?int $distributing = null;

    public ?int $clearing = null;

    public ?int $newtMinutes = null;

    public bool $hideActions = false;

    public function mount(bool $hideActions = false): void
    {
        $this->hideActions = $hideActions;
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
        $colors = config('ps.colors', []);

        return $colors[now()->dayName] ?? 'purple';
    }

    #[Computed]
    public function firstGroup(): int
    {
        $zeroGroupDays = config('ps.group_zero', []);

        return array_key_exists(now()->dayName, $zeroGroupDays) ? 0 : 1;
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
    public function nextOpeningCountdown(): ?array
    {
        if ($this->saleStatus() !== 'closed') {
            return null;
        }

        $currentTime = now();

        $nextWristbandStart = null;

        if (DateHelpers::isPlantSaleOpenOnDate($currentTime)) {
            $todayHours = config('ps.hours.'.$currentTime->dayName);

            if (is_array($todayHours) && isset($todayHours['wristbands'])) {
                $todayWristbandStart = $currentTime->copy()->setTimeFromTimeString($todayHours['wristbands']);

                if ($currentTime->lessThanOrEqualTo($todayWristbandStart->copy()->addMinutes(15))) {
                    $nextWristbandStart = $todayWristbandStart;
                }
            }
        }

        if ($nextWristbandStart === null) {
            $nextWristbandStart = DateHelpers::nextWristbandDistributionStart($currentTime, 7);
        }

        if ($nextWristbandStart === null) {
            return null;
        }

        $hours = config('ps.hours.'.$nextWristbandStart->dayName);

        if (! is_array($hours) || ! isset($hours['wristbands'], $hours['open'])) {
            return null;
        }

        $showStartingSoon = $currentTime->betweenIncluded(
            $nextWristbandStart->copy()->subMinutes(10),
            $nextWristbandStart->copy()->addMinutes(15),
        );

        $nextPublicSale = null;

        if (DateHelpers::isPlantSaleOpenOnDate($currentTime) && $nextWristbandStart->isThursday()) {
            $nextPublicWristbandStart = DateHelpers::nextWristbandDistributionStart(
                $nextWristbandStart->copy()->addDay()->startOfDay(),
                7,
            );

            if ($nextPublicWristbandStart !== null) {
                $nextPublicHours = config('ps.hours.'.$nextPublicWristbandStart->dayName);

                if (is_array($nextPublicHours) && isset($nextPublicHours['wristbands'], $nextPublicHours['open'])) {
                    $nextPublicSale = [
                        'day' => $nextPublicWristbandStart->format('l'),
                        'date' => $nextPublicWristbandStart->format('F j'),
                        'wristbands' => Carbon::createFromFormat('H:i', $nextPublicHours['wristbands'], $nextPublicWristbandStart->timezone)->format('g:i A'),
                        'open' => Carbon::createFromFormat('H:i', $nextPublicHours['open'], $nextPublicWristbandStart->timezone)->format('g:i A'),
                    ];
                }
            }
        }

        return [
            'target' => $nextWristbandStart->toIso8601String(),
            'day' => $nextWristbandStart->format('l'),
            'date' => $nextWristbandStart->format('F j'),
            'is_today' => DateHelpers::isPlantSaleOpenOnDate($currentTime),
            'is_thursday' => $nextWristbandStart->isThursday(),
            'show_starting_soon' => $showStartingSoon,
            'wristbands' => Carbon::createFromFormat('H:i', $hours['wristbands'], $nextWristbandStart->timezone)->format('g:i A'),
            'open' => Carbon::createFromFormat('H:i', $hours['open'], $nextWristbandStart->timezone)->format('g:i A'),
            'next_public_sale' => $nextPublicSale,
        ];
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
            @php($nextOpening = $this->nextOpeningCountdown())
            <div class="bg-gray-600 text-yellow-200 rounded-xl p-6 flex flex-col items-center text-2xl text-center">
                <span class="fas fa-ban text-6xl my-2"></span>
                <span>The sale is currently closed</span>

                @if ($nextOpening !== null)
                    <div
                        class="mt-4 w-full max-w-2xl rounded-xl bg-gray-800/40 p-4 text-left text-base text-yellow-50"
                        x-data="{
                            targetMs: Date.parse('{{ $nextOpening['target'] }}'),
                            remainingSeconds: 0,
                            intervalId: null,
                            init() {
                                this.tick();
                                this.intervalId = setInterval(() => this.tick(), 1000);
                            },
                            destroy() {
                                if (this.intervalId !== null) {
                                    clearInterval(this.intervalId);
                                }
                            },
                            tick() {
                                this.remainingSeconds = Math.max(0, Math.floor((this.targetMs - Date.now()) / 1000));
                            },
                            days() {
                                return Math.floor(this.remainingSeconds / 86400);
                            },
                            hours() {
                                return Math.floor((this.remainingSeconds % 86400) / 3600);
                            },
                            minutes() {
                                return Math.floor((this.remainingSeconds % 3600) / 60);
                            },
                            seconds() {
                                return this.remainingSeconds % 60;
                            },
                            format(value) {
                                return String(value);
                            },
                        }"
                    >
                        <p class="text-lg font-semibold">
                            @if ($nextOpening['is_today'])
                                We'll see you real soon!
                            @else
                                Next sale day: {{ $nextOpening['day'] }}, {{ $nextOpening['date'] }}@if ($nextOpening['is_thursday']) <span class="font-medium">(volunteer pre-sale)</span>@endif
                            @endif
                        </p>
                        @if ($nextOpening['is_today'] && $nextOpening['is_thursday'] && $nextOpening['next_public_sale'] !== null)
                            <p class="mt-1">Wristband distribution for the volunteer pre-sale begins at {{ $nextOpening['wristbands'] }} and the sale opens at {{ $nextOpening['open'] }}.</p>
                            <p class="mt-1">The next public sale day is {{ $nextOpening['next_public_sale']['day'] }}, {{ $nextOpening['next_public_sale']['date'] }}. Wristband distribution begins at {{ $nextOpening['next_public_sale']['wristbands'] }} and the sale opens at {{ $nextOpening['next_public_sale']['open'] }}.</p>
                        @else
                            <p class="mt-1">Wristband distribution begins at {{ $nextOpening['wristbands'] }} and the sale opens at {{ $nextOpening['open'] }}.</p>
                        @endif
                        @if ($nextOpening['show_starting_soon'])
                            <p class="mt-4 text-center text-xl font-semibold">Wristband distribution beginning shortly...</p>
                        @else
                            <div class="mt-4 flex flex-col gap-2 sm:grid sm:grid-cols-4 sm:text-center">
                                <div x-bind:class="days() < 1 ? 'hidden sm:block' : 'flex sm:block'" class="items-baseline justify-between rounded-lg bg-gray-900/40 p-3">
                                    <p class="text-3xl font-black" x-text="days()"></p>
                                    <p class="text-xs uppercase tracking-[0.2em] sm:mt-1">
                                        <span class="sm:hidden" x-text="days() === 1 ? 'day' : 'days'"></span>
                                        <span class="hidden sm:inline md:hidden">days</span>
                                        <span class="hidden md:inline" x-text="days() === 1 ? 'day' : 'days'"></span>
                                    </p>
                                </div>
                                <div class="flex items-baseline justify-between rounded-lg bg-gray-900/40 p-3 sm:block">
                                    <p class="text-3xl font-black" x-text="format(hours())"></p>
                                    <p class="text-xs uppercase tracking-[0.2em] sm:mt-1">
                                        <span class="sm:hidden" x-text="hours() === 1 ? 'hour' : 'hours'"></span>
                                        <span class="hidden sm:inline md:hidden">hrs</span>
                                        <span class="hidden md:inline" x-text="hours() === 1 ? 'hour' : 'hours'"></span>
                                    </p>
                                </div>
                                <div class="flex items-baseline justify-between rounded-lg bg-gray-900/40 p-3 sm:block">
                                    <p class="text-3xl font-black" x-text="format(minutes())"></p>
                                    <p class="text-xs uppercase tracking-[0.2em] sm:mt-1">
                                        <span class="sm:hidden" x-text="minutes() === 1 ? 'minute' : 'minutes'"></span>
                                        <span class="hidden sm:inline md:hidden">min</span>
                                        <span class="hidden md:inline" x-text="minutes() === 1 ? 'minute' : 'minutes'"></span>
                                    </p>
                                </div>
                                <div class="flex items-baseline justify-between rounded-lg bg-gray-900/40 p-3 sm:block">
                                    <p class="text-3xl font-black" x-text="format(seconds())"></p>
                                    <p class="text-xs uppercase tracking-[0.2em] sm:mt-1">
                                        <span class="sm:hidden" x-text="seconds() === 1 ? 'second' : 'seconds'"></span>
                                        <span class="hidden sm:inline md:hidden">sec</span>
                                        <span class="hidden md:inline" x-text="seconds() === 1 ? 'second' : 'seconds'"></span>
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @unless ($this->hideActions)
                    <div class="mt-4 flex w-full flex-col gap-2 text-base">
                        <a href="https://www.friendsschoolplantsale.com/doing-sale" class="block w-full rounded-2xl bg-gray-200 px-3 py-2 text-gray-800 hover:bg-gray-100 active:bg-gray-50"><span class="fas fa-clock"></span> Full hours</a>
                        <a href="https://www.friendsschoolplantsale.com/driving" class="block w-full rounded-2xl bg-gray-200 px-3 py-2 text-gray-800 hover:bg-gray-100 active:bg-gray-50"><span class="fas fa-parking"></span> Arrival &amp; parking info</a>
                        <a href="https://www.friendsschoolplantsale.com/accessibility" class="block w-full rounded-2xl bg-blue-200 px-3 py-2 text-blue-800 hover:bg-blue-100 active:bg-blue-50"><span class="fab fa-accessible-icon"></span> Accessible arrival &amp; parking info</a>
                    </div>
                @endunless
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
                            <p>Wristbands are not required to enter the Plant Sale for the rest of the day. Come on in!</p>
                        </div>
                    </div>
                @else
                    <div class="flex items-stretch bg-{{ $this->color() }}-100 dark:bg-{{ $this->color() }}-900">
                        <div class="flex-1 p-2 sm:text-xl min-h-16 flex items-center">Currently distributing wristband group</div>
                        <div class="bg-{{ $this->color() }}-300 dark:bg-{{ $this->color() }}-700 ml-auto w-40 shrink-0 self-stretch flex items-center justify-center text-center text-4xl font-black">{{ $this->distributing }}</div>
                    </div>
                    @if ($this->saleStatus() === 'on-bands')
                        <div class="flex items-stretch mt-1 bg-{{ $this->color() }}-100 dark:bg-{{ $this->color() }}-900">
                            <div class="flex-1 p-2 sm:text-xl min-h-16 flex items-center">Currently welcoming customers in {{ $this->clearing === $this->firstGroup() ? 'group' : 'groups' }}</div>
                            <div class="bg-{{ $this->color() }}-300 dark:bg-{{ $this->color() }}-700 ml-auto w-40 shrink-0 self-stretch flex items-center justify-center text-center text-4xl font-black">{{ $this->clearingDisplay() }}</div>
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
                            @unless($this->hideActions)
                                <p>Wait times fluctuate throughout the day.</p>
                                <p>If you're not at the sale yet, your wait time will likely be different when you arrive.</p>
                            @endunless
                        </div>
                    @endif
                @endif
            </div>
        @break
    @endswitch
</div>
