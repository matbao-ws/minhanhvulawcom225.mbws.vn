<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\LanguageRegistry;

class ProductOptionService
{
    public function __construct(private readonly LanguageRegistry $languages)
    {
    }

    /**
     * Synchronise the product option catalogue. Values already used by a SKU can
     * be renamed or disabled, but cannot be silently deleted.
     */
    public function sync(Product $product, array $groups): void
    {
        DB::transaction(function () use ($product, $groups): void {
            $existingGroups = $product->optionGroups()->with('values.variants')->get()->keyBy('id');
            $submittedGroupIds = [];

            foreach (array_values($groups) as $groupOrder => $input) {
                $group = ! empty($input['id']) ? $existingGroups->get((int) $input['id']) : null;
                if (! empty($input['id']) && ! $group) {
                    throw new DomainException('Nhóm thuộc tính không thuộc sản phẩm này.');
                }

                $group ??= new ProductOptionGroup(['product_id' => $product->id]);
                $group->fill([
                    'name' => $this->translations($input['name'], $group->getTranslations('name')),
                    'code' => $this->uniqueGroupCode($product, $this->defaultValue($input['name']), $group->exists ? $group->id : null),
                    'display_type' => $input['display_type'] ?? 'select',
                    'sort_order' => $groupOrder,
                ])->save();
                $submittedGroupIds[] = $group->id;

                $existingValues = $group->values()->with('variants')->get()->keyBy('id');
                $submittedValueIds = [];
                foreach (array_values($input['values'] ?? []) as $valueOrder => $valueInput) {
                    $value = ! empty($valueInput['id']) ? $existingValues->get((int) $valueInput['id']) : null;
                    if (! empty($valueInput['id']) && ! $value) {
                        throw new DomainException('Giá trị thuộc tính không thuộc nhóm đã chọn.');
                    }

                    $value ??= new ProductOptionValue(['product_option_group_id' => $group->id]);
                    $value->fill([
                        'label' => $this->translations($valueInput['label'], $value->getTranslations('label')),
                        'code' => $this->uniqueValueCode($group, $this->defaultValue($valueInput['label']), $value->exists ? $value->id : null),
                        'color_hex' => $valueInput['color_hex'] ?? null,
                        'image_url' => $valueInput['image_url'] ?? null,
                        'sort_order' => $valueOrder,
                        'is_active' => (bool) ($valueInput['is_active'] ?? false),
                    ])->save();
                    $submittedValueIds[] = $value->id;
                }

                $removedValues = $existingValues->except($submittedValueIds);
                if ($removedValues->contains(fn (ProductOptionValue $value) => $value->variants->isNotEmpty())) {
                    throw new DomainException('Không thể xóa giá trị đang được một SKU sử dụng. Hãy vô hiệu hóa giá trị đó hoặc xóa SKU trước.');
                }
                ProductOptionValue::query()->whereIn('id', $removedValues->pluck('id')->all())->delete();
            }

            $removedGroups = $existingGroups->except($submittedGroupIds);
            if ($removedGroups->contains(fn (ProductOptionGroup $group) => $group->values->contains(fn (ProductOptionValue $value) => $value->variants->isNotEmpty()))) {
                throw new DomainException('Không thể xóa nhóm thuộc tính đang được SKU sử dụng.');
            }
            ProductOptionGroup::query()->whereIn('id', $removedGroups->pluck('id')->all())->delete();
        });
    }

    private function translations(string|array $value, array $existing = []): array
    {
        if (is_string($value)) {
            $value = trim($value);
            return [...$existing, ...collect($this->languages->codes())->mapWithKeys(fn (string $locale) => [$locale => $value])->all()];
        }

        return [...$existing, ...collect($value)
            ->filter(fn ($translation, $locale) => $this->languages->supports((string) $locale) && is_string($translation) && trim($translation) !== '')
            ->map(fn (string $translation) => trim($translation))->all()];
    }

    private function defaultValue(string|array $value): string
    {
        if (is_string($value)) return $value;
        return (string) ($value[$this->languages->defaultLocale()] ?? collect($value)->first() ?? '');
    }

    private function uniqueGroupCode(Product $product, string $name, ?int $ignoreId): string
    {
        return $this->uniqueCode(Str::slug($name) ?: 'option', ProductOptionGroup::query()->where('product_id', $product->id), $ignoreId);
    }

    private function uniqueValueCode(ProductOptionGroup $group, string $label, ?int $ignoreId): string
    {
        return $this->uniqueCode(Str::slug($label) ?: 'value', ProductOptionValue::query()->where('product_option_group_id', $group->id), $ignoreId);
    }

    private function uniqueCode(string $base, $query, ?int $ignoreId): string
    {
        $code = $base;
        $suffix = 2;
        while ((clone $query)->where('code', $code)->when($ignoreId, fn ($builder) => $builder->whereKeyNot($ignoreId))->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}
