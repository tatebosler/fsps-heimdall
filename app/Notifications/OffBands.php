<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class OffBands extends Notification implements ShouldQueue
{
    use Queueable;

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
        $message = "FSPS: We are off wristbands for the day!";

        if (array_key_exists(date('l'), config('ps.hours'))) {
            $close_time = config('ps.hours')[date('l')]['close'];
            $close_date_obj = Carbon::parse($close_time);
            $message .= " You can enter anytime until {$close_date_obj->format('g:i a')} today without getting a wristband first.";
        }

        return (new VonageMessage)->content("$message Thank you for shopping with us!");
    }
}
