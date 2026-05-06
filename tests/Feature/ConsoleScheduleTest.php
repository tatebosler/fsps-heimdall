<?php

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schedule;

afterEach(function () {
    Date::setTestNow();
});

function scheduledCacheClearEvent()
{
    app(Kernel::class)->bootstrap();

    return collect(Schedule::events())
        ->first(fn ($scheduledEvent) => str_contains($scheduledEvent->command, 'cache:clear'));
}

test('cache clear is scheduled every minute during the sale close reset window', function () {
    Date::setTestNow(Carbon::create(2026, 5, 7, 20, 31, 0));

    $event = scheduledCacheClearEvent();

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('* * * * *');
    expect($event->isDue(app()))->toBeTrue();
    expect($event->filtersPass(app()))->toBeTrue();
});

test('cache clear is skipped outside the sale close reset window', function () {
    Date::setTestNow(Carbon::create(2026, 5, 7, 20, 46, 0));

    $event = scheduledCacheClearEvent();

    expect($event)->not->toBeNull();
    expect($event->isDue(app()))->toBeTrue();
    expect($event->filtersPass(app()))->toBeFalse();
});
