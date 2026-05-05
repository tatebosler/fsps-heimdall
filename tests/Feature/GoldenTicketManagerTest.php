<?php

use App\Helpers\DateHelpers;
use App\Models\Ticket;
use Livewire\Livewire;

test('golden ticket manager renders for current year', function () {
    $psYear = DateHelpers::psYearForDate(now());

    Livewire::test('gt.golden-ticket-manager')
        ->assertSet('selectedPsYear', $psYear)
        ->assertOk();
});

test('year dropdown is hidden when tickets exist in only one year', function () {
    $psYear = DateHelpers::psYearForDate(now());
    Ticket::factory()->create(['ps_year' => $psYear]);

    Livewire::test('gt.golden-ticket-manager')
        ->assertDontSee((string) DateHelpers::calendarYearForPsYear($psYear - 1));
});

test('year dropdown appears when tickets exist in multiple years', function () {
    $currentPsYear = DateHelpers::psYearForDate(now());
    $previousPsYear = $currentPsYear - 1;

    Ticket::factory()->create(['ps_year' => $currentPsYear]);
    Ticket::factory()->create(['ps_year' => $previousPsYear]);

    Livewire::test('gt.golden-ticket-manager')
        ->assertSee((string) DateHelpers::calendarYearForPsYear($currentPsYear))
        ->assertSee((string) DateHelpers::calendarYearForPsYear($previousPsYear));
});

test('selecting a year updates the selected ps year', function () {
    $currentPsYear = DateHelpers::psYearForDate(now());
    $previousPsYear = $currentPsYear - 1;

    Ticket::factory()->create(['ps_year' => $previousPsYear]);

    Livewire::test('gt.golden-ticket-manager')
        ->assertSet('selectedPsYear', $currentPsYear)
        ->call('selectYear', $previousPsYear)
        ->assertSet('selectedPsYear', $previousPsYear);
});
