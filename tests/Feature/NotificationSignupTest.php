<?php

use App\Models\User;
use Livewire\Livewire;

test('loading notification signup does not create a user record', function () {
    Livewire::test('notification-signup');

    expect(User::count())->toBe(0);
});

test('phone stage shows NANP error for full-length invalid number', function () {
    Livewire::test('notification-signup')
        ->set('phone', '(111) 111-1111')
        ->assertSee('Please enter a valid US or Canada mobile phone number');
});

test('phone stage does not show NANP error for valid NANP number', function () {
    Livewire::test('notification-signup')
        ->set('phone', '(800) 221-1212')
        ->assertDontSee('Please enter a valid US or Canada mobile phone number');
});

test('cannot advance to notifications stage with invalid phone but can with valid phone', function () {
    Livewire::test('notification-signup')
        ->set('phone', '(111) 111-1111')
        ->call('goToNotificationsStage')
        ->assertSet('stage', 'phone')
        ->set('phone', '(800) 221-1212')
        ->call('goToNotificationsStage')
        ->assertSet('stage', 'notifications');
});

test('notifications stage displays phone number in masked format', function () {
    Livewire::test('notification-signup')
        ->set('phone', '(800) 221-1212')
        ->call('goToNotificationsStage')
        ->assertSee('(800) 221-1212');
});
