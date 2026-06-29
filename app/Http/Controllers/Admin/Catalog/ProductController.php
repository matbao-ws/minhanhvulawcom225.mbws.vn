<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use App\Services\Catalog\ProductImportService;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
        private readonly ProductImportService $importService
    ) {
    }

    public function index()
    {
        return view('admin.catalog.products.index', [
            'products' => Product::query()
                ->with(['category', 'brand'])
                ->withCount('variants')
                ->when(request('q'), function ($query, string $keyword) {
                    $query->where(function ($query) use ($keyword) {
                        $query->where('sku', 'like', "%{$keyword}%")
                            ->orWhere('slug', 'like', "%{$keyword}%")
                            ->orWhere('name', 'like', "%{$keyword}%");
                    });
                })
                ->when(request('category_id'), function ($query, $categoryId) {
                    $query->where('category_id', $categoryId);
                })
                ->when(request('brand_id'), function ($query, $brandId) {
                    $query->where('brand_id', $brandId);
                })
                ->when(request()->filled('status'), function ($query) {
                    $query->where('is_active', request('status'));
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'categoryOptions' => $this->categoryOptions(),
            'brandOptions' => $this->brandOptions(),
        ]);
    }

    public function create()
    {
        return view('admin.catalog.products.create', [
            'product' => new Product([
                'price' => 0,
                'stock_quantity' => 0,
                'manage_stock' => true,
                'is_active' => true,
            ]),
            'categoryOptions' => $this->categoryOptions(),
            'brandOptions' => $this->brandOptions(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('image_file')) {
                $cloudinaryService = app(\App\Services\CloudinaryService::class);
                $data['image_url'] = $cloudinaryService->uploadFile($request->file('image_file'), 'products');
            }
            $product = $this->products->create($data);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', __('catalog.products.created'));
    }

    public function show(string $locale, Product $product)
    {
        return view('admin.catalog.products.show', [
            'product' => $product->load(['category', 'variants']),
        ]);
    }

    public function edit(string $locale, Product $product)
    {
        return view('admin.catalog.products.edit', [
            'product' => $product,
            'categoryOptions' => $this->categoryOptions(),
            'brandOptions' => $this->brandOptions(),
        ]);
    }

    public function update(ProductRequest $request, string $locale, Product $product)
    {
        $data = $request->validated();
        if ($request->hasFile('image_file')) {
            $cloudinaryService = app(\App\Services\CloudinaryService::class);
            $data['image_url'] = $cloudinaryService->uploadFile($request->file('image_file'), 'products');
        }
        $product = $this->products->update($product, $data);

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', __('catalog.products.updated'));
    }

    public function destroy(string $locale, Product $product)
    {
        $this->products->delete($product);

        return redirect()
            ->route('admin.products.index')
            ->with('success', __('catalog.products.deleted'));
    }

    private function categoryOptions()
    {
        $allCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $grouped = $allCategories->groupBy('parent_id');
        $rootCategories = $allCategories->whereNull('parent_id');

        $flatOptions = collect();

        $flatten = function ($categories, $depth = 0) use (&$flatten, &$flatOptions, $grouped) {
            foreach ($categories as $category) {
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

    private function brandOptions()
    {
        return Brand::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function export()
    {
        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=products_export_' . date('Y-m-d') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $products = Product::query()
            ->with(['category', 'brand'])
            ->when(request('q'), function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('sku', 'like', "%{$keyword}%")
                        ->orWhere('slug', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%");
                });
            })
            ->when(request('category_id'), function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when(request('brand_id'), function ($query, $brandId) {
                $query->where('brand_id', $brandId);
            })
            ->when(request()->filled('status'), function ($query) {
                $query->where('is_active', request('status'));
            })
            ->latest()
            ->get();

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM
            fputs($file, "\xEF\xBB\xBF");

            // Write Headers
            fputcsv($file, [
                'name_vi',
                'name_en',
                'slug',
                'sku',
                'category',
                'brand',
                'price',
                'compare_at_price',
                'cost_price',
                'stock_quantity',
                'manage_stock',
                'is_active',
                'short_description_vi',
                'short_description_en',
                'description_vi',
                'description_en',
                'image_url'
            ]);

            foreach ($products as $product) {
                fputcsv($file, [
                    $product->getTranslation('name', 'vi', false) ?: $product->name,
                    $product->getTranslation('name', 'en', false) ?: $product->name,
                    $product->slug,
                    $product->sku,
                    $product->category?->name ?? '',
                    $product->brand?->name ?? '',
                    $product->price,
                    $product->compare_at_price,
                    $product->cost_price,
                    $product->stock_quantity,
                    $product->manage_stock ? 1 : 0,
                    $product->is_active ? 1 : 0,
                    $product->getTranslation('short_description', 'vi', false) ?: '',
                    $product->getTranslation('short_description', 'en', false) ?: '',
                    $product->getTranslation('description', 'vi', false) ?: '',
                    $product->getTranslation('description', 'en', false) ?: '',
                    $product->image_url
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadTemplate(string $locale, string $type)
    {
        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=template_{$type}.csv",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($type) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            if ($type === 'wordpress') {
                fputcsv($file, [
                    'SKU',
                    'Name',
                    'Published',
                    'Short description',
                    'Description',
                    'Regular price',
                    'Sale price',
                    'Manage stock?',
                    'Stock',
                    'Categories',
                    'Images'
                ]);

                fputcsv($file, [
                    'WP-SAMPLE-01',
                    'Sample WordPress Product',
                    '1',
                    'This is short description',
                    '<p>This is rich HTML description</p>',
                    '100000',
                    '80000',
                    'yes',
                    '50',
                    'Electronics > Computers',
                    'https://images.unsplash.com/photo-1517694712202-14dd9538aa97'
                ]);
            } else {
                fputcsv($file, [
                    'Name_VI',
                    'Name_EN',
                    'Slug',
                    'SKU',
                    'Category',
                    'Brand',
                    'Price',
                    'Compare_At_Price',
                    'Cost_Price',
                    'Stock_Quantity',
                    'Manage_Stock',
                    'Is_Active',
                    'Short_Description_VI',
                    'Short_Description_EN',
                    'Description_VI',
                    'Description_EN',
                    'Image_URL'
                ]);

                fputcsv($file, [
                    'Sản phẩm mẫu',
                    'Sample Product',
                    'san-pham-mau',
                    'SKU-SAMPLE-1',
                    'Electronics > Laptops',
                    'Dell',
                    '150000',
                    '200000',
                    '100000',
                    '20',
                    '1',
                    '1',
                    'Mô tả ngắn tiếng Việt',
                    'English short description',
                    '<p>Mô tả chi tiết</p>',
                    '<p>Detailed English description</p>',
                    'https://images.unsplash.com/photo-1517694712202-14dd9538aa97'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'import_type' => ['required', 'string', 'in:standard,wordpress'],
        ]);

        $file = $request->file('import_file');
        $type = $request->input('import_type');

        try {
            $results = $this->importService->import($file->getRealPath(), $type);
            
            $msg = __('catalog.products.import_summary', [
                'success' => $results['success'],
                'failed' => $results['failed']
            ]);

            if ($results['failed'] > 0) {
                return back()->with('warning', $msg)->with('import_errors', $results['errors']);
            }

            return back()->with('success', $msg);
        } catch (\Exception $exception) {
            return back()->with('error', __('catalog.products.import_failed') . ' ' . $exception->getMessage());
        }
    }
}
