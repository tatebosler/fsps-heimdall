<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

test('wristband booth distributes the next group and sets distribution_started_at timestamps', function () {
    Date::setTestNow('2026-05-07 10:00:00'); // Thursday

    Livewire::test('wristband-booth')
        ->call('distribute');

    $psYear = DateHelpers::psYearForDate(now());
    $weekday = date('N');
    $channelId = sprintf('%s%s%02d', $psYear, $weekday, 1);

    $channel = Channel::find($channelId);

    expect($channel)->not->toBeNull();
    expect($channel->distribution_started_at)->not->toBeNull();
    expect($channel->distribution_started_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'));
});
