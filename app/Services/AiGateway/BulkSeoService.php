<?php

namespace App\Services\AiGateway;

use App\Models\AiBulkJob;
use App\Models\AiSuggestion;
use App\Models\Post;
use App\Models\User;
use App\Services\LanguageRegistry;
use App\Support\FeatureGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Bulk SEO": one gateway job per run, for one kind of work —
 *  - meta:    SEO title/description where missing (bulk.meta),
 *  - seo_fix: posts failing text rules of the SEO checker (bulk.seo_fix),
 *  - alt:     images without alt text (bulk.alt, the image travels in the job).
 * Each item is built exactly like the single-click tool, its result becomes an
 * ai_suggestion, and applying goes through that tool's apply(): same
 * permission checks, same conflict check against what the record held when
 * the job started, same activity log.
 */
class BulkSeoService
{
    public const KINDS = ['meta' => 'bulk.meta', 'seo_fix' => 'bulk.seo_fix', 'alt' => 'bulk.alt'];

    private const TASKS = ['meta' => MetaSuggestionService::TASK, 'seo_fix' => SeoFixService::TASK, 'alt' => AltTextService::TASK];

    private const LIMITS = ['meta' => 100, 'seo_fix' => 100, 'alt' => 20];

    /** Images per job in total, before base64. */
    private const ALT_MAX_BYTES = 15 * 1024 * 1024;

    /** Rules a rewrite of title, description or opening can fix. */
    public const FIXABLE_RULES = ['title_length', 'title_keyword', 'desc_length', 'desc_keyword', 'first_paragraph'];

