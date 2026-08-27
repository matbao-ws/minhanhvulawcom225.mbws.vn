<?php

namespace App\Services;

use App\Models\SiteBlock;
use App\Models\SiteList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * How many items a repeatable storefront section shows, and in what order.
 *
 * The template declares the ids the theme ships with; this service only records
 * departures from that. A section nobody has touched has no row, so a fresh
 * install renders the approved design with nothing seeded — the same contract
 * the editable regions follow.
 *
 * Adding a block anywhere on a page is explicitly not what this does. An item
 * can only join a list the designer already built as a list, and it is rendered
 * by that list's own Blade markup.
 */
class SiteListService
{
    public const MAX_ITEMS = 40;

    /** @var array<string, SiteList>|null */
    private ?array $lists = null;

    /**
     * Ordered item ids for a section.
     *
     * @param  array<int, string>  $defaults  ids the template ships with
     * @return array<int, string>
     */
    public function items(string $key, array $defaults = []): array
    {
        $stored = $this->all()[$key]->items ?? null;

        if (! is_array($stored)) {
            return array_values($defaults);
        }

        $items = array_values(array_filter(
            $stored,
            fn ($id) => is_string($id) && $this->isValidId($id),
        ));

        // An empty stored list is a real answer — the editor removed every item.
        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * Append a new, empty item and return its id.
     *
     * @param  array<int, string>  $defaults
     */
    public function add(string $key, array $defaults = []): string
    {
        $items = $this->items($key, $defaults);

        if (count($items) >= self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'key' => 'Danh sách đã đạt giới hạn '.self::MAX_ITEMS.' mục.',
            ]);
        }

        do {
            $id = Str::lower(Str::random(8));
        } while (in_array($id, $items, true));

        $items[] = $id;
        $this->store($key, $items);

        return $id;
    }

    /**
     * Drop one item and the content it owned.
     *
     * @param  array<int, string>  $defaults
     */
    public function remove(string $key, string $itemId, array $defaults = []): void
    {
        $items = $this->items($key, $defaults);

        if (! in_array($itemId, $items, true)) {
            throw ValidationException::withMessages(['item' => 'Mục không tồn tại trong danh sách.']);
        }

        DB::transaction(function () use ($key, $itemId, $items): void {
            $this->store($key, array_values(array_diff($items, [$itemId])));

            // Nothing renders these rows any more, and leaving them behind would
            // resurrect old text if the same id were ever issued again.
            SiteBlock::query()
                ->where('key', 'like', $this->itemPrefix($key, $itemId).'%')
                ->delete();
        });
    }

    /**
     * @param  array<int, string>  $order
     * @param  array<int, string>  $defaults
     */
    public function reorder(string $key, array $order, array $defaults = []): void
    {
        $items = $this->items($key, $defaults);
        $order = array_values(array_filter($order, fn ($id) => in_array($id, $items, true)));

        if (count($order) !== count($items)) {
            throw ValidationException::withMessages(['order' => 'Thứ tự gửi lên không khớp danh sách hiện tại.']);
        }

        $this->store($key, $order);
    }

    /**
     * Region key for one slot of one item, e.g.
     * "about.leadership.item_a1f3c9d2.name".
     */
    public function itemKey(string $listKey, string $itemId, string $slot): string
    {
        return $this->itemPrefix($listKey, $itemId).$slot;
    }

    /**
     * Ids a list may contain.
     *
     * Two kinds share this shape: the random ids issued for boxes an editor added,
     * and the section names a template authors. Hyphens are allowed for the second
     * — `product-grid` is a normal partial name — but never dots, because a dot is
     * what separates one list's key from the next and a name carrying one could
     * address a different list's storage.
     */
    public function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/', $id);
    }

    private function itemPrefix(string $listKey, string $itemId): string
    {
        return $listKey.'.item_'.$itemId.'.';
    }

    /**
     * @param  array<int, string>  $items
     */
    private function store(string $key, array $items): void
    {
        SiteList::query()->updateOrCreate(['key' => $key], ['items' => array_values($items)]);

        // Per-request cache only; drop it so anything rendering later in the
        // same request sees the change.
        $this->lists = null;
    }

    /**
     * @return array<string, SiteList>
     */
    private function all(): array
    {
        return $this->lists ??= SiteList::query()->get()->keyBy('key')->all();
    }
}
