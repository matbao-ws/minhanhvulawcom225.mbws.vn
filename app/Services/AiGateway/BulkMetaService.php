<?php

namespace App\Services\AiGateway;

use App\Models\AiBulkJob;
use App\Models\AiSuggestion;
use App\Models\User;
use App\Support\FeatureGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Bulk SEO": meta for every record that lacks it, as one gateway job
 * (`bulk.meta`). Results land in ai_suggestions like single suggestions and
 * are applied through MetaSuggestionService::apply, so the conflict check,
 * permissions and activity log are the same as one click — a record edited
 * after the job started is never overwritten.
 */
class BulkMetaService
{
    public const TYPE = 'bulk.meta';

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly MetaSuggestionService $meta,
    ) {}

    /**
     * Records missing a meta title or description in the locale, per type the user may edit.
     *
     * @return array<string, Collection<int, Model>>
     */
    public function candidates(User $user, string $locale, int $limit = 100): array
    {
        $found = [];
        foreach (MetaSuggestionService::ENTITIES as $type => $definition) {
            if (! $user->can($definition['permission']) || ! $this->features->availableTo($user, $definition['feature'])) {
                continue;
            }

            $found[$type] = $definition['model']::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $this->missing($query, $definition['meta_title'], $locale)
                    ->orWhere(fn (Builder $q) => $this->missing($q, $definition['meta_description'], $locale)))
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get();
        }

        return $found;
    }

    /**
     * Ask the gateway what a job would cost.
     *
     * @param  list<string>  $types
     * @return array{count: int, credits_estimated: int, credits_remaining: int}
     *
     * @throws GatewayUnavailable
     */
    public function estimate(User $user, string $locale, array $types): array
    {
        [$inputs] = $this->inputs($user, $locale, $types);
        abort_if($inputs === [], 422, __('ai.bulk.nothing'));

        $response = $this->gateway->post('v1/jobs/'.self::TYPE, ['dry_run' => 1], ['items' => $inputs]);

        return ['count' => count($inputs), 'credits_estimated' => (int) $response['credits_estimated'], 'credits_remaining' => (int) $response['credits_remaining']];
    }

    /**
     * @param  list<string>  $types
     *
     * @throws GatewayUnavailable
     */
    public function start(User $user, string $locale, array $types): AiBulkJob
    {
        [$inputs, $snapshot] = $this->inputs($user, $locale, $types);
        abort_if($inputs === [], 422, __('ai.bulk.nothing'));

        $response = $this->gateway->job(self::TYPE, $inputs, (string) Str::uuid());

        return AiBulkJob::query()->create([
            'gateway_job_id' => (int) $response['job_id'],
            'type' => self::TYPE,
            'locale' => $locale,
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
            $snapshot = collect($job->items)->keyBy(fn ($item) => $item['entity_type'].':'.$item['entity_id']);

            foreach ((array) ($remote['items'] ?? []) as $item) {
                $known = $snapshot->get(($item['entity_type'] ?? '').':'.($item['entity_id'] ?? ''));
                $options = array_values(array_filter((array) ($item['result']['options'] ?? []), 'is_array'));
                if (! $known || ($item['status'] ?? '') !== 'done' || $options === [] || empty($item['suggestion_id'])) {
                    continue;
                }

                AiSuggestion::query()->firstOrCreate(
                    ['gateway_suggestion_id' => (int) $item['suggestion_id'], 'task' => MetaSuggestionService::TASK],
                    [
                        'entity_type' => $known['entity_type'],
                        'entity_id' => (int) $known['entity_id'],
                        'locale' => $job->locale,
                        // What the meta held when the job started: apply refuses if it changed since.
                        'old_values' => $known['old_values'],
                        'options' => $options,
                        'status' => AiSuggestion::PROPOSED,
                        'user_id' => $job->user_id,
                    ],
                );
            }
            $job->imported_at = now();
        }

        $job->save();

        return $job;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, AiSuggestion> open suggestions of the job */
    public function review(AiBulkJob $job)
    {
        $ids = collect($job->items)->groupBy('entity_type')->map(fn ($items) => $items->pluck('entity_id')->all());

        return AiSuggestion::query()
            ->where('task', MetaSuggestionService::TASK)
            ->where('locale', $job->locale)
            ->where('status', AiSuggestion::PROPOSED)
            ->where('created_at', '>=', $job->created_at)
            ->where(function (Builder $query) use ($ids) {
                foreach ($ids as $type => $list) {
                    $query->orWhere(fn (Builder $q) => $q->where('entity_type', $type)->whereIn('entity_id', $list));
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Apply the chosen results; each one alone, so one conflict does not stop the rest.
     *
     * @param  list<array{suggestion_id: int, title: string, description: string}>  $chosen
     * @return array{applied: int, conflicts: int, closed: int}
     */
    public function apply(AiBulkJob $job, array $chosen, User $user): array
    {
        $open = $this->review($job)->keyBy('id');
        $counts = ['applied' => 0, 'conflicts' => 0, 'closed' => 0];

        foreach ($chosen as $row) {
            $suggestion = $open->get((int) $row['suggestion_id']);
            if (! $suggestion) {
                $counts['closed']++;

                continue;
            }

            try {
                $this->meta->apply($suggestion, 0, $row['title'], $row['description'], $user);
                $counts['applied']++;
            } catch (HttpException $e) {
                $counts[$e->getStatusCode() === 409 ? 'conflicts' : 'closed']++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<string>  $types
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} gateway inputs and the local snapshot
     */
    private function inputs(User $user, string $locale, array $types): array
    {
        $inputs = [];
        $snapshot = [];
        foreach ($this->candidates($user, $locale) as $type => $records) {
            if (! in_array($type, $types, true)) {
                continue;
            }
            foreach ($records as $record) {
                $input = $this->meta->taskInput($type, $record, $locale);
                if (trim((string) $input['content_text']) === '' && trim((string) $input['title']) === '') {
                    continue;
                }
                $inputs[] = $input;
                $snapshot[] = [
                    'entity_type' => $type,
                    'entity_id' => (int) $record->getKey(),
                    'title' => (string) $input['title'],
                    'old_values' => $this->meta->currentValuesOf($type, $record, $locale),
                ];
                if (count($inputs) >= 100) {
                    break 2;
                }
            }
        }

        return [$inputs, $snapshot];
    }

    /** No value for the locale in a translatable JSON column. */
    private function missing(Builder $query, string $column, string $locale): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull($column)
            ->orWhereNull($column.'->'.$locale)
            ->orWhere($column.'->'.$locale, ''));
    }
}
