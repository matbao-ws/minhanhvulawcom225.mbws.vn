<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\BrandRequest;
use App\Models\Brand;
use App\Services\Catalog\BrandService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
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
            ->paginate(15)
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
        $this->brands->delete($brand);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', __('catalog.brands.deleted'));
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
