<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
    Cache::forget('entry-distributing');
    Cache::forget('entry-clearing');
    Cache::forget('entry-newt-minutes');
});

test('closed status shows volunteer pre-sale label for the next Thursday sale day', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 3, 12, 0, 0));

    Livewire::test('entry-status')
        ->assertSee('Next sale day: Thursday, May 7')
        ->assertSee('(volunteer pre-sale)')
        ->assertSeeHtml('sm:grid sm:grid-cols-4 sm:text-center')
        ->assertSeeHtml("x-bind:class=\"days() < 1 ? 'hidden sm:block' : 'flex sm:block'\"")
        ->assertSeeHtml("sm:hidden\" x-text=\"days() === 1 ? 'day' : 'days'\"")
        ->assertSeeHtml("sm:hidden\" x-text=\"hours() === 1 ? 'hour' : 'hours'\"")
        ->assertSeeHtml("sm:hidden\" x-text=\"minutes() === 1 ? 'minute' : 'minutes'\"")
        ->assertSeeHtml("sm:hidden\" x-text=\"seconds() === 1 ? 'second' : 'seconds'\"")
        ->assertSeeHtml('hidden sm:inline md:hidden">hrs</span>')
        ->assertSeeHtml('hidden sm:inline md:hidden">min</span>')
        ->assertSeeHtml('hidden sm:inline md:hidden">sec</span>')
        ->assertSeeHtml("x-text=\"days() === 1 ? 'day' : 'days'\"")
        ->assertDontSee('Wristband distribution beginning shortly...');
});

test('closed status shows starting shortly message instead of the clock within ten minutes of wristband distribution', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 7, 13, 55, 0));

    Livewire::test('entry-status')
        ->assertSeeHtml("We'll see you real soon!")
        ->assertSee('Wristband distribution for the volunteer pre-sale begins at 2:00 PM and the sale opens at 2:30 PM.')
        ->assertSee('The next public sale day is Friday, May 8. Wristband distribution begins at 6:30 AM and the sale opens at 9:00 AM.')
        ->assertSee('Wristband distribution beginning shortly...')
        ->assertDontSee('Days');
});

test('closed status still shows starting shortly message for up to fifteen minutes after wristband distribution should have started', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 7, 14, 12, 0));

    Livewire::test('entry-status')
        ->assertSeeHtml("We'll see you real soon!")
        ->assertSee('Wristband distribution for the volunteer pre-sale begins at 2:00 PM and the sale opens at 2:30 PM.')
        ->assertSee('The next public sale day is Friday, May 8. Wristband distribution begins at 6:30 AM and the sale opens at 9:00 AM.')
        ->assertSee('Wristband distribution beginning shortly...')
        ->assertDontSee('Days');
});

test('closed status can hide footer action links', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 3, 12, 0, 0));

    Livewire::test('entry-status', ['hideActions' => true])
        ->assertDontSee('Full hours')
        ->assertDontSee('Arrival & parking info')
        ->assertDontSee('Accessible arrival & parking info');

    Livewire::test('entry-status')
        ->assertSee('Full hours')
        ->assertSee('Arrival & parking info')
        ->assertSee('Accessible arrival & parking info');
});
