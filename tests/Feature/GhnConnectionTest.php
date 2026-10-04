<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\ShippingPartner;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GHN settings checked against GHN itself, so a wrong environment, token or Shop ID is named
 * instead of a generic "cannot authenticate" — and the admin checkout test page built on it.
 */
class GhnConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const PROD = 'https://online-gateway.ghn.vn';

    private const DEV = 'https://dev-online-gateway.ghn.vn';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'shipping'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
    }

    public function test_a_production_token_on_the_sandbox_setting_is_named(): void
    {
        $this->ghn(self::DEV);
        Http::fake([
            self::DEV.'/*' => Http::response(['code' => 401, 'message' => 'Token is not valid!'], 401),
            self::PROD.'/shiip/public-api/v2/shop/all' => Http::response(['data' => ['shops' => [['_id' => 5300247, 'name' => 'Shop A']]]]),
        ]);

        $check = app(ShippingService::class)->ghnVerify();

        $this->assertSame('wrong_environment', $check['status']);
        $this->assertStringContainsString('thuộc môi trường Production nhưng cài đặt đang chọn Sandbox', $check['message']);
    }

    public function test_token_rejected_everywhere_shop_of_another_account_and_a_working_setup(): void
    {
        $this->ghn(self::PROD);
        Http::fake(['*' => Http::response(['code' => 401, 'message' => 'Token is not valid!'], 401)]);
        $this->assertSame('invalid_token', app(ShippingService::class)->ghnVerify()['status']);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([self::PROD.'/shiip/public-api/v2/shop/all' => Http::response(['data' => ['shops' => [['_id' => 111, 'name' => 'Other']]]])]);
        $this->assertSame('shop_not_found', app(ShippingService::class)->ghnVerify()['status']);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([self::PROD.'/shiip/public-api/v2/shop/all' => Http::response(['data' => ['shops' => [['_id' => 5300247, 'name' => 'Shop A']]]])]);
        $check = app(ShippingService::class)->ghnVerify();
        $this->assertTrue($check['ok']);
        $this->assertSame('Shop A', $check['shop_name']);
        Http::assertSent(fn ($request) => $request->hasHeader('Token', 'prod-token'));
    }

    public function test_address_lookup_reports_the_real_cause(): void
    {
        $this->ghn(self::DEV);
        Http::fake([
            self::DEV.'/*' => Http::response(['code' => 401, 'message' => 'Token is not valid!'], 401),
            self::PROD.'/*' => Http::response(['data' => ['shops' => [['_id' => 5300247]]]]),
        ]);

        $this->actingAs($this->admin())->getJson('/vi/admin/shipping/ghn/provinces')
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'đổi "Môi trường API" sang Production'));
    }

    public function test_address_lookup_follows_province_district_ward(): void
    {
        $this->ghn(self::PROD);
        Http::fake([
            self::PROD.'/shiip/public-api/master-data/province' => Http::response(['data' => [['ProvinceID' => 202, 'ProvinceName' => 'Hồ Chí Minh']]]),
            self::PROD.'/shiip/public-api/master-data/district' => Http::response(['data' => [['DistrictID' => 1442, 'DistrictName' => 'Quận 1']]]),
            self::PROD.'/shiip/public-api/master-data/ward' => Http::response(['data' => [['WardCode' => '20109', 'WardName' => 'Phường Bến Nghé']]]),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/vi/admin/shipping/ghn/provinces')->assertOk()->assertJsonPath('data.0.ProvinceID', 202);
        $this->actingAs($admin)->getJson('/vi/admin/shipping/ghn/provinces/202/districts')->assertOk()->assertJsonPath('data.0.DistrictID', 1442);
        $this->actingAs($admin)->getJson('/vi/admin/shipping/ghn/districts/1442/wards')->assertOk()->assertJsonPath('data.0.WardCode', '20109');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/district') && $r['province_id'] === 202);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/ward') && $r['district_id'] === 1442);
    }

    public function test_saving_ghn_settings_warns_when_ghn_disagrees(): void
    {
        $partner = $this->ghn(self::PROD);
        Http::fake([
            self::DEV.'/shiip/public-api/v2/shop/all' => Http::response(['code' => 401], 401),
            self::PROD.'/shiip/public-api/v2/shop/all' => Http::response(['data' => ['shops' => [['_id' => 5300247]]]]),
        ]);

        $this->actingAs($this->admin())
            ->post('/vi/admin/shipping-partners/'.$partner->id.'/settings', ['api_token' => 'prod-token', 'api_url' => self::DEV, 'shop_id' => 5300247])
            ->assertRedirect()
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'đổi "Môi trường API" sang Production'));
        $this->assertSame(self::DEV, $partner->fresh()->settings['api_url'], 'saved anyway');

        $this->actingAs($this->admin())
            ->post('/vi/admin/shipping-partners/'.$partner->id.'/settings', ['api_token' => 'prod-token', 'api_url' => self::PROD, 'shop_id' => 5300247])
            ->assertSessionHas('success');
    }

    public function test_the_checkout_test_page_uses_the_admin_layout(): void
    {
        $this->ghn(self::PROD);

        $this->actingAs($this->admin())->get('/vi/admin/checkout-test')
            ->assertOk()
            ->assertSee('Thử đặt hàng')
            ->assertSee('admin-assets/js/checkout-test.js', false)
            ->assertSee('/vi/admin/shipping/ghn/status', false)
            ->assertDontSee('client-assets/js/checkout-test.js', false);

        $role = Role::query()->create(['name' => 'Viewer', 'permissions' => ['orders.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]))->get('/vi/admin/checkout-test')->assertForbidden();
    }

    private function ghn(string $url): ShippingPartner
    {
        $partner = ShippingPartner::query()->where('partner_code', 'DTGH000013')->firstOrFail();
        $partner->update(['status' => 'active', 'settings' => ['api_token' => 'prod-token', 'api_url' => $url, 'shop_id' => '5300247']]);

        return $partner;
    }

    private function admin(): User
    {
        $role = Role::query()->create(['name' => 'Admin '.uniqid(), 'permissions' => ['*']]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
