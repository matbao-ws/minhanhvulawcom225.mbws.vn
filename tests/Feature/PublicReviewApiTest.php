<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\Review;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Brand $brand;
    private Product $product;
    private Product $inactiveProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable review feature setting globally by default for tests
        FeatureSetting::query()->updateOrCreate(
            ['feature_code' => 'review'],
            ['is_enabled' => true]
        );

        $this->category = Category::query()->create([
            'name' => ['vi' => 'Điện thoại', 'en' => 'Phones'],
            'slug' => 'dien-thoai',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->brand = Brand::query()->create([
            'name' => 'Apple',
            'slug' => 'apple',
            'is_active' => true,
            'sort_order' => 1,
        ]);

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

        $this->inactiveProduct = Product::query()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => ['vi' => 'Sản phẩm ẩn', 'en' => 'Hidden Product'],
            'slug' => 'san-pham-an',
            'sku' => 'HIDDEN-PROD',
            'price' => 5000000.00,
            'stock_quantity' => 5,
            'manage_stock' => true,
            'is_active' => false,
        ]);
    }

    private function createOrderForProduct(Product $product, array $attributes = []): Order
    {
        $order = Order::query()->create(array_merge([
            'order_number' => 'ORD-' . strtoupper(\Illuminate\Support\Str::random(10)),
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '0988776655',
            'shipping_address' => '123 Test St',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'processing',
            'subtotal' => $product->price,
            'discount' => 0.00,
            'grand_total' => $product->price,
        ], $attributes));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name['vi'] ?? 'Product',
            'price' => $product->price,
            'quantity' => 1,
            'total' => $product->price,
        ]);

        return $order;
    }

    public function test_guest_can_submit_review_with_valid_data(): void
    {
        // Purchase first
        $this->createOrderForProduct($this->product, [
            'customer_email' => 'guest@example.com'
        ]);

        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'customer_name' => 'Khách Vãng Lai',
            'customer_email' => 'guest@example.com',
            'rating' => 5,
            'comment' => 'Sản phẩm rất tốt, giao hàng nhanh!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Gửi đánh giá thành công.',
        ]);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $this->product->id,
            'customer_name' => 'Khách Vãng Lai',
            'customer_email' => 'guest@example.com',
            'rating' => 5,
            'comment' => 'Sản phẩm rất tốt, giao hàng nhanh!',
            'user_id' => null,
            'is_visible' => true,
        ]);
    }

    public function test_guest_can_submit_review_using_product_slug(): void
    {
        // Purchase first
        $this->createOrderForProduct($this->product, [
            'customer_email' => 'guest.slug@example.com'
        ]);

        $response = $this->postJson("/api/public/products/{$this->product->slug}/reviews", [
            'customer_name' => 'Guest User',
            'customer_email' => 'guest.slug@example.com',
            'rating' => 4,
            'comment' => 'Đánh giá qua slug thành công',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reviews', [
            'product_id' => $this->product->id,
            'customer_email' => 'guest.slug@example.com',
            'rating' => 4,
        ]);
    }

    public function test_guest_cannot_submit_review_with_missing_or_invalid_fields(): void
    {
        // Purchase first
        $this->createOrderForProduct($this->product, [
            'customer_email' => 'email@test.com'
        ]);

        // Missing name and email
        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'comment' => 'Thiếu thông tin người gửi',
        ]);
        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'errors' => ['customer_name', 'customer_email']]);

        // Invalid email format and invalid rating (out of range)
        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'customer_name' => 'Name',
            'customer_email' => 'invalid-email',
            'rating' => 6,
        ]);
        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'errors' => ['customer_email', 'rating']]);

        // Rating is less than 1
        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'customer_name' => 'Name',
            'customer_email' => 'email@test.com',
            'rating' => 0,
        ]);
        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_submit_review_without_providing_personal_info(): void
    {
        $user = User::factory()->create([
            'name' => 'Thành Viên Víp',
            'email' => 'member.vip@example.com',
        ]);

        // Purchase first
        $this->createOrderForProduct($this->product, [
            'user_id' => $user->id
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'comment' => 'Đánh giá từ tài khoản thành viên',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $this->product->id,
            'user_id' => $user->id,
            'customer_name' => 'Thành Viên Víp',
            'customer_email' => 'member.vip@example.com',
            'rating' => 5,
            'comment' => 'Đánh giá từ tài khoản thành viên',
        ]);
    }

    public function test_authenticated_user_can_override_default_personal_info(): void
    {
        $user = User::factory()->create([
            'name' => 'Thành Viên Víp',
            'email' => 'member.vip@example.com',
        ]);

        // Purchase first
        $this->createOrderForProduct($this->product, [
            'user_id' => $user->id
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'customer_name' => 'Tên Hiển Thị Khác',
            'customer_email' => 'other.email@example.com',
            'rating' => 3,
            'comment' => 'Đánh giá với tên hiển thị ẩn danh',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reviews', [
            'product_id' => $this->product->id,
            'user_id' => $user->id,
            'customer_name' => 'Tên Hiển Thị Khác',
            'customer_email' => 'other.email@example.com',
            'rating' => 3,
        ]);
    }

    public function test_cannot_submit_review_without_purchasing(): void
    {
        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'customer_name' => 'No Purchase',
            'customer_email' => 'no.purchase@example.com',
            'rating' => 5,
            'comment' => 'Chưa mua hàng mà đòi đánh giá',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Bạn chỉ có thể đánh giá sản phẩm sau khi đã mua hàng.',
        ]);
    }

    public function test_cannot_submit_review_if_order_is_cancelled(): void
    {
        // Purchase, but cancelled
        $this->createOrderForProduct($this->product, [
            'customer_email' => 'cancelled@example.com',
            'status' => 'cancelled'
        ]);

        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'customer_name' => 'Cancelled Guest',
            'customer_email' => 'cancelled@example.com',
            'rating' => 5,
            'comment' => 'Đơn hàng đã hủy không được đánh giá',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_submit_review_for_non_existent_product(): void
    {
        $response = $this->postJson("/api/public/products/999999/reviews", [
            'customer_name' => 'Name',
            'customer_email' => 'guest@example.com',
            'rating' => 5,
        ]);

        $response->assertStatus(404);
    }

    public function test_cannot_submit_review_for_inactive_product(): void
    {
        $response = $this->postJson("/api/public/products/{$this->inactiveProduct->id}/reviews", [
            'customer_name' => 'Name',
            'customer_email' => 'guest@example.com',
            'rating' => 5,
        ]);

        $response->assertStatus(404);
    }

    public function test_cannot_submit_review_when_feature_is_disabled(): void
    {
        // Disable the feature
        FeatureSetting::query()->updateOrCreate(
            ['feature_code' => 'review'],
            ['is_enabled' => false]
        );

        $response = $this->postJson("/api/public/products/{$this->product->id}/reviews", [
            'customer_name' => 'Name',
            'customer_email' => 'guest@example.com',
            'rating' => 5,
        ]);

        $response->assertStatus(403);
    }
}
