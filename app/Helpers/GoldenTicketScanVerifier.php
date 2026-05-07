<?php

namespace App\Helpers;

use App\Models\ScanLog;
use App\Models\Ticket;
use Carbon\CarbonInterface;

class GoldenTicketScanVerifier
{
    /**
     * @return array{status: string, first_name: ?string, message: string}
     */
    public function scan(string $qrCode, ?string $dataSource = null, bool $honorDuplicateGracePeriod = true): array
    {
        $ticketValue = $this->extractTicketValue($qrCode);

        if ($ticketValue === null) {
            return $this->scanPayload('INVALID');
        }

        $testResponse = $this->resolveTestTicketResponse($ticketValue);

        if ($testResponse !== null) {
            return $testResponse;
        }

        $serialNumber = $this->decodeSerialNumber($ticketValue);

        if ($serialNumber === null) {
            return $this->scanPayload('INVALID');
        }

        $ticket = $this->findTicketBySerial($serialNumber);

        if ($ticket === null) {
            return $this->scanPayload('INVALID');
        }

        return $this->scanTicket($ticket, $dataSource, $honorDuplicateGracePeriod);
    }

    /**
     * @return array{status: string, first_name: ?string, message: string}
     */
    public function scanSerialNumber(string $serialNumber, ?string $dataSource = null, bool $honorDuplicateGracePeriod = true): array
    {
        $ticket = $this->findTicketBySerial($serialNumber);

        if ($ticket === null) {
            return $this->scanPayload('INVALID');
        }

        return $this->scanTicket($ticket, $dataSource, $honorDuplicateGracePeriod);
    }

    /**
     * @param  array<int, string>  $qrCodes
     * @return array{results: array<int, array{line: int, qr_code: string, status: string, first_name: ?string, message: string}>, summary: array{total: int, success: int, invalid: int, revoked: int, already_scanned: int, duplicate_in_import: int, counts: array<string, int>}}
     */
    public function scanMany(array $qrCodes, ?string $dataSource = null): array
    {
        $dataSource = $dataSource ?? 'Mobile QR Scanning';
        $results = [];
        $counts = [];
        $processedSerials = [];

        foreach (array_values($qrCodes) as $index => $qrCode) {
            $scanInput = trim($qrCode);
            $isSerialInput = preg_match('/^\d{6}$/', $scanInput) === 1;

            $ticketValue = $isSerialInput ? null : $this->extractTicketValue($scanInput);
            $serialNumber = $isSerialInput
                ? $scanInput
                : ($ticketValue !== null ? $this->decodeSerialNumber($ticketValue) : null);

            // Check for duplicates within this import
            if ($serialNumber !== null && isset($processedSerials[$serialNumber])) {
                $status = 'DUPLICATE_IN_IMPORT';
                $firstName = null;
                $result = $this->scanPayload($status, $firstName);

                $ticket = $this->findTicketBySerial($serialNumber);

                if ($ticket !== null) {
                    $this->recordScanLog($ticket, $status);
                }

                $counts[$status] = ($counts[$status] ?? 0) + 1;

                $results[] = [
                    'line' => $index + 1,
                    'qr_code' => $qrCode,
                    ...$result,
                ];

                continue;
            }

            // Process the scan normally
            $result = $isSerialInput
                ? $this->scanSerialNumber($scanInput, $dataSource, false)
                : $this->scan($scanInput, $dataSource, false);
            $status = $result['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            // Track this serial number as processed
            if ($serialNumber !== null) {
                $processedSerials[$serialNumber] = true;
            }

            $results[] = [
                'line' => $index + 1,
                'qr_code' => $qrCode,
                ...$result,
            ];
        }

        return [
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => ($counts['OK'] ?? 0) + ($counts['OK_GROUP_ZERO'] ?? 0),
                'invalid' => $counts['INVALID'] ?? 0,
                'revoked' => $counts['REVOKED'] ?? 0,
                'already_scanned' => $counts['ALREADY_SCANNED'] ?? 0,
                'duplicate_in_import' => $counts['DUPLICATE_IN_IMPORT'] ?? 0,
                'counts' => $counts,
            ],
        ];
    }

    protected function findTicketBySerial(string $serialNumber): ?Ticket
    {
        return Ticket::query()
            ->where('ps_year', DateHelpers::psYearForDate(now()))
            ->where('serial', $serialNumber)
            ->first();
    }

