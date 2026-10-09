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
        $response->assertSee('mediaUploadDropzone', false);
        $response->assertSee('images-empty.png', false);
        $response->assertSee('aria-label="breadcrumb"', false);
        $response->assertSee('color: #fff !important', false);
        $response->assertSee('Dung lượng đã sử dụng');
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

    public function test_media_library_sorts_files_and_reports_local_storage_usage(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::disk('public')->put('general/zebra.jpg', str_repeat('z', 1024));
        Storage::disk('public')->put('general/apple.jpg', str_repeat('a', 2048));

        $response = $this->get('/vi/admin/media?folder=general&sort=name_asc');

        $response->assertOk()->assertSee('name="sort"', false);
        $this->assertSame('general/apple.jpg', $response->viewData('resources')->items()[0]['public_id']);
        $this->assertSame(3072, $response->viewData('storageUsage')['used']);
        $this->assertSame(10 * 1024 * 1024 * 1024, $response->viewData('storageUsage')['limit']);
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

    public function test_admin_can_upload_multiple_media_files_in_one_request(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/vi/admin/media/upload', [
            'file' => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.png'),
            ],
            'folder' => 'general',
        ])->assertRedirect()->assertSessionHas('success');

        $files = Storage::disk('public')->files('general');
        $this->assertCount(2, $files);
        $extensions = array_map(fn (string $file): string => pathinfo($file, PATHINFO_EXTENSION), $files);
        $this->assertContains('jpg', $extensions);
        $this->assertContains('png', $extensions);
    }

    public function test_admin_can_upload_and_manage_an_mp4_video(): void
    {
        $this->actingAs(User::factory()->create());
        $video = UploadedFile::fake()->create('product-demo.mp4', 1024, 'video/mp4');

        $this->post('/vi/admin/media/upload', [
            'file' => $video,
            'folder' => 'general',
        ])->assertRedirect()->assertSessionHas('success');

        $file = Storage::disk('public')->files('general')[0];
        $this->assertStringEndsWith('.mp4', $file);

        $this->get('/vi/admin/media?folder=general')
            ->assertOk()
            ->assertSee('<video', false)
            ->assertSee('resource_type" value="video"', false);

        $this->delete('/vi/admin/media/delete', [
            'public_id' => $file,
            'resource_type' => 'video',
        ])->assertRedirect()->assertSessionHas('success');

        Storage::disk('public')->assertMissing($file);
    }

    public function test_media_library_rejects_unsupported_or_oversized_videos(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/vi/admin/media/upload', [
            'file' => UploadedFile::fake()->create('product-demo.webm', 1024, 'video/webm'),
            'folder' => 'general',
        ])->assertSessionHasErrors('file');

        $this->post('/vi/admin/media/upload', [
            'file' => UploadedFile::fake()->create('too-large.mp4', 51201, 'video/mp4'),
            'folder' => 'general',
        ])->assertSessionHasErrors('file');
    }

    /**
     * Validation reads the content, so an image polyglot named x.html passes
     * it. Stored under the client's extension, the web server would serve it
     * as text/html on the admin's origin.
     */
    public function test_the_stored_extension_comes_from_the_content_not_the_client_name(): void
    {
        $this->actingAs(User::factory()->create());
        // A real UploadedFile: the fake one reports its MIME type from the name.
        $path = tempnam(sys_get_temp_dir(), 'poly');
        file_put_contents($path, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7').'<script>alert(1)</script>');
        $polyglot = new UploadedFile($path, 'banner.html', null, null, true);

        $this->post('/vi/admin/media/upload', [
            'file' => $polyglot,
            'folder' => 'general',
        ])->assertRedirect()->assertSessionHas('success');

        $files = Storage::disk('public')->files('general');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.gif', $files[0]);
    }

    public function test_uploads_are_rate_limited_per_admin(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 20) as $attempt) {
            $this->post('/vi/admin/media/upload', ['file' => UploadedFile::fake()->image("p{$attempt}.jpg"), 'folder' => 'general'])->assertRedirect();
        }

        $this->post('/vi/admin/media/upload', ['file' => UploadedFile::fake()->image('p21.jpg'), 'folder' => 'general'])->assertStatus(429);
    }

    public function test_the_extension_audit_lists_files_uploads_no_longer_produce(): void
    {
        Storage::disk('public')->put('general/old-banner.html', '<script>alert(1)</script>');
        Storage::disk('public')->put('general/photo.jpg', 'jpg');

        $this->artisan('media:audit-extensions')
            ->expectsOutputToContain('storage/app/public/general/old-banner.html')
            ->doesntExpectOutputToContain('photo.jpg')
            ->assertFailed();

        Storage::disk('public')->assertExists('general/old-banner.html');
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
