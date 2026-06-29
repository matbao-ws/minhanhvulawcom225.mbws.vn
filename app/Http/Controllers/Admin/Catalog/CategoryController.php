<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\CategoryRequest;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index()
    {
        // Ensure default "Chưa phân loại" category exists
        Category::query()->firstOrCreate(
            ['slug' => 'chua-phan-loai'],
            [
                'name' => [
                    'vi' => 'Chưa phân loại',
                    'en' => 'Uncategorized',
                ],
                'description' => [
                    'vi' => 'Danh mục mặc định cho các sản phẩm chưa được phân loại.',
                    'en' => 'Default category for uncategorized products.',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        // Paginate root categories
        $rootCategories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

        // Get all categories in one query to build tree in memory
        $allCategories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Group by parent_id
        $grouped = $allCategories->groupBy('parent_id');

        // Recursively compute total products count (direct + descendants) for each category
        $computeTotalProducts = function ($category) use (&$computeTotalProducts, $grouped) {
            $total = $category->products_count;
            $children = $grouped->get($category->id) ?? collect();
            foreach ($children as $child) {
                $total += $computeTotalProducts($child);
            }
            $category->total_products_count = $total;

            return $total;
        };

        // Run calculation for all root categories
        $allRootCategories = $allCategories->whereNull('parent_id');
        foreach ($allRootCategories as $rootCategory) {
            $computeTotalProducts($rootCategory);
        }

        $flatCategories = collect();

        $flatten = function ($categories, $depth = 0) use (&$flatten, &$flatCategories, $grouped) {
            foreach ($categories as $category) {
                $category->depth = $depth;
                $flatCategories->push($category);

                $children = $grouped->get($category->id) ?? collect();
                if ($children->isNotEmpty()) {
                    $flatten($children, $depth + 1);
                }
            }
        };

        // Filter root categories from $allCategories matching the current page's root IDs
        $rootIds = $rootCategories->pluck('id')->toArray();
        $rootItems = $allCategories->filter(fn ($c) => in_array($c->id, $rootIds));

        $flatten($rootItems);

        $rootCategories->setCollection($flatCategories);

        return view('admin.catalog.categories.index', [
            'categories' => $rootCategories,
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function create()
    {
        return view('admin.catalog.categories.create', [
            'category' => new Category(['is_active' => true]),
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        $this->categories->create($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', __('catalog.categories.created'));
    }

    public function edit(string $locale, Category $category)
    {
        return view('admin.catalog.categories.edit', [
            'category' => $category,
            'parentOptions' => $this->parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, string $locale, Category $category)
    {
        $this->categories->update($category, $request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', __('catalog.categories.updated'));
    }

    public function destroy(string $locale, Category $category)
    {
        if ($category->slug === 'chua-phan-loai') {
            return back()->with('error', __('catalog.categories.delete_default_blocked'));
        }

        if ($category->children()->exists()) {
            return back()->with('error', __('catalog.categories.delete_blocked'));
        }

        // Ensure default "Chưa phân loại" category exists
        $defaultCategory = Category::query()->firstOrCreate(
            ['slug' => 'chua-phan-loai'],
            [
                'name' => [
                    'vi' => 'Chưa phân loại',
                    'en' => 'Uncategorized',
                ],
                'description' => [
                    'vi' => 'Danh mục mặc định cho các sản phẩm chưa được phân loại.',
                    'en' => 'Default category for uncategorized products.',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        // Reassign products to the default category
        $category->products()->update(['category_id' => $defaultCategory->id]);

        $this->categories->delete($category);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', __('catalog.categories.deleted'));
    }

    public function quickUpdate(CategoryRequest $request, string $locale, Category $category)
    {
        $this->categories->update($category, $request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', __('catalog.categories.updated'));
    }

    public function sort(Request $request, string $locale)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:categories,id'],
            'start_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->categories->reorder($validated['ids'], (int) ($validated['start_order'] ?? 0));

        return response()->json([
            'message' => __('catalog.categories.sorted'),
        ]);
    }

    private function parentOptions(?Category $excluded = null)
    {
        $allCategories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $grouped = $allCategories->groupBy('parent_id');
        $rootCategories = $allCategories->whereNull('parent_id')
            ->filter(fn ($c) => $c->slug !== 'chua-phan-loai');

        $flatOptions = collect();

        $flatten = function ($categories, $depth = 0) use (&$flatten, &$flatOptions, $grouped, $excluded) {
            foreach ($categories as $category) {
                if ($excluded && $category->id === $excluded->id) {
                    continue;
                }

                $category->depth = $depth;
                $flatOptions->push($category);

                $children = $grouped->get($category->id) ?? collect();
                if ($children->isNotEmpty()) {
                    $flatten($children, $depth + 1);
                }
            }
        };

        $flatten($rootCategories);

        return $flatOptions;
    }
}
