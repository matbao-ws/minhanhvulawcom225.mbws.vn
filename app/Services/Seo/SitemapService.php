<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\Catalog\ProductQueryService;
use App\Services\LanguageRegistry;
use App\Support\FeatureGate;
use App\Support\Seo\RecordLocales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * URLs a search engine may crawl, one entry per record and locale.
 *
 * Only records with a storefront route are listed. The core ships routes for
 * policy pages, product categories and post categories; product and post pages
 * exist once a theme is cut, so they join the sitemap as soon as the theme
 * registers `client.products.show` / `client.posts.show` — a sitemap must never
 * point at a 404.
 *
 * A theme's own pages (home, about, contact… in routes/theme.php) are listed too: every
 * GET route named `client.*` whose only parameter is {locale}, except redirects and
 * private pages (cart, checkout, account, search…). A theme leaves one out with
 * `->defaults('sitemap', false)`.
 */
class SitemapService
{
    public const CACHE_KEY = 'seo.sitemap.v2';

    /** Route names (after `client.`) that are never worth crawling. */
    private const PRIVATE_PAGES = '/(^|[.\-_])(cart|checkout|account|login|register|password|logout|search|orders?|thank|track|wishlist|compare|profile|payment|gio-hang|thanh-toan|tai-khoan|dang-nhap|dang-ky|tim-kiem|don-hang)([.\-_]|$)/i';

    /** The protocol's ceiling for one sitemap file. */
    public const MAX_URLS = 50000;

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly FeatureGate $features,
        private readonly ProductQueryService $products,
    ) {}

    /**
     * @return list<array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public function entries(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => $this->build());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function build(): array
    {
        $sources = [];

        if ($this->features->enabled('cms_page')) {
            $sources[] = ['client.pages.show', Page::query()
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())];
            $sources[] = ['client.post-categories.show', PostCategory::query()->where('is_active', true)];
            $sources[] = ['client.posts.show', Post::query()
                ->where('is_active', true)
                ->where('robots_index', true)
                ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))];
        }

        if ($this->features->enabled('catalog')) {
            $sources[] = ['client.categories.show', Category::query()->where('is_active', true)->where('is_draft', false)];
            // The same visibility rules the storefront listing applies.
            $sources[] = ['client.products.show', $this->products->listing()];
        }

        $codes = $this->languages->codes();
        $entries = $this->themePages($codes);

        foreach ($sources as [$routeName, $query]) {
            if (! Route::has($routeName)) {
                continue;
            }

            foreach ($query->with('localizedSlugs')->orderBy('id')->lazy(500) as $model) {
                foreach ($this->entriesFor($model, $routeName, $codes) as $entry) {
                    $entries[] = $entry;

                    if (count($entries) >= self::MAX_URLS) {
                        return $entries;
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * The theme's pages without a record behind them, home first. Written in Blade per
     * language, so they exist in every language of the site.
     *
     * @param  list<string>  $codes
     * @return list<array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    private function themePages(array $codes): array
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'client.')
                || ! in_array('GET', $route->methods(), true)
                || $route->parameterNames() !== ['locale']
                || ($route->defaults['sitemap'] ?? true) === false
                || str_contains($route->getActionName(), 'RedirectController')
                || preg_match(self::PRIVATE_PAGES, substr($name, strlen('client.')))) {
                continue;
            }
            $names[$name] = true;
        }
        $names = array_keys($names);
        usort($names, fn (string $a, string $b) => [$a !== 'client.home', $a] <=> [$b !== 'client.home', $b]);

        $entries = [];
        foreach ($names as $name) {
            $urls = [];
            foreach ($codes as $code) {
                $urls[$code] = route($name, ['locale' => $code]);
            }
            $alternates = count($urls) > 1 ? $urls : [];
            foreach ($urls as $loc) {
                $entries[] = ['loc' => $loc, 'lastmod' => null, 'alternates' => $alternates];
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $codes
     * @return list<array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    private function entriesFor(Model $model, string $routeName, array $codes): array
    {
        $urls = [];

        // A language the record has no text in only shows the fallback's: not a page to list.
        foreach (RecordLocales::of($model, $codes, $this->languages->fallbackLocale()) as $code) {
            $urls[$code] = route($routeName, ['locale' => $code, 'slug' => $model->canonicalSlug($code)]);
        }

        $lastmod = $model->getAttribute('updated_at')?->toAtomString();
        $alternates = count($urls) > 1 ? $urls : [];

        return array_map(fn (string $loc) => [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'alternates' => $alternates,
        ], array_values($urls));
    }
}
