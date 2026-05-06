<?php

use App\Helpers\GoldenTicketScanVerifier;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('components.layouts.admin')] #[Title('Bulk Scanner')] class extends Component
{
    public string $scanDump = '';

    /**
     * @var array<int, array{line: int, qr_code: string, status: string, first_name: ?string, message: string}>
     */
    public array $results = [];

    /**
     * @var array{total: int, success: int, invalid: int, revoked: int, already_scanned: int, duplicate_in_import: int, counts: array<string, int>}|null
     */
    public ?array $summary = null;

    public ?string $reportGeneratedAt = null;

    public function qrDataUri(string $value): string
    {
        $qrCode = new QRCode([
            'eccLevel' => EccLevel::H,
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
        ]);

        $pngBinary = (string) $qrCode->render($value);

        return 'data:image/png;base64,'.base64_encode($pngBinary);
    }

    public function processScanDump(GoldenTicketScanVerifier $verifier): void
    {
        $this->validate([
            'scanDump' => ['required', 'string', 'max:50000'],
        ]);

        $inputs = collect(preg_split('/\r\n|\r|\n/', $this->scanDump) ?: [])
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        if ($inputs === []) {
            $this->addError('scanDump', 'Please scan, paste, or type at least one code or serial number.');

            return;
        }

        $report = $verifier->scanMany($inputs, 'Nadamoo Bulk Scanner');

        $this->results = $report['results'];
        $this->summary = $report['summary'];
        $this->reportGeneratedAt = now()->toIso8601String();
        $this->reset('scanDump');
    }

    public function downloadReport(): StreamedResponse
    {
        if ($this->results === [] || $this->summary === null) {
            $this->addError('scanDump', 'Run a bulk scan first before downloading a report.');

            return response()->streamDownload(fn () => print(''), 'bulk-scan-report-empty.csv');
        }

        $generatedAt = $this->reportGeneratedAt !== null
            ? Carbon::parse($this->reportGeneratedAt)
            : now();

        $filename = 'bulk-scan-report-'.$generatedAt->format('Ymd-His').'.csv';
        $summary = $this->summary;
        $results = $this->results;

        return response()->streamDownload(function () use ($summary, $results): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['metric', 'value']);
            fputcsv($stream, ['total', $summary['total']]);
            fputcsv($stream, ['success', $summary['success']]);
            fputcsv($stream, ['invalid', $summary['invalid']]);
            fputcsv($stream, ['revoked', $summary['revoked']]);
            fputcsv($stream, ['already_scanned', $summary['already_scanned']]);
            fputcsv($stream, ['duplicate_in_import', $summary['duplicate_in_import']]);
            fputcsv($stream, []);
            fputcsv($stream, ['line', 'input', 'status', 'first_name', 'message']);

            foreach ($results as $result) {
                fputcsv($stream, [
                    $result['line'],
                    $result['qr_code'],
                    $result['status'],
                    $result['first_name'] ?? '',
                    $result['message'],
                ]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
};
?>

<div class="space-y-6 p-4 sm:px-8">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h1>Offline Scanner Sync</h1>
    </div>
    <div>
        <flux:timeline align="baseline">
            <flux:timeline.item>
                <flux:timeline.indicator>1</flux:timeline.indicator>
                <flux:timeline.content class="space-y-2">
                    <p class="text-lg font-bold">Configure Nadamoo scanner in Bluetooth mode with storage disabled</p>
                    <p class="text-current">Scan these QR codes, one at a time, with the Nadamoo scanner you wish to use. Don't forget to complete the Bluetooth pairing process on your device. Nadamoo scanners typically appear as Bluetooth keyboards.</p>
                </flux:timeline.content>
            </flux:timeline.item>

            <flux:timeline.item>
                <flux:timeline.block>
                    <div x-data="{ showControlCodes: true }" class="space-y-3">
                        <button
                            type="button"
                            @click="showControlCodes = !showControlCodes"
                            class="inline-flex items-center gap-2 rounded-lg bg-gray-200 px-3 py-2 text-sm font-semibold text-gray-900 transition hover:bg-white"
                            :aria-pressed="showControlCodes ? 'true' : 'false'"
                        >
                            <span class="fas" :class="showControlCodes ? 'fa-eye-slash' : 'fa-eye'"></span>
                            <span x-text="showControlCodes ? 'Hide setup codes' : 'Show setup codes'"></span>
                        </button>

                        <flux:callout x-show="showControlCodes" x-transition.opacity variant="secondary" class="bg-white/10">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Enter Bluetooth Mode</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCodeAA') }}"
                                        alt="Nadamoo setup QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Enable Storage Mode</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCode11') }}"
                                        alt="Nadamoo setup QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Clear Memory</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCode18') }}"
                                        alt="Nadamoo setup QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                            </div>
                        </flux:callout>
                    </div>
                </flux:timeline.block>
            </flux:timeline.item>
            <flux:timeline.item>
                <flux:timeline.indicator>2</flux:timeline.indicator>
                <flux:timeline.content class="space-y-2">
                    <p class="text-lg font-bold">Scan codes with the Nadamoo scanner or write down serial numbers</p>
                </flux:timeline.content>
            </flux:timeline.item>
            <flux:timeline.item>
                <flux:timeline.indicator>3</flux:timeline.indicator>
                <flux:timeline.content class="space-y-3">
                    <p class="text-lg font-bold">Place your cursor in the textarea below</p>
                    <p class="text-current">If you've written down a series of serial numbers, you can also paste or type them in here, one per line, instead of scanning the QR codes below to upload data from a Nadamoo scanner.</p>

                    <form wire:submit="processScanDump" class="space-y-3">
                        <label for="bulk-scan-dump" class="block text-sm font-semibold uppercase tracking-wide text-current">Bulk scan dump</label>
                        <textarea
                            id="bulk-scan-dump"
                            wire:model="scanDump"
                            rows="10"
                            spellcheck="false"
                            placeholder="Paste or scan one item per line"
                            class="w-full rounded-xl border border-white/30 bg-white/85 px-4 py-3 text-base text-gray-950 placeholder:text-gray-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300/50"
                        ></textarea>

                        @error('scanDump')
                            <p class="text-sm font-semibold text-red-200">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-200"
                            >
                                <span class="fas fa-upload"></span>
                                <span>Submit bulk scan dump</span>
                            </button>

                            @if ($summary !== null)
                                <button
                                    type="button"
                                    wire:click="downloadReport"
                                    class="inline-flex items-center gap-2 rounded-lg bg-sky-300 px-4 py-2 text-sm font-semibold text-sky-950 transition hover:bg-sky-200"
                                >
                                    <span class="fas fa-file-csv"></span>
                                    <span>Download CSV report</span>
                                </button>
                            @endif
                        </div>
                    </form>

                    @if ($summary !== null)
                        <div class="rounded-xl border border-white/30 bg-white/10 p-4">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-current">Import summary</h2>
                            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-lg bg-black/20 px-3 py-2">
                                    <dt class="text-current">Total</dt>
                                    <dd class="text-lg font-bold">{{ $summary['total'] }}</dd>
                                </div>
                                <div class="rounded-lg bg-black/20 px-3 py-2">
                                    <dt class="text-current">Success</dt>
                                    <dd class="text-lg font-bold">{{ $summary['success'] }}</dd>
                                </div>
                                <div class="rounded-lg bg-black/20 px-3 py-2">
                                    <dt class="text-current">Invalid</dt>
                                    <dd class="text-lg font-bold">{{ $summary['invalid'] }}</dd>
                                </div>
                                <div class="rounded-lg bg-black/20 px-3 py-2">
                                    <dt class="text-current">Revoked</dt>
                                    <dd class="text-lg font-bold">{{ $summary['revoked'] }}</dd>
                                </div>
                                <div class="rounded-lg bg-black/20 px-3 py-2">
                                    <dt class="text-current">Already scanned</dt>
                                    <dd class="text-lg font-bold">{{ $summary['already_scanned'] }}</dd>
                                </div>
                                <div class="rounded-lg bg-black/20 px-3 py-2">
                                    <dt class="text-current">Duplicate in import</dt>
                                    <dd class="text-lg font-bold">{{ $summary['duplicate_in_import'] }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-white/30 bg-white/10 p-4">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-current">Per-line results</h2>
                            <div class="mt-3 max-h-80 overflow-auto rounded-lg border border-white/20">
                                <table class="min-w-full text-left text-xs sm:text-sm">
                                    <thead class="bg-black/20">
                                        <tr>
                                            <th scope="col" class="px-3 py-2 font-semibold">Line</th>
                                            <th scope="col" class="px-3 py-2 font-semibold">Input</th>
                                            <th scope="col" class="px-3 py-2 font-semibold">Status</th>
                                            <th scope="col" class="px-3 py-2 font-semibold">Message</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($results as $result)
                                            <tr class="border-t border-white/10 align-top">
                                                <td class="px-3 py-2">{{ $result['line'] }}</td>
                                                <td class="px-3 py-2 font-mono text-[11px] sm:text-xs">{{ $result['qr_code'] }}</td>
                                                <td class="px-3 py-2 font-semibold">{{ $result['status'] }}</td>
                                                <td class="px-3 py-2">{{ $result['message'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </flux:timeline.content>
            </flux:timeline.item>
            <flux:timeline.item>
                <flux:timeline.indicator>4</flux:timeline.indicator>
                <flux:timeline.content class="space-y-2">
                    <p class="text-lg font-bold">Scan these QR codes with the Nadamoo scanner, one at a time</p>
                    <p class="text-current">Don't forget to complete the Bluetooth pairing process on your device before scanning the "Upload data" QR code. Nadamoo scanners typically appear as Bluetooth keyboards.</p>
                </flux:timeline.content>
            </flux:timeline.item>
            <flux:timeline.item>
                <flux:timeline.block>
                    <div x-data="{ showControlCodes: true }" class="space-y-3">
                        <button
                            type="button"
                            @click="showControlCodes = !showControlCodes"
                            class="inline-flex items-center gap-2 rounded-lg bg-gray-200 px-3 py-2 text-sm font-semibold text-gray-900 transition hover:bg-white"
                            :aria-pressed="showControlCodes ? 'true' : 'false'"
                        >
                            <span class="fas" :class="showControlCodes ? 'fa-eye-slash' : 'fa-eye'"></span>
                            <span x-text="showControlCodes ? 'Hide second-phase control codes' : 'Show second-phase control codes'"></span>
                        </button>

                        <flux:callout x-show="showControlCodes" x-transition.opacity variant="secondary" class="bg-white/10">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Enter Bluetooth Mode</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCodeAA') }}"
                                        alt="Nadamoo second-phase control QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Start Pairing</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCode99') }}"
                                        alt="Nadamoo second-phase control QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Upload Memory</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCode16') }}"
                                        alt="Nadamoo second-phase control QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                            </div>
                        </flux:callout>
                    </div>
                </flux:timeline.block>
            </flux:timeline.item>
            <flux:timeline.item>
                <flux:timeline.indicator>5</flux:timeline.indicator>
                <flux:timeline.content class="space-y-2">
                    <p class="text-lg font-bold">Wait for the scanner to finish upload its data and beep, then press the Submit button to process data</p>
                </flux:timeline.content>
            </flux:timeline.item>
        </flux:timeline>
    </div>
</div>
