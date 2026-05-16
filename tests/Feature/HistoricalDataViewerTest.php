<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use App\Models\User;
use Livewire\Livewire;

test('historical data viewer defaults to current year and only includes years with non-special channels', function () {
    $currentPsYear = DateHelpers::psYearForDate(now());
    $previousPsYear = $currentPsYear - 1;
    $twoYearsAgoPsYear = $currentPsYear - 2;

    Channel::create(['id' => sprintf('%d401', $previousPsYear)]);
    Channel::create(['id' => sprintf('%d901', $twoYearsAgoPsYear)]); // special channel, ignored for years list

    Livewire::test('historical-data-viewer')
        ->assertSet('selectedCalendarYear', now()->year)
        ->assertSee((string) now()->year)
        ->assertSee((string) (now()->year - 1))
        ->assertDontSee((string) (now()->year - 2));
});

test('historical data viewer shows subscriber count and duration metrics', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $channelId = sprintf('%d401', $psYear);

    $channel = Channel::create(['id' => $channelId]);
    $channel->forceFill([
        'customers_arrived_at' => '2026-05-07 09:30:00',
        'distribution_started_at' => '2026-05-07 09:45:00',
        'estimated_entry_at' => '2026-05-07 10:05:00',
        'original_estimated_entry_at' => '2026-05-07 10:00:00',
        'cleared_at' => '2026-05-07 10:15:00',
    ])->save();

    $users = User::factory()->count(2)->create();
    $channel->subscribers()->attach($users->pluck('id'));

    Livewire::test('historical-data-viewer')
        ->assertSee((string) $channelId)
        ->assertSee('2')
        ->assertSee('2026-05-07')
        ->assertSee('0:15:00')
        ->assertSee('0:45:00');
});

test('historical data viewer shows minimum wait time graph values', function () {
    $psYear = DateHelpers::psYearForDate(now());

    $channelZero = Channel::create(['id' => sprintf('%d400', $psYear)]);
    $channelOne = Channel::create(['id' => sprintf('%d401', $psYear)]);
    $channelTwo = Channel::create(['id' => sprintf('%d402', $psYear)]);

    $channelZero->forceFill([
        'cleared_at' => '2026-05-07 09:00:00',
    ])->save();

    $channelOne->forceFill([
        'distribution_started_at' => '2026-05-07 09:10:00',
        'cleared_at' => '2026-05-07 09:30:00',
    ])->save();

    $channelTwo->forceFill([
        'distribution_started_at' => '2026-05-07 09:35:00',
        'cleared_at' => '2026-05-07 09:50:00',
    ])->save();

    $graph = Livewire::test('historical-data-viewer')
        ->instance()
        ->graphsByDay()
        ->first()['minimum_wait_time'];

    expect($graph['series'][0])->toMatchArray(['group' => 0, 'value' => 0]);
    expect($graph['series'][1])->toMatchArray(['group' => 1, 'value' => 300]);
    expect($graph['series'][2])->toMatchArray(['group' => 2, 'value' => 0]);
});

test('historical data viewer shows graph subtitles for estimate error and max wait time', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $channelId = sprintf('%d401', $psYear);

    $channel = Channel::create(['id' => $channelId]);
    $channel->forceFill([
        'customers_arrived_at' => '2026-05-07 09:30:00',
        'distribution_started_at' => '2026-05-07 09:45:00',
        'estimated_entry_at' => '2026-05-07 10:05:00',
        'original_estimated_entry_at' => '2026-05-07 10:00:00',
        'cleared_at' => '2026-05-07 10:15:00',
    ])->save();

    User::factory()->count(2)->create()->each(fn ($user) => $channel->subscribers()->attach($user->id));

    Livewire::test('historical-data-viewer')
        ->assertSee('Negative times = group was cleared earlier than original estimate. Closer to zero is better.')
        ->assertSee('Measures the maximum wait time within each group, using customer arrival or distribution start time. Lower is better.')
        ->assertSee('Time from current group clear to next group distribution start. Group Zero and the last group are always 0.');
});

test('historical data viewer shows off bands times from 9x0 channels', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $offBandChannelId = sprintf('%d940', $psYear);

    $offBandChannel = Channel::create(['id' => $offBandChannelId]);
    $offBandChannel->forceFill([
        'cleared_at' => '2026-05-07 07:05:00',
    ])->save();

    $users = User::factory()->count(11)->create();
    $offBandChannel->subscribers()->attach($users->pluck('id'));

    Livewire::test('historical-data-viewer')
        ->assertSee('Off Bands Times')
        ->assertSee((string) $offBandChannelId)
        ->assertSee('11')
        ->assertSee('Thursday')
        ->assertSee('2026-05-07 07:05:00');
});

test('historical data viewer time-between graphs use group transition labels', function () {
    $psYear = DateHelpers::psYearForDate(now());

    $channelOne = Channel::create(['id' => sprintf('%d401', $psYear)]);
    $channelTwo = Channel::create(['id' => sprintf('%d402', $psYear)]);

    $channelOne->forceFill([
        'distribution_started_at' => '2026-05-07 09:00:00',
        'cleared_at' => '2026-05-07 09:15:00',
    ])->save();

    $channelTwo->forceFill([
        'distribution_started_at' => '2026-05-07 09:05:00',
        'cleared_at' => '2026-05-07 09:25:00',
    ])->save();

    $component = Livewire::test('historical-data-viewer')->instance();
    $dayGraphs = $component->graphsByDay()->first();

    expect($dayGraphs['time_between_clearance']['series'][0]['x_label'])->toBe('1 → 2');
    expect($dayGraphs['time_between_distribution']['series'][0]['x_label'])->toBe('1 → 2');
});
