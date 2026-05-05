<?php

use App\Helpers\DateHelpers;
use App\Helpers\VolunteerTicketCsvImporter;
use App\Models\Ticket;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Golden Ticket Manager')] class extends Component
{
    use WithFileUploads;

    public int $selectedPsYear;

    public mixed $csv = null;

    /**
     * @var array{
     *     rows_read:int,
     *     tickets_created:int,
     *     tickets_updated:int,
     *     invalid_rows:int,
     *     errors:array<int, string>
     * }|null
     */
    public ?array $importSummary = null;

    public function mount(): void
    {
        $this->selectedPsYear = DateHelpers::psYearForDate(now());

        $years = $this->availablePsYears();
        if (! in_array($this->selectedPsYear, $years, true)) {
            $this->selectedPsYear = $years[0] ?? DateHelpers::psYearForDate(now());
        }
    }

    public function availablePsYears(): array
    {
        $years = Ticket::query()
            ->select('ps_year')
            ->distinct()
            ->orderByDesc('ps_year')
            ->pluck('ps_year')
            ->map(static fn (int $psYear): int => $psYear)
            ->all();

        return collect($years)
            ->push(DateHelpers::psYearForDate(now()))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function selectYear(int $psYear): void
    {
        $this->selectedPsYear = $psYear;
    }

    public function importCsv(VolunteerTicketCsvImporter $importer): void
    {
        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $this->importSummary = $importer->import($this->csv->getRealPath());
            $this->reset('csv');

            $years = $this->availablePsYears();
            if (! in_array($this->selectedPsYear, $years, true)) {
                $this->selectedPsYear = $years[0] ?? DateHelpers::psYearForDate(now());
            }
        } catch (\InvalidArgumentException $exception) {
            $this->addError('csv', $exception->getMessage());
        }
    }
};
?>

<div class="space-y-6 p-4 sm:px-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <h1>Golden Ticket Manager</h1>
        <div class="flex items-center gap-2">
            @php $years = $this->availablePsYears(); @endphp
            @if (count($years) > 1)
                <flux:dropdown>
                    <flux:button icon:trailing="chevron-down">
                        {{ DateHelpers::calendarYearForPsYear($selectedPsYear) }}
                    </flux:button>
                    <flux:menu>
                        @foreach ($years as $psYear)
                            <flux:menu.item
                                wire:click="selectYear({{ $psYear }})"
                                :class="$selectedPsYear === $psYear ? 'font-semibold' : ''"
                            >
                                {{ DateHelpers::calendarYearForPsYear($psYear) }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif
            <flux:dropdown>
                <flux:button icon:trailing="chevron-down" align="end">Options</flux:button>

                <flux:menu>
                    <flux:menu.group>
                        <flux:menu.item icon="user-plus">Create single ticket</flux:menu.item>
                        <flux:modal.trigger name="import-tickets">
                            <flux:menu.item icon="arrow-up-tray">Import tickets from VL</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:menu.item icon="ticket">Print master ticket list</flux:menu.item>
                    </flux:menu.group>
                    <flux:menu.group>
                        <flux:menu.item icon="plus">Create anonymous tickets</flux:menu.item>
                        <flux:menu.item icon="printer">Print anonymous tickets</flux:menu.item>
                    </flux:menu.group>
                    <flux:menu.group>
                        <flux:menu.item icon="envelope">Send all staged tickets</flux:menu.item>
                    </flux:menu.group>
                    <flux:menu.group>
                        <flux:menu.item icon="qr-code">Open browser scanner</flux:menu.item>
                        <flux:menu.item icon="chart-bar-square">Open Nadamoo live scanner</flux:menu.item>
                        <flux:menu.item icon="arrow-turn-up-right">Sync from Nadamoo offline scanner</flux:menu.item>
                        <flux:menu.item icon="pencil-square">Sync scans from serial numbers</flux:menu.item>
                    </flux:menu.group>
                    <flux:menu.group>
                        <flux:menu.item icon="arrow-down-tray">Download scan report</flux:menu.item>
                    </flux:menu.group>

                    <flux:menu.item variant="danger" icon="trash">Delete all tickets</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <flux:modal name="import-tickets" flyout>
        <h2 class="mb-4">Import from VolunteerLocal</h2>
        <flux:timeline align="baseline">
            <flux:timeline.item>
                <flux:timeline.indicator>1</flux:timeline.indicator>
                <flux:timeline.content class="space-y-1">
                    <p class="text-lg font-bold">Create an export in VolunteerLocal</p>
                    <p class="text-gray-700 dark:text-gray-300">This tool expects a VL export to contain the following fields (columns can be in any order):</p>
                    <ul class="list-disc ml-8 text-gray-700 dark:text-gray-300">
                        <li>Job</li>
                        <li>Shift start date</li>
                        <li>Shift start time</li>
                        <li>Shift end time</li>
                        <li>Volunteer Identifier</li>
                        <li>Phone (mobile preferred)</li>
                        <li>Email</li>
                        <li>First Name</li>
                        <li>Last Name</li>
                        <li>Zip code</li>
                    </ul>
                    <p class="text-gray-700 dark:text-gray-300">It's okay to split data into multiple exports &mdash; all exports will be merged together to create the master ticket list.</p>
                </flux:timeline.content>
            </flux:timeline.item>
            <flux:timeline.item>
                <flux:timeline.indicator>2</flux:timeline.indicator>
                <flux:timeline.content class="space-y-1">
                    <p class="text-lg font-bold">Upload the resulting CSV here</p>
                    <form class="space-y-3" wire:submit="importCsv">
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

                        <p wire:loading wire:target="csv,importCsv" class="text-sm text-zinc-500 dark:text-zinc-400">Uploading and importing tickets...</p>
                    </form>

                    @if ($importSummary)
                        <div class="mt-3 rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 space-y-1">
                            <p><span class="font-semibold">Rows read:</span> {{ $importSummary['rows_read'] }}</p>
                            <p><span class="font-semibold">Tickets created:</span> {{ $importSummary['tickets_created'] }}</p>
                            <p><span class="font-semibold">Tickets updated:</span> {{ $importSummary['tickets_updated'] }}</p>
                            <p><span class="font-semibold">Invalid rows:</span> {{ $importSummary['invalid_rows'] }}</p>

                            @if (! empty($importSummary['errors']))
                                <p class="font-semibold pt-2">Row errors</p>
                                <ul class="list-disc ml-6 space-y-1">
                                    @foreach ($importSummary['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </flux:timeline.content>
            </flux:timeline.item>
            <flux:timeline.item>
                <flux:timeline.indicator>3</flux:timeline.indicator>
                <flux:timeline.content class="space-y-1">
                    <p class="text-lg font-bold">Tickets will be automatically imported and staged.</p>
                    <p class="text-gray-700 dark:text-gray-300">Emails will not be sent out yet &mdash; it's okay to stage tickets early, in case you need to review anything.</p>
                </flux:timeline.content>
            </flux:timeline.item>
        </flux:timeline>
    </flux:modal>
</div>
