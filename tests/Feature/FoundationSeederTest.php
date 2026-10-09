<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Product;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_creates_only_core_data_and_not_demo_catalog_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('feature_settings', [
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);
        $this->assertSame(0, Product::query()->count());
        $this->assertTrue(FeatureSetting::query()->where('feature_code', 'inventory_log')->value('is_enabled'));
    }

    public function test_seed_creates_an_active_superadmin_on_a_fresh_install(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = $this->seededAdmin();
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->role->is_superadmin);
        $this->assertNotEmpty($admin->password);
    }

    public function test_reseeding_never_touches_an_existing_superadmin(): void
    {
        // Re-running the seed on a live shop must not hand the account back to
        // whatever ADMIN_PASSWORD currently holds — usually a stale value from some
        // developer's .env — because that locks the operator out of their own admin.
        $this->seed(DatabaseSeeder::class);

        $admin = $this->seededAdmin();
        $admin->update(['password' => 'changed-by-the-owner', 'name' => 'Nguyen Van A']);

        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('changed-by-the-owner', $admin->password));
        $this->assertSame('Nguyen Van A', $admin->name);
        $this->assertSame(1, User::query()->where('email', $admin->email)->count());
    }

    public function test_reseeding_does_not_reactivate_a_deactivated_superadmin(): void
    {
        // Deactivating the default account after creating another superadmin is a
        // deliberate act; a repair seed silently switching it back on would hand a
        // dormant login back to whoever still knows its password.
        $this->seed(DatabaseSeeder::class);

        $admin = $this->seededAdmin();
        $admin->update(['is_active' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse($admin->refresh()->is_active);
    }

    /**
     * deploy.py offers the seed again on every deploy. It used to upsert the shop's
     * settings, so a re-seed put the name, logo, contact and SEO back to the defaults
     * and undid what the owner had set up on production.
     */
    public function test_reseeding_keeps_everything_the_shop_has_changed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $setting = fn (string $key) => ProjectSetting::query()->where('setting_key', $key)->firstOrFail();
        $setting('shop_name')->update(['setting_value' => 'Cửa hàng Spico']);
        $setting('contact')->update(['setting_value' => ['phone' => '0909 000 111', 'email' => 'shop@spico.vn', 'address' => 'HCM']]);
        $setting('seo')->update(['setting_value' => ['title' => 'Spico chính hãng', 'description' => 'Mô tả']]);
        $setting('logo_url')->update(['setting_value' => 'settings/logo.png']);
        Language::query()->where('code', 'en')->update(['is_active' => false]);
        Menu::query()->where('key', 'primary')->update(['name' => 'Menu đầu trang']);
        Category::query()->where('slug', 'chua-phan-loai')->firstOrFail()->update(['name' => ['vi' => 'Khác']]);
        Role::query()->where('name', 'Admin')->firstOrFail()->update(['permissions' => ['orders.view']]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Cửa hàng Spico', $setting('shop_name')->setting_value);
        $this->assertSame('0909 000 111', $setting('contact')->setting_value['phone']);
        $this->assertSame('Spico chính hãng', $setting('seo')->setting_value['title']);
        $this->assertSame('settings/logo.png', $setting('logo_url')->setting_value);
        $this->assertFalse((bool) Language::query()->where('code', 'en')->value('is_active'));
        $this->assertSame('Menu đầu trang', Menu::query()->where('key', 'primary')->value('name'));
        $this->assertSame('Khác', Category::query()->where('slug', 'chua-phan-loai')->firstOrFail()->getTranslation('name', 'vi'));
        $this->assertSame(['orders.view'], Role::query()->where('name', 'Admin')->firstOrFail()->permissions);
    }

    public function test_reseeding_restores_foundation_rows_that_are_missing(): void
    {
        $this->seed(DatabaseSeeder::class);
        ProjectSetting::query()->where('setting_key', 'seo')->delete();
        Menu::query()->where('key', 'primary')->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('project_settings', ['setting_key' => 'seo']);
        $this->assertDatabaseHas('menus', ['key' => 'primary']);
        $this->assertTrue($this->seededAdmin()->role->is_superadmin);
    }

    private function seededAdmin(): User
    {
        // The seeder reads ADMIN_EMAIL through env(), which Laravel resolves
        // immutably from .env at boot — so the test follows the same source rather
        // than trying to override it.
        return User::query()
            ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
            ->firstOrFail();
    }
}
