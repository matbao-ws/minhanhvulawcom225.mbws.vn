<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * "Let the AI fix it" on a task of this week's SEO work. The task says what is wrong; this
 * class picks the existing AI tools that fix it, runs them one step at a time (the page shows
 * each step as it comes back, and no single request waits on several model calls), and
 * applies only what the owner kept, through each tool's own apply() — same permission,
 * conflict and sanitizing rules as on the record's form. Applying marks the task done for
 * the AI, which starts the gateway's before/after measurement.
 *
 * Declining pages and pages people find with the wrong intent are not fixed here: the cause
 * is usually outside the text (competitors, an update, the page type), so the owner decides.
 */
class SeoTaskFixer
{
    public const STEPS = ['meta', 'section', 'faq', 'alt'];

    /** Gateway task code per step, sent with "done" so outcomes can be read per tool. */
    public const TOOLS = [
        'meta' => MetaSuggestionService::TASK,
        'section' => QueryGapService::TASK,
        'faq' => FaqService::TASK,
        'alt' => AltTextService::TASK,
    ];

    /** Credits per step (alt: per image). */
    private const CREDITS = ['meta' => 1, 'section' => 2, 'faq' => 1, 'alt' => 1];

    /** Images captioned in one fix; the rest stay for the record's own alt tool. */
    public const MAX_IMAGES = 3;

    /** Words that make a search a question people would ask in an FAQ. */
    private const QUESTION_WORDS = [
        'là gì', 'bao nhiêu', 'như thế nào', 'thế nào', 'tại sao', 'vì sao', 'ở đâu', 'khi nào', 'có nên', 'có tốt không',
        'có được không', 'loại nào', 'nào tốt', 'cách ', 'làm sao', 'bao lâu', 'mấy ', 'what', 'how', 'why', 'where', 'when',
        'which', 'can ', 'does ', 'should ', 'is ',
    ];

    public function __construct(
        private readonly SeoTaskService $tasks,
        private readonly MetaSuggestionService $meta,
        private readonly QueryGapService $queryGap,
        private readonly FaqService $faq,
        private readonly AltTextService $alt,
    ) {}

    /**
     * What the AI would do for a task, before anything runs; null when the AI does not fix it.
     *
     * @return array{steps: list<string>, credits: int, manual: list<string>}|null
     */
    public function plan(array $task): ?array
    {
        $entity = $task['entity'] ?? null;
        if (! is_array($entity) || empty($task['edit_url'])) {
            return null;
        }

        $type = (string) ($entity['type'] ?? '');
        $issues = (array) ($task['issues'] ?? []);
        $steps = [];
        $manual = [];

        switch ($task['type'] ?? null) {
            case 'low_ctr':
                $steps[] = 'meta';
                break;
            case 'striking_distance':
                if ($this->queries($task) !== [] && in_array($type, QueryGapService::ENTITY_TYPES, true)) {
                    $steps[] = 'section';
                }
                if ($this->questions($task) !== [] && in_array($type, FaqService::ENTITY_TYPES, true)) {
                    $steps[] = 'faq';
                }
                break;
            case 'on_page':
                if (array_intersect($issues, ['missing_meta_title', 'missing_meta_description']) !== []) {
                    $steps[] = 'meta';
                }
                if (in_array('thin_content', $issues, true) && in_array($type, QueryGapService::ENTITY_TYPES, true)) {
                    $steps[] = 'section';
                }
                if (in_array('images_without_alt', $issues, true) && in_array($type, AltTextService::ENTITY_TYPES, true)) {
                    $steps[] = 'alt';
                }
                // Splitting a long post into sections is a writing decision.
                $manual = array_values(array_intersect($issues, ['no_headings']));
                break;
        }

        if ($steps === []) {
            return null;
        }

        $credits = array_sum(array_map(fn (string $step) => self::CREDITS[$step] * ($step === 'alt' ? self::MAX_IMAGES : 1), $steps));

        return ['steps' => $steps, 'credits' => $credits, 'manual' => $manual];
    }

    /**
     * Run one step of the plan and return what the review shows for it.
     *
     * @return array<string, mixed>
     *
     * @throws GatewayUnavailable
     */
    public function suggest(string $taskId, string $step, User $user): array
    {
        $task = $this->tasks->find($taskId, $user);
        $plan = $this->plan($task) ?? abort(422, __('ai.tasks.fix.unavailable'));
        abort_unless(in_array($step, $plan['steps'], true), 404, __('ai.errors.not_found'));

        $entity = $task['entity'];
        [$type, $id, $locale] = [(string) $entity['type'], (int) $entity['id'], (string) ($entity['locale'] ?? app()->getLocale())];

        return match ($step) {
            'meta' => $this->metaStep($task, $type, $id, $locale, $user),
            'section' => $this->sectionStep($task, $type, $id, $locale, $user),
            'faq' => $this->faqStep($task, $type, $id, $locale, $user),
            'alt' => $this->altStep($type, $id, $locale, $user),
            default => abort(404, __('ai.errors.not_found')),
        };
    }

