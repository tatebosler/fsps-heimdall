<?php

use App\Helpers\DateHelpers;
use App\Helpers\VolunteerTicketCsvImporter;
use App\Mail\GoldenTicket;
use App\Models\Ticket;
use Fpdf\Fpdf;
use Illuminate\Support\Facades\Mail;
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

    public ?int $yearTicketPendingDeletionCount = null;

    public ?Ticket $workingTicket = null;

    public ?string $ticketFirstName = null;

    public ?string $ticketLastName = null;

    public ?string $ticketEmail = null;

    public ?string $ticketPhone = null;

    public ?string $ticketZip = null;

    public bool $ticketPriorityAdmission = false;

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

    public string $masterTicketListSort = 'last_name';

    public bool $masterTicketListGroupZeroFirst = false;

    public string $masterTicketListOrientation = 'portrait';

    public int $anonymousTicketQuantity = 4;

    public ?int $testEmailTicketId = null;

    public string $testEmailAddress = '';

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

    public function closeModals(): void
    {
        $this->modal('import-tickets')->close();
        $this->modal('print-master-ticket-list')->close();
        $this->modal('create-anonymous-tickets')->close();
        $this->modal('delete-ticket-confirmation')->close();
        $this->modal('delete-all-tickets-confirmation')->close();
        $this->modal('test-email-ticket')->close();
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

    public function openDeleteAllTicketsModal(): void
    {
        $this->yearTicketPendingDeletionCount = Ticket::query()
            ->where('ps_year', $this->selectedPsYear)
            ->count();

        $this->modal('delete-all-tickets-confirmation')->show();
    }

    public function openCreateAnonymousTicketsModal(): void
    {
        $this->anonymousTicketQuantity = 4;

        $this->resetValidation(['anonymousTicketQuantity']);
        $this->modal('create-anonymous-tickets')->show();
    }

    public function createAnonymousTickets(): void
    {
        $validated = $this->validate([
            'anonymousTicketQuantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $requestedQuantity = (int) $validated['anonymousTicketQuantity'];
        $quantityToCreate = (int) (ceil($requestedQuantity / 4) * 4);

        for ($i = 0; $i < $quantityToCreate; $i++) {
            Ticket::query()->create([
                'ps_year' => $this->selectedPsYear,
                'group_zero' => false,
                'serial' => $this->generateUniqueSerial($this->selectedPsYear, false, true),
            ]);
        }

        $this->anonymousTicketQuantity = 4;
        $this->modal('create-anonymous-tickets')->close();

        unset($this->tickets);
    }

    public function cancelDeleteAllTickets(): void
    {
        $this->yearTicketPendingDeletionCount = null;
        $this->modal('delete-all-tickets-confirmation')->close();
    }

    public function deleteAllTicketsForSelectedYear(): void
    {
        Ticket::query()
            ->where('ps_year', $this->selectedPsYear)
            ->delete();

        $this->yearTicketPendingDeletionCount = null;
        $this->modal('delete-all-tickets-confirmation')->close();

        unset($this->tickets);
    }

    public function downloadScanReport(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $psYear = $this->selectedPsYear;
        $calendarYear = DateHelpers::calendarYearForPsYear($psYear);
        $fileName = "golden-ticket-scan-report-{$calendarYear}.csv";

        return response()->streamDownload(function () use ($psYear): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new \RuntimeException('Unable to create scan report output stream.');
            }

            fputcsv($handle, [
                'VLID',
                'First name',
                'Last name',
                'Email address',
                'Phone number',
                'Zip code',
                'Send timestamp',
                'Scan timestamp',
                'Scanned By',
            ]);

            Ticket::query()
                ->where('ps_year', $psYear)
                ->orderBy('id')
                ->cursor()
                ->each(function (Ticket $ticket) use ($handle): void {
                    $vlid = is_string($ticket->vlid) && trim($ticket->vlid) !== ''
                        ? $ticket->vlid
                        : "anonymous-{$ticket->serial}";

                    fputcsv($handle, [
                        $vlid,
                        $ticket->first_name ?? '',
                        $ticket->last_name ?? '',
                        $ticket->email ?? '',
                        $ticket->phone ?? '',
                        $ticket->zip ?? '',
                        $ticket->sent_at?->toIso8601String() ?? '',
                        $ticket->scanned_at?->toIso8601String() ?? '',
                        $ticket->scanned_by ?? '',
                    ]);
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function openPrintMasterTicketListModal(): void
    {
        $this->masterTicketListSort = 'last_name';
        $this->masterTicketListGroupZeroFirst = false;
        $this->masterTicketListOrientation = 'portrait';

        $this->resetValidation([
            'masterTicketListSort',
            'masterTicketListGroupZeroFirst',
            'masterTicketListOrientation',
        ]);

        $this->modal('print-master-ticket-list')->show();
    }

    public function printMasterTicketList(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validated = $this->validate([
            'masterTicketListSort' => ['required', 'in:first_name,last_name,serial_number'],
            'masterTicketListGroupZeroFirst' => ['required', 'boolean'],
            'masterTicketListOrientation' => ['required', 'in:portrait,landscape'],
        ]);

        $query = Ticket::query()
            ->where('ps_year', $this->selectedPsYear);

        if ((bool) $validated['masterTicketListGroupZeroFirst']) {
            $query->orderByDesc('group_zero');
        }

        if ($validated['masterTicketListSort'] === 'first_name') {
            $query
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->orderBy('serial');
        }

        if ($validated['masterTicketListSort'] === 'last_name') {
            $query
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('serial');
        }

        if ($validated['masterTicketListSort'] === 'serial_number') {
            $query
                ->orderBy('serial')
                ->orderBy('last_name')
                ->orderBy('first_name');
        }

        $tickets = $query
            ->orderBy('id')
            ->get([
                'first_name',
                'last_name',
                'group_zero',
                'serial',
                'vlid',
                'phone',
                'email',
            ]);

        $orientation = $validated['masterTicketListOrientation'] === 'landscape' ? 'L' : 'P';
        $sortLabel = match ($validated['masterTicketListSort']) {
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'serial_number' => 'Serial number',
        };

        $psYear = $this->selectedPsYear;
        $calendarYear = DateHelpers::calendarYearForPsYear($psYear);
        $fileName = "golden-ticket-master-list-{$calendarYear}.pdf";

        $this->modal('print-master-ticket-list')->close();

        return response()->streamDownload(function () use ($tickets, $orientation, $calendarYear, $sortLabel, $validated): void {
            $encodeValue = static function (?string $value): string {
                $trimmed = trim((string) $value);

                if ($trimmed === '') {
                    return '';
                }

                $encoded = @iconv('UTF-8', 'windows-1252//TRANSLIT', $trimmed);

                if ($encoded === false) {
                    return preg_replace('/[^\x20-\x7E]/', '?', $trimmed) ?? '';
                }

                return $encoded;
            };

            $pdf = new Fpdf($orientation, 'mm', 'Letter');
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 10);
            $pdf->AddPage();

            $columnWidths = $orientation === 'L'
                ? [30, 30, 18, 24, 30, 38, 69]
                : [24, 24, 18, 20, 22, 30, 52];

            $renderHeader = static function (Fpdf $pdf, array $columnWidths, callable $encodeValue): void {
                $pdf->SetFillColor(241, 245, 249);

                $pdf->SetFont('Helvetica', 'B', 10);
                $pdf->Cell($columnWidths[0], 8, $encodeValue('First name'), 1, 0, 'L', true);
                $pdf->Cell($columnWidths[1], 8, $encodeValue('Last name'), 1, 0, 'L', true);
                $pdf->Cell($columnWidths[2], 8, $encodeValue('Group Zero?'), 1, 0, 'L', true);
                $pdf->Cell($columnWidths[3], 8, $encodeValue('Serial number'), 1, 0, 'L', true);

                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->Cell($columnWidths[4], 8, $encodeValue('VLID'), 1, 0, 'L', true);
                $pdf->Cell($columnWidths[5], 8, $encodeValue('Phone number'), 1, 0, 'L', true);
                $pdf->Cell($columnWidths[6], 8, $encodeValue('Email address'), 1, 1, 'L', true);
            };

            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->Cell(0, 8, $encodeValue("Golden Ticket Master List ({$calendarYear})"), 0, 1);

            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(0, 6, $encodeValue('Sort: '.$sortLabel), 0, 1);
            $pdf->Cell(0, 6, $encodeValue('Group Zero first: '.($validated['masterTicketListGroupZeroFirst'] ? 'Yes' : 'No')), 0, 1);
            $pdf->Ln(2);

            $renderHeader($pdf, $columnWidths, $encodeValue);

            foreach ($tickets as $ticket) {
                $lineHeight = 8;

                if ($pdf->GetY() + $lineHeight > ($pdf->GetPageHeight() - 10)) {
                    $pdf->AddPage();
                    $renderHeader($pdf, $columnWidths, $encodeValue);
                }

                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->Cell($columnWidths[0], $lineHeight, $encodeValue($ticket->first_name), 1, 0, 'L');
                $pdf->Cell($columnWidths[1], $lineHeight, $encodeValue($ticket->last_name), 1, 0, 'L');
                $pdf->Cell($columnWidths[2], $lineHeight, $encodeValue($ticket->group_zero ? 'Yes' : 'No'), 1, 0, 'L');
                $pdf->Cell($columnWidths[3], $lineHeight, $encodeValue($ticket->serial), 1, 0, 'L');

                $pdf->SetFont('Helvetica', '', 9);
                $pdf->Cell($columnWidths[4], $lineHeight, $encodeValue($ticket->vlid), 1, 0, 'L');
                $pdf->Cell($columnWidths[5], $lineHeight, $encodeValue($ticket->phone), 1, 0, 'L');
                $pdf->Cell($columnWidths[6], $lineHeight, $encodeValue($ticket->email), 1, 1, 'L');
            }

            $output = $pdf->Output('S');

            if (! is_string($output)) {
                throw new \RuntimeException('Unable to render master ticket list PDF.');
            }

            echo $output;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function openCreateTicketModal(): void
    {
        $this->workingTicket = null;

        $this->ticketFirstName = null;
        $this->ticketLastName = null;
        $this->ticketEmail = null;
        $this->ticketPhone = null;
        $this->ticketZip = null;
        $this->ticketPriorityAdmission = false;

        $this->resetValidation();
        $this->modal('create-edit-ticket')->show();
    }

    public function openEditTicketModal(int $ticketId): void
    {
        $ticket = Ticket::query()
            ->whereKey($ticketId)
            ->where('ps_year', $this->selectedPsYear)
            ->first();

        if (! $ticket) {
            return;
        }

        $this->workingTicket = $ticket;
        $this->ticketFirstName = $ticket->first_name;
        $this->ticketLastName = $ticket->last_name;
        $this->ticketEmail = $ticket->email;
        $this->ticketPhone = $ticket->phone;
        $this->ticketZip = $ticket->zip;
        $this->ticketPriorityAdmission = (bool) $ticket->group_zero;

        $this->resetValidation();
        $this->modal('create-edit-ticket')->show();
    }

    public function saveTicket(): void
    {
        $validated = $this->validate([
            'ticketFirstName' => ['nullable', 'string', 'max:255'],
            'ticketLastName' => ['nullable', 'string', 'max:255'],
            'ticketEmail' => ['nullable', 'email', 'max:255'],
            'ticketPhone' => ['nullable', 'string', 'max:255'],
            'ticketZip' => ['nullable', 'string', 'max:32'],
            'ticketPriorityAdmission' => ['required', 'boolean'],
        ]);

        $ticket = null;

        if ($this->workingTicket?->id) {
            $ticket = Ticket::query()
                ->whereKey($this->workingTicket->id)
                ->where('ps_year', $this->selectedPsYear)
                ->first();
        }

        if (! $ticket) {
            $ticket = new Ticket;
            $ticket->ps_year = $this->selectedPsYear;
            $ticket->serial = $this->generateUniqueSerial($this->selectedPsYear, (bool) $validated['ticketPriorityAdmission']);
        }

        $ticket->first_name = $this->normalizeNullableString($validated['ticketFirstName']);
        $ticket->last_name = $this->normalizeNullableString($validated['ticketLastName']);
        $ticket->email = $this->normalizeNullableString($validated['ticketEmail']);
        $ticket->phone = $this->normalizeNullableString($validated['ticketPhone']);
        $ticket->zip = $this->normalizeNullableString($validated['ticketZip']);
        $ticket->group_zero = (bool) $validated['ticketPriorityAdmission'];

        $ticket->save();

        $this->workingTicket = null;
        $this->modal('create-edit-ticket')->close();

        unset($this->tickets);
    }

    public function openTestEmailModal(int $ticketId): void
    {
        $this->testEmailTicketId = $ticketId;
        $this->testEmailAddress = '';
        $this->resetValidation(['testEmailAddress']);
        $this->modal('test-email-ticket')->show();
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'testEmailAddress' => ['required', 'email', 'max:255'],
        ]);

        $ticket = Ticket::query()
            ->whereKey($this->testEmailTicketId)
            ->where('ps_year', $this->selectedPsYear)
            ->first();

        if (! $ticket) {
            $this->modal('test-email-ticket')->close();

            return;
        }

        Mail::to($this->testEmailAddress)->send(new GoldenTicket($ticket));

        $this->testEmailTicketId = null;
        $this->testEmailAddress = '';
        $this->modal('test-email-ticket')->close();
    }

    public function sendAllStagedTickets(): void
    {
        Ticket::query()
            ->where('ps_year', $this->selectedPsYear)
            ->whereNull('sent_at')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->cursor()
            ->each(function (Ticket $ticket): void {
                Mail::to($ticket->email)->send(new GoldenTicket($ticket));

                $ticket->forceFill([
                    'sent_at' => now(),
                ])->save();
            });
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function generateUniqueSerial(int $psYear, bool $groupZero, bool $anonymous = false): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $prefix = $groupZero ? '0' : ($anonymous ? '9' : (string) random_int(1, 8));
            $middle = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $suffix = (string) random_int(0, 9);
            $serial = $prefix.$middle.$suffix;

            $exists = Ticket::query()
                ->where('ps_year', $psYear)
                ->where('serial', $serial)
                ->exists();

            if (! $exists) {
                return $serial;
            }
        }

        throw new \RuntimeException('Unable to generate a unique serial number for this PS year.');
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
                    <button type="button" wire:click="openCreateTicketModal" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-user-plus mr-2" aria-hidden="true"></span>Create single ticket
                    </button>
                    <button type="button" wire:click="openImportTicketsModal" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-file-import mr-2" aria-hidden="true"></span>Import tickets from VL
                    </button>
                    <button type="button" wire:click="openPrintMasterTicketListModal" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-ticket-alt mr-2" aria-hidden="true"></span>Print master ticket list
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" wire:click="openCreateAnonymousTicketsModal" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-plus mr-2" aria-hidden="true"></span>Create anonymous tickets
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-print mr-2" aria-hidden="true"></span>Print anonymous tickets
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" wire:click="sendAllStagedTickets" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-envelope mr-2" aria-hidden="true"></span>Send all staged tickets
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-expand mr-2" aria-hidden="true"></span>Open browser scanner
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-qrcode mr-2" aria-hidden="true"></span>Open Nadamoo live scanner
                    </button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-cloud-arrow-up mr-2" aria-hidden="true"></span>Sync from offline scanners
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" wire:click="downloadScanReport" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                        <span class="fas fa-download mr-2" aria-hidden="true"></span>Download scan report
                    </button>

                    <div class="my-1 border-t border-gray-200 dark:border-white/10"></div>

                    <button type="button" wire:click="openDeleteAllTicketsModal" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30" role="menuitem">
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
                    <flux:button type="button" variant="primary" wire:click="closeModals" class="mt-1">
                        Close
                    </flux:button>
                </flux:timeline.content>
            </flux:timeline.item>
        </flux:timeline>
    </flux:modal>

    <flux:modal name="print-master-ticket-list" flyout>
        <h2>Print Master Ticket List</h2>

        <form class="mt-4 space-y-4" wire:submit="printMasterTicketList">
            <flux:field>
                <flux:label>Sort</flux:label>
                <flux:select wire:model="masterTicketListSort">
                    <option value="first_name">First name</option>
                    <option value="last_name">Last name</option>
                    <option value="serial_number">Serial number</option>
                </flux:select>
                <flux:error name="masterTicketListSort" />
            </flux:field>

            <flux:checkbox wire:model="masterTicketListGroupZeroFirst" label="Display Group Zero eligible tickets first" />
            <flux:error name="masterTicketListGroupZeroFirst" />

            <flux:field>
                <flux:label>Page orientation</flux:label>
                <flux:select wire:model="masterTicketListOrientation">
                    <option value="portrait">Portrait</option>
                    <option value="landscape">Landscape</option>
                </flux:select>
                <flux:error name="masterTicketListOrientation" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('print-master-ticket-list').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Generate PDF</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="create-anonymous-tickets">
        <h2>Create Anonymous Tickets</h2>

        <form class="mt-4 space-y-4" wire:submit="createAnonymousTickets">
            <flux:field>
                <flux:label>How many tickets should be created?</flux:label>
                <flux:input wire:model="anonymousTicketQuantity" type="number" min="1" step="1" />
                <flux:error name="anonymousTicketQuantity" />
            </flux:field>

            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                The requested amount will be rounded up to the nearest multiple of 4.
            </p>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-anonymous-tickets').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create tickets</flux:button>
            </div>
        </form>
    </flux:modal>

    <table class="relative min-w-full divide-y divide-gray-400 dark:divide-white/15">
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
        <tbody class="divide-y divide-gray-400 dark:divide-white/15">
            @foreach ($this->tickets as $ticket)
                <tr wire:key="{{ $ticket->id }}">
                    <td class="pt-4 pb-7 pr-3 pl-4 text-sm align-top sm:pl-0">
                        <div class="space-y-1">
                            <p class="font-mono text-2xl">{{ $ticket->serial }}</p>
                            <p class="mb-4">Global ID: <span class="font-mono">{{ $ticket->id }}</span></p>
                            @if ($ticket->group_zero)
                                <span class="bg-purple-700 text-purple-200 px-3 py-2 rounded">
                                    <span class="fas fa-award mr-1"></span>
                                    Group Zero
                                </span>
                            @else
                                <span class="bg-green-700 text-green-200 px-3 py-2 rounded">
                                    <span class="fas fa-seedling mr-1"></span>
                                    Standard
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-3 py-4 text-sm align-top text-gray-500 dark:text-gray-400">
                        @if (empty($ticket->first_name) && empty($ticket->last_name))
                            <p class="text-xl font-bold italic text-gray-600 dark:text-gray-400">Anonymous</p>
                        @else
                            <p class="text-xl font-bold">{{ $ticket->first_name }} {{ $ticket->last_name }}</p>
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
                                <button type="button" wire:click="openEditTicketModal({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                                    <span class="fas fa-pen-to-square mr-2" aria-hidden="true"></span>Edit ticket
                                </button>
                                <a href="{{ route('admin.ticket.pdf', $ticket) }}" target="_blank" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                                    <span class="fas fa-file-pdf mr-2" aria-hidden="true"></span>View PDF
                                </a>
                                <button type="button" wire:click="openTestEmailModal({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                                    <span class="fas fa-paper-plane mr-2" aria-hidden="true"></span>Send test email
                                </button>
                                @if ($ticket->scanned_at)
                                    <button type="button" wire:click="undoTicketScan({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                                        <span class="fas fa-qrcode mr-2" aria-hidden="true"></span>Undo scan
                                    </button>
                                @else
                                    <button type="button" wire:click="markTicketAsScanned({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                                        <span class="fas fa-qrcode mr-2" aria-hidden="true"></span>Mark as scanned
                                    </button>
                                @endif
                                @if ($ticket->revoked_at)
                                    <button type="button" wire:click="reinstateTicket({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">
                                        <span class="fas fa-circle-check mr-2" aria-hidden="true"></span>Reinstate
                                    </button>
                                @else
                                    <button type="button" wire:click="revokeTicket({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30" role="menuitem">
                                        <span class="fas fa-ban mr-2" aria-hidden="true"></span>Revoke
                                    </button>
                                @endif
                                <button type="button" wire:click="confirmDeleteTicket({{ $ticket->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30" role="menuitem">
                                    <span class="fas fa-trash-can mr-2" aria-hidden="true"></span>Delete
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <flux:modal name="create-edit-ticket" flyout>
        <h2>{{ $workingTicket ? 'Edit' : 'Create' }} Ticket</h2>
        <form class="mt-4 space-y-4" wire:submit="saveTicket">
            <flux:input wire:model="ticketFirstName" label="First name" />
            <flux:input wire:model="ticketLastName" label="Last name" />
            <flux:input wire:model="ticketEmail" type="email" label="Email" />
            <flux:input wire:model="ticketPhone" label="Phone" />
            <flux:input wire:model="ticketZip" label="Zip code" />
            <flux:checkbox wire:model="ticketPriorityAdmission" label="Group Zero" />

            @unless ($workingTicket)
                <p class="text-sm">Heads up: creating a ticket does not automatically send it to this volunteer. You'll need to manually send the ticket when you're ready.</p>
            @endunless

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-edit-ticket').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>

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

    <flux:modal name="delete-all-tickets-confirmation">
        <div class="space-y-4">
            <h2>Delete All Tickets</h2>
            <p>
                Are you sure you want to delete all Golden Tickets for
                <span class="font-semibold">{{ DateHelpers::calendarYearForPsYear($selectedPsYear) }}</span>?
                @if ($yearTicketPendingDeletionCount !== null)
                    This will delete <span class="font-semibold">{{ $yearTicketPendingDeletionCount }}</span>
                    {{ \Illuminate\Support\Str::plural('ticket', $yearTicketPendingDeletionCount) }}.
                @endif
                This action cannot be undone.
            </p>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelDeleteAllTickets" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="deleteAllTicketsForSelectedYear" variant="danger">Delete all tickets</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="test-email-ticket">
        <div class="space-y-4">
            <h2>Send Test Email</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">A copy of the Golden Ticket email will be sent to the address below. The ticket's <strong>sent_at</strong> timestamp will not be updated.</p>

            <form wire:submit="sendTestEmail" class="space-y-4">
                <flux:field>
                    <flux:label>Destination email address</flux:label>
                    <flux:input wire:model="testEmailAddress" type="email" placeholder="recipient@example.com" />
                    <flux:error name="testEmailAddress" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('test-email-ticket').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Send email</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
