<?php

use App\Models\Channel;
use App\Notifications\CoordinatorChannelBroadcastMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.admin')] #[Title('Coordinator Channel Broadcast')] class extends Component
{
    public string $channelCode = '';

    public string $message = '';

    public int $lastSentCount = 0;

    public function sendBroadcast(): void
    {
        $this->validate([
            'channelCode' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:140'],
        ]);

        $message = trim($this->message);
        if ($message === '') {
            $this->addError('message', 'Message cannot be blank.');

            return;
        }

        $channelId = (int) $this->channelCode;
        $channel = Channel::find($channelId);

        if (! $channel || ! $channel->isSpecial()) {
            $this->addError('channelCode', 'Channel code must exist and be a special channel code.');

            return;
        }

        $firehoseChannelId = ((int) floor($channel->id / 1000)) * 1000 + 999;
        $firehoseSubscribers = Channel::firstOrCreate(['id' => $firehoseChannelId])->subscribers;

        $recipients = $channel->subscribers
            ->merge($firehoseSubscribers)
            ->unique('id')
            ->values();

        $recipients->each(function ($subscriber) use ($channel, $message): void {
            $subscriber->notify(new CoordinatorChannelBroadcastMessage($channel->id, $message));
        });

        $this->lastSentCount = $recipients->count();
        $this->message = '';
    }
};
?>

<div class="space-y-6 p-4 sm:px-8">
    <div class="space-y-2">
        <h1>Coordinator Channel Broadcast</h1>
        <p class="text-sm text-gray-700 dark:text-gray-300">Send a 140-character text blast to one special channel plus that year&apos;s Firehose channel.</p>
    </div>

    <form wire:submit="sendBroadcast" class="space-y-4 rounded-xl border border-gray-300/70 bg-white/70 p-4 dark:border-gray-700 dark:bg-gray-900/40">
        <div>
            <label for="broadcast-channel-code" class="mb-2 block text-sm font-semibold uppercase tracking-wide">Special Channel Code</label>
            <input
                id="broadcast-channel-code"
                type="text"
                wire:model="channelCode"
                inputmode="numeric"
                autocomplete="off"
                placeholder="37901"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-400"
            >
            @error('channelCode')
                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="broadcast-message" class="mb-2 block text-sm font-semibold uppercase tracking-wide">Message (max 140 chars)</label>
            <textarea
                id="broadcast-message"
                wire:model.live="message"
                rows="5"
                maxlength="140"
                spellcheck="true"
                placeholder="Enter broadcast message"
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-400"
            ></textarea>
            <div class="mt-2 flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                <span>SMS body length should stay short for readability.</span>
                <span>{{ mb_strlen($this->message) }}/140</span>
            </div>
            @error('message')
                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-emerald-600 active:bg-emerald-500">
                <span class="fas fa-paper-plane"></span>
                <span>Send Broadcast</span>
            </button>
        </div>
    </form>

    @if ($lastSentCount > 0)
        <div class="rounded-xl border border-emerald-300/70 bg-emerald-50 p-4 text-sm dark:border-emerald-700/70 dark:bg-emerald-950/30">
            <p class="font-semibold text-emerald-900 dark:text-emerald-100">Broadcast sent to {{ $lastSentCount }} unique subscribers.</p>
        </div>
    @endif
</div>
