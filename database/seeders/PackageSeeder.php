<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Package;
use App\Models\PackageFeature;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            'basic_2m' => [
                'package' => [
                    'name' => 'Basic 2M',
                    'price' => 2000000,
                    'description' => 'Basic ecommerce package.',
                    'is_active' => true,
                ],
                'features' => [
                    'catalog' => true,
                    'cart' => true,
                    'cod_order' => true,
                    'online_payment' => false,
                    'voucher' => false,
                    'review' => false,
                    'zalo_oa' => false,
                    'cms_page' => false,
                    'banner' => true,
                    'menu' => true,
                    'multi_admin' => false,
                    'inventory_log' => false,
                    'max_products' => 50,
                    'max_admin_users' => 1,
                ],
            ],
            'standard_4m' => [
                'package' => [
                    'name' => 'Standard 4M',
                    'price' => 4000000,
                    'description' => 'Standard ecommerce package.',
                    'is_active' => true,
                ],
                'features' => [
                    'catalog' => true,
                    'cart' => true,
                    'cod_order' => true,
                    'online_payment' => true,
                    'voucher' => true,
                    'review' => true,
                    'zalo_oa' => true,
                    'cms_page' => true,
                    'banner' => true,
                    'menu' => true,
                    'multi_admin' => true,
                    'inventory_log' => true,
                    'max_products' => 200,
                    'max_admin_users' => 3,
                ],
            ],
        ];

        $features = Feature::query()->get()->keyBy('code');

        foreach ($packages as $code => $preset) {
            $package = Package::query()->updateOrCreate(
                ['code' => $code],
                $preset['package'] + ['code' => $code]
            );

            foreach ($features as $featureCode => $feature) {
                $value = $preset['features'][$featureCode] ?? false;

                PackageFeature::query()->updateOrCreate(
                    [
                        'package_id' => $package->id,
                        'feature_id' => $feature->id,
                    ],
                    [
                        'is_enabled' => is_bool($value) ? $value : true,
                        'limit_value' => is_bool($value) ? null : (string) $value,
                        'config' => null,
                    ]
                );
            }
        }
    }
}
