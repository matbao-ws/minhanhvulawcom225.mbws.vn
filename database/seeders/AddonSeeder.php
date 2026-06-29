<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $addons = [
            [
                'code' => 'shipping_api',
                'name' => 'Kết nối API vận chuyển',
                'price' => 500000.00,
                'description' => 'Mở khóa kết nối API đồng bộ đơn hàng với các đối tác vận chuyển lớn: SPX Express, Viettel Post, GHTK, GHN, J&T Express.',
                'is_purchased' => false,
            ],
            [
                'code' => 'vnpay',
                'name' => 'Tích hợp cổng thanh toán VNPAY',
                'price' => 1000000.00,
                'description' => 'Tích hợp cổng thanh toán VNPAY trực tuyến. Hỗ trợ khách hàng quét mã QR ngân hàng hoặc thanh toán thẻ ATM/Visa/Mastercard.',
                'is_purchased' => false,
            ],
            [
                'code' => 'sepay',
                'name' => 'Cổng thanh toán tự động Sepay',
                'price' => 800000.00,
                'description' => 'Cổng tự động nhận chuyển khoản ngân hàng qua quét QR VietQR, tự động nhận dạng giao dịch qua Webhook trong 1-3 giây.',
                'is_purchased' => false,
            ],
            [
                'code' => 'stripe',
                'name' => 'Cổng thanh toán quốc tế Stripe',
                'price' => 1500000.00,
                'description' => 'Tích hợp cổng thanh toán thẻ quốc tế Stripe dành cho khách hàng nước ngoài thanh toán bằng thẻ Visa/Master/JCB/Amex.',
                'is_purchased' => false,
            ],
        ];

        foreach ($addons as $addon) {
            Addon::query()->updateOrCreate(
                ['code' => $addon['code']],
                $addon
            );
        }
    }
}
