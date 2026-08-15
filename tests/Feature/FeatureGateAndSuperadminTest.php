<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGateAndSuperadminTest extends TestCase
{
    use RefreshDatabase;

    private Role $superadminRole;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['users.view', 'users.create', 'users.update', 'users.delete', 'products.view', 'products.create', 'products.update', 'products.delete'],
        ]);

        // Seed some feature settings
        FeatureSetting::query()->create([
            'feature_code' => 'multi_admin',
            'is_enabled' => true,
        ]);

    }

    public function test_guest_cannot_access_features_settings(): void
    {
        $response = $this->get('/vi/admin/features');
        $response->assertRedirect('/vi/admin/login');
    }

    public function test_standard_admin_cannot_access_features_settings(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/features');
        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_features_settings(): void
    {
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        $response = $this->get('/vi/admin/features');
        $response->assertOk();
        $response->assertViewIs('admin.features.index');
        $response->assertViewHas('featureGroups');
        $response->assertSee('Website bán hàng');
        $response->assertSee('Website không bán hàng');
        $response->assertSee('data-feature-group-switch="ecommerce"', false);
        $response->assertSee('data-feature-group-switch="non_ecommerce"', false);
    }

    public function test_superadmin_can_update_features_settings(): void
    {
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        $response = $this->post('/vi/admin/features', [
            'features' => [
                'multi_admin' => '0',
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('feature_settings', [
            'feature_code' => 'multi_admin',
            'is_enabled' => false,
        ]);

    }

    public function test_admin_cannot_access_users_management_when_multi_admin_disabled(): void
    {
        // Disable multi_admin
        FeatureSetting::query()->where('feature_code', 'multi_admin')->update([
            'is_enabled' => false,
        ]);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/users');
        $response->assertRedirect();
        $response->assertSessionHas('error', \App\Support\FeatureGate::SUPPORT_MESSAGE);
    }

    public function test_superadmin_can_access_users_management_even_when_multi_admin_disabled(): void
    {
        // Disable multi_admin
        FeatureSetting::query()->where('feature_code', 'multi_admin')->update([
            'is_enabled' => false,
        ]);

        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        $response = $this->get('/vi/admin/users');
        $response->assertOk(); // Bypassed
    }

    public function test_standard_admin_cannot_see_superadmin_users_in_listing(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/users');
        $response->assertOk();
        
        // Assert superadmin is NOT in the view data 'users' list
        $usersList = $response->viewData('users');
        $this->assertFalse($usersList->contains($superadmin));
        $this->assertTrue($usersList->contains($admin));
    }

    public function test_standard_admin_cannot_edit_superadmin_user(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($admin);

        $responseGet = $this->get('/vi/admin/users/' . $superadmin->id . '/edit');
        $responseGet->assertStatus(403);

        $responsePut = $this->put('/vi/admin/users/' . $superadmin->id, [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
            'role_id' => $this->adminRole->id,
        ]);
        $responsePut->assertStatus(403);
    }

    public function test_user_without_admin_role_cannot_access_admin_panel(): void
    {
        // User with no role_id (e.g. regular customer)
        $customer = User::factory()->create([
            'role_id' => null,
        ]);

        $this->actingAs($customer);

        $response = $this->get('/vi/admin');
        $response->assertStatus(403);
    }

    public function test_superadmin_can_toggle_feature_via_ajax(): void
    {
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin);

        // Initially multi_admin is true
        $this->assertTrue(\App\Models\FeatureSetting::where('feature_code', 'multi_admin')->first()->is_enabled);

        $response = $this->postJson('/vi/admin/features/toggle', [
            'feature_code' => 'multi_admin',
            'is_enabled' => 0
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true
        ]);

        $this->assertFalse(\App\Models\FeatureSetting::where('feature_code', 'multi_admin')->first()->is_enabled);
    }

    public function test_superadmin_can_toggle_an_entire_feature_group_without_changing_another_group(): void
    {
        FeatureSetting::query()->insert([
            ['feature_code' => 'catalog', 'is_enabled' => true],
            ['feature_code' => 'cart', 'is_enabled' => true],
            ['feature_code' => 'cms_page', 'is_enabled' => true],
        ]);
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $response = $this->actingAs($superadmin)->postJson('/vi/admin/features/group-toggle', [
            'group' => 'ecommerce',
            'is_enabled' => false,
        ]);

        // catalog and cart from the inserts above, plus "shipping", which its
        // migration seeds enabled and which belongs to the same group.
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'updated' => 3,
            ]);
        $this->assertDatabaseHas('feature_settings', ['feature_code' => 'catalog', 'is_enabled' => false]);
        $this->assertDatabaseHas('feature_settings', ['feature_code' => 'cart', 'is_enabled' => false]);
        $this->assertDatabaseHas('feature_settings', ['feature_code' => 'shipping', 'is_enabled' => false]);
        $this->assertDatabaseHas('feature_settings', ['feature_code' => 'cms_page', 'is_enabled' => true]);
    }

    public function test_feature_group_toggle_rejects_unknown_groups(): void
    {
        $superadmin = User::factory()->create([
            'role_id' => $this->superadminRole->id,
        ]);

        $this->actingAs($superadmin)
            ->postJson('/vi/admin/features/group-toggle', [
                'group' => 'unknown',
                'is_enabled' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group');

        $this->assertTrue(FeatureSetting::query()->where('feature_code', 'multi_admin')->value('is_enabled'));
    }

    public function test_standard_admin_cannot_toggle_a_feature_group(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin)
            ->postJson('/vi/admin/features/group-toggle', [
                'group' => 'ecommerce',
                'is_enabled' => false,
            ])
            ->assertForbidden();
    }

    public function test_feature_groups_cover_every_runtime_feature_once(): void
    {
        $groupedCodes = collect(config('features.groups'))->flatten()->values();

        $this->assertEqualsCanonicalizing(config('features.codes'), $groupedCodes->all());
        $this->assertSame($groupedCodes->count(), $groupedCodes->unique()->count());
    }

    public function test_legacy_product_limit_does_not_block_catalog_creation(): void
    {
        FeatureSetting::query()->create([
            'feature_code' => 'max_products',
            'is_enabled' => true,
            'limit_value' => '1',
        ]);
        Product::query()->create([
            'name' => ['vi' => 'Sản phẩm cũ'],
            'slug' => 'san-pham-cu',
            'sku' => 'LEGACY-LIMIT-01',
            'price' => 100000,
        ]);

        $product = app(ProductService::class)->create([
            'name' => ['vi' => 'Sản phẩm mới'],
            'sku' => 'LEGACY-LIMIT-02',
            'price' => 200000,
        ]);

        $this->assertSame('san-pham-moi', $product->slug);
        $this->assertDatabaseCount('products', 2);
    }
}
