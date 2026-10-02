<?php

namespace App\Services\AiGateway;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Services\LanguageRegistry;
use App\Support\FeatureGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sends the gateway a plain description of every public record, one row per
 * locale, so it can match Search Console URLs to records by slug and spot the
 * pages worth working on. Only public content and counts leave the site.
 *
 * Rows go in chunks under one generation; the gateway swaps in the new index
 * only when the final chunk arrives, so a run cut short leaves the old one.
 */
class SiteIndexPublisher
{
    public const CHUNK = 500;

    /**
     * entity type => model, its fields, and which records are public.
     *
     * @var array<string, array{model: class-string<Model>, title: string, content: string, meta_title: string|null, meta_description: string|null}>
     */
    private const ENTITIES = [
        'post' => ['model' => Post::class, 'title' => 'title', 'content' => 'content', 'meta_title' => 'seo_title', 'meta_description' => 'seo_description'],
        'product' => ['model' => Product::class, 'title' => 'name', 'content' => 'description', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
        'category' => ['model' => Category::class, 'title' => 'name', 'content' => 'description', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
        'page' => ['model' => Page::class, 'title' => 'title', 'content' => 'published_html', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
    ];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
    ) {}

    public function enabled(): bool
    {
        return $this->gateway->configured() && $this->features->enabled('ai_seo');
    }

    /**
     * @return int rows sent
     *
     * @throws GatewayUnavailable
     */
    public function publish(): int
    {
        $generation = 'g'.now()->format('YmdHis').Str::lower(Str::random(6));
        $locales = $this->languages->codes();
        $buffer = [];
        $sent = 0;

        foreach (self::ENTITIES as $type => $definition) {
            $this->publicRecords($type, $definition['model'])->chunkById(200, function ($records) use ($type, $definition, $locales, $generation, &$buffer, &$sent) {
                foreach ($records as $record) {
                    foreach ($locales as $locale) {
                        $buffer[] = $this->row($type, $definition, $record, $locale);
                    }
                }

                while (count($buffer) >= self::CHUNK) {
                    $this->send($generation, array_splice($buffer, 0, self::CHUNK), false);
                    $sent += self::CHUNK;
                }
            });
        }

        // The final chunk may be empty: it still tells the gateway the run is complete.
        $this->send($generation, $buffer, true);

        return $sent + count($buffer);
    }

    /** @return Builder<Model> */
    private function publicRecords(string $type, string $model): Builder
    {
        $query = $model::query()->with('localizedSlugs')->where('is_active', true);

        // The same rules the storefront applies before showing a record.
        return match ($type) {
            'category' => $query->where('is_draft', false),
            'page' => $query->whereNotNull('published_at')->where('published_at', '<=', now()),
            default => $query,
        };
    }

    /** @return array<string, mixed> */
    private function row(string $type, array $definition, Model $record, string $locale): array
    {
        $html = $this->translation($record, $definition['content'], $locale);
        $text = $this->plain($html);

        return [
            'entity_type' => $type,
            'entity_id' => (int) $record->getKey(),
            'locale' => $locale,
            'slug' => $slug = Str::limit($record->canonicalSlug($locale), 255, ''),
            'url' => $this->url($type, $locale, $slug),
            'title' => Str::limit($this->plain($this->translation($record, $definition['title'], $locale)) ?: '#'.$record->getKey(), 300, ''),
            'meta_title' => $this->optional($record, $definition['meta_title'], $locale, 300),
            'meta_description' => $this->optional($record, $definition['meta_description'], $locale, 1000),
            'focus_keyword' => $record instanceof Post && filled($record->seo_keys) ? Str::limit((string) $record->seo_keys, 120, '') : null,
            'word_count' => $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []),
            'h2_count' => min(65535, preg_match_all('/<h2\b/i', $html)),
            'images_without_alt' => min(65535, $this->imagesWithoutAlt($html)),
            'internal_links' => min(65535, $this->internalLinks($html)),
            'indexable' => ! ($record instanceof Post) || $record->robots_index !== false,
            'site_updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    /** URLs of the storefront routes this core has; products and posts belong to the theme, so null. */
    private function url(string $type, string $locale, string $slug): ?string
    {
        $route = ['category' => 'client.categories.show', 'page' => 'client.pages.show'][$type] ?? null;

        return $route && $slug !== '' && preg_match('/^[A-Za-z0-9\-_]+$/', $slug) ? route($route, ['locale' => $locale, 'slug' => $slug]) : null;
    }

    private function send(string $generation, array $pages, bool $final): void
    {
        $this->gateway->post('v1/site-index', [], ['generation' => $generation, 'final' => $final, 'pages' => $pages]);
    }

    private function translation(Model $record, string $field, string $locale): string
    {
        return (string) ($record->getTranslation($field, $locale, false)
            ?: $record->getTranslation($field, $this->languages->defaultLocale(), false));
    }

    private function optional(Model $record, ?string $field, string $locale, int $max): ?string
    {
        $value = $field ? $this->plain((string) $record->getTranslation($field, $locale, false)) : '';

        return $value !== '' ? Str::limit($value, $max, '') : null;
    }

    private function plain(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    private function imagesWithoutAlt(string $html): int
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $images);

        return count(array_filter($images[0], fn (string $tag) => ! preg_match('/\salt\s*=\s*("[^"]*\S[^"]*"|\'[^\']*\S[^\']*\')/i', $tag)));
    }

    /** Links to this website: relative, or absolute to the site's own host. */
    private function internalLinks(string $html): int
    {
        preg_match_all('/<a\b[^>]*\shref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $html, $matches);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return count(array_filter(array_map(fn ($d, $s) => html_entity_decode($d !== '' ? $d : $s), $matches[2], $matches[3]), function (string $href) use ($host) {
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                return false;
            }
            $linkHost = parse_url($href, PHP_URL_HOST);

            return $linkHost === null || ($host && strcasecmp($linkHost, $host) === 0);
        }));
    }
}
