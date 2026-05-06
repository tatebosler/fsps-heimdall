<?php

use App\Http\Middleware\AdminToolsAuth;
use App\Models\Channel;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('entry data io page renders', function () {
    config()->set('ps.admin_tools_password', 'test-password');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ])->get(route('entry-data-io'))
        ->assertSuccessful();
});

test('entry data io imports a csv upload', function () {
    config()->set('ps.admin_tools_password', 'test-password');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ]);

    $csv = implode("\n", [
        'id_5d,group_distribution_start,estimated_entry,actual_entry,original_estimated_entry,customer_arrival',
        '35408,2024-05-09 15:11:46,2024-05-09 15:30:24,2024-05-09 15:30:33,2024-05-09 15:30:24,2024-05-09 15:10:00',
    ]);

    $upload = UploadedFile::fake()->createWithContent('historical.csv', $csv);

    Livewire::test('entry-data-io')
        ->set('csv', $upload)
        ->call('importCsv')
        ->assertHasNoErrors()
        ->assertSee('Import Results')
        ->assertSee('Rows read')
        ->assertSee('1');

    $channel = Channel::find(35408);

    expect($channel)->not->toBeNull();
    expect($channel->customers_arrived_at?->format('Y-m-d H:i:s'))->toBe('2024-05-09 15:10:00');
});
