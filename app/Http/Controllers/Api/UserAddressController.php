<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserAddressController extends Controller
{
    /**
     * Get list of addresses for the logged-in customer.
     */
    public function index(Request $request)
    {
        $addresses = $request->user()
            ->addresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return ApiResponse::success($addresses);
    }

    /**
     * Create a new address.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $user = $request->user();
        $isFirst = $user->addresses()->count() === 0;
        $isDefault = $isFirst || (bool) $request->input('is_default');

        if ($isDefault) {
            // Reset other default addresses
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
            'address' => $request->input('address'),
            'is_default' => $isDefault,
        ]);

        return ApiResponse::success($address, 'Thêm địa chỉ mới thành công.');
    }

    /**
     * Update an existing address.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->find($id);

        if (!$address) {
            return ApiResponse::error('Không tìm thấy địa chỉ hoặc bạn không có quyền sửa địa chỉ này.', 403);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $isDefault = (bool) $request->input('is_default');

        if ($isDefault && !$address->is_default) {
            // Reset other default addresses
            $user->addresses()->update(['is_default' => false]);
        }

        $address->update([
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
            'address' => $request->input('address'),
            'is_default' => $isDefault || $address->is_default, // if it was already default, keep it default
        ]);

        return ApiResponse::success($address, 'Cập nhật địa chỉ thành công.');
    }

    /**
     * Delete an address.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->find($id);

        if (!$address) {
            return ApiResponse::error('Không tìm thấy địa chỉ hoặc bạn không có quyền xóa địa chỉ này.', 403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            // Set another address as default
            $latest = $user->addresses()->latest()->first();
            if ($latest) {
                $latest->update(['is_default' => true]);
            }
        }

        return ApiResponse::success(null, 'Xóa địa chỉ thành công.');
    }

    /**
     * Set an address as default.
     */
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->find($id);

        if (!$address) {
            return ApiResponse::error('Không tìm thấy địa chỉ hoặc bạn không có quyền thao tác trên địa chỉ này.', 403);
        }

        $user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return ApiResponse::success($address, 'Đặt làm địa chỉ mặc định thành công.');
    }
}
