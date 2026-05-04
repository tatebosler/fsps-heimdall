<?php

use App\Helpers\DateHelpers;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Entry Texting System')] class extends Component {
    public $stage = 'phone';
    public $phone = '';
    public $channelCode = '';

    public ?User $user = null;
    public $availableChannels = [];
    public $subscribedChannelIds = [];
    public int $offBandsChannelId = 0;

    public function goToNotificationsStage(): void
    {
        if ($this->isPhoneStageNextDisabled() or ! $this->isPhoneNanpValid()) {
            return;
        }

        $this->user = User::firstOrCreate(
            ['phone' => $this->phoneDigits()]
        );

        $this->setChannels();

        $this->stage = 'notifications';
    }

    public function mount(): void
    {
        $this->setChannels();
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

    public function setChannels(): void
    {
        $current_ps_year = $this->currentPsYear();

        $this->refreshAvailableChannels();

        if (! $this->user) {
            $this->subscribedChannelIds = [];

            return;
        }

        $this->subscribedChannelIds = [];
        foreach ($this->user->channels as $channel) {
            $channel_floor = (int) floor($channel->id / 1000) * 1000;
            if ($channel_floor === $current_ps_year) {
                $this->subscribedChannelIds[] = $channel->id;
            }
        }
    }

    public function refreshAvailableChannels(): void
    {
        $current_ps_year = $this->currentPsYear();
        $standard_channels = Channel::whereBetween('id', [$current_ps_year + 100, $current_ps_year + 800])
            ->whereNull('cleared_at')
            ->whereNotNull('distribution_started_at')
            ->orderBy('id')
            ->get();
        $off_bands_channel_id = $current_ps_year + date('N') * 10 + 900;

        Channel::firstOrCreate(['id' => $off_bands_channel_id]);

        $this->offBandsChannelId = $off_bands_channel_id;
        $this->availableChannels = $standard_channels->all();
    }

    private function currentPsYear(): int
    {
        return DateHelpers::psYearForDate(now()) * 1000;
    }

    public function subscribe(int $channelId): void
    {
        $current_ps_year = DateHelpers::psYearForDate(now()) * 1000;
        if ($channelId % 1000 < 100 || ($channelId % 1000 >= 900 && $channelId % 10 > 0) || (int) floor($channelId / 1000) * 1000 !== $current_ps_year) {
            return;
        }
        if (!in_array($channelId, $this->subscribedChannelIds)) {
            $this->subscribedChannelIds[] = $channelId;
        }
    }

    public function unsubscribe(int $channelId): void
    {
        $current_ps_year = DateHelpers::psYearForDate(now()) * 1000;
        if ((int) floor($channelId / 1000) * 1000 !== $current_ps_year) {
            return;
        }
        if (in_array($channelId, $this->subscribedChannelIds)) {
            $this->subscribedChannelIds = array_diff($this->subscribedChannelIds, [$channelId]);
        }
    }

    public function toggleSubscription(int $channelId): void
    {
        if (in_array($channelId, $this->subscribedChannelIds)) {
            $this->unsubscribe($channelId);
        } else {
            $this->subscribe($channelId);
        }
    }

    public function saveSubscriptions(): void
    {
        $this->user->channels()->sync($this->subscribedChannelIds);

        $this->stage = 'confirmation';
    }

    public function addChannelCode(): void
    {
        $current_ps_year = DateHelpers::psYearForDate(now()) * 1000;
        $channelId = (int) $this->channelCode;
        if ($channelId % 1000 < 900 || (int) floor($channelId / 1000) * 1000 !== $current_ps_year) {
            $this->channelCode = '';
            $this->modal('channel-code')->close();
            return;
        }

        $config_suffix = str_pad($channelId % 100, 2, '0', STR_PAD_LEFT);
        if (!config()->has('ps.special_channel_suffixes.' . $config_suffix)) {
            $this->channelCode = '';
            $this->modal('channel-code')->close();
            return;
        }

        $channel = Channel::firstOrCreate(['id' => $channelId]);

        if (!in_array($channel->id, $this->subscribedChannelIds)) {
            $this->subscribedChannelIds[] = $channel->id;
        }

        $this->channelCode = '';
        $this->modal('channel-code')->close();
    }

    #[Computed]
    public function specialChannelIds(): array
    {
        $current_ps_year = DateHelpers::psYearForDate(now()) * 1000;

        $specialChannelIds = array_filter(
            $this->subscribedChannelIds,
            fn (int $id): bool => ($id % 1000 >= 900 && ($id % 100 > 90 || $id % 10 > 0)) && (int) floor($id / 1000) * 1000 === $current_ps_year,
        );

        sort($specialChannelIds, SORT_NUMERIC);

        return array_values($specialChannelIds);
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
                        <p>By entering your phone number, you agree to receive automated text messages from Friends School of Minnesota with wristband group/waitlist alerts for the Friends School Plant Sale.</p>
                        <p>Your phone number will only be used to send you the wristband/waitlist alert messages you subscribe to. (For more details, see our <a href="{{ route('privacy') }}" class="text-emerald-500 hover:text-emerald-600 active:text-emerald-700 dark:text-emerald-300 hover:dark:text-emerald-200 active:dark:text-emerald-100">privacy policy</a> and <a href="{{ route('terms') }}" class="text-emerald-500 hover:text-emerald-600 active:text-emerald-700 dark:text-emerald-300 hover:dark:text-emerald-200 active:dark:text-emerald-100">terms of service</a>.) We'll delete your phone number from our records after the sale is over.</p>
                        <p>Please note that we are unable to send text messages to landline phone numbers, mobile numbers located outside the US or Canada, or some virtual numbers.</p>
                        <p>Finally, while the Plant Sale doesn't charge for this service, messaging and data rates from your carrier may apply.</p>
                    </div>
                    <flux:input mask="(999) 999-9999" type="tel" icon="phone" placeholder="Enter your cell phone number" autocomplete="mobile tel-national" wire:model.live.debounce.200ms="phone" :invalid="$hasPhoneNanpError" />
                    @if ($hasPhoneNanpError)
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">Please enter a valid US or Canada mobile phone number</p>
                    @endif

                    <div class="mt-auto pb-4 sm:pb-8">
                        <div class="mt-4 sm:mt-8 flex items-center gap-4 sm:gap-8">
                            <a href="{{ route('home') }}" class="bg-gray-800 hover:bg-gray-700 active:bg-gray-600 text-gray-100 px-4 py-2 rounded text-xl min-w-32 block"><span class="fas fa-times"></span> Cancel</a>
                            <button type="submit" class="block w-full ml-auto bg-emerald-800 hover:bg-emerald-700 active:bg-emerald-600 text-emerald-100 text-xl px-4 py-2 rounded disabled:cursor-not-allowed disabled:opacity-50" @disabled($this->isPhoneStageNextDisabled())>Next <span class="fas fa-arrow-right"></span></button>
                        </div>
                    </div>
                </form>
                @break

            @case('notifications')
                <div class="bg-gray-300 dark:bg-gray-700 p-2 sm:p-4 rounded-xl sm:text-xl mb-4">
                    <p>Managing texts for: <strong>{{ $this->formattedPhone() }}</strong></p>
                </div>
                <div class="flex flex-col items-start sm:flex-row">
                    <div class="space-y-1">
                        <h1>Select the notifications you'd like to receive</h1>
                        <p class="text-lg">Don't forget to press <em>Save</em> at the bottom once you're done.</p>
                        <p>To unsubscribe from all texts, deselect everything you see below and press <em>Save</em>, or reply <em>STOP</em> to any text message we send you.</p>
                    </div>
                    <button type="button" wire:click="$refresh" class="ml-auto bg-gray-800 hover:bg-gray-700 active:bg-gray-600 text-gray-100 px-4 py-2 rounded text-xl"><span class="fas fa-sync"></span> Refresh</button>
                </div>
                <div class="space-y-4" wire:poll="refreshAvailableChannels">
                    @if (in_array($offBandsChannelId, $subscribedChannelIds))
                        <div class="flex items-center gap-2 bg-{{ config('ps.colors.'.date('l')) }}-300 dark:bg-{{ config('ps.colors.'.date('l')) }}-700 p-2 sm:p-4 rounded-xl mt-4 cursor-pointer" wire:click="unsubscribe({{ $offBandsChannelId }})">
                            <span class="text-2xl fas fa-circle-check"></span>
                            <span class="text-xl">Text me when wristbands are no longer required for today</span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 bg-gray-300 dark:bg-gray-700 p-2 sm:p-4 rounded-xl mt-4 cursor-pointer" wire:click="subscribe({{ $offBandsChannelId }})">
                            <span class="text-2xl far fa-circle"></span>
                            <span class="text-xl">Text me when wristbands are no longer required for today</span>
                        </div>
                    @endif

                    @if (count($availableChannels))
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($availableChannels as $channel)
                                <div class="flex items-center gap-2 rounded-xl cursor-pointer bg-{{ in_array($channel->id, $subscribedChannelIds) ? config('ps.colors.'.date('l')) : 'gray' }}-300 dark:bg-{{ in_array($channel->id, $subscribedChannelIds) ? config('ps.colors.'.date('l')) : 'gray' }}-700 p-2 sm:p-4" wire:click="toggleSubscription({{ $channel->id }})">
                                    <span class="text-2xl {{ in_array($channel->id, $subscribedChannelIds) ? 'fas fa-circle-check' : 'far fa-circle' }}"></span>
                                    <div>
                                        <p class="text-xl">Group <span class="font-black">{{ $channel->id % 100 }}</span></p>
                                        <p class="text-sm">Estimated entry time {{ optional($channel->estimated_entry_at)->format('g:i A') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="bg-gray-300 dark:bg-gray-700 p-2 sm:p-4 rounded-xl sm:text-xl mb-4 text-center">
                            <p>There are no active wristband groups at the moment.</p>
                            <p>You can sign up for wristband-specific notificaitons once wristbands are distributed. If you're not seeing your wristband group, press <em>Refresh</em> in the upper right corner to check again.</p>
                        </div>
                    @endif
                    <flux:modal.trigger name="channel-code">
                        <div class="flex items-center gap-2 bg-gray-300 dark:bg-gray-700 p-2 sm:p-4 rounded-xl cursor-pointer mb-4">
                            <span class="text-2xl fas fa-bullhorn"></span>
                            <span class="text-xl">I have a Channel Code</span>
                        </div>
                    </flux:modal.trigger>
                    @foreach ($this->specialChannelIds as $channelId)
                        <div class="flex items-center gap-2 bg-{{ config('ps.colors.'.date('l')) }}-300 dark:bg-{{ config('ps.colors.'.date('l')) }}-700 p-2 sm:p-4 rounded-xl cursor-pointer" wire:click="unsubscribe({{ $channelId }})">
                            <span class="text-2xl fas fa-circle-check"></span>
                            <span class="text-xl">{{ Channel::find($channelId)->getDescription() }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-auto pb-4 sm:pb-8">
                    <div class="mt-4 sm:mt-8 gap-4 sm:gap-8 flex items-center">
                        <button type="button" class="bg-gray-800 hover:bg-gray-700 active:bg-gray-600 text-gray-100 px-4 py-2 rounded text-xl min-w-32" wire:click="$set('stage', 'phone')"><span class="fas fa-arrow-left"></span> Back</button>
                        <button type="button" class="block ml-auto w-full bg-emerald-800 hover:bg-emerald-700 active:bg-emerald-600 text-emerald-100 text-xl px-4 py-2 rounded disabled:cursor-not-allowed disabled:opacity-50" wire:click="saveSubscriptions">Save <span class="fas fa-arrow-right"></span></button>
                    </div>
                </div>

                <flux:modal name="channel-code" class="md:w-96">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Subscribe via Channel Code</flux:heading>
                            <flux:text class="mt-2">Plant Sale coordinators: if you have a Channel Code, you can use it to subscribe to special notification types.</flux:text>
                        </div>
                        <form wire:submit="addChannelCode" class="contents">

                            <flux:input type="tel" autocomplete="off" label="Channel Code" wire:model="channelCode" placeholder="Your Channel Code" />

                            <div class="flex mt-4">
                                <flux:spacer />

                                <flux:button type="submit" variant="primary">Add channel</flux:button>
                            </div>
                        </form>
                    </div>
                </flux:modal>
                @break

            @case('confirmation')
                <div class="text-center">
                    <span class="fas fa-circle-check text-6xl text-green-500 mb-4"></span>
                    <h1>Your subscriptions have been saved.</h1>
                    <p class="text-xl">We'll text you updates based on the notifications you've selected. You can return to this page at any time to update your preferences or unsubscribe from all messages.</p>
                </div>
                <a href="{{ route('estimates') }}" class="mt-8 inline-block dark:bg-{{ config('ps.colors.'.date('l')) }}-800 hover:dark:bg-{{ config('ps.colors.'.date('l')) }}-700 active:dark:bg-{{ config('ps.colors.'.date('l')) }}-600 dark:text-{{ config('ps.colors.'.date('l')) }}-100 bg-{{ config('ps.colors.'.date('l')) }}-200 hover:bg-{{ config('ps.colors.'.date('l')) }}-300 active:bg-{{ config('ps.colors.'.date('l')) }}-400 text-{{ config('ps.colors.'.date('l')) }}-900 p-4 rounded text-2xl"><span class="fas fa-arrow-right-to-bracket"></span> Track your estimated entry time</a>
                <a href="{{ route('home') }}" class="mt-4 inline-block dark:bg-gray-800 hover:dark:bg-gray-700 active:dark:bg-gray-600 dark:text-gray-100 bg-gray-200 hover:bg-gray-300 active:bg-gray-400 text-gray-900 p-4 rounded text-2xl"><span class="fas fa-home"></span> Return to homepage</a>
                @break
        @endswitch
    </div>
</div>
