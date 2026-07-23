<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    /**
     * Get the active shipping configurations.
     */
    public function getSettings()
    {
        $partners = \App\Models\ShippingPartner::query()->get();

        $ghtk = $partners->firstWhere('partner_code', 'DTGH000012');
        $flatRate = $partners->firstWhere('partner_code', 'DTGHTUGIAO');

        return [
            'ghtk' => [
                'enabled' => $ghtk ? ($ghtk->status === 'active') : false,
                'api_token' => $ghtk ? data_get($ghtk->settings, 'api_token', '') : '',
                'api_url' => $ghtk ? data_get($ghtk->settings, 'api_url', 'https://services.giaohangtietkiem.vn') : 'https://services.giaohangtietkiem.vn',
                'name' => $ghtk ? $ghtk->name : 'Giao Hàng Tiết Kiệm (GHTK)',
                'webhook_token' => $ghtk ? data_get($ghtk->settings, 'webhook_token', '') : '',
                'pickup' => $ghtk ? data_get($ghtk->settings, 'pickup', []) : [],
            ],
            'flat_rate' => [
                'enabled' => $flatRate ? ($flatRate->status === 'active') : false,
                'fee' => $flatRate ? (float) data_get($flatRate->settings, 'fee', 0) : 0.0,
                'name' => $flatRate ? $flatRate->name : 'Giao hàng nhanh đồng giá',
            ]
        ];
    }

    /**
     * Push an order to Giao Hàng Tiết Kiệm (GHTK).
     */
    public function pushToGHTK(Order $order, array $params = []): array
    {
        $settings = $this->getSettings();
        $ghtkSettings = data_get($settings, 'ghtk');

        if (!data_get($ghtkSettings, 'enabled')) {
            return [
                'success' => false,
                'message' => 'Phương thức Giao Hàng Tiết Kiệm chưa được kích hoạt.'
            ];
        }

        $apiToken = data_get($ghtkSettings, 'api_token');
        $apiUrl = data_get($ghtkSettings, 'api_url', 'https://services.giaohangtietkiem.vn');

        if ($apiToken === 'mock' && app()->environment(['local', 'testing'])) {
            Log::info("Using mock integration for GHTK. Order ID: {$order->id}");
            
            $mockFee = 35000;
            if ($order->subtotal > 1000000) {
                $mockFee = 0; // Free ship for order over 1M
            }

            return [
                'success' => true,
                'tracking_number' => 'GHTK.MOCK.' . strtoupper(bin2hex(random_bytes(6))),
                'fee' => $mockFee,
                'message' => 'Đẩy đơn hàng sang GHTK thành công (Môi trường giả lập).'
            ];
        }

        if (empty($apiToken) || ! in_array($apiUrl, ['https://services.giaohangtietkiem.vn', 'https://services.ghtk.vn'], true)) {
            return ['success' => false, 'message' => 'Cấu hình GHTK không hợp lệ.'];
        }

        $pickup = data_get($ghtkSettings, 'pickup', []);
        foreach (['name', 'tel', 'province', 'district', 'ward', 'address'] as $field) {
            if (blank(data_get($pickup, $field))) {
                return ['success' => false, 'message' => 'Thiếu thông tin địa chỉ lấy hàng GHTK.'];
            }
        }

        try {
            // Prepare products list for GHTK API
            $products = [];
            foreach ($order->items as $item) {
                $products[] = [
                    'name' => $item->product_name,
                    'weight' => 0.2, // default 200g
                    'quantity' => $item->quantity,
                    'price' => (int) $item->price,
                ];
            }

            // Prepare order structure for GHTK API
            $payload = [
                'products' => $products,
                'order' => [
                    'id' => $order->order_number,
                    'pick_name' => $pickup['name'],
                    'pick_money' => $order->payment_method === 'cod' ? (int) $order->grand_total : 0,
                    'pick_tel' => $pickup['tel'],
                    'pick_province' => $pickup['province'],
                    'pick_district' => $pickup['district'],
                    'pick_ward' => $pickup['ward'],
                    'pick_address' => $pickup['address'],
                    'tel' => $order->customer_phone,
                    'name' => $order->customer_name,
                    'address' => $order->shipping_address,
                    'province' => $params['province'] ?? 'Hồ Chí Minh',
                    'district' => $params['district'] ?? 'Quận 1',
                    'ward' => $params['ward'] ?? 'Phường Bến Nghé',
                    'is_freeship' => 0,
                    'weight_option' => 'gram',
                    'total_weight' => $params['weight'] ?? 500, // weight in grams
                    'value' => (int) $order->grand_total,
                ]
            ];

            $response = Http::timeout(15)->retry(2, 200)->withHeaders([
                'Token' => $apiToken,
                'Content-Type' => 'application/json',
            ])->post(rtrim($apiUrl, '/') . '/services/shipment/order', $payload);

            if ($response->failed()) {
                Log::error("GHTK API connection failed: " . $response->body());
                return [
                    'success' => false,
                    'message' => 'Không thể kết nối với hệ thống GHTK. Vui lòng thử lại sau.'
                ];
            }

            $data = $response->json();
            if (data_get($data, 'success')) {
                return [
                    'success' => true,
                    'tracking_number' => data_get($data, 'order.label'),
                    'fee' => (float) data_get($data, 'order.fee', 0),
                    'message' => 'Đẩy đơn hàng sang GHTK thành công.'
                ];
            }

            return [
                'success' => false,
                'message' => data_get($data, 'message', 'Có lỗi xảy ra khi đẩy đơn hàng sang GHTK.')
            ];
        } catch (\Exception $e) {
            Log::error("GHTK push error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi kết nối dịch vụ vận chuyển. Vui lòng thử lại sau.'
            ];
        }
    }
}
