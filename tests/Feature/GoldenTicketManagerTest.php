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

test('tickets array is populated for selected year and refreshed when year changes', function () {
    $currentPsYear = DateHelpers::psYearForDate(now());
    $previousPsYear = $currentPsYear - 1;

    $currentTicket = Ticket::factory()->create([
        'ps_year' => $currentPsYear,
        'first_name' => 'Alex',
        'last_name' => 'Able',
    ]);

    $previousTicket = Ticket::factory()->create([
        'ps_year' => $previousPsYear,
        'first_name' => 'Parker',
        'last_name' => 'Baker',
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->assertSet('tickets.0.id', $currentTicket->id)
        ->call('selectYear', $previousPsYear)
        ->assertSet('tickets.0.id', $previousTicket->id);
});

test('mark as scanned sets scanned timestamp and scanner name', function () {
    $ticket = Ticket::factory()->create([
        'scanned_at' => null,
        'scanned_by' => null,
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('markTicketAsScanned', $ticket->id);

    $ticket->refresh();

    expect($ticket->scanned_at)->not->toBeNull();
    expect($ticket->scanned_by)->toBe('GTManager');
});

test('undo scan clears scanned timestamp and scanner name', function () {
    $ticket = Ticket::factory()->create([
        'scanned_at' => now(),
        'scanned_by' => 'GTManager',
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('undoTicketScan', $ticket->id);

    $ticket->refresh();

    expect($ticket->scanned_at)->toBeNull();
    expect($ticket->scanned_by)->toBeNull();
});

test('revoke and reinstate set and clear revoked timestamp', function () {
    $ticket = Ticket::factory()->create([
        'revoked_at' => null,
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('revokeTicket', $ticket->id);

    $ticket->refresh();
    expect($ticket->revoked_at)->not->toBeNull();

    Livewire::test('gt.golden-ticket-manager')
        ->call('reinstateTicket', $ticket->id);

    $ticket->refresh();
    expect($ticket->revoked_at)->toBeNull();
});

test('delete ticket action requires confirmation and removes ticket after confirm', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test('gt.golden-ticket-manager')
        ->call('confirmDeleteTicket', $ticket->id)
        ->assertSet('ticketPendingDeletionId', $ticket->id)
        ->call('deleteConfirmedTicket');

    $this->assertModelMissing($ticket);
});
