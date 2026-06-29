<?php

namespace Database\Seeders;

use App\Models\FeatureSetting;
use App\Models\Package;
use App\Models\ProjectSetting;
use App\Models\ProjectSubscription;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FeatureSeeder::class,
            PackageSeeder::class,
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

        $package = Package::query()
            ->where('code', config('packages.default', 'basic_2m'))
            ->with('features')
            ->firstOrFail();

        ProjectSubscription::query()->updateOrCreate(
            ['id' => 1],
            [
                'package_id' => $package->id,
                'status' => 'active',
                'started_at' => now()->toDateString(),
                'expired_at' => null,
            ]
        );

        foreach ($package->features as $feature) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $feature->code],
                [
                    'is_enabled' => (bool) $feature->pivot->is_enabled,
                    'limit_value' => $feature->pivot->limit_value,
                    'config' => $feature->pivot->config,
                    'updated_at' => now(),
                ]
            );
        }

        $superadminRole = Role::query()->updateOrCreate(
            ['name' => 'Superadmin'],
            ['permissions' => ['*']]
        );

        $adminRole = Role::query()->updateOrCreate(
            ['name' => 'Admin'],
            ['permissions' => ['*']]
        );

        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'role_id' => $superadminRole->id,
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => env('ADMIN_PASSWORD', 'password'),
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
