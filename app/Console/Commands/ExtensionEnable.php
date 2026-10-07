<?php

namespace App\Console\Commands;

use App\Support\Extensions\ExtensionManager;
use Illuminate\Console\Command;

/** Switch an extension back on. It still has to be valid and compatible to load. */
class ExtensionEnable extends Command
{
    protected $signature = 'extension:enable {id}';

    protected $description = 'Switch a disabled extension back on';

    public function handle(ExtensionManager $extensions): int
    {
        $id = (string) $this->argument('id');
        if (! array_key_exists($id, $extensions->all())) {
            $this->components->error("No extension \"{$id}\" in {$extensions->root()}.");

            return self::FAILURE;
        }

        $extensions->state()->enable($id);
        $extensions->forget();
        $record = $extensions->all()[$id];

        if ($record['status'] === ExtensionManager::READY) {
            $this->components->info("Extension {$id} is on; it loads on the next request.");

            return self::SUCCESS;
        }
        $this->components->warn("Extension {$id} is on but will not load: {$record['status']}".($record['error'] ? " — {$record['error']}" : ''));

        return self::FAILURE;
    }
}
