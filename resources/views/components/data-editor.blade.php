<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.admin')] #[Title('Manual Data Editor')] class extends Component
{
    public int $selectedCalendarYear;

    /**
     * @var array<int, array{customers_arrived_at: string|null, distribution_started_at: string|null, cleared_at: string|null}>
     */
    public array $timestamps = [];

    /**
     * @var array<int, array{cleared_at: string|null}>
     */
    public array $offBandsTimestamps = [];

    public function mount(): void
    {
        $this->selectedCalendarYear = now()->year;

        $years = $this->calendarYears();
        if (! in_array($this->selectedCalendarYear, $years, true)) {
            $this->selectedCalendarYear = $years[0] ?? now()->year;
        }

        $this->loadTimestamps();
    }

    public function updatedSelectedCalendarYear(): void
    {
        $this->loadTimestamps();
    }

    public function calendarYears(): array
    {
        $psYears = Channel::query()
            ->whereRaw('MOD(id, 1000) < 900')
            ->selectRaw('FLOOR(id / 1000) as ps_year')
            ->distinct()
            ->pluck('ps_year')
            ->map(static fn (int|string $psYear): int => DateHelpers::calendarYearForPsYear($psYear));

        return $psYears
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function channels(): EloquentCollection
    {
        $psYear = DateHelpers::psYearForDate(now()->setYear($this->selectedCalendarYear));

        return Channel::query()
            ->whereRaw('MOD(id, 1000) < 900')
            ->whereBetween('id', [
                $psYear * 1000,
                (($psYear + 1) * 1000) - 1,
            ])
            ->orderBy('id')
            ->get();
    }

    public function offBandsChannels(): EloquentCollection
    {
        $psYear = DateHelpers::psYearForDate(now()->setYear($this->selectedCalendarYear));

        return Channel::query()
            ->whereRaw('FLOOR(id / 1000) = ?', [$psYear])
            ->whereRaw('FLOOR(MOD(id, 1000) / 100) = 9')
            ->whereRaw('MOD(id, 10) = 0')
            ->whereRaw('MOD(id, 100) != 0')
            ->orderBy('id')
            ->get();
    }

    public function loadTimestamps(): void
    {
        $this->timestamps = [];

        foreach ($this->channels() as $channel) {
            $this->timestamps[$channel->id] = [
                'customers_arrived_at' => $channel->customers_arrived_at?->format('H:i:s'),
                'distribution_started_at' => $channel->distribution_started_at?->format('H:i:s'),
                'cleared_at' => $channel->cleared_at?->format('H:i:s'),
            ];
        }

        $this->offBandsTimestamps = [];

        foreach ($this->offBandsChannels() as $channel) {
            $this->offBandsTimestamps[$channel->id] = [
                'cleared_at' => $channel->cleared_at?->format('H:i:s'),
            ];
        }
    }

    public function saveChannel(int $channelId): void
    {
        $channel = Channel::find($channelId);

        if (! $channel || $channel->isSpecial()) {
            return;
        }

        $row = $this->timestamps[$channelId] ?? [];

        $channel->customers_arrived_at = $this->buildTimestamp($channel, 'customers_arrived_at', $row['customers_arrived_at'] ?? null);
        $channel->distribution_started_at = $this->buildTimestamp($channel, 'distribution_started_at', $row['distribution_started_at'] ?? null);
        $channel->cleared_at = $this->buildTimestamp($channel, 'cleared_at', $row['cleared_at'] ?? null);
        $channel->save();

        $this->timestamps[$channelId] = [
            'customers_arrived_at' => $channel->customers_arrived_at?->format('H:i:s'),
            'distribution_started_at' => $channel->distribution_started_at?->format('H:i:s'),
            'cleared_at' => $channel->cleared_at?->format('H:i:s'),
        ];
    }

    public function saveOffBandsChannel(int $channelId): void
    {
        $channel = Channel::find($channelId);

        if (! $channel || ! $channel->isSpecial()) {
            return;
        }

        $row = $this->offBandsTimestamps[$channelId] ?? [];

        $channel->cleared_at = $this->buildTimestamp($channel, 'cleared_at', $row['cleared_at'] ?? null);
        $channel->save();

        $this->offBandsTimestamps[$channelId] = [
            'cleared_at' => $channel->cleared_at?->format('H:i:s'),
        ];
    }

    public function saveAll(): void
    {
        foreach (array_keys($this->timestamps) as $channelId) {
            $this->saveChannel((int) $channelId);
        }

        foreach (array_keys($this->offBandsTimestamps) as $channelId) {
            $this->saveOffBandsChannel((int) $channelId);
        }
    }

    private function buildTimestamp(Channel $channel, string $field, ?string $timeValue): ?Carbon
    {
        if ($timeValue === null || $timeValue === '') {
            return null;
        }

        $date = $channel->{$field}?->copy()->startOfDay() ?? $this->getChannelDate($channel);

        return $date->setTimeFromTimeString($timeValue);
    }

    private function getChannelDate(Channel $channel): Carbon
    {
        foreach (['customers_arrived_at', 'distribution_started_at', 'cleared_at'] as $field) {
            if ($channel->{$field}) {
                return $channel->{$field}->copy()->startOfDay();
            }
        }

        $dayNumber = $channel->isSpecial()
            ? (int) floor(($channel->id % 100) / 10)
            : (int) floor($channel->id / 100) % 10;

        return DateHelpers::psDayForCalendarYear($this->selectedCalendarYear, $dayNumber);
    }
};
?>

<div class="space-y-6 p-4 sm:px-8">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Manual Data Editor</h1>
        <flux:button wire:click="saveAll" variant="primary" icon="check">Save All</flux:button>
    </div>

    <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:field class="max-w-xs">
            <flux:label>Calendar Year</flux:label>
            <flux:select wire:model.live="selectedCalendarYear">
                @foreach ($this->calendarYears() as $year)
                    <flux:select.option :value="$year">{{ $year }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    </section>

    <section class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Channel</th>
                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Customers Arrived</th>
                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Distribution Started</th>
                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cleared</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->channels() as $channel)
                    <tr wire:key="editor-row-{{ $channel->id }}">
                        <td class="px-3 py-2 font-mono whitespace-nowrap text-zinc-500">
                            {{ $channel->id }}
                            <span class="block text-xs text-zinc-400">{{ $channel->getDescription() }}</span>
                        </td>
                        <td class="px-2 py-1">
                            <input
                                type="time"
                                step="1"
                                wire:model="timestamps.{{ $channel->id }}.customers_arrived_at"
                                wire:blur="saveChannel({{ $channel->id }})"
                                class="w-full rounded border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </td>
                        <td class="px-2 py-1">
                            <input
                                type="time"
                                step="1"
                                wire:model="timestamps.{{ $channel->id }}.distribution_started_at"
                                wire:blur="saveChannel({{ $channel->id }})"
                                class="w-full rounded border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </td>
                        <td class="px-2 py-1">
                            <input
                                type="time"
                                step="1"
                                wire:model="timestamps.{{ $channel->id }}.cleared_at"
                                wire:blur="saveChannel({{ $channel->id }})"
                                class="w-full rounded border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-zinc-500">
                            No standard channels found for {{ $selectedCalendarYear }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if (count($this->offBandsChannels()) > 0)
        <section class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                <h2 class="font-semibold">Off-Bands</h2>
            </div>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Channel</th>
                        <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cleared</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->offBandsChannels() as $channel)
                        <tr wire:key="offbands-row-{{ $channel->id }}">
                            <td class="px-3 py-2 font-mono whitespace-nowrap text-zinc-500">
                                {{ $channel->id }}
                                <span class="block text-xs text-zinc-400">{{ $channel->getDescription() }}</span>
                            </td>
                            <td class="px-2 py-1">
                                <input
                                    type="time"
                                    step="1"
                                    wire:model="offBandsTimestamps.{{ $channel->id }}.cleared_at"
                                    wire:blur="saveOffBandsChannel({{ $channel->id }})"
                                    class="w-full rounded border border-zinc-300 bg-white px-2 py-1 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <div class="flex justify-end">
        <flux:button wire:click="saveAll" variant="primary" icon="check">Save All</flux:button>
    </div>
</div>

