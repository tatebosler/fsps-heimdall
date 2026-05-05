<?php

use App\Helpers\DateHelpers;
use App\Helpers\VolunteerTicketCsvImporter;
use App\Models\Ticket;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Golden Ticket Manager')] class extends Component
{
    use WithFileUploads;

    public int $selectedPsYear;

    public ?int $ticketPendingDeletionId = null;

    public ?string $ticketPendingDeletionSerial = null;

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
        unset($this->tickets);
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

            unset($this->tickets);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('csv', $exception->getMessage());
        }
    }

    public function markTicketAsScanned(int $ticketId): void
    {
        $ticket = Ticket::query()
            ->whereKey($ticketId)
            ->where('ps_year', $this->selectedPsYear)
            ->first();

        if (! $ticket) {
            return;
        }

        $ticket->forceFill([
            'scanned_at' => now(),
            'scanned_by' => 'GTManager',
        ])->save();

        unset($this->tickets);
    }

    public function undoTicketScan(int $ticketId): void
    {
        $ticket = Ticket::query()
            ->whereKey($ticketId)
            ->where('ps_year', $this->selectedPsYear)
            ->first();

        if (! $ticket) {
            return;
        }

        $ticket->forceFill([
            'scanned_at' => null,
            'scanned_by' => null,
        ])->save();

        unset($this->tickets);
    }

    public function revokeTicket(int $ticketId): void
    {
        $ticket = Ticket::query()
            ->whereKey($ticketId)
            ->where('ps_year', $this->selectedPsYear)
            ->first();

        if (! $ticket) {
            return;
        }

        $ticket->forceFill([
            'revoked_at' => now(),
        ])->save();

        unset($this->tickets);
    }

    public function reinstateTicket(int $ticketId): void
    {
        $ticket = Ticket::query()
            ->whereKey($ticketId)
            ->where('ps_year', $this->selectedPsYear)
            ->first();

        if (! $ticket) {
            return;
        }

        $ticket->forceFill([
            'revoked_at' => null,
        ])->save();

        unset($this->tickets);
    }

    public function deleteTicket(int $ticketId): void
    {
        Ticket::query()
            ->whereKey($ticketId)
            ->where('ps_year', $this->selectedPsYear)
            ->delete();

        unset($this->tickets);
    }

    public function confirmDeleteTicket(int $ticketId): void
    {
        $ticket = Ticket::query()
            ->whereKey($ticketId)
            ->where('ps_year', $this->selectedPsYear)
            ->first();

        if (! $ticket) {
            return;
        }

        $this->ticketPendingDeletionId = $ticket->id;
        $this->ticketPendingDeletionSerial = $ticket->serial;

        $this->modal('delete-ticket-confirmation')->show();
    }

    public function deleteConfirmedTicket(): void
    {
        if ($this->ticketPendingDeletionId === null) {
            return;
        }

        $this->deleteTicket($this->ticketPendingDeletionId);

        $this->ticketPendingDeletionId = null;
        $this->ticketPendingDeletionSerial = null;

        $this->modal('delete-ticket-confirmation')->close();
    }

    public function cancelDeleteTicket(): void
    {
        $this->ticketPendingDeletionId = null;
        $this->ticketPendingDeletionSerial = null;

        $this->modal('delete-ticket-confirmation')->close();
    }

    public function openImportTicketsModal(): void
    {
        $this->modal('import-tickets')->show();
    }

    #[Computed]
    public function tickets(): \Illuminate\Support\Collection
    {
        return Ticket::query()
            ->where('ps_year', $this->selectedPsYear)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
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
            <div x-data="{ open: false }" class="relative inline-block text-left">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex w-full items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:ring-white/10 dark:hover:bg-white/20"
                    aria-haspopup="true"
                    :aria-expanded="open"
                >
                    Options
                    <span class="fas fa-chevron-down text-xs text-gray-400" aria-hidden="true"></span>
                </button>

                <div
                    x-show="open"
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    style="display: none;"
                    class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                    role="menu"
                >
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-user-plus mr-2" aria-hidden="true"></span>Create single ticket
                    </button>
                    <button type="button" wire:click="openImportTicketsModal" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-file-import mr-2" aria-hidden="true"></span>Import tickets from VL
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-ticket-alt mr-2" aria-hidden="true"></span>Print master ticket list
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-plus mr-2" aria-hidden="true"></span>Create anonymous tickets
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-print mr-2" aria-hidden="true"></span>Print anonymous tickets
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-envelope mr-2" aria-hidden="true"></span>Send all staged tickets
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-qrcode mr-2" aria-hidden="true"></span>Open browser scanner
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-chart-bar mr-2" aria-hidden="true"></span>Open Nadamoo live scanner
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-arrow-up-right-from-square mr-2" aria-hidden="true"></span>Sync from Nadamoo offline scanner
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-pen-to-square mr-2" aria-hidden="true"></span>Sync scans from serial numbers
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-download mr-2" aria-hidden="true"></span>Download scan report
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30" role="menuitem">
                        <span class="fas fa-trash mr-2" aria-hidden="true"></span>Delete all tickets
                    </button>
                </div>
            </div>
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

    <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
        <thead>
            <tr>
                <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">Ticket</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Volunteer</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-0">
                    <span class="sr-only">Actions</span>
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($this->tickets as $ticket)
                <tr wire:key="{{ $ticket->id }}">
                    <td class="py-4 pr-3 pl-4 text-sm align-top sm:pl-0">
                        <div class="space-y-1">
                            <p class="font-mono text-2xl">{{ $ticket->serial }}</p>
                            <p>Global ID: <span class="font-mono">{{ $ticket->id }}</span></p>
                            @if ($ticket->group_zero)
                                <flux:badge color="purple" class="mb-1">
                                    <span class="fas fa-award mr-1"></span>
                                    Group Zero
                                </flux:badge>
                            @else
                                <flux:badge color="green" class="mb-1">
                                    <span class="fas fa-seedling mr-1"></span>
                                    Standard
                                </flux:badge>
                            @endif
                        </div>
                    </td>
                    <td class="px-3 py-4 text-sm align-top text-gray-500 dark:text-gray-400">
                        @if (empty($ticket->first_name) && empty($ticket->last_name))
                            <p class="text-sm italic text-gray-600 dark:text-gray-400">Anonymous</p>
                        @else
                            <p>{{ $ticket->first_name }} {{ $ticket->last_name }}</p>
                        @endif
                        <p>{{ $ticket->email }}</p>
                        <p>{{ $ticket->phone }}</p>
                        <p>{{ $ticket->zip }}</p>
                        @if (!empty($ticket->shifts) && count($ticket->shifts) > 0)
                            <div class="mt-2">
                                <p class="font-semibold">Shifts:</p>
                                <ul class="list-disc ml-8">
                                    @foreach ($ticket->shifts as $shift)
                                        <li>{{ $shift["job"] }}: {{ \Carbon\Carbon::parse($shift["start"])->format('M j, Y') }} {{ \Carbon\Carbon::parse($shift["start"])->format('g:i A') }} - {{ \Carbon\Carbon::parse($shift["end"])->format('g:i A') }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </td>
                    <td class="px-3 py-4 text-sm align-top text-gray-500 dark:text-gray-400">
                        @if ($ticket->revoked_at)
                            <div class="bg-red-700 text-red-200 px-3 py-2 rounded">
                                <span class="fas fa-ban mr-1"></span>
                                Revoked
                            </div>
                            <p class="mt-2">Revoked at {{ $ticket->revoked_at }} ({{ $ticket->revoked_at->diffForHumans() }})</p>
                        @elseif ($ticket->scanned_at)
                            <div class="bg-green-700 text-green-200 px-3 py-2 rounded">
                                <span class="fas fa-check mr-1"></span>
                                Scanned
                            </div>
                            <p class="mt-2">Scanned at {{ $ticket->scanned_at }} ({{ $ticket->scanned_at->diffForHumans() }})</p>
                            @if ($ticket->scanned_by)
                                <p class="mt-1">Scan method: {{ $ticket->scanned_by }}</p>
                            @endif
                        @elseif ($ticket->sent_at)
                            <div class="bg-blue-700 text-blue-200 px-3 py-2 rounded">
                                <span class="fas fa-envelope-circle-check mr-1"></span>
                                Sent
                            </div>
                            <p class="mt-2">Sent at {{ $ticket->sent_at }} ({{ $ticket->sent_at->diffForHumans() }})</p>
                        @else
                            <div class="bg-gray-700 text-gray-200 px-3 py-2 rounded">
                                <span class="fas fa-play mr-1"></span>
                                Staged
                            </div>
                        @endif
                    </td>
                    <td class="py-4 pr-4 pl-3 text-right align-top sm:pr-0">
                        <div x-data="{ open: false }" class="relative inline-block text-left">
                            <button
                                type="button"
                                @click="open = !open"
                                class="inline-flex w-full items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:ring-white/10 dark:hover:bg-white/20"
                                aria-haspopup="true"
                                :aria-expanded="open"
                            >
                                Actions
                                <span class="fas fa-chevron-down text-xs text-gray-400" aria-hidden="true"></span>
                            </button>

                            <div
                                x-show="open"
                                @click.outside="open = false"
                                @keydown.escape.window="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                style="display: none;"
                                class="absolute right-0 z-50 mt-2 w-52 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                                role="menu"
                            >
                                @if ($ticket->scanned_at)
                                    <button type="button" wire:click="undoTicketScan({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">Undo scan</button>
                                @else
                                    <button type="button" wire:click="markTicketAsScanned({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">Mark as scanned</button>
                                @endif
                                @if ($ticket->revoked_at)
                                    <button type="button" wire:click="reinstateTicket({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">Reinstate</button>
                                @else
                                    <button type="button" wire:click="revokeTicket({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30" role="menuitem">Revoke</button>
                                @endif
                                <button type="button" wire:click="confirmDeleteTicket({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30" role="menuitem">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <flux:modal name="delete-ticket-confirmation">
        <div class="space-y-4">
            <h2>Delete Ticket</h2>
            <p>
                Are you sure you want to delete
                @if ($ticketPendingDeletionSerial)
                    ticket <span class="font-mono font-semibold">{{ $ticketPendingDeletionSerial }}</span>
                @else
                    this ticket
                @endif
                ? This action cannot be undone.
            </p>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDeleteTicket" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="deleteConfirmedTicket" variant="danger">Delete ticket</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
