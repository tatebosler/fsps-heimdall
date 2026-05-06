<?php

namespace App\Mail;

use App\Helpers\DateHelpers;
use App\Helpers\GoldenTicketPdfGenerator;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoldenTicket extends Mailable
{
    use Queueable, SerializesModels;

    private const AWARD_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path fill="#111111" d="M341.9 38.1C328.5 29.9 311.6 29.9 298.2 38.1C273.8 53 258.7 57 230.1 56.4C214.4 56 199.8 64.5 192.2 78.3C178.5 103.4 167.4 114.5 142.3 128.2C128.5 135.7 120.1 150.4 120.4 166.1C121.1 194.7 117 209.8 102.1 234.2C93.9 247.6 93.9 264.5 102.1 277.9C117 302.3 121 317.4 120.4 346C120 361.7 128.5 376.3 142.3 383.9C164.4 396 175.6 406 187.4 425.4L138.7 522.5C132.8 534.4 137.6 548.8 149.4 554.7L235.4 597.7C246.9 603.4 260.9 599.1 267.1 587.9L319.9 492.8L372.7 587.9C378.9 599.1 392.9 603.5 404.4 597.7L490.4 554.7C502.3 548.8 507.1 534.4 501.1 522.5L452.5 425.3C464.2 405.9 475.5 395.9 497.6 383.8C511.4 376.3 519.8 361.6 519.5 345.9C518.8 317.3 522.9 302.2 537.8 277.8C546 264.4 546 247.5 537.8 234.1C522.9 209.7 518.9 194.6 519.5 166C519.9 150.3 511.4 135.7 497.6 128.1C472.5 114.4 461.4 103.3 447.7 78.2C440.2 64.4 425.5 56 409.8 56.3C381.2 57 366.1 52.9 341.7 38zM320 160C373 160 416 203 416 256C416 309 373 352 320 352C267 352 224 309 224 256C224 203 267 160 320 160z"/></svg>';

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
        $qrCodePayload = $this->qrCodeUrl();
        $qrCenterIcon = $this->qrCenterIcon();

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
                'qr_code_data' => $qrCodePayload,
                'qrcode' => $this->qrCodeImage($qrCodePayload),
                'qr_center_icon' => $qrCenterIcon['data'],
                'qr_center_icon_mime' => $qrCenterIcon['mime'],
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

    protected function qrCodeImage(string $payload): string
    {
        $qrCode = new QRCode([
            'eccLevel' => EccLevel::H,
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'addLogoSpace' => true,
            'logoSpaceWidth' => 17,
            'logoSpaceHeight' => 17,
        ]);

        return $qrCode->render($payload);
    }

    /**
     * @return array{data: string, mime: string}
     */
    protected function qrCenterIcon(): array
    {
        if (! empty($this->ticket->priority)) {
            return [
                'data' => self::AWARD_ICON_SVG,
                'mime' => 'image/svg+xml',
            ];
        }

        $logoData = file_get_contents(public_path('icons/icon-512x512.png'));

        return [
            'data' => is_string($logoData) ? $logoData : '',
            'mime' => 'image/png',
        ];
    }

    protected function qrCodeUrl(): string
    {
        return 'https://www.friendsschoolplantsale.com/driving?tkt='.base64_encode($this->ticket->serial_number);
    }
}
