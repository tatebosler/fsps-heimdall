<?php

use App\Models\Channel;

test('channels can be created with an ID only', function () {
    $channel = Channel::create(['id' => '12345']);
    expect($channel)->toBeInstanceOf(Channel::class);
});

test('special channels are identified correctly', function () {
    $specialChannel = Channel::create(['id' => '37900']);
    $standardChannel = Channel::create(['id' => '37400']);

    expect($specialChannel->isSpecial())->toBeTrue();
    expect($standardChannel->isSpecial())->toBeFalse();
});

test('special channels have descriptions pulled from config file', function () {
    $specialChannel = Channel::create(['id' => '37900']);
    $description = config('ps.special_channel_suffixes.00');

    expect($specialChannel->getDescription())->toBe("{$description} (2026)");
});

test('standard channels have wristband groups as their descriptions', function () {
    $standardChannel = Channel::create(['id' => '37400']);

    expect($standardChannel->getDescription())->toBe('Thursday Group 0 (2026)');
});
