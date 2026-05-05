<?php

namespace App\Console\Commands;

use App\Helpers\HistoricalEntryCsvImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('app:import-historical-entry-data {csv : Path to the CSV file}')]
#[Description('Import historical entry channel data from a CSV file')]
class ImportHistoricalEntryData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(HistoricalEntryCsvImporter $importer): int
    {
        $path = (string) $this->argument('csv');

        try {
            $summary = $importer->import($path);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Rows read', $summary['rows_read']],
                ['Channels created', $summary['channels_created']],
                ['Channels updated', $summary['channels_updated']],
                ['Channels skipped', $summary['channels_skipped']],
                ['Estimates created', $summary['estimates_created']],
                ['Invalid rows', $summary['invalid_rows']],
            ]
        );

        if ($summary['errors'] !== []) {
            $this->newLine();
            $this->warn('Row-level errors:');

            foreach ($summary['errors'] as $error) {
                $this->line("- {$error}");
            }
        }

        $this->info('Historical data import complete.');

        return self::SUCCESS;
    }
}
