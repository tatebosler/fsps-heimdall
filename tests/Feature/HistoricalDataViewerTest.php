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
        ->assertSee('0:30:00');
});

test('historical data viewer shows off bands times from 9x0 channels', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $offBandChannelId = sprintf('%d940', $psYear);

    $offBandChannel = Channel::create(['id' => $offBandChannelId]);
    $offBandChannel->forceFill([
        'cleared_at' => '2026-05-07 07:05:00',
    ])->save();

    Livewire::test('historical-data-viewer')
        ->assertSee('Off Bands Times')
        ->assertSee((string) $offBandChannelId)
        ->assertSee('Thursday')
        ->assertSee('2026-05-07 07:05:00');
});
