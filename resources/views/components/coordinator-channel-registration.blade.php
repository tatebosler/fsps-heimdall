<?php

use App\Models\Channel;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.admin')] #[Title('Coordinator Channel Registration')] class extends Component
{
    public string $phoneNumbersText = '';

    public string $channelCodesText = '';

    /**
     * @var array{registered_phone_count: int, channel_count: int, subscriptions_added: int, users_created: int}|null
     */
    public ?array $registrationSummary = null;

    public function registerSubscribers(): void
    {
        $this->validate([
            'phoneNumbersText' => ['required', 'string', 'max:50000'],
            'channelCodesText' => ['required', 'string', 'max:50000'],
        ]);

        $channelIds = $this->validatedSpecialChannelIds();
        if ($channelIds === null) {
            return;
        }

        $phoneNumbers = $this->validatedPhoneNumbers();
        if ($phoneNumbers === null) {
            return;
        }

        foreach ($channelIds as $channelId) {
            Channel::firstOrCreate(['id' => $channelId]);
        }

        $usersCreated = 0;
        $subscriptionsAdded = 0;

        foreach ($phoneNumbers as $phoneNumber) {
            $user = User::firstOrCreate(['phone' => $phoneNumber]);
            if ($user->wasRecentlyCreated) {
                $usersCreated++;
            }

            $beforeCount = $user->channels()
                ->whereIn('channels.id', $channelIds)
                ->count();

            $user->channels()->syncWithoutDetaching($channelIds);

            $afterCount = $user->channels()
                ->whereIn('channels.id', $channelIds)
                ->count();

            $subscriptionsAdded += max(0, $afterCount - $beforeCount);
        }

        $this->registrationSummary = [
            'registered_phone_count' => count($phoneNumbers),
            'channel_count' => count($channelIds),
            'subscriptions_added' => $subscriptionsAdded,
            'users_created' => $usersCreated,
        ];
    }

    /**
     * @return array<int>|null
     */
    private function validatedSpecialChannelIds(): ?array
    {
        $rawCodes = $this->splitLines($this->channelCodesText);
        if ($rawCodes === []) {
            $this->addError('channelCodesText', 'Please provide at least one channel code.');

            return null;
        }

        $invalidCodes = [];
        $channelIds = [];

        foreach ($rawCodes as $rawCode) {
            if (! preg_match('/^\d+$/', $rawCode)) {
                $invalidCodes[] = $rawCode;

                continue;
            }

            $channelId = (int) $rawCode;
            if (! $this->isSpecialChannelCode($channelId)) {
                $invalidCodes[] = $rawCode;

                continue;
            }

            $channelIds[] = $channelId;
        }

        if ($invalidCodes !== []) {
            $this->addError('channelCodesText', 'Only special channel codes are allowed. Invalid values: '.implode(', ', array_unique($invalidCodes)));

            return null;
        }

        return array_values(array_unique($channelIds));
    }

    /**
     * @return array<string>|null
     */
    private function validatedPhoneNumbers(): ?array
    {
        $rawPhones = $this->splitLines($this->phoneNumbersText);
        if ($rawPhones === []) {
            $this->addError('phoneNumbersText', 'Please provide at least one phone number.');

            return null;
        }

        $invalidPhones = [];
        $phoneNumbers = [];

        foreach ($rawPhones as $rawPhone) {
            $normalizedPhone = $this->normalizePhoneNumber($rawPhone);

            if ($normalizedPhone === null) {
                $invalidPhones[] = $rawPhone;

                continue;
            }

            $phoneNumbers[] = $normalizedPhone;
        }

        if ($invalidPhones !== []) {
            $this->addError('phoneNumbersText', 'Some phone numbers are invalid: '.implode(', ', array_unique($invalidPhones)));

            return null;
        }

        return array_values(array_unique($phoneNumbers));
    }

    /**
     * @return array<string>
     */
    private function splitLines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    private function isSpecialChannelCode(int $channelId): bool
    {
        if ($channelId % 1000 < 900) {
            return false;
        }

        $suffix = str_pad((string) ($channelId % 100), 2, '0', STR_PAD_LEFT);

        return config()->has('ps.special_channel_suffixes.'.$suffix);
    }

    private function normalizePhoneNumber(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (preg_match('/^(?:[2-9](?:[02-9][0-9]|1[02-9])){2}[0-9]{4}$/', $digits) !== 1) {
            return null;
        }

        return $digits;
    }
};
?>

<div class="space-y-6 p-4 sm:px-8">
    <div class="space-y-2">
        <h1>Bulk Coordinator Channel Registration</h1>
        <p class="text-sm text-gray-700 dark:text-gray-300">Add coordinator phone numbers and subscribe them to special channel codes. Enter one item per line in each field.</p>
    </div>

    <form wire:submit="registerSubscribers" class="space-y-4 rounded-xl border border-gray-300/70 bg-white/70 p-4 dark:border-gray-700 dark:bg-gray-900/40">
        <div>
            <label for="registration-phone-list" class="mb-2 block text-sm font-semibold uppercase tracking-wide">Phone Numbers</label>
            <textarea
                id="registration-phone-list"
                wire:model="phoneNumbersText"
                rows="10"
                spellcheck="false"
                placeholder="(800) 221-1212"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-400"
            ></textarea>
            @error('phoneNumbersText')
                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="registration-channel-list" class="mb-2 block text-sm font-semibold uppercase tracking-wide">Special Channel Codes</label>
            <textarea
                id="registration-channel-list"
                wire:model="channelCodesText"
                rows="8"
                spellcheck="false"
                placeholder="37901"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-400"
            ></textarea>
            @error('channelCodesText')
                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-emerald-600 active:bg-emerald-500">
                <span class="fas fa-user-plus"></span>
                <span>Register Subscribers</span>
            </button>
        </div>
    </form>

    @if ($registrationSummary !== null)
        <div class="rounded-xl border border-emerald-300/70 bg-emerald-50 p-4 dark:border-emerald-700/70 dark:bg-emerald-950/30">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Registration Complete</h2>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-emerald-800/80 dark:text-emerald-200/80">Phone numbers processed</dt>
                    <dd class="text-lg font-bold text-emerald-900 dark:text-emerald-100">{{ $registrationSummary['registered_phone_count'] }}</dd>
                </div>
                <div>
                    <dt class="text-emerald-800/80 dark:text-emerald-200/80">Channels processed</dt>
                    <dd class="text-lg font-bold text-emerald-900 dark:text-emerald-100">{{ $registrationSummary['channel_count'] }}</dd>
                </div>
                <div>
                    <dt class="text-emerald-800/80 dark:text-emerald-200/80">New users created</dt>
                    <dd class="text-lg font-bold text-emerald-900 dark:text-emerald-100">{{ $registrationSummary['users_created'] }}</dd>
                </div>
                <div>
                    <dt class="text-emerald-800/80 dark:text-emerald-200/80">Subscriptions added</dt>
                    <dd class="text-lg font-bold text-emerald-900 dark:text-emerald-100">{{ $registrationSummary['subscriptions_added'] }}</dd>
                </div>
            </dl>
        </div>
    @endif
</div>
