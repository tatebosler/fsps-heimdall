<?php

use App\Models\Ticket;

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
