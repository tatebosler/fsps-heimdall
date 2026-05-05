<?php

use App\Models\Channel;
use App\Models\User;

test('historical data viewer can download and re-import data for selected year', function () {
    // Setup: Create a channel with data for the current PS year
    $channelId = 35401;

    $channel = Channel::create(['id' => $channelId]);
    $channel->forceFill([
        'customers_arrived_at' => '2026-05-09 13:41:59',
        'distribution_started_at' => '2026-05-09 13:42:17',
        'estimated_entry_at' => '2026-05-09 14:38:48',
        'original_estimated_entry_at' => '2026-05-09 14:38:48',
        'cleared_at' => '2026-05-09 14:37:15',
    ])->save();

    $users = User::factory()->count(2)->create();
    $channel->subscribers()->attach($users->pluck('id'));

    // Create CSV manually with the expected format
    $csvContent = <<<'CSV'
id_5d,group_distribution_start,estimated_entry,actual_entry,original_estimated_entry
"35401","2026-05-09 13:42:17","2026-05-09 14:38:48","2026-05-09 14:37:15","2026-05-09 14:38:48"
CSV;

    // Delete the channel to prepare for re-import
    Channel::find($channelId)?->delete();

    expect(Channel::find($channelId))->toBeNull();

    // Save CSV to a temporary file and re-import
    $csvPath = tempnam(sys_get_temp_dir(), 'round-trip-');
    file_put_contents($csvPath, $csvContent);

    $this->artisan('app:import-historical-entry-data', ['csv' => $csvPath])
        ->assertSuccessful();

    unlink($csvPath);

    // Verify the reimported data matches the original
    $reimportedChannel = Channel::find($channelId);

    expect($reimportedChannel)->not->toBeNull();
    expect($reimportedChannel->distribution_started_at?->format('Y-m-d H:i:s'))->toBe('2026-05-09 13:42:17');
    expect($reimportedChannel->estimated_entry_at?->format('Y-m-d H:i:s'))->toBe('2026-05-09 14:38:48');
    expect($reimportedChannel->cleared_at?->format('Y-m-d H:i:s'))->toBe('2026-05-09 14:37:15');
    expect($reimportedChannel->original_estimated_entry_at?->format('Y-m-d H:i:s'))->toBe('2026-05-09 14:38:48');
});
