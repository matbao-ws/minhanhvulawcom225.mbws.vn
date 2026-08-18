<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Mail\OrderStatusMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Brand $brand;
    private Product $product;
    private ProductVariant $variant;
    private ProductOptionValue $optionValue;
    private Voucher $voucher;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'cart', 'cod_order', 'online_payment', 'voucher', 'review', 'cms_page'] as $featureCode) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                ['is_enabled' => true],
            );
        }

        \App\Models\PaymentMethod::query()
            ->where('method_code', 'bank_transfer')
            ->update(['status' => 'active']);

        // Create Category
        $this->category = Category::query()->create([
            'name' => ['vi' => 'Điện thoại', 'en' => 'Phones'],
            'slug' => 'dien-thoai',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create Brand
        $this->brand = Brand::query()->create([
            'name' => 'Apple',
            'slug' => 'apple',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create Product
        $this->product = Product::query()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => ['vi' => 'iPhone 15 Pro', 'en' => 'iPhone 15 Pro'],
            'slug' => 'iphone-15-pro',
            'sku' => 'IPHONE15PRO',
            'price' => 30000000.00,
            'stock_quantity' => 10,
            'manage_stock' => true,
            'is_active' => true,
        ]);

        $group = ProductOptionGroup::query()->create([
            'product_id' => $this->product->id,
            'name' => ['vi' => 'Dung lượng'],
            'code' => 'storage',
        ]);
        $this->optionValue = ProductOptionValue::query()->create([
            'product_option_group_id' => $group->id,
            'label' => ['vi' => '256GB', 'en' => '256GB'],
            'code' => '256gb',
            'is_active' => true,
        ]);

        // Create Variant
        $this->variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'name' => '256GB Gold',
            'sku' => 'IPHONE15PRO-256G-GOLD',
            'option_signature' => ProductVariant::signatureForOptionValueIds([$this->optionValue->id]),
            'price' => 32000000.00,
            'stock_quantity' => 5,
        ]);
        $this->variant->optionValues()->sync([$this->optionValue->id]);

        // Create Voucher
        $this->voucher = Voucher::query()->create([
            'code' => 'DISCOUNT100K',
            'name' => ['vi' => 'Giảm 100k', 'en' => '100k Off'],
            'type' => 'fixed',
            'value' => 100000.00,
            'min_order_amount' => 500000.00,
            'quantity' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);
    }

    public function test_can_get_active_categories_and_brands(): void
    {
        $response = $this->getJson('/api/public/categories');
        $response->assertStatus(200);
        $response->assertJsonFragment(['slug' => 'dien-thoai']);

        $response = $this->getJson('/api/public/brands');
        $response->assertStatus(200);
        $response->assertJsonFragment(['slug' => 'apple']);
    }

    public function test_can_list_and_filter_products(): void
    {
        $response = $this->getJson('/api/public/products');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data', 'meta']);
        $response->assertJsonFragment(['slug' => 'iphone-15-pro']);

        // Filter by category
        $response = $this->getJson('/api/public/products?category=dien-thoai');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Filter by wrong category
        $response = $this->getJson('/api/public/products?category=does-not-exist');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_can_get_product_detail_with_variants(): void
    {
        $response = $this->getJson("/api/public/products/{$this->product->slug}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['slug' => 'iphone-15-pro']);
        $response->assertJsonFragment(['name' => '256GB Gold']);
    }

    public function test_public_settings_exposes_feature_states_for_client_visibility(): void
    {
        FeatureSetting::query()->where('feature_code', 'online_payment')->update(['is_enabled' => false]);

        $this->getJson('/api/public/settings')
            ->assertOk()
            ->assertJsonPath('data.features.catalog', true)
            ->assertJsonPath('data.features.online_payment', false);
    }

    public function test_disabled_features_block_their_public_api_modules(): void
    {
        FeatureSetting::query()->where('feature_code', 'catalog')->update(['is_enabled' => false]);
        $this->getJson('/api/public/products')->assertForbidden();

        FeatureSetting::query()->where('feature_code', 'catalog')->update(['is_enabled' => true]);
        FeatureSetting::query()->where('feature_code', 'cms_page')->update(['is_enabled' => false]);
        $this->getJson('/api/public/pages')->assertForbidden();

        FeatureSetting::query()->where('feature_code', 'voucher')->update(['is_enabled' => false]);
        $this->postJson('/api/public/vouchers/apply', [
            'code' => 'DISCOUNT100K',
            'subtotal' => 600000,
        ])->assertForbidden();

        $payload = $this->checkoutPayload('disabled-voucher@example.com');
        $payload['voucher_code'] = 'DISCOUNT100K';
        $this->postJson('/api/public/orders/checkout', $payload)->assertForbidden();

        FeatureSetting::query()->where('feature_code', 'cart')->update(['is_enabled' => false]);
        $this->postJson('/api/public/orders/checkout', $this->checkoutPayload('disabled-cart@example.com'))
            ->assertForbidden();
    }

    public function test_disabled_review_feature_removes_reviews_from_product_detail(): void
    {
        Review::query()->create([
            'product_id' => $this->product->id,
            'customer_name' => 'Khách hàng',
            'customer_email' => 'review@example.com',
            'rating' => 5,
            'is_visible' => true,
        ]);
        FeatureSetting::query()->where('feature_code', 'review')->update(['is_enabled' => false]);

        $this->getJson("/api/public/products/{$this->product->slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.reviews');
    }

    public function test_checkout_blocks_disabled_cod_and_online_payment_features(): void
    {
        FeatureSetting::query()->where('feature_code', 'cod_order')->update(['is_enabled' => false]);
        $this->postJson('/api/public/orders/checkout', $this->checkoutPayload('disabled-cod@example.com'))
            ->assertForbidden();

        FeatureSetting::query()->where('feature_code', 'cod_order')->update(['is_enabled' => true]);
        FeatureSetting::query()->where('feature_code', 'online_payment')->update(['is_enabled' => false]);
        $payload = $this->checkoutPayload('disabled-online@example.com');
        $payload['payment_method'] = 'bank_transfer';
        $this->postJson('/api/public/orders/checkout', $payload)->assertForbidden();
    }

    public function test_apply_voucher_validation(): void
    {
        // Valid voucher
        $response = $this->postJson('/api/public/vouchers/apply', [
            'code' => 'DISCOUNT100K',
            'subtotal' => 600000.00,
        ]);
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'code' => 'DISCOUNT100K',
            'discount_amount' => 100000.00
        ]);

        // Below minimum order amount
        $response = $this->postJson('/api/public/vouchers/apply', [
            'code' => 'DISCOUNT100K',
            'subtotal' => 200000.00,
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_guest_checkout_creation(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'Guest Customer',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '0988776655',
            'shipping_address' => '789 District 3, HCMC',
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'option_value_ids' => [$this->optionValue->id],
                    'quantity' => 2,
                ]
            ],
            'voucher_code' => 'DISCOUNT100K',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);

        $this->product->refresh();
        $this->variant->refresh();

        // Variant inventory is authoritative; product stock is not decremented too.
        $this->assertEquals(10, $this->product->stock_quantity);
        $this->assertEquals(3, $this->variant->stock_quantity);

        // Assert order created
        $order = Order::query()->where('customer_email', 'guest@example.com')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->user_id);
        $this->assertEquals(63900000.00, $order->grand_total); // (32M * 2) - 100k discount
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'action' => 'sale',
            'direction' => 'out',
            'quantity' => 2,
            'product_stock_after' => 10,
            'variant_stock_after' => 3,
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_method' => 'cod',
            'gateway' => 'cod',
            'status' => 'pending',
        ]);

        Mail::assertSent(OrderStatusMail::class, function ($mail) use ($order) {
            return $mail->hasTo('guest@example.com');
        });
    }

    public function test_admin_api_token_cannot_be_used_for_customer_checkout(): void
    {
        $role = Role::query()->create([
            'name' => 'Checkout Admin',
            'permissions' => ['orders.view', 'orders.create', 'orders.update'],
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'Blocked Admin',
            'customer_email' => 'blocked-admin@example.com',
            'customer_phone' => '0988776655',
            'shipping_address' => '789 District 3, HCMC',
            'payment_method' => 'cod',
            'items' => [[
                'product_id' => $this->product->id,
                'option_value_ids' => [$this->optionValue->id],
                'quantity' => 1,
            ]],
        ])->assertStatus(403);

        $this->assertDatabaseMissing('orders', ['customer_email' => 'blocked-admin@example.com']);
    }

    public function test_checkout_rejects_when_variant_stock_is_insufficient(): void
    {
        $this->variant->update(['stock_quantity' => 1]);

        $response = $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'Guest Customer',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '0988776655',
            'shipping_address' => '789 District 3, HCMC',
            'payment_method' => 'cod',
            'items' => [[
                'product_id' => $this->product->id,
                'option_value_ids' => [$this->optionValue->id],
                'quantity' => 2,
            ]],
        ]);

        $response->assertStatus(422)->assertJsonFragment(['success' => false]);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, $this->variant->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_customer_checkout_links_user(): void
    {
        Mail::fake();

        $customer = User::factory()->create([
            'role_id' => null,
        ]);

        Sanctum::actingAs($customer, ['customer']);

        $response = $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'Member Customer',
            'customer_email' => 'member@example.com',
            'customer_phone' => '0912345678',
            'shipping_address' => '456 Tran Hung Dao, Da Nang',
            'payment_method' => 'bank_transfer',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'option_value_ids' => [$this->optionValue->id],
                    'quantity' => 1,
                ]
            ]
        ]);

        $response->assertStatus(200);
        $order = Order::query()->where('customer_email', 'member@example.com')->first();
        $this->assertNotNull($order);
        $this->assertEquals($customer->id, $order->user_id);
    }

    public function test_customer_auth_flow(): void
    {
        // 1. Register
        $responseRegister = $this->postJson('/api/public/auth/register', [
            'name' => 'New Customer',
            'email' => 'new.customer@example.com',
            'password' => 'Password123!x',
            'password_confirmation' => 'Password123!x',
        ]);
        $responseRegister->assertStatus(200);
        $responseRegister->assertJsonStructure(['success', 'data' => ['user', 'token']]);

        // 2. Login
        $responseLogin = $this->postJson('/api/public/auth/login', [
            'email' => 'new.customer@example.com',
            'password' => 'Password123!x',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('data.token');
        $this->assertNotEmpty($token);

        // 3. Me profile
        $responseMe = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/public/auth/me');
        $responseMe->assertStatus(200);
        $responseMe->assertJsonFragment(['email' => 'new.customer@example.com']);

        // 4. Logout
        $responseLogout = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/public/auth/logout');
        $responseLogout->assertStatus(200);
        $responseLogout->assertJsonFragment(['success' => true]);
    }

    public function test_track_order_success(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-TRACK-001',
            'customer_name' => 'Track User',
            'customer_email' => 'track@user.com',
            'customer_phone' => '0933221100',
            'shipping_address' => 'Some Address',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 1000000.00,
            'discount' => 0.00,
            'grand_total' => 1000000.00,
        ]);

        $response = $this->getJson('/api/public/orders/track?order_number=ORD-TRACK-001&contact=0933221100');
        $response->assertStatus(200);
        $response->assertJsonFragment(['customer_name' => 'Track User']);

        $responseEmail = $this->getJson('/api/public/orders/track?order_number=ORD-TRACK-001&contact=track@user.com');
        $responseEmail->assertStatus(200);
    }

    public function test_can_access_api_documentation_page(): void
    {
        $response = $this->get('/api/docs');
        $response->assertStatus(200);
        $response->assertSee('openapi.json');
    }

    public function test_openapi_specification_file_exists(): void
    {
        $filePath = public_path('docs/openapi.json');
        $this->assertFileExists($filePath);
        
        $jsonContent = file_get_contents($filePath);
        $this->assertJson($jsonContent);
    }

    private function checkoutPayload(string $email): array
    {
        return [
            'customer_name' => 'Feature Customer',
            'customer_email' => $email,
            'customer_phone' => '0988776655',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'items' => [[
                'product_id' => $this->product->id,
                'option_value_ids' => [$this->optionValue->id],
                'quantity' => 1,
            ]],
        ];
    }
}
