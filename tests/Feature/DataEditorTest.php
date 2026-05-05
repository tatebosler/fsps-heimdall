<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use Livewire\Livewire;

test('data editor renders with year selector and table', function () {
    $psYear = DateHelpers::psYearForDate(now());
    Channel::create(['id' => sprintf('%d401', $psYear)]);

    Livewire::test('data-editor')
        ->assertSet('selectedCalendarYear', now()->year)
        ->assertSee((string) now()->year)
        ->assertSee('Customers Arrived')
        ->assertSee('Distribution Started')
        ->assertSee('Cleared');
});

test('data editor only shows standard channels, not special channels in main table', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $standardId = (int) sprintf('%d401', $psYear);
    $specialId = (int) sprintf('%d901', $psYear);

    Channel::create(['id' => $standardId]);
    Channel::create(['id' => $specialId]);

    $component = Livewire::test('data-editor');

    expect($component->get('timestamps'))->toHaveKey($standardId);
    expect($component->get('timestamps'))->not->toHaveKey($specialId);
});

test('data editor loads existing timestamps as time strings', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $channelId = (int) sprintf('%d401', $psYear);

    Channel::create(['id' => $channelId])->forceFill([
        'distribution_started_at' => '2026-05-07 09:45:00',
        'cleared_at' => '2026-05-07 10:15:00',
    ])->save();

    Livewire::test('data-editor')
        ->assertSet("timestamps.{$channelId}.distribution_started_at", '09:45:00')
        ->assertSet("timestamps.{$channelId}.cleared_at", '10:15:00')
        ->assertSet("timestamps.{$channelId}.customers_arrived_at", null);
});

test('saveChannel persists timestamp changes using existing date for that field', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $channelId = (int) sprintf('%d401', $psYear);

    Channel::create(['id' => $channelId])->forceFill([
        'customers_arrived_at' => '2026-05-07 08:00:00',
        'distribution_started_at' => '2026-05-07 09:30:00',
        'cleared_at' => '2026-05-07 10:00:00',
    ])->save();

    Livewire::test('data-editor')
        ->set("timestamps.{$channelId}.customers_arrived_at", '08:30:00')
        ->set("timestamps.{$channelId}.distribution_started_at", '09:45:00')
        ->set("timestamps.{$channelId}.cleared_at", '10:15:00')
        ->call('saveChannel', $channelId);

    $channel = Channel::find($channelId);
    expect($channel->customers_arrived_at->format('Y-m-d H:i:s'))->toBe('2026-05-07 08:30:00');
    expect($channel->distribution_started_at->format('Y-m-d H:i:s'))->toBe('2026-05-07 09:45:00');
    expect($channel->cleared_at->format('Y-m-d H:i:s'))->toBe('2026-05-07 10:15:00');
});

test('saveChannel sets timestamps to null when input is cleared', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $channelId = (int) sprintf('%d401', $psYear);

    Channel::create(['id' => $channelId])->forceFill([
        'distribution_started_at' => '2026-05-07 09:45:00',
        'cleared_at' => '2026-05-07 10:15:00',
    ])->save();

    Livewire::test('data-editor')
        ->set("timestamps.{$channelId}.distribution_started_at", '')
        ->set("timestamps.{$channelId}.cleared_at", '')
        ->call('saveChannel', $channelId);

    $channel = Channel::find($channelId);
    expect($channel->distribution_started_at)->toBeNull();
    expect($channel->cleared_at)->toBeNull();
});

test('saveChannel ignores special channels', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $specialId = (int) sprintf('%d901', $psYear);
    Channel::create(['id' => $specialId]);

    Livewire::test('data-editor')
        ->set("timestamps.{$specialId}.cleared_at", '10:00:00')
        ->call('saveChannel', $specialId);

    $channel = Channel::find($specialId);
    expect($channel->cleared_at)->toBeNull();
});

test('saveAll persists all standard channels in the selected year', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $channelA = (int) sprintf('%d401', $psYear);
    $channelB = (int) sprintf('%d402', $psYear);

    Channel::create(['id' => $channelA])->forceFill(['cleared_at' => '2026-05-07 10:00:00'])->save();
    Channel::create(['id' => $channelB])->forceFill(['cleared_at' => '2026-05-07 10:00:00'])->save();

    Livewire::test('data-editor')
        ->set("timestamps.{$channelA}.cleared_at", '10:05:00')
        ->set("timestamps.{$channelB}.cleared_at", '10:20:00')
        ->call('saveAll');

    expect(Channel::find($channelA)->cleared_at->format('H:i:s'))->toBe('10:05:00');
    expect(Channel::find($channelB)->cleared_at->format('H:i:s'))->toBe('10:20:00');
});

test('changing year reloads timestamps for the new year', function () {
    $currentPsYear = DateHelpers::psYearForDate(now());
    $previousCalendarYear = now()->year - 1;
    $previousPsYear = $currentPsYear - 1;

    $currentChannelId = (int) sprintf('%d401', $currentPsYear);
    $previousChannelId = (int) sprintf('%d401', $previousPsYear);

    Channel::create(['id' => $currentChannelId])->forceFill(['cleared_at' => '2026-05-07 10:00:00'])->save();
    Channel::create(['id' => $previousChannelId])->forceFill(['cleared_at' => '2025-05-03 10:30:00'])->save();

    Livewire::test('data-editor')
        ->assertSet('selectedCalendarYear', now()->year)
        ->assertSet("timestamps.{$currentChannelId}.cleared_at", '10:00:00')
        ->set('selectedCalendarYear', $previousCalendarYear)
        ->assertSet("timestamps.{$previousChannelId}.cleared_at", '10:30:00');
});

test('off-bands channels are loaded into offBandsTimestamps', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $offBandsId = (int) sprintf('%d940', $psYear); // format: {psYear}9{weekday}0

    Channel::create(['id' => $offBandsId])->forceFill(['cleared_at' => '2026-05-07 11:00:00'])->save();

    Livewire::test('data-editor')
        ->assertSet("offBandsTimestamps.{$offBandsId}.cleared_at", '11:00:00');
});

test('saveOffBandsChannel persists cleared_at for off-bands channels', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $offBandsId = (int) sprintf('%d940', $psYear);

    Channel::create(['id' => $offBandsId])->forceFill(['cleared_at' => '2026-05-07 11:00:00'])->save();

    Livewire::test('data-editor')
        ->set("offBandsTimestamps.{$offBandsId}.cleared_at", '11:30:00')
        ->call('saveOffBandsChannel', $offBandsId);

    expect(Channel::find($offBandsId)->cleared_at->format('H:i:s'))->toBe('11:30:00');
});

test('saveOffBandsChannel can clear the off-bands cleared_at timestamp', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $offBandsId = (int) sprintf('%d940', $psYear);

    Channel::create(['id' => $offBandsId])->forceFill(['cleared_at' => '2026-05-07 11:00:00'])->save();

    Livewire::test('data-editor')
        ->set("offBandsTimestamps.{$offBandsId}.cleared_at", '')
        ->call('saveOffBandsChannel', $offBandsId);

    expect(Channel::find($offBandsId)->cleared_at)->toBeNull();
});

test('saveOffBandsChannel ignores standard channels', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $standardId = (int) sprintf('%d401', $psYear);
    Channel::create(['id' => $standardId]);

    Livewire::test('data-editor')
        ->set("offBandsTimestamps.{$standardId}.cleared_at", '10:00:00')
        ->call('saveOffBandsChannel', $standardId);

    expect(Channel::find($standardId)->cleared_at)->toBeNull();
});