    /**
     * Apply the changes the owner kept, then mark the task done for the AI.
     *
     * @param  list<array<string, mixed>>  $changes
     * @return array{applied: list<string>, failed: list<array{step: string, message: string}>, marked: bool}
     */
    public function apply(string $taskId, array $changes, User $user): array
    {
        $task = $this->tasks->find($taskId, $user);
        $plan = $this->plan($task) ?? abort(422, __('ai.tasks.fix.unavailable'));
        $entity = $task['entity'];

        // Order matters: meta does not touch the body; the section and FAQ each check the whole
        // body, so the FAQ is moved onto the body the section just wrote; alt checks one image.
        $byStep = collect($changes)->filter(fn (array $change) => in_array($change['step'] ?? null, $plan['steps'], true))
            ->keyBy('step');
        $applied = [];
        $failed = [];
        $bodyBeforeSection = null;

        foreach (self::STEPS as $step) {
            if (! $byStep->has($step)) {
                continue;
            }
            $change = $byStep->get($step);

            try {
                match ($step) {
                    'meta' => $this->meta->apply(
                        $this->suggestion($change['suggestion_id'] ?? null, MetaSuggestionService::TASK, $entity),
                        (int) ($change['option'] ?? 0),
                        Str::limit(trim((string) ($change['title'] ?? '')), MetaSuggestionService::TITLE_MAX, ''),
                        Str::limit(trim((string) ($change['description'] ?? '')), MetaSuggestionService::DESCRIPTION_MAX, ''),
                        $user,
                    ),
                    'section' => (function () use ($change, $entity, $user, &$bodyBeforeSection) {
                        $suggestion = $this->suggestion($change['suggestion_id'] ?? null, QueryGapService::TASK, $entity);
                        $bodyBeforeSection = $suggestion->old_values['html'] ?? null;
                        $this->queryGap->apply($suggestion, $user, is_array($change['section'] ?? null) ? $change['section'] : null);
                    })(),
                    'faq' => (function () use ($change, $entity, $user, $applied, $bodyBeforeSection) {
                        $suggestion = $this->suggestion($change['suggestion_id'] ?? null, FaqService::TASK, $entity);
                        if (in_array('section', $applied, true) && ($suggestion->old_values['html'] ?? null) === $bodyBeforeSection) {
                            // The only change since the FAQ was written is the section applied just now.
                            $suggestion->update(['old_values' => ['html' => $this->currentBody($entity)]]);
                        }
                        $this->faq->apply($suggestion, $this->faqItems($change['items'] ?? []), $user);
                    })(),
                    'alt' => $this->applyAlt((array) ($change['items'] ?? []), $entity, $user),
                };
                $applied[] = $step;
            } catch (HttpExceptionInterface $e) {
                $failed[] = ['step' => $step, 'message' => $e->getMessage() ?: __('ai.errors.default')];
            }
        }

        $marked = false;
        if ($applied !== []) {
            try {
                $this->tasks->mark($taskId, 'done', 'ai', array_map(fn (string $step) => self::TOOLS[$step], $applied));
                $marked = true;
            } catch (GatewayUnavailable $e) {
                // The page is fixed either way; only the measurement is missing. The owner can mark it done.
                Log::info('SEO task applied but not marked done.', ['reason' => $e->reason]);
            }
        }

        return ['applied' => $applied, 'failed' => $failed, 'marked' => $marked];
    }

    /** @return array<string, mixed> */
    private function metaStep(array $task, string $type, int $id, string $locale, User $user): array
    {
        [$suggestion, $usage] = $this->meta->suggest($type, $id, $locale, $user, $this->queries($task));

        return [
            'step' => 'meta',
            'suggestion_id' => $suggestion->id,
            'current' => $suggestion->old_values,
            'options' => array_values(array_map(fn ($option) => [
                'title' => (string) ($option['title'] ?? ''),
                'description' => (string) ($option['description'] ?? ''),
                'angle' => (string) ($option['angle'] ?? ''),
            ], (array) $suggestion->options)),
            'limits' => ['title' => 60, 'description' => 160],
            'usage' => $usage,
        ];
    }

