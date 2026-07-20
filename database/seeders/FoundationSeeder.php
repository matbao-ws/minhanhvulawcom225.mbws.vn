<?php

namespace Database\Seeders;

use App\Models\FeatureSetting;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FeatureSeeder::class,
            PermissionSeeder::class,
            AddonSeeder::class,
        ]);

        \App\Models\Category::query()->updateOrCreate(
            ['slug' => 'chua-phan-loai'],
            [
                'name' => [
                    'vi' => 'Chưa phân loại',
                    'en' => 'Uncategorized',
                ],
                'description' => [
                    'vi' => 'Danh mục mặc định cho các sản phẩm chưa được phân loại.',
                    'en' => 'Default category for uncategorized products.',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        foreach (config('features.codes', []) as $featureCode) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                [
                    'is_enabled' => true,
                    'limit_value' => null,
                    'config' => null,
                    'updated_at' => now(),
                ]
            );
        }

        $superadminRole = Role::query()->updateOrCreate(
            ['name' => 'Superadmin'],
            ['permissions' => ['*'], 'is_system' => true]
        );

        $adminRole = Role::query()->updateOrCreate(
            ['name' => 'Admin'],
            ['permissions' => ['*']]
        );

        $adminPassword = env('ADMIN_PASSWORD');
        if (blank($adminPassword) && app()->environment('production')) {
            throw new RuntimeException('ADMIN_PASSWORD must be set before running the production seed.');
        }

        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'role_id' => $superadminRole->id,
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => $adminPassword ?: 'password',
                'is_active' => true,
            ]
        );

        $settings = [
            'shop_name' => 'Laravel Ecommerce Core',
            'logo_url' => null,
            'favicon_url' => null,
            'contact' => [
                'phone' => null,
                'email' => null,
                'address' => null,
            ],
            'theme' => [
                'primary_color' => '#0d6efd',
                'layout' => 'default',
            ],
            'seo' => [
                'title' => 'Laravel Ecommerce Core',
                'description' => null,
            ],
            'social_links' => [],
        ];

        foreach ($settings as $key => $value) {
            ProjectSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
