<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('QR Code Scanner')] class extends Component
{
    public bool $scanning = false;
};
?>

<div data-qr-scanner-root data-qr-verify-url="{{ route('golden-tickets.scan') }}" class="min-h-screen bg-gray-950 text-white transition-colors duration-300">
    <div id="toolbar" class="flex items-center gap-2 px-3 py-3 shadow-sm">
        <button
            type="button"
            data-qr-activate-sfx
            class="flex h-12 items-center gap-2 rounded-lg bg-amber-300 px-4 text-sm font-semibold text-amber-950 transition hover:bg-amber-200"
        >
            <span class="fas fa-volume-high text-lg"></span>
            <span>Activate Sound Effects</span>
        </button>

        <button
            type="button"
            data-qr-toggle
            aria-pressed="false"
            class="flex h-12 items-center gap-2 rounded-lg bg-gray-200 px-4 text-sm font-semibold text-gray-900 transition hover:bg-white"
        >
            <span data-qr-toggle-icon class="fas fa-power-off text-lg"></span>
            <span data-qr-toggle-label>Start scanner</span>
        </button>
    </div>

    <div class="flex flex-col gap-4 px-4 pb-4">
        <div wire:ignore class="overflow-hidden rounded-2xl border border-gray-800 bg-black shadow-2xl">
            <video
                data-qr-video
                class="h-[60vh] min-h-80 w-full bg-black object-cover sm:h-auto sm:aspect-video"
                playsinline
                muted
            ></video>
        </div>

        <div data-qr-feedback-panel class="rounded-3xl border border-white/15 bg-black/25 px-6 py-8 text-center shadow-2xl backdrop-blur-sm">
            <p data-qr-feedback-heading class="text-4xl font-black tracking-[0.2em] sm:text-6xl">READY</p>
            <p data-qr-feedback-name class="mt-3 hidden text-2xl font-semibold sm:text-4xl"></p>
            <p data-qr-feedback-detail class="mt-3 hidden text-sm text-white/80 sm:text-base"></p>

            <button
                type="button"
                data-qr-acknowledge
                class="mt-6 hidden rounded-xl bg-white px-5 py-3 text-base font-semibold text-gray-900 shadow-lg transition hover:bg-gray-100"
            >
                Acknowledge
            </button>
        </div>
    </div>
</div>
