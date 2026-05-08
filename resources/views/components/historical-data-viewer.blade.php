<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.admin')] #[Title('Historical Data Viewer')] class extends Component
{
    public int $selectedCalendarYear;

    public function mount(): void
    {
        $this->selectedCalendarYear = now()->year;

        if (! in_array($this->selectedCalendarYear, $this->calendarYears(), true)) {
            $this->selectedCalendarYear = $this->calendarYears()[0];
        }
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
            ->withCount('subscribers')
            ->orderBy('id')
            ->get();
    }

    public function offBandChannels(): Collection
    {
        $psYear = DateHelpers::psYearForDate(now()->setYear($this->selectedCalendarYear));

        return Channel::query()
            ->whereBetween('id', [
                $psYear * 1000,
                (($psYear + 1) * 1000) - 1,
            ])
            ->whereRaw('FLOOR(MOD(id, 1000) / 100) = 9')
            ->whereRaw('MOD(id, 10) = 0')
            ->withCount('subscribers')
            ->orderBy('id')
            ->get()
            ->map(function (Channel $channel): array {
                $dayNumber = (int) floor(($channel->id % 100) / 10);
                $date = DateHelpers::psDayForCalendarYear($this->selectedCalendarYear, $dayNumber);

                return [
                    'id' => $channel->id,
                    'subscribers_count' => $channel->subscribers_count,
                    'day_name' => DateHelpers::dayNumberToString($dayNumber),
                    'date_label' => $date->isoFormat('dddd, MMMM D, YYYY'),
                    'cleared_at' => $channel->cleared_at,
                ];
            })
            ->values();
    }

    public function graphsByDay(): Collection
    {
        return $this->channels()
            ->groupBy(fn (Channel $channel): int => (int) floor($channel->id / 100) % 10)
            ->sortKeys()
            ->map(function (Collection $channels, int $dayNumber): array {
                $date = DateHelpers::psDayForCalendarYear($this->selectedCalendarYear, $dayNumber);
                $dayName = DateHelpers::dayNumberToString($dayNumber);

                return [
                    'day_number' => $dayNumber,
                    'day_name' => $dayName,
                    'date_label' => $date->isoFormat('dddd, MMMM D, YYYY'),
                    'color' => config('ps.colors')[$dayName] ?? 'zinc',
                    'estimate_error' => $this->buildEstimateErrorGraph($channels),
                    'max_wait_time' => $this->buildMaxWaitTimeGraph($channels),
                    'time_between_clearance' => $this->buildConsecutiveDiffGraph($channels, 'cleared_at'),
                    'time_between_distribution' => $this->buildConsecutiveDiffGraph($channels, 'distribution_started_at'),
                ];
            })
            ->values();
    }

    public function estimateErrorSeconds(Channel $channel): ?int
    {
        if (! $channel->original_estimated_entry_at || ! $channel->cleared_at) {
            return null;
        }

        return $channel->original_estimated_entry_at->diffInSeconds($channel->cleared_at, false);
    }

    public function maxWaitTimeSeconds(Channel $channel): ?int
    {
        if (! $channel->distribution_started_at || ! $channel->cleared_at) {
            return null;
        }

        return $channel->distribution_started_at->diffInSeconds($channel->cleared_at, false);
    }

    public function formatTimestamp(mixed $timestamp): string
    {
        return $timestamp?->format('Y-m-d H:i:s') ?? 'N/A';
    }

    public function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'N/A';
        }

        $sign = $seconds < 0 ? '-' : '';
        $abs = abs($seconds);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);
        $remainingSeconds = $abs % 60;

        return sprintf('%s%d:%02d:%02d', $sign, $hours, $minutes, $remainingSeconds);
    }

    private function buildEstimateErrorGraph(Collection $channels): array
    {
        $rows = $channels
            ->map(function (Channel $channel): ?array {
                $seconds = $this->estimateErrorSeconds($channel);

                if ($seconds === null) {
                    return null;
                }

                return [
                    'group' => (int) ($channel->id % 100),
                    'x_label' => (string) ((int) ($channel->id % 100)),
                    'value' => $seconds,
                    'value_min' => round($seconds / 60, 2),
                ];
            })
            ->filter()
            ->values();

        return [
            'series' => $rows->all(),
            'stats' => $this->calculateStats($rows->pluck('value')),
            'x_field' => 'group',
            'tooltip_heading_field' => 'group',
            'tick_count' => 10,
        ];
    }

    private function buildMaxWaitTimeGraph(Collection $channels): array
    {
        $rows = $channels
            ->map(function (Channel $channel): ?array {
                $seconds = $this->maxWaitTimeSeconds($channel);

                if ($seconds === null) {
                    return null;
                }

                return [
                    'group' => (int) ($channel->id % 100),
                    'x_label' => (string) ((int) ($channel->id % 100)),
                    'value' => $seconds,
                    'value_min' => round($seconds / 60, 2),
                ];
            })
            ->filter()
            ->values();

        return [
            'series' => $rows->all(),
            'stats' => $this->calculateStats($rows->pluck('value')),
            'x_field' => 'group',
            'tooltip_heading_field' => 'group',
            'tick_count' => 10,
        ];
    }

    private function buildConsecutiveDiffGraph(Collection $channels, string $timestampField): array
    {
        $ordered = $channels->sortBy('id')->values();
        $rows = collect();

        foreach ($ordered as $index => $currentChannel) {
            if ($index === 0) {
                continue;
            }

            $previousChannel = $ordered[$index - 1];
            $currentTimestamp = $currentChannel->{$timestampField};
            $previousTimestamp = $previousChannel->{$timestampField};

            if (! $currentTimestamp || ! $previousTimestamp) {
                continue;
            }

            $seconds = $previousTimestamp->diffInSeconds($currentTimestamp, false);
            $previousGroup = (int) ($previousChannel->id % 100);
            $currentGroup = (int) ($currentChannel->id % 100);

            $rows->push([
                'group' => $currentGroup,
                'x_label' => sprintf('%d -> %d', $previousGroup, $currentGroup),
                'value' => $seconds,
                'value_min' => round($seconds / 60, 2),
            ]);
        }

        return [
            'series' => $rows->values()->all(),
            'stats' => $this->calculateStats($rows->pluck('value')),
            'x_field' => 'x_label',
            'tooltip_heading_field' => 'x_label',
            'tick_count' => $this->transitionTickCount($rows->count()),
        ];
    }

    private function transitionTickCount(int $pointCount): int
    {
        if ($pointCount <= 6) {
            return max(2, $pointCount);
        }

        if ($pointCount <= 12) {
            return 6;
        }

        return 5;
    }

    private function calculateStats(Collection $values): array
    {
        if ($values->isEmpty()) {
            return [
                'min' => null,
                'median' => null,
                'average' => null,
                'p90' => null,
                'max' => null,
            ];
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $median = $count % 2 === 1
            ? (int) $sorted[intdiv($count, 2)]
            : (int) round(($sorted[($count / 2) - 1] + $sorted[$count / 2]) / 2);
        $p90Index = max(0, (int) ceil($count * 0.9) - 1);

        return [
            'min' => (int) $sorted->first(),
            'median' => $median,
            'average' => (int) round($values->avg()),
            'p90' => (int) $sorted[$p90Index],
            'max' => (int) $sorted->last(),
        ];
    }

    public function downloadCsv()
    {
        $channels = $this->channels();

        $csv = "id_5d,group_distribution_start,estimated_entry,actual_entry,original_estimated_entry\n";

        foreach ($channels as $channel) {
            $row = [
                $channel->id,
                $channel->distribution_started_at?->format('Y-m-d H:i:s') ?? '',
                $channel->estimated_entry_at?->format('Y-m-d H:i:s') ?? '',
                $channel->cleared_at?->format('Y-m-d H:i:s') ?? '',
                $channel->original_estimated_entry_at?->format('Y-m-d H:i:s') ?? '',
            ];

            $csv .= '"'.implode('","', $row)."\"\n";
        }

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            "historical-data-{$this->selectedCalendarYear}.csv",
            ['Content-Type' => 'text/csv']
        );
    }
};
?>

