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
use App\Services\PostSeoAnalyzer;
use App\Services\SiteBranding;
use App\Support\FeatureGate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Meta title/description suggestions from the MatBao AI gateway.
 *
 * The website builds the model's input itself from the record — the browser
 * only names the record — so nothing but the record's public content leaves
 * the site. A suggestion is written only when an admin applies it, only to the
 * two meta fields of one locale, and only if those fields still hold what they
 * held when the suggestion was made.
 */
class MetaSuggestionService
{
    use HandlesAiSuggestions;

    public const TASK = 'meta.suggest';

    /**
     * entity type => model, feature, permission to change it, and its fields.
     *
     * @var array<string, array{model: class-string<Model>, feature: string, permission: string, title: string, content: string, meta_title: string, meta_description: string}>
     */
    public const ENTITIES = [
        'product' => ['model' => Product::class, 'feature' => 'catalog', 'permission' => 'products.update',
            'title' => 'name', 'content' => 'description', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
        'post' => ['model' => Post::class, 'feature' => 'cms_page', 'permission' => 'posts.update',
            'title' => 'title', 'content' => 'content', 'meta_title' => 'seo_title', 'meta_description' => 'seo_description'],
        'page' => ['model' => Page::class, 'feature' => 'cms_page', 'permission' => 'pages.update',
            'title' => 'title', 'content' => 'published_html', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
        'category' => ['model' => Category::class, 'feature' => 'catalog', 'permission' => 'products.update',
            'title' => 'name', 'content' => 'description', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
        'post_category' => ['model' => PostCategory::class, 'feature' => 'cms_page', 'permission' => 'posts.update',
            'title' => 'name', 'content' => 'description', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
    ];

    /** Limits of the admin forms the values end up in. */
    public const TITLE_MAX = 255;

    public const DESCRIPTION_MAX = 500;

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly SiteBranding $branding,
        private readonly PostSeoAnalyzer $seoAnalyzer,
    ) {}

    /**
     * @return array{0: AiSuggestion, 1: array<string, int>, 2: string} the suggestion, the credit usage after it,
     *                                                                  and the focus keyword the model chose (or echoed)
     *
     * @throws GatewayUnavailable
     */
    public function suggest(string $type, int $id, string $locale, User $user): array
    {
        [$definition, $record] = $this->resolve($type, $id, $user);
        abort_unless($this->languages->supports($locale), 422, __('ai.errors.locale'));

        $current = $this->currentValues($definition, $record, $locale);
        $input = $this->taskInput($type, $record, $locale);

        $response = $this->gateway->task(self::TASK, $input, (string) Str::uuid());

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => $current,
            'options' => (array) ($response['result']['options'] ?? []),
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? []), trim((string) ($response['result']['focus_keyword'] ?? ''))];
    }

    /**
     * Write the chosen (and possibly edited) option to the record.
     *
     * @throws HttpException 409 when the record changed since the suggestion, 410 when it is closed
     */
    public function apply(AiSuggestion $suggestion, int $option, string $title, string $description, User $user): Model
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $proposed = $suggestion->options[$option] ?? abort(422, __('ai.errors.option'));

        DB::transaction(function () use ($suggestion, $definition, $record, $option, $title, $description, $user) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());

            if ($this->currentValues($definition, $record, $suggestion->locale) !== $suggestion->old_values) {
                abort(409, __('ai.errors.conflict'));
            }

            $before = $this->currentValues($definition, $record, $suggestion->locale);
            $record->setTranslation($definition['meta_title'], $suggestion->locale, $title);
            $record->setTranslation($definition['meta_description'], $suggestion->locale, $description);

            if ($record instanceof Post) {
                $this->refreshPostScore($record);
            }

            $record->save();
            $this->refreshBoxScore($record);

            $suggestion->update([
                'status' => AiSuggestion::APPLIED,
                'applied_option' => $option,
                'user_id' => $user->id,
                'applied_at' => now(),
            ]);

            ActivityLogger::log('ai_meta_applied', $record, 'Áp dụng gợi ý meta từ AI ('.$suggestion->locale.')', [
                'before' => $before,
                'after' => ['title' => $title, 'description' => $description],
            ]);
        });

        $distance = $this->editDistance($proposed['title'].'|'.$proposed['description'], $title.'|'.$description);
        $this->report($suggestion, $distance < 0.0001 ? 'applied' : 'applied_after_edit', [
            'option_index' => $option,
            'edit_distance' => round($distance, 4),
        ]);

        return $record;
    }

    /**
     * What the model sees for one record — the same for a single click and a bulk job.
     *
     * @return array<string, mixed>
     */
    public function taskInput(string $type, Model $record, string $locale): array
    {
        $definition = self::ENTITIES[$type];
        $current = $this->currentValues($definition, $record, $locale);

        return [
            'locale' => $locale,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'title' => Str::limit($this->text($record, $definition['title'], $locale), 290, ''),
            // Public content only, as plain text: no markup, no customer data.
            'content_text' => Str::limit($this->text($record, $definition['content'], $locale), 19000, ''),
            'current_meta_title' => $current['title'] !== '' ? $current['title'] : null,
            'current_meta_description' => $current['description'] !== '' ? $current['description'] : null,
            'site_name' => Str::limit((string) ($this->branding->current()['name'] ?? ''), 120, ''),
            // Posts carry a focus keyword the SEO checker scores the meta against.
            // The focus keyword (the first of the SEO box's badges) of any record that has one.
            'focus_keyword' => filled($record->getAttribute('seo_keys'))
                ? Str::limit(PostSeoAnalyzer::primaryKeyword((string) $record->getAttribute('seo_keys')), 120, '')
                : null,
        ];
    }

    /** @return array{title: string, description: string} */
    public function currentValuesOf(string $type, Model $record, string $locale): array
    {
        return $this->currentValues(self::ENTITIES[$type], $record, $locale);
    }

    /** @return array{title: string, description: string} */
    private function currentValues(array $definition, Model $record, string $locale): array
    {
        return [
            'title' => (string) $record->getTranslation($definition['meta_title'], $locale, false),
            'description' => (string) $record->getTranslation($definition['meta_description'], $locale, false),
        ];
    }

    private function text(Model $record, string $field, string $locale): string
    {
        $value = (string) ($record->getTranslation($field, $locale, false)
            ?: $record->getTranslation($field, $this->languages->defaultLocale(), false));

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)));
    }

    /** 0 = applied as proposed, 1 = entirely rewritten (byte-level Levenshtein, normalised). */
    private function editDistance(string $proposed, string $final): float
    {
        return min(1, levenshtein($proposed, $final) / max(strlen($proposed), strlen($final), 1));
    }
}
