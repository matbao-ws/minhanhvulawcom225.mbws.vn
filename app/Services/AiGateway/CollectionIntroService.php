<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\LanguageRegistry;
use App\Services\LocalizedContent;
use App\Services\PageContentService;
use App\Services\PostSeoAnalyzer;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use App\Support\PageHtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Write the description with AI" on a product category, blog category or brand: an introduction
 * written from its name, its parent and the titles of the products or posts it holds — nothing
 * else about it is sent, and the owner reviews the text before it replaces the description.
 */
class CollectionIntroService
{
    use HandlesAiSuggestions;

    public const TASK = 'content.collection_intro';

    public const MIN_WORDS = 60;

    public const MAX_WORDS = 400;

    public const TIMEOUT = 58;

    /** At most this many product or post titles go with the request. */
    private const MAX_ITEMS = 30;

    public const ENTITIES = [
        'category' => ['model' => Category::class, 'feature' => 'catalog', 'permission' => 'products.update', 'title' => 'name', 'content' => 'description'],
        'post_category' => ['model' => PostCategory::class, 'feature' => 'cms_page', 'permission' => 'posts.update', 'title' => 'name', 'content' => 'description'],
        'brand' => ['model' => Brand::class, 'feature' => 'catalog', 'permission' => 'products.update', 'title' => 'name', 'content' => 'description'],
    ];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PageHtmlSanitizer $pageSanitizer,
        private readonly PageContentService $pages,
        private readonly LocalizedContent $content,
    ) {}

    /**
     * @return array{0: AiSuggestion, 1: array<string, int>}
     *
     * @throws GatewayUnavailable
     */
    public function suggest(string $type, int $id, string $locale, int $words, User $user): array
    {
        [$definition, $record] = $this->resolve($type, $id, $user);
        abort_unless($this->languages->supports($locale), 422, __('ai.errors.locale'));

        $current = $this->body($definition, $record, $locale);
        $keywords = (string) $record->getAttribute('seo_keys');
        $parent = $record->getAttribute('parent_id') ? $record->newQuery()->find($record->getAttribute('parent_id')) : null;

        $response = $this->gateway->task(self::TASK, [
            'locale' => $locale,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'name' => Str::limit((string) $this->content->get($record, 'name', $locale), 290, ''),
            'parent' => $parent ? Str::limit((string) $this->content->get($parent, 'name', $locale), 290, '') : null,
            'items' => $this->items($type, $record, $locale),
            'current_description' => $current !== '' ? $current : null,
            'focus_keyword' => $keywords !== '' ? Str::limit(PostSeoAnalyzer::primaryKeyword($keywords), 120, '') : null,
            'target_words' => $words,
        ], (string) Str::uuid(), self::TIMEOUT);

        $html = $this->htmlSanitizer->cleanRichText($this->withoutLinksAndImages((string) ($response['result']['html'] ?? '')));
        abort_if(trim(strip_tags($html)) === '', 422, __('ai.errors.default'));

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => ['html' => $current],
            'options' => [['html' => $html, 'missing_info' => array_values(array_filter((array) ($response['result']['missing_info'] ?? []), 'is_string'))]],
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? [])];
    }

    public function apply(AiSuggestion $suggestion, User $user): Model
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $record = DB::transaction(function () use ($suggestion, $definition, $record, $user) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());

            if ($this->body($definition, $record, $suggestion->locale) !== $suggestion->old_values['html']) {
                abort(409, __('ai.errors.conflict_content'));
            }

            $this->writeBody($record, $definition, $suggestion->locale, (string) $suggestion->options[0]['html'], $user);
            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);
            ActivityLogger::log('ai_collection_described', $record, 'Viết mô tả bằng AI ('.$suggestion->locale.')');

            return $record;
        });

        $this->report($suggestion, 'applied', ['option_index' => 0]);

        return $record;
    }

    /**
     * Brands have no SEO meta, so their definitions are this tool's own, not MetaSuggestionService's.
     *
     * @return array{0: array, 1: Model}
     */
    protected function resolve(string $type, int $id, User $user): array
    {
        $definition = self::ENTITIES[$type] ?? abort(404, __('ai.errors.not_found'));

        abort_unless($this->features->availableTo($user, $definition['feature']), 404, __('ai.errors.not_found'));
        abort_unless($user->can($definition['permission']), 403, __('ai.errors.forbidden'));

        $record = $definition['model']::query()->find($id) ?? abort(404, __('ai.errors.not_found'));

        return [$definition, $record];
    }

    /** @return list<string> the titles of the products or posts the collection holds, newest first */
    private function items(string $type, Model $record, string $locale): array
    {
        $query = match ($type) {
            'category' => Product::query()->where('category_id', $record->getKey())->where('is_active', true),
            'brand' => Product::query()->where('brand_id', $record->getKey())->where('is_active', true),
            'post_category' => Post::query()->where('category_id', $record->getKey())->where('is_active', true),
        };
        $field = $type === 'post_category' ? 'title' : 'name';

        return $query->latest('id')->limit(self::MAX_ITEMS)->get()
            ->map(fn (Model $item) => Str::limit(trim(strip_tags((string) $this->content->get($item, $field, $locale))), 290, ''))
            ->filter()->values()->all();
    }
}
