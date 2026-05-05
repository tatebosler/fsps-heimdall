<?php

namespace App\Helpers;

use App\Models\Channel;
use App\Models\Estimate;
use Carbon\Carbon;
use InvalidArgumentException;
use SplFileObject;

class HistoricalEntryCsvImporter
{
    /**
     * @return array{
     *     rows_read:int,
     *     channels_created:int,
     *     channels_updated:int,
     *     channels_skipped:int,
     *     estimates_created:int,
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

        $headers = $this->readHeaders($file);
        $idColumn = $this->resolveIdColumn($headers);

        $this->ensureColumnsExist($headers, [
            'estimated_entry',
            'actual_entry',
        ]);

        $summary = [
            'rows_read' => 0,
            'channels_created' => 0,
            'channels_updated' => 0,
            'channels_skipped' => 0,
            'estimates_created' => 0,
            'invalid_rows' => 0,
            'errors' => [],
        ];

        foreach ($file as $index => $row) {
            // Skip the header row — foreach rewinds SplFileObject to position 0.
            if ($index === 0) {
                continue;
            }

            if (! is_array($row) || $this->isBlankRow($row)) {
                continue;
            }

            $summary['rows_read']++;
            $lineNumber = (int) $index + 1;

            try {
                /** @var array<string, string|null> $record */
                $record = $this->mapRowToHeaders($headers, $row);

                $rawId = $record[$idColumn] ?? null;
                if (! is_string($rawId) && ! is_numeric($rawId)) {
                    throw new InvalidArgumentException("Missing {$idColumn} value");
                }

                $channelId = (int) $rawId;
                $normalizedChannelId = $this->normalizeChannelId($channelId);

                if ($normalizedChannelId === null) {
                    $summary['channels_skipped']++;

                    continue;
                }

                $estimatedEntryAt = $this->isSpecialChannelId($normalizedChannelId)
                    ? $this->parseOptionalTimestamp($record['estimated_entry'] ?? null)
                    : $this->parseRequiredTimestamp($record['estimated_entry'] ?? null, 'estimated_entry');

                $actualEntryAt = $this->isSpecialChannelId($normalizedChannelId)
                    ? $this->parseOptionalTimestamp($record['actual_entry'] ?? null)
                    : $this->parseRequiredTimestamp($record['actual_entry'] ?? null, 'actual_entry');

                $distributionStartedAt = $this->isSpecialChannelId($normalizedChannelId)
                    ? $this->parseOptionalTimestamp($record['group_distribution_start'] ?? null)
                    : $this->parseRequiredTimestamp($record['group_distribution_start'] ?? null, 'group_distribution_start');

                // Skip special channels with no data
                if ($this->isSpecialChannelId($normalizedChannelId) && ! $estimatedEntryAt && ! $actualEntryAt && ! $distributionStartedAt) {
                    $summary['channels_skipped']++;

                    continue;
                }

                $channel = Channel::firstOrNew(['id' => $normalizedChannelId]);
                $isNewChannel = ! $channel->exists;

                $originalEstimatedEntryAt = $this->parseOptionalTimestamp($record['original_estimated_entry'] ?? null);
                if (! $originalEstimatedEntryAt && ! $channel->exists) {
                    $originalEstimatedEntryAt = $estimatedEntryAt;
                }

                $channel->distribution_started_at = $distributionStartedAt;
                $channel->estimated_entry_at = $estimatedEntryAt;
                $channel->cleared_at = $actualEntryAt;

                if (array_key_exists('customer_arrival', $record)) {
                    $channel->customers_arrived_at = $this->parseOptionalTimestamp($record['customer_arrival']);
                }

                if ($originalEstimatedEntryAt) {
                    $channel->original_estimated_entry_at = $originalEstimatedEntryAt;
                }

                $channel->save();

                if ($isNewChannel) {
                    $summary['channels_created']++;
                } else {
                    $summary['channels_updated']++;
                }

                if ($estimatedEntryAt) {
                    $estimate = Estimate::firstOrCreate([
                        'channel_id' => $channel->id,
                        'estimated_entry_at' => $estimatedEntryAt,
                    ]);

                    if ($estimate->wasRecentlyCreated) {
                        $summary['estimates_created']++;
                    }
                }
            } catch (\Throwable $throwable) {
                $summary['invalid_rows']++;
                $summary['errors'][] = "Line {$lineNumber}: {$throwable->getMessage()}";
            }
        }

        return $summary;
    }

    private function resolveIdColumn(array $headers): string
    {
        if (in_array('id_5d', $headers, true)) {
            return 'id_5d';
        }

        if (in_array('id', $headers, true)) {
            return 'id';
        }

        throw new InvalidArgumentException('CSV must include either an id_5d or id column.');
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, string|null>
     */
    private function mapRowToHeaders(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $mapped;
    }

    /**
     * @return array<int, string>
     */
    private function readHeaders(SplFileObject $file): array
    {
        $headers = $file->fgetcsv();

        if (! is_array($headers)) {
            throw new InvalidArgumentException('CSV file is empty or unreadable.');
        }

        $normalizedHeaders = collect($headers)
            ->map(fn (mixed $header): string => strtolower(trim((string) $header)))
            ->values()
            ->all();

        // Strip UTF-8 BOM from the first header (present in Excel-exported CSVs).
        if (isset($normalizedHeaders[0]) && str_starts_with($normalizedHeaders[0], "\xef\xbb\xbf")) {
            $normalizedHeaders[0] = substr($normalizedHeaders[0], 3);
        }

        if ($normalizedHeaders === [] || $normalizedHeaders === ['']) {
            throw new InvalidArgumentException('CSV file does not contain a valid header row.');
        }

        return $normalizedHeaders;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $requiredColumns
     */
    private function ensureColumnsExist(array $headers, array $requiredColumns): void
    {
        $missing = collect($requiredColumns)
            ->filter(fn (string $column): bool => ! in_array($column, $headers, true))
            ->values()
            ->all();

        if ($missing !== []) {
            throw new InvalidArgumentException('CSV is missing required columns: '.implode(', ', $missing));
        }
    }

    private function parseRequiredTimestamp(mixed $value, string $column): Carbon
    {
        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            throw new InvalidArgumentException("{$column} is required");
        }

        return Carbon::parse($stringValue);
    }

    private function parseOptionalTimestamp(mixed $value): ?Carbon
    {
        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        return Carbon::parse($stringValue);
    }

    private function normalizeChannelId(int $channelId): ?int
    {
        $suffix = $channelId % 1000;

        if ($suffix >= 921 && $suffix <= 928) {
            $x = $suffix % 10;

            return (int) (floor($channelId / 1000) * 1000) + (900 + ($x * 10));
        }

        if ((int) floor($suffix / 100) === 9) {
            return null;
        }

        return $channelId;
    }

    private function isSpecialChannelId(int $channelId): bool
    {
        return ($channelId % 1000) >= 900;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
