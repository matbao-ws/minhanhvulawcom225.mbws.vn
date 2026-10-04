<?php

namespace App\Console\Commands;

use App\Support\CoreManifest;
use App\Support\Extensions\ExtensionChecker;
use App\Support\Extensions\ExtensionManager;
use Illuminate\Console\Command;

/**
 * Checks extensions before they ship: manifest, compatibility, provider file, extension API only,
 * no replaced core class, migrations on their own tables. Exit 1 on any error — team.py and the
 * project CI run it. A guardrail, not a sandbox (docs/EXTENSIONS_DESIGN.md, section 3).
 */
class ExtensionCheck extends Command
{
    protected $signature = 'extension:check {id? : One extension; all of them when omitted}';

    protected $description = 'Check extensions against the extension rules (manifest, API, migrations)';

    public function handle(ExtensionManager $extensions, ExtensionChecker $checker, CoreManifest $core): int
    {
        $records = $extensions->all();
        $id = $this->argument('id');
        if ($id !== null) {
            if (! isset($records[$id])) {
                $this->components->error("No extension \"{$id}\" in {$extensions->root()}.");

                return self::FAILURE;
            }
            $records = [$id => $records[$id]];
        }
        if ($records === []) {
            $this->components->info('No extension to check.');

            return self::SUCCESS;
        }

        $failed = false;
        foreach ($records as $record) {
            if ($record['manifest'] === null) {
                $this->components->error("{$record['id']}: extension.json — {$record['error']}");
                $failed = true;

                continue;
            }

            ['errors' => $errors, 'warnings' => $warnings] = $checker->check($record['manifest'], $core->version());
            foreach ($errors as $error) {
                $this->components->error("{$record['id']}: {$error}");
            }
            foreach ($warnings as $warning) {
                $this->components->warn("{$record['id']}: {$warning}");
            }
            if ($errors === []) {
                $this->components->info("{$record['id']} {$record['manifest']->version}: OK".($warnings === [] ? '' : ' (with warnings)'));
            }
            $failed = $failed || $errors !== [];
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
