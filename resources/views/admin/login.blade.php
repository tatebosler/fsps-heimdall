<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center bg-gray-100 px-6 py-12 dark:bg-gray-900">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg shadow-gray-200/70 ring-1 ring-gray-200 dark:bg-gray-800 dark:shadow-black/30 dark:ring-gray-700">
            <div class="mb-6 text-center">
                <x-logo horizontal class="mx-auto h-16 w-auto" />
            </div>

            <form method="POST" action="{{ route('admin.login.attempt') }}" autocomplete="off" class="space-y-5">
                @csrf

                <flux:field>
                    <flux:label>Enter the coordinator tools password to proceed</flux:label>
                    <flux:input type="password" name="potato12345" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" autofocus />
                    <flux:error name="potato12345" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full justify-center">
                    Unlock tools
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts.app>