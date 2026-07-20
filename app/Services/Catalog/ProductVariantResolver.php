<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use DomainException;

class ProductVariantResolver
{
    /** Resolve one active SKU from client-selected option value IDs. */
    public function resolve(Product $product, array $optionValueIds, bool $lockForUpdate = false): ?ProductVariant
    {
        $groups = $product->optionGroups()->get(['id']);
        if ($groups->isEmpty()) {
            if ($optionValueIds !== []) {
                throw new DomainException('Sản phẩm này không có thuộc tính biến thể.');
            }

            return null;
        }

        $ids = collect($optionValueIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->count() !== $groups->count()) {
            throw new DomainException('Vui lòng chọn đúng một giá trị cho mỗi thuộc tính của sản phẩm.');
        }

        $values = ProductOptionValue::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get(['id', 'product_option_group_id']);

        if ($values->count() !== $ids->count()
            || $values->pluck('product_option_group_id')->unique()->sort()->values()->all() !== $groups->pluck('id')->sort()->values()->all()) {
            throw new DomainException('Tổ hợp thuộc tính không hợp lệ cho sản phẩm này.');
        }

        $query = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('option_signature', ProductVariant::signatureForOptionValueIds($ids->all()))
            ->where('is_active', true);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $variant = $query->first();
        if (! $variant) {
            throw new DomainException('Tổ hợp thuộc tính này hiện chưa có SKU để bán.');
        }

        return $variant;
    }
}
