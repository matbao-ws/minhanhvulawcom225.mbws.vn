<?php

namespace App\Console\Commands;

use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\InternalLinkService;
use App\Services\AiGateway\MetaSuggestionService;
use App\Services\LanguageRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Runs internal-link suggestions on many saved posts/products, exactly as the editor
 * button does, without applying anything. On the gateway each run logs the candidate
 * set the production generator built from the full article — the `production_logged`
 * pairs that `eval:candidates --from=production` samples for labelling (docs/EVAL.md).
 * Every run uses one AI credit of this website's project.
 */
class SampleLinkCandidates extends Command
{
    protected $signature = 'ai:link-candidates
        {--limit=100 : Records to run, most recently updated first}
        {--types=post,product : Record types}
        {--locale= : Content locale (default: the site default)}
        {--pause=3 : Seconds between calls (the gateway allows 20 AI calls a minute)}';

    protected $description = 'Run internal-link suggestions on many records to log production candidate sets for the eval';

    /** Reasons after which nothing else would succeed either. */
    private const STOPPING = ['quota_exceeded', 'cost_cap_reached', 'ai_disabled', 'too_many_failures', 'project_suspended', 'not_configured', 'unauthorized'];

    public function handle(InternalLinkService $links, GatewayClient $gateway, LanguageRegistry $languages): int
    {
        if (! $gateway->configured()) {
            $this->error('The MatBao AI gateway is not configured (MATBAO_AI_URL / MATBAO_AI_TOKEN).');

            return self::FAILURE;
        }

        $locale = (string) ($this->option('locale') ?: $languages->defaultLocale());
        $types = array_intersect(array_map('trim', explode(',', (string) $this->option('types'))), InternalLinkService::ENTITY_TYPES);
        $limit = max(1, (int) $this->option('limit'));
        $pause = max(0, (int) $this->option('pause'));
        $done = 0;
        $failed = 0;

        foreach ($types as $type) {
            $model = MetaSuggestionService::ENTITIES[$type]['model'];
            foreach ($model::query()->where('is_active', true)->orderByDesc('updated_at')->limit($limit)->cursor() as $record) {
                if ($done >= $limit) {
                    break 2;
                }
                $input = $links->taskInput($type, $record, $locale);
                if ($input === null) {
                    continue;
                }

                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    try {
                        $gateway->task(InternalLinkService::TASK, $input, (string) Str::uuid());
                        $done++;
                        $this->line("  $type #{$record->getKey()}: ok");
                        break;
                    } catch (GatewayUnavailable $e) {
                        if ($e->reason === 'rate_limited' && $attempt === 1) {
                            $this->warn('  rate limited, waiting 60 s…');
                            sleep(60);

                            continue;
                        }
                        if (in_array($e->reason, self::STOPPING, true)) {
                            $this->error("Stopped: {$e->reason}. $done runs done.");

                            return self::FAILURE;
                        }
                        $failed++;
                        $this->line("  $type #{$record->getKey()}: {$e->reason}");
                        break;
                    }
                }

                sleep($pause);
            }
        }

        $this->info("$done runs logged their candidate sets on the gateway ($failed failed).");
        $this->line('Next, on the gateway: php artisan eval:candidates <project_id> --from=production');

        return self::SUCCESS;
    }
}
