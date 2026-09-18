<?php

namespace App\Services;

use App\Models\SiteSection;
use App\Models\SiteSectionRevision;
use App\Support\SectionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reads and writes how one section of a storefront template renders.
 *
 * Registered scoped: a page renders many sections and must not issue a query per
 * section — the same reason {@see SiteContentService} is scoped.
 *
 * What an editor may change is decided by {@see SectionRegistry} and by nothing
 * else. A value that is not declared there is rejected rather than stored and
 * ignored: a setting silently kept in the database is a setting somebody will
 * later believe is in effect.
 */
class SiteSectionService
{
    public const MAX_REVISIONS_PER_SECTION = 50;

    /** @var array<string, SiteSection>|null */
    private ?array $sections = null;

    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly SiteListService $lists,
    ) {}

    /**
     * The variant an editor chose, or null to let the section render its own way.
     */
    public function variant(string $listKey, string $section): ?string
    {
        $variant = $this->find($listKey, $section)?->variant;

        // Guard the read as well as the write: a variant retired from the registry
        // must stop applying, not keep resolving from an old row.
        return $this->registry->allowsVariant($section, $variant) ? $variant : null;
    }

    /**
     * The stored settings, filtered to what this section still offers.
     *
     * Only chosen values appear. There is no merge with defaults because there
     * are no defaults: an absent setting means the template's own appearance
     * stands, which is what lets a fresh install render the approved design.
     *
     * @return array<string, string>
     */
    public function settings(string $listKey, string $section): array
    {
        $stored = $this->find($listKey, $section)?->settings ?? [];

        if (! is_array($stored)) {
            return [];
        }

        $settings = [];

        foreach ($stored as $name => $value) {
            if (is_string($name) && $value !== null && $this->registry->allowsValue($section, $name, $value)) {
                $settings[$name] = $value;
            }
        }

        return $settings;
    }

    /** True when an editor hid this section from the page. */
    public function isHidden(string $listKey, string $section): bool
    {
        $stored = $this->find($listKey, $section);

        return $stored !== null && $stored->is_visible === false;
    }

    /**
     * The classes and inline custom properties one section's stored settings add
     * to its wrapper.
     *
     * Returned together because they come from the same decision and a caller
     * that fetched one without the other would render a colour with no class to
     * apply it, or the reverse.
     *
     * @return array{classes: array<int, string>, styles: array<string, string>}
     */
    public function presentation(string $listKey, string $section): array
    {
        $classes = [];
        $styles = [];

        foreach ($this->settings($listKey, $section) as $name => $value) {
            $class = $this->registry->tokenClass($section, $name, $value);

            if ($class !== null) {
                $classes[] = $class;

                continue;
            }

            // Everything the registry has no class for is a colour, and a colour
            // travels as a custom property: no class can exist in a hand-written
            // stylesheet for a value the editor invented at pick time.
            $spec = $this->registry->settingSpec($section, $name);

            if (($spec['type'] ?? null) === SectionRegistry::TYPE_COLOR) {
                $classes[] = $this->registry->colorClass($name);
                $styles['--client-section-'.$name] = $value;
            }
        }

        return ['classes' => $classes, 'styles' => $styles];
    }

    /**
     * Store an editor's choices for one section.
     *
     * `$settings` replaces what was stored rather than merging into it, so
     * clearing a setting is expressed by sending it as null — the panel always
     * submits the whole set it is showing.
     *
     * @param  array<string, string|null>  $settings
     *
     * @throws ValidationException
     */
    public function update(
        string $listKey,
        string $section,
        ?string $variant,
        array $settings,
        bool $visible,
        ?int $userId,
    ): ?SiteSection {
        $this->assertAddressable($listKey, $section);

        if (! $this->registry->has($section)) {
            throw ValidationException::withMessages(['section' => 'Section này không có tuỳ chọn hiển thị nào.']);
        }

        if (! $this->registry->allowsVariant($section, $variant)) {
            throw ValidationException::withMessages(['variant' => 'Biến thể không hợp lệ cho section này.']);
        }

        $clean = [];

        foreach ($settings as $name => $value) {
            if (! is_string($name) || ! $this->registry->allowsSetting($section, $name)) {
                throw ValidationException::withMessages(['settings' => 'Section này không có tuỳ chọn "'.$name.'".']);
            }

            if (! $this->registry->allowsValue($section, $name, $value)) {
                throw ValidationException::withMessages(['settings' => 'Giá trị không hợp lệ cho tuỳ chọn "'.$name.'".']);
            }

            // Null is how the panel clears a setting. Storing it would make the
            // row claim a choice nobody made.
            if ($value !== null) {
                $clean[$name] = $value;
            }
        }

        $key = SiteSection::keyFor($listKey, $section);

        return DB::transaction(function () use ($key, $variant, $clean, $visible, $userId): ?SiteSection {
            $stored = SiteSection::query()->lockForUpdate()->firstOrNew(['key' => $key]);

            if ($stored->exists
                && $stored->variant === $variant
                && $stored->settings === $clean
                && $stored->is_visible === $visible
            ) {
                return $stored;
            }

            $this->snapshot($stored, $userId);

            // Nothing overridden any more, so the row itself is the thing to
            // remove: the row *is* the override, exactly as in `site_blocks`.
            if ($variant === null && $clean === [] && $visible) {
                if ($stored->exists) {
                    $stored->delete();
                }

                $this->forget();

                return null;
            }

            $stored->key = $key;
            $stored->variant = $variant;
            $stored->settings = $clean;
            $stored->is_visible = $visible;
            $stored->save();

            $this->forget();

            return $stored;
        });
    }

    /**
     * Drop every override so the section renders the way the design does.
     *
     * @throws ValidationException
     */
    public function restore(string $listKey, string $section, ?int $userId): void
    {
        $this->assertAddressable($listKey, $section);

        DB::transaction(function () use ($listKey, $section, $userId): void {
            $stored = SiteSection::query()
                ->lockForUpdate()
                ->firstWhere('key', SiteSection::keyFor($listKey, $section));

            if (! $stored) {
                return;
            }

            $this->snapshot($stored, $userId);
            $stored->delete();
            $this->forget();
        });
    }

    /**
     * The saved history of one section, newest first.
     *
     * Each entry is described in the registry's own words — "Tối", "Rộng" — not as
     * tokens. A list of `bg-dark, pad-lg` is a list nobody can read back.
     *
     * @return array<int, array<string, mixed>>
     */
    public function revisions(string $listKey, string $section, int $limit = 20): array
    {
        $stored = SiteSection::query()->firstWhere('key', SiteSection::keyFor($listKey, $section));

        if (! $stored) {
            return [];
        }

        return $stored->revisions()
            ->with('author')
            ->limit($limit)
            ->get()
            ->map(fn (SiteSectionRevision $revision): array => [
                'id' => $revision->id,
                'created_at' => $revision->created_at?->toIso8601String(),
                'author' => $revision->author?->name,
                'preview' => $this->describe($section, $revision),
            ])
            ->all();
    }

    /**
     * Put one snapshot back.
     *
     * The section comes from the revision, never from the caller: accepting both
     * would let a crafted request write one section's appearance onto another.
     *
     * @throws ValidationException
     */
    public function restoreRevision(SiteSectionRevision $revision, ?int $userId): ?SiteSection
    {
        return DB::transaction(function () use ($revision, $userId): ?SiteSection {
            $stored = SiteSection::query()->lockForUpdate()->find($revision->site_section_id);

            if (! $stored) {
                throw ValidationException::withMessages(['revision' => 'Section không còn tồn tại.']);
            }

            $section = $this->sectionOf($stored->key);

            // Forced, so the state being replaced stays recoverable.
            $this->snapshot($stored, $userId, true);

            /*
             * Filtered through the registry on the way back in. An old snapshot can
             * name a token that has since been retired, and restoring it verbatim
             * would put a value into the row that no read path will ever honour.
             */
            $settings = [];

            foreach ((array) $revision->settings as $name => $value) {
                if (is_string($name) && $this->registry->allowsValue($section, $name, $value) && $value !== null) {
                    $settings[$name] = $value;
                }
            }

            $variant = $this->registry->allowsVariant($section, $revision->variant) ? $revision->variant : null;

            $stored->variant = $variant;
            $stored->settings = $settings;
            $stored->is_visible = $revision->is_visible;
            $stored->save();

            $this->forget();

            return $stored;
        });
    }

    /** Drop the request-level cache so later renders in this request see the write. */
    public function forget(): void
    {
        $this->sections = null;
    }

    /**
     * The section name inside a storage key.
     *
     * The key is `<list key>.<section name>` and a section name may not contain a
     * dot, so the last segment is unambiguous.
     */
    private function sectionOf(string $key): string
    {
        $parts = explode('.', $key);

        return (string) array_pop($parts);
    }

    /** One snapshot in the registry's own labels, for the history list. */
    private function describe(string $section, SiteSectionRevision $revision): string
    {
        $parts = [];

        if ($revision->variant !== null) {
            $parts[] = $this->registry->variants($section)[$revision->variant] ?? $revision->variant;
        }

        foreach ((array) $revision->settings as $name => $value) {
            $spec = $this->registry->settingSpec($section, (string) $name);
            $parts[] = $spec['values'][$value]['label'] ?? (string) $value;
        }

        if ($revision->is_visible === false) {
            $parts[] = 'đã ẩn';
        }

        return $parts === [] ? 'Như template' : implode(', ', $parts);
    }

    private function find(string $listKey, string $section): ?SiteSection
    {
        return $this->all()[SiteSection::keyFor($listKey, $section)] ?? null;
    }

    /** @return array<string, SiteSection> */
    private function all(): array
    {
        return $this->sections ??= SiteSection::query()->get()->keyBy('key')->all();
    }

    /**
     * Reject a list key or section name that could address another section's row.
     *
     * A dot in the section name is the dangerous case, which is why the id rule
     * that governs section names forbids one.
     *
     * @throws ValidationException
     */
    private function assertAddressable(string $listKey, string $section): void
    {
        if (! $this->lists->isValidId($section)) {
            throw ValidationException::withMessages(['section' => 'Tên section không hợp lệ.']);
        }

        if (! preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:\.[a-z0-9]+(?:[_-][a-z0-9]+)*)+$/', $listKey)) {
            throw ValidationException::withMessages(['list_key' => 'Mã vùng nội dung không hợp lệ.']);
        }
    }

    /**
     * Coalesce revisions the way region edits do: a settings panel fires on every
     * change, and one revision per click is noise, not history.
     */
    private function snapshot(SiteSection $section, ?int $userId, bool $force = false): void
    {
        if (! $section->exists) {
            // A brand-new row has nothing to snapshot.
            return;
        }

        // A rollback forces one, or it would be the single operation here that
        // cannot itself be undone.
        if (! $force && $section->revisions()->where('created_at', '>=', now()->subMinutes(10))->exists()) {
            return;
        }

        $section->revisions()->create([
            'created_by' => $userId,
            'variant' => $section->variant,
            'settings' => $section->settings ?? [],
            'is_visible' => $section->is_visible,
            'created_at' => now(),
        ]);

        $staleIds = SiteSectionRevision::query()
            ->where('site_section_id', $section->id)
            ->orderByDesc('id')
            ->skip(self::MAX_REVISIONS_PER_SECTION)
            ->take(1000000)
            ->pluck('id');
        if ($staleIds->isNotEmpty()) {
            SiteSectionRevision::query()->whereKey($staleIds)->delete();
        }
    }
}
