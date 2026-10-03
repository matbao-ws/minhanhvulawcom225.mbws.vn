<?php

namespace App\Console\Commands;

use App\Services\AiGateway\GatewayUnavailable;
use App\Support\CoreUpdate\CoreUpdateService;
use Illuminate\Console\Command;

/**
 * Hourly, at a minute of its own (CoreUpdateService::pollMinute): ask the update service for a
 * release. Installs it only when the gateway says `auto` (an auto-update website, a safe non-major
 * release); otherwise it just shows up in the admin for someone to click.
 */
class CorePoll extends Command
{
    protected $signature = 'core:poll {--no-install : Only ask, never install}';

    protected $description = 'Ask the update service for a core release and install it when it is marked auto';

    public function handle(CoreUpdateService $service): int
    {
        if (! $service->enabled()) {
            $this->components->info('Core updates are disabled or the gateway is not configured.');

            return self::SUCCESS;
        }

        try {
            $offer = $service->offer();
        } catch (GatewayUnavailable $e) {
            $this->components->warn('Gateway: '.$e->reason);

            return self::FAILURE;
        }

        if ($offer === null) {
            $this->components->info('Up to date.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Offered', $offer['version'].' ('.$offer['level'].($offer['auto'] ? ', auto' : '').')');
        if (! $offer['auto'] || $this->option('no-install')) {
            return self::SUCCESS;
        }

        $result = $service->run($offer);
        $this->line($result['ok'] ? 'UPDATE_INSTALLED '.$result['to'] : 'UPDATE_FAILED '.($result['error'] ?? implode(' | ', $result['blockers'] ?? [])));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
