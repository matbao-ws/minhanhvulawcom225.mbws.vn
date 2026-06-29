<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImportService
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function import(string $filePath, string $type): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("File is not readable or does not exist.");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException("Cannot open file.");
        }

        // Handle UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException("CSV file is empty or invalid.");
        }

        // Clean headers: trim, lowercase, remove quotes
        $headers = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $h)));
        }, $headers);

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) < count($headers)) {
                // Pad row if it has fewer fields than headers
                $row = array_pad($row, count($headers), '');
            }
            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            try {
                DB::transaction(function () use ($data, $type) {
                    $payload = $this->mapPayload($data, $type);
                    if (!$payload) {
                        return; // Skip empty row
                    }

                    // Check if product exists by SKU
                    $product = null;
                    if (!empty($payload['sku'])) {
                        $product = Product::query()->where('sku', $payload['sku'])->first();
                    }

                    if ($product) {
                        $this->productService->update($product, $payload);
                    } else {
                        $this->productService->create($payload);
                    }
                });
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        fclose($handle);
        return $results;
    }

    private function mapPayload(array $data, string $type): ?array
    {
        if ($type === 'wordpress') {
            return $this->mapWordPressPayload($data);
        }

        return $this->mapStandardPayload($data);
    }

    private function mapStandardPayload(array $data): ?array
    {
        // Require at least name_vi or name_en to be non-empty
        $nameVi = $data['name_vi'] ?? '';
        $nameEn = $data['name_en'] ?? '';
        if (empty($nameVi) && empty($nameEn)) {
            return null;
        }

        $categoryId = null;
        if (!empty($data['category_id'])) {
            $categoryId = (int) $data['category_id'];
        } elseif (!empty($data['category'])) {
            $categoryId = $this->findOrCreateCategory($data['category']);
        }

        $brandId = null;
        if (!empty($data['brand_id'])) {
            $brandId = (int) $data['brand_id'];
        } elseif (!empty($data['brand'])) {
            $brandId = $this->findOrCreateBrand($data['brand']);
        }

        return [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => [
                'vi' => $nameVi ?: $nameEn,
                'en' => $nameEn ?: $nameVi,
            ],
            'slug' => $data['slug'] ?? '',
            'sku' => $data['sku'] ?? null,
            'price' => $this->parsePrice($data['price'] ?? 0),
            'compare_at_price' => $this->parsePrice($data['compare_at_price'] ?? null),
            'cost_price' => $this->parsePrice($data['cost_price'] ?? null),
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'manage_stock' => (bool) ($data['manage_stock'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'short_description' => [
                'vi' => $data['short_description_vi'] ?? '',
                'en' => $data['short_description_en'] ?? '',
            ],
            'description' => [
                'vi' => $data['description_vi'] ?? '',
                'en' => $data['description_en'] ?? '',
            ],
            'image_url' => $data['image_url'] ?? null,
        ];
    }

    private function mapWordPressPayload(array $data): ?array
    {
        // WooCommerce columns
        $name = $data['name'] ?? '';
        if (empty($name)) {
            return null;
        }

        $sku = $data['sku'] ?? null;
        $shortDesc = $data['short description'] ?? $data['short_description'] ?? '';
        $desc = $data['description'] ?? '';
        
        $regularPrice = $this->parsePrice($data['regular price'] ?? $data['regular_price'] ?? 0);
        $salePrice = $this->parsePrice($data['sale price'] ?? $data['sale_price'] ?? null);

        $price = $regularPrice;
        $compareAtPrice = null;
        if ($salePrice !== null && $salePrice < $regularPrice) {
            $price = $salePrice;
            $compareAtPrice = $regularPrice;
        }

        $manageStockRaw = strtolower(trim($data['manage stock?'] ?? $data['manage_stock'] ?? ''));
        $manageStock = ($manageStockRaw === '1' || $manageStockRaw === 'yes' || $manageStockRaw === 'true');

        $stock = $data['stock'] ?? $data['stock_quantity'] ?? 0;
        $stockQuantity = ($stock === '' || $stock === null) ? 0 : (int) $stock;

        $published = (int) ($data['published'] ?? 1);
        $isActive = ($published === 1);

        $categoriesString = $data['categories'] ?? '';
        $categoryId = null;
        if (!empty($categoriesString)) {
            $firstCategoryPath = trim(explode(',', $categoriesString)[0]);
            $categoryId = $this->findOrCreateCategory($firstCategoryPath);
        }

        $imagesString = $data['images'] ?? '';
        $imageUrl = null;
        if (!empty($imagesString)) {
            $imageUrl = trim(explode(',', $imagesString)[0]);
        }

        return [
            'category_id' => $categoryId,
            'brand_id' => null,
            'name' => [
                'vi' => $name,
                'en' => $name,
            ],
            'slug' => $data['slug'] ?? '',
            'sku' => $sku,
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'cost_price' => null,
            'stock_quantity' => $stockQuantity,
            'manage_stock' => $manageStock,
            'is_active' => $isActive,
            'short_description' => [
                'vi' => $shortDesc,
                'en' => $shortDesc,
            ],
            'description' => [
                'vi' => $desc,
                'en' => $desc,
            ],
            'image_url' => $imageUrl,
        ];
    }

    private function findOrCreateCategory(string $categoryString): ?int
    {
        $categoryString = trim($categoryString);
        if ($categoryString === '') {
            return null;
        }

        $segments = preg_split('/\s*(?:>|&gt;)\s*/', $categoryString);
        
        $parentId = null;
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $category = Category::query()
                ->where('parent_id', $parentId)
                ->where(function ($q) use ($segment) {
                    $q->where('name->vi', $segment)
                      ->orWhere('name->en', $segment);
                })
                ->first();

            if (!$category) {
                $category = Category::query()->create([
                    'parent_id' => $parentId,
                    'name' => [
                        'vi' => $segment,
                        'en' => $segment,
                    ],
                    'slug' => $this->uniqueCategorySlug($segment),
                    'is_active' => true,
                ]);
            }
            $parentId = $category->id;
        }

        return $parentId;
    }

    private function findOrCreateBrand(?string $brandName): ?int
    {
        if (empty($brandName)) {
            return null;
        }
        $brandName = trim($brandName);

        $brand = Brand::query()
            ->where('name->vi', $brandName)
            ->orWhere('name->en', $brandName)
            ->first();

        if (!$brand) {
            $brand = Brand::query()->create([
                'name' => [
                    'vi' => $brandName,
                    'en' => $brandName,
                ],
                'slug' => $this->uniqueBrandSlug($brandName),
                'is_active' => true,
            ]);
        }

        return $brand->id;
    }

    private function parsePrice($val): ?float
    {
        if ($val === '' || $val === null) {
            return null;
        }
        $clean = preg_replace('/[^\d.]/', '', $val);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function uniqueCategorySlug(string $value): string
    {
        $slug = Str::slug($value) ?: Str::random(8);
        $base = $slug;
        $counter = 2;
        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }
        return $slug;
    }

    private function uniqueBrandSlug(string $value): string
    {
        $slug = Str::slug($value) ?: Str::random(8);
        $base = $slug;
        $counter = 2;
        while (Brand::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }
        return $slug;
    }
}
