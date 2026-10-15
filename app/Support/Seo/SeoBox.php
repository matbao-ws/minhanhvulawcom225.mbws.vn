<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Services\LanguageRegistry;
use App\Services\PostSeoAnalyzer;
use Illuminate\Database\Eloquent\Model;

/**
 * The server side of the admin SEO box (admin.shared.seo-box) for every record that has one:
 * which of the record's fields play title, body, SEO title and description, the rules and
 * storage of the box's own fields, and the score PostSeoAnalyzer gives the record.
 */
final class SeoBox
{
    /** Per model: its title, body, SEO title and SEO description fields, and its storefront route. */
    public const MODELS = [
        Post::class => ['title' => 'title', 'content' => 'content', 'seo_title' => 'seo_title', 'seo_description' => 'seo_description', 'route' => 'client.posts.show'],
        Product::class => ['title' => 'name', 'content' => 'description', 'seo_title' => 'meta_title', 'seo_description' => 'meta_description', 'route' => 'client.products.show'],
        Page::class => ['title' => 'title', 'content' => 'published_html', 'seo_title' => 'meta_title', 'seo_description' => 'meta_description', 'route' => 'client.pages.show'],
        Category::class => ['title' => 'name', 'content' => 'description', 'seo_title' => 'meta_title', 'seo_description' => 'meta_description', 'route' => 'client.categories.show'],
        PostCategory::class => ['title' => 'name', 'content' => 'description', 'seo_title' => 'meta_title', 'seo_description' => 'meta_description', 'route' => 'client.post-categories.show'],
    ];

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $analyzer,
    ) {}

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = [
            'seo_keys' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'robots_index' => ['nullable', 'boolean'],
            'robots_follow' => ['nullable', 'boolean'],
            'og_title' => ['nullable', 'array'],
            'og_description' => ['nullable', 'array'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'schema_faq' => ['nullable', 'boolean'],
        ];
        foreach ($this->languages->codes() as $code) {
            $rules["og_title.$code"] = ['nullable', 'string', 'max:255'];
            $rules["og_description.$code"] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    /**
     * The box's fields from validated input. Only what was sent: an import or API call that does
     * not know these fields leaves them as they are. A share title or description sent empty is
     * removed, so that language follows the SEO title again.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function payload(array $data, ?Model $record = null): array
    {
        $payload = [];
        foreach (['seo_keys', 'canonical_url', 'og_image'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : null;
                $payload[$field] = $value === '' ? null : $value;
            }
        }
        foreach (['robots_index', 'robots_follow', 'schema_faq'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }
        foreach (['og_title', 'og_description'] as $field) {
            if (! array_key_exists($field, $data) || ! is_array($data[$field])) {
                continue;
            }
            $current = $record && method_exists($record, 'getTranslations') ? $record->getTranslations($field) : [];
            foreach ($data[$field] as $code => $value) {
                if (! $this->languages->supports((string) $code)) {
                    continue;
                }
                $value = is_string($value) ? trim($value) : '';
                if ($value === '') {
                    unset($current[$code]);
                } else {
                    $current[$code] = $value;
                }
            }
            $payload[$field] = $current;
        }

        return $payload;
    }

    /**
     * The record's SEO analysis in its default language, the same PostSeoAnalyzer checks the
     * box runs in the browser.
     *
     * @return array{score: int, status: string, rules: array<string, bool>, metrics: array<string, int|float>}
     */
    public function analyze(Model $record): array
    {
        $map = self::MODELS[$record::class];
        $locale = $this->languages->defaultLocale();
        $text = fn (string $field): string => (string) (method_exists($record, 'getTranslation') ? $record->getTranslation($field, $locale, false) : '');

        return $this->analyzer->analyze([
            'title' => $text($map['title']),
            'slug' => (string) $record->getAttribute('slug'),
            'content' => $text($map['content']),
            'seo_title' => $text($map['seo_title']),
            'seo_description' => $text($map['seo_description']),
            'focus_keyword' => (string) $record->getAttribute('seo_keys'),
        ]);
    }

    /** Stores the record's current score (shown in its list and the box) without touching updated_at. */
    public function refreshScore(Model $record): void
    {
        $record->forceFill(['seo_score' => $this->analyze($record)['score']])->saveQuietly();
    }
}
