<?php

use App\Helpers\DateHelpers;
use App\Helpers\EntryTimeEstimator;
use App\Models\Channel;
use Carbon\Carbon;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
});

test('tower shows formatted estimated clear time when next group is defined', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 7, 10, 0, 0));

    $psYear = DateHelpers::psYearForDate(now());
    Channel::create(['id' => (int) sprintf('%d401', $psYear)])
        ->forceFill(['cleared_at' => '2026-05-07 09:50:00'])
        ->save();
    Channel::create(['id' => (int) sprintf('%d402', $psYear)]);

    $expectedEstimate = EntryTimeEstimator::getEstimate(2);
    $expectedText = $expectedEstimate->format('g:i a').' ('.$expectedEstimate->diffForHumans().')';

    $component = Livewire::test('tower');

    expect($component->get('nextGroupEstimatedClearTime'))->toBe($expectedText);

    $component
        ->assertSee('Group 2 estimated clear time '.$expectedText);
});

test('tower does not show estimated clear time when next group is not defined', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 7, 10, 0, 0));

    $psYear = DateHelpers::psYearForDate(now());
    Channel::create(['id' => (int) sprintf('%d401', $psYear)])
        ->forceFill(['cleared_at' => '2026-05-07 09:50:00'])
        ->save();

    $component = Livewire::test('tower');

    expect($component->get('nextGroupEstimatedClearTime'))->toBeNull();

    $component
        ->assertSee('Group 1 cleared')
        ->assertDontSee('estimated clear time');
});
