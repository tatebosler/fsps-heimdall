<?php

use App\Helpers\HistoricalEntryCsvImporter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Entry Data IO')] class extends Component
{
    use WithFileUploads;

    public mixed $csv = null;

    /**
     * @var array{
     *     rows_read:int,
     *     channels_created:int,
     *     channels_updated:int,
     *     channels_skipped:int,
     *     estimates_created:int,
     *     invalid_rows:int,
     *     errors:array<int, string>
     * }|null
     */
    public ?array $summary = null;

    public function importCsv(HistoricalEntryCsvImporter $importer): void
    {
        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $this->summary = $importer->import($this->csv->getRealPath());
            $this->reset('csv');
        } catch (\InvalidArgumentException $exception) {
            $this->addError('csv', $exception->getMessage());
        }
    }
};
?>

<div class="space-y-6 p-4 sm:px-8">
    <h1 class="text-2xl font-bold">Entry Data IO</h1>

    <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <h2 class="text-lg font-semibold">Import Historical Entry Data</h2>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
            Upload a CSV with columns: <strong>id_5d or id</strong>, <strong>group_distribution_start</strong>,
            <strong>estimated_entry</strong>, and <strong>actual_entry</strong>. Optional columns: <strong>original_estimated_entry</strong> and
            <strong>customer_arrival</strong>.
        </p>

        <form class="mt-4 space-y-4" wire:submit="importCsv">
            <flux:field>
                <flux:label>CSV File</flux:label>
                <input
                    type="file"
                    wire:model="csv"
                    accept=".csv,text/csv"
                    class="block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                >
                <flux:error name="csv" />
            </flux:field>

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="csv,importCsv">
                Import CSV
            </flux:button>

            <p wire:loading wire:target="csv,importCsv" class="text-sm text-zinc-500 dark:text-zinc-400">Uploading and importing...</p>
        </form>
    </section>

    @if ($summary)
        <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 space-y-3">
            <h2 class="text-lg font-semibold">Import Results</h2>

            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                <div class="rounded bg-zinc-100 p-3 dark:bg-zinc-800">
                    <div class="text-zinc-500">Rows read</div>
                    <div class="text-xl font-semibold">{{ $summary['rows_read'] }}</div>
                </div>
                <div class="rounded bg-zinc-100 p-3 dark:bg-zinc-800">
                    <div class="text-zinc-500">Channels created</div>
                    <div class="text-xl font-semibold">{{ $summary['channels_created'] }}</div>
                </div>
                <div class="rounded bg-zinc-100 p-3 dark:bg-zinc-800">
                    <div class="text-zinc-500">Channels updated</div>
                    <div class="text-xl font-semibold">{{ $summary['channels_updated'] }}</div>
                </div>
                <div class="rounded bg-zinc-100 p-3 dark:bg-zinc-800">
                    <div class="text-zinc-500">Channels skipped</div>
                    <div class="text-xl font-semibold">{{ $summary['channels_skipped'] }}</div>
                </div>
                <div class="rounded bg-zinc-100 p-3 dark:bg-zinc-800">
                    <div class="text-zinc-500">Estimates created</div>
                    <div class="text-xl font-semibold">{{ $summary['estimates_created'] }}</div>
                </div>
                <div class="rounded bg-zinc-100 p-3 dark:bg-zinc-800">
                    <div class="text-zinc-500">Invalid rows</div>
                    <div class="text-xl font-semibold">{{ $summary['invalid_rows'] }}</div>
                </div>
            </div>

            @if (! empty($summary['errors']))
                <div class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                    <p class="font-semibold">Row errors</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($summary['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif
</div>
