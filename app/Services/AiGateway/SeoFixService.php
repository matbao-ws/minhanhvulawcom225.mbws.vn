<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\LanguageRegistry;
use App\Services\PageContentService;
use App\Services\PostSeoAnalyzer;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use App\Support\PageHtmlSanitizer;
use App\Support\Seo\SeoBox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Fix by SEO score" on a post, product or policy page (the records with the SEO box). The
 * website's checker (PostSeoAnalyzer through SeoBox, on the saved record in the default
 * language, as the form scores it) decides which rules fail; the AI proposes a new SEO title,
 * description and/or opening for those rules only, and the owner picks which to apply. The
 * score is computed again by code after applying — the AI never reports its own score.
 */
class SeoFixService
{
    use HandlesAiSuggestions;

    public const TASK = 'seo.fix_suggest';

    /** What the task proposes; per record they land in its SEO title, description and body. */
    public const FIELDS = ['seo_title', 'seo_description', 'intro_html'];

    /** The records the button and bulk jobs work on (SeoBox::MODELS by entity type). */
    public const ENTITY_TYPES = [
        'post' => Post::class, 'product' => Product::class, 'page' => Page::class,
        'category' => Category::class, 'post_category' => PostCategory::class,
    ];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PageHtmlSanitizer $pageSanitizer,
        private readonly PageContentService $pages,
        private readonly SeoBox $seoBox,
    ) {}

    /**
     * @return array{0: AiSuggestion, 1: array<string, int>, 2: array}
     *
     * @throws GatewayUnavailable
     */
    public function suggest(int $id, User $user, string $type = 'post'): array
    {
        $record = $this->record($type, $id, $user);
        $locale = $this->languages->defaultLocale();
        $analysis = $this->analyze($record);
        $failing = array_keys(array_filter($analysis['rules'], fn (bool $passed) => ! $passed));

        abort_if($failing === [], 422, __('ai.seo.all_good'));

        $current = $this->current($record, $locale);
        $response = $this->gateway->task(self::TASK, $this->taskInput($record, $locale, $analysis), (string) Str::uuid());

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => $current,
            'options' => [$this->proposal((array) ($response['result'] ?? []), $current, $failing, $record)],
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? []), $analysis];
    }

    /**
     * Apply the fields the owner kept (and possibly edited).
     *
     * @param  array<string, string>  $values  subset of FIELDS
     * @return array{before: int, after: int}
     */
    public function apply(AiSuggestion $suggestion, array $values, User $user): array
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        $record = $this->record($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $values = array_filter(array_intersect_key($values, array_flip(self::FIELDS)), fn ($value) => trim((string) $value) !== '');
        abort_if($values === [], 422, __('ai.errors.option'));

        $scores = DB::transaction(function () use ($suggestion, $record, $values, $user) {
            $record = $record->newQuery()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();
            $map = SeoBox::MODELS[$record::class];
            $locale = $suggestion->locale;
            $before = (int) $this->analyze($record)['score'];
            $current = $this->current($record, $locale);

            foreach (array_keys($values) as $field) {
                if ($current[$field] !== ($suggestion->old_values[$field] ?? null)) {
                    abort(409, __('ai.errors.conflict'));
                }
            }

            if (isset($values['seo_title'])) {
                $record->setTranslation($map['seo_title'], $locale, Str::limit(trim($values['seo_title']), 255, ''));
            }
            if (isset($values['seo_description'])) {
                $record->setTranslation($map['seo_description'], $locale, Str::limit(trim($values['seo_description']), 500, ''));
            }
            $body = null;
            if (isset($values['intro_html'])) {
                $intro = $this->sanitizeBody($record, $values['intro_html']);
                abort_unless($this->bodyReferences($intro) === $this->bodyReferences($current['intro_html']), 422, __('ai.errors.unsafe_output'));
                $body = $intro.substr((string) $record->getTranslation($map['content'], $locale, false), strlen($current['intro_html']));
            }

            if ($record instanceof Page) {
                $record->save();
                // The page body goes the way its own editors write it: sanitizer + revision checkpoint.
                if ($body !== null) {
                    $this->pages->updateLocale($record, $locale, $body, $user->id);
                }
            } else {
                if ($body !== null) {
                    $record->setTranslation($map['content'], $locale, $this->htmlSanitizer->cleanRichText($body));
                }
                if ($record instanceof Post) {
                    $this->refreshPostScore($record);
                }
                $record->save();
            }
            if (! $record instanceof Post) {
                $this->seoBox->refreshScore($record);
            }

            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);
            $after = (int) $record->refresh()->seo_score;
            ActivityLogger::log('ai_seo_fixed', $record, 'Sửa theo điểm SEO bằng AI', ['fields' => array_keys($values), 'score' => [$before, $after]]);

            return ['before' => $before, 'after' => $after];
        });

        $proposal = $suggestion->options[0];
        $edited = collect($values)->contains(fn ($value, $field) => trim((string) $value) !== trim((string) ($proposal[$field] ?? '')));
        $this->report($suggestion, $edited ? 'applied_after_edit' : 'applied', ['option_index' => 0]);

        return $scores;
    }

    /**
     * What the model sees for one record — the same for a single click and a bulk job.
     *
     * @return array<string, mixed>
     */
    public function taskInput(Post|Product|Page|Category|PostCategory $record, string $locale, array $analysis): array
    {
        $current = $this->current($record, $locale);
        $map = SeoBox::MODELS[$record::class];

        return [
            'locale' => $locale,
            'entity_type' => (string) array_search($record::class, self::ENTITY_TYPES, true),
            'entity_id' => $record->getKey(),
            'title' => Str::limit((string) $record->getTranslation($map['title'], $locale, false), 290, ''),
            'focus_keyword' => filled($record->getAttribute('seo_keys')) ? Str::limit((string) $record->getAttribute('seo_keys'), 120, '') : null,
            'seo_title' => $current['seo_title'],
            'seo_description' => $current['seo_description'],
            'intro_html' => $current['intro_html'],
            'failing_rules' => array_keys(array_filter($analysis['rules'], fn (bool $passed) => ! $passed)),
            'metrics' => $analysis['metrics'],
        ];
    }

    /** The model's answer as an option: the opening sanitized, and dropped if it changed a link or image. */
    public function proposal(array $result, array $current, array $failing, Post|Product|Page|Category|PostCategory|null $record = null): array
    {
        $proposal = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($result[$field] ?? ''));
            if ($field === 'intro_html' && $value !== '') {
                $value = $record ? $this->sanitizeBody($record, $value) : $this->htmlSanitizer->cleanRichText($value);
                // The opening may be reworded, never re-linked.
                if ($this->bodyReferences($value) !== $this->bodyReferences($current['intro_html'])) {
                    $value = '';
                }
            }
            $proposal[$field] = $value;
        }

        return $proposal + ['notes' => array_values(array_filter((array) ($result['notes'] ?? []), 'is_array')), 'failing' => $failing];
    }

    /** Scored like the record's form: default language, saved fields. */
    public function analyze(Post|Product|Page|Category|PostCategory $record): array
    {
        return $this->seoBox->analyze($record);
    }

    /** The record behind a suggestion, once the user may change it; only types with an SEO box. */
    private function record(string $type, int $id, User $user): Post|Product|Page|Category|PostCategory
    {
        abort_unless(isset(self::ENTITY_TYPES[$type]), 404, __('ai.errors.not_found'));
        [, $record] = $this->resolve($type, $id, $user);
        if (! $record instanceof Post && ! $record instanceof Product && ! $record instanceof Page
            && ! $record instanceof Category && ! $record instanceof PostCategory) {
            abort(404, __('ai.errors.not_found'));
        }

        return $record;
    }

    /** @return array{seo_title: string, seo_description: string, intro_html: string} */
    public function current(Post|Product|Page|Category|PostCategory $record, string $locale): array
    {
        $map = SeoBox::MODELS[$record::class];
        $body = (string) $record->getTranslation($map['content'], $locale, false);
        // The opening: everything before the first sub-heading (what first_paragraph reads), at most 8,000 bytes.
        $cut = preg_match('/<h[23]\b/i', $body, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : strlen($body);

        return [
            'seo_title' => (string) $record->getTranslation($map['seo_title'], $locale, false),
            'seo_description' => (string) $record->getTranslation($map['seo_description'], $locale, false),
            'intro_html' => $cut <= 8000 ? substr($body, 0, $cut) : '',
        ];
    }
}
