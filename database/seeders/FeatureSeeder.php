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
            ['code' => 'max_products', 'name' => 'Max Products', 'value_type' => 'number'],
            ['code' => 'max_admin_users', 'name' => 'Max Admin Users', 'value_type' => 'number'],
        ];

        foreach ($features as $feature) {
            Feature::query()->updateOrCreate(
                ['code' => $feature['code']],
                $feature
            );
        }
    }
}
