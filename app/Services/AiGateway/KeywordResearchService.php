<?php

namespace App\Services\AiGateway;

use App\Http\Controllers\Admin\AiPostDraftController;
use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Keywords & competitors" from the gateway (v1/research): competitors the owner names, keywords
 * they rank for in Google and this website does not, weekly rank tracking, and AI topic ideas.
 * Every provider call is paid and limited on the gateway; this website only shows and asks.
 */
class KeywordResearchService
{
    public const TOPICS_TASK = 'research.topics';

    /** Gap keywords sent to the AI for topic ideas, most searched first. */
    public const TOPIC_KEYWORDS = 60;

    private const CACHE_KEY = 'ai_gateway.research';

    public function __construct(private readonly GatewayClient $gateway) {}

    /**
     * @param  bool  $fresh  skip the cache (the page polling for a fetch to finish)
     * @return array{now: string|null, domain: string|null, limits: array<string, int>, competitors: list<array<string, mixed>>, gaps: list<array<string, mixed>>, tracked: list<array<string, mixed>>}
     *
     * @throws GatewayUnavailable
     */
    public function overview(bool $fresh = false): array
    {
        $cached = $fresh ? null : Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $research = (array) ($this->gateway->get('v1/research')['research'] ?? []);
        $result = [
            'now' => is_string($research['now'] ?? null) ? $research['now'] : null,
            'domain' => is_string($research['domain'] ?? null) ? $research['domain'] : null,
            'limits' => array_map('intval', (array) ($research['limits'] ?? [])),
            'competitors' => $this->rows($research['competitors'] ?? []),
            'gaps' => $this->rows($research['gaps'] ?? []),
            'tracked' => $this->rows($research['tracked'] ?? []),
        ];

        // Short while the gateway's background jobs are still fetching.
        Cache::put(self::CACHE_KEY, $result, self::pending($result) !== [] ? 15 : 600);

        return $result;
    }

    /**
     * What is still being fetched: competitors waiting for their keywords and keywords waiting for
     * their first position, each with when it was asked for (the gateway's clock).
     *
     * @return list<array{kind: string, label: string, since: string|null}>
     */
    public static function pending(array $overview): array
    {
        $pending = [];
        foreach ($overview['competitors'] ?? [] as $row) {
            if (($row['status'] ?? null) === 'queued') {
                $pending[] = ['kind' => 'competitor', 'label' => (string) ($row['domain'] ?? ''), 'since' => $row['queued_at'] ?? null];
            }
        }
        foreach ($overview['tracked'] ?? [] as $row) {
            if (($row['checked_at'] ?? null) === null && ($row['error'] ?? null) === null) {
                $pending[] = ['kind' => 'keyword', 'label' => (string) ($row['keyword'] ?? ''), 'since' => $row['added_at'] ?? null];
            }
        }

        return $pending;
    }

    /** @throws GatewayUnavailable */
    public function addCompetitor(string $domain): void
    {
        $this->gateway->post('v1/research/competitors', [], ['domain' => $domain]);
        self::forget();
    }

    /** @throws GatewayUnavailable */
    public function removeCompetitor(string $domain): void
    {
        $this->gateway->delete('v1/research/competitors/'.rawurlencode($domain));
        self::forget();
    }

    /**
     * @return list<array{domain: string, common_keywords: int, avg_position: float|null, traffic: int|null}>
     *
     * @throws GatewayUnavailable
     */
    public function suggestCompetitors(): array
    {
        return $this->rows($this->gateway->post('v1/research/competitors/suggest')['competitors'] ?? []);
    }

    /**
     * @return int competitors whose keywords are fetched again
     *
     * @throws GatewayUnavailable
     */
    public function refreshGaps(): int
    {
        $queued = (int) ($this->gateway->post('v1/research/gaps/refresh')['queued'] ?? 0);
        self::forget();

        return $queued;
    }

    /**
     * @param  list<string>  $keywords
     * @return array{added: int, existing: int}
     *
     * @throws GatewayUnavailable
     */
    public function track(array $keywords): array
    {
        $response = $this->gateway->post('v1/research/keywords', [], ['keywords' => array_values($keywords)]);
        self::forget();

        return ['added' => (int) ($response['added'] ?? 0), 'existing' => (int) ($response['existing'] ?? 0)];
    }

    /** @throws GatewayUnavailable */
    public function untrack(int $id): void
    {
        $this->gateway->delete('v1/research/keywords/'.$id);
        self::forget();
    }

    /**
     * Blog post topics from the current gap keywords. The keywords come from the gateway's list,
     * never from the browser; the answer is kept until that list changes.
     *
     * @return array{topics: list<array{title: string, primary_keyword: string, keywords: list<string>, reason: string}>, usage: array<string, int>}
     *
     * @throws GatewayUnavailable
     */
    public function topics(string $locale): array
    {
        $keywords = array_map(fn (array $gap) => [
            'keyword' => Str::limit((string) $gap['keyword'], 290, ''),
            'volume' => isset($gap['search_volume']) ? (int) $gap['search_volume'] : null,
            'intent' => $gap['intent'] ?? null,
        ], array_slice($this->overview()['gaps'], 0, self::TOPIC_KEYWORDS));

        if (count($keywords) < 3) {
            throw new GatewayUnavailable('invalid_input');
        }

        $key = 'ai_gateway.research_topics.'.$locale.'.'.sha1(json_encode($keywords));
        if (is_array($cached = Cache::get($key))) {
            return ['topics' => $cached, 'usage' => []];
        }

        $business = ProjectSetting::query()->where('setting_key', AiPostDraftController::BUSINESS_SETTING)->value('setting_value');
        $response = $this->gateway->task(self::TOPICS_TASK, array_filter([
            'locale' => $locale,
            'business_info' => is_string($business) && $business !== '' ? Str::limit($business, 2990, '') : null,
            'keywords' => $keywords,
        ], fn ($value) => $value !== null), (string) Str::uuid());

        $topics = array_values(array_map(fn ($topic) => [
            'title' => (string) ($topic['title'] ?? ''),
            'primary_keyword' => (string) ($topic['primary_keyword'] ?? ''),
            'keywords' => array_values(array_map('strval', (array) ($topic['keywords'] ?? []))),
            'reason' => (string) ($topic['reason'] ?? ''),
        ], array_filter((array) ($response['result']['topics'] ?? []), 'is_array')));
        Cache::put($key, $topics, now()->addDays(7));

        return ['topics' => $topics, 'usage' => (array) ($response['usage'] ?? [])];
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $rows): array
    {
        return array_values(array_filter((array) $rows, 'is_array'));
    }
}
