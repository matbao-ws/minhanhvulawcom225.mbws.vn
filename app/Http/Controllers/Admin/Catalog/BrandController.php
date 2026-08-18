<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\HandlesBulkActions;
use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\BrandRequest;
use App\Models\Brand;
use App\Services\ActivityLogger;
use App\Services\Catalog\BrandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    use HandlesBulkActions, HandlesPagination;

    public function __construct(private readonly BrandService $brands) {}

    public function index()
    {
        $keyword = request('q');

        $brandsList = Brand::query()
            ->withCount('products')
            ->when($keyword, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('slug', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return view('admin.catalog.brands.index', [
            'brands' => $brandsList,
        ]);
    }

    public function create()
    {
        return view('admin.catalog.brands.create', [
            'brand' => new Brand(['is_active' => true]),
        ]);
    }

    public function store(BrandRequest $request)
    {
        $this->brands->create($request->validated());

        return redirect()
            ->route('admin.brands.index')
            ->with('success', __('catalog.brands.created'));
    }

    public function edit(string $locale, Brand $brand)
    {
        return view('admin.catalog.brands.edit', [
            'brand' => $brand,
        ]);
    }

    public function update(BrandRequest $request, string $locale, Brand $brand)
    {
        $this->brands->update($brand, $request->validated());

        return redirect()
            ->route('admin.brands.index')
            ->with('success', __('catalog.brands.updated'));
    }

    public function destroy(string $locale, Brand $brand)
    {
        if ($brand->products()->exists()) {
            return back()->with('error', 'Không thể xóa thương hiệu đang được gán cho sản phẩm.');
        }

        $this->brands->delete($brand);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', __('catalog.brands.deleted'));
    }

    public function bulk(Request $request, string $locale)
    {
        $validated = $this->validatedBulkAction($request, 'brands', 'products');
        $ids = $validated['ids'];

        if ($validated['action'] === 'delete') {
            $usedCount = Brand::query()->whereIn('id', $ids)->has('products')->count();
            if ($usedCount > 0) {
                return back()->with('error', "Không thể xóa {$usedCount} thương hiệu đang được gán cho sản phẩm.");
            }

            DB::transaction(function () use ($ids): void {
                Brand::query()->whereIn('id', $ids)->lockForUpdate()->get()
                    ->each(fn (Brand $brand) => $this->brands->delete($brand));
            });

            ActivityLogger::log('bulk_deleted', null, 'Xóa hàng loạt thương hiệu', [
                'model' => Brand::class,
                'ids' => $ids,
                'count' => count($ids),
            ]);

            return back()->with('success', 'Đã xóa '.count($ids).' thương hiệu.');
        }

        $isActive = $validated['action'] === 'activate';
        $updated = Brand::query()->whereIn('id', $ids)->update(['is_active' => $isActive]);
        ActivityLogger::log('bulk_status_changed', null, 'Cập nhật trạng thái hàng loạt thương hiệu', [
            'model' => Brand::class,
            'ids' => $ids,
            'count' => $updated,
            'is_active' => $isActive,
        ]);

        return back()->with('success', 'Đã '.($isActive ? 'kích hoạt' : 'tạm ẩn').' '.$updated.' thương hiệu.');
    }

    public function quickUpdate(BrandRequest $request, string $locale, Brand $brand)
    {
        $this->brands->update($brand, $request->validated());

        return redirect()
            ->route('admin.brands.index')
            ->with('success', __('catalog.brands.updated'));
    }

    public function sort(Request $request, string $locale)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:brands,id'],
            'start_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->brands->reorder($validated['ids'], (int) ($validated['start_order'] ?? 0));

        return response()->json([
            'message' => __('catalog.brands.sorted'),
        ]);
    }
}
