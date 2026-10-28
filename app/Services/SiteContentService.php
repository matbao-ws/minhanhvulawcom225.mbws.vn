<?php

namespace App\Services;

use App\Models\SiteBlock;
use App\Models\SiteBlockRevision;
use App\Support\HtmlSanitizer;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reads and writes overrides for static storefront regions.
 *
 * Registered scoped: a page renders dozens of regions and must not issue a
 * query per region.
 */
class SiteContentService
{
    public const MAX_REVISIONS_PER_BLOCK = 50;

    /** @var array<string, SiteBlock>|null */
    private ?array $blocks = null;

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * The stored override, or null when the Blade default should win.
     *
     * Null covers both "never edited" and "deliberately emptied"; those mean
     * opposite things, so {@see isCleared()} tells them apart.
     *
     * `$fallback = false` returns only this locale's own edit, for themes whose template
     * text is already translated (config multilingual.inline_edit_fallback).
     */
    public function value(string $key, ?string $locale = null, bool $fallback = true): ?string
    {
        $locale ??= app()->getLocale();
        $block = $this->all()[$key] ?? null;

        if (! $block) {
            return null;
        }

        $translations = $block->rawTranslations();
        $stored = $translations[$locale]
            ?? ($fallback ? $translations[$this->languages->fallbackLocale()] ?? null : null);

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        return in_array($block->type, SiteBlock::PICTURE_TYPES, true) ? MediaUrl::resolve($stored) : $stored;
    }

    /**
     * True when an admin emptied this region on purpose, so it renders nothing.
     *
     * The signal is the presence of the locale key, not its contents.
     */
    public function isCleared(string $key, ?string $locale = null): bool
    {
        $locale ??= app()->getLocale();
        $block = $this->all()[$key] ?? null;

        if (! $block) {
            return false;
        }

        $translations = $block->rawTranslations();

        return array_key_exists($locale, $translations) && $translations[$locale] === '';
    }

    /**
     * The stored content type, or null when the region has never been saved.
     *
     * A region authored as plain text becomes HTML the moment the inline toolbar
     * formats it, and must keep rendering as HTML on later requests.
     */
    public function type(string $key): ?string
    {
        $type = $this->all()[$key]->type ?? null;

        return in_array($type, SiteBlock::TYPES, true) ? $type : null;
    }

    /**
     * The text alignment an editor chose, or null to keep the theme's.
     */
    public function align(string $key): ?string
    {
        $align = $this->all()[$key]->align ?? null;

        return in_array($align, SiteBlock::ALIGNS, true) ? $align : null;
    }

    /**
     * The semantic wrapper an editor chose, or null to keep the Blade tag.
     */
    public function format(string $key): ?string
    {
        $format = $this->all()[$key]->format ?? null;

        return in_array($format, SiteBlock::FORMATS, true) ? $format : null;
    }

