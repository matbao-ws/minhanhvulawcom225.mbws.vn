<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductUploadAndMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable catalog feature
        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);

        Storage::fake('public');
    }

    public function test_admin_can_access_media_library(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/vi/admin/media');

        $response->assertOk();
        $response->assertViewIs('admin.media.index');
        $response->assertViewHas('folders');
        $response->assertViewHas('resources');
    }

    public function test_admin_can_upload_media_file(): void
    {
        $this->actingAs(User::factory()->create());

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post('/vi/admin/media/upload', [
            'file' => $file,
            'folder' => 'general',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Storage::disk('public')->assertExists('general/' . $file->hashName());
    }

    public function test_admin_can_delete_media_file(): void
    {
        $this->actingAs(User::factory()->create());

        // Place a dummy file in storage
        $filename = 'general/photo_to_delete.jpg';
        Storage::disk('public')->put($filename, 'dummy content');
        Storage::disk('public')->assertExists($filename);

        $response = $this->delete('/vi/admin/media/delete', [
            'public_id' => $filename,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Storage::disk('public')->assertMissing($filename);
    }

    public function test_can_create_product_with_image_upload(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::query()->create([
            'name' => ['vi' => 'Điện thoại', 'en' => 'Phones'],
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);

        $imageFile = UploadedFile::fake()->image('iphone.png');

        $response = $this->post('/vi/admin/products', [
            'name' => 'iPhone 15',
            'slug' => 'iphone-15',
            'sku' => 'IP-15',
            'price' => 1200,
            'category_id' => $category->id,
            'image_file' => $imageFile,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        
        $product = Product::query()->where('sku', 'IP-15')->firstOrFail();
        $this->assertNotNull($product->image_url);
        $this->assertStringContainsString('products/', $product->image_url);

        // Verify the file was stored on the fallback public disk
        $filename = 'products/' . $imageFile->hashName();
        Storage::disk('public')->assertExists($filename);
    }

    public function test_can_save_product_as_draft_inactive(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::query()->create([
            'name' => ['vi' => 'Điện thoại', 'en' => 'Phones'],
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);

        $response = $this->post('/vi/admin/products', [
            'name' => 'iPhone Draft',
            'slug' => 'iphone-draft',
            'sku' => 'IP-DRAFT',
            'price' => 1200,
            'category_id' => $category->id,
            'is_active' => 0, // Inactive (saved as draft)
        ]);

        $response->assertRedirect();
        
        $product = Product::query()->where('sku', 'IP-DRAFT')->firstOrFail();
        $this->assertFalse($product->is_active);
    }
}
