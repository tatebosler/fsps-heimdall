<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use App\Models\Estimate;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

function createReplayChannel(int $psYear, int $group, string $distributionStartedAt, ?string $clearedAt = null): Channel
{
    $channel = Channel::create([
        'id' => (int) sprintf('%d4%02d', $psYear, $group),
        'distribution_started_at' => $distributionStartedAt,
    ]);

    if ($clearedAt !== null) {
        $channel->forceFill(['cleared_at' => $clearedAt])->save();
    }

    return $channel;
}

test('command replays target day events and rebuilds estimates', function () {
    $targetDate = Carbon::create(2026, 5, 7, 12, 0, 0);
    $psYear = DateHelpers::psYearForDate($targetDate);

    createReplayChannel($psYear, 0, '2026-05-07 14:00:00', '2026-05-07 14:29:00');
    createReplayChannel($psYear, 1, '2026-05-07 14:05:00', '2026-05-07 14:40:00');
    createReplayChannel($psYear, 2, '2026-05-07 14:10:00', '2026-05-07 14:47:00');
    createReplayChannel($psYear, 3, '2026-05-07 14:15:00', '2026-05-07 14:55:00');
    createReplayChannel($psYear, 4, '2026-05-07 14:20:00', '2026-05-07 15:02:00');
    createReplayChannel($psYear, 5, '2026-05-07 14:25:00', '2026-05-07 15:10:00');
    createReplayChannel($psYear, 6, '2026-05-07 14:30:00');

    Channel::create([
        'id' => (int) sprintf('%d940', $psYear),
        'distribution_started_at' => '2026-05-07 15:50:00',
        'cleared_at' => '2026-05-07 16:00:00',
    ]);

    $group6Id = (int) sprintf('%d406', $psYear);
    $group8Id = (int) sprintf('%d408', $psYear);
    Channel::create([
        'id' => $group8Id,
        'estimated_entry_at' => '2026-05-07 17:00:00',
        'original_estimated_entry_at' => '2026-05-07 17:00:00',
    ]);

    Channel::where('id', $group6Id)->update([
        'estimated_entry_at' => '2026-05-07 14:00:00',
        'original_estimated_entry_at' => '2026-05-07 14:00:00',
    ]);
    Estimate::create([
        'channel_id' => $group6Id,
        'estimated_entry_at' => '2026-05-07 14:00:00',
    ]);
    Estimate::create([
        'channel_id' => $group8Id,
        'estimated_entry_at' => '2026-05-07 17:00:00',
    ]);

    $this->artisan('app:resimulate-entry-estimates', ['date' => '2026-05-07'])
        ->assertSuccessful()
        ->expectsOutputToContain('Re-simulation complete.');

    $group6 = Channel::find($group6Id);
    $group8 = Channel::find($group8Id);

    expect($group6)->not->toBeNull();
    expect($group8)->not->toBeNull();
    expect($group6->estimated_entry_at)->not->toBeNull();
    expect($group6->original_estimated_entry_at)->not->toBeNull();
    expect($group6->estimated_entry_at->greaterThan(Carbon::parse('2026-05-07 15:10:00')))->toBeTrue();
    expect(Estimate::where('channel_id', $group6Id)->count())->toBeGreaterThan(0);
    expect($group8->estimated_entry_at)->toBeNull();
    expect($group8->original_estimated_entry_at)->toBeNull();
    expect(Estimate::where('channel_id', $group8Id)->count())->toBe(0);
});

test('command rejects invalid date argument', function () {
    $this->artisan('app:resimulate-entry-estimates', ['date' => '05-07-2026'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid date. Use Y-m-d format');
});
