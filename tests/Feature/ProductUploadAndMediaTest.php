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
        $response->assertSee('row-cols-xl-8', false);
        $response->assertSee('object-fit: contain', false);
        // The folder list follows the scroll instead of sitting in a tall empty column.
        $response->assertSee('media-folder-sidebar', false);
        $response->assertSee('position: sticky', false);
    }

    public function test_media_library_paginates_the_file_grid(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (range(1, 30) as $number) {
            Storage::disk('public')->put(sprintf('general/library-%02d.jpg', $number), 'image');
        }

        $firstPage = $this->get('/vi/admin/media?folder=general');
        $firstPage->assertOk();
        $paginator = $firstPage->viewData('resources');
        $this->assertSame(30, $paginator->total());
        $this->assertCount(25, $paginator->items());
        $this->assertSame(2, $paginator->lastPage());
        $firstPage->assertSee('folder=general&amp;page=2', false);

        $secondPage = $this->get('/vi/admin/media?folder=general&page=2');
        $secondPage->assertOk();
        $this->assertCount(5, $secondPage->viewData('resources')->items());
    }

    public function test_media_library_honours_the_shared_per_page_option(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (range(1, 20) as $number) {
            Storage::disk('public')->put(sprintf('general/library-%02d.jpg', $number), 'image');
        }

        $response = $this->get('/vi/admin/media?folder=general&per_page=15');

        $response->assertOk();
        $this->assertCount(15, $response->viewData('resources')->items());
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

        $files = Storage::disk('public')->files('general');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.jpg', $files[0]);
    }

    public function test_image_picker_rejects_svg_with_a_clear_security_message(): void
    {
        $this->actingAs(User::factory()->create());
        $svg = UploadedFile::fake()->createWithContent(
            'unsafe.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->postJson('/vi/admin/media/upload', [
            'file' => $svg,
            'folder' => 'general',
            'image_only' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.file.0',
                'Định dạng ảnh không được hỗ trợ. Chỉ chấp nhận JPG, PNG, WEBP hoặc GIF. SVG bị chặn vì lý do bảo mật.',
            );
    }

    public function test_admin_can_get_image_resources_for_the_media_picker(): void
    {
        $this->actingAs(User::factory()->create());
        $image = UploadedFile::fake()->image('picker-image.jpg', 320, 180);
        Storage::disk('public')->putFileAs('general', $image, 'picker-image.jpg');
        Storage::disk('public')->put('general/document.pdf', 'document');

        $this->getJson('/vi/admin/media/resources?folder=general')
            ->assertOk()
            ->assertJsonCount(1, 'resources')
            ->assertJsonPath('resources.0.public_id', 'general/picker-image.jpg')
            ->assertJsonPath('resources.0.width', 320)
            ->assertJsonPath('resources.0.height', 180);
    }

    public function test_media_picker_paginates_local_images(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (range(1, 25) as $number) {
            Storage::disk('public')->put("general/image-{$number}.jpg", 'image');
        }

        $firstPage = $this->getJson('/vi/admin/media/resources?folder=general')->assertOk();
        $firstPage->assertJsonCount(24, 'resources')->assertJsonPath('next_cursor', 'local:2');

        $this->getJson('/vi/admin/media/resources?folder=general&cursor=local:2')
            ->assertOk()
            ->assertJsonCount(1, 'resources')
            ->assertJsonPath('next_cursor', null);
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
        $files = Storage::disk('public')->files('products');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
    }

    public function test_can_create_product_with_an_existing_media_url(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::query()->create([
            'name' => ['vi' => 'Điện thoại'],
            'slug' => 'dien-thoai-library',
            'is_active' => true,
        ]);

        $this->post('/vi/admin/products', [
            'name' => 'Library product',
            'slug' => 'library-product',
            'sku' => 'LIBRARY-1',
            'price' => 1200,
            'category_id' => $category->id,
            'image_url' => 'https://cdn.example.test/products/library.jpg',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'sku' => 'LIBRARY-1',
            'image_url' => 'https://cdn.example.test/products/library.jpg',
        ]);
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
