<?php

namespace App\Services\AiGateway;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "This week's SEO work" from the gateway (GET v1/seo-tasks), with each task
 * linked to the record's editor when it still exists and the admin may edit
 * it. The list is the gateway's; this website only adds its own links.
 *
 * Advice for one task is asked once per sync: the browser names the task by
 * id and the input comes from the list, never from the browser.
 */
class SeoTaskService
{
    public const TASK = 'seo.task_advice';

    private const CACHE_KEY = 'ai_gateway.seo_tasks';

    private const OUTCOMES_CACHE_KEY = 'ai_gateway.seo_task_outcomes';

    /** entity type => model, feature, permission to edit, editor route. */
    private const EDITORS = [
        'post' => [Post::class, 'cms_page', 'posts.update', 'admin.posts.edit', 'post'],
        'product' => [Product::class, 'catalog', 'products.update', 'admin.products.edit', 'product'],
        'category' => [Category::class, 'catalog', 'products.update', 'admin.categories.edit', 'category'],
        'page' => [Page::class, 'cms_page', 'pages.update', 'admin.pages.edit', 'page'],
    ];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
    ) {}

    /**
     * @return array{status: string, synced_through: string|null, totals: array<string, mixed>|null, tasks: list<array<string, mixed>>}
     *
     * @throws GatewayUnavailable
     */
    public function weekly(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->gateway->get('v1/seo-tasks');
        $result = [
            'status' => (string) ($response['status'] ?? 'pending'),
            'synced_through' => $response['synced_through'] ?? null,
            'totals' => $response['totals'] ?? null,
            // Tasks the owner marked done or dismissed, kept out of the list for a while.
            'hidden' => (int) ($response['hidden'] ?? 0),
            'tasks' => array_values(array_filter((array) ($response['tasks'] ?? []), 'is_array')),
        ];
        // The gateway recomputes once a day; a short cache keeps page loads off it.
        Cache::put(self::CACHE_KEY, $result, 900);

        return $result;
    }

    /**
     * Mark a task done or dismissed (or bring it back with 'open'); the gateway keeps it hidden for a while.
     * Done also starts the before/after measurement: who fixed it (the AI tools or the owner) is recorded.
     *
     * @param  list<string>  $tools  AI task codes that made the change, e.g. meta.suggest
     *
     * @throws GatewayUnavailable
     */
    public function mark(string $taskId, string $status, string $appliedBy = 'owner', array $tools = []): void
    {
        $this->gateway->post('v1/seo-tasks/'.$taskId.'/status', [], array_filter([
            'status' => $status,
            'applied_by' => $status === 'done' ? $appliedBy : null,
            'tools' => $status === 'done' && $tools !== [] ? $tools : null,
        ], fn ($value) => $value !== null));
        self::forget();
    }

    /**
     * Fixed tasks and how their pages did in the 28 days after, against the rest of the website.
     *
     * @return array{synced_through: string|null, summary: array<string, int>, outcomes: list<array<string, mixed>>}
     *
     * @throws GatewayUnavailable
     */
    public function outcomes(): array
    {
        $cached = Cache::get(self::OUTCOMES_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->gateway->get('v1/seo-tasks/outcomes');
        $result = [
            'synced_through' => $response['synced_through'] ?? null,
            'summary' => array_map('intval', (array) ($response['summary'] ?? [])),
            'outcomes' => array_values(array_filter((array) ($response['outcomes'] ?? []), 'is_array')),
        ];
        Cache::put(self::OUTCOMES_CACHE_KEY, $result, 900);

        return $result;
    }

    /**
     * One task of the current list, as forUser() shows it to this admin.
     *
     * @throws GatewayUnavailable
     */
    public function find(string $taskId, User $user): array
    {
        $task = collect($this->forUser($user)['tasks'])->firstWhere('id', $taskId);
        abort_unless(is_array($task), 404, __('ai.tasks.gone'));

        return $task;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::OUTCOMES_CACHE_KEY);
    }

    /**
     * The list with `edit_url` (null when the record is gone or not editable by this admin).
     *
     * @throws GatewayUnavailable
     */
    public function forUser(User $user): array
    {
        $weekly = $this->weekly();
        $existing = $this->existingRecords($weekly['tasks']);

        $weekly['tasks'] = array_map(function (array $task) use ($user, $existing) {
            $entity = $task['entity'] ?? null;
            $task['edit_url'] = null;
            // Why there is no editor link: the record was deleted here, or this admin may not edit it.
            $task['record_gone'] = false;

            if (is_array($entity) && isset(self::EDITORS[$entity['type'] ?? ''])) {
                [, $feature, $permission, $route, $parameter] = self::EDITORS[$entity['type']];
                $task['record_gone'] = ! isset($existing[$entity['type']][(int) $entity['id']]);
                if (! $task['record_gone']
                    && $user->can($permission)
                    && $this->features->availableTo($user, $feature)) {
                    $task['edit_url'] = route($route, [$parameter => (int) $entity['id']]);
                }
            }

            return $task;
        }, $weekly['tasks']);

        return $weekly;
    }

    /**
     * @return array{advice: array{summary: string, steps: list<string>}, usage: array<string, int>}
     *
     * @throws GatewayUnavailable
     */
    public function advice(string $taskId): array
    {
        $weekly = $this->weekly();
        $task = collect($weekly['tasks'])->firstWhere('id', $taskId);
        abort_unless(is_array($task), 404, __('ai.tasks.gone'));

        $key = 'ai_gateway.seo_advice.'.$taskId.'.'.($weekly['synced_through'] ?? '');
        if (is_array($cached = Cache::get($key))) {
            return ['advice' => $cached, 'usage' => []];
        }

        $entity = is_array($task['entity'] ?? null) ? $task['entity'] : [];
        $input = array_filter([
            'locale' => $entity['locale'] ?? app()->getLocale(),
            'task_type' => $task['type'],
            'entity_type' => $entity['type'] ?? null,
            'entity_id' => $entity['id'] ?? null,
            'page' => $task['page'] ?? null,
            'title' => isset($entity['title']) ? Str::limit((string) $entity['title'], 290, '') : null,
            'meta_title' => $entity['meta_title'] ?? null,
            'meta_description' => $entity['meta_description'] ?? null,
            'focus_keyword' => $entity['focus_keyword'] ?? null,
            'metrics' => array_filter((array) ($task['metrics'] ?? []), 'is_numeric'),
            'queries' => array_values(array_slice(array_map(fn ($q) => Str::limit((string) ($q['query'] ?? ''), 290, ''), (array) ($task['queries'] ?? [])), 0, 5)),
            'issues' => array_values((array) ($task['issues'] ?? [])),
            'intent' => $task['intent'] ?? null,
        ], fn ($value) => $value !== null && $value !== []);

        $response = $this->gateway->task(self::TASK, $input, (string) Str::uuid());
        $advice = [
            'summary' => (string) ($response['result']['summary'] ?? ''),
            'steps' => array_values(array_map('strval', (array) ($response['result']['steps'] ?? []))),
        ];
        Cache::put($key, $advice, now()->addDays(7));

        return ['advice' => $advice, 'usage' => (array) ($response['usage'] ?? [])];
    }

    /** @return array<string, array<int, true>> ids per type that still exist here */
    private function existingRecords(array $tasks): array
    {
        $ids = [];
        foreach ($tasks as $task) {
            $entity = $task['entity'] ?? null;
            if (is_array($entity) && isset(self::EDITORS[$entity['type'] ?? ''])) {
                $ids[$entity['type']][] = (int) $entity['id'];
            }
        }

        $existing = [];
        foreach ($ids as $type => $list) {
            $model = self::EDITORS[$type][0];
            $existing[$type] = array_fill_keys($model::query()->whereKey(array_unique($list))->pluck('id')->all(), true);
        }

        return $existing;
    }
}
