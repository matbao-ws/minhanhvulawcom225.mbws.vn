<?php

namespace App\Console\Commands;

use App\Support\CoreUpdate\CoreUpdateService;
use Illuminate\Console\Command;

/** Every minute: carry out an update or rollback an admin asked for in the admin panel. */
class CoreRunRequested extends Command
{
    protected $signature = 'core:run-requested';

    protected $description = 'Run the core update or rollback requested from the admin panel';

    public function handle(CoreUpdateService $service): int
    {
        if (! (bool) config('core_update.enabled')) {
            return self::SUCCESS;
        }

        $result = $service->runRequested();
        if ($result === null) {
            return self::SUCCESS;
        }

        $this->line($result['ok'] ? 'DONE' : 'FAILED '.($result['error'] ?? implode(' | ', $result['blockers'] ?? [])));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
