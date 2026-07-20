<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductOptionService;
use App\Services\Catalog\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VariantV2DemoSeeder extends Seeder
{
    /** Seed one complex, idempotent Variant V2 product for admin review. */
    public function run(): void
    {
        DB::transaction(function (): void {
            $category = Category::query()->firstOrCreate(
                ['slug' => 'demo-variant-v2'],
                [
                    'name' => ['vi' => 'Demo biến thể V2', 'en' => 'Variant V2 demo'],
                    'description' => [
                        'vi' => 'Danh mục dữ liệu mẫu để kiểm tra biến thể nâng cao.',
                        'en' => 'Sample category for advanced variant testing.',
                    ],
                    'is_active' => true,
                ],
            );

            $product = Product::query()->where('slug', 'ao-khoac-outdoor-variant-v2-demo')->first();
            if ($product && $product->variants()->whereNull('option_signature')->exists()) {
                $product = $this->moveContaminatedDemoToSafeProduct($product, $category);
            }
            $product ??= $this->createDemoProduct($category);

            if ($product->optionGroups()->doesntExist()) {
                app(ProductOptionService::class)->sync($product, [
                    [
                        'name' => 'Màu sắc',
                        'display_type' => 'color',
                        'values' => [
                            ['label' => 'Đen than', 'color_hex' => '#1f2937', 'is_active' => true],
                            ['label' => 'Xanh rêu', 'color_hex' => '#53624d', 'is_active' => true],
                            ['label' => 'Be cát', 'color_hex' => '#d4c5a9', 'is_active' => true],
                            ['label' => 'Cam đất', 'color_hex' => '#c95f3a', 'is_active' => true],
                        ],
                    ],
                    [
                        'name' => 'Kích thước',
                        'display_type' => 'select',
                        'values' => [
                            ['label' => 'S', 'is_active' => true],
                            ['label' => 'M', 'is_active' => true],
                            ['label' => 'L', 'is_active' => true],
                            ['label' => 'XL', 'is_active' => true],
                        ],
                    ],
                    [
                        'name' => 'Chất liệu',
                        'display_type' => 'select',
                        'values' => [
                            ['label' => 'Nylon chống nước', 'is_active' => true],
                            ['label' => 'Canvas dày', 'is_active' => true],
                        ],
                    ],
                ]);
            }

            app(ProductService::class)->generateVariants($product);

            $sizePrice = ['s' => 0, 'm' => 30000, 'l' => 60000, 'xl' => 90000];
            $colorStock = ['den-than' => 12, 'xanh-reu' => 9, 'be-cat' => 7, 'cam-dat' => 5];

            $product->variants()->with('optionValues')->get()->each(function ($variant) use ($sizePrice, $colorStock): void {
                $codes = $variant->optionValues->pluck('code')->all();
                $size = collect($codes)->first(fn (string $code) => array_key_exists($code, $sizePrice));
                $materialIsCanvas = in_array('canvas-day', $codes, true);
                $color = collect($codes)->first(fn (string $code) => array_key_exists($code, $colorStock));
                $price = 890000 + ($sizePrice[$size] ?? 0) + ($materialIsCanvas ? 120000 : 0);
                $stock = ($colorStock[$color] ?? 4) + ($materialIsCanvas ? 2 : 0) + (($sizePrice[$size] ?? 0) / 30000);

                $variant->update([
                    'barcode' => '893'.str_pad((string) $variant->id, 10, '0', STR_PAD_LEFT),
                    'price' => $price,
                    'compare_at_price' => $price + 150000,
                    'cost_price' => 460000 + ($materialIsCanvas ? 80000 : 0),
                    'weight_grams' => $materialIsCanvas ? 920 : 780,
                    'stock_quantity' => (int) $stock,
                    'is_active' => true,
                ]);
            });
        });

        $this->command?->info('Đã seed Áo khoác Outdoor V2: 32 SKU (4 màu × 4 size × 2 chất liệu).');
    }

    private function createDemoProduct(Category $category): Product
    {
        $product = new Product();
        $product->forceFill(array_merge($this->demoAttributes($category), [
            // Existing databases can contain variants whose parent ID was deleted.
            // Reserve an ID above those references so demo data never reattaches them.
            'id' => $this->nextSafeProductId(),
        ]));
        $product->save();

        return $product;
    }

    private function moveContaminatedDemoToSafeProduct(Product $contaminated, Category $category): Product
    {
        $legacyId = $contaminated->id;
        $newProduct = null;

        // Keep legacy SKUs intact and clearly isolated instead of deleting them.
        $contaminated->update([
            'name' => ['vi' => "Dữ liệu SKU legacy chưa ghép sản phẩm #{$legacyId}", 'en' => "Unmatched legacy SKU data #{$legacyId}"],
            'slug' => "legacy-orphan-skus-{$legacyId}",
            'sku' => "LEGACY-ORPHAN-{$legacyId}",
            'is_active' => false,
            'is_featured' => false,
            'published_at' => null,
        ]);

        $newProduct = $this->createDemoProduct($category);
        $v2VariantIds = ProductVariant::query()
            ->where('product_id', $legacyId)
            ->whereNotNull('option_signature')
            ->pluck('id');

        ProductVariant::query()->whereIn('id', $v2VariantIds)->update(['product_id' => $newProduct->id]);
        $contaminated->optionGroups()->update(['product_id' => $newProduct->id]);

        return $newProduct;
    }

    private function nextSafeProductId(): int
    {
        $lastProductId = (int) Product::query()->max('id');
        $lastOrphanReference = (int) ProductVariant::query()->whereDoesntHave('product')->max('product_id');

        return max($lastProductId, $lastOrphanReference) + 1;
    }

    private function demoAttributes(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'name' => ['vi' => 'Áo khoác Outdoor 4 mùa — Demo Variant V2', 'en' => 'Four-season Outdoor Jacket — Variant V2 Demo'],
            'slug' => 'ao-khoac-outdoor-variant-v2-demo',
            'sku' => 'JACKET-V2-DEMO',
            'short_description' => [
                'vi' => 'Dữ liệu mẫu gồm màu sắc, kích thước và chất liệu để kiểm tra tổ hợp SKU.',
                'en' => 'Sample data with color, size and material SKU combinations.',
            ],
            'description' => [
                'vi' => '<p>Sản phẩm demo cho Variant V2. Mỗi SKU là một tổ hợp duy nhất của <strong>Màu sắc</strong>, <strong>Kích thước</strong> và <strong>Chất liệu</strong>.</p>',
                'en' => '<p>Variant V2 demo product. Every SKU is a unique combination of <strong>Color</strong>, <strong>Size</strong> and <strong>Material</strong>.</p>',
            ],
            'image_url' => 'https://placehold.co/1200x1200/172033/ffffff?text=Outdoor+Jacket+V2',
            'price' => 890000,
            'compare_at_price' => 1090000,
            'cost_price' => 460000,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ];
    }
}
