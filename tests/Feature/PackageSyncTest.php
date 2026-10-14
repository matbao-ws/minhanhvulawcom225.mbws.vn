<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Packages\PackageInfo;
use App\Services\Packages\PackageSync;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageSyncTest extends TestCase
{
    use RefreshDatabase;

    /** What the fake tool answers; swapped per test step. */
    private mixed $toolResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        // One fake for the whole test: a second Http::fake() would not replace
        // the first stub, so steps swap $toolResponse instead.
        Http::fake(['tool.test/*' => fn () => $this->toolResponse]);

        config([
            'packages.domain' => 'shop123.mbws.test',
            'packages.info_url' => 'https://tool.test/get-package-info.php',
            'packages.plans' => [
                'BASIC' => ['features' => ['catalog', 'cart', 'banner']],
                'FULL' => ['features' => '*'],
            ],
        ]);

        foreach (config('features.codes') as $code) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $code], ['is_enabled' => true]);
        }
    }

    public function test_package_sync_migration_can_rerun_after_a_partial_run(): void
    {
        // Staging incident: two migrate runs raced and the second hit "Duplicate column".
        $migration = require database_path('migrations/2026_09_30_100000_add_package_sync_to_feature_settings.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('feature_settings', ['package_enabled', 'is_overridden']));
        $this->assertTrue(Schema::hasTable('package_usage_counters'));
        $this->assertSame(1, FeatureSetting::query()->where('feature_code', 'payment_vnpay')->count());
    }

    public function test_sync_switches_features_to_the_package(): void
    {
        $this->fakeTool('BASIC');

        $result = app(PackageSync::class)->sync();

        $this->assertSame(PackageInfo::OK, $result['status']);
        $gate = app(FeatureGate::class);
        $this->assertTrue($gate->enabled('catalog'));
        $this->assertTrue($gate->enabled('banner'));
        $this->assertFalse($gate->enabled('voucher'));
        $this->assertFalse($gate->enabled('multilingual'));
        $this->assertArrayHasKey('voucher', $result['changes']);

        $snapshot = app(PackageSync::class)->snapshot();
        $this->assertSame('BASIC', $snapshot['code']);
        // numberBanner (the banner design service) is not read: it is no limit of the core.
        $this->assertSame(['data_unit' => 500, 'email_monthly' => 200], $snapshot['limits']);
        $this->assertTrue($snapshot['plan_known']);

        Http::assertSent(fn ($request) => $request->url() === 'https://tool.test/get-package-info.php/shop123.mbws.test');
    }

    public function test_wildcard_plan_enables_every_feature(): void
    {
        FeatureSetting::query()->update(['is_enabled' => false]);
        $this->fakeTool('FULL');

        app(PackageSync::class)->sync();

        $this->assertSame(0, FeatureSetting::query()->where('is_enabled', false)->count());
    }

    public function test_superadmin_override_survives_the_next_sync(): void
    {
        $this->fakeTool('BASIC');
        $sync = app(PackageSync::class);
        $sync->sync();

        // Support switches voucher on for this shop although BASIC does not include it.
        $sync->setByAdmin(FeatureSetting::query()->where('feature_code', 'voucher')->firstOrFail(), true);
        $this->assertDatabaseHas('feature_settings', ['feature_code' => 'voucher', 'is_enabled' => true, 'is_overridden' => true]);

        PackageSync::forgetChecked();
        $sync->sync();

        $this->assertDatabaseHas('feature_settings', ['feature_code' => 'voucher', 'is_enabled' => true, 'is_overridden' => true, 'package_enabled' => false]);
    }

    public function test_choice_that_matches_the_package_is_not_an_override(): void
    {
        $this->fakeTool('BASIC');
        $sync = app(PackageSync::class);
        $sync->sync();

        $setting = FeatureSetting::query()->where('feature_code', 'catalog')->firstOrFail();
        $sync->setByAdmin($setting, false);
        $this->assertTrue($setting->fresh()->is_overridden);

        $sync->setByAdmin($setting->fresh(), true);
        $this->assertFalse($setting->fresh()->is_overridden);
    }

    public function test_reset_returns_overridden_features_to_the_package(): void
    {
        $this->fakeTool('BASIC');
        $sync = app(PackageSync::class);
        $sync->sync();
        $sync->setByAdmin(FeatureSetting::query()->where('feature_code', 'voucher')->firstOrFail(), true);

        $sync->resetOverrides(['voucher']);

        $this->assertDatabaseHas('feature_settings', ['feature_code' => 'voucher', 'is_enabled' => false, 'is_overridden' => false]);
    }

    public function test_tool_failures_never_change_features(): void
    {
        foreach ([
            Http::response(['retCode' => 1, 'retText' => 'Không tìm thấy dịch vụ hoặc dự án', 'data' => null]),
            Http::response('gateway down', 502),
            Http::response(['retCode' => 0, 'data' => ['packageName' => 'no code']]),
        ] as $response) {
            $this->toolResponse = $response;

            $result = app(PackageSync::class)->sync();

            $this->assertNotSame(PackageInfo::OK, $result['status']);
            $this->assertSame(0, FeatureSetting::query()->where('is_enabled', false)->count());
            $this->assertNull(app(PackageSync::class)->snapshot());
        }
    }

    public function test_last_synced_package_stays_when_the_tool_goes_down(): void
    {
        $this->fakeTool('BASIC');
        app(PackageSync::class)->sync();

        $this->toolResponse = Http::response('down', 503);
        $result = app(PackageSync::class)->sync();

        $this->assertSame(PackageInfo::UNREACHABLE, $result['status']);
        $this->assertSame('BASIC', app(PackageSync::class)->snapshot()['code']);
        $this->assertFalse(app(FeatureGate::class)->enabled('voucher'));
    }

    public function test_unknown_package_code_keeps_features_but_stores_limits(): void
    {
        $this->fakeTool('SOMETHING_NEW');

        $result = app(PackageSync::class)->sync();

        $this->assertSame(PackageInfo::OK, $result['status']);
        $this->assertFalse($result['package']['plan_known']);
        $this->assertSame([], $result['changes']);
        $this->assertSame(0, FeatureSetting::query()->where('is_enabled', false)->count());
        $this->assertSame(500, app(PackageSync::class)->snapshot()['limits']['data_unit']);
    }

    public function test_without_a_domain_nothing_is_fetched(): void
    {
        config(['packages.domain' => null]);
        Http::fake();

        app(PackageSync::class)->syncIfStale();
        $this->artisan('package:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_sync_if_stale_fetches_once_per_ttl(): void
    {
        $this->fakeTool('BASIC');

        app(PackageSync::class)->syncIfStale();
        app(PackageSync::class)->syncIfStale();

        Http::assertSentCount(1);
    }

    public function test_admin_requests_sync_the_package_lazily(): void
    {
        $this->fakeTool('BASIC');
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/vi/admin/features')->assertOk();

        Http::assertSentCount(1);
        $this->assertFalse(app(FeatureGate::class)->enabled('voucher'));
    }

    public function test_command_reports_package_and_limits(): void
    {
        $this->fakeTool('BASIC');

        $this->artisan('package:sync')
            ->expectsOutputToContain('BASIC')
            ->expectsOutputToContain('500')
            ->assertSuccessful();

        $this->toolResponse = Http::response(['retCode' => 1, 'data' => null]);
        $this->artisan('package:sync')->assertFailed();
    }

    public function test_every_plan_in_the_shipped_config_uses_known_feature_codes(): void
    {
        $plans = require config_path('packages.php');

        foreach ($plans['plans'] as $code => $plan) {
            if ($plan['features'] === '*') {
                continue;
            }

            $this->assertSame([], array_diff($plan['features'], config('features.codes')), "Gói {$code} dùng feature code không tồn tại");
        }
    }

    public function test_features_page_allows_superadmin_to_reset_feature_overrides(): void
    {
        $this->fakeTool('BASIC');
        app(PackageSync::class)->sync();
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/vi/admin/features')
            ->assertOk()
            ->assertSee(route('admin.features.sync-package'), false);

        $this->actingAs($admin)->postJson('/vi/admin/features/toggle', ['feature_code' => 'voucher', 'is_enabled' => true])
            ->assertOk()
            ->assertJson(['is_overridden' => true]);

        $this->actingAs($admin)->post('/vi/admin/features/reset-overrides', ['feature_code' => 'voucher'])
            ->assertRedirect('/vi/admin/features');
        $this->assertFalse(app(FeatureGate::class)->enabled('voucher'));

        // Package limits live on the dashboard and are never editable from features.
        $this->actingAs($admin)->get('/vi/admin/features')
            ->assertDontSee('name="limits[', false);
    }

    public function test_standard_admin_cannot_sync_the_package(): void
    {
        $role = Role::query()->create(['name' => 'Admin', 'permissions' => ['*']]);
        $admin = User::factory()->create(['role_id' => $role->id]);
        Http::fake();

        $this->actingAs($admin)->post('/vi/admin/features/sync-package')->assertStatus(403);
    }

    private function superadmin(): User
    {
        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function fakeTool(string $code): void
    {
        Cache::flush();

        $this->toolResponse = Http::response([
            'retCode' => 0,
            'retText' => 'Ok',
            'data' => [
                'id' => 4,
                'code' => $code,
                'packageName' => 'Web - Cơ bản',
                'dataUnit' => 500,
                'numberLayouts' => 25,
                'interfaceType' => $code,
                'price' => 199000,
                'numberData' => 40,
                'numberBanner' => 1,
                'numberPrimaryColor' => 1,
                'numberChangePageLayout' => 1,
                'numberEmail' => 200,
            ],
        ]);
    }
}
