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

    /**
     * The saved checkpoints for one page, shaped for a history list.
     *
     * Same shape as {@see SiteContentService::revisions()} so the storefront
     * history panel can render a page the way it renders a region: id, when,
     * who, whether this locale is in the snapshot at all, and a short plain
     * text label. A label, not a diff.
     *
     * @return array<int, array<string, mixed>>
     */
    public function revisions(Page $page, ?string $locale = null, int $limit = 20): array
    {
        $locale ??= app()->getLocale();

        return $page->revisions()
            ->with('creator')
            ->limit($limit)
            ->get()
            ->map(function (PageRevision $revision) use ($locale): array {
                $content = is_array($revision->published_html) ? $revision->published_html : [];
                $value = $content[$locale] ?? null;

                return [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at?->toIso8601String(),
                    'author' => $revision->creator?->name,
                    'has_locale' => array_key_exists($locale, $content),
                    'preview' => is_string($value) ? Str::limit(trim(strip_tags($value)), 90) : null,
                ];
            })
            ->all();
    }

    /**
     * Put one snapshot back.
     *
     * `$locale` is what separates the two callers, and the difference matters.
     * The admin metadata screen owns the whole record, so it restores every
     * locale at once — that is what it has always done. The storefront editor
     * is looking at exactly one language, and restoring the others from there
     * would silently revert translations nobody on that screen can see. So it
     * passes the locale it is showing, and only that key is written.
     *
     * A locale-scoped restore whose snapshot never held that locale is
     * refused rather than satisfied with an empty string: an empty string is a
     * real stored value meaning "hidden on purpose", a different instruction
     * from "this version had nothing to say here".
     *
     * The page always comes from the revision, never from the request.
     * Accepting both would let a crafted call write page A's snapshot onto
     * page B with nothing in the audit trail to show for it.
     *
     * @throws ValidationException
     */
    public function restore(Page $page, PageRevision $revision, ?int $userId, ?string $locale = null): Page
    {
        abort_unless($revision->page_id === $page->id, 404);

        if ($locale === null) {
            $value = $revision->published_html;
        } else {
            $snapshot = is_array($revision->published_html) ? $revision->published_html : [];

            if (! array_key_exists($locale, $snapshot)) {
                throw ValidationException::withMessages([
                    'revision' => 'Phiên bản này không có nội dung cho ngôn ngữ đang xem.',
                ]);
            }

            $value = array_merge($page->getTranslations('published_html'), [$locale => $snapshot[$locale]]);
        }

        return DB::transaction(function () use ($page, $value, $userId): Page {
            // Forced, not coalesced. Ten-minute checkpoints are right for
            // typing; skipping one here would make a rollback the only action
            // in the editor that cannot itself be undone.
            $this->snapshot($page, $userId);
            $page->update(['published_html' => $value]);

            return $page->refresh();
        });
    }

    /**
     * The admin form submits `published_html` only for the locales whose
     * editor was actually touched, so submitted locales are merged over the
     * stored ones. A save that leaves the editor alone never rewrites content
     * the storefront inline editor saved — Quill would flatten its markup.
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
            $payload['published_html'] = array_merge(
                $page?->getTranslations('published_html') ?? [],
                $this->cleanHtmlByLocale($data['published_html'] ?? []),
            );
        }

        return $payload;
    }

    private function cleanHtmlByLocale(array $values): array
    {
        return collect($values)
            // null is an emptied editor after ConvertEmptyStringsToNull: a real
            // "no content" the admin chose, stored as '' like the inline editor does.
            ->filter(fn ($value, $locale) => $this->languages->supports((string) $locale) && (is_string($value) || $value === null))
            ->map(fn (?string $value) => $value === null ? '' : $this->htmlSanitizer->clean($value))
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
