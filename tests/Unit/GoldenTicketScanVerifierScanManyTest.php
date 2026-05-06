<?php

use App\Helpers\GoldenTicketScanVerifier;
use App\Models\Ticket;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

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

test('scanMany bypasses the live scan grace period for previously scanned tickets', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 6, 12, 0, 0));

    Ticket::factory()->create([
        'serial' => '112233',
        'first_name' => 'Avery',
        'scanned_at' => now()->subSeconds(5),
        'scanned_by' => 'Gate 3',
    ]);

    $verifier = app(GoldenTicketScanVerifier::class);

    $report = $verifier->scanMany([
        '112233',
    ], 'Bulk Test');

    expect($report['summary']['already_scanned'])->toBe(1);
    expect($report['summary']['success'])->toBe(0);
    expect($report['results'][0])->toMatchArray([
        'status' => 'ALREADY_SCANNED',
        'first_name' => 'Avery',
        'message' => 'Scanned on May 6, 2026 11:59:55 AM',
    ]);
});
