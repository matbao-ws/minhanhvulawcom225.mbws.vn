<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShippingPartner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    public const GHN_PRODUCTION_URL = 'https://online-gateway.ghn.vn';

    public const GHN_DEVELOPMENT_URL = 'https://dev-online-gateway.ghn.vn';

    /**
     * Get the active shipping configurations.
     */
    public function getSettings()
    {
        $partners = ShippingPartner::query()->get();

        $ghtk = $partners->firstWhere('partner_code', 'DTGH000012');
        $ghn = $partners->firstWhere('partner_code', 'DTGH000013');
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
            ],
            'ghn' => [
                'enabled' => $ghn?->status === 'active',
                'api_token' => data_get($ghn?->settings, 'api_token', ''),
                'api_url' => data_get($ghn?->settings, 'api_url', self::GHN_DEVELOPMENT_URL),
                'shop_id' => data_get($ghn?->settings, 'shop_id', ''),
                'webhook_token' => data_get($ghn?->settings, 'webhook_token', ''),
                // Create the GHN shipment when an admin confirms the order (pending → processing).
                'auto_push_on_confirm' => (bool) data_get($ghn?->settings, 'auto_push_on_confirm', false),
                // Used when the products carry no weight / for the parcel size.
                'default_weight' => (int) data_get($ghn?->settings, 'default_weight', 500),
                'default_length' => (int) data_get($ghn?->settings, 'default_length', 20),
                'default_width' => (int) data_get($ghn?->settings, 'default_width', 15),
                'default_height' => (int) data_get($ghn?->settings, 'default_height', 10),
            ],
        ];
    }

    /** Create a GHN v2 shipping order. All geographic IDs are GHN master-data IDs. */
    public function pushToGHN(Order $order, array $params): array
    {
        $settings = data_get($this->getSettings(), 'ghn', []);
        if (! data_get($settings, 'enabled')) {
            return ['success' => false, 'message' => 'Phương thức Giao Hàng Nhanh chưa được kích hoạt.'];
        }

        $token = (string) data_get($settings, 'api_token');
        $shopId = (string) data_get($settings, 'shop_id');
        $baseUrl = rtrim((string) data_get($settings, 'api_url'), '/');
        if ($token === '' || $shopId === '' || ! in_array($baseUrl, [self::GHN_PRODUCTION_URL, self::GHN_DEVELOPMENT_URL], true)) {
            return ['success' => false, 'message' => 'Cấu hình GHN không hợp lệ.'];
        }

        $headers = ['Token' => $token, 'ShopId' => $shopId, 'Content-Type' => 'application/json'];
        $dimensions = [
            'weight' => (int) $params['weight'],
            'length' => (int) ($params['length'] ?? 10),
            'width' => (int) ($params['width'] ?? 10),
            'height' => (int) ($params['height'] ?? 10),
        ];
        $items = $order->items->map(fn ($item) => [
            'name' => mb_substr((string) $item->product_name, 0, 1024),
            'code' => $item->sku ? mb_substr((string) $item->sku, 0, 50) : null,
            'quantity' => (int) $item->quantity,
            'price' => (int) round((float) $item->price),
            ...$dimensions,
        ])->all();

        try {
            $client = Http::timeout(20)->retry(2, 300)->acceptJson()->withHeaders($headers);
            $services = $client->post($baseUrl.'/shiip/public-api/v2/shipping-order/available-services', [
                'shop_id' => (int) $shopId,
                'to_district' => (int) $params['to_district_id'],
            ]);
            if ($services->failed() || empty($services->json('data'))) {
                return ['success' => false, 'message' => 'GHN không có dịch vụ giao hàng cho địa chỉ này.'];
            }
            $serviceId = (int) ($params['service_id'] ?? data_get($services->json('data'), '0.service_id'));
            if ($serviceId < 1) {
                return ['success' => false, 'message' => 'Không xác định được dịch vụ GHN.'];
            }

            $common = [
                'service_id' => $serviceId,
                'to_district_id' => (int) $params['to_district_id'],
                'to_ward_code' => (string) $params['to_ward_code'],
                'insurance_value' => min((int) round((float) $order->grand_total), 5000000),
                'items' => $items,
                ...$dimensions,
            ];
            $fee = $client->post($baseUrl.'/shiip/public-api/v2/shipping-order/fee', $common);
            if ($fee->failed() || ! is_numeric($fee->json('data.total'))) {
                return ['success' => false, 'message' => 'Không thể báo phí GHN cho địa chỉ này.'];
            }

            $created = $client->post($baseUrl.'/shiip/public-api/v2/shipping-order/create', [
                ...$common,
                'payment_type_id' => $order->payment_method === 'cod' ? 2 : 1,
                'required_note' => 'KHONGCHOXEMHANG',
                'note' => $order->notes ? mb_substr((string) $order->notes, 0, 500) : null,
                'to_name' => mb_substr((string) $order->customer_name, 0, 1024),
                'to_phone' => (string) $order->customer_phone,
                'to_address' => mb_substr((string) $order->shipping_address, 0, 1024),
                'cod_amount' => $order->payment_method === 'cod' ? (int) round((float) $order->grand_total) : 0,
                'content' => 'Đơn hàng '.$order->order_number,
                'client_order_code' => $order->order_number,
            ]);
            if ($created->failed() || ! $created->json('data.order_code')) {
                Log::warning('GHN order creation failed.', ['order_id' => $order->id, 'status' => $created->status(), 'code' => $created->json('code')]);

                return ['success' => false, 'message' => 'GHN từ chối tạo vận đơn. Vui lòng kiểm tra địa chỉ và cấu hình shop.'];
            }

            return [
                'success' => true,
                'tracking_number' => (string) $created->json('data.order_code'),
                'fee' => (float) $fee->json('data.total'),
                'message' => 'Đã tạo vận đơn GHN thành công.',
            ];
        } catch (\Throwable $exception) {
            Log::error('GHN order creation failed.', ['order_id' => $order->id, 'message' => $exception->getMessage()]);

            return ['success' => false, 'message' => 'Không thể kết nối GHN. Vui lòng thử lại sau.'];
        }
    }

    /** GHN master data is proxied server-side so the browser never receives integration credentials. */
    public function ghnMasterData(string $resource, array $payload = []): array
    {
        $settings = data_get($this->getSettings(), 'ghn', []);
        $baseUrl = rtrim((string) data_get($settings, 'api_url'), '/');
        if (! data_get($settings, 'enabled') || blank(data_get($settings, 'api_token')) || blank(data_get($settings, 'shop_id')) || ! in_array($baseUrl, [self::GHN_PRODUCTION_URL, self::GHN_DEVELOPMENT_URL], true)) {
            throw new \DomainException('GHN chưa được cấu hình hoặc kích hoạt.');
        }
        try {
            $response = $this->ghnRequest($baseUrl, (string) data_get($settings, 'api_token'), (string) data_get($settings, 'shop_id'))
                ->post($baseUrl.'/shiip/public-api/master-data/'.$resource, $payload);
        } catch (ConnectionException) {
            throw new \DomainException('Không kết nối được GHN. Vui lòng thử lại sau.');
        }
        if ($response->status() === 401) {
            // Say which setting is wrong instead of a generic failure.
            throw new \DomainException($this->ghnVerify()['message']);
        }
        if ($response->failed() || ! is_array($response->json('data'))) {
            Log::warning('GHN master-data request failed.', ['resource' => $resource, 'status' => $response->status()]);
            throw new \DomainException('Không thể lấy dữ liệu địa chỉ từ GHN.');
        }

        return $response->json('data');
    }

    /**
     * Check the saved GHN settings against GHN itself: the token is valid in the chosen environment,
     * and the Shop ID belongs to that account. Production and sandbox are separate GHN systems — a
     * token from one is rejected by the other, the most common setup mistake — so on a rejected
     * token the other environment is tried to say so.
     *
     * @return array{ok: bool, status: string, message: string, shop_name?: string}
     *                                                                              status: ok | not_configured | wrong_environment | invalid_token | shop_not_found | unreachable
     */
    public function ghnVerify(): array
    {
        $settings = data_get($this->getSettings(), 'ghn', []);
        $token = trim((string) data_get($settings, 'api_token'));
        $shopId = trim((string) data_get($settings, 'shop_id'));
        $baseUrl = rtrim((string) data_get($settings, 'api_url'), '/');
        $environments = [self::GHN_PRODUCTION_URL => 'Production', self::GHN_DEVELOPMENT_URL => 'Sandbox'];

        if ($token === '' || $shopId === '' || ! isset($environments[$baseUrl])) {
            return ['ok' => false, 'status' => 'not_configured', 'message' => 'GHN chưa nhập đủ Token, Shop ID và môi trường.'];
        }

        $shops = function (string $url) use ($token) {
            return $this->ghnRequest($url, $token)->post($url.'/shiip/public-api/v2/shop/all', ['offset' => 0, 'limit' => 200]);
        };

        try {
            $response = $shops($baseUrl);
            if ($response->status() === 401) {
                $otherUrl = $baseUrl === self::GHN_PRODUCTION_URL ? self::GHN_DEVELOPMENT_URL : self::GHN_PRODUCTION_URL;
                if ($shops($otherUrl)->successful()) {
                    return ['ok' => false, 'status' => 'wrong_environment', 'message' => sprintf(
                        'Token GHN thuộc môi trường %s nhưng cài đặt đang chọn %s. Vào Đối tác vận chuyển → GHN và đổi "Môi trường API" sang %s.',
                        $environments[$otherUrl], $environments[$baseUrl], $environments[$otherUrl],
                    )];
                }

                return ['ok' => false, 'status' => 'invalid_token', 'message' => 'GHN từ chối Token (cả Production lẫn Sandbox). Kiểm tra lại Token trong trang quản lý của GHN.'];
            }
            if (! $response->successful()) {
                return ['ok' => false, 'status' => 'unreachable', 'message' => 'GHN trả lỗi '.$response->status().'. Vui lòng thử lại sau.'];
            }
        } catch (ConnectionException) {
            return ['ok' => false, 'status' => 'unreachable', 'message' => 'Không kết nối được GHN. Vui lòng thử lại sau.'];
        }

        $shop = collect((array) $response->json('data.shops'))->first(fn ($shop) => (string) data_get($shop, '_id') === $shopId);
        if ($shop === null) {
            return ['ok' => false, 'status' => 'shop_not_found', 'message' => "Token hợp lệ nhưng Shop ID {$shopId} không thuộc tài khoản GHN này (môi trường {$environments[$baseUrl]})."];
        }

        return ['ok' => true, 'status' => 'ok', 'message' => 'Kết nối GHN '.$environments[$baseUrl].' hợp lệ.', 'shop_name' => (string) data_get($shop, 'name')];
    }

    private function ghnRequest(string $baseUrl, string $token, ?string $shopId = null): PendingRequest
    {
        return Http::timeout(15)->connectTimeout(5)->acceptJson()
            ->withHeaders(array_filter(['Token' => trim($token), 'ShopId' => $shopId !== null ? trim($shopId) : null]));
    }

    /**
     * Push an order to Giao Hàng Tiết Kiệm (GHTK).
     */
    public function pushToGHTK(Order $order, array $params = []): array
    {
        $settings = $this->getSettings();
        $ghtkSettings = data_get($settings, 'ghtk');

        if (! data_get($ghtkSettings, 'enabled')) {
            return [
                'success' => false,
                'message' => 'Phương thức Giao Hàng Tiết Kiệm chưa được kích hoạt.',
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
                'tracking_number' => 'GHTK.MOCK.'.strtoupper(bin2hex(random_bytes(6))),
                'fee' => $mockFee,
                'message' => 'Đẩy đơn hàng sang GHTK thành công (Môi trường giả lập).',
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
                ],
            ];

            $response = Http::timeout(15)->retry(2, 200)->withHeaders([
                'Token' => $apiToken,
                'Content-Type' => 'application/json',
            ])->post(rtrim($apiUrl, '/').'/services/shipment/order', $payload);

            if ($response->failed()) {
                Log::error('GHTK API connection failed: '.$response->body());

                return [
                    'success' => false,
                    'message' => 'Không thể kết nối với hệ thống GHTK. Vui lòng thử lại sau.',
                ];
            }

            $data = $response->json();
            if (data_get($data, 'success')) {
                return [
                    'success' => true,
                    'tracking_number' => data_get($data, 'order.label'),
                    'fee' => (float) data_get($data, 'order.fee', 0),
                    'message' => 'Đẩy đơn hàng sang GHTK thành công.',
                ];
            }

            return [
                'success' => false,
                'message' => data_get($data, 'message', 'Có lỗi xảy ra khi đẩy đơn hàng sang GHTK.'),
            ];
        } catch (\Exception $e) {
            Log::error('GHTK push error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Lỗi kết nối dịch vụ vận chuyển. Vui lòng thử lại sau.',
            ];
        }
    }
}
