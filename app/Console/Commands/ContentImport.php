<?php

namespace App\Console\Commands;

use App\Support\Deploy\ContentTransfer;
use App\Support\Deploy\ContentTransferRefused;
use Illuminate\Console\Command;

/**
 * Run on the website by deploy.py's first-deploy script, after migrate and seed. Refuses a
 * website that is no longer new; there is deliberately no --force.
 */
class ContentImport extends Command
{
    protected $signature = 'content:import {file : An export written by content:export}';

    protected $description = 'Import a content export into a website on its first deploy';

    public function handle(ContentTransfer $transfer): int
    {
        try {
            $counts = $transfer->import((string) $this->argument('file'));
        } catch (ContentTransferRefused $e) {
            $this->line('CONTENT_IMPORT_REFUSED: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach (array_filter($counts) as $table => $count) {
            $this->line(sprintf('  %-32s %d', $table, $count));
        }
        $this->line('CONTENT_IMPORT_OK');

        return self::SUCCESS;
    }
}
