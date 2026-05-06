<?php

use App\Models\Ticket;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('scan endpoint accepts test ticket on apex host', function () {
    $this->postJson('/golden-tickets/scan', [
        'qr_code' => 'https://friendsschoolplantsale.com/driving?tkt=TEST_OK_2034',
    ])->assertOk()
        ->assertJson([
            'status' => 'OK',
            'first_name' => 'Test',
            'message' => 'OK Test',
        ]);
});

test('scan endpoint accepts test ticket on www host', function () {
    $this->postJson('/golden-tickets/scan', [
        'qr_code' => 'https://www.friendsschoolplantsale.com/driving?tkt=TEST_OK_2034',
    ])->assertOk()
        ->assertJson([
            'status' => 'OK',
            'first_name' => 'Test',
            'message' => 'OK Test',
        ]);
});

test('scan endpoint accepts serial input and stamps provided scan source', function () {
    $ticket = Ticket::factory()->create([
        'serial' => '123456',
        'first_name' => 'Nadia',
        'scanned_at' => null,
        'scanned_by' => null,
    ]);

    $this->postJson('/golden-tickets/scan', [
        'qr_code' => '123456',
        'data_source' => 'Nadamoo Live Scanner',
    ])->assertOk()
        ->assertJson([
            'status' => 'OK',
            'first_name' => 'Nadia',
            'message' => 'OK Nadia',
        ]);

    $ticket->refresh();

    expect($ticket->scanned_at)->not->toBeNull();
    expect($ticket->scanned_by)->toBe('Nadamoo Live Scanner');
});

test('scan endpoint stamps provided scan source for full qr payloads', function () {
    $ticket = Ticket::factory()->create([
        'serial' => '654321',
        'first_name' => 'Mara',
        'scanned_at' => null,
        'scanned_by' => null,
    ]);

    $token = rtrim(strtr(base64_encode($ticket->serial), '+/', '-_'), '=');

    $this->postJson('/golden-tickets/scan', [
        'qr_code' => 'https://friendsschoolplantsale.com/driving?tkt='.$token,
        'data_source' => 'Nadamoo Live Scanner',
    ])->assertOk()
        ->assertJson([
            'status' => 'OK',
            'first_name' => 'Mara',
            'message' => 'OK Mara',
        ]);

    $ticket->refresh();

    expect($ticket->scanned_at)->not->toBeNull();
    expect($ticket->scanned_by)->toBe('Nadamoo Live Scanner');
});

test('scan endpoint allows rescans within 30 seconds without changing the original scan stamp', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 6, 12, 0, 0));

    $scannedAt = now()->subSeconds(29);

    $ticket = Ticket::factory()->create([
        'serial' => '333333',
        'first_name' => 'Mara',
        'scanned_at' => $scannedAt,
        'scanned_by' => 'Gate 1',
    ]);

    $this->postJson('/golden-tickets/scan', [
        'qr_code' => '333333',
        'data_source' => 'Nadamoo Live Scanner',
    ])->assertOk()
        ->assertJson([
            'status' => 'OK',
            'first_name' => 'Mara',
            'message' => 'OK Mara',
        ]);

    $ticket->refresh();

    expect($ticket->scanned_at?->equalTo($scannedAt))->toBeTrue();
    expect($ticket->scanned_by)->toBe('Gate 1');
});

test('scan endpoint rejects rescans at 30 seconds and returns when the ticket was scanned', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 6, 12, 0, 0));

    $scannedAt = now()->subSeconds(30);

    Ticket::factory()->create([
        'serial' => '444444',
        'first_name' => 'Nadia',
        'scanned_at' => $scannedAt,
        'scanned_by' => 'Gate 2',
    ]);

    $this->postJson('/golden-tickets/scan', [
        'qr_code' => '444444',
        'data_source' => 'Nadamoo Live Scanner',
    ])->assertOk()
        ->assertJson([
            'status' => 'ALREADY_SCANNED',
            'first_name' => 'Nadia',
            'message' => 'Scanned on May 6, 2026 11:59:30 AM',
        ]);
});
