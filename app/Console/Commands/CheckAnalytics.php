<?php

namespace App\Console\Commands;

use App\Exceptions\AnalyticsUnavailable;
use App\Services\AiGateway\GatewayClient;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Console\Command;

class CheckAnalytics extends Command
{
    protected $signature = 'analytics:check';

    protected $description = 'Check the Google Analytics connection through the MatBao gateway';

    public function handle(GatewayClient $gateway, AnalyticsReport $report): int
    {
        $settings = $report->settings();

        // URL and IDs only: the project token never goes to the terminal.
        $this->components->twoColumnDetail('Gateway', $gateway->configured() ? (string) config('ai_gateway.url') : 'not configured (MATBAO_AI_URL / MATBAO_AI_TOKEN)');
        $this->components->twoColumnDetail('Reader email', $report->readerEmail() ?? 'unknown');
        $this->components->twoColumnDetail('Measurement ID', $settings['measurement_id'] ?? 'not set');
        $this->components->twoColumnDetail('Property ID', $settings['property_id'] ?? 'not set');

        try {
            $this->components->info('Connected to '.$report->checkConnection().'.');
        } catch (AnalyticsUnavailable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
