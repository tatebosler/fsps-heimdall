<?php

use App\Helpers\DateHelpers;
use App\Helpers\EntryTimeEstimator;
use App\Models\Channel;
use Carbon\Carbon;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
});

function createThursdayChannel(int $psYear, int $group, string $distributionStartedAt, ?string $clearedAt = null): Channel
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

test('status board does not duplicate already-distributed groups when future groups are included', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 7, 14, 45, 0));

    $psYear = DateHelpers::psYearForDate(now());

    createThursdayChannel($psYear, 0, '2026-05-07 14:00:00', '2026-05-07 14:29:00');

    foreach ([1, 2, 3, 4, 5, 6] as $group) {
        createThursdayChannel($psYear, $group, '2026-05-07 14:05:00');
    }

    $component = Livewire::test('status-board', ['includeFutureGroups' => true]);

    $groups = collect($component->get('channels'))
        ->map(fn (Channel $channel) => $channel->id % 100);

    expect($groups->filter(fn (int $group) => $group === 5)->count())->toBe(1);
});

test('estimate entry times remain forward-moving after six or more groups are cleared', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 7, 15, 16, 0));

    $psYear = DateHelpers::psYearForDate(now());

    createThursdayChannel($psYear, 0, '2026-05-07 14:00:00', '2026-05-07 14:29:00');
    createThursdayChannel($psYear, 1, '2026-05-07 14:05:00', '2026-05-07 14:40:00');
    createThursdayChannel($psYear, 2, '2026-05-07 14:10:00', '2026-05-07 14:47:00');
    createThursdayChannel($psYear, 3, '2026-05-07 14:15:00', '2026-05-07 14:55:00');
    createThursdayChannel($psYear, 4, '2026-05-07 14:20:00', '2026-05-07 15:02:00');
    createThursdayChannel($psYear, 5, '2026-05-07 14:25:00', '2026-05-07 15:10:00');

    createThursdayChannel($psYear, 6, '2026-05-07 14:30:00');
    createThursdayChannel($psYear, 7, '2026-05-07 14:35:00');
    createThursdayChannel($psYear, 8, '2026-05-07 14:40:00');

    EntryTimeEstimator::estimateEntryTimes();

    $group6 = Channel::find((int) sprintf('%d406', $psYear));
    $group7 = Channel::find((int) sprintf('%d407', $psYear));
    $group8 = Channel::find((int) sprintf('%d408', $psYear));

    expect($group6?->estimated_entry_at)->not->toBeNull();
    expect($group7?->estimated_entry_at)->not->toBeNull();
    expect($group8?->estimated_entry_at)->not->toBeNull();

    expect($group6->estimated_entry_at->greaterThanOrEqualTo(now()))->toBeTrue();
    expect($group7->estimated_entry_at->greaterThan($group6->estimated_entry_at))->toBeTrue();
    expect($group8->estimated_entry_at->greaterThan($group7->estimated_entry_at))->toBeTrue();
});