    /** Records scanned per type when looking for work. */
    private const SCAN = 300;

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly MetaSuggestionService $meta,
        private readonly SeoFixService $seoFix,
        private readonly AltTextService $alt,
    ) {}

    /**
     * How much work there is, per kind (meta also per record type).
     *
     * @return array{meta: array<string, int>, seo_fix: int, alt: int}
     */
    public function counts(User $user, string $locale): array
    {
        return [
            'meta' => array_map(fn ($records) => $records->count(), $this->metaCandidates($user, $locale)),
            'seo_fix' => count($this->seoFixCandidates($user)),
            'alt' => count($this->altCandidates($user, $locale)),
        ];
    }

    /**
     * @param  list<string>  $types  record types, meta only
     * @return array{count: int, credits_estimated: int, credits_remaining: int}
     *
     * @throws GatewayUnavailable
     */
    public function estimate(string $kind, User $user, string $locale, array $types): array
    {
        [$inputs] = $this->inputs($kind, $user, $locale, $types);
        abort_if($inputs === [], 422, __('ai.bulk.nothing'));

        $response = $this->gateway->post('v1/jobs/'.self::KINDS[$kind], ['dry_run' => 1], ['items' => $inputs]);

        return ['count' => count($inputs), 'credits_estimated' => (int) $response['credits_estimated'], 'credits_remaining' => (int) $response['credits_remaining']];
    }

    /**
     * @param  list<string>  $types
     *
     * @throws GatewayUnavailable
     */
    public function start(string $kind, User $user, string $locale, array $types): AiBulkJob
    {
        [$inputs, $snapshot] = $this->inputs($kind, $user, $locale, $types);
        abort_if($inputs === [], 422, __('ai.bulk.nothing'));

        $response = $this->gateway->job(self::KINDS[$kind], $inputs, (string) Str::uuid());

        return AiBulkJob::query()->create([
            'gateway_job_id' => (int) $response['job_id'],
            'type' => self::KINDS[$kind],
            'locale' => $inputs[0]['locale'],
            'status' => 'queued',
            'items_total' => count($inputs),
            'credits_estimated' => (int) $response['credits_estimated'],
            'items' => $snapshot,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Ask the gateway how the job is doing; once finished, copy its results into ai_suggestions.
     *
     * @throws GatewayUnavailable
     */
    public function refresh(AiBulkJob $job): AiBulkJob
    {
        if ($job->imported_at) {
            return $job;
        }

        $remote = (array) $this->gateway->get('v1/jobs/'.$job->gateway_job_id)['job'];
        $job->fill(['status' => (string) $remote['status'], 'stop_reason' => $remote['stop_reason'] ?? null]);

        if ($job->isFinished()) {
            $kind = $job->kind();
            $items = $job->items;

            foreach ((array) ($remote['items'] ?? []) as $item) {
                $position = (int) ($item['position'] ?? -1);
                $known = $items[$position] ?? null;
                if (! $known || ($item['status'] ?? '') !== 'done' || empty($item['suggestion_id'])
                    || ($known['entity_id'] ?? null) !== ($item['entity_id'] ?? null)) {
                    continue;
                }

                $result = (array) ($item['result'] ?? []);
                [$oldValues, $options] = match ($kind) {
                    'meta' => [$known['old_values'], array_values(array_filter((array) ($result['options'] ?? []), 'is_array'))],
                    'seo_fix' => [$known['old_values'], [$this->seoFix->proposal($result, $known['old_values'], $known['failing'] ?? [])]],
                    'alt' => [['src' => $known['src']], filled($result['alt'] ?? null) ? [['alt' => (string) $result['alt']]] : []],
                };
                if ($options === []) {
                    continue;
                }

                $suggestion = AiSuggestion::query()->firstOrCreate(
                    ['gateway_suggestion_id' => (int) $item['suggestion_id'], 'task' => self::TASKS[$kind]],
                    [
                        'entity_type' => $known['entity_type'],
                        'entity_id' => (int) $known['entity_id'],
                        'locale' => $job->locale,
                        // What the record held when the job started: apply refuses if it changed since.
                        'old_values' => $oldValues,
                        'options' => $options,
                        'status' => AiSuggestion::PROPOSED,
                        'user_id' => $job->user_id,
                    ],
                );
                $items[$position]['suggestion_id'] = $suggestion->id;
            }

            $job->items = $items;
            $job->imported_at = now();
        }

        $job->save();

        return $job;
    }

    /** @return Collection<int, AiSuggestion> results of the job still waiting for a decision */
    public function review(AiBulkJob $job): Collection
    {
        $ids = array_values(array_filter(array_column($job->items, 'suggestion_id')));

        return AiSuggestion::query()->whereIn('id', $ids)->where('status', AiSuggestion::PROPOSED)->orderBy('id')->get()
            ->filter(fn (AiSuggestion $suggestion) => $suggestion->isOpen())
            ->values();
    }

    /**
     * Apply the chosen results; each one alone, so one conflict does not stop the rest.
     *
     * @param  array<int, array<string, string>>  $chosen  suggestion id => the values the admin kept
     * @return array{applied: int, conflicts: int, closed: int}
     */
    public function apply(AiBulkJob $job, array $chosen, User $user): array
    {
        $open = $this->review($job)->keyBy('id');
        $counts = ['applied' => 0, 'conflicts' => 0, 'closed' => 0];

        foreach ($chosen as $id => $values) {
            $suggestion = $open->get((int) $id);
            if (! $suggestion) {
                $counts['closed']++;

                continue;
            }

            try {
                match ($job->kind()) {
                    'meta' => $this->meta->apply($suggestion, 0, (string) ($values['title'] ?? ''), (string) ($values['description'] ?? ''), $user),
                    // Title and description as edited; the opening only as proposed, when ticked.
                    'seo_fix' => $this->seoFix->apply($suggestion, array_filter([
                        'seo_title' => (string) ($values['seo_title'] ?? ''),
                        'seo_description' => (string) ($values['seo_description'] ?? ''),
                        'intro_html' => ! empty($values['use_intro']) ? (string) ($suggestion->options[0]['intro_html'] ?? '') : '',
                    ], fn (string $value) => trim($value) !== ''), $user),
                    'alt' => $this->alt->apply($suggestion, (string) ($values['alt'] ?? ''), $user),
                };
                $counts['applied']++;
            } catch (HttpException $e) {
                $counts[$e->getStatusCode() === 409 ? 'conflicts' : 'closed']++;
            }
        }

        return $counts;
    }

    /**
     * Gateway inputs and the local snapshot, in the same order (the gateway answers by position).
     *
     * @param  list<string>  $types
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function inputs(string $kind, User $user, string $locale, array $types): array
    {
        $inputs = [];
        $snapshot = [];
        $limit = self::LIMITS[$kind];

        if ($kind === 'meta') {
            foreach ($this->metaCandidates($user, $locale) as $type => $records) {
                if (! in_array($type, $types, true)) {
                    continue;
                }
                foreach ($records as $record) {
                    $input = $this->meta->taskInput($type, $record, $locale);
                    if (trim((string) $input['content_text']) === '' && trim((string) $input['title']) === '') {
                        continue;
                    }
                    $inputs[] = $input;
                    $snapshot[] = ['entity_type' => $type, 'entity_id' => (int) $record->getKey(), 'title' => (string) $input['title'],
                        'old_values' => $this->meta->currentValuesOf($type, $record, $locale)];
                    if (count($inputs) >= $limit) {
                        break 2;
                    }
                }
            }
        } elseif ($kind === 'seo_fix') {
            // The SEO checker scores the default language, like the post form.
            $default = $this->languages->defaultLocale();
            foreach (array_slice($this->seoFixCandidates($user), 0, $limit) as [$post, $analysis]) {
                $input = $this->seoFix->taskInput($post, $default, $analysis);
                $inputs[] = $input;
                $snapshot[] = ['entity_type' => 'post', 'entity_id' => (int) $post->id, 'title' => (string) $input['title'],
                    'old_values' => $this->seoFix->current($post, $default), 'failing' => $input['failing_rules']];
            }
        } else {
            $bytes = 0;
            foreach ($this->altCandidates($user, $locale) as [$type, $record, $src]) {
                try {
                    $input = $this->alt->taskInput($type, $record, $locale, $src);
                } catch (GatewayUnavailable) {
                    // An image this website cannot read itself (external host, too large): left out.
                    continue;
                }
                $size = strlen($input['image']['data']) * 3 / 4;
                if ($bytes + $size > self::ALT_MAX_BYTES) {
                    break;
                }
                $bytes += $size;
                $inputs[] = $input;
                $snapshot[] = ['entity_type' => $type, 'entity_id' => (int) $record->getKey(), 'title' => (string) $input['page_title'], 'src' => $src];
                if (count($inputs) >= $limit) {
                    break;
                }
            }
        }

        return [$inputs, $snapshot];
    }

    /** @return array<string, Collection<int, Model>> records missing a meta title or description, per type */
    private function metaCandidates(User $user, string $locale): array
    {
        $found = [];
        foreach (MetaSuggestionService::ENTITIES as $type => $definition) {
            if (! $this->mayEdit($user, $definition)) {
                continue;
            }
            $found[$type] = $definition['model']::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $this->missing($query, $definition['meta_title'], $locale)
                    ->orWhere(fn (Builder $q) => $this->missing($q, $definition['meta_description'], $locale)))
                ->orderByDesc('updated_at')
                ->limit(self::LIMITS['meta'])
                ->get();
        }

        return $found;
    }

    /** @return list<array{0: Post, 1: array}> posts with a focus keyword that fail a text-fixable rule */
    private function seoFixCandidates(User $user): array
    {
        if (! $this->mayEdit($user, MetaSuggestionService::ENTITIES['post'])) {
            return [];
        }

        $found = [];
        $posts = Post::query()->where('is_active', true)->whereNotNull('seo_keys')->where('seo_keys', '!=', '')
            ->orderByDesc('updated_at')->limit(self::SCAN)->get();
        foreach ($posts as $post) {
            $analysis = $this->seoFix->analyze($post);
            $failing = array_keys(array_filter($analysis['rules'], fn (bool $passed) => ! $passed));
            if (array_intersect($failing, self::FIXABLE_RULES) !== []) {
                $found[] = [$post, $analysis];
            }
        }

        return $found;
    }

    /** @return list<array{0: string, 1: Model, 2: string}> type, record, src of each image without alt */
    private function altCandidates(User $user, string $locale): array
    {
        $found = [];
        foreach (AltTextService::ENTITY_TYPES as $type) {
            $definition = MetaSuggestionService::ENTITIES[$type];
            if (! $this->mayEdit($user, $definition)) {
                continue;
            }
            $records = $definition['model']::query()->where('is_active', true)->orderByDesc('updated_at')->limit(self::SCAN)->get();
            foreach ($records as $record) {
                $html = (string) $record->getTranslation($definition['content'], $locale, false);
                foreach ($this->alt->imagesWithoutAlt($html) as $src) {
                    $found[] = [$type, $record, $src];
                }
            }
        }

        return $found;
    }

    private function mayEdit(User $user, array $definition): bool
    {
        return $user->can($definition['permission']) && $this->features->availableTo($user, $definition['feature']);
    }

    /** No value for the locale in a translatable JSON column. */
    private function missing(Builder $query, string $column, string $locale): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull($column)
            ->orWhereNull($column.'->'.$locale)
            ->orWhere($column.'->'.$locale, ''));
    }
}
