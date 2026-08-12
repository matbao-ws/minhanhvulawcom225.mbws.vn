<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageRevision;
use App\Support\PageHtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageContentService
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

    /**
     * Persist a single locale's content from the client inline editor.
     * No-op (and no revision) when the sanitized HTML is unchanged.
     */
    public function updateLocale(Page $page, string $locale, string $html, ?int $userId): Page
    {
        if (! $this->languages->supports($locale)) {
            throw ValidationException::withMessages(['content_locale' => 'Ngôn ngữ nội dung không hợp lệ.']);
        }

        $clean = $this->htmlSanitizer->clean($html);
        $current = $page->getTranslation('published_html', $locale, false);
        if ($clean === $current) {
            return $page;
        }

        return DB::transaction(function () use ($page, $locale, $clean, $userId): Page {
            $this->snapshotIfStale($page, $userId);

            $translations = $page->getTranslations('published_html');
            $translations[$locale] = $clean;
            $page->update(['published_html' => $translations]);

            return $page->refresh();
        });
    }

    public function restore(Page $page, PageRevision $revision, ?int $userId): Page
    {
        abort_unless($revision->page_id === $page->id, 404);

        return DB::transaction(function () use ($page, $revision, $userId): Page {
            $this->snapshot($page, $userId);
            $page->update([
                'published_html' => $revision->published_html,
            ]);

            return $page->refresh();
        });
    }

    /**
     * `published_html` is only present in $data for programmatic callers
     * (e.g. seeders) — the admin metadata form no longer submits it, since
     * content is authored via the client inline editor instead. Omitting the
     * key here (rather than defaulting to empty) means an admin metadata save
     * never wipes out content the inline editor has already saved.
     */
    private function payload(array $data, ?Page $page = null): array
    {
        $titles = $this->localizedStrings($data['title']);
        $slugs = $this->localizedStrings($data['slug'] ?? []);
        $baseSlug = $slugs[$this->languages->defaultLocale()]
            ?? Str::slug($titles[$this->languages->defaultLocale()]);
        $active = (bool) $data['is_active'];

        $payload = [
            'title' => $titles,
            'slug' => $this->uniqueLegacySlug($baseSlug, $page?->id),
            'meta_title' => $this->localizedStrings($data['meta_title'] ?? []),
            'meta_description' => $this->localizedStrings($data['meta_description'] ?? []),
            'is_active' => $active,
            'published_at' => $active ? ($page?->published_at ?: now()) : null,
        ];

        if (array_key_exists('published_html', $data)) {
            $payload['published_html'] = $this->cleanHtmlByLocale($data['published_html']);
        }

        return $payload;
    }

    private function cleanHtmlByLocale(array $values): array
    {
        return collect($values)
            ->filter(fn ($value, $locale) => $this->languages->supports((string) $locale) && is_string($value))
            ->map(fn (string $value) => $this->htmlSanitizer->clean($value))
            ->all();
    }

    /** Always snapshots — used by the low-frequency admin metadata form and restore. */
    private function snapshot(Page $page, ?int $userId): PageRevision
    {
        return $page->revisions()->create([
            'created_by' => $userId,
            'published_html' => $page->getTranslations('published_html'),
        ]);
    }

    /**
     * Coalesces snapshots for the high-frequency inline auto-save path:
     * skips creating a new revision if one already exists within the window,
     * so a long editing session only leaves one "before this session" checkpoint.
     */
    private function snapshotIfStale(Page $page, ?int $userId): void
    {
        $recentExists = $page->revisions()
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($recentExists) {
            return;
        }

        $page->revisions()->create([
            'created_by' => $userId,
            'published_html' => $page->getTranslations('published_html'),
        ]);
    }

    private function localizedStrings(array $values): array
    {
        return collect($values)
            ->filter(fn ($value, $locale) => $this->languages->supports((string) $locale) && is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->all();
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
