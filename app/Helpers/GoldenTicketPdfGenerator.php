<?php

namespace App\Helpers;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use Fpdf\Fpdf;
use Symfony\Component\Process\Process;

class GoldenTicketPdfGenerator
{
    private const AWARD_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path fill="#111111" d="M341.9 38.1C328.5 29.9 311.6 29.9 298.2 38.1C273.8 53 258.7 57 230.1 56.4C214.4 56 199.8 64.5 192.2 78.3C178.5 103.4 167.4 114.5 142.3 128.2C128.5 135.7 120.1 150.4 120.4 166.1C121.1 194.7 117 209.8 102.1 234.2C93.9 247.6 93.9 264.5 102.1 277.9C117 302.3 121 317.4 120.4 346C120 361.7 128.5 376.3 142.3 383.9C164.4 396 175.6 406 187.4 425.4L138.7 522.5C132.8 534.4 137.6 548.8 149.4 554.7L235.4 597.7C246.9 603.4 260.9 599.1 267.1 587.9L319.9 492.8L372.7 587.9C378.9 599.1 392.9 603.5 404.4 597.7L490.4 554.7C502.3 548.8 507.1 534.4 501.1 522.5L452.5 425.3C464.2 405.9 475.5 395.9 497.6 383.8C511.4 376.3 519.8 361.6 519.5 345.9C518.8 317.3 522.9 302.2 537.8 277.8C546 264.4 546 247.5 537.8 234.1C522.9 209.7 518.9 194.6 519.5 166C519.9 150.3 511.4 135.7 497.6 128.1C472.5 114.4 461.4 103.3 447.7 78.2C440.2 64.4 425.5 56 409.8 56.3C381.2 57 366.1 52.9 341.7 38zM320 160C373 160 416 203 416 256C416 309 373 352 320 352C267 352 224 309 224 256C224 203 267 160 320 160z"/></svg>';

    private const TEMPLATE_PDF_PATH = 'app/private/template.pdf';

    private const TEMPLATE_PNG_PATH = 'app/private/template.png';

    private const AWARD_ICON_PNG_PATH = 'app/private/award-icon.png';

    private const PAGE_WIDTH = 612;

    private const PAGE_HEIGHT = 792;

    private const QR_X = 240;

    private const QR_Y = 477;

    private const QR_SIZE = 132;

    private const PRIORITY_BANNER_Y = 459;

    private const SERIAL_NUMBER_Y = 607;

    private const VOLUNTEER_NAME_Y = 631;

    public static function filename(Ticket $ticket): string
    {
        $serialNumber = preg_replace('/[^A-Za-z0-9_-]/', '-', $ticket->serial_number ?: (string) $ticket->id);
        $serialNumber = trim($serialNumber ?: 'ticket', '-');

        return "golden-ticket-{$ticket->year}-{$serialNumber}.pdf";
    }

    public static function binary(Ticket $ticket): string
    {
        $pdf = new Fpdf('P', 'pt', 'Letter');
        $pdf->SetCreator((string) config('app.name', 'Heimdall'));
        $pdf->SetAuthor((string) config('app.name', 'Heimdall'));
        $pdf->SetTitle('Golden Ticket '.($ticket->serial_number ?? (string) $ticket->id));
        $pdf->SetSubject('Golden Ticket');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $pdf->Image(self::templateBackgroundPath(), 0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, 'PNG');

        self::renderYear($pdf, $ticket);
        self::renderPresaleBar($pdf, $ticket);
        self::renderPriorityBanner($pdf, $ticket);
        self::renderQrCode($pdf, $ticket);
        self::renderSerialNumber($pdf, $ticket);
        self::renderVolunteerName($pdf, $ticket);

        $result = $pdf->Output('S');

        if (! is_string($result)) {
            throw new \RuntimeException('Unable to render Golden Ticket PDF.');
        }

        return $result;
    }

