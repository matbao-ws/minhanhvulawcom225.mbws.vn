<?php

namespace App\Console\Commands;

use App\Support\Extensions\ExtensionManager;
use Illuminate\Console\Command;

/** Switch an extension off (storage/app/core/extensions.json) — works even while it breaks the site. */
class ExtensionDisable extends Command
{
    protected $signature = 'extension:disable {id} {--reason= : Why, shown in extension:list and the admin}';

    protected $description = 'Switch an extension off without removing it';

    public function handle(ExtensionManager $extensions): int
    {
        $id = (string) $this->argument('id');
        if (! array_key_exists($id, $extensions->all())) {
            $this->components->error("No extension \"{$id}\" in {$extensions->root()}.");

            return self::FAILURE;
        }

        $extensions->state()->disable($id, $this->option('reason') ?: null);
        $extensions->forget();
        $this->components->info("Extension {$id} is off. Turn it back on: php artisan extension:enable {$id}");

        return self::SUCCESS;
    }
}
