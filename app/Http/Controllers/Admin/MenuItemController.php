<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PostCategory;
use App\Services\ActivityLogger;
use App\Services\MenuService;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function index(string $locale, Menu $menu)
    {
        return view('admin.menus.items.index', [
            'menu' => $menu,
            'items' => $this->menus->adminTree($menu),
        ]);
    }

    public function create(string $locale, Menu $menu)
    {
        return view('admin.menus.items.create', [
            'menu' => $menu,
            'item' => new MenuItem(['type' => MenuItem::TYPE_PAGE, 'is_active' => true]),
        ] + $this->formOptions($menu));
    }

    public function store(MenuItemRequest $request, string $locale, Menu $menu)
    {
        $item = $menu->items()->create($this->payload($request, $menu));
        ActivityLogger::log('created', $item, "Thêm mục menu vào {$menu->name}", [
            'new' => $item->only(['label', 'type', 'is_active']),
        ]);

        return redirect()
            ->route('admin.menus.items.index', ['menu' => $menu])
            ->with('success', __('admin.menus.items.created'));
    }

    public function edit(string $locale, Menu $menu, MenuItem $item)
    {
        $this->assertBelongsToMenu($menu, $item);

        return view('admin.menus.items.edit', [
            'menu' => $menu,
            'item' => $item,
        ] + $this->formOptions($menu, $item));
    }

    public function update(MenuItemRequest $request, string $locale, Menu $menu, MenuItem $item)
    {
        $this->assertBelongsToMenu($menu, $item);

        $old = $item->only(['label', 'type', 'is_active']);
        $item->update($this->payload($request, $menu, $item));
        ActivityLogger::log('updated', $item, "Cập nhật mục menu trong {$menu->name}", [
            'old' => $old,
            'new' => $item->only(['label', 'type', 'is_active']),
        ]);

        return redirect()
            ->route('admin.menus.items.index', ['menu' => $menu])
            ->with('success', __('admin.menus.items.updated'));
    }

    public function destroy(string $locale, Menu $menu, MenuItem $item)
    {
        $this->assertBelongsToMenu($menu, $item);

        // Children cascade in the database; deleting a branch is deliberate.
        $item->delete();
        ActivityLogger::log('deleted', $item, "Xóa mục menu khỏi {$menu->name}", [
            'old' => $item->only(['label', 'type']),
        ]);

        return redirect()
            ->route('admin.menus.items.index', ['menu' => $menu])
            ->with('success', __('admin.menus.items.deleted'));
    }

    public function sort(Request $request, string $locale, Menu $menu)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'start_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->menus->reorder($menu, $validated['ids'], (int) ($validated['start_order'] ?? 0));

        return response()->json(['message' => __('admin.menus.items.sorted')]);
    }

    /**
     * Only the field matching the chosen type is kept; the rest are cleared so
     * a type switch cannot leave a stale target behind.
     *
     * @return array<string, mixed>
     */
    private function payload(MenuItemRequest $request, Menu $menu, ?MenuItem $item = null): array
    {
        $data = $request->validated();
        $type = $data['type'];

        return [
            'parent_id' => $data['parent_id'] ?? null,
            'label' => $data['label'],
            'type' => $type,
            'page_id' => $type === MenuItem::TYPE_PAGE ? $data['page_id'] : null,
            'category_id' => $type === MenuItem::TYPE_CATEGORY ? $data['category_id'] : null,
            'post_category_id' => $type === MenuItem::TYPE_POST_CATEGORY ? $data['post_category_id'] : null,
            'url' => $type === MenuItem::TYPE_URL ? trim((string) $data['url']) : null,
            'target_blank' => (bool) ($data['target_blank'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? $item?->sort_order ?? $this->nextSortOrder($menu, $data['parent_id'] ?? null)),
        ];
    }

    private function nextSortOrder(Menu $menu, ?int $parentId): int
    {
        return (int) $menu->items()->where('parent_id', $parentId)->max('sort_order') + 1;
    }

    /**
     * Nested route model binding resolves `{item}` on its own, so the pairing
     * with `{menu}` has to be checked or any item id would be editable through
     * any menu.
     */
    private function assertBelongsToMenu(Menu $menu, MenuItem $item): void
    {
        abort_unless($item->menu_id === $menu->id, 404);
    }

    /** @return array<string, mixed> */
    private function formOptions(Menu $menu, ?MenuItem $item = null): array
    {
        $excluded = $item ? $this->menus->descendantIds($item) : [];

        return [
            'parentOptions' => $this->menus->adminTree($menu)
                ->reject(fn (MenuItem $candidate): bool => in_array($candidate->id, $excluded, true))
                ->values(),
            'pages' => Page::query()->orderBy('id')->get(),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('id')->get(),
            'postCategories' => PostCategory::query()->orderBy('sort_order')->orderBy('id')->get(),
        ];
    }
}
