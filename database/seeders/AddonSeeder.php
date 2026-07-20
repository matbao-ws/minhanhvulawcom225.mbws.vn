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
                'price' => 0,
                'description' => 'Kết nối API đồng bộ đơn hàng với các đối tác vận chuyển. Cần hỗ trợ kích hoạt hoặc cấu hình, vui lòng liên hệ bộ phận hỗ trợ.',
                'is_purchased' => true,
            ],
            [
                'code' => 'vnpay',
                'name' => 'Tích hợp cổng thanh toán VNPAY',
                'price' => 0,
                'description' => 'Tích hợp cổng thanh toán VNPAY trực tuyến. Cần hỗ trợ kích hoạt hoặc cấu hình, vui lòng liên hệ bộ phận hỗ trợ.',
                'is_purchased' => true,
            ],
            [
                'code' => 'sepay',
                'name' => 'Cổng thanh toán tự động Sepay',
                'price' => 0,
                'description' => 'Cổng tự động nhận chuyển khoản ngân hàng qua VietQR và webhook. Cần hỗ trợ kích hoạt hoặc cấu hình, vui lòng liên hệ bộ phận hỗ trợ.',
                'is_purchased' => true,
            ],
            [
                'code' => 'stripe',
                'name' => 'Cổng thanh toán quốc tế Stripe',
                'price' => 0,
                'description' => 'Tích hợp cổng thanh toán quốc tế Stripe. Cần hỗ trợ kích hoạt hoặc cấu hình, vui lòng liên hệ bộ phận hỗ trợ.',
                'is_purchased' => true,
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
