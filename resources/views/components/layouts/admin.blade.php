<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#e11d48">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PS Hub">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="min-h-screen bg-gray-200 text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-200">
    <flux:sidebar collapsible="mobile" class="bg-gray-50 dark:bg-gray-950 lg:min-h-screen">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="/admin"
                logo="{{ asset('logo/rose-light.svg')}}"
                logo:dark="{{ asset('logo/rose-dark.svg')}}"
                name="FSPS Hub"
            />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group heading="Customer Entry" icon="arrow-right-end-on-rectangle">
                <flux:sidebar.item href="/wb" :current="request()->is('wb')">
                    <span class="fas fa-store"></span>
                    Wristband Booth
                </flux:sidebar.item>
                <flux:sidebar.item href="/tower" :current="request()->is('tower')">
                    <span class="fas fa-tower-observation"></span>
                    Tower
                </flux:sidebar.item>
                <flux:sidebar.item href="/admin/editor" :current="request()->is('admin/editor')">
                    <span class="fas fa-table-cells-large"></span>
                    Manual Data Editor
                </flux:sidebar.item>
                <flux:sidebar.item href="/admin/historical" :current="request()->is('admin/historical')">
                    <span class="fas fa-chart-line"></span>
                    Historical Data
                </flux:sidebar.item>
                <flux:sidebar.item href="/admin/entry-io" :current="request()->is('admin/entry-io')">
                    <span class="fas fa-file-arrow-up"></span>
                    Entry Data IO
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.spacer class="my-2" />

            <flux:sidebar.group heading="Golden Tickets" icon="ticket">
                <flux:sidebar.item href="/gtscanner" :current="request()->is('gtscan')">
                    <span class="fas fa-qrcode"></span>
                    Ticket Scanning
                </flux:sidebar.item>
                <flux:sidebar.item href="/admin/nadamoo-sync" :current="request()->is('admin/nadamoo-sync')" wire:navigate>
                    <span class="fas fa-upload"></span>
                    Sync from Nadamoo
                </flux:sidebar.item>
                <flux:sidebar.item href="/admin/serial-number-scan" :current="request()->is('admin/serial-number-scan')" wire:navigate>
                    <span class="fas fa-pen-to-square"></span>
                    Sync Serial Numbers
                </flux:sidebar.item>
                <flux:sidebar.item href="/admin/gtmanager" :current="request()->is('admin/gtmanager')">
                    <span class="fas fa-ticket"></span>
                    Ticket Management
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>
    </flux:sidebar>

    <flux:header class="lg:hidden bg-gray-100 dark:bg-gray-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:brand
            href="/admin"
            logo="{{ asset('logo/rose-light.svg')}}"
            logo:dark="{{ asset('logo/rose-dark.svg')}}"
            name="FSPS Hub"
        />
    </flux:header>

    <flux:main>
        <main>
            {{ $slot }}
        </main>
    </flux:main>

    @livewireScripts
    @fluxScripts
</body>
</html>
