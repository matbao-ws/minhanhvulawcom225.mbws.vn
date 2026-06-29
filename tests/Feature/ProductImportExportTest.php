<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->create([
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);
    }

    public function test_can_download_export_csv(): void
    {
        Product::query()->create([
            'name' => ['vi' => 'Sản phẩm Test', 'en' => 'Test Product'],
            'slug' => 'san-pham-test',
            'sku' => 'EXP-SKU-1',
            'price' => 50000,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create());

        $response = $this->get('/vi/admin/products/export');
        
        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=products_export_' . date('Y-m-d') . '.csv');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('EXP-SKU-1', $content);
        $this->assertStringContainsString('Sản phẩm Test', $content);
    }

    public function test_can_download_templates(): void
    {
        $this->actingAs(User::factory()->create());

        // Test Standard Template
        $responseStandard = $this->get('/vi/admin/products/template/standard');
        $responseStandard->assertOk();
        $responseStandard->assertHeader('Content-Disposition', 'attachment; filename=template_standard.csv');
        $contentStandard = $responseStandard->streamedContent();
        $this->assertStringContainsString('Name_VI,Name_EN,Slug,SKU', $contentStandard);

        // Test WordPress Template
        $responseWordpress = $this->get('/vi/admin/products/template/wordpress');
        $responseWordpress->assertOk();
        $responseWordpress->assertHeader('Content-Disposition', 'attachment; filename=template_wordpress.csv');
        $contentWordpress = $responseWordpress->streamedContent();
        $this->assertStringContainsString('SKU,Name,Published', $contentWordpress);
        $this->assertStringContainsString('"Short description"', $contentWordpress);
    }

    public function test_can_import_standard_csv(): void
    {
        $csvContent = "\xEF\xBB\xBFName_VI,Name_EN,Slug,SKU,Category,Brand,Price,Compare_At_Price,Cost_Price,Stock_Quantity,Manage_Stock,Is_Active,Short_Description_VI,Short_Description_EN,Description_VI,Description_EN,Image_URL\n"
            . "Laptop Dell,Dell Laptop,laptop-dell,DELL-999,Laptops,Dell,15000,20000,,10,1,1,Mô tả ngắn,Short desc,Mô tả chi tiết,Long desc,http://example.com/dell.png\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'import_test');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'import_standard.csv',
            'text/csv',
            null,
            true
        );

        $this->actingAs(User::factory()->create());

        $response = $this->post('/vi/admin/products/import', [
            'import_type' => 'standard',
            'import_file' => $uploadedFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => 'DELL-999',
            'slug' => 'laptop-dell',
            'price' => 15000,
            'stock_quantity' => 10,
        ]);

        $product = Product::query()->where('sku', 'DELL-999')->firstOrFail();
        $this->assertEquals('Laptop Dell', $product->getTranslation('name', 'vi'));
        $this->assertEquals('Dell Laptop', $product->getTranslation('name', 'en'));
        $this->assertEquals('Laptops', $product->category->getTranslation('name', 'vi'));
        $this->assertEquals('Dell', $product->brand->getTranslation('name', 'vi'));

        @unlink($tempFile);
    }

    public function test_can_import_wordpress_woocommerce_csv(): void
    {
        $csvContent = "\xEF\xBB\xBF\"SKU\",\"Name\",\"Published\",\"Short description\",\"Description\",\"Regular price\",\"Sale price\",\"Manage stock?\",\"Stock\",\"Categories\",\"Images\"\n"
            . "\"WP-123\",\"WP Keyboard\",\"1\",\"Classic clicky keyboard\",\"Awesome mechanical keyboard\",\"100000\",\"90000\",\"yes\",\"15\",\"Computers > Keyboards\",\"https://example.com/kbd.png\"\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'import_test');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'import_wordpress.csv',
            'text/csv',
            null,
            true
        );

        $this->actingAs(User::factory()->create());

        $response = $this->post('/vi/admin/products/import', [
            'import_type' => 'wordpress',
            'import_file' => $uploadedFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => 'WP-123',
            'price' => 90000,
            'compare_at_price' => 100000,
            'stock_quantity' => 15,
            'manage_stock' => true,
        ]);

        // Assert dynamic category tree creation
        $parentCategory = Category::query()->where('name->vi', 'Computers')->whereNull('parent_id')->firstOrFail();
        $childCategory = Category::query()->where('name->vi', 'Keyboards')->where('parent_id', $parentCategory->id)->firstOrFail();

        $product = Product::query()->where('sku', 'WP-123')->firstOrFail();
        $this->assertEquals($childCategory->id, $product->category_id);
        $this->assertEquals('https://example.com/kbd.png', $product->image_url);

        @unlink($tempFile);
    }
}
