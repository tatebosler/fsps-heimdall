<?php

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.admin')] #[Title('Nadamoo & Test Codes')] class extends Component
{
    /**
     * @var array<int, array{
     *     title:string,
     *     description:?string,
     *     codes:array<int, array{label:string, qr_data_uri:string, sequence:?int}>
     * }>
     */
    public array $codeGroups = [];

    public function mount(): void
    {
        $this->codeGroups = [
            [
                'title' => 'Test Codes',
                'description' => 'Each test case uses a newly randomized 4-digit suffix on page load.',
                'codes' => [
                    $this->buildCode('OK', $this->drivingTestUrl('TEST_OK_'.sprintf('%04d', random_int(0, 9999)))),
                    $this->buildCode('Group Zero', $this->drivingTestUrl('TEST_OK_GROUP_ZERO_'.sprintf('%04d', random_int(0, 9999)))),
                    $this->buildCode('Revoked', $this->drivingTestUrl('TEST_REVOKED_'.sprintf('%04d', random_int(0, 9999)))),
                    $this->buildCode('Invalid', $this->drivingTestUrl('TEST_INVALID_'.sprintf('%04d', random_int(0, 9999)))),
                    $this->buildCode('Already Scanned', $this->drivingTestUrl('TEST_ALREADY_SCANNED_'.sprintf('%04d', random_int(0, 9999)))),
                    $this->buildCode('Timeout', $this->drivingTestUrl('TEST_TIMEOUT_'.sprintf('%04d', random_int(0, 9999)))),
                ],
            ],
            [
                'title' => 'Nadamoo Reset Codes',
                'description' => 'Scan these in order to reset the scanner.',
                'codes' => [
                    $this->buildCode('Reset Step 1', '%%SpecCode93', 1),
                    $this->buildCode('Reset Step 2', '^#SC^303FFF0', 2),
                    $this->buildCode('Reset Step 3', '%%SpecCodeA8', 3),
                    $this->buildCode('Reset Step 4', '%%SpecCode7C', 4),
                    $this->buildCode('Reset Step 5', '%%SpecCode96', 5),
                ],
            ],
            [
                'title' => 'Nadamoo Bluetooth',
                'description' => null,
                'codes' => [
                    $this->buildCode('Enter Bluetooth Mode', '%%SpecCodeAA'),
                    $this->buildCode('Start Pairing', '%%SpecCode99'),
                ],
            ],
            [
                'title' => 'Nadamoo Storage Mode',
                'description' => null,
                'codes' => [
                    $this->buildCode('Enable Storage Mode', '%%SpecCode11'),
                    $this->buildCode('Disable Storage Mode', '%%SpecCode10'),
                    $this->buildCode('Clear Memory', '%%SpecCode18'),
                ],
            ],
        ];
    }

    private function drivingTestUrl(string $token): string
    {
        return 'https://friendsschoolplantsale.com/driving?tkt='.$token;
    }

    /**
     * @return array{label:string, qr_data_uri:string, sequence:?int}
     */
    private function buildCode(string $label, string $rawCode, ?int $sequence = null): array
    {
        return [
            'label' => $label,
            'qr_data_uri' => $this->qrDataUri($rawCode),
            'sequence' => $sequence,
        ];
    }

    private function qrDataUri(string $value): string
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
    <section class="space-y-2">
        <h1 class="text-2xl font-bold">Nadamoo &amp; Test Codes</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">Scan the labeled QR codes below. Raw code values are intentionally hidden.</p>
    </section>

    @foreach ($codeGroups as $group)
        <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">{{ $group['title'] }}</h2>

            @if ($group['description'])
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $group['description'] }}</p>
            @endif

            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($group['codes'] as $code)
                    <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <div class="mb-3 flex items-start justify-between gap-2">
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $code['label'] }}</h3>

                            @if ($code['sequence'] !== null)
                                <span class="rounded-full bg-zinc-900 px-2 py-0.5 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">
                                    Step {{ $code['sequence'] }}
                                </span>
                            @endif
                        </div>

                        <img
                            src="{{ $code['qr_data_uri'] }}"
                            alt="{{ $group['title'] }} - {{ $code['label'] }} QR code"
                            class="mx-auto w-full max-w-[260px] rounded bg-white p-2"
                            loading="lazy"
                        >
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
