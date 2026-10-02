<?php

namespace App\Services\AiGateway;

use Illuminate\Support\Facades\Cache;

/** This month's AI credits, as the gateway counts them (it is the one that enforces them). */
class AiUsage
{
    public const CACHE_KEY = 'ai_gateway.usage';

    public function __construct(private readonly GatewayClient $gateway) {}

    /**
     * @return array{credits_used: int, credits_limit: int, period: string}|null null when unknown
     */
    public function current(): ?array
    {
        if (! $this->gateway->configured()) {
            return null;
        }

        $usage = Cache::remember(self::CACHE_KEY, 300, function () {
            try {
                return (array) ($this->gateway->get('v1/usage')['usage'] ?? []);
            } catch (GatewayUnavailable) {
                return [];
            }
        });

        return isset($usage['credits_limit']) ? [
            'credits_used' => (int) ($usage['credits_used'] ?? 0),
            'credits_limit' => (int) $usage['credits_limit'],
            'period' => (string) ($usage['period'] ?? ''),
        ] : null;
    }

    /** A task answer carries the fresh numbers; keep the dashboard in step without another call. */
    public static function remember(array $usage, ?string $period = null): void
    {
        if (isset($usage['credits_used'], $usage['credits_limit'])) {
            Cache::put(self::CACHE_KEY, $usage + ['period' => $period ?? now()->format('Y-m')], 300);
        }
    }
}
