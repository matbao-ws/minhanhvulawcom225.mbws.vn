<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageRevision;
use App\Support\PageHtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageBuilderService
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly PageHtmlSanitizer $htmlSanitizer,
    ) {}

    public function create(array $data): Page
    {
        return DB::transaction(function () use ($data): Page {
            $page = Page::query()->create($this->payload($data));
            $this->localizedSlugs->sync($page, $data['slug'] ?? [], $data['title']);

            return $page;
        });
    }

    public function update(Page $page, array $data, ?int $userId): Page
    {
        return DB::transaction(function () use ($page, $data, $userId): Page {
            $this->snapshot($page, $userId);
            $page->update($this->payload($data, $page));
            $this->localizedSlugs->sync($page, $data['slug'] ?? [], $data['title']);

            return $page->refresh();
        });
    }

    public function updateLocale(Page $page, string $locale, array $data, ?int $userId): Page
    {
        if (! $this->languages->supports($locale)) {
            throw ValidationException::withMessages(['content_locale' => 'Ngôn ngữ nội dung không hợp lệ.']);
        }

        return DB::transaction(function () use ($page, $locale, $data, $userId): Page {
            $this->snapshot($page, $userId);

            $builderData = $page->builder_data ?: ['version' => 1, 'locales' => []];
            $builderData['version'] = max(1, (int) ($builderData['version'] ?? 1));
            $builderData['locales'][$locale] = $data['builder_data'];

            $html = $page->getTranslations('published_html');
            $html[$locale] = $this->htmlSanitizer->clean($data['published_html']);

            $css = $page->getTranslations('published_css');
            $css[$locale] = $this->cleanCss((string) ($data['published_css'] ?? ''));

            $page->update([
                'schema_version' => $builderData['version'],
                'builder_data' => $this->cleanBuilderData($builderData),
                'published_html' => $html,
                'published_css' => $css,
            ]);

            return $page->refresh();
        });
    }

    public function restore(Page $page, PageRevision $revision, ?int $userId): Page
    {
        abort_unless($revision->page_id === $page->id, 404);

        return DB::transaction(function () use ($page, $revision, $userId): Page {
            $this->snapshot($page, $userId);
            $page->update([
                'schema_version' => $revision->schema_version,
                'builder_data' => $revision->builder_data,
                'published_html' => $revision->published_html,
                'published_css' => $revision->published_css,
            ]);

            return $page->refresh();
        });
    }

    private function payload(array $data, ?Page $page = null): array
    {
        $titles = $this->localizedStrings($data['title']);
        $slugs = $this->localizedStrings($data['slug'] ?? []);
        $baseSlug = $slugs[$this->languages->defaultLocale()]
            ?? Str::slug($titles[$this->languages->defaultLocale()]);
        $active = (bool) $data['is_active'];

        return [
            'title' => $titles,
            'slug' => $this->uniqueLegacySlug($baseSlug, $page?->id),
            'schema_version' => (int) ($data['builder_data']['version'] ?? 1),
            'builder_data' => $this->cleanBuilderData($data['builder_data']),
            'published_html' => $this->cleanHtmlByLocale($data['published_html'] ?? []),
            'published_css' => $this->cleanCssByLocale($data['published_css'] ?? []),
            'meta_title' => $this->localizedStrings($data['meta_title'] ?? []),
            'meta_description' => $this->localizedStrings($data['meta_description'] ?? []),
            'is_active' => $active,
            'published_at' => $active ? ($page?->published_at ?: now()) : null,
        ];
    }

    private function snapshot(Page $page, ?int $userId): PageRevision
    {
        return $page->revisions()->create([
            'created_by' => $userId,
            'schema_version' => $page->schema_version,
            'builder_data' => $page->builder_data,
            'published_html' => $page->getTranslations('published_html'),
            'published_css' => $page->getTranslations('published_css'),
        ]);
    }

    private function localizedStrings(array $values): array
    {
        return collect($values)
            ->filter(fn ($value, $locale) => $this->languages->supports((string) $locale) && is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->all();
    }

    private function cleanHtmlByLocale(array $values): array
    {
        return collect($values)
            ->filter(fn ($value, $locale) => $this->languages->supports((string) $locale) && is_string($value))
            ->map(fn (string $value) => $this->htmlSanitizer->clean($value))
            ->all();
    }

    private function cleanCssByLocale(array $values): array
    {
        return collect($values)
            ->filter(fn ($value, $locale) => $this->languages->supports((string) $locale) && is_string($value))
            ->map(fn (string $value) => $this->cleanCss($value))
            ->all();
    }

    private function cleanCss(string $css): string
    {
        if (preg_match('/@import|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|<\s*\/?\s*(script|style)/i', $css)) {
            throw ValidationException::withMessages(['published_css' => 'CSS chứa nội dung không được phép.']);
        }

        return mb_substr(trim($css), 0, 500000);
    }

    private function cleanBuilderData(array $data): array
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > 2000000) {
            throw ValidationException::withMessages(['builder_data' => 'Dữ liệu giao diện vượt quá giới hạn cho phép.']);
        }
        if (preg_match('/"tagName"\s*:\s*"(script|object|embed)"/i', $encoded)) {
            throw ValidationException::withMessages(['builder_data' => 'Giao diện chứa component không được phép.']);
        }

        return $data;
    }

    private function uniqueLegacySlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $counter = 2;
        while (Page::query()->withTrashed()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
