<?php

use App\Models\Channel;
use App\Models\Estimate;

test('command imports csv rows and applies special 9xx mapping rules', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'historical-import-');

    expect($csvPath)->not->toBeFalse();

    file_put_contents($csvPath, implode("\n", [
        'id,group_distribution_start,estimated_entry,actual_entry,customer_arrival',
        '35401,2024-05-09 13:42:17,2024-05-09 14:38:48,2024-05-09 14:37:15,2024-05-09 13:41:59',
        '35924,,2024-05-09 14:56:24,2024-05-09 14:52:47,',
        '35929,2024-05-09 14:10:11,2024-05-09 15:01:35,2024-05-09 15:00:11,',
    ]));

    $this->artisan('app:import-historical-entry-data', ['csv' => $csvPath])
        ->assertSuccessful();

    unlink($csvPath);

    $channel35401 = Channel::find(35401);
    $channel35940 = Channel::find(35940);

    expect($channel35401)->not->toBeNull();
    expect($channel35401->customers_arrived_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 13:41:59');
    expect($channel35401->original_estimated_entry_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 14:38:48');
    expect($channel35401->distribution_started_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 13:42:17');

    expect($channel35940)->not->toBeNull();
    expect($channel35940->distribution_started_at)->toBeNull();
    expect(Channel::find(35929))->toBeNull();
    expect(Estimate::where('channel_id', 35401)->count())->toBe(1);
    expect(Estimate::where('channel_id', 35940)->count())->toBe(1);
});

test('command supports id_5d and optional original_estimated_entry columns', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'historical-import-');

    expect($csvPath)->not->toBeFalse();

    file_put_contents($csvPath, implode("\n", [
        'id_5d,group_distribution_start,estimated_entry,actual_entry,original_estimated_entry',
        '35404,2024-05-09 14:10:11,2024-05-09 15:01:35,2024-05-09 15:00:11,2024-05-09 15:05:12',
    ]));

    $this->artisan('app:import-historical-entry-data', ['csv' => $csvPath])
        ->assertSuccessful();

    unlink($csvPath);

    $channel = Channel::find(35404);

    expect($channel)->not->toBeNull();
    expect($channel->distribution_started_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 14:10:11');
    expect($channel->estimated_entry_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 15:01:35');
    expect($channel->cleared_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 15:00:11');
    expect($channel->original_estimated_entry_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 15:05:12');
});

test('header row is not parsed as a data row', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'historical-import-');

    expect($csvPath)->not->toBeFalse();

    file_put_contents($csvPath, implode("\n", [
        'id,group_distribution_start,estimated_entry,actual_entry',
        '35401,2024-05-09 13:42:17,2024-05-09 14:38:48,2024-05-09 14:37:15',
    ]));

    $this->artisan('app:import-historical-entry-data', ['csv' => $csvPath])
        ->assertSuccessful();

    unlink($csvPath);

    // Only one channel should be created (for row 2); the header row must not be treated as a record.
    expect(Channel::count())->toBe(1);
    expect(Channel::find(35401))->not->toBeNull();
});

test('csv with utf-8 bom is imported correctly', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'historical-import-');

    expect($csvPath)->not->toBeFalse();

    // Prepend UTF-8 BOM (\xEF\xBB\xBF) to simulate an Excel-exported CSV.
    $bom = "\xef\xbb\xbf";
    file_put_contents($csvPath, $bom.implode("\n", [
        'id_5d,group_distribution_start,estimated_entry,actual_entry',
        '34401,2023-05-11 14:00:00,2023-05-11 14:30:00,2023-05-11 14:30:00',
    ]));

    $this->artisan('app:import-historical-entry-data', ['csv' => $csvPath])
        ->assertSuccessful();

    unlink($csvPath);

    expect(Channel::find(34401))->not->toBeNull();
});
