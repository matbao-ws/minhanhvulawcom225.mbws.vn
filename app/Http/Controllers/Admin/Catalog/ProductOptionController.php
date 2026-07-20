<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\ProductOptionRequest;
use App\Models\Product;
use App\Services\Catalog\ProductOptionService;
use DomainException;

class ProductOptionController extends Controller
{
    public function __construct(private readonly ProductOptionService $options)
    {
    }

    public function edit(string $locale, Product $product)
    {
        return view('admin.catalog.products.options', [
            'product' => $product->load('optionGroups.values'),
        ]);
    }

    public function update(ProductOptionRequest $request, string $locale, Product $product)
    {
        try {
            $this->options->sync($product, $request->validated('groups'));
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['groups' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Đã lưu cấu hình thuộc tính biến thể.');
    }
}
