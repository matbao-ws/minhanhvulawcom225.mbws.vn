<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuRequest;
use App\Models\Menu;
use App\Services\ActivityLogger;

class MenuController extends Controller
{
    use HandlesPagination;

    public function index()
    {
        $menus = Menu::query()
            ->withCount('items')
            ->orderBy('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return view('admin.menus.index', compact('menus'));
    }

    public function create()
    {
        return view('admin.menus.create', [
            'menu' => new Menu(['is_active' => true]),
        ]);
    }

    public function store(MenuRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $menu = Menu::query()->create($data);
        ActivityLogger::log('created', $menu, "Tạo menu {$menu->name}", [
            'new' => $menu->only(['key', 'name', 'is_active']),
        ]);

        return redirect()
            ->route('admin.menus.items.index', ['menu' => $menu])
            ->with('success', __('admin.menus.created'));
    }

    public function edit(string $locale, Menu $menu)
    {
        return view('admin.menus.edit', compact('menu'));
    }

    public function update(MenuRequest $request, string $locale, Menu $menu)
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $old = $menu->only(['key', 'name', 'is_active']);
        $menu->update($data);
        ActivityLogger::log('updated', $menu, "Cập nhật menu {$menu->name}", [
            'old' => $old,
            'new' => $menu->only(['key', 'name', 'is_active']),
        ]);

        return redirect()
            ->route('admin.menus.index')
            ->with('success', __('admin.menus.updated'));
    }

    public function destroy(string $locale, Menu $menu)
    {
        $name = $menu->name;
        // Items cascade with the menu; nothing else references them.
        $menu->delete();
        ActivityLogger::log('deleted', $menu, "Xóa menu {$name}", ['old' => ['key' => $menu->key, 'name' => $name]]);

        return redirect()
            ->route('admin.menus.index')
            ->with('success', __('admin.menus.deleted'));
    }
}
