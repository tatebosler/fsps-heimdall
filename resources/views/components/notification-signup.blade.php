<?php

use App\Models\User;
use Livewire\Component;

new class extends Component {
    public $stage = 'phone';
    public $phone = '';

    public $user = null;

    public function goToNotificationsStage(): void
    {
        if ($this->isPhoneStageNextDisabled() or ! $this->isPhoneNanpValid()) {
            return;
        }

        $this->user = User::firstOrCreate(
            ['phone' => $this->phoneDigits()]
        );

        $this->stage = 'notifications';
    }

    private function formattedPhone(): string
    {
        $d = $this->phoneDigits();

        return '(' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6, 4);
    }

    private function phoneDigits(): string
    {
        return preg_replace('/\D+/', '', $this->phone) ?? '';
    }

    private function isPhoneFullLength(): bool
    {
        return strlen($this->phoneDigits()) === 10;
    }

    private function isPhoneNanpValid(): bool
    {
        return preg_match('/^(?:[2-9](?:[02-9][0-9]|1[02-9])){2}[0-9]{4}$/', $this->phoneDigits()) === 1;
    }

    public function hasPhoneNanpError(): bool
    {
        return $this->isPhoneFullLength() && ! $this->isPhoneNanpValid();
    }

    public function isPhoneStageNextDisabled(): bool
    {
        return $this->phoneDigits() === '' || ! $this->isPhoneNanpValid();
    }
};
?>

<div class="min-h-dvh flex flex-col">
    <div class="max-sm:mt-4 mb-4 sm:mb-8 flex max-sm:flex-col items-center sm:gap-4 px-4 sm:px-8">
        <x-logo horizontal class="h-24" />
        <h1 class="hidden sm:block ml-auto">Entry Texting System</h1>
    </div>

    <div class="px-4 sm:px-8 flex-1 flex flex-col">
        <div class="hidden sm:block mb-8">
            <flux:timeline horizontal>
                <flux:timeline.item status="{{ $stage === 'phone' ? 'current' : 'complete' }}">
                    <flux:timeline.indicator>
                        <flux:icon.phone variant="micro" />
                    </flux:timeline.indicator>

                    <flux:timeline.content>
                        <flux:heading>Enter phone number</flux:heading>
                    </flux:timeline.content>
                </flux:timeline.item>
                <flux:timeline.item status="{{ $stage === 'notifications' ? 'current' : ($stage === 'phone' ? 'incomplete' : 'complete') }}">
                    <flux:timeline.indicator>
                        <flux:icon.list-bullet variant="micro" />
                    </flux:timeline.indicator>

                    <flux:timeline.content>
                        <flux:heading>Select notifications</flux:heading>
                    </flux:timeline.content>
                </flux:timeline.item>
                <flux:timeline.item status="{{ $stage === 'confirmation' ? 'current' : 'incomplete' }}">
                    <flux:timeline.indicator>
                        <flux:icon.check variant="micro" />
                    </flux:timeline.indicator>

                    <flux:timeline.content>
                        <flux:heading>Subscription confirmed</flux:heading>
                    </flux:timeline.content>
                </flux:timeline.item>
            </flux:timeline>
        </div>

        @switch ($stage)
            @case('phone')
                @php
                    $hasPhoneNanpError = $this->hasPhoneNanpError();
                @endphp

                <form wire:submit="goToNotificationsStage" class="contents">
                    <div class="space-y-2 mb-4">
                        <h1>Enter your cell phone number to get started</h1>
                        <p>By entering your phone number, you agree to receive automated text messages from Friends School of Minnesota.</p>
                        <p>Your phone number will only be used to send you the messages you subscribe to. (For more details, see our <a href="{{ route('privacy') }}" class="text-emerald-500 hover:text-emerald-600 active:text-emerald-700 dark:text-emerald-300 hover:dark:text-emerald-200 active:dark:text-emerald-100">privacy policy</a> and <a href="{{ route('terms') }}" class="text-emerald-500 hover:text-emerald-600 active:text-emerald-700 dark:text-emerald-300 hover:dark:text-emerald-200 active:dark:text-emerald-100">terms of service</a>.) We'll delete your phone number from our records after the sale is over.</p>
                        <p>Please note that we are unable to send text messages to landline phone numbers, mobile numbers located outside the US or Canada, or some virtual numbers.</p>
                        <p>Finally, while the Plant Sale doesn't charge for this service, messaging and data rates from your carrier may apply.</p>
                    </div>
                    <flux:input mask="(999) 999-9999" type="tel" icon="phone" placeholder="Enter your cell phone number" autocomplete="mobile tel-national" wire:model.live.debounce.200ms="phone" :invalid="$hasPhoneNanpError" />
                    @if ($hasPhoneNanpError)
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">Please enter a valid US or Canada mobile phone number</p>
                    @endif

                    <div class="mt-auto pb-4 sm:pb-8">
                        <div class="mt-4 sm:mt-8 flex items-center gap-4">
                            <a href="{{ route('home') }}" class="bg-gray-800 hover:bg-gray-700 active:bg-gray-600 text-gray-100 px-4 py-2 rounded text-xl"><span class="fas fa-times"></span> Cancel</a>
                            <button type="submit" class="ml-auto bg-emerald-800 hover:bg-emerald-700 active:bg-emerald-600 text-emerald-100 text-xl px-4 py-2 rounded disabled:cursor-not-allowed disabled:opacity-50" @disabled($this->isPhoneStageNextDisabled())>Next &rarr;</button>
                        </div>
                    </div>
                </form>
                @break

            @case('notifications')
                <div class="bg-gray-300 dark:bg-gray-700 p-2 sm:p-4 rounded-xl sm:text-xl mb-4">
                    <p>Managing texts for: <strong>{{ $this->formattedPhone() }}</strong></p>
                </div>
                <h1>Select the notifications you'd like to receive</h1>
                @break

            @case('confirmation')
                <h1>You're all set! Here's what to expect next...</h1>
                @break
        @endswitch
    </div>
</div>
