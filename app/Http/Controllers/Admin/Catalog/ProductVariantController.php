<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\ProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductService;

class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductService $products)
    {
    }

    public function create(string $locale, Product $product)
    {
        return view('admin.catalog.variants.create', [
            'product' => $product,
            'variant' => new ProductVariant([
                'stock_quantity' => 0,
                'sort_order' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(ProductVariantRequest $request, string $locale, Product $product)
    {
        $this->products->createVariant($product, $request->validated());

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', __('catalog.variants.created'));
    }

    public function edit(string $locale, Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);

        return view('admin.catalog.variants.edit', [
            'product' => $product,
            'variant' => $variant,
        ]);
    }

    public function update(ProductVariantRequest $request, string $locale, Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);

        $this->products->updateVariant($variant, $request->validated());

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', __('catalog.variants.updated'));
    }

    public function destroy(string $locale, Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);

        $this->products->deleteVariant($variant);

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', __('catalog.variants.deleted'));
    }
}
