<?php

namespace Tests\Feature;

use App\Exceptions\PackageLimitExceeded;
use App\Models\Banner;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ProductImportService;
use App\Services\Catalog\ProductService;
use App\Services\MailSettings;
use App\Services\Packages\PackageLimits;
use App\Services\Packages\PackageSync;
use App\Services\Packages\PackageUsageAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class PackageLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_a_package_nothing_is_limited(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createProduct($i);
        }

        $this->assertDatabaseCount('products', 3);
        $this->assertNull(app(PackageLimits::class)->limit(PackageLimits::DATA_UNIT));
    }

    public function test_products_and_posts_share_the_data_unit_limit(): void
    {
        $this->package(['data_unit' => 2]);

        $this->createProduct(1);
        $this->createPost(1);

        $this->expectException(PackageLimitExceeded::class);
        $this->createProduct(2);
    }

    public function test_limit_also_blocks_posts(): void
    {
        $this->package(['data_unit' => 1]);
        $this->createProduct(1);

        try {
            $this->createPost(1);
            $this->fail('Bài viết vượt hạn mức vẫn được tạo.');
        } catch (PackageLimitExceeded $e) {
            $this->assertSame(PackageLimits::DATA_UNIT, $e->metric);
        }

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_csv_import_reports_rows_past_the_limit_as_failed(): void
    {
        $this->package(['data_unit' => 2]);
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "name_vi,sku,price\nSP 1,IMP-1,1000\nSP 2,IMP-2,1000\nSP 3,IMP-3,1000\n");

        $results = app(ProductImportService::class)->import($csv, 'standard');
        @unlink($csv);

        $this->assertSame(2, $results['success']);
        $this->assertSame(1, $results['failed']);
        $this->assertStringContainsString('Row 4', $results['errors'][0]);
        $this->assertDatabaseCount('products', 2);
    }

    public function test_banner_limit_blocks_the_admin_form_with_a_message(): void
    {
        $this->package(['banner' => 1]);
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'banner'], ['is_enabled' => true]);
        Storage::fake('public');
        Banner::query()->create(['title' => 'Có sẵn', 'image_path' => 'banners/a.jpg', 'position' => 'home_main', 'is_active' => true]);
        $admin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin', 'permissions' => ['*']])->id]);

        $response = $this->actingAs($admin)->from('/vi/admin/banners/create')->post('/vi/admin/banners', [
            'title' => 'Mới',
            'image_file' => UploadedFile::fake()->image('b.jpg'),
            'position' => 'home_main',
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        // Blocked by the route middleware before the controller even runs.
        $response->assertRedirect('/vi/admin/banners/create');
        $response->assertSessionHas(PackageLimitExceeded::FLASH_KEY, __('admin.package.limit_exceeded.banner', ['limit' => 1]));
        $this->assertDatabaseCount('banners', 1);
    }

    public function test_create_screens_are_blocked_with_a_dialog_once_the_limit_is_used_up(): void
    {
        $this->package(['data_unit' => 1, 'banner' => 1]);
        foreach (['catalog', 'cms_page', 'banner'] as $code) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $code], ['is_enabled' => true]);
        }
        $this->createProduct(1);
        Banner::query()->create(['title' => 'Có sẵn', 'image_path' => 'banners/a.jpg', 'position' => 'home_main', 'is_active' => true]);
        $admin = $this->admin();

        foreach ([
            ['/vi/admin/products', '/vi/admin/products/create', 'data_unit'],
            ['/vi/admin/posts', '/vi/admin/posts/create', 'data_unit'],
            ['/vi/admin/banners', '/vi/admin/banners/create', 'banner'],
        ] as [$list, $create, $metric]) {
            $message = __('admin.package.limit_exceeded.'.$metric, ['limit' => 1]);

            $this->actingAs($admin)->from($list)->get($create)
                ->assertRedirect($list)
                ->assertSessionHas(PackageLimitExceeded::FLASH_KEY, $message);

            // The list page then opens a blocking dialog, not a fading toast.
            $page = $this->actingAs($admin)->withSession([PackageLimitExceeded::FLASH_KEY => $message])->get($list);
            // @json escapes the Vietnamese text inside the script; the browser decodes it.
            $page->assertOk()
                ->assertSee(json_encode(__('admin.package.limit_reached_title')), false)
                ->assertSee(json_encode(e($message)), false)
                ->assertSee('allowOutsideClick: false', false);
        }
    }

    public function test_create_screens_open_normally_below_the_limit(): void
    {
        $this->package(['data_unit' => 5]);
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'catalog'], ['is_enabled' => true]);

        $this->actingAs($this->admin())->get('/vi/admin/products/create')->assertOk();
    }

    public function test_json_create_request_gets_a_422_with_the_message(): void
    {
        $this->package(['data_unit' => 1]);
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'catalog'], ['is_enabled' => true]);
        $this->createProduct(1);

        $this->actingAs($this->admin())->postJson('/vi/admin/products', [])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => __('admin.package.limit_exceeded.data_unit', ['limit' => 1])]);
    }

    public function test_import_past_the_limit_flashes_the_dialog(): void
    {
        $this->package(['data_unit' => 1]);
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'catalog'], ['is_enabled' => true]);

        $response = $this->actingAs($this->admin())->from('/vi/admin/products')->post('/vi/admin/products/import', [
            'import_type' => 'standard',
            'import_file' => UploadedFile::fake()->createWithContent('p.csv', "name_vi,sku,price\nSP 1,IMP-1,1000\nSP 2,IMP-2,1000\n"),
        ]);

        $response->assertRedirect('/vi/admin/products')
            ->assertSessionHas(PackageLimitExceeded::FLASH_KEY, __('admin.package.limit_exceeded.data_unit', ['limit' => 1]));
        $this->assertDatabaseCount('products', 1);
    }

    private function admin(): User
    {
        $role = Role::query()->create(['name' => 'Admin '.uniqid(), 'permissions' => ['*']]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_limit_applies_to_superadmin_too(): void
    {
        $this->package(['data_unit' => 1]);
        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->createProduct(1);

        $this->expectException(PackageLimitExceeded::class);
        $this->createProduct(2);
    }

    public function test_limits_come_only_from_the_tool_and_cannot_be_changed_in_the_admin(): void
    {
        $this->package(['data_unit' => 1]);

        $this->assertFalse(Route::has('admin.features.limits'));
        $this->assertSame(1, app(PackageLimits::class)->limit(PackageLimits::DATA_UNIT));
    }

    public function test_email_quota_is_read_from_the_relay_only_while_the_shop_uses_it(): void
    {
        // Shop on .env: no relay, no relay quota.
        $limits = app(PackageLimits::class);
        $this->assertNull($limits->limit(PackageLimits::EMAIL_MONTHLY));
        $this->assertSame(0, $limits->usage(PackageLimits::EMAIL_MONTHLY));

        $this->useSharedSmtp(used: 45, limit: 200);
        $this->assertSame(MailSettings::SOURCE_REMOTE, app(MailSettings::class)->source());
        $this->assertSame(200, $limits->limit(PackageLimits::EMAIL_MONTHLY));
        $this->assertSame(45, $limits->usage(PackageLimits::EMAIL_MONTHLY));
        $this->assertSame(155, $limits->remaining(PackageLimits::EMAIL_MONTHLY));
    }

    public function test_email_quota_is_not_counted_on_this_website(): void
    {
        // The relay enforces it; nothing here may stop or count a mail.
        $this->useSharedSmtp(used: 200, limit: 200);

        $this->assertNull(event(new MessageSending($this->email()), [], true));
        $this->assertSame(0, DB::table('package_usage_counters')->count());
    }

    public function test_dashboard_shows_package_usage(): void
    {
        $this->package(['data_unit' => 10, 'banner' => null, 'email_monthly' => null]);
        $this->createProduct(1);
        $role = Role::query()->create(['name' => 'Admin', 'permissions' => ['*']]);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->get('/vi/admin')
            ->assertOk()
            ->assertSee(__('admin.package.usage_title'))
            ->assertSee('1 / 10')
            ->assertSee('Gói test')
            ->assertSee(route('admin.dashboard.package-usage.refresh'), false);
    }

    public function test_dashboard_names_the_missing_package(): void
    {
        $this->actingAs($this->admin())->get('/vi/admin')
            ->assertOk()
            ->assertSee(__('admin.package.no_package'));
    }

    public function test_refresh_resyncs_the_package_from_the_tool(): void
    {
        $this->package(['data_unit' => 10]);
        config(['packages.domain' => 'shop.mbws.test', 'packages.info_url' => 'https://tool.test/info']);
        Http::fake(['tool.test/*' => Http::response(['retCode' => 0, 'data' => [
            'code' => 'COBAN', 'packageName' => 'Web - Nâng cấp', 'dataUnit' => 900, 'numberBanner' => 3, 'numberEmail' => 500,
        ]])]);

        $this->actingAs($this->admin())->post('/vi/admin/dashboard/package-usage/refresh')
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        Http::assertSentCount(1);
        $this->assertSame(900, app(PackageLimits::class)->limit(PackageLimits::DATA_UNIT));

        $this->actingAs($this->admin())->get('/vi/admin')->assertSee('Web - Nâng cấp');
    }

    public function test_refresh_reports_an_unreachable_tool_without_changing_the_package(): void
    {
        $this->package(['data_unit' => 10]);
        config(['packages.domain' => 'shop.mbws.test', 'packages.info_url' => 'https://tool.test/info']);
        Http::fake(['tool.test/*' => Http::response('down', 503)]);

        $this->actingAs($this->admin())->post('/vi/admin/dashboard/package-usage/refresh')
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error', __('admin.package.unreachable'));

        $this->assertSame(10, app(PackageLimits::class)->limit(PackageLimits::DATA_UNIT));
    }

    public function test_package_usage_alerts_report_near_and_exhausted_limits(): void
    {
        $this->package(['data_unit' => 5, 'banner' => 1]);
        $this->createProduct(1);
        $this->createProduct(2);
        $this->createProduct(3);
        $this->createProduct(4);
        Banner::query()->create(['title' => 'At limit', 'image_path' => 'banners/a.jpg', 'position' => 'home_main', 'is_active' => true]);

        $alerts = app(PackageUsageAlerts::class)->forAdmin();

        $this->assertCount(2, $alerts);
        $this->assertSame('warning', $alerts[0]['level']);
        $this->assertStringContainsString('4/5', $alerts[0]['message']);
        $this->assertSame('danger', $alerts[1]['level']);
        $this->assertStringContainsString('1/1', $alerts[1]['message']);
    }

    public function test_dashboard_shows_usage_and_storage_without_a_package(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/a.jpg', str_repeat('x', 3 * 1024 * 1024));
        $this->createProduct(1);
        $role = Role::query()->create(['name' => 'Admin', 'permissions' => ['*']]);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->get('/vi/admin')
            ->assertOk()
            ->assertSee(__('admin.package.metric.data_unit'))
            ->assertSee('1 / '.__('admin.package.unlimited'))
            ->assertSee(__('admin.package.metric.storage'))
            ->assertSee('3 MB');
    }

    /**
     * @param  array<string, int|null>  $limits
     */
    private function package(array $limits): void
    {
        ProjectSetting::query()->updateOrCreate(
            ['setting_key' => PackageSync::SETTING_KEY],
            ['setting_value' => [
                'code' => 'TEST',
                'name' => 'Gói test',
                'limits' => array_merge(['data_unit' => null, 'banner' => null, 'email_monthly' => null], $limits),
                'details' => [],
                'plan_known' => true,
                'synced_at' => now()->toIso8601String(),
            ]],
        );

        Cache::flush();
    }

    private function createProduct(int $n): Product
    {
        return app(ProductService::class)->create([
            'name' => ['vi' => "Sản phẩm {$n}"],
            'sku' => "LIMIT-{$n}",
            'price' => 1000,
        ]);
    }

    private function createPost(int $n): Post
    {
        return Post::query()->create([
            'title' => ['vi' => "Bài {$n}"],
            'slug' => "bai-{$n}",
            'content' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
        ]);
    }

    private function useSharedSmtp(int $used = 0, int $limit = 200): void
    {
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_test']);
        MailSettings::flush();
        Cache::forget(PackageLimits::EMAIL_USAGE_CACHE_KEY);
        Http::fake(['gw.test/v1/mail/usage' => Http::response(['ok' => true, 'usage' => ['used' => $used, 'limit' => $limit]])]);
    }

    private function email(): Email
    {
        return (new Email)->from('a@mail.relay.test')->to('b@example.com')->subject('Hi')->text('Hi');
    }
}
