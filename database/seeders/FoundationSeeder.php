<?php

namespace Database\Seeders;

use App\Models\FeatureSetting;
use App\Models\ProjectSetting;
use App\Support\PermissionRegistry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Illuminate\Support\Str;

/**
 * The rows a shop cannot run without: features, permissions, the superadmin, default roles,
 * settings, languages, the primary menu and the fallback category.
 *
 * Create-only. deploy.py offers this seed again on every deploy, and a live shop has edited these
 * rows by then: upserting put the shop's name, logo, contact, SEO, languages, menu name and the
 * Admin role's permissions back to the defaults. The seeder guarantees each row exists; once it
 * does, its values belong to the shop.
 */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FeatureSeeder::class,
            PermissionSeeder::class,
        ]);

        \App\Models\Category::query()->firstOrCreate(
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

        // Create-only: this seeder is re-run on live shops, and switching every
        // feature back on would undo both the package and support's choices.
        foreach (config('features.codes', []) as $featureCode) {
            FeatureSetting::query()->firstOrCreate(
                ['feature_code' => $featureCode],
                [
                    'is_enabled' => true,
                    'limit_value' => null,
                    'config' => null,
                    'updated_at' => now(),
                ]
            );
        }

        // With PACKAGE_INFO_DOMAIN set, a fresh install starts on its package.
        if (app(\App\Services\Packages\PackageInfo::class)->configured()) {
            $result = app(\App\Services\Packages\PackageSync::class)->sync();
            $this->command?->info('Package sync: '.$result['status'].($result['package'] ? ' ('.$result['package']['code'].')' : ''));
        }

        $superadminRole = Role::query()->firstOrCreate(
            ['name' => 'Superadmin'],
            ['permissions' => ['*'], 'is_system' => true]
        );
        // The one repair kept: this role is the superadmin by definition, and losing the flag
        // would lock the owner out of permission management.
        if (! $superadminRole->is_superadmin || ! $superadminRole->is_system) {
            $superadminRole->update(['permissions' => ['*'], 'is_system' => true]);
        }

        // Everything except permission management and language configuration,
        // which stay with the superadmin.
        // The owner may narrow or widen it afterwards; a re-seed must not undo that.
        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'Admin'],
            [
                'permissions' => array_values(array_filter(
                    PermissionRegistry::codes(),
                    static fn (string $code): bool => ! str_starts_with($code, 'roles.')
                        && ! str_starts_with($code, 'languages.'),
                )),
                'is_system' => false,
            ]
        );

        // The client layout asks for the `primary` menu, so the install always
        // has one to fill in rather than a missing key.
        \App\Models\Menu::query()->firstOrCreate(
            ['key' => 'primary'],
            ['name' => 'Menu chính', 'is_active' => true],
        );

        // Create-only, deliberately.
        //
        // This seeder is re-run on existing shops (a repair, a deploy that ticks the
        // seed option), and the account it targets is a live login by then. Upserting
        // it reset the operator's password back to whatever ADMIN_PASSWORD happened to
        // hold — usually a stale value from a developer's .env — and locked them out
        // of their own admin. Its job is to guarantee a superadmin exists on a fresh
        // install, not to own that account's credentials or profile forever after.
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');

        if (User::query()->where('email', $adminEmail)->exists()) {
            $this->command?->info("Superadmin {$adminEmail} đã tồn tại; giữ nguyên mật khẩu và hồ sơ.");
        } else {
            $adminPassword = env('ADMIN_PASSWORD');
            if (blank($adminPassword) && ! app()->environment('testing')) {
                throw new RuntimeException('ADMIN_PASSWORD must be set before running the foundation seed.');
            }

            User::query()->create([
                'email' => $adminEmail,
                'role_id' => $superadminRole->id,
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => $adminPassword ?: Str::random(40),
                'is_active' => true,
            ]);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('languages')) {
            \App\Models\Language::query()->firstOrCreate(
                ['code' => 'vi'],
                ['name' => 'Vietnamese', 'native_name' => 'Tiếng Việt', 'regional' => 'vi_VN', 'flag_path' => 'admin-assets/images/flag/Flag_of_Vietnam.svg.png', 'is_active' => true, 'is_default' => true, 'is_content_fallback' => true, 'sort_order' => 0],
            );
            \App\Models\Language::query()->firstOrCreate(
                ['code' => 'en'],
                ['name' => 'English', 'native_name' => 'English', 'regional' => 'en_US', 'flag_path' => 'admin-assets/images/flag/icon-flag-en.svg', 'is_active' => true, 'is_default' => false, 'is_content_fallback' => false, 'sort_order' => 10],
            );
        }

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
            'multilingual' => [
                'enabled' => true,
                'mode' => 'manual',
                'gtranslate' => [
                    'target_locales' => ['en'],
                    'widget_look' => 'float',
                    'position' => 'bottom_right',
                    'detect_browser_language' => false,
                    'native_language_names' => true,
                ],
            ],
        ];

        foreach ($settings as $key => $value) {
            ProjectSetting::query()->firstOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
