<?php

namespace App\Console\Commands;

use App\Support\CoreManifest;
use Illuminate\Console\Command;

/**
 * The list of core files with their sha256 — what a release package contains and what a website
 * records as installed. Used by the release build (CI) and for checking which files are core.
 */
class CoreManifestCommand extends Command
{
    protected $signature = 'core:manifest
        {--output= : Write the JSON to this file instead of printing it}
        {--owner= : Only print which side owns this path (core, dependencies or site)}';

    protected $description = 'List the core files and their checksums as JSON';

    public function handle(CoreManifest $manifest): int
    {
        if (filled($path = $this->option('owner'))) {
            $this->line($manifest->owner($path));

            return self::SUCCESS;
        }

        $json = json_encode($manifest->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (filled($output = $this->option('output'))) {
            file_put_contents($output, $json);
            $this->components->info(sprintf('%d core files, version %s → %s', count($manifest->coreFiles()), $manifest->version(), $output));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
