<?php

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.admin')] #[Title('Singleton Scanner')] class extends Component
{
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
};
?>

<div class="space-y-6 p-4 sm:px-8">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h1>Nadamoo Live Scanner</h1>
        <button
            type="button"
            data-singleton-activate-sfx
            class="inline-flex items-center gap-2 rounded-lg bg-amber-300 px-3 py-2 text-sm font-semibold text-amber-950 transition hover:bg-amber-200"
        >
            <span class="fas fa-volume-high"></span>
            Activate sound effects
        </button>
    </div>

    <div
        data-singleton-scanner-root
        data-singleton-verify-url="{{ route('golden-tickets.scan') }}"
        data-singleton-data-source="Nadamoo Live Scanner"
        class="space-y-6 rounded-2xl bg-gray-950 p-4 text-white transition-colors duration-300"
    >
        <flux:timeline align="baseline">
            <flux:timeline.item>
                <flux:timeline.indicator>1</flux:timeline.indicator>
                <flux:timeline.content class="space-y-2">
                    <p class="text-lg font-bold">Configure Nadamoo scanner in Bluetooth mode with storage disabled</p>
                    <p class="text-current">Scan these QR codes, one at a time, with the Nadamoo scanner you wish to use. Don't forget to complete the Bluetooth pairing process on your device.</p>
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
                            <span x-text="showControlCodes ? 'Hide control codes' : 'Show control codes'"></span>
                        </button>

                        <flux:callout x-show="showControlCodes" x-transition.opacity variant="secondary" class="bg-white/10">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Enter Bluetooth Mode</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCodeAA') }}"
                                        alt="Nadamoo control QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Start Pairing</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCode99') }}"
                                        alt="Nadamoo control QR code"
                                        class="mx-auto w-full rounded bg-white p-2"
                                        loading="lazy"
                                    >
                                </div>
                                <div class="rounded-xl bg-black/20 p-3">
                                    <p class="mb-2 text-sm font-semibold text-current">Disable Storage Mode</p>
                                    <img
                                        src="{{ $this->qrDataUri('%%SpecCode10') }}"
                                        alt="Nadamoo control QR code"
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
                    <p class="text-lg font-bold">Place your cursor in the input below, then scan one code at a time</p>
                    <p class="text-current">This field accepts full QR URL payloads and six-digit serial numbers.</p>

                    <div class="max-w-2xl space-y-2">
                        <label for="singleton-scan-input" class="block text-sm font-semibold uppercase tracking-wide text-current">Scan Input</label>
                        <input
                            id="singleton-scan-input"
                            data-singleton-input
                            type="text"
                            inputmode="text"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            placeholder="Scan a QR code or type a 6-digit serial, then press Enter"
                            class="w-full rounded-xl border border-white/30 bg-white/85 px-4 py-3 text-base text-gray-950 placeholder:text-gray-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300/50"
                        >
                    </div>
                </flux:timeline.content>
            </flux:timeline.item>

            <flux:timeline.item>
                <flux:timeline.indicator>3</flux:timeline.indicator>
                <flux:timeline.content class="space-y-3">
                    <p class="text-lg font-bold">Scan results</p>
                    <p class="text-current">The panel updates after each scan and resets to ready automatically.</p>

                    <div data-singleton-feedback-panel class="rounded-2xl border border-white/40 bg-white/80 p-4 text-center text-gray-950 shadow-xl">
                        <p data-singleton-feedback-heading class="text-3xl font-black tracking-[0.2em] sm:text-4xl">READY</p>
                        <p data-singleton-feedback-name class="mt-2 hidden text-2xl font-semibold"></p>
                        <p data-singleton-feedback-detail class="mt-2 hidden text-sm text-gray-800"></p>
                    </div>

                    <section class="space-y-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-current">Recent scans</h2>
                        <ul data-singleton-log class="space-y-2 text-sm">
                            <li class="rounded-lg border border-white/40 bg-white/70 px-3 py-2 text-gray-900">No scans yet.</li>
                        </ul>
                    </section>
                </flux:timeline.content>
            </flux:timeline.item>
        </flux:timeline>
    </div>
</div>
