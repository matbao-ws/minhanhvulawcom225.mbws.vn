<?php

namespace Tests\Feature;

use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Packages\PackageSync;
use App\Support\Deploy\ContentTables;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A first deploy can carry the content a developer entered locally (content:export on the
 * machine, content:import on the website after migrate + seed). One in-memory database plays
 * both: filled like a developer's, exported, wiped back to a freshly seeded website, imported.
 */
class ContentTransferTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = sys_get_temp_dir().'/content-'.uniqid().'.json.gz';
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function test_content_travels_and_people_secrets_and_test_usage_do_not(): void
    {
        $developer = User::factory()->create(['role_id' => Role::query()->where('name', 'Admin')->value('id')]);
        $this->fillLikeADeveloper($developer);

        $this->artisan('content:export', ['file' => $this->file])->expectsOutputToContain('CONTENT_EXPORT_OK')->assertSuccessful();
        $this->becomeAFreshlySeededWebsite();

        $this->artisan('content:import', ['file' => $this->file])->expectsOutputToContain('CONTENT_IMPORT_OK')->assertSuccessful();

        // Content, with its ids and relations.
        $this->assertDatabaseHas('products', ['sku' => 'SPICO-01', 'stock_quantity' => 12]);
        $this->assertDatabaseHas('posts', ['slug' => 'bai-viet-dau-tien']);
        $this->assertDatabaseHas('banners', ['id' => DB::table('banners')->value('id')]);
        $this->assertSame('Cửa hàng Spico', ProjectSetting::query()->where('setting_key', 'shop_name')->value('setting_value'));
        // Usage counted by the developer's test orders starts over.
        $this->assertSame(0, (int) DB::table('vouchers')->where('code', 'SPICO10')->value('used_count'));
        // A revision written by the developer's account no longer points at an account.
        $this->assertNull(DB::table('page_revisions')->value('created_by'));

        // Never people, orders or secrets.
        $this->assertDatabaseMissing('users', ['email' => $developer->email]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull(ProjectSetting::query()->where('setting_key', 'notification_settings')->first());
        // The website's own superadmin is still the only account.
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_website_that_is_no_longer_new_is_refused_untouched(): void
    {
        $this->fillLikeADeveloper(null);
        $this->artisan('content:export', ['file' => $this->file])->assertSuccessful();
        ProjectSetting::query()->where('setting_key', 'shop_name')->firstOrFail()->update(['setting_value' => 'Shop đang bán']);

        $this->artisan('content:import', ['file' => $this->file])
            ->expectsOutputToContain('CONTENT_IMPORT_REFUSED: not_new')
            ->assertFailed();

        $this->assertSame('Shop đang bán', ProjectSetting::query()->where('setting_key', 'shop_name')->value('setting_value'));
    }

    public function test_a_website_with_accounts_is_refused(): void
    {
        $this->artisan('content:export', ['file' => $this->file])->assertSuccessful();
        User::factory()->create(['role_id' => null]);

        $this->artisan('content:import', ['file' => $this->file])->expectsOutputToContain('not_new')->assertFailed();
    }

    public function test_an_export_from_another_core_version_is_refused(): void
    {
        $this->artisan('content:export', ['file' => $this->file])->assertSuccessful();
        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_from_a_newer_core', 'batch' => 99]);

        $this->artisan('content:import', ['file' => $this->file])->expectsOutputToContain('schema_mismatch')->assertFailed();
    }

    public function test_an_export_beyond_the_package_is_refused(): void
    {
        $this->fillLikeADeveloper(null);
        $this->artisan('content:export', ['file' => $this->file])->assertSuccessful();
        $this->becomeAFreshlySeededWebsite();
        ProjectSetting::query()->create(['setting_key' => PackageSync::SETTING_KEY, 'setting_value' => ['code' => 'HERO1', 'limits' => ['data_unit' => 1]]]);
        Cache::flush(); // the snapshot is cached; the test cache is in memory

        $this->artisan('content:import', ['file' => $this->file])->expectsOutputToContain('package_limit')->assertFailed();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_an_export_is_refused_when_the_package_is_required_but_missing(): void
    {
        $this->fillLikeADeveloper(null);
        $this->artisan('content:export', ['file' => $this->file])->assertSuccessful();
        $this->becomeAFreshlySeededWebsite();
        config(['packages.required' => true]);
        Cache::flush();

        $this->artisan('content:import', ['file' => $this->file])->expectsOutputToContain('package_missing')->assertFailed();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_a_corrupt_file_is_refused(): void
    {
        file_put_contents($this->file, 'not gzip');

        $this->artisan('content:import', ['file' => $this->file])->expectsOutputToContain('invalid_file')->assertFailed();
    }

    private function fillLikeADeveloper(?User $developer): void
    {
        ProjectSetting::query()->where('setting_key', 'shop_name')->firstOrFail()->update(['setting_value' => 'Cửa hàng Spico']);
        ProjectSetting::query()->create(['setting_key' => 'notification_settings', 'setting_value' => ['smtp' => ['password' => 'local-secret']]]);

        $categoryId = DB::table('categories')->value('id');
        $productId = DB::table('products')->insertGetId([
            'name' => json_encode(['vi' => 'Áo Spico']), 'slug' => 'ao-spico', 'sku' => 'SPICO-01', 'price' => 100000,
            'stock_quantity' => 12, 'manage_stock' => true, 'is_active' => true, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('posts')->insert(['title' => json_encode(['vi' => 'Bài viết đầu tiên']), 'slug' => 'bai-viet-dau-tien', 'content' => json_encode(['vi' => '<p>x</p>']), 'created_at' => now(), 'updated_at' => now()]);
        $pageId = DB::table('pages')->insertGetId(['title' => json_encode(['vi' => 'Chính sách']), 'slug' => json_encode(['vi' => 'chinh-sach']), 'published_html' => json_encode(['vi' => '<p>a</p>']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('page_revisions')->insert(['page_id' => $pageId, 'created_by' => $developer?->id, 'published_html' => json_encode(['vi' => '<p>a</p>']), 'created_at' => now()]);
        DB::table('banners')->insert(['title' => 'Banner', 'image_path' => 'banners/a.jpg', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('vouchers')->insert(['code' => 'SPICO10', 'name' => json_encode(['vi' => 'Giảm 10']), 'type' => 'fixed', 'value' => 10000, 'min_order_amount' => 0, 'used_count' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        // The developer's test order and its customer must not travel.
        $customer = User::factory()->create(['role_id' => null]);
        DB::table('orders')->insert([
            'order_number' => 'ORD-LOCALTEST1', 'user_id' => $customer->id, 'customer_name' => 'Test', 'customer_email' => 'test@example.com',
            'customer_phone' => '0900000000', 'shipping_address' => 'HCM', 'payment_method' => 'cod', 'payment_status' => 'pending',
            'status' => 'pending', 'subtotal' => 100000, 'discount' => 0, 'grand_total' => 100000, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** What production looks like after migrate + seed: no content, no orders, one superadmin. */
    private function becomeAFreshlySeededWebsite(): void
    {
        DB::table('orders')->delete();
        foreach (array_reverse(ContentTables::TABLES) as $table) {
            DB::table($table)->delete();
        }
        $superadminRole = Role::query()->where('name', 'Superadmin')->value('id');
        User::query()->where(fn ($q) => $q->whereNull('role_id')->orWhere('role_id', '!=', $superadminRole))->delete();
        DB::table('project_settings')->delete();

        $this->seed(DatabaseSeeder::class);
    }
}
