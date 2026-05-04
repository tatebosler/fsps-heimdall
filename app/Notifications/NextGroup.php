<?php

namespace App\Notifications;

use App\Models\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class NextGroup extends Notification implements ShouldQueue
{
    use Queueable;

    private Channel $channel;

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
        return (new VonageMessage)->content("FSPS: It's almost your turn to shop - group {$group} is next to be admitted! Please make your way to the sale entrance. Thank you for shopping with us!")->clientReference("next_{$this->channel->id}");
    }
}
