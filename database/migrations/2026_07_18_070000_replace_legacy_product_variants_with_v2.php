<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_option_groups')) {
            Schema::create('product_option_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->json('name');
                $table->string('code', 100);
                $table->string('display_type', 20)->default('select');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'code']);
                $table->index(['product_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('product_option_values')) {
            Schema::create('product_option_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_option_group_id')->constrained()->cascadeOnDelete();
                $table->json('label');
                $table->string('code', 100);
                $table->string('color_hex', 20)->nullable();
                $table->string('image_url')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['product_option_group_id', 'code']);
                $table->index(['product_option_group_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('product_variant_option_values')) {
            Schema::create('product_variant_option_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['product_variant_id', 'product_option_value_id'], 'variant_option_value_unique');
                $table->index('product_option_value_id');
            });
        }

        $hasSignature = Schema::hasColumn('product_variants', 'option_signature');
        $hasBarcode = Schema::hasColumn('product_variants', 'barcode');
        Schema::table('product_variants', function (Blueprint $table) use ($hasSignature, $hasBarcode) {
            if (! $hasBarcode) {
                $table->string('barcode')->nullable()->after('sku');
                $table->index('barcode');
            }
            if (! $hasSignature) {
                $table->string('option_signature', 64)->nullable()->after('option_values');
                $table->unique(['product_id', 'option_signature'], 'product_variant_signature_unique');
            }
            if (! Schema::hasColumn('product_variants', 'compare_at_price')) {
                $table->decimal('compare_at_price', 15, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('product_variants', 'cost_price')) {
                $table->decimal('cost_price', 15, 2)->nullable()->after('compare_at_price');
            }
            if (! Schema::hasColumn('product_variants', 'image_url')) {
                $table->string('image_url')->nullable()->after('cost_price');
            }
            if (! Schema::hasColumn('product_variants', 'weight_grams')) {
                $table->unsignedInteger('weight_grams')->nullable()->after('image_url');
            }
            if (! Schema::hasColumn('product_variants', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
        });

        $this->backfillLegacyVariants();
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variant_signature_unique');
            $table->dropIndex(['barcode']);
            $table->dropColumn([
                'barcode',
                'option_signature',
                'compare_at_price',
                'cost_price',
                'image_url',
                'weight_grams',
                'is_default',
            ]);
        });

        Schema::dropIfExists('product_variant_option_values');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_option_groups');
    }

    private function backfillLegacyVariants(): void
    {
        DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->select('product_variants.*')
            ->orderBy('product_variants.id')
            ->chunkById(100, function ($variants): void {
            foreach ($variants as $variant) {
                $legacyOptions = $this->decodeJson($variant->option_values ?? null);
                $optionValueIds = [];

                foreach ($legacyOptions as $groupName => $valueLabel) {
                    $groupName = trim(is_string($groupName) ? $groupName : '');
                    $valueLabel = trim(is_scalar($valueLabel) ? (string) $valueLabel : '');

                    if ($groupName === '' || $valueLabel === '') {
                        continue;
                    }

                    $groupId = $this->findOrCreateGroup((int) $variant->product_id, $groupName, count($optionValueIds));
                    $optionValueIds[] = $this->findOrCreateValue($groupId, $valueLabel, count($optionValueIds));
                }

                // Old variants without JSON options remain selectable as a one-value "Variant" group.
                if ($optionValueIds === []) {
                    $groupId = $this->findOrCreateGroup((int) $variant->product_id, 'Variant', 0);
                    $optionValueIds[] = $this->findOrCreateValue(
                        $groupId,
                        $this->legacyVariantLabel($variant),
                        0,
                    );
                }

                sort($optionValueIds);
                foreach ($optionValueIds as $optionValueId) {
                    DB::table('product_variant_option_values')->insertOrIgnore([
                        'product_variant_id' => $variant->id,
                        'product_option_value_id' => $optionValueId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $signature = hash('sha256', implode(',', $optionValueIds));
                $signatureTaken = DB::table('product_variants')
                    ->where('product_id', $variant->product_id)
                    ->where('option_signature', $signature)
                    ->where('id', '!=', $variant->id)
                    ->exists();

                // Preserve duplicate historical SKUs without allowing new duplicate combinations.
                if ($signatureTaken) {
                    $signature = hash('sha256', implode(',', $optionValueIds).'|legacy:'.$variant->id);
                }

                DB::table('product_variants')->where('id', $variant->id)->update([
                    'option_signature' => $signature,
                    'is_default' => ! DB::table('product_variants')
                        ->where('product_id', $variant->product_id)
                        ->where('is_default', true)
                        ->exists(),
                    'updated_at' => now(),
                ]);
            }
            }, 'product_variants.id', 'id');
    }

    private function findOrCreateGroup(int $productId, string $name, int $sortOrder): int
    {
        $baseCode = Str::slug($name) ?: 'option';
        $code = $baseCode;
        $suffix = 2;

        while (true) {
            $group = DB::table('product_option_groups')
                ->where('product_id', $productId)
                ->where('code', $code)
                ->first();

            if (! $group) {
                return DB::table('product_option_groups')->insertGetId([
                    'product_id' => $productId,
                    'name' => json_encode(['vi' => $name, 'en' => $name], JSON_UNESCAPED_UNICODE),
                    'code' => $code,
                    'display_type' => 'select',
                    'sort_order' => $sortOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $storedName = $this->decodeJson($group->name ?? null);
            if (($storedName['vi'] ?? $storedName['en'] ?? null) === $name) {
                return (int) $group->id;
            }

            $code = $baseCode.'-'.$suffix++;
        }
    }

    private function findOrCreateValue(int $groupId, string $label, int $sortOrder): int
    {
        $baseCode = Str::slug($label) ?: 'value';
        $code = $baseCode;
        $suffix = 2;

        while (true) {
            $value = DB::table('product_option_values')
                ->where('product_option_group_id', $groupId)
                ->where('code', $code)
                ->first();

            if (! $value) {
                return DB::table('product_option_values')->insertGetId([
                    'product_option_group_id' => $groupId,
                    'label' => json_encode(['vi' => $label, 'en' => $label], JSON_UNESCAPED_UNICODE),
                    'code' => $code,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $storedLabel = $this->decodeJson($value->label ?? null);
            if (($storedLabel['vi'] ?? $storedLabel['en'] ?? null) === $label) {
                return (int) $value->id;
            }

            $code = $baseCode.'-'.$suffix++;
        }
    }

    private function legacyVariantLabel(object $variant): string
    {
        $name = $this->decodeJson($variant->name ?? null);
        $label = is_array($name) ? ($name['vi'] ?? $name['en'] ?? reset($name)) : $name;

        return trim((string) ($label ?: $variant->sku ?: 'SKU '.$variant->id));
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