    /**
     * Store an override for one locale.
     *
     * `$format` is nullable and defaults to null so the existing callers — the
     * admin block screens, which have no heading control — keep working unchanged.
     *
     * @throws ValidationException
     */
    public function updateLocale(string $key, string $type, string $locale, string $value, ?int $userId, ?string $format = null, string|false|null $align = false): SiteBlock
    {
        // false: the caller did not touch the alignment (the admin block screens); null: back to the theme's.
        if ($align !== false && $align !== null && ! in_array($align, SiteBlock::ALIGNS, true)) {
            throw ValidationException::withMessages(['align' => 'Căn lề không hợp lệ.']);
        }
        if (in_array($type, SiteBlock::PICTURE_TYPES, true)) {
            $align = false;
        }

        $this->assertSupported($type, $locale);

        if ($format !== null && ! in_array($format, SiteBlock::FORMATS, true)) {
            throw ValidationException::withMessages(['format' => 'Định dạng tiêu đề không hợp lệ.']);
        }

        // A heading wrapper only means something around rich text; forcing it on a
        // plain label would silently promote that label to HTML.
        if ($format !== null && $type !== SiteBlock::TYPE_HTML) {
            throw ValidationException::withMessages(['format' => 'Định dạng tiêu đề chỉ áp dụng cho nội dung HTML.']);
        }

        $format = $type === SiteBlock::TYPE_HTML ? $format : null;
        $clean = $this->clean($type, $value, $format);

        return DB::transaction(function () use ($key, $type, $locale, $clean, $userId, $format, $align): SiteBlock {
            $block = SiteBlock::query()->lockForUpdate()->firstOrNew(['key' => $key]);
            $translations = $block->exists ? $block->rawTranslations() : [];

            // Inline editors fire on every blur; an unchanged value must not
            // create a write or a revision. The format counts as part of "value":
            // switching a saved paragraph to H2 changes nothing else.
            if ($block->exists
                && array_key_exists($locale, $translations)
                && $translations[$locale] === $clean
                && $block->format === $format
                && ($align === false || $block->align === $align)
            ) {
                return $block;
            }

            $this->snapshot($block, $userId);

            $translations[$locale] = $clean;
            $block->key = $key;
            $block->type = $type;
            $block->format = $format;
            if ($align !== false) {
                $block->align = $align;
            }
            $block->setRawAttributes(array_merge($block->getAttributes(), [
                'content' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            ]));
            $block->save();

            $this->forget();

            return $block;
        });
    }

    /**
     * Drop the override for one locale so the template's own text returns.
     *
     * Clearing and restoring are different operations: clearing stores an empty
     * string, restoring removes the locale entirely and deletes the row once no
     * locale is left — the row *is* the override.
     */
    public function restoreLocale(string $key, string $locale, ?int $userId): ?SiteBlock
    {
        $this->assertSupported(SiteBlock::TYPE_TEXT, $locale);

        return DB::transaction(function () use ($key, $locale, $userId): ?SiteBlock {
            $block = SiteBlock::query()->lockForUpdate()->firstWhere('key', $key);

            if (! $block) {
                return null;
            }

            $translations = $block->rawTranslations();
            if (! array_key_exists($locale, $translations)) {
                return $block;
            }

            $this->snapshot($block, $userId);
            unset($translations[$locale]);

            if ($translations === []) {
                $block->delete();
                $this->forget();

                return null;
            }

            $block->setRawAttributes(array_merge($block->getAttributes(), [
                'content' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            ]));
            $block->save();

            $this->forget();

            return $block;
        });
    }

    /**
     * The saved history of one region, newest first.
     *
     * A preview of what each snapshot held *for this locale* — history is read in
     * the language the editor is looking at, and a snapshot taken while editing
     * another locale has nothing to show here.
     *
     * @return array<int, array<string, mixed>>
     */
    public function revisions(string $key, ?string $locale = null, int $limit = 20): array
    {
        $locale ??= app()->getLocale();
        $block = SiteBlock::query()->firstWhere('key', $key);

        if (! $block) {
            return [];
        }

        return $block->revisions()
            ->with('author')
            ->limit($limit)
            ->get()
            ->map(function (SiteBlockRevision $revision) use ($locale): array {
                $content = is_array($revision->content) ? $revision->content : [];
                $value = $content[$locale] ?? null;

                return [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at?->toIso8601String(),
                    'author' => $revision->author?->name,
                    'has_locale' => array_key_exists($locale, $content),
                    // Plain text and short: this is a label in a list, not a diff.
                    'preview' => is_string($value) ? Str::limit(trim(strip_tags($value)), 90) : null,
                ];
            })
            ->all();
    }