    /**
     * @return array{status: string, first_name: ?string, message: string}
     */
    protected function scanTicket(Ticket $ticket, ?string $dataSource, bool $honorDuplicateGracePeriod): array
    {
        if ($ticket->revoked_at !== null) {
            $result = $this->scanPayload('REVOKED', $ticket->first_name);
            $this->recordScanLog($ticket, $result['status']);

            return $result;
        }

        if ($this->shouldRejectAsDuplicate($ticket, $honorDuplicateGracePeriod)) {
            $result = $this->scanPayload('ALREADY_SCANNED', $ticket->first_name, $ticket->scanned_at);
            $this->recordScanLog($ticket, $result['status']);

            return $result;
        }

        if ($ticket->scanned_at === null) {
            $dataSource = $dataSource ?? 'Mobile QR Scanning';
            $ticket->forceFill([
                'scanned_at' => now(),
                'scanned_by' => $dataSource,
            ])->save();
        }

        $result = $this->scanPayload($ticket->group_zero ? 'OK_GROUP_ZERO' : 'OK', $ticket->first_name);
        $this->recordScanLog($ticket, $result['status']);

        return $result;
    }

    protected function recordScanLog(Ticket $ticket, string $status): void
    {
        ScanLog::query()->create([
            'ticket_id' => $ticket->id,
            'scanned_at' => now(),
            'result' => $status,
        ]);
    }

    protected function extractTicketValue(string $qrCode): ?string
    {
        $urlParts = parse_url(trim($qrCode));

        if (! is_array($urlParts)) {
            return null;
        }

        if (($urlParts['scheme'] ?? null) !== 'https') {
            return null;
        }

        $host = strtolower((string) ($urlParts['host'] ?? ''));

        if (! in_array($host, ['friendsschoolplantsale.com', 'www.friendsschoolplantsale.com'], true)) {
            return null;
        }

        if (($urlParts['path'] ?? '') !== '/driving') {
            return null;
        }

        parse_str($urlParts['query'] ?? '', $query);

        $ticketValue = trim((string) ($query['tkt'] ?? ''));

        return $ticketValue !== '' ? $ticketValue : null;
    }

    /**
     * @return array{status: string, first_name: ?string, message: string}|null
     */
    protected function resolveTestTicketResponse(string $ticketValue): ?array
    {
        if (preg_match('/^TEST_([A-Z_]+)_\d{4}$/', strtoupper($ticketValue), $matches) !== 1) {
            return null;
        }

        return match ($matches[1]) {
            'OK' => $this->scanPayload('OK', 'Test'),
            'OK_GROUP_ZERO', 'GROUP_ZERO', 'PRIORITY' => $this->scanPayload('OK_GROUP_ZERO', 'Test'),
            'REVOKED' => $this->scanPayload('REVOKED'),
            'ALREADY_SCANNED' => $this->scanPayload('ALREADY_SCANNED'),
            default => $this->scanPayload('INVALID'),
        };
    }

    protected function decodeSerialNumber(string $ticketValue): ?string
    {
        $normalizedValue = strtr($ticketValue, '-_', '+/');
        $padding = strlen($normalizedValue) % 4;

        if ($padding !== 0) {
            $normalizedValue .= str_repeat('=', 4 - $padding);
        }

        $decodedValue = base64_decode($normalizedValue, true);

        if ($decodedValue === false) {
            return null;
        }

        $serialNumber = trim($decodedValue);

        return preg_match('/^\d{6}$/', $serialNumber) === 1
            ? $serialNumber
            : null;
    }

    protected function shouldRejectAsDuplicate(Ticket $ticket, bool $honorDuplicateGracePeriod): bool
    {
        if ($ticket->scanned_at === null) {
            return false;
        }

        if (! $honorDuplicateGracePeriod) {
            return true;
        }

        return ! $ticket->scanned_at->gt(now()->subSeconds(30));
    }

    /**
     * @return array{status: string, first_name: ?string, message: string}
     */
    protected function scanPayload(string $status, ?string $firstName = null, ?CarbonInterface $scannedAt = null): array
    {
        $normalizedFirstName = blank($firstName) ? null : trim((string) $firstName);

        $message = match (true) {
            in_array($status, ['OK', 'OK_GROUP_ZERO'], true) && $normalizedFirstName !== null => $status.' '.$normalizedFirstName,
            $status === 'ALREADY_SCANNED' && $scannedAt !== null => 'Scanned on '.$scannedAt->format('M j, Y g:i:s A'),
            default => $status,
        };

        return [
            'status' => $status,
            'first_name' => $normalizedFirstName,
            'message' => $message,
        ];
    }
}