    /** @return array<string, mixed> */
    private function sectionStep(array $task, string $type, int $id, string $locale, User $user): array
    {
        // A thin page found by no search yet: write on what the page itself is about.
        $queries = $this->queries($task) ?: array_values(array_filter([(string) ($task['entity']['focus_keyword'] ?? ''), (string) ($task['entity']['title'] ?? '')]));
        [$suggestion, $usage] = $this->queryGap->suggest($type, $id, $locale, $queries, $user);

        return ['step' => 'section', 'suggestion_id' => $suggestion->id, 'section' => $suggestion->options[0], 'usage' => $usage];
    }

    /** @return array<string, mixed> */
    private function faqStep(array $task, string $type, int $id, string $locale, User $user): array
    {
        [$suggestion, $usage] = $this->faq->suggest($type, $id, $locale, $user, $this->questions($task));

        return ['step' => 'faq', 'suggestion_id' => $suggestion->id, 'items' => $suggestion->options, 'usage' => $usage];
    }

    /** @return array<string, mixed> */
    private function altStep(string $type, int $id, string $locale, User $user): array
    {
        $missing = $this->alt->missing($type, $id, $locale, $user);
        $items = [];
        $usage = [];
        foreach (array_slice($missing, 0, self::MAX_IMAGES) as $src) {
            try {
                [$suggestion, $usage] = $this->alt->suggest($type, $id, $locale, $src, $user);
                $items[] = ['suggestion_id' => $suggestion->id, 'src' => $src, 'alt' => (string) ($suggestion->options[0]['alt'] ?? '')];
            } catch (GatewayUnavailable $e) {
                // An image this website cannot read (hot-linked elsewhere) is skipped, not fatal.
                if ($e->reason !== 'image_unavailable' || count($missing) === 1) {
                    throw $e;
                }
            }
        }
        abort_if($items === [], 422, __('ai.tasks.fix.no_images'));

        return ['step' => 'alt', 'items' => $items, 'remaining' => max(0, count($missing) - self::MAX_IMAGES), 'usage' => $usage];
    }

    /** @param  array<int, mixed>  $items */
    private function applyAlt(array $items, array $entity, User $user): void
    {
        $done = 0;
        foreach ($items as $item) {
            $alt = Str::limit(trim((string) ($item['alt'] ?? '')), 250, '');
            if (! is_array($item) || $alt === '') {
                continue;
            }
            $this->alt->apply($this->suggestion($item['suggestion_id'] ?? null, AltTextService::TASK, $entity), $alt, $user);
            $done++;
        }
        abort_if($done === 0, 422, __('ai.errors.option'));
    }

    /** @return list<array{question: string, answer: string}> */
    private function faqItems(mixed $items): array
    {
        return array_values(array_filter(array_map(fn ($item) => [
            'question' => Str::limit(trim((string) ($item['question'] ?? '')), 300, ''),
            'answer' => Str::limit(trim((string) ($item['answer'] ?? '')), 2000, ''),
        ], array_filter((array) $items, 'is_array')), fn (array $item) => $item['question'] !== '' && $item['answer'] !== ''));
    }

    /** An open suggestion of this tool, made for the task's record. */
    private function suggestion(mixed $id, string $tool, array $entity): AiSuggestion
    {
        $suggestion = AiSuggestion::query()->find((int) $id);
        abort_unless($suggestion
            && $suggestion->task === $tool
            && $suggestion->entity_type === (string) $entity['type']
            && (int) $suggestion->entity_id === (int) $entity['id'], 404, __('ai.errors.not_found'));

        return $suggestion;
    }

    private function currentBody(array $entity): string
    {
        $definition = MetaSuggestionService::ENTITIES[(string) $entity['type']];
        $record = $definition['model']::query()->findOrFail((int) $entity['id']);

        return (string) $record->getTranslation($definition['content'], (string) ($entity['locale'] ?? app()->getLocale()), false);
    }

    /** @return list<string> the task's searches, most seen first */
    private function queries(array $task): array
    {
        return array_values(array_filter(array_map(fn ($query) => trim((string) ($query['query'] ?? '')), (array) ($task['queries'] ?? []))));
    }

    /** @return list<string> */
    private function questions(array $task): array
    {
        return array_values(array_filter($this->queries($task), function (string $query) {
            $text = ' '.mb_strtolower($query).' ';

            return str_contains($text, '?') || collect(self::QUESTION_WORDS)->contains(fn (string $word) => str_contains($text, ' '.$word));
        }));
    }
}
