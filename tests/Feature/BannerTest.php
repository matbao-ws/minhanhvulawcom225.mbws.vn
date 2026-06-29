<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'], // Superadmin or Admin roles have '*'
        ]);

        \App\Models\FeatureSetting::query()->create([
            'feature_code' => 'banner',
            'is_enabled' => true,
        ]);

        Storage::fake('public');
    }

    public function test_guests_cannot_access_banners(): void
    {
        $response = $this->get('/vi/admin/banners');
        $response->assertRedirect('/login');
    }

    public function test_non_authorized_users_cannot_access_banners(): void
    {
        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer);

        $response = $this->get('/vi/admin/banners');
        $response->assertStatus(403);
    }

    public function test_admin_can_browse_banners_list(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        Banner::create([
            'title' => 'Sale Banner',
            'image_path' => 'http://localhost/storage/banners/sale.jpg',
            'link_url' => 'https://example.com/sale',
            'position' => 'home_main',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/vi/admin/banners');
        $response->assertOk();
        $response->assertSee('Sale Banner');
        $response->assertSee('home_main');
    }

    public function test_admin_can_create_banner(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $file = UploadedFile::fake()->image('main_banner.jpg');

        $response = $this->post('/vi/admin/banners', [
            'title' => 'New Summer Sale',
            'image_file' => $file,
            'link_url' => 'https://example.com/summer-sale',
            'position' => 'home_main',
            'sort_order' => 2,
            'is_active' => 1,
        ]);

        $response->assertRedirect('/vi/admin/banners');
        
        $this->assertDatabaseHas('banners', [
            'title' => 'New Summer Sale',
            'link_url' => 'https://example.com/summer-sale',
            'position' => 'home_main',
            'sort_order' => 2,
            'is_active' => 1,
        ]);

        $banner = Banner::where('title', 'New Summer Sale')->first();
        $this->assertNotNull($banner->image_path);
        
        // Assert that the image file was stored
        $path = str_replace(Storage::disk('public')->url(''), '', $banner->image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_can_update_banner_details_and_image(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        // Pre-create banner with old image
        $oldFile = UploadedFile::fake()->image('old_banner.jpg');
        $oldPath = $oldFile->store('banners', 'public');
        $oldUrl = Storage::disk('public')->url($oldPath);

        $banner = Banner::create([
            'title' => 'Old Title',
            'image_path' => $oldUrl,
            'link_url' => 'https://example.com/old',
            'position' => 'home_sidebar',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        // Assert old file exists
        Storage::disk('public')->assertExists($oldPath);

        // Update with new image and details
        $newFile = UploadedFile::fake()->image('new_banner.jpg');

        $response = $this->put("/vi/admin/banners/{$banner->id}", [
            'title' => 'Updated Title',
            'image_file' => $newFile,
            'link_url' => 'https://example.com/new',
            'position' => 'promotional',
            'sort_order' => 10,
            'is_active' => 1,
        ]);

        $response->assertRedirect('/vi/admin/banners');
        
        $banner->refresh();
        $this->assertEquals('Updated Title', $banner->title);
        $this->assertEquals('https://example.com/new', $banner->link_url);
        $this->assertEquals('promotional', $banner->position);
        $this->assertEquals(10, $banner->sort_order);

        // Assert new image is saved, old is deleted
        $newPath = str_replace(Storage::disk('public')->url(''), '', $banner->image_path);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_can_delete_banner(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $file = UploadedFile::fake()->image('banner_to_delete.jpg');
        $path = $file->store('banners', 'public');
        $url = Storage::disk('public')->url($path);

        $banner = Banner::create([
            'title' => 'To Delete',
            'image_path' => $url,
            'position' => 'home_main',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Storage::disk('public')->assertExists($path);

        $response = $this->delete("/vi/admin/banners/{$banner->id}");
        $response->assertRedirect('/vi/admin/banners');

        $this->assertDatabaseMissing('banners', [
            'id' => $banner->id,
        ]);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_cannot_access_banners_when_feature_disabled(): void
    {
        // Disable banner feature
        \App\Models\FeatureSetting::query()->where('feature_code', 'banner')->update([
            'is_enabled' => false,
        ]);

        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $response = $this->get('/vi/admin/banners');
        $response->assertStatus(403);
    }
}
