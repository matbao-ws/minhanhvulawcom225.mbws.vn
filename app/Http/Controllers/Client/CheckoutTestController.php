<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

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

        return view('client.checkout.test', [
            'products' => $products,
            'productOptions' => $products->mapWithKeys(fn (Product $product) => [$product->id => $product->optionGroups->map(fn ($group) => [
                'name' => $group->getTranslation('name', app()->getLocale()),
                'values' => $group->values->map(fn ($value) => ['id' => $value->id, 'label' => $value->getTranslation('label', app()->getLocale())])->values(),
            ])->values()]),
        ]);
    }

    public function provinces(ShippingService $shipping): JsonResponse { return $this->masterData($shipping, 'province'); }
    public function districts(ShippingService $shipping, int $provinceId): JsonResponse { return $this->masterData($shipping, 'district', ['province_id' => $provinceId]); }
    public function wards(ShippingService $shipping, int $districtId): JsonResponse { return $this->masterData($shipping, 'ward', ['district_id' => $districtId]); }

    private function masterData(ShippingService $shipping, string $resource, array $payload = []): JsonResponse
    {
        try { return response()->json(['data' => $shipping->ghnMasterData($resource, $payload)]); }
        catch (\DomainException $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'GHN không xác thực được Token/Shop ID của môi trường hiện tại.'], 422);
        }
    }
}
