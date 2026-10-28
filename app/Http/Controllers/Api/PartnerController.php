<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPartnerResource;
use App\Services\Partners\PartnerQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * GET /api/public/partners?group=<key> — the active logos of one partner group, in admin order.
 * Reads through PartnerQueryService, the same query a Blade theme uses.
 */
class PartnerController extends Controller
{
    public function index(Request $request, PartnerQueryService $partners): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'group' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $group = (string) $request->query('group');

        return ApiResponse::success(PublicPartnerResource::collection($partners->forGroup($group)), null, ['group' => $group]);
    }
}
