<?php

namespace App\Console\Commands;

use App\Support\CoreManifest;
use Illuminate\Console\Command;

/**
 * Which core version this website runs and whether its core files were edited by hand since it
 * was installed. An update refuses to run on a website with drift (it would overwrite the edits):
 * move the change into the theme, or into core itself, then `--baseline` again.
 */
class CoreStatus extends Command
{
    protected $signature = 'core:status
        {--baseline : Record the current core files as the installed release (first adoption, or after fixing drift)}
        {--json : Print the result as JSON}';

    protected $description = 'Show the core version and core files edited since it was installed';

    public function handle(CoreManifest $manifest): int
    {
        if ($this->option('baseline')) {
            $recorded = $manifest->recordInstalled();
            $this->components->info(sprintf('Recorded %d core files as version %s.', count($recorded['files']), $recorded['version']));

            return self::SUCCESS;
        }

        $installed = $manifest->installed();
        $drift = $manifest->drift();
        $clean = $drift !== null && $drift['modified'] === [] && $drift['missing'] === [] && $drift['added'] === [];

        if ($this->option('json')) {
            $this->line(json_encode([
                'version' => $manifest->version(),
                'installed_version' => $installed['version'] ?? null,
                'baseline' => $installed !== null,
                'clean' => $clean,
                'drift' => $drift,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $clean ? self::SUCCESS : self::FAILURE;
        }

        $this->components->twoColumnDetail('Core version', $manifest->version());

        if ($drift === null) {
            $this->components->warn('No baseline recorded. Run `php artisan core:status --baseline` once the core files are as shipped.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Installed version', $installed['version'] ?? '?');

        if ($clean) {
            $this->components->info('Core files match the installed release.');

            return self::SUCCESS;
        }

        foreach (['modified' => 'Edited', 'missing' => 'Deleted', 'added' => 'Added'] as $key => $label) {
            foreach ($drift[$key] as $path) {
                $this->components->twoColumnDetail($path, $label);
            }
        }
        $this->components->error('Core files differ from the installed release; an update would overwrite them. Move theme changes out of core files.');

        return self::FAILURE;
    }
}
