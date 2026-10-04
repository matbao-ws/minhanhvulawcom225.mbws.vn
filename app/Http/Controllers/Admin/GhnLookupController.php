<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;

/**
 * GHN connection check and address master data (province → district → ward), proxied so the GHN
 * token never reaches the browser. Used by the order page's "Tạo vận đơn" dialog and the checkout
 * test page.
 */
class GhnLookupController extends Controller
{
    public function __construct(private readonly ShippingService $shipping) {}

    /** Whether the saved GHN token, environment and Shop ID work — and if not, which one is wrong. */
    public function status(): JsonResponse
    {
        return response()->json($this->shipping->ghnVerify());
    }

    public function provinces(): JsonResponse
    {
        return $this->masterData('province');
    }

    public function districts(string $locale, int $provinceId): JsonResponse
    {
        return $this->masterData('district', ['province_id' => $provinceId]);
    }

    public function wards(string $locale, int $districtId): JsonResponse
    {
        return $this->masterData('ward', ['district_id' => $districtId]);
    }

    private function masterData(string $resource, array $payload = []): JsonResponse
    {
        try {
            return response()->json(['data' => $this->shipping->ghnMasterData($resource, $payload)]);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
