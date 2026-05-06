<?php

use App\Http\Middleware\AdminToolsAuth;
use App\Models\Channel;
use App\Models\User;
use App\Notifications\CoordinatorChannelBroadcastMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('coordinator channel broadcast page requires admin tools auth', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->get('/admin/coordinator-channel-broadcast')
        ->assertRedirect(route('admin.login'));
});

test('broadcast rejects messages longer than 140 characters', function () {
    Livewire::test('coordinator-channel-broadcast')
        ->set('channelCode', '37901')
        ->set('message', str_repeat('A', 141))
        ->call('sendBroadcast')
        ->assertHasErrors(['message']);
});

test('broadcast sends to target channel plus firehose subscribers without duplicates', function () {
    Notification::fake();

    $channel = Channel::create(['id' => 37901]);
    $firehose = Channel::create(['id' => 37999]);

    $channelOnly = User::factory()->create(['phone' => '8002211212']);
    $both = User::factory()->create(['phone' => '6515550000']);
    $firehoseOnly = User::factory()->create(['phone' => '6125551111']);
    $outsideUser = User::factory()->create(['phone' => '7635552222']);

    $channel->subscribers()->attach([$channelOnly->id, $both->id]);
    $firehose->subscribers()->attach([$both->id, $firehoseOnly->id]);

    Livewire::test('coordinator-channel-broadcast')
        ->set('channelCode', '37901')
        ->set('message', 'Coordinator check-in at the red tent in 10 minutes.')
        ->call('sendBroadcast')
        ->assertHasNoErrors()
        ->assertSet('lastSentCount', 3);

    Notification::assertSentTo($channelOnly, CoordinatorChannelBroadcastMessage::class);
    Notification::assertSentTo($both, CoordinatorChannelBroadcastMessage::class);
    Notification::assertSentTo($firehoseOnly, CoordinatorChannelBroadcastMessage::class);
    Notification::assertNotSentTo($outsideUser, CoordinatorChannelBroadcastMessage::class);

    expect(Notification::sent($both, CoordinatorChannelBroadcastMessage::class))->toHaveCount(1);
});

test('broadcast rejects channel codes that are missing or not special', function () {
    Channel::create(['id' => 37401]);

    Livewire::test('coordinator-channel-broadcast')
        ->set('channelCode', '37401')
        ->set('message', 'Test')
        ->call('sendBroadcast')
        ->assertHasErrors(['channelCode']);

    Livewire::test('coordinator-channel-broadcast')
        ->set('channelCode', '37988')
        ->set('message', 'Test')
        ->call('sendBroadcast')
        ->assertHasErrors(['channelCode']);
});

test('coordinator channel broadcast page renders when tools are unlocked', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ])->get('/admin/coordinator-channel-broadcast')
        ->assertSuccessful()
        ->assertSee('Coordinator Channel Broadcast')
        ->assertSee('Send Broadcast');
});

test('message field uses live binding for character counter updates', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ])->get('/admin/coordinator-channel-broadcast')
        ->assertSuccessful()
        ->assertSee('wire:model.live="message"', false);
});
