<?php

namespace App\Helpers;

use App\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use SplFileObject;

class VolunteerTicketCsvImporter
{
    /**
     * @var array<string, string>
     */
    private const REQUIRED_COLUMNS = [
        'job' => 'Job',
        'shiftstartdate' => 'Shift start date',
        'shiftstarttime' => 'Shift start time',
    ];

    /**
     * @return array{
     *     rows_read:int,
     *     tickets_created:int,
     *     tickets_updated:int,
     *     invalid_rows:int,
     *     errors:array<int, string>
     * }
     */
    public function import(string $csvPath): array
    {
        if (! is_file($csvPath)) {
            throw new InvalidArgumentException("CSV file not found at path: {$csvPath}");
        }

        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        [$headers, $indexByKey] = $this->readHeaders($file);
        $this->ensureColumnsExist($indexByKey);

        $summary = [
            'rows_read' => 0,
            'tickets_created' => 0,
            'tickets_updated' => 0,
            'invalid_rows' => 0,
            'errors' => [],
        ];

        foreach ($file as $index => $row) {
            if ($index === 0) {
                continue;
            }

            if (! is_array($row) || $this->isBlankRow($row)) {
                continue;
            }

            $summary['rows_read']++;
            $lineNumber = (int) $index + 1;

            try {
                $record = $this->mapRowToHeaders($headers, $row);
                $this->upsertTicketFromRecord($record, $summary);
            } catch (\Throwable $throwable) {
                $summary['invalid_rows']++;
                $summary['errors'][] = "Line {$lineNumber}: {$throwable->getMessage()}";
            }
        }

        return $summary;
    }

    /**
     * @return array{0:array<int, string>, 1:array<string, int>}
     */
    private function readHeaders(SplFileObject $file): array
    {
        $headers = $file->fgetcsv();

        if (! is_array($headers)) {
            throw new InvalidArgumentException('CSV file is empty or unreadable.');
        }

        $normalizedHeaders = collect($headers)
            ->map(fn (mixed $header): string => $this->sanitizeCsvValue((string) $header))
            ->map(fn (string $header): string => strtolower(trim($header)))
            ->values()
            ->all();

        if (isset($normalizedHeaders[0])) {
            $normalizedHeaders[0] = $this->stripUtfBom($normalizedHeaders[0]);
        }

        if ($normalizedHeaders === [] || $normalizedHeaders === ['']) {
            throw new InvalidArgumentException('CSV file does not contain a valid header row.');
        }

        /** @var array<string, int> $indexByKey */
        $indexByKey = [];

        foreach ($normalizedHeaders as $index => $header) {
            $key = $this->canonicalizeHeader($header);
            if ($key !== '' && ! array_key_exists($key, $indexByKey)) {
                $indexByKey[$key] = $index;
            }
        }

        return [$normalizedHeaders, $indexByKey];
    }

