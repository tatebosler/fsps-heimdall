<?php

use App\Http\Middleware\AdminToolsAuth;
use App\Models\Channel;
use App\Models\User;
use Livewire\Livewire;

test('coordinator channel registration page requires admin tools auth', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->get('/admin/coordinator-channel-registration')
        ->assertRedirect(route('admin.login'));
});

test('bulk registration subscribes unique phones to unique special channels and creates missing channels', function () {
    $component = Livewire::test('coordinator-channel-registration')
        ->set('phoneNumbersText', implode("\n", [
            '(800) 221-1212',
            '651-555-0000',
            '(800) 221-1212',
        ]))
        ->set('channelCodesText', implode("\n", [
            '37901',
            '37999',
            '37901',
        ]))
        ->call('registerSubscribers')
        ->assertHasNoErrors();

    $component->assertSet('registrationSummary.registered_phone_count', 2)
        ->assertSet('registrationSummary.channel_count', 2)
        ->assertSet('registrationSummary.users_created', 2)
        ->assertSet('registrationSummary.subscriptions_added', 4);

    $channelOne = Channel::find(37901);
    $channelTwo = Channel::find(37999);

    expect($channelOne)->not->toBeNull();
    expect($channelTwo)->not->toBeNull();
    expect(User::count())->toBe(2);
    expect($channelOne->subscribers()->count())->toBe(2);
    expect($channelTwo->subscribers()->count())->toBe(2);
});

test('bulk registration rejects non-special channel codes', function () {
    Livewire::test('coordinator-channel-registration')
        ->set('phoneNumbersText', '(800) 221-1212')
        ->set('channelCodesText', implode("\n", [
            '37401',
            '37901',
        ]))
        ->call('registerSubscribers')
        ->assertHasErrors(['channelCodesText']);

    expect(User::count())->toBe(0);
});

test('coordinator channel registration page renders when tools are unlocked', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ])->get('/admin/coordinator-channel-registration')
        ->assertSuccessful()
        ->assertSee('Bulk Coordinator Channel Registration')
        ->assertSee('Register Subscribers');
});