<div class="space-y-6">
    <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-end gap-4">
            <div class="flex-1">
                <flux:field>
                    <flux:label>Calendar Year</flux:label>
                    <flux:select wire:model.live="selectedCalendarYear">
                        @foreach ($this->calendarYears() as $year)
                            <flux:select.option :value="$year">{{ $year }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            <flux:button wire:click="downloadCsv" icon="arrow-down">
                Download CSV
            </flux:button>
        </div>
    </section>

    <section class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Channel ID</th>
                    <th class="px-3 py-2 text-left font-semibold">Subscribers</th>
                    <th class="px-3 py-2 text-left font-semibold">Customers Arrived</th>
                    <th class="px-3 py-2 text-left font-semibold">Distribution Started</th>
                    <th class="px-3 py-2 text-left font-semibold">Current Estimated Entry</th>
                    <th class="px-3 py-2 text-left font-semibold">Original Estimated Entry</th>
                    <th class="px-3 py-2 text-left font-semibold">Cleared</th>
                    <th class="px-3 py-2 text-left font-semibold">Estimate Error</th>
                    <th class="px-3 py-2 text-left font-semibold">Max Wait Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->channels() as $channel)
                    <tr wire:key="channel-row-{{ $channel->id }}">
                        <td class="px-3 py-2">{{ $channel->id }}</td>
                        <td class="px-3 py-2">{{ $channel->subscribers_count }}</td>
                        <td class="px-3 py-2">{{ $this->formatTimestamp($channel->customers_arrived_at) }}</td>
                        <td class="px-3 py-2">{{ $this->formatTimestamp($channel->distribution_started_at) }}</td>
                        <td class="px-3 py-2">{{ $this->formatTimestamp($channel->estimated_entry_at) }}</td>
                        <td class="px-3 py-2">{{ $this->formatTimestamp($channel->original_estimated_entry_at) }}</td>
                        <td class="px-3 py-2">{{ $this->formatTimestamp($channel->cleared_at) }}</td>
                        <td class="px-3 py-2">{{ $this->formatDuration($this->estimateErrorSeconds($channel)) }}</td>
                        <td class="px-3 py-2">{{ $this->formatDuration($this->maxWaitTimeSeconds($channel)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-4 text-center text-zinc-500">No channels found for {{ $selectedCalendarYear }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
            <h2 class="text-base font-semibold">Off Bands Times</h2>
        </div>

        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Channel ID</th>
                    <th class="px-3 py-2 text-left font-semibold">Subscribers</th>
                    <th class="px-3 py-2 text-left font-semibold">Day</th>
                    <th class="px-3 py-2 text-left font-semibold">Date</th>
                    <th class="px-3 py-2 text-left font-semibold">Cleared At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->offBandChannels() as $offBandChannel)
                    <tr wire:key="off-band-row-{{ $offBandChannel['id'] }}">
                        <td class="px-3 py-2">{{ $offBandChannel['id'] }}</td>
                        <td class="px-3 py-2">{{ $offBandChannel['subscribers_count'] }}</td>
                        <td class="px-3 py-2">{{ $offBandChannel['day_name'] }}</td>
                        <td class="px-3 py-2">{{ $offBandChannel['date_label'] }}</td>
                        <td class="px-3 py-2">{{ $this->formatTimestamp($offBandChannel['cleared_at']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-4 text-center text-zinc-500">No off bands channels found for {{ $selectedCalendarYear }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="space-y-8">
        @foreach ($this->graphsByDay() as $day)
            <article wire:key="day-graphs-{{ $day['day_number'] }}" class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">{{ $day['date_label'] }}</h2>

                @php
                    $graphs = [
                        'Estimate Error' => $day['estimate_error'],
                        'Max Wait Time' => $day['max_wait_time'],
                        'Time Between Group Clearance' => $day['time_between_clearance'],
                        'Time Between Group Distribution' => $day['time_between_distribution'],
                    ];
                @endphp

                <div class="grid gap-6 lg:grid-cols-2">
                    @foreach ($graphs as $title => $graph)
                        <div wire:key="{{ str($title)->slug() }}-{{ $day['day_number'] }}" class="space-y-3">
                            <h3 class="font-medium">{{ $title }}</h3>

                            @if (count($graph['series']) >= 2)
                                <flux:chart :value="$graph['series']" class="h-64 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                    <flux:chart.svg>
                                        <flux:chart.axis axis="x" :field="$graph['x_field']" :tick-count="$graph['tick_count']">
                                            <flux:chart.axis.line />
                                            <flux:chart.axis.tick />
                                        </flux:chart.axis>

                                        <flux:chart.axis axis="y" tick-suffix=" min">
                                            <flux:chart.axis.grid />
                                            <flux:chart.axis.tick />
                                        </flux:chart.axis>

                                        <flux:chart.line field="value_min" class="text-{{ $day['color'] }}-500" />
                                        <flux:chart.point field="value_min" class="text-{{ $day['color'] }}-600" />
                                        <flux:chart.cursor />
                                    </flux:chart.svg>

                                    <flux:chart.tooltip>
                                        <flux:chart.tooltip.heading :field="$graph['tooltip_heading_field']" />
                                        <flux:chart.tooltip.value field="value_min" label="Value" suffix=" min" />
                                    </flux:chart.tooltip>
                                </flux:chart>
                            @else
                                <div class="flex h-64 items-center justify-center rounded-lg border border-dashed border-zinc-300 text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                                    Not enough data points to render this graph.
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                                <div class="rounded bg-zinc-100 p-2 text-center dark:bg-zinc-800">
                                    <div class="text-xs uppercase text-zinc-500">Min</div>
                                    <div class="font-medium">{{ $this->formatDuration($graph['stats']['min']) }}</div>
                                </div>
                                <div class="rounded bg-zinc-100 p-2 text-center dark:bg-zinc-800">
                                    <div class="text-xs uppercase text-zinc-500">Median</div>
                                    <div class="font-medium">{{ $this->formatDuration($graph['stats']['median']) }}</div>
                                </div>
                                <div class="rounded bg-zinc-100 p-2 text-center dark:bg-zinc-800">
                                    <div class="text-xs uppercase text-zinc-500">Average</div>
                                    <div class="font-medium">{{ $this->formatDuration($graph['stats']['average']) }}</div>
                                </div>
                                <div class="rounded bg-zinc-100 p-2 text-center dark:bg-zinc-800">
                                    <div class="text-xs uppercase text-zinc-500">P90</div>
                                    <div class="font-medium">{{ $this->formatDuration($graph['stats']['p90']) }}</div>
                                </div>
                                <div class="rounded bg-zinc-100 p-2 text-center dark:bg-zinc-800">
                                    <div class="text-xs uppercase text-zinc-500">Max</div>
                                    <div class="font-medium">{{ $this->formatDuration($graph['stats']['max']) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>
</div>
