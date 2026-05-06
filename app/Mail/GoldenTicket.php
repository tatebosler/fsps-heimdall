<?php

namespace App\Mail;

use App\Helpers\DateHelpers;
use App\Helpers\GoldenTicketPdfGenerator;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoldenTicket extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(protected Ticket $ticket) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->ticket->year} Plant Sale Golden Ticket is Here!",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.golden-ticket',
            with: [
                'ticket' => $this->ticket,
                'year' => $this->ticket->year,
                'first_name' => $this->ticket->first_name,
                'presale_date' => $this->presaleDate()?->format('l, F j'),
                'presale_day' => $this->presaleDate()?->format('l'),
                'presale_open_time' => $this->formatPresaleTime('sale_open'),
                'presale_close_time' => $this->formatPresaleTime('sale_close'),
                'wristband_distribution_start_time' => $this->formatPresaleTime('wristbands_open'),
                'projected_off_bands_time' => $this->formatPresaleTime('wristbands_close'),
                'qrcode' => GoldenTicketPdfGenerator::qrCodePng($this->ticket),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => GoldenTicketPdfGenerator::binary($this->ticket),
                GoldenTicketPdfGenerator::filename($this->ticket),
            )->withMime('application/pdf'),
        ];
    }

    private function presaleDate(): ?CarbonImmutable
    {
        $calendarYear = $this->ticket->getCalendarYear();
        $thursday = DateHelpers::psDayForCalendarYear($calendarYear, DateHelpers::dayStringToNumber('Thursday'));

        return CarbonImmutable::instance($thursday);
    }

    private function formatPresaleTime(string $key): string
    {
        $keyMap = [
            'sale_open' => 'open',
            'sale_close' => 'close',
            'wristbands_open' => 'wristbands',
            'wristbands_close' => 'off_bands_estimate',
        ];

        $configKey = $keyMap[$key] ?? $key;
        $time = (string) config("ps.hours.Thursday.{$configKey}", '');

        if ($time === '') {
            return '';
        }

        $carbon = CarbonImmutable::createFromFormat('H:i', $time);

        if ($carbon === false) {
            return '';
        }

        return $carbon->format('g:i A');
    }
}
