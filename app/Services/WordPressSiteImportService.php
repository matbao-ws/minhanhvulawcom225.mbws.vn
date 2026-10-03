<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Copies a live WordPress site's public content through its REST API — posts, pages and
 * WooCommerce products — keeping each slug, so Search Console URLs of that site match the
 * copied records here. Read-only on the WordPress side: public endpoints, no login, no plugin.
 *
 * Meant for staging and local test sites: it gives the AI SEO tools real content to work on.
 * Posts go through WordPressPostImportService (the WXR importer's storage path), pages through
 * PageContentService, products straight into the catalogue; images are downloaded into this
 * site's library like the WXR import does.
 */
class WordPressSiteImportService
{
    public const TYPES = ['posts', 'pages', 'products'];

    private const PER_PAGE = 50;

    private string $base = '';

    /** Use ?rest_route= when the site has no pretty permalinks. */
    private bool $plainRoutes = false;

    private string $userAgent = 'MatBaoWS-Importer/1.0';

    public function __construct(
        private readonly WordPressPostImportService $posts,
        private readonly PageContentService $pages,
        private readonly WordPressImageImportService $images,
        private readonly WordPressContentNormalizer $normalizer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly LanguageRegistry $languages,
    ) {}

    /**
     * @param  list<string>  $types
     * @param  callable(string $type, string $title, string $result): void|null  $progress
     * @return array<string, array{imported: int, updated: int, skipped: int, failed: int}|mixed>
     *
     * @throws RuntimeException when the site has no reachable REST API
     */
    public function import(string $url, array $types, int $limit, string $duplicateAction = 'skip', ?callable $progress = null, ?string $userAgent = null): array
    {
        if ($userAgent !== null && trim($userAgent) !== '') {
            $this->userAgent = trim($userAgent);
            $this->images->useUserAgent($this->userAgent);
        }
        $this->base = rtrim($url, '/');
        if (! preg_match('#^https?://[^/\s]+#i', $this->base)) {
            throw new RuntimeException('URL WordPress không hợp lệ.');
        }
        $this->detectApi();
        $this->images->reset();

        $report = ['errors' => []];
        foreach (array_intersect(self::TYPES, $types) as $type) {
            $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
            $route = ['posts' => '/wp/v2/posts', 'pages' => '/wp/v2/pages', 'products' => '/wc/store/v1/products'][$type];
            $query = $type === 'products' ? [] : ['_embed' => 1, 'status' => 'publish'];

            foreach ($this->each($route, $query, $limit, $type === 'products') as $item) {
                $title = (string) ($item['title']['rendered'] ?? $item['name'] ?? '#'.($item['id'] ?? ''));
                try {
                    $result = match ($type) {
                        'posts' => $this->posts->importRestPost($item, $duplicateAction),
                        'pages' => $this->importPage($item, $duplicateAction),
                        'products' => $this->importProduct($item, $duplicateAction),
                    };
                } catch (Throwable $e) {
                    $result = 'failed';
                    $report['errors'][] = "$type ".html_entity_decode(strip_tags($title)).': '.$e->getMessage();
                }
                $counts[$result]++;
                if ($progress) {
                    $progress($type, html_entity_decode(strip_tags($title)), $result);
                }
            }
            $report[$type] = $counts;
        }

        $report['images'] = $this->images->report();

        return $report;
    }

    private function detectApi(): void
    {
        foreach ([false, true] as $plain) {
            $this->plainRoutes = $plain;
            $response = $this->request('/');
            if ($response?->successful() && is_array($response->json('routes'))) {
                return;
            }
        }

        throw new RuntimeException('Không đọc được REST API của site này (đã tắt, hoặc tường lửa chặn client lạ — nếu chủ site đồng ý, thử --user-agent).');
    }

    /**
     * Every item of a collection route, page by page, up to $limit.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function each(string $route, array $query, int $limit, bool $optional): \Generator
    {
        $seen = 0;
        for ($page = 1; $seen < $limit; $page++) {
            $response = $this->request($route, $query + ['per_page' => self::PER_PAGE, 'page' => $page]);
            if (! $response?->successful()) {
                // WooCommerce absent, or past the last page (WordPress answers 400 there).
                if ($page === 1 && ! $optional) {
                    throw new RuntimeException("Không đọc được $route (HTTP ".($response?->status() ?? 'lỗi mạng').').');
                }

                return;
            }
            $items = $response->json();
            if (! is_array($items) || $items === []) {
                return;
            }
            foreach ($items as $item) {
                if (is_array($item) && $seen < $limit) {
                    $seen++;
                    yield $item;
                }
            }
            if ($page >= (int) $response->header('X-WP-TotalPages')) {
                return;
            }
        }
    }

    private function request(string $route, array $query = [])
    {
        $url = $this->plainRoutes
            ? $this->base.'/?'.http_build_query(['rest_route' => $route] + $query)
            : $this->base.'/wp-json'.$route.($query === [] ? '' : '?'.http_build_query($query));

        return rescue(fn () => Http::connectTimeout(8)->timeout(30)->acceptJson()
            ->withHeaders(['User-Agent' => $this->userAgent])->get($url), null, false);
    }

    /** @param array<string, mixed> $item */
    private function importPage(array $item, string $duplicateAction): string
    {
        $locale = $this->languages->defaultLocale();
        $title = trim(html_entity_decode(strip_tags((string) ($item['title']['rendered'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === '' || ! empty($item['content']['protected'])) {
            return 'skipped';
        }
        $slug = Str::slug(rawurldecode((string) ($item['slug'] ?? ''))) ?: Str::slug($title);
        $existing = Page::query()->where('slug', $slug)->first();
        if ($existing && $duplicateAction === 'skip') {
            return 'skipped';
        }

        $yoast = (array) ($item['yoast_head_json'] ?? []);
        $data = [
            'title' => [$locale => Str::limit($title, 255, '')],
            'slug' => [$locale => $slug],
            'meta_title' => array_filter([$locale => Str::limit(trim((string) ($yoast['title'] ?? '')), 255, '')]),
            'meta_description' => array_filter([$locale => Str::limit(trim((string) ($yoast['description'] ?? '')), 500, '')]),
            'is_active' => true,
            'published_html' => [$locale => $this->images->localizeHtml($this->normalizer->normalize((string) ($item['content']['rendered'] ?? '')))],
        ];

        if ($existing) {
            $this->pages->update($existing, $data, null);

            return 'updated';
        }
        $this->pages->create($data);

        return 'imported';
    }

    /** @param array<string, mixed> $item WooCommerce Store API product */
    private function importProduct(array $item, string $duplicateAction): string
    {
        $locale = $this->languages->defaultLocale();
        $name = trim(html_entity_decode(strip_tags((string) ($item['name'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($name === '') {
            return 'skipped';
        }
        $slug = Str::slug(rawurldecode((string) ($item['slug'] ?? ''))) ?: Str::slug($name);

        return DB::transaction(function () use ($item, $locale, $name, $slug, $duplicateAction): string {
            $existing = Product::query()->where('slug', $slug)->lockForUpdate()->first();
            if ($existing && $duplicateAction === 'skip') {
                return 'skipped';
            }

            $prices = (array) ($item['prices'] ?? []);
            $unit = 10 ** max(0, (int) ($prices['currency_minor_unit'] ?? 0));
            $price = (float) ($prices['price'] ?? 0) / $unit;
            $regular = (float) ($prices['regular_price'] ?? 0) / $unit;
            $sku = trim((string) ($item['sku'] ?? ''));
            $category = $this->category((array) (($item['categories'] ?? [])[0] ?? []), $locale);
            $clean = fn (string $html) => $this->htmlSanitizer->clean($this->images->localizeHtml($this->normalizer->normalize($html)));

            $payload = [
                'category_id' => $category?->id,
                'name' => [$locale => Str::limit($name, 255, '')],
                'slug' => $slug,
                // SKUs are unique here; a duplicate or empty one is left blank rather than failing.
                'sku' => $sku !== '' && ! Product::query()->where('sku', $sku)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists() ? Str::limit($sku, 100, '') : null,
                'short_description' => array_filter([$locale => $clean((string) ($item['short_description'] ?? ''))]),
                'description' => array_filter([$locale => $clean((string) ($item['description'] ?? ''))]),
                'image_url' => $this->images->localize((string) ($item['images'][0]['src'] ?? '')),
                'price' => $price,
                'compare_at_price' => $regular > $price ? $regular : null,
                'manage_stock' => false,
                'is_active' => true,
                'published_at' => $existing?->published_at ?? now(),
            ];

            $product = $existing ? tap($existing)->update($payload) : Product::query()->create($payload);
            $this->localizedSlugs->sync($product, [$locale => $slug], [$locale => $name]);

            return $existing ? 'updated' : 'imported';
        });
    }

    /** @param array<string, mixed> $data {name, slug} */
    private function category(array $data, string $locale): ?Category
    {
        $name = trim(html_entity_decode((string) ($data['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($name === '') {
            return null;
        }
        $slug = Str::slug(rawurldecode((string) ($data['slug'] ?? ''))) ?: Str::slug($name);
        $category = Category::query()->where('slug', $slug)->first();
        if ($category) {
            return $category;
        }

        $category = Category::query()->create([
            'name' => [$locale => Str::limit($name, 255, '')],
            'slug' => $slug,
            'description' => [],
            'is_active' => true,
            'is_draft' => false,
            'sort_order' => 0,
        ]);
        $this->localizedSlugs->sync($category, [$locale => $slug], [$locale => $name]);

        return $category;
    }
}
