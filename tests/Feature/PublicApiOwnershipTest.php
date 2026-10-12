<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Object-level access on /api/public: every route that takes a resource identifier (an order
 * number, an id or slug, or an id in the body) reaches only what the caller may see. Addresses
 * are covered by CustomerAddressApiTest and UserAddressApiTest.
 */
class PublicApiOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalog', 'cart', 'cod_order', 'review', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        PaymentMethod::query()->where('method_code', 'cod')->update(['status' => 'active']);

        $this->alice = User::factory()->create(['role_id' => null]);
        $this->bob = User::factory()->create(['role_id' => null]);
    }

    public function test_order_history_lists_only_the_callers_orders(): void
    {
        $this->order('ORD-ALICE', $this->alice);
        $this->order('ORD-BOB', $this->bob);
        $this->order('ORD-GUEST', null, 'alice-guest@example.com');

        Sanctum::actingAs($this->alice, ['customer']);
        $numbers = collect($this->getJson('/api/public/orders')->assertOk()->json('data'))->pluck('order_number')->all();

        $this->assertSame(['ORD-ALICE'], $numbers);
    }

    public function test_order_detail_by_number_is_only_for_its_owner(): void
    {
        $this->order('ORD-ALICE', $this->alice);
        $this->order('ORD-GUEST', null);

        $this->getJson('/api/public/orders/ORD-ALICE')->assertUnauthorized();

        Sanctum::actingAs($this->bob, ['customer']);
        $this->getJson('/api/public/orders/ORD-ALICE')->assertNotFound()->assertJsonMissing(['order_number' => 'ORD-ALICE']);
        $this->getJson('/api/public/orders/ORD-GUEST')->assertNotFound(); // a guest order belongs to no account

        Sanctum::actingAs($this->alice, ['customer']);
        $this->getJson('/api/public/orders/ORD-ALICE')->assertOk()->assertJsonPath('data.order_number', 'ORD-ALICE');
    }

    public function test_tracking_needs_the_contact_of_that_same_order(): void
    {
        $this->order('ORD-A', null, 'a@example.com', '0900000001');
        $this->order('ORD-B', null, 'b@example.com', '0900000002');

        $this->getJson('/api/public/orders/track?order_number=ORD-A&contact=a@example.com')->assertOk();
        $this->getJson('/api/public/orders/track?order_number=ORD-A&contact=b@example.com')->assertNotFound();
        $this->getJson('/api/public/orders/track?order_number=ORD-A&contact=0900000002')->assertNotFound();
        $this->getJson('/api/public/orders/track?order_number=ORD-A')->assertStatus(422);
    }

    public function test_an_inactive_product_is_hidden_by_id_and_by_slug(): void
    {
        $product = $this->product(active: false);

        $this->getJson('/api/public/products/'.$product->id)->assertNotFound();
        $this->getJson('/api/public/products/'.$product->slug)->assertNotFound();
    }

    public function test_hidden_reviews_never_reach_the_product_page(): void
    {
        $product = $this->product();
        Review::query()->create(['product_id' => $product->id, 'customer_name' => 'Hiện', 'customer_email' => 'shown@example.com', 'rating' => 5, 'comment' => 'Đã duyệt', 'is_visible' => true]);
        Review::query()->create(['product_id' => $product->id, 'customer_name' => 'Ẩn', 'customer_email' => 'hidden@example.com', 'rating' => 1, 'comment' => 'Chưa duyệt', 'is_visible' => false]);

        $response = $this->getJson('/api/public/products/'.$product->slug)->assertOk();

        $this->assertSame(['Đã duyệt'], collect($response->json('data.reviews'))->pluck('comment')->all());
        $response->assertJsonMissing(['comment' => 'Chưa duyệt'])->assertJsonMissing(['customer_email' => 'hidden@example.com']);
    }

    public function test_a_draft_or_scheduled_post_is_hidden_by_id_and_by_slug(): void
    {
        $draft = $this->makePost('ban-nhap', ['is_active' => false]);
        $scheduled = $this->makePost('sap-dang', ['published_at' => now()->addDay()]);

        foreach ([$draft, $scheduled] as $post) {
            $this->getJson('/api/public/posts/'.$post->id)->assertNotFound();
            $this->getJson('/api/public/posts/'.$post->slug)->assertNotFound();
        }
    }

    public function test_checkout_refuses_a_product_id_the_storefront_cannot_sell(): void
    {
        $hidden = $this->product(active: false);

        $this->postJson('/api/public/orders/checkout', [
            'customer_name' => 'Khách',
            'customer_email' => 'khach@example.com',
            'customer_phone' => '0900000009',
            'shipping_address' => '1 Lê Lợi',
            'payment_method' => 'cod',
            'items' => [['product_id' => $hidden->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(50, $hidden->fresh()->stock_quantity);
    }

    private function order(string $number, ?User $user, string $email = 'buyer@example.com', string $phone = '0900000000'): Order
    {
        return Order::query()->create([
            'order_number' => $number,
            'user_id' => $user?->id,
            'customer_name' => 'Khách',
            'customer_email' => $email,
            'customer_phone' => $phone,
            'shipping_address' => '1 Lê Lợi',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 100000,
            'discount' => 0,
            'grand_total' => 100000,
        ]);
    }

    private function product(bool $active = true): Product
    {
        return Product::query()->create([
            'name' => ['vi' => 'Sản phẩm'],
            'slug' => 'san-pham-'.str()->random(8),
            'sku' => 'SP-'.str()->upper(str()->random(6)),
            'price' => 100000,
            'stock_quantity' => 50,
            'manage_stock' => true,
            'is_active' => $active,
        ]);
    }

    private function makePost(string $slug, array $overrides): Post
    {
        return Post::query()->create([
            'title' => ['vi' => 'Bài '.$slug],
            'slug' => $slug,
            'content' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
            'published_at' => now()->subDay(),
            ...$overrides,
        ]);
    }
}