    /**
     * Put one snapshot back, for the locale the editor is looking at.
     *
     * The key comes from the revision, never from the caller: accepting both would
     * let a crafted request write revision A's content onto region B.
     *
     * @throws ValidationException
     */
    public function restoreRevision(SiteBlockRevision $revision, string $locale, ?int $userId): ?SiteBlock
    {
        $this->assertSupported(SiteBlock::TYPE_TEXT, $locale);

        $content = is_array($revision->content) ? $revision->content : [];

        if (! array_key_exists($locale, $content) || ! is_string($content[$locale])) {
            throw ValidationException::withMessages([
                'revision' => 'Phiên bản này không có nội dung cho ngôn ngữ đang xem.',
            ]);
        }

        return DB::transaction(function () use ($revision, $locale, $content, $userId): ?SiteBlock {
            $block = SiteBlock::query()->lockForUpdate()->find($revision->site_block_id);

            if (! $block) {
                throw ValidationException::withMessages(['revision' => 'Vùng nội dung không còn tồn tại.']);
            }

            // Forced, so the value being replaced is itself recoverable.
            $this->snapshot($block, $userId, true);

            $translations = $block->rawTranslations();
            $translations[$locale] = $content[$locale];

            $block->setRawAttributes(array_merge($block->getAttributes(), [
                'content' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            ]));
            $block->save();

            $this->forget();

            return $block;
        });
    }

    /** Drop the request-level cache so later renders in this request see the write. */
    public function forget(): void
    {
        $this->blocks = null;
    }

    /** @return array<string, SiteBlock> */
    private function all(): array
    {
        return $this->blocks ??= SiteBlock::query()->get()->keyBy('key')->all();
    }

    private function clean(string $type, string $value, ?string $format = null): string
    {
        if ($type === SiteBlock::TYPE_HTML && in_array($format, ['ul', 'ol'], true)) {
            // A list region stores the wrapper as `format` and only its inner HTML
            // as content. Purifying bare <li> nodes drops them because they have no
            // list parent, so sanitize in the same semantic context used to render.
            $wrapped = $this->htmlSanitizer->cleanSiteBlock('<'.$format.'>'.trim($value).'</'.$format.'>');
            if (preg_match('~<'.$format.'(?:\s[^>]*)?>(.*)</'.$format.'>~is', $wrapped, $matches) === 1) {
                return trim($matches[1]);
            }

            return '';
        }

        return match ($type) {
            // A single-line label has no business holding markup.
            SiteBlock::TYPE_TEXT => trim(strip_tags($value)),
            SiteBlock::TYPE_HTML => $this->htmlSanitizer->cleanSiteBlock(trim($value)),
            // Stored relative so the reference survives an APP_URL change.
            SiteBlock::TYPE_IMAGE, SiteBlock::TYPE_BACKGROUND => (string) MediaUrl::toStorable(trim($value)),
            default => trim($value),
        };
    }

    /**
     * Coalesce revisions: inline editing saves constantly and one revision per
     * keystroke is noise, not history.
     */
    private function snapshot(SiteBlock $block, ?int $userId, bool $force = false): void
    {
        if (! $block->exists) {
            // A brand-new block has nothing to snapshot.
            return;
        }

        // A rollback forces one. Coalescing is right for typing — one revision per
        // keystroke is noise — but a rollback that skipped its checkpoint would be
        // the one operation in the editor that cannot be undone.
        if (! $force && $block->revisions()->where('created_at', '>=', now()->subMinutes(10))->exists()) {
            return;
        }

        $block->revisions()->create([
            'created_by' => $userId,
            'content' => $block->rawTranslations(),
            'created_at' => now(),
        ]);

        $staleIds = SiteBlockRevision::query()
            ->where('site_block_id', $block->id)
            ->orderByDesc('id')
            ->skip(self::MAX_REVISIONS_PER_BLOCK)
            ->take(1000000)
            ->pluck('id');
        if ($staleIds->isNotEmpty()) {
            SiteBlockRevision::query()->whereKey($staleIds)->delete();
        }
    }

    private function assertSupported(string $type, string $locale): void
    {
        if (! in_array($type, SiteBlock::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Loại nội dung không hợp lệ.']);
        }

        if (! $this->languages->supports($locale)) {
            throw ValidationException::withMessages(['content_locale' => 'Ngôn ngữ không được hỗ trợ.']);
        }
    }
}
