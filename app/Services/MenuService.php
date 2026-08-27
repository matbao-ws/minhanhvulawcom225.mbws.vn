<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single place storefront navigation is read and reordered.
 *
 * Blade must not call this application's own public API, so the navigation
 * partial and `/api/public/menus/{key}` both come through here. Adding a filter
 * or an eager load in only one of them is exactly the drift this service exists
 * to prevent.
 */
class MenuService
{
    public function __construct(private readonly LanguageRegistry $languages) {}

    /**
     * Active items of a menu, nested, with every link already resolved.
     *
     * @return Collection<int, array{label: string, url: string|null, target_blank: bool, children: Collection}>
     */
    public function tree(string $key, ?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();

        $menu = Menu::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $menu) {
            return collect();
        }

        $grouped = $menu->items()
            ->where('is_active', true)
            ->with(['page.localizedSlugs', 'category.localizedSlugs', 'postCategory.localizedSlugs'])
            ->get()
            ->groupBy('parent_id');

        return $this->branch($grouped->get(null) ?? collect(), $grouped, $locale);
    }

    /**
     * Every item of a menu as a flat, depth-annotated list for the admin tree.
     *
     * @return Collection<int, MenuItem>
     */
    public function adminTree(Menu $menu): Collection
    {
        $grouped = $menu->items()
            ->with(['page', 'category', 'postCategory'])
            ->get()
            ->groupBy('parent_id');

        $flat = collect();

        $walk = function (Collection $items, int $depth) use (&$walk, $flat, $grouped): void {
            foreach ($items as $item) {
                $item->depth = $depth;
                $flat->push($item);
                $walk($grouped->get($item->id) ?? collect(), $depth + 1);
            }
        };

        $walk($grouped->get(null) ?? collect(), 0);

        return $flat;
    }

    /**
     * Storefront URL for an item, or null when nothing can be linked yet.
     *
     * Null is still a real answer: an item whose target record was deleted has
     * nowhere to point, and the renderer shows it as plain text rather than a
     * link into a 404.
     */
    public function resolveUrl(MenuItem $item, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if ($item->type === MenuItem::TYPE_URL) {
            return $this->safeUrl($item->url);
        }

        if ($item->hasMissingTarget()) {
            return null;
        }

        return match ($item->type) {
            MenuItem::TYPE_PAGE => route('client.pages.show', [
                'locale' => $locale,
                'slug' => $item->page->canonicalSlug($locale),
            ]),
            MenuItem::TYPE_CATEGORY => route('client.categories.show', [
                'locale' => $locale,
                'slug' => $item->category->canonicalSlug($locale),
            ]),
            MenuItem::TYPE_POST_CATEGORY => route('client.post-categories.show', [
                'locale' => $locale,
                'slug' => $item->postCategory->canonicalSlug($locale),
            ]),
            default => null,
        };
    }

    /**
     * Persist a new order for sibling items.
     *
     * Ids are scoped to the menu so a crafted payload cannot reshuffle another
     * menu, and the whole run is one transaction so a half-applied order never
     * survives.
     *
     * @param  array<int, int>  $ids
     */
    public function reorder(Menu $menu, array $ids, int $startOrder = 0): void
    {
        DB::transaction(function () use ($menu, $ids, $startOrder): void {
            foreach (array_values($ids) as $index => $id) {
                $menu->items()->whereKey($id)->update(['sort_order' => $startOrder + $index]);
            }
        });
    }

    /**
     * Ids of an item and everything beneath it — the set that may not become
     * its own parent.
     *
     * @return array<int, int>
     */
    public function descendantIds(MenuItem $item): array
    {
        $grouped = MenuItem::query()
            ->where('menu_id', $item->menu_id)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [$item->id];
        $queue = [$item->id];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($grouped->get($current) ?? collect() as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @param  Collection<int|null, Collection<int, MenuItem>>  $grouped
     * @return Collection<int, array<string, mixed>>
     */
    private function branch(Collection $items, Collection $grouped, string $locale): Collection
    {
        return $items
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'label' => $this->label($item, $locale),
                'url' => $this->resolveUrl($item, $locale),
                'target_blank' => $item->target_blank,
                'children' => $this->branch($grouped->get($item->id) ?? collect(), $grouped, $locale),
            ]);
    }

    private function label(MenuItem $item, string $locale): string
    {
        return $item->getTranslation('label', $locale, false)
            ?: $item->getTranslation('label', $this->languages->fallbackLocale(), false)
            ?: (string) collect($item->getTranslations('label'))->first();
    }

    /**
     * Custom URLs are rendered straight into an href, so only relative paths
     * and http(s) links survive. `javascript:` and `data:` must never reach the
     * markup.
     */
    private function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
