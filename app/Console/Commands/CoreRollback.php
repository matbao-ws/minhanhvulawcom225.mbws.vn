<?php

namespace App\Console\Commands;

use App\Support\CoreUpdate\CoreUpdater;
use Illuminate\Console\Command;

/** Puts back the code from before the latest core update. The database is left as it is. */
class CoreRollback extends Command
{
    protected $signature = 'core:rollback';

    protected $description = 'Restore the core code from before the latest update (code only, never the database)';

    public function handle(CoreUpdater $updater): int
    {
        $result = $updater->rollback();

        if (! $result['ok']) {
            $this->components->error($result['error']);

            return self::FAILURE;
        }

        $this->components->info('Core code restored to '.$result['restored'].'. The database was not changed.');

        return self::SUCCESS;
    }
}
