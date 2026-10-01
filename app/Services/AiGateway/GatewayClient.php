<?php

namespace App\Services\AiGateway;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the MatBaoAI-SEO gateway, authenticated by this
 * website's project token. Every failure becomes a GatewayUnavailable with the
 * gateway's own reason code (or `unreachable` / `not_configured`); response
 * bodies are never logged, and the token never is.
 */
class GatewayClient
{
    public const PROJECT_CACHE_KEY = 'ai_gateway.project';

    public function configured(): bool
    {
        return filled(config('ai_gateway.url')) && filled(config('ai_gateway.token'));
    }

    /**
     * This website's project as the gateway describes it (reader email, relay
     * sender…), cached ten minutes. Empty when not configured or unreachable.
     *
     * @return array<string, mixed>
     */
    public function project(): array
    {
        if (! $this->configured()) {
            return [];
        }

        try {
            return Cache::remember(self::PROJECT_CACHE_KEY, 600, fn () => (array) $this->get('v1/project')['project']);
        } catch (GatewayUnavailable) {
            return [];
        }
    }

    public static function forgetProject(): void
    {
        Cache::forget(self::PROJECT_CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws GatewayUnavailable
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send(fn () => $this->request()->get($this->url($path), $query));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws GatewayUnavailable
     */
    public function put(string $path, array $body): array
    {
        return $this->send(fn () => $this->request()->put($this->url($path), $body));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws GatewayUnavailable
     */
    public function post(string $path, array $query = [], array $body = []): array
    {
        $url = $this->url($path).($query === [] ? '' : '?'.http_build_query($query));

        return $this->send(fn () => $this->request()->post($url, $body));
    }

    /**
     * @param  callable(): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        if (! $this->configured()) {
            throw new GatewayUnavailable(GatewayUnavailable::NOT_CONFIGURED);
        }

        try {
            $response = $call();
        } catch (\Throwable $e) {
            Log::warning('MatBao gateway unreachable.', ['exception' => $e::class]);

            throw new GatewayUnavailable(GatewayUnavailable::UNREACHABLE);
        }

        $json = $response->json();

        if ($response->successful() && is_array($json) && ($json['ok'] ?? false) === true) {
            return $json;
        }

        $reason = is_array($json) && is_string($json['reason'] ?? null) ? $json['reason'] : GatewayUnavailable::UNREACHABLE;
        if ($reason === GatewayUnavailable::UNREACHABLE) {
            Log::warning('MatBao gateway returned an unexpected response.', ['status' => $response->status()]);
        }

        throw new GatewayUnavailable($reason, is_array($json) ? (array) ($json['errors'] ?? []) : []);
    }

    private function request()
    {
        return Http::withToken((string) config('ai_gateway.token'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout((int) config('ai_gateway.timeout', 15));
    }

    private function url(string $path): string
    {
        return config('ai_gateway.url').'/'.ltrim($path, '/');
    }
}
