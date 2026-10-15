<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\MultilingualSettings;
use App\Support\NotificationHelper;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Turning a feature off must actually remove its admin surface: both the route
 * and the sidebar entry that leads to it.
 *
 * Shipping and payment configuration used to carry no feature check at all — an
 * admin with every feature disabled still reached and used both screens.
 */
class FeatureDisablesAdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        foreach (config('features.codes', []) as $code) {
            // "shipping" already exists: its migration seeds it enabled so the
            // screen does not vanish from sites installed before that code.
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $code],
                ['is_enabled' => true],
            );
        }

        $role = Role::query()->create([
            'name' => 'Admin',
            'permissions' => Permission::query()->pluck('code')->all(),
        ]);

        $this->admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function disable(string ...$codes): void
    {
        FeatureSetting::query()->whereIn('feature_code', $codes)->update(['is_enabled' => false]);
    }

    private function enable(string ...$codes): void
    {
        foreach ($codes as $code) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $code], ['is_enabled' => true]);
        }
    }

    private function sidebar(): string
    {
        return $this->actingAs($this->admin)->get('/vi/admin')->getContent();
    }

    public function test_shipping_is_a_configurable_feature(): void
    {
        $this->assertContains('shipping', config('features.codes'));
    }

    public function test_disabling_shipping_closes_the_shipping_configuration(): void
    {
        $this->disable('shipping');

        $this->actingAs($this->admin)
            ->get('/vi/admin/shipping-partners')
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));

        $this->actingAs($this->admin)
            ->get('/vi/admin/shipping-partners/create')
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));

        $this->assertStringNotContainsString('/admin/shipping-partners', $this->sidebar());
    }

    public function test_shipping_configuration_stays_open_while_the_feature_is_on(): void
    {
        $this->actingAs($this->admin)->get('/vi/admin/shipping-partners')->assertOk();
        $this->assertStringContainsString('/admin/shipping-partners', $this->sidebar());
    }

    public function test_disabling_both_payment_features_closes_payment_configuration(): void
    {
        $this->disable('cod_order', 'online_payment');

        $this->actingAs($this->admin)
            ->get('/vi/admin/payment-methods')
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));

        $this->assertStringNotContainsString('/admin/payment-methods', $this->sidebar());
    }

    public function test_payment_configuration_survives_while_either_payment_feature_is_on(): void
    {
        // COD alone is still a payment method worth configuring.
        $this->disable('online_payment');
        $this->actingAs($this->admin)->get('/vi/admin/payment-methods')->assertOk();
        $this->assertStringContainsString('/admin/payment-methods', $this->sidebar());

        FeatureSetting::query()->where('feature_code', 'online_payment')->update(['is_enabled' => true]);
        $this->disable('cod_order');
        $this->actingAs($this->admin)->get('/vi/admin/payment-methods')->assertOk();
    }

    public function test_disabling_shipping_blocks_pushing_a_waybill(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-FEATURE-001',
            'customer_name' => 'Nguyen Van A',
            'customer_email' => 'a@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '1 Le Loi, HCMC',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000.00,
            'discount' => 0.00,
            'grand_total' => 100000.00,
        ]);

        $this->disable('shipping');

        $this->actingAs($this->admin)
            ->post("/vi/admin/orders/{$order->id}/push-shipping", [
                'carrier' => 'ghtk',
                'weight' => 500,
            ])
            ->assertRedirect(route('admin.dashboard', ['locale' => 'vi']));
    }

    public function test_superadmin_keeps_its_documented_bypass(): void
    {
        $this->disable(...config('features.codes'));

        $superadmin = User::factory()->create([
            'role_id' => Role::query()->create([
                'name' => 'Superadmin',
                'permissions' => ['*'],
                'is_system' => true,
            ])->id,
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)->get('/vi/admin/shipping-partners')->assertOk();
        $this->actingAs($superadmin)->get('/vi/admin/payment-methods')->assertOk();
    }

    public function test_disabled_variant_feature_blocks_variant_screens(): void
    {
        $product = app(ProductService::class)->create(['name' => ['vi' => 'Áo'], 'sku' => 'VAR-GATE', 'price' => 1000]);
        $this->disable('product_variant');

        $this->actingAs($this->admin)->get("/vi/admin/products/{$product->id}/options")
            ->assertRedirect('/vi/admin');
        // The product itself stays editable.
        $this->actingAs($this->admin)->get("/vi/admin/products/{$product->id}/edit")->assertOk();
    }

    public function test_disabled_multilingual_feature_blocks_languages_and_runs_single_language(): void
    {
        $this->assertTrue(app(MultilingualSettings::class)->enabled());

        $this->disable('multilingual');

        $this->actingAs($this->admin)->get('/vi/admin/languages')->assertRedirect('/vi/admin');
        $this->actingAs($this->admin)->postJson('/vi/admin/translations/preview', [])->assertForbidden();
        $this->assertFalse(app(MultilingualSettings::class)->enabled());
        $this->assertStringNotContainsString(route('admin.languages.index'), $this->sidebar());
    }

    public function test_vnpay_settings_need_the_vnpay_feature_too(): void
    {
        $vnpay = PaymentMethod::query()->updateOrCreate(['method_code' => 'vnpay'], [
            'name' => 'VNPAY Gateway', 'type' => 'connected', 'status' => 'active', 'settings' => [],
        ]);
        $this->enable('online_payment');
        $this->disable('payment_vnpay');
        $staff = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Kế toán', 'permissions' => ['payments.view', 'payments.update']])->id]);

        // Reached by its URL, not only hidden from the list.
        $this->actingAs($staff)->get('/vi/admin/payment-methods/'.$vnpay->id.'/settings')->assertForbidden();
    }

    public function test_variants_are_neither_offered_nor_saved_without_the_variant_feature(): void
    {
        $this->enable('catalog');
        $this->disable('product_variant');
        $staff = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Kho', 'permissions' => ['products.view', 'products.create', 'products.update']])->id]);

        $this->actingAs($staff)->get('/vi/admin/products/create')->assertOk()->assertDontSee('name="has_variants"', false);

        $this->actingAs($staff)->post('/vi/admin/products', [
            'name' => ['vi' => 'Áo'], 'price' => 100000, 'stock_quantity' => 5, 'is_active' => 1,
            'has_variants' => 1, 'variant_groups' => [['name' => 'Màu', 'values' => ['Đỏ', 'Xanh']]],
        ]);

        $product = Product::query()->latest('id')->first();
        $this->assertNotNull($product, 'the product itself is saved');
        $this->assertSame(0, $product->optionGroups()->count());
        $this->assertSame(0, $product->variants()->count());
    }

    public function test_the_features_page_says_what_a_feature_needs(): void
    {
        $this->disable('online_payment');

        // The features page is superadmin only: the system Superadmin role.
        $root = User::factory()->create(['is_active' => true, 'role_id' => Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true])->id]);
        $this->actingAs($root)->get('/vi/admin/features')->assertOk()
            ->assertSee(__('admin.features.requires', ['features' => __('admin.features.online_payment')]))
            ->assertSee('data-feature-requires="online_payment"', false);
    }

    public function test_the_zalo_bot_stays_silent_while_the_zalo_feature_is_off(): void
    {
        Http::fake();
        Mail::fake();
        ProjectSetting::query()->updateOrCreate(['setting_key' => 'notification_settings'], ['setting_value' => [
            'zalo_personal' => ['enabled' => true, 'bot_token' => 'bot-token', 'chat_id' => 'chat-1'],
        ]]);
        $order = Order::query()->create([
            'order_number' => 'ORD-ZALO-1', 'customer_name' => 'Khách', 'customer_email' => 'k@example.com',
            'customer_phone' => '0900000000', 'shipping_address' => '1 Lê Lợi', 'payment_method' => 'cod',
            'payment_status' => 'pending', 'status' => 'pending', 'subtotal' => 100000, 'discount' => 0, 'grand_total' => 100000,
        ]);

        $this->disable('zalo_oa');
        NotificationHelper::deliverNewOrderNotification($order);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'zaloplatforms'));

        $this->enable('zalo_oa');
        NotificationHelper::deliverNewOrderNotification($order);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'zaloplatforms'));
    }

    public function test_feature_requirements_name_real_features(): void
    {
        $codes = config('features.codes');
        foreach (config('features.requires') as $feature => $parents) {
            $this->assertContains($feature, $codes);
            foreach ($parents as $parent) {
                $this->assertContains($parent, $codes, "{$feature} requires an unknown {$parent}");
            }
        }
    }

    public function test_disabled_vnpay_feature_hides_vnpay_from_payment_methods(): void
    {
        PaymentMethod::query()->updateOrCreate(['method_code' => 'vnpay'], [
            'name' => 'VNPAY Gateway', 'type' => 'connected', 'status' => 'active', 'settings' => [],
        ]);

        $this->actingAs($this->admin)->get('/vi/admin/payment-methods')->assertSee('VNPAY Gateway');

        $this->disable('payment_vnpay');

        $this->actingAs($this->admin)->get('/vi/admin/payment-methods')->assertOk()->assertDontSee('VNPAY Gateway');
    }
}