    public static function qrCodePng(Ticket $ticket): string
    {
        $qrUrl = 'https://www.friendsschoolplantsale.com/driving?tkt='.base64_encode((string) $ticket->serial_number);
        $qrPng = self::generateQrPng($qrUrl);

        return self::composeQrCenterIcon($qrPng, self::qrCenterIconPath($ticket));
    }

    private static function generateQrPng(string $data): string
    {
        $qrCode = new QRCode([
            'eccLevel' => EccLevel::H,
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'addLogoSpace' => true,
            'logoSpaceWidth' => 15,
            'logoSpaceHeight' => 15,
        ]);

        return (string) $qrCode->render($data);
    }

    private static function renderYear(Fpdf $pdf, Ticket $ticket): void
    {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', 'B', 64);
        $pdf->SetXY(340, 72);
        $pdf->Cell(248, 24, (string) $ticket->year, 0, 0, 'C');
    }

    private static function renderPresaleBar(Fpdf $pdf, Ticket $ticket): void
    {
        $text = self::presaleSummary($ticket);

        $fontSize = 16;
        $pdf->SetFont('Helvetica', 'B', $fontSize);

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(76, 147);
        $pdf->Cell(460, 12, $text, 0, 0, 'C');
    }

    private static function renderPriorityBanner(Fpdf $pdf, Ticket $ticket): void
    {
        if (! $ticket->priority) {
            return;
        }

        $pdf->SetFillColor(107, 33, 168);
        $pdf->Rect(186, self::PRIORITY_BANNER_Y, 240, 18, 'F');

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(186, self::PRIORITY_BANNER_Y + 4);
        $pdf->Cell(240, 10, 'GROUP ZERO', 0, 0, 'C');
    }

    private static function renderQrCode(Fpdf $pdf, Ticket $ticket): void
    {
        $qrTmp = tempnam(sys_get_temp_dir(), 'gt_qr_');

        if ($qrTmp === false) {
            throw new \RuntimeException('Unable to create temporary QR code file.');
        }

        try {
            $qrPng = self::qrCodePng($ticket);
            file_put_contents($qrTmp, $qrPng);
            $pdf->Image($qrTmp, self::QR_X, self::QR_Y, self::QR_SIZE, self::QR_SIZE, 'PNG');
        } finally {
            @unlink($qrTmp);
        }
    }

    private static function renderSerialNumber(Fpdf $pdf, Ticket $ticket): void
    {
        $pdf->SetFont('Helvetica', 'B', 26);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(0, self::SERIAL_NUMBER_Y);
        $pdf->Cell(self::PAGE_WIDTH, 18, (string) $ticket->serial_number, 0, 0, 'C');
    }

    private static function renderVolunteerName(Fpdf $pdf, Ticket $ticket): void
    {
        $displayName = $ticket->getDisplayName() ?? 'Anonymous';

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(0, self::VOLUNTEER_NAME_Y);
        $pdf->Cell(self::PAGE_WIDTH, 11, $displayName, 0, 0, 'C');
    }

    private static function composeQrCenterIcon(string $qrPng, string $iconPath): string
    {
        if (! function_exists('imagecreatefromstring') || ! is_file($iconPath)) {
            return $qrPng;
        }

        $qrImage = imagecreatefromstring($qrPng);
        $iconData = file_get_contents($iconPath);

        if ($qrImage === false || ! is_string($iconData)) {
            if ($qrImage !== false) {
                imagedestroy($qrImage);
            }

            return $qrPng;
        }

        $iconImage = imagecreatefromstring($iconData);

        if ($iconImage === false) {
            imagedestroy($qrImage);

            return $qrPng;
        }

        imagealphablending($qrImage, true);
        imagesavealpha($qrImage, true);
        imagealphablending($iconImage, true);
        imagesavealpha($iconImage, true);

        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);
        $backingSize = (int) round(min($qrWidth, $qrHeight) * (34 / 132));
        $iconSize = (int) round(min($qrWidth, $qrHeight) * (24 / 132));
        $backingX = (int) round(($qrWidth - $backingSize) / 2);
        $backingY = (int) round(($qrHeight - $backingSize) / 2);
        $iconX = (int) round(($qrWidth - $iconSize) / 2);
        $iconY = (int) round(($qrHeight - $iconSize) / 2);
        $white = imagecolorallocate($qrImage, 255, 255, 255);

