<?php

namespace App\Notifications;

use App\Models\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class SignificantEstimateChange extends Notification implements ShouldQueue
{
    private Channel $channel;

    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['vonage'];
    }

    /**
     * Get the Vonage/SMS representation of the notification.
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        $group = $this->channel->id % 100;
        $newTime = $this->channel->estimated_entry_at->format('g:i a');
        $newTs = $this->channel->estimated_entry_at->format('U');
        return (new VonageMessage)->content("FSPS: Group {$group} has a new estimated entry time of {$newTime}. Check https://entry.friendsschoolplantsale.com/estimates for the latest updates.")->clientReference("ec_{$this->channel->id}_{$newTs}");
    }
}
