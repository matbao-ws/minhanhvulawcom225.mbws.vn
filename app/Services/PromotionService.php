<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use DomainException;

class PromotionService
{
    /**
     * Returns the best non-stacked promotion for one sellable SKU. Voucher
     * discount is intentionally calculated later on the promotional subtotal.
     */
    public function quote(Product $product, ?ProductVariant $variant, float $originalPrice, int $quantity): array
    {
        $candidates = Promotion::query()
            ->activeNow()
            ->with(['targets' => fn ($query) => $query->where('product_id', $product->id)
                ->where(fn ($query) => $query->whereNull('product_variant_id')->orWhere('product_variant_id', $variant?->id))])
            ->where(function ($query) use ($product, $variant) {
                $query->where('applies_to', 'all_products')
                    ->orWhereHas('targets', fn ($targetQuery) => $targetQuery
                        ->where('product_id', $product->id)
                        ->where(fn ($targetQuery) => $targetQuery->whereNull('product_variant_id')->orWhere('product_variant_id', $variant?->id)));
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $best = null;
        foreach ($candidates as $promotion) {
            if ($quantity < $promotion->min_quantity || ! $promotion->isAvailableFor($quantity)) {
                continue;
            }

            $target = $promotion->applies_to === 'selected'
                ? $promotion->targets->sortByDesc(fn (PromotionTarget $target) => $target->product_variant_id !== null)->first()
                : null;
            if ($target && ! $target->canReserve($quantity)) {
                continue;
            }

            $unitPrice = $this->discountedUnitPrice($promotion, $originalPrice);
            $candidate = compact('promotion', 'target', 'unitPrice');
            if (! $best
                || $promotion->priority > $best['promotion']->priority
                || ($promotion->priority === $best['promotion']->priority && $unitPrice < $best['unitPrice'])) {
                $best = $candidate;
            }
        }

        $unitPrice = $best['unitPrice'] ?? $originalPrice;
        $discount = max(0, ($originalPrice - $unitPrice) * $quantity);

        return [
            'promotion' => $best['promotion'] ?? null,
            'target' => $best['target'] ?? null,
            'original_unit_price' => $originalPrice,
            'unit_price' => $unitPrice,
            'discount' => $discount,
        ];
    }

    /** Locks and consumes campaign/SKU quota after checkout creates its order. */
    public function reserve(array $quote, int $quantity): void
    {
        /** @var Promotion|null $quotedPromotion */
        $quotedPromotion = $quote['promotion'] ?? null;
        if (! $quotedPromotion) {
            return;
        }

        $promotion = Promotion::query()->lockForUpdate()->find($quotedPromotion->id);
        if (! $promotion || ! $promotion->isAvailableFor($quantity)) {
            throw new DomainException('Chương trình khuyến mãi vừa hết hiệu lực hoặc đã hết lượt.');
        }

        $target = null;
        if (! empty($quote['target'])) {
            $target = PromotionTarget::query()->lockForUpdate()->find($quote['target']->id);
            if (! $target || ! $target->canReserve($quantity)) {
                throw new DomainException('Flash sale của SKU này đã hết suất.');
            }
        }

        $promotion->increment('used_count', $quantity);
        $target?->increment('used_count', $quantity);
    }

    private function discountedUnitPrice(Promotion $promotion, float $originalPrice): float
    {
        return match ($promotion->discount_type) {
            'percentage' => max(0, $originalPrice * (1 - ((float) $promotion->value / 100))),
            'fixed_amount' => max(0, $originalPrice - (float) $promotion->value),
            'fixed_price' => min($originalPrice, (float) $promotion->value),
            default => $originalPrice,
        };
    }
}