        imagefilledrectangle(
            $qrImage,
            $backingX,
            $backingY,
            $backingX + $backingSize,
            $backingY + $backingSize,
            $white,
        );

        imagecopyresampled(
            $qrImage,
            $iconImage,
            $iconX,
            $iconY,
            0,
            0,
            $iconSize,
            $iconSize,
            imagesx($iconImage),
            imagesy($iconImage),
        );

        ob_start();
        imagepng($qrImage);
        $rendered = ob_get_clean();

        imagedestroy($iconImage);
        imagedestroy($qrImage);

        return is_string($rendered) ? $rendered : $qrPng;
    }

    private static function presaleSummary(Ticket $ticket): string
    {
        $presaleDate = CarbonImmutable::instance(
            DateHelpers::psDayForCalendarYear($ticket->year, DateHelpers::dayStringToNumber('Thursday'))
        );

        $hours = (array) config('ps.hours.Thursday', []);
        $saleOpen = self::formatHour((string) ($hours['open'] ?? '14:30'));
        $saleClose = self::formatHour((string) ($hours['close'] ?? '20:30'));

        return sprintf(
            '%s | %s - %s | Minnesota State Fair Grandstand',
            $presaleDate->format('l, F j, Y'),
            $saleOpen,
            $saleClose,
        );
    }

    private static function formatHour(string $time): string
    {
        $formatted = CarbonImmutable::createFromFormat('H:i', $time);

        return $formatted === false ? $time : $formatted->format('g:i A');
    }

    private static function templateBackgroundPath(): string
    {
        $templatePngPath = storage_path(self::TEMPLATE_PNG_PATH);
        $templatePdfPath = storage_path(self::TEMPLATE_PDF_PATH);

        if (is_file($templatePngPath) && filemtime($templatePngPath) >= filemtime($templatePdfPath)) {
            return $templatePngPath;
        }

        if (! is_file($templatePdfPath)) {
            throw new \RuntimeException('Golden Ticket template PDF is missing.');
        }

        $process = new Process([
            'sips',
            '-s',
            'format',
            'png',
            $templatePdfPath,
            '--out',
            $templatePngPath,
        ]);

        $process->run();

        if (! $process->isSuccessful() || ! is_file($templatePngPath)) {
            throw new \RuntimeException('Unable to render Golden Ticket template PNG from PDF.');
        }

        return $templatePngPath;
    }

    private static function qrCenterIconPath(Ticket $ticket): string
    {
        if (! $ticket->priority) {
            return public_path('icons/icon-512x512.png');
        }

        return self::awardIconPath();
    }

    private static function awardIconPath(): string
    {
        $awardIconPngPath = storage_path(self::AWARD_ICON_PNG_PATH);

        if (is_file($awardIconPngPath)) {
            return $awardIconPngPath;
        }

        $awardIconSvgBasePath = tempnam(sys_get_temp_dir(), 'gt_award_svg_');

        if ($awardIconSvgBasePath === false) {
            throw new \RuntimeException('Unable to create temporary award SVG file.');
        }

        $awardIconSvgPath = $awardIconSvgBasePath.'.svg';
        rename($awardIconSvgBasePath, $awardIconSvgPath);

        try {
            file_put_contents($awardIconSvgPath, self::AWARD_ICON_SVG);

            $process = new Process([
                'sips',
                '-s',
                'format',
                'png',
                $awardIconSvgPath,
                '--out',
                $awardIconPngPath,
            ]);

            $process->run();

            if (! $process->isSuccessful() || ! is_file($awardIconPngPath)) {
                throw new \RuntimeException('Unable to render award icon PNG from SVG.');
            }
        } finally {
            @unlink($awardIconSvgPath);
        }

        return $awardIconPngPath;
    }
}
