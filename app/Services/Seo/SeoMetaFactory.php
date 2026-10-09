<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Services\LanguageRegistry;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Route;
use App\Services\LocalizedContent;
use App\Services\SiteBranding;
use App\Support\Seo\RecordLocales;
use App\Support\Seo\SeoMeta;
use App\Support\Seo\StructuredData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds the <head> metadata of storefront pages. Controllers hand it what the
 * record says; URLs, hreflang and indexing policy are decided here once, so
 * every page type (and every theme page added later) gets the same rules.
 */
class SeoMetaFactory
{
    public const DESCRIPTION_LIMIT = 160;

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly SiteBranding $branding,
        private readonly LocalizedContent $content,
    ) {}

    /**
     * Metadata for a record served by a `{locale}/…/{slug}` storefront route.
     *
     * @param  Model  $model  uses HasLocalizedSlugs
     * @param  list<array{name: string, url: string}>  $breadcrumbs  without the current page; it is appended
     */
    public function forRecord(
        Model $model,
        string $routeName,
        string $title,
        ?string $description = null,
        ?string $image = null,
        string $type = 'website',
        array $breadcrumbs = [],
        bool $index = true,
        bool $follow = true,
        ?string $canonical = null,
        array $structuredData = [],
    ): SeoMeta {
        $locale = app()->getLocale();
        $url = fn (string $code) => route($routeName, ['locale' => $code, 'slug' => $model->canonicalSlug($code)]);

        // Only the languages the record has its own text in: elsewhere it shows the fallback's
        // text, and that URL is not a translation search engines should index.
        $fallback = $this->languages->fallbackLocale();
        $own = RecordLocales::of($model, $this->languages->codes(), $fallback);

        // An owner-set canonical (a post republished from elsewhere) wins; it must be absolute.
        $canonical = $canonical && preg_match('#^https?://#i', $canonical)
            ? $canonical
            : $this->withPage($url(RecordLocales::canonicalLocale($locale, $own, $fallback)));

        $alternates = [];

        // hreflang only means something when there is more than one language.
        if (count($own) > 1) {
            foreach ($own as $code) {
                $alternates[$code] = $url($code);
            }
        }
        $default = $this->languages->defaultLocale();

        $trail = [...$breadcrumbs, ['name' => $title, 'url' => $url($locale)]];

        return new SeoMeta(
            title: $title,
            description: $this->description($description),
            canonical: $canonical,
            index: $index && $this->indexable(),
            follow: $follow,
            type: $type,
            image: $image ?: ($this->branding->current()['logo_url'] ?? null),
            alternates: $alternates,
            xDefault: $alternates ? $url(in_array($default, $own, true) ? $default : $own[0]) : null,
            structuredData: array_values(array_filter([StructuredData::breadcrumbs($trail), ...$structuredData])),
        );
    }

    /**
     * A product detail page, for a theme's `client.products.show` route: meta, breadcrumbs
     * through its category and Product JSON-LD with price, availability and visible reviews.
     * Load it with ProductQueryService::findActiveDetail() so the reviews are the visible ones.
     */
    public function forProduct(Product $product, string $routeName = 'client.products.show'): SeoMeta
    {
        $name = (string) $this->content->get($product, 'name');
        $description = $this->description($this->content->get($product, 'meta_description')
            ?: $this->content->get($product, 'short_description')
            ?: $this->content->get($product, 'description'));
        $images = collect([$product->image_url, ...$product->images()->limit(4)->pluck('image_url')])
            ->map(fn ($path) => MediaUrl::resolve($path))->filter()->unique()->values()->all();

        $category = $product->category;
        $breadcrumbs = $category && $category->is_active && ! $category->is_draft && Route::has('client.categories.show')
            ? [...$this->ancestorTrail($category, 'client.categories.show', 'name', fn (Category $parent) => $parent->is_active && ! $parent->is_draft),
                ['name' => (string) $this->content->get($category, 'name'), 'url' => route('client.categories.show', ['locale' => app()->getLocale(), 'slug' => $category->canonicalSlug()])]]
            : [];

        $url = Route::has($routeName) ? route($routeName, ['locale' => app()->getLocale(), 'slug' => $product->canonicalSlug()]) : url()->current();
        $schema = StructuredData::product([
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'images' => $images,
            'sku' => $product->sku,
            'brand' => $product->brand ? (string) $this->content->get($product->brand, 'name') : null,
        ], $this->offers($product), $this->rating($product));

        // A theme route under another name: the page still gets its tags, at the URL it was opened on.
        if (! Route::has($routeName)) {
            return $this->fallback($this->content->get($product, 'meta_title') ?: $name, $description, [$schema], $images[0] ?? null, 'product');
        }

        return $this->forRecord($product, $routeName, $this->content->get($product, 'meta_title') ?: $name, $description,
            image: $images[0] ?? null, type: 'product', breadcrumbs: $breadcrumbs, structuredData: [$schema]);
    }

    /**
     * A blog post page, for a theme's `client.posts.show` route: the post's own SEO fields,
     * robots and canonical settings, breadcrumbs through its category and Article JSON-LD.
     */
    public function forPost(Post $post, string $routeName = 'client.posts.show'): SeoMeta
    {
        $title = (string) ($this->content->get($post, 'seo_title') ?: $this->content->get($post, 'title'));
        $description = $this->description($this->content->get($post, 'seo_description')
            ?: $this->content->get($post, 'summary')
            ?: $this->content->get($post, 'content'));
        $image = MediaUrl::resolve($post->image_url);
        $url = Route::has($routeName) ? route($routeName, ['locale' => app()->getLocale(), 'slug' => $post->canonicalSlug()]) : url()->current();
        $category = $post->category;
        $breadcrumbs = $category && $category->is_active && Route::has('client.post-categories.show')
            ? [['name' => (string) $this->content->get($category, 'name'), 'url' => route('client.post-categories.show', ['locale' => app()->getLocale(), 'slug' => $category->canonicalSlug()])]]
            : [];

        $schema = StructuredData::article([
            'headline' => (string) $this->content->get($post, 'title'),
            'url' => $url,
            'description' => $description,
            'image' => $image,
            'published' => ($post->published_at ?? $post->created_at)?->toAtomString(),
            'modified' => $post->updated_at?->toAtomString(),
        ], $this->branding->current());

        if (! Route::has($routeName)) {
            return $this->fallback($title, $description, [$schema], $image, 'article');
        }

        return $this->forRecord($post, $routeName, $title, $description,
            image: $image, type: 'article', breadcrumbs: $breadcrumbs,
            index: $post->robots_index ?? true, follow: $post->robots_follow ?? true,
            canonical: $post->canonical_url, structuredData: [$schema]);
    }

    /**
     * What a buyer can actually order, by the checkout's rules: a variant without its own price
     * sells at the product's; stock counts only when the product manages it, per variant when
     * the product has option groups.
     *
     * @return list<array{price: float, in_stock: bool}>
     */
    private function offers(Product $product): array
    {
        $variants = $product->relationLoaded('variants') ? $product->variants : $product->variants()->where('is_active', true)->get();
        $byVariant = $variants->isNotEmpty() && $product->usesVariantInventory();

        if (! $byVariant) {
            return [['price' => (float) $product->price, 'in_stock' => ! $product->manage_stock || $product->stock_quantity > 0]];
        }

        return $variants->where('is_active', true)->map(fn ($variant) => [
            'price' => $variant->price !== null ? (float) $variant->price : (float) $product->price,
            'in_stock' => ! $product->manage_stock || $variant->stock_quantity > 0,
        ])->values()->all();
    }

    /** @return array{average: float, count: int}|null visible reviews, only when reviews are switched on */
    private function rating(Product $product): ?array
    {
        if (! app(\App\Support\FeatureGate::class)->enabled('review')) {
            return null;
        }

        $reviews = $product->relationLoaded('reviews')
            ? $product->reviews->where('is_visible', true)
            : $product->reviews()->where('is_visible', true)->get(['rating']);

        return $reviews->isEmpty() ? null : ['average' => (float) $reviews->avg('rating'), 'count' => $reviews->count()];
    }

    /**
     * Breadcrumbs for the parents of a nested record (category → subcategory),
     * root first. Stops at the first parent a visitor could not open, since a
     * crumb must link to a page that exists.
     *
     * @param  \Closure(Model): bool  $visible
     * @return list<array{name: string, url: string}>
     */
    public function ancestorTrail(Model $model, string $routeName, string $nameAttribute, \Closure $visible): array
    {
        $locale = app()->getLocale();
        $trail = [];
        $seen = [$model->getKey() => true];
        $parent = $model->parent;

        // Bounded: parent_id loops are blocked by the admin form, not by the schema.
        while ($parent && ! isset($seen[$parent->getKey()]) && count($trail) < 10 && $visible($parent)) {
            $seen[$parent->getKey()] = true;
            array_unshift($trail, [
                'name' => (string) $this->content->get($parent, $nameAttribute),
                'url' => route($routeName, ['locale' => $locale, 'slug' => $parent->canonicalSlug($locale)]),
            ]);
            $parent = $parent->parent;
        }

        return $trail;
    }

    /** Metadata for a page that has no record behind it (a theme route, an error page). */
    public function fallback(string $title, ?string $description = null, array $structuredData = [], ?string $image = null, string $type = 'website'): SeoMeta
    {
        return new SeoMeta(
            title: $title,
            description: $this->description($description),
            canonical: $this->withPage(url()->current()),
            index: $this->indexable(),
            type: $type,
            image: $image ?: ($this->branding->current()['logo_url'] ?? null),
            structuredData: $structuredData,
        );
    }

    /**
     * Only production is meant to be found. Staging, local and test copies of a
     * shop carry its real content, and an indexed copy competes with the shop.
     */
    public function indexable(): bool
    {
        return app()->isProduction();
    }

    public function description(?string $text): ?string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5)));

        return $plain === '' ? null : Str::limit($plain, self::DESCRIPTION_LIMIT, '…');
    }

    /** Page 2+ of a listing is its own URL; pointing it at page 1 hides those items. */
    private function withPage(string $url): string
    {
        $page = (int) request()->query('page', 1);

        return $page > 1 ? $url.'?page='.$page : $url;
    }
}