    /**
     * @param  array<string, int>  $indexByKey
     */
    private function ensureColumnsExist(array $indexByKey): void
    {
        $missing = collect(array_keys(self::REQUIRED_COLUMNS))
            ->filter(fn (string $key): bool => ! array_key_exists($key, $indexByKey))
            ->map(fn (string $key): string => self::REQUIRED_COLUMNS[$key])
            ->values()
            ->all();

        if ($missing !== []) {
            throw new InvalidArgumentException('CSV is missing required columns: '.implode(', ', $missing));
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, string>
     */
    private function mapRowToHeaders(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $key = $this->canonicalizeHeader($header);
            if ($key === '') {
                continue;
            }

            $mapped[$key] = isset($row[$index]) ? trim($this->sanitizeCsvValue((string) $row[$index])) : '';
        }

        return $mapped;
    }

    /**
     * @param  array<string, string>  $record
     * @param  array{
     *     rows_read:int,
     *     tickets_created:int,
     *     tickets_updated:int,
     *     invalid_rows:int,
     *     errors:array<int, string>
     * }  $summary
     */
    private function upsertTicketFromRecord(array $record, array &$summary): void
    {
        $vlid = $this->normalizeVlid($record['volunteeridentifier'] ?? '');
        $firstName = $this->normalizeName($record['firstname'] ?? '');
        $lastName = $this->normalizeName($record['lastname'] ?? '');
        $phone = $this->normalizePhone($record['phonemobilepreferred'] ?? '');
        $email = $this->normalizeEmail($record['email'] ?? '');
        $zip = $this->normalizeZip($record['zipcode'] ?? '');

        if ($vlid === null && ($firstName === null || $lastName === null || ($phone === null && $email === null))) {
            throw new InvalidArgumentException('Volunteer identity is missing: provide Volunteer Identifier or First Name + Last Name + Phone/Email.');
        }

        $shift = $this->parseShift($record);
        $psYear = $shift === null
            ? DateHelpers::psYearForDate(now())
            : DateHelpers::psYearForDate(Carbon::parse($shift['start'])->setTimezone(config('app.timezone')));

        $ticket = $this->findMatchingTicket($psYear, $vlid, $firstName, $lastName, $phone, $email);

        if (! $ticket) {
            $ticket = new Ticket;
            $ticket->ps_year = $psYear;
        }

        $isNewTicket = ! $ticket->exists;

        $ticket->ps_year = $psYear;
        $ticket->vlid = $vlid ?? $ticket->vlid;
        $ticket->first_name = $this->mergeString($ticket->first_name, $firstName);
        $ticket->last_name = $this->mergeString($ticket->last_name, $lastName);
        $ticket->phone = $this->mergeString($ticket->phone, $phone);
        $ticket->email = $this->mergeString($ticket->email, $email);
        $ticket->zip = $this->mergeString($ticket->zip, $zip);

        $existingShifts = $this->normalizeExistingShifts($ticket->shifts);
        $allShifts = $shift === null ? $existingShifts : $this->mergeShifts($existingShifts, [$shift]);

        $ticket->shifts = $allShifts === [] ? null : $allShifts;
        $ticket->group_zero = $this->isGroupZeroEligible($allShifts);

        if (! is_string($ticket->serial) || strlen(trim($ticket->serial)) !== 6) {
            $ticket->serial = $this->generateUniqueSerial($psYear, $ticket->group_zero);
        }

        $ticket->save();

        if ($isNewTicket) {
            $summary['tickets_created']++;
        } else {
            $summary['tickets_updated']++;
        }
    }

    private function findMatchingTicket(
        int $psYear,
        ?string $vlid,
        ?string $firstName,
        ?string $lastName,
        ?string $phone,
        ?string $email,
    ): ?Ticket {
        if ($vlid !== null) {
            $ticketByVlid = Ticket::query()
                ->where('ps_year', $psYear)
                ->where('vlid', $vlid)
                ->first();

            if ($ticketByVlid) {
                return $ticketByVlid;
            }
        }

        if ($firstName === null || $lastName === null || ($phone === null && $email === null)) {
            return null;
        }

        return Ticket::query()
            ->where('ps_year', $psYear)
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower($firstName)])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [strtolower($lastName)])
            ->when(
                $phone !== null,
                fn (Builder $query): Builder => $query->where('phone', $phone),
                fn (Builder $query): Builder => $query->whereRaw('LOWER(TRIM(email)) = ?', [strtolower((string) $email)])
            )
            ->first();
    }

    /**
     * @param  array<string, string>  $record
     * @return array{job:string,start:string,end:string}|null
     */
    private function parseShift(array $record): ?array
    {
        $shiftDate = trim((string) ($record['shiftstartdate'] ?? ''));
        $shiftStart = trim((string) ($record['shiftstarttime'] ?? ''));
        $shiftEnd = trim((string) ($record['shiftendtime'] ?? ''));

        if ($shiftDate === '' && $shiftStart === '' && $shiftEnd === '') {
            return null;
        }

        if ($shiftDate === '' || $shiftStart === '') {
            throw new InvalidArgumentException('Shift start date and shift start time are required when any shift field is present.');
        }

        $timezone = config('app.timezone', 'America/Chicago');

        $start = Carbon::parse("{$shiftDate} {$shiftStart}", $timezone);

        if ($shiftEnd === '') {
            $end = $start->copy()->addHours(4);
        } else {
            $end = Carbon::parse("{$shiftDate} {$shiftEnd}", $timezone);

            if ($end->lessThan($start)) {
                $end->addDay();
            }
        }

        return [
            'job' => trim((string) ($record['job'] ?? '')),
            'start' => $start->copy()->utc()->toIso8601String(),
            'end' => $end->copy()->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{job:string,start:string,end:string}>
     */
    private function normalizeExistingShifts(mixed $shifts): array
    {
        if (! is_array($shifts)) {
            return [];
        }

        $normalized = [];

        foreach ($shifts as $shift) {
            if (! is_array($shift)) {
                continue;
            }

            $job = isset($shift['job']) ? trim((string) $shift['job']) : '';
            $start = isset($shift['start']) ? trim((string) $shift['start']) : '';
            $end = isset($shift['end']) ? trim((string) $shift['end']) : '';

            if ($start === '' || $end === '') {
                continue;
            }

            $normalized[] = [
                'job' => $job,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $this->mergeShifts([], $normalized);
    }

    /**
     * @param  array<int, array{job:string,start:string,end:string}>  $existing
     * @param  array<int, array{job:string,start:string,end:string}>  $incoming
     * @return array<int, array{job:string,start:string,end:string}>
     */
    private function mergeShifts(array $existing, array $incoming): array
    {
        $deduped = [];

        foreach (array_merge($existing, $incoming) as $shift) {
            $key = implode('|', [
                strtolower(trim($shift['job'])),
                $shift['start'],
                $shift['end'],
            ]);

            $deduped[$key] = [
                'job' => trim($shift['job']),
                'start' => $shift['start'],
                'end' => $shift['end'],
            ];
        }

        $shifts = array_values($deduped);

        usort($shifts, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

        return $shifts;
    }

    /**
     * @param  array<int, array{job:string,start:string,end:string}>  $shifts
     */
    private function isGroupZeroEligible(array $shifts): bool
    {
        $rulesByDay = config('ps.group_zero', []);

        if (! is_array($rulesByDay) || $rulesByDay === []) {
            return false;
        }

        $timezone = config('app.timezone', 'America/Chicago');

        foreach ($shifts as $shift) {
            $start = Carbon::parse($shift['start'])->setTimezone($timezone);
            $end = Carbon::parse($shift['end'])->setTimezone($timezone);

            if ($this->timeMatchesDayRules($start, $rulesByDay, 'shift_start_timestamps')) {
                return true;
            }

            if ($this->timeMatchesDayRules($end, $rulesByDay, 'shift_end_timestamps')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rulesByDay
     */
    private function timeMatchesDayRules(CarbonInterface $timestamp, array $rulesByDay, string $ruleKey): bool
    {
        $day = $timestamp->dayName;
        $dayRules = $rulesByDay[$day] ?? null;

        if (! is_array($dayRules) || ! isset($dayRules[$ruleKey]) || ! is_array($dayRules[$ruleKey])) {
            return false;
        }

        foreach ($dayRules[$ruleKey] as $range) {
            if (! is_array($range) || count($range) < 2) {
                continue;
            }

            $windowStartString = trim((string) ($range[0] ?? ''));
            $windowEndString = trim((string) ($range[1] ?? ''));

            if ($windowStartString === '' || $windowEndString === '') {
                continue;
            }

            $windowStart = Carbon::parse($timestamp->toDateString().' '.$windowStartString, $timestamp->timezone);
            $windowEnd = Carbon::parse($timestamp->toDateString().' '.$windowEndString, $timestamp->timezone);

            if ($windowEnd->lessThan($windowStart)) {
                $windowEnd->addDay();
            }

            if ($timestamp->betweenIncluded($windowStart, $windowEnd)) {
                return true;
            }
        }

        return false;
    }

    private function generateUniqueSerial(int $psYear, bool $groupZero): string
    {
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $prefix = $groupZero ? '0' : (string) random_int(1, 8);
            $middle = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $base = $prefix.$middle;
            $serial = $base.$this->verhoeffCheckDigit($base);

            $exists = Ticket::query()
                ->where('ps_year', $psYear)
                ->where('serial', $serial)
                ->exists();

            if (! $exists) {
                return $serial;
            }
        }

        throw new InvalidArgumentException('Unable to generate a unique serial number for this PS year.');
    }

    private function verhoeffCheckDigit(string $number): int
    {
        $d = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
            [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
            [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
            [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
            [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
            [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
            [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
            [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
            [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
        ];

        $p = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
            [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
            [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
            [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
            [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
            [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
            [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
        ];

        $inverse = [0, 4, 3, 2, 1, 5, 6, 7, 8, 9];

        $c = 0;
        $reversed = array_reverse(str_split($number));

        foreach ($reversed as $index => $digit) {
            $c = $d[$c][$p[($index + 1) % 8][(int) $digit]];
        }

        return $inverse[$c];
    }

    private function mergeString(mixed $existing, ?string $incoming): ?string
    {
        if ($incoming !== null && $incoming !== '') {
            return $incoming;
        }

        $existingString = trim((string) $existing);

        return $existingString === '' ? null : $existingString;
    }

    private function normalizeName(string $value): ?string
    {
        $name = trim($value);

        return $name === '' ? null : $name;
    }

    private function normalizePhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    private function normalizeEmail(string $value): ?string
    {
        $email = strtolower(trim($value));

        return $email === '' ? null : $email;
    }

    private function normalizeZip(string $value): ?string
    {
        $zip = trim($value);

        return $zip === '' ? null : $zip;
    }

    private function normalizeVlid(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : strtoupper($trimmed);
    }

    private function canonicalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($header)) ?? '';
    }

    private function stripUtfBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;
    }

    private function sanitizeCsvValue(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;

        // Remove zero-width and control formatting characters that can break key/value matching.
        return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{2060}\x{00A0}]/u', ' ', $value) ?? $value;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($this->sanitizeCsvValue((string) $value)) !== '') {
                return false;
            }
        }

        return true;
    }
}
