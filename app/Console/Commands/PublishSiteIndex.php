<?php

namespace App\Console\Commands;

use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\SiteIndexPublisher;
use Illuminate\Console\Command;

class PublishSiteIndex extends Command
{
    protected $signature = 'ai:site-index';

    protected $description = 'Send the MatBao AI gateway the index of public pages, for Search Console matching';

    public function handle(SiteIndexPublisher $publisher): int
    {
        if (! $publisher->enabled()) {
            $this->info('Skipped: the AI gateway is not configured or the ai_seo feature is off.');

            return self::SUCCESS;
        }

        try {
            $this->info('Sent '.$publisher->publish().' rows.');
        } catch (GatewayUnavailable $e) {
            $this->error('Gateway refused: '.$e->reason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
