<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable review feature setting
        FeatureSetting::query()->create([
            'feature_code' => 'review',
            'is_enabled' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['manage_reviews'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        // Create a dummy product
        $this->product = Product::query()->create([
            'name' => ['vi' => 'Sản phẩm test', 'en' => 'Test Product'],
            'slug' => 'san-pham-test',
            'sku' => 'TEST-123',
            'price' => 100000,
            'is_active' => true,
        ]);
    }

    public function test_guests_cannot_access_reviews(): void
    {
        $response = $this->get('/vi/admin/reviews');
        $response->assertRedirect('/login');
    }

    public function test_non_authorized_admins_cannot_access_reviews(): void
    {
        $roleWithoutPermission = Role::query()->create([
            'name' => 'Editor',
            'permissions' => ['manage_posts'],
        ]);
        $unauthorizedAdmin = User::factory()->create([
            'role_id' => $roleWithoutPermission->id,
        ]);
        $this->actingAs($unauthorizedAdmin);

        $response = $this->get('/vi/admin/reviews');
        $response->assertStatus(403);
    }

    public function test_authorized_admins_can_browse_reviews(): void
    {
        $this->actingAs($this->admin);

        $review = Review::query()->create([
            'product_id' => $this->product->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'rating' => 5,
            'comment' => 'Sản phẩm tuyệt vời!',
            'is_visible' => true,
        ]);

        $response = $this->get('/vi/admin/reviews');
        $response->assertOk();
        $response->assertSee('John Doe');
        $response->assertSee('Sản phẩm tuyệt vời!');
    }

    public function test_authorized_admins_can_update_review_via_ajax(): void
    {
        $this->actingAs($this->admin);

        $review = Review::query()->create([
            'product_id' => $this->product->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'rating' => 5,
            'comment' => 'Sản phẩm tuyệt vời!',
            'is_visible' => true,
        ]);

        $response = $this->putJson("/vi/admin/reviews/{$review->id}", [
            'comment' => 'Sản phẩm siêu chất lượng!',
            'is_visible' => false,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'comment' => 'Sản phẩm siêu chất lượng!',
            'is_visible' => false,
        ]);
    }

    public function test_authorized_admins_can_toggle_visibility_via_ajax(): void
    {
        $this->actingAs($this->admin);

        $review = Review::query()->create([
            'product_id' => $this->product->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'rating' => 5,
            'comment' => 'Sản phẩm tuyệt vời!',
            'is_visible' => true,
        ]);

        $response = $this->patchJson("/vi/admin/reviews/{$review->id}/toggle-visibility");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'is_visible' => false,
        ]);

        $this->assertFalse($review->fresh()->is_visible);
    }

    public function test_authorized_admins_can_delete_review(): void
    {
        $this->actingAs($this->admin);

        $review = Review::query()->create([
            'product_id' => $this->product->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'rating' => 5,
            'comment' => 'Sản phẩm tuyệt vời!',
            'is_visible' => true,
        ]);

        $response = $this->deleteJson("/vi/admin/reviews/{$review->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);
    }
}
