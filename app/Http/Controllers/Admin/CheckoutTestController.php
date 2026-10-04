<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ShippingService;
use Illuminate\Contracts\View\View;

/**
 * Admin tool: place a real order through the public checkout API (prices, promotions, stock and
 * shipping decided by the server, exactly as a storefront would), and look up GHN address codes.
 */
class CheckoutTestController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['optionGroups.values' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sku', 'price']);

        $locale = app()->getLocale();

        return view('admin.checkout-test.index', [
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->getTranslation('name', $locale),
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'options' => $product->optionGroups->map(fn ($group) => [
                    'name' => $group->getTranslation('name', $locale),
                    'values' => $group->values->map(fn ($value) => ['id' => $value->id, 'label' => $value->getTranslation('label', $locale)])->values(),
                ])->values(),
            ])->values(),
            'ghnEnabled' => (bool) data_get(app(ShippingService::class)->getSettings(), 'ghn.enabled'),
        ]);
    }
}
