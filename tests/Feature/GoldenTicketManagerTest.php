<?php

use App\Helpers\DateHelpers;
use App\Mail\GoldenTicket;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;
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

test('ticket badges render each priority designation label', function () {
    $activeYear = DateHelpers::psYearForDate(now());

    Ticket::factory()->createMany([
        [
            'ps_year' => $activeYear,
            'group_zero' => true,
            'first_name' => 'Shift',
            'last_name' => 'Start',
            'shifts' => [
                ['job' => 'Cashier', 'start' => '2025-05-08 17:00:00', 'end' => '2025-05-08 20:00:00'],
            ],
        ],
        [
            'ps_year' => $activeYear,
            'group_zero' => true,
            'first_name' => 'Shift',
            'last_name' => 'End',
            'shifts' => [
                ['job' => 'Greeter', 'start' => '2025-05-08 12:00:00', 'end' => '2025-05-08 14:15:00'],
            ],
        ],
        [
            'ps_year' => $activeYear,
            'group_zero' => true,
            'first_name' => 'Manual',
            'last_name' => 'GroupZero',
            'shifts' => [],
        ],
        [
            'ps_year' => $activeYear,
            'group_zero' => false,
            'first_name' => 'Standard',
            'last_name' => 'Ticket',
            'shifts' => [],
        ],
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->assertSeeHtml('Group&nbsp;Zero&nbsp;(S)')
        ->assertSeeHtml('Group&nbsp;Zero&nbsp;(E)')
        ->assertSeeHtml('Group&nbsp;Zero&nbsp;(M)')
        ->assertSee('Standard');
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

test('create ticket action sets working ticket to null and can create a new ticket', function () {
    Livewire::test('gt.golden-ticket-manager')
        ->call('openCreateTicketModal')
        ->assertSet('workingTicket', null)
        ->set('ticketFirstName', 'Ada')
        ->set('ticketLastName', 'Lovelace')
        ->set('ticketEmail', 'ada@example.com')
        ->set('ticketPhone', '555-111-2222')
        ->set('ticketZip', '10001')
        ->set('ticketPriorityAdmission', true)
        ->call('saveTicket');

    $createdTicket = Ticket::query()->latest('id')->first();

    expect($createdTicket)->not->toBeNull();
    expect($createdTicket->first_name)->toBe('Ada');
    expect($createdTicket->last_name)->toBe('Lovelace');
    expect($createdTicket->email)->toBe('ada@example.com');
    expect($createdTicket->phone)->toBe('555-111-2222');
    expect($createdTicket->zip)->toBe('10001');
    expect($createdTicket->group_zero)->toBeTrue();
});

test('creating a standard ticket uses a serial that starts between 1 and 8', function () {
    Livewire::test('gt.golden-ticket-manager')
        ->call('openCreateTicketModal')
        ->set('ticketFirstName', 'Sam')
        ->set('ticketLastName', 'Standard')
        ->set('ticketPriorityAdmission', false)
        ->call('saveTicket');

    $createdTicket = Ticket::query()->latest('id')->first();

    expect($createdTicket)->not->toBeNull();
    expect($createdTicket->serial)->toHaveLength(6);
    expect((int) substr($createdTicket->serial, 0, 1))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(8);
});

test('create anonymous tickets rounds quantity up to nearest multiple of four and uses 9 prefix', function () {
    $activeYear = DateHelpers::psYearForDate(now());

    Livewire::test('gt.golden-ticket-manager')
        ->set('selectedPsYear', $activeYear)
        ->call('openCreateAnonymousTicketsModal')
        ->set('anonymousTicketQuantity', 5)
        ->call('createAnonymousTickets')
        ->assertSet('anonymousTicketQuantity', 4);

    $createdTickets = Ticket::query()
        ->where('ps_year', $activeYear)
        ->orderBy('id')
        ->get();

    expect($createdTickets)->toHaveCount(8);

    foreach ($createdTickets as $ticket) {
        expect($ticket->serial)->toHaveLength(6);
        expect($ticket->serial)->toStartWith('9');
        expect($ticket->group_zero)->toBeFalse();
        expect($ticket->first_name)->toBeNull();
        expect($ticket->last_name)->toBeNull();
        expect($ticket->email)->toBeNull();
        expect($ticket->phone)->toBeNull();
        expect($ticket->zip)->toBeNull();
    }
});

test('edit ticket action sets working ticket and updates editable fields', function () {
    $ticket = Ticket::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'old@example.com',
        'phone' => '555-0000',
        'zip' => '60606',
        'group_zero' => false,
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('openEditTicketModal', $ticket->id)
        ->assertSet('workingTicket.id', $ticket->id)
        ->set('ticketFirstName', 'New')
        ->set('ticketLastName', 'Person')
        ->set('ticketEmail', 'new@example.com')
        ->set('ticketPhone', '555-9999')
        ->set('ticketZip', '73301')
        ->set('ticketPriorityAdmission', true)
        ->call('saveTicket')
        ->assertSet('workingTicket', null);

    $ticket->refresh();

    expect($ticket->first_name)->toBe('New');
    expect($ticket->last_name)->toBe('Person');
    expect($ticket->email)->toBe('new@example.com');
    expect($ticket->phone)->toBe('555-9999');
    expect($ticket->zip)->toBe('73301');
    expect($ticket->group_zero)->toBeTrue();
});

test('delete all tickets action only deletes tickets for the active year after confirmation', function () {
    $activeYear = DateHelpers::psYearForDate(now());
    $otherYear = $activeYear - 1;

    Ticket::factory()->count(2)->create(['ps_year' => $activeYear]);
    Ticket::factory()->count(1)->create(['ps_year' => $otherYear]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('openDeleteAllTicketsModal')
        ->assertSet('yearTicketPendingDeletionCount', 2)
        ->call('deleteAllTicketsForSelectedYear');

    expect(Ticket::query()->where('ps_year', $activeYear)->count())->toBe(0);
    expect(Ticket::query()->where('ps_year', $otherYear)->count())->toBe(1);
});

test('download scan report action returns a csv download', function () {
    $activeYear = DateHelpers::psYearForDate(now());
    $calendarYear = DateHelpers::calendarYearForPsYear($activeYear);

    Ticket::factory()->create([
        'ps_year' => $activeYear,
        'vlid' => null,
        'serial' => '012345',
        'first_name' => 'Pat',
        'last_name' => 'Taylor',
        'email' => 'pat@example.com',
        'phone' => '555-222-3333',
        'zip' => '12345',
        'sent_at' => now()->subHour(),
        'scanned_at' => now(),
        'scanned_by' => 'GTManager',
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('downloadScanReport')
        ->assertFileDownloaded("golden-ticket-scan-report-{$calendarYear}.csv");
});

test('send all staged tickets queues unsent ticket emails and updates sent_at', function () {
    Mail::fake();

    $activeYear = DateHelpers::psYearForDate(now());

    $unsentTicketOne = Ticket::factory()->create([
        'ps_year' => $activeYear,
        'email' => 'one@example.com',
        'sent_at' => null,
    ]);

    $unsentTicketTwo = Ticket::factory()->create([
        'ps_year' => $activeYear,
        'email' => 'two@example.com',
        'sent_at' => null,
    ]);

    $alreadySentTicket = Ticket::factory()->create([
        'ps_year' => $activeYear,
        'email' => 'sent@example.com',
        'sent_at' => now()->subHour(),
    ]);

    $ticketWithoutEmail = Ticket::factory()->create([
        'ps_year' => $activeYear,
        'email' => null,
        'sent_at' => null,
    ]);

    $revokedTicket = Ticket::factory()->create([
        'ps_year' => $activeYear,
        'email' => 'revoked@example.com',
        'sent_at' => null,
        'revoked_at' => now()->subMinute(),
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('sendAllStagedTickets')
        ->assertSet('bulkSendStatusMessage', 'Queued 2 staged tickets for delivery.');

    Mail::assertQueued(GoldenTicket::class, 2);

    $unsentTicketOne->refresh();
    $unsentTicketTwo->refresh();
    $alreadySentTicket->refresh();
    $ticketWithoutEmail->refresh();
    $revokedTicket->refresh();

    expect($unsentTicketOne->sent_at)->not->toBeNull();
    expect($unsentTicketTwo->sent_at)->not->toBeNull();
    expect($alreadySentTicket->sent_at)->not->toBeNull();
    expect($ticketWithoutEmail->sent_at)->toBeNull();
    expect($revokedTicket->sent_at)->toBeNull();

    Mail::assertNotQueued(GoldenTicket::class, function (GoldenTicket $mail) use ($revokedTicket): bool {
        return $mail->hasTo($revokedTicket->email);
    });
});

test('open print master ticket list modal initializes print options', function () {
    Livewire::test('gt.golden-ticket-manager')
        ->set('masterTicketListSort', 'serial_number')
        ->set('masterTicketListGroupZeroFirst', true)
        ->set('masterTicketListOrientation', 'landscape')
        ->call('openPrintMasterTicketListModal')
        ->assertSet('masterTicketListSort', 'last_name')
        ->assertSet('masterTicketListGroupZeroFirst', false)
        ->assertSet('masterTicketListOrientation', 'portrait');
});

test('print master ticket list action returns a pdf download', function () {
    $activeYear = DateHelpers::psYearForDate(now());
    $calendarYear = DateHelpers::calendarYearForPsYear($activeYear);

    Ticket::factory()->create([
        'ps_year' => $activeYear,
        'first_name' => 'Aly',
        'last_name' => 'Zimmer',
        'group_zero' => false,
        'serial' => '912345',
        'vlid' => 'VL-100',
        'phone' => '555-111-1111',
        'email' => 'aly@example.com',
    ]);

    Ticket::factory()->create([
        'ps_year' => $activeYear,
        'first_name' => 'Bryn',
        'last_name' => 'Able',
        'group_zero' => true,
        'serial' => '012345',
        'vlid' => 'VL-200',
        'phone' => '555-222-2222',
        'email' => 'bryn@example.com',
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->set('masterTicketListSort', 'last_name')
        ->set('masterTicketListGroupZeroFirst', true)
        ->set('masterTicketListOrientation', 'landscape')
        ->call('printMasterTicketList')
        ->assertFileDownloaded("golden-ticket-master-list-{$calendarYear}.pdf");
});

test('print anonymous tickets action returns a pdf download', function () {
    $activeYear = DateHelpers::psYearForDate(now());
    $calendarYear = DateHelpers::calendarYearForPsYear($activeYear);

    Ticket::factory()->create([
        'ps_year' => $activeYear,
        'serial' => '912345',
        'email' => null,
    ]);

    Ticket::factory()->create([
        'ps_year' => $activeYear,
        'serial' => '934567',
        'email' => null,
    ]);

    Ticket::factory()->create([
        'ps_year' => $activeYear,
        'serial' => '812345',
        'email' => null,
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('printAnonymousTickets')
        ->assertFileDownloaded("anonymous-tickets-{$calendarYear}.pdf");
});

test('print anonymous tickets creates 4 if none exist', function () {
    $activeYear = DateHelpers::psYearForDate(now());
    $calendarYear = DateHelpers::calendarYearForPsYear($activeYear);

    Livewire::test('gt.golden-ticket-manager')
        ->call('printAnonymousTickets')
        ->assertFileDownloaded("anonymous-tickets-{$calendarYear}.pdf");

    expect(Ticket::query()->where('ps_year', $activeYear)->whereNull('email')->where('serial', 'like', '9%')->count())->toBe(4);
});

test('print anonymous tickets rounds anonymous count up to multiple of four', function () {
    $activeYear = DateHelpers::psYearForDate(now());
    $calendarYear = DateHelpers::calendarYearForPsYear($activeYear);

    Ticket::factory()->createMany([
        ['ps_year' => $activeYear, 'serial' => '912345', 'email' => null],
        ['ps_year' => $activeYear, 'serial' => '923456', 'email' => null],
        ['ps_year' => $activeYear, 'serial' => '934567', 'email' => null],
    ]);

    Livewire::test('gt.golden-ticket-manager')
        ->call('printAnonymousTickets')
        ->assertFileDownloaded("anonymous-tickets-{$calendarYear}.pdf");

    expect(Ticket::query()->where('ps_year', $activeYear)->whereNull('email')->where('serial', 'like', '9%')->count())->toBe(4);
});
