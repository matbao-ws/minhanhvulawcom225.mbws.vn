<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Voucher::truncate();

        Voucher::create([
            'code' => 'WINTER10',
            'name' => [
                'vi' => 'Khuyến mãi mùa đông 10%',
                'en' => 'Winter Promotion 10%',
            ],
            'description' => [
                'vi' => 'Giảm 10% cho tất cả đơn hàng từ 200k',
                'en' => '10% discount for orders from 200k',
            ],
            'type' => 'percentage',
            'value' => 10.00,
            'min_order_amount' => 200000.00,
            'max_discount_amount' => 50000.00,
            'quantity' => 100,
            'used_count' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ]);

        Voucher::create([
            'code' => 'FREESHIP',
            'name' => [
                'vi' => 'Miễn phí vận chuyển',
                'en' => 'Free Shipping',
            ],
            'description' => [
                'vi' => 'Miễn phí vận chuyển tối đa 30k',
                'en' => 'Free shipping up to 30k',
            ],
            'type' => 'fixed',
            'value' => 30000.00,
            'min_order_amount' => 150000.00,
            'max_discount_amount' => null,
            'quantity' => 500,
            'used_count' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ]);

        Voucher::create([
            'code' => 'FIXED50',
            'name' => [
                'vi' => 'Giảm giá 50k',
                'en' => '50k Fixed Discount',
            ],
            'description' => [
                'vi' => 'Giảm ngay 50k cho đơn hàng từ 500k',
                'en' => 'Get 50k off for orders from 500k',
            ],
            'type' => 'fixed',
            'value' => 50000.00,
            'min_order_amount' => 500000.00,
            'max_discount_amount' => null,
            'quantity' => 50,
            'used_count' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ]);
    }
}
