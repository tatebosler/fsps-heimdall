<?php

use App\Models\Ticket;
use Livewire\Livewire;

test('bulk scanner page requires admin tools auth', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->get('/admin/bulk-scan')
        ->assertRedirect(route('admin.login'));
});

test('bulk scanner processes textarea input and can download a csv report', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $ticket = Ticket::factory()->create([
        'serial' => '123456',
        'first_name' => 'Nadia',
        'scanned_at' => null,
        'scanned_by' => null,
    ]);

    $component = Livewire::test('bulk-scanner')
        ->set('scanDump', implode("\n", [
            '123456',
            '123456',
            'not-a-scan',
        ]))
        ->call('processScanDump')
        ->assertHasNoErrors()
        ->assertSee('Import summary')
        ->assertSee('Per-line results')
        ->assertSet('summary.total', 3)
        ->assertSet('summary.success', 1)
        ->assertSet('summary.duplicate_in_import', 1)
        ->assertSet('summary.invalid', 1);

    $component->call('downloadReport')
        ->assertFileDownloaded();

    $ticket->refresh();

    expect($ticket->scanned_at)->not->toBeNull();
    expect($ticket->scanned_by)->toBe('Nadamoo Bulk Scanner');
});
