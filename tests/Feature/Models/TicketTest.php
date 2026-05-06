<?php

use App\Helpers\DateHelpers;
use App\Models\Ticket;

it('returns none when group_zero is false', function () {
    $ticket = Ticket::factory()->make(['group_zero' => false, 'shifts' => null]);

    expect($ticket->priorityDesignation())->toBe('none');
});

it('returns manual when group_zero is true with no qualifying shifts', function () {
    $ticket = Ticket::factory()->make([
        'group_zero' => true,
        'shifts' => [
            ['job' => 'Setup', 'start' => '2025-05-08 08:00:00', 'end' => '2025-05-08 12:00:00'],
        ],
    ]);

    expect($ticket->priorityDesignation())->toBe('manual');
});

it('returns manual when group_zero is true with no shifts', function () {
    $ticket = Ticket::factory()->make(['group_zero' => true, 'shifts' => []]);

    expect($ticket->priorityDesignation())->toBe('manual');
});

it('returns shift_start when a shift starts during a qualifying window', function () {
    // config('ps.group_zero.Thursday.shift_start_timestamps') = [['16:30','20:30']]
    $ticket = Ticket::factory()->make([
        'group_zero' => true,
        'shifts' => [
            ['job' => 'Cashier', 'start' => '2025-05-08 17:00:00', 'end' => '2025-05-08 20:00:00'],
        ],
    ]);

    expect($ticket->priorityDesignation())->toBe('shift_start');
});

it('returns shift_end when a shift ends during a qualifying window', function () {
    // config('ps.group_zero.Thursday.shift_end_timestamps') = [['14:00','14:30']]
    $ticket = Ticket::factory()->make([
        'group_zero' => true,
        'shifts' => [
            ['job' => 'Greeter', 'start' => '2025-05-08 12:00:00', 'end' => '2025-05-08 14:15:00'],
        ],
    ]);

    expect($ticket->priorityDesignation())->toBe('shift_end');
});

it('prioritises shift_start over shift_end when both apply', function () {
    $ticket = Ticket::factory()->make([
        'group_zero' => true,
        'shifts' => [
            ['job' => 'Greeter', 'start' => '2025-05-08 12:00:00', 'end' => '2025-05-08 14:15:00'],
            ['job' => 'Cashier', 'start' => '2025-05-08 17:00:00', 'end' => '2025-05-08 20:00:00'],
        ],
    ]);

    // All shifts are checked for shift_start before shift_end, so shift_start takes precedence
    expect($ticket->priorityDesignation())->toBe('shift_start');
});

it('exposes serial_number accessor mapping to serial column', function () {
    $ticket = Ticket::factory()->make(['serial' => '012345']);

    expect($ticket->serial_number)->toBe('012345');
});

it('exposes year accessor via getCalendarYear', function () {
    $psYear = DateHelpers::psYearForDate(now());
    $ticket = Ticket::factory()->make(['ps_year' => $psYear]);

    expect($ticket->year)->toBe(DateHelpers::calendarYearForPsYear($psYear));
});

it('exposes priority accessor mapping to group_zero', function () {
    $ticket = Ticket::factory()->make(['group_zero' => true]);
    expect($ticket->priority)->toBeTrue();

    $ticket2 = Ticket::factory()->make(['group_zero' => false]);
    expect($ticket2->priority)->toBeFalse();
});

it('getDisplayName returns full name when both parts present', function () {
    $ticket = Ticket::factory()->make(['first_name' => 'Jane', 'last_name' => 'Doe']);

    expect($ticket->getDisplayName())->toBe('Jane Doe');
});

it('getDisplayName returns null when no name is set', function () {
    $ticket = Ticket::factory()->make(['first_name' => null, 'last_name' => null]);

    expect($ticket->getDisplayName())->toBeNull();
});
