<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['code' => 'catalog', 'name' => 'Catalog', 'value_type' => 'boolean'],
            ['code' => 'cart', 'name' => 'Cart', 'value_type' => 'boolean'],
            ['code' => 'cod_order', 'name' => 'COD Order', 'value_type' => 'boolean'],
            ['code' => 'online_payment', 'name' => 'Online Payment', 'value_type' => 'boolean'],
            ['code' => 'voucher', 'name' => 'Voucher', 'value_type' => 'boolean'],
            ['code' => 'review', 'name' => 'Review', 'value_type' => 'boolean'],
            ['code' => 'zalo_oa', 'name' => 'Zalo OA', 'value_type' => 'boolean'],
            ['code' => 'cms_page', 'name' => 'CMS Page', 'value_type' => 'boolean'],
            ['code' => 'banner', 'name' => 'Banner', 'value_type' => 'boolean'],
            ['code' => 'menu', 'name' => 'Menu', 'value_type' => 'boolean'],
            ['code' => 'multi_admin', 'name' => 'Multi Admin', 'value_type' => 'boolean'],
            ['code' => 'inventory_log', 'name' => 'Inventory Log', 'value_type' => 'boolean'],
            ['code' => 'shipping', 'name' => 'Shipping', 'value_type' => 'boolean'],
            ['code' => 'product_variant', 'name' => 'Product Variant', 'value_type' => 'boolean'],
            ['code' => 'payment_vnpay', 'name' => 'VNPAY Payment', 'value_type' => 'boolean'],
            ['code' => 'multilingual', 'name' => 'Multilingual', 'value_type' => 'boolean'],
            ['code' => 'analytics', 'name' => 'Analytics', 'value_type' => 'boolean'],
            ['code' => 'ai_seo', 'name' => 'AI SEO Copilot', 'value_type' => 'boolean'],
            ['code' => 'ai_research', 'name' => 'Keyword Research', 'value_type' => 'boolean'],
        ];

        foreach ($features as $feature) {
            Feature::query()->updateOrCreate(
                ['code' => $feature['code']],
                $feature
            );
        }
    }
}
