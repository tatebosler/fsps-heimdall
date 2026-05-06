<?php

use App\Helpers\DateHelpers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cache:clear')
    ->everyMinute()
    ->when(fn (): bool => DateHelpers::saleHasJustClosed(now()));
