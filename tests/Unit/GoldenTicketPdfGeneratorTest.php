<?php

use App\Helpers\DateHelpers;
use App\Helpers\GoldenTicketPdfGenerator;
use App\Models\Ticket;

test('golden ticket pdf generator returns a pdf payload', function () {
    $ticket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '012345',
        'group_zero' => true,
        'first_name' => 'John',
        'last_name' => 'Smith',
    ]);

    $pdf = GoldenTicketPdfGenerator::binary($ticket);

    expect($pdf)->toStartWith('%PDF-');
    expect(strlen($pdf))->toBeGreaterThan(1000);
    expect($pdf)->toContain('012345');
});

test('golden ticket pdf generator output changes for priority tickets', function () {
    $standardTicket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '557741',
        'group_zero' => false,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $priorityTicket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '557741',
        'group_zero' => true,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    expect(GoldenTicketPdfGenerator::binary($standardTicket))
        ->not->toBe(GoldenTicketPdfGenerator::binary($priorityTicket));
});

test('golden ticket qr code png includes the centered icon variant', function () {
    $standardTicket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '557741',
        'group_zero' => false,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $priorityTicket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '557741',
        'group_zero' => true,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $standardQr = GoldenTicketPdfGenerator::qrCodePng($standardTicket);
    $priorityQr = GoldenTicketPdfGenerator::qrCodePng($priorityTicket);

    expect($standardQr)->toStartWith("\x89PNG\r\n\x1a\n");
    expect($priorityQr)->toStartWith("\x89PNG\r\n\x1a\n");
    expect($standardQr)->not->toBe($priorityQr);
});

test('golden ticket pdf generator output changes when volunteer name changes', function () {
    $namedTicket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '557741',
        'group_zero' => false,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $renamedTicket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '557741',
        'group_zero' => false,
        'first_name' => 'Janet',
        'last_name' => 'Doe',
    ]);

    expect(GoldenTicketPdfGenerator::binary($namedTicket))
        ->not->toBe(GoldenTicketPdfGenerator::binary($renamedTicket));
});

test('golden ticket pdf generator builds a stable filename', function () {
    $ticket = Ticket::factory()->make([
        'ps_year' => DateHelpers::psYearForDate(now()),
        'serial' => '012345',
    ]);

    expect(GoldenTicketPdfGenerator::filename($ticket))
        ->toBe('golden-ticket-'.$ticket->year.'-012345.pdf');
});
