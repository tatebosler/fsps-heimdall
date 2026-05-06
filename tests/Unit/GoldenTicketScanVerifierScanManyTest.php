<?php

use App\Helpers\GoldenTicketScanVerifier;
use App\Models\Ticket;

test('scanMany accepts serial input and flags duplicates in a single import', function () {
    $ticket = Ticket::factory()->create([
        'serial' => '654321',
        'first_name' => 'Mara',
        'scanned_at' => null,
        'scanned_by' => null,
    ]);

    $verifier = app(GoldenTicketScanVerifier::class);

    $report = $verifier->scanMany([
        '654321',
        '654321',
        'bad-input',
    ], 'Bulk Test');

    expect($report['summary']['total'])->toBe(3);
    expect($report['summary']['success'])->toBe(1);
    expect($report['summary']['duplicate_in_import'])->toBe(1);
    expect($report['summary']['invalid'])->toBe(1);

    expect($report['results'][0]['status'])->toBe('OK');
    expect($report['results'][1]['status'])->toBe('DUPLICATE_IN_IMPORT');
    expect($report['results'][2]['status'])->toBe('INVALID');

    $ticket->refresh();

    expect($ticket->scanned_at)->not->toBeNull();
    expect($ticket->scanned_by)->toBe('Bulk Test');
});
