<?php

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
