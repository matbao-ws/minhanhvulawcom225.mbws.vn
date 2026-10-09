<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An order takes stock, a voucher use and promotion quota when placed. A
 * cancelled order hands all three back, and an online order left unpaid is
 * cancelled after its payment window, so anonymous checkouts cannot hold a
 * product, a limited voucher or a flash sale hostage.
 */
class UnpaidOrderExpiryTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Promotion $promotion;

    private PromotionTarget $target;

    private Voucher $voucher;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        foreach (['catalog', 'cart', 'cod_order', 'voucher'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'active']);

        $this->product = Product::query()->create([
            'name' => ['vi' => 'Áo flash sale'],
            'slug' => 'ao-flash-sale',
            'sku' => 'FLASH-EXP',
            'price' => 100000,
            'stock_quantity' => 20,
            'manage_stock' => true,
            'is_active' => true,
        ]);
        $this->promotion = Promotion::query()->create([
            'name' => ['vi' => 'Flash sale'],
            'kind' => 'flash_sale',
            'applies_to' => 'selected',
            'discount_type' => 'fixed_price',
            'value' => 50000,
            'quantity_limit' => 5,
            'is_active' => true,
        ]);
        $this->target = PromotionTarget::query()->create([
            'promotion_id' => $this->promotion->id,
            'product_id' => $this->product->id,
            'quantity_limit' => 5,
        ]);
        $this->voucher = Voucher::query()->create([
            'code' => 'GIAM10K',
            'name' => ['vi' => 'Giảm 10k'],
            'type' => 'fixed',
            'value' => 10000,
            'min_order_amount' => 0,
            'quantity' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);
    }

    public function test_an_online_order_left_unpaid_past_its_window_is_cancelled_and_hands_everything_back(): void
    {
        $order = $this->placeOrder(quantity: 3, paymentMethod: 'sepay', placedHoursAgo: 25);
        $this->assertReserved(stock: 17, promotion: 3, voucher: 1);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->reservations_released_at);
        $this->assertReserved(stock: 20, promotion: 0, voucher: 0);
        $this->assertDatabaseMissing('voucher_usages', ['order_id' => $order->id]);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'to_status' => 'cancelled']);
    }

    public function test_orders_inside_their_window_cod_orders_and_paid_orders_are_left_alone(): void
    {
        $recent = $this->placeOrder(quantity: 1, paymentMethod: 'sepay', placedHoursAgo: 10);
        $cod = $this->placeOrder(quantity: 1, paymentMethod: 'cod', placedHoursAgo: 72, voucher: null);
        $paid = $this->placeOrder(quantity: 1, paymentMethod: 'vnpay', placedHoursAgo: 2, voucher: null);
        $paid->forceFill(['payment_status' => 'paid', 'status' => 'processing'])->save();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('pending', $recent->fresh()->status);
        $this->assertSame('pending', $cod->fresh()->status);
        $this->assertSame('processing', $paid->fresh()->status);
    }

    public function test_vnpay_orders_expire_after_thirty_minutes(): void
    {
        $order = $this->placeOrder(quantity: 1, paymentMethod: 'vnpay', placedHoursAgo: 1, voucher: null);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_an_admin_cancellation_hands_back_the_voucher_and_promotion_quota(): void
    {
        $order = $this->placeOrder(quantity: 2, paymentMethod: 'cod', placedHoursAgo: 0);
        $admin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Ops', 'permissions' => ['orders.view', 'orders.update']])->id]);

        $this->actingAs($admin)->patch('/vi/admin/orders/'.$order->id.'/status', [
            'status' => 'cancelled',
            'payment_status' => 'pending',
        ])->assertRedirect();

        $this->assertReserved(stock: 20, promotion: 0, voucher: 0);
    }

    public function test_releasing_twice_hands_back_once(): void
    {
        $order = $this->placeOrder(quantity: 2, paymentMethod: 'cod', placedHoursAgo: 0);
        // Another order keeps one unit of quota in use, so a double release would show.
        $this->placeOrder(quantity: 1, paymentMethod: 'cod', placedHoursAgo: 0, voucher: null, email: 'other@example.com');

        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->update(['status' => 'cancelled']);
            app(OrderCancellationService::class)->releaseReservations($locked);
            app(OrderCancellationService::class)->releaseReservations($locked);
        });

        $this->assertReserved(stock: 19, promotion: 1, voucher: 0);
    }

    public function test_checkout_caps_the_quantity_per_line_and_the_number_of_lines(): void
    {
        $base = [
            'customer_name' => 'Khách',
            'customer_email' => 'khach@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'HCM',
            'payment_method' => 'cod',
        ];

        $this->postJson('/api/public/orders/checkout', $base + [
            'items' => [['product_id' => $this->product->id, 'quantity' => config('orders.max_line_quantity') + 1]],
        ])->assertUnprocessable();

        $this->postJson('/api/public/orders/checkout', $base + [
            'items' => array_fill(0, config('orders.max_lines') + 1, ['product_id' => $this->product->id, 'quantity' => 1]),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(20, $this->product->fresh()->stock_quantity);
    }

    /** Placed through the real checkout (COD), then moved to the method under test. */
    private function placeOrder(int $quantity, string $paymentMethod, int $placedHoursAgo, ?string $voucher = 'GIAM10K', string $email = 'buyer@example.com'): Order
    {
        $this->postJson('/api/public/orders/checkout', array_filter([
            'customer_name' => 'Người mua',
            'customer_email' => $email,
            'customer_phone' => '0900000001',
            'shipping_address' => 'HCM',
            'payment_method' => 'cod',
            'voucher_code' => $voucher,
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]],
        ]))->assertOk();

        $order = Order::query()->latest('id')->firstOrFail();
        $order->forceFill(['payment_method' => $paymentMethod, 'created_at' => now()->subHours($placedHoursAgo)])->save();

        return $order;
    }

    private function assertReserved(int $stock, int $promotion, int $voucher): void
    {
        $this->assertSame($stock, $this->product->fresh()->stock_quantity, 'stock');
        $this->assertSame($promotion, $this->promotion->fresh()->used_count, 'promotion used_count');
        $this->assertSame($promotion, $this->target->fresh()->used_count, 'target used_count');
        $this->assertSame($voucher, $this->voucher->fresh()->used_count, 'voucher used_count');
    }
}
