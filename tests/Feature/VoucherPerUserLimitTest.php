<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VoucherPerUserLimitTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Voucher $voucher;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        foreach (['catalog', 'cart', 'cod_order', 'voucher'] as $featureCode) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                ['is_enabled' => true],
            );
        }

        $category = Category::query()->create([
            'name' => ['vi' => 'Danh mục', 'en' => 'Category'],
            'slug' => 'danh-muc',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Simple product (no variants) priced above the voucher minimum.
        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'name' => ['vi' => 'Sản phẩm', 'en' => 'Product'],
            'slug' => 'san-pham',
            'sku' => 'SP-001',
            'price' => 600000.00,
            'stock_quantity' => 100,
            'manage_stock' => true,
            'is_active' => true,
        ]);

        $this->voucher = Voucher::query()->create([
            'code' => 'ONEPERUSER',
            'name' => ['vi' => 'Một lần mỗi khách', 'en' => 'One per user'],
            'type' => 'fixed',
            'value' => 100000.00,
            'min_order_amount' => 500000.00,
            'quantity' => 100,
            'per_user_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);
    }

    private function checkoutPayload(string $email): array
    {
        return [
            'customer_name' => 'Khách Hàng',
            'customer_email' => $email,
            'customer_phone' => '0988776655',
            'shipping_address' => '123 Đường ABC, HCMC',
            'payment_method' => 'cod',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
            'voucher_code' => 'ONEPERUSER',
        ];
    }

    public function test_model_enforces_per_user_limit_by_user_id(): void
    {
        $user = User::factory()->create(['role_id' => null]);

        $this->assertFalse($this->voucher->reachedPerUserLimit($user->id, null));
        $this->assertTrue($this->voucher->isValidForOrder(600000.00, $user->id, null));

        VoucherUsage::query()->create([
            'voucher_id' => $this->voucher->id,
            'user_id' => $user->id,
            'customer_email' => 'used@example.com',
            'used_at' => now(),
        ]);

        $this->assertTrue($this->voucher->reachedPerUserLimit($user->id, null));
        $this->assertFalse($this->voucher->isValidForOrder(600000.00, $user->id, null));
        // A different user is unaffected.
        $this->assertTrue($this->voucher->isValidForOrder(600000.00, $user->id + 999, null));
    }

    public function test_null_per_user_limit_is_unlimited(): void
    {
        $this->voucher->update(['per_user_limit' => null]);
        $user = User::factory()->create(['role_id' => null]);

        VoucherUsage::query()->create([
            'voucher_id' => $this->voucher->id,
            'user_id' => $user->id,
            'customer_email' => 'x@example.com',
            'used_at' => now(),
        ]);

        $this->assertFalse($this->voucher->reachedPerUserLimit($user->id, null));
        $this->assertTrue($this->voucher->isValidForOrder(600000.00, $user->id, null));
    }

    public function test_authenticated_customer_blocked_on_second_use(): void
    {
        $customer = User::factory()->create(['role_id' => null]);
        Sanctum::actingAs($customer, ['customer']);

        $first = $this->postJson('/api/public/orders/checkout', $this->checkoutPayload('member@example.com'));
        $first->assertStatus(200);

        $this->assertDatabaseHas('voucher_usages', [
            'voucher_id' => $this->voucher->id,
            'user_id' => $customer->id,
        ]);
        $this->assertSame(1, $this->voucher->fresh()->used_count);

        $second = $this->postJson('/api/public/orders/checkout', $this->checkoutPayload('member@example.com'));
        $second->assertStatus(422)->assertJsonFragment(['success' => false]);

        // No second usage recorded and the global counter did not advance.
        $this->assertDatabaseCount('voucher_usages', 1);
        $this->assertSame(1, $this->voucher->fresh()->used_count);
    }

    public function test_guest_blocked_on_second_use_by_email(): void
    {
        $first = $this->postJson('/api/public/orders/checkout', $this->checkoutPayload('guest@example.com'));
        $first->assertStatus(200);

        $second = $this->postJson('/api/public/orders/checkout', $this->checkoutPayload('guest@example.com'));
        $second->assertStatus(422)->assertJsonFragment(['success' => false]);

        // A different guest email can still use it.
        $other = $this->postJson('/api/public/orders/checkout', $this->checkoutPayload('another@example.com'));
        $other->assertStatus(200);

        $this->assertDatabaseCount('voucher_usages', 2);
    }

    public function test_apply_voucher_preview_reports_per_user_limit(): void
    {
        // Anonymous preview is not blocked by the per-user rule.
        $this->postJson('/api/public/vouchers/apply', [
            'code' => 'ONEPERUSER',
            'subtotal' => 600000.00,
        ])->assertStatus(200);

        $customer = User::factory()->create(['role_id' => null]);
        VoucherUsage::query()->create([
            'voucher_id' => $this->voucher->id,
            'user_id' => $customer->id,
            'customer_email' => 'member@example.com',
            'used_at' => now(),
        ]);

        Sanctum::actingAs($customer, ['customer']);

        $this->postJson('/api/public/vouchers/apply', [
            'code' => 'ONEPERUSER',
            'subtotal' => 600000.00,
        ])->assertStatus(422);
    }
}
