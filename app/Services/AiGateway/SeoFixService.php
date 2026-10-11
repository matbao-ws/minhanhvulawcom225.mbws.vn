<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\Post;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\LanguageRegistry;
use App\Services\PostSeoAnalyzer;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Fix by SEO score" on a post. The website's checker (PostSeoAnalyzer, on the
 * saved post in the default language, as the form scores it) decides which
 * rules fail; the AI proposes a new SEO title, description and/or opening for
 * those rules only, and the owner picks which to apply. The score is computed
 * again by code after applying — the AI never reports its own score.
 */
class SeoFixService
{
    use HandlesAiSuggestions;

    public const TASK = 'seo.fix_suggest';

    public const FIELDS = ['seo_title', 'seo_description', 'intro_html'];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * @return array{0: AiSuggestion, 1: array<string, int>, 2: array}
     *
     * @throws GatewayUnavailable
     */
    public function suggest(int $id, User $user): array
    {
        [$definition, $post] = $this->resolve('post', $id, $user);
        $locale = $this->languages->defaultLocale();
        $analysis = $this->analyze($post);
        $failing = array_keys(array_filter($analysis['rules'], fn (bool $passed) => ! $passed));

        abort_if($failing === [], 422, __('ai.seo.all_good'));

        $current = $this->current($post, $locale);
        $response = $this->gateway->task(self::TASK, $this->taskInput($post, $locale, $analysis), (string) Str::uuid());

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => 'post',
            'entity_id' => $post->id,
            'locale' => $locale,
            'old_values' => $current,
            'options' => [$this->proposal((array) ($response['result'] ?? []), $current, $failing)],
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
        [$definition, $post] = $this->resolve('post', $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $values = array_filter(array_intersect_key($values, array_flip(self::FIELDS)), fn ($value) => trim((string) $value) !== '');
        abort_if($values === [], 422, __('ai.errors.option'));

        $scores = DB::transaction(function () use ($suggestion, $post, $values, $user) {
            /** @var Post $post */
            $post = Post::query()->lockForUpdate()->findOrFail($post->id);
            $locale = $suggestion->locale;
            $before = (int) $this->analyze($post)['score'];
            $current = $this->current($post, $locale);

            foreach (array_keys($values) as $field) {
                if ($current[$field] !== $suggestion->old_values[$field]) {
                    abort(409, __('ai.errors.conflict'));
                }
            }

            if (isset($values['seo_title'])) {
                $post->setTranslation('seo_title', $locale, Str::limit(trim($values['seo_title']), 255, ''));
            }
            if (isset($values['seo_description'])) {
                $post->setTranslation('seo_description', $locale, Str::limit(trim($values['seo_description']), 500, ''));
            }
            if (isset($values['intro_html'])) {
                $intro = $this->htmlSanitizer->clean($values['intro_html']);
                abort_unless($this->bodyReferences($intro) === $this->bodyReferences($current['intro_html']), 422, __('ai.errors.unsafe_output'));
                $body = (string) $post->getTranslation('content', $locale, false);
                $post->setTranslation('content', $locale, $this->htmlSanitizer->clean($intro.substr($body, strlen($current['intro_html']))));
            }

            $this->refreshPostScore($post);
            $post->save();

            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);
            ActivityLogger::log('ai_seo_fixed', $post, 'Sửa theo điểm SEO bằng AI', ['fields' => array_keys($values), 'score' => [$before, $post->seo_score]]);

            return ['before' => $before, 'after' => (int) $post->seo_score];
        });

        $proposal = $suggestion->options[0];
        $edited = collect($values)->contains(fn ($value, $field) => trim((string) $value) !== trim((string) ($proposal[$field] ?? '')));
        $this->report($suggestion, $edited ? 'applied_after_edit' : 'applied', ['option_index' => 0]);

        return $scores;
    }

    /**
     * What the model sees for one post — the same for a single click and a bulk job.
     *
     * @return array<string, mixed>
     */
    public function taskInput(Post $post, string $locale, array $analysis): array
    {
        $current = $this->current($post, $locale);

        return [
            'locale' => $locale,
            'entity_type' => 'post',
            'entity_id' => $post->id,
            'title' => Str::limit((string) $post->getTranslation('title', $locale, false), 290, ''),
            'focus_keyword' => filled($post->seo_keys) ? Str::limit((string) $post->seo_keys, 120, '') : null,
            'seo_title' => $current['seo_title'],
            'seo_description' => $current['seo_description'],
            'intro_html' => $current['intro_html'],
            'failing_rules' => array_keys(array_filter($analysis['rules'], fn (bool $passed) => ! $passed)),
            'metrics' => $analysis['metrics'],
        ];
    }

    /** The model's answer as an option: the opening sanitized, and dropped if it changed a link or image. */
    public function proposal(array $result, array $current, array $failing): array
    {
        $proposal = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($result[$field] ?? ''));
            if ($field === 'intro_html' && $value !== '') {
                $value = $this->htmlSanitizer->clean($value);
                // The opening may be reworded, never re-linked.
                if ($this->bodyReferences($value) !== $this->bodyReferences($current['intro_html'])) {
                    $value = '';
                }
            }
            $proposal[$field] = $value;
        }

        return $proposal + ['notes' => array_values(array_filter((array) ($result['notes'] ?? []), 'is_array')), 'failing' => $failing];
    }

    /** Scored like the post form: default language, saved fields. */
    public function analyze(Post $post): array
    {
        $locale = $this->languages->defaultLocale();

        return $this->seoAnalyzer->analyze([
            'title' => (string) $post->getTranslation('title', $locale, false),
            'slug' => (string) $post->slug,
            'content' => (string) $post->getTranslation('content', $locale, false),
            'seo_title' => (string) $post->getTranslation('seo_title', $locale, false),
            'seo_description' => (string) $post->getTranslation('seo_description', $locale, false),
            'focus_keyword' => $post->seo_keys,
        ]);
    }

    /** @return array{seo_title: string, seo_description: string, intro_html: string} */
    public function current(Post $post, string $locale): array
    {
        $body = (string) $post->getTranslation('content', $locale, false);
        // The opening: everything before the first sub-heading (what first_paragraph reads), at most 8,000 bytes.
        $cut = preg_match('/<h[23]\b/i', $body, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : strlen($body);

        return [
            'seo_title' => (string) $post->getTranslation('seo_title', $locale, false),
            'seo_description' => (string) $post->getTranslation('seo_description', $locale, false),
            'intro_html' => $cut <= 8000 ? substr($body, 0, $cut) : '',
        ];
    }
}
