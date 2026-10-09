<?php

namespace App\Console\Commands;

use App\Support\Deploy\ContentTransfer;
use App\Support\Deploy\ContentTransferRefused;
use Illuminate\Console\Command;

/** Run on the developer's machine by deploy.py before a first deploy (ContentTables lists what travels). */
class ContentExport extends Command
{
    protected $signature = 'content:export {file : Where to write the export (.json.gz)}';

    protected $description = 'Export the catalogue, content and shop settings for a first deploy';

    public function handle(ContentTransfer $transfer): int
    {
        try {
            $counts = $transfer->export((string) $this->argument('file'));
        } catch (ContentTransferRefused $e) {
            $this->line('CONTENT_EXPORT_REFUSED: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach (array_filter($counts) as $table => $count) {
            $this->line(sprintf('  %-32s %d', $table, $count));
        }
        $this->line('CONTENT_EXPORT_OK');

        return self::SUCCESS;
    }
}
