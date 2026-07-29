<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\ProjectSetting;
use App\Models\Review;
use App\Models\ShippingPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'cart', 'cod_order', 'review'] as $featureCode) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                ['is_enabled' => true],
            );
        }
    }

    public function test_public_settings_never_include_notification_secrets(): void
    {
        ProjectSetting::query()->create([
            'setting_key' => 'notification_settings',
            'setting_value' => ['smtp' => ['password' => 'smtp-secret']],
        ]);
        ProjectSetting::query()->create([
            'setting_key' => 'shop_name',
            'setting_value' => 'Safe Store',
        ]);

        $response = $this->getJson('/api/public/settings');

        $response->assertOk();
        $response->assertJsonPath('data.shop_name', 'Safe Store');
        $response->assertJsonMissing(['notification_settings' => ['smtp' => ['password' => 'smtp-secret']]]);
        $response->assertDontSee('smtp-secret');
    }

    public function test_public_product_response_redacts_cost_price_and_reviewer_email(): void
    {
        $product = Product::query()->create([
            'name' => ['vi' => 'Sản phẩm bảo mật'],
            'slug' => 'san-pham-bao-mat',
            'sku' => 'SECURE-PRODUCT',
            'price' => 200000,
            'cost_price' => 50000,
            'is_active' => true,
        ]);
        Review::query()->create([
            'product_id' => $product->id,
            'customer_name' => 'Khách hàng',
            'customer_email' => 'private@example.com',
            'rating' => 5,
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/public/products/san-pham-bao-mat');

        $response->assertOk();
        $response->assertDontSee('cost_price');
        $response->assertDontSee('private@example.com');
    }

    public function test_logout_revokes_the_sanctum_token(): void
    {
        $registration = $this->postJson('/api/public/auth/register', [
            'name' => 'Token Customer',
            'email' => 'token-customer@example.com',
            'password' => 'Password123!x',
            'password_confirmation' => 'Password123!x',
        ])->assertOk();
        $token = $registration->json('data.token');

        $this->withToken($token)->getJson('/api/public/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/public/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/public/auth/me')->assertUnauthorized();
    }

    public function test_checkout_uses_server_shipping_fee_and_requires_an_active_payment_method(): void
    {
        Mail::fake();
        $product = Product::query()->create([
            'name' => ['vi' => 'Sản phẩm checkout'],
            'slug' => 'san-pham-checkout',
            'sku' => 'CHECKOUT-PRODUCT',
            'price' => 100000,
            'is_active' => true,
            'manage_stock' => false,
        ]);
        ShippingPartner::query()->where('partner_code', 'DTGHTUGIAO')->update([
            'status' => 'active',
            'settings' => ['fee' => 30000],
        ]);

        $payload = [
            'customer_name' => 'Checkout Customer',
            'customer_email' => 'checkout@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'shipping_fee' => 99999999,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/public/orders/checkout', $payload)->assertOk();
        $this->assertSame('30000.00', Order::query()->firstOrFail()->shipping_fee);

        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'inactive']);
        $this->postJson('/api/public/orders/checkout', array_merge($payload, [
            'customer_email' => 'inactive-payment@example.com',
        ]))->assertUnprocessable();
    }

    public function test_integration_settings_are_encrypted_at_rest(): void
    {
        $method = PaymentMethod::query()->where('method_code', 'cod')->firstOrFail();
        $method->update(['settings' => ['api_key' => 'payment-secret-value']]);

        $rawSettings = DB::table('payment_methods')->where('id', $method->id)->value('settings');

        $this->assertStringNotContainsString('payment-secret-value', $rawSettings);
        $this->assertSame('payment-secret-value', $method->fresh()->settings['api_key']);
    }
}
