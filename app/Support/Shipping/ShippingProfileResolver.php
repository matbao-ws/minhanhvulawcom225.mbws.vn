<?php

namespace App\Support\Shipping;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * The one place that decides the shipping profile (weight) of goods, so every carrier and every
 * screen agrees. Order of precedence, most specific first:
 *
 *   1. the variant's own weight        (product_variants.weight_grams)
 *   2. the product's weight            (products.weight_grams) — for a product without variants it is the
 *                                      weight; for one with variants it is the default of all of them
 *   3. nothing                         — the caller falls back to the shop's default parcel (GHN settings)
 *
 * A weight of 0 counts as "not entered".
 */
class ShippingProfileResolver
{
    /** The heaviest parcel the carriers accept, in grams. */
    public const MAX_PARCEL_GRAMS = 50000;

    /** Validation of a weight field, shared by the product and variant forms. */
    public static function weightRules(): array
    {
        return ['nullable', 'integer', 'min:0', 'max:'.self::MAX_PARCEL_GRAMS];
    }

    public function forVariant(?ProductVariant $variant, ?Product $product = null): ShippingProfile
    {
        foreach ([$variant?->weight_grams, $product?->weight_grams] as $grams) {
            if ((int) $grams >= 1) {
                return new ShippingProfile((int) $grams);
            }
        }

        return ShippingProfile::unknown();
    }

    public function forItem(OrderItem $item): ShippingProfile
    {
        return $this->forVariant($item->variant, $item->product);
    }

    /**
     * Grams of the whole parcel: the sum over the lines when every line has a weight, otherwise the
     * shop's default parcel — a partial sum would under-declare and the carrier would bill the difference.
     */
    public function parcelWeight(Order $order, int $default): int
    {
        $order->loadMissing(['items.variant', 'items.product']);

        $total = 0;
        foreach ($order->items as $item) {
            $profile = $this->forItem($item);
            if (! $profile->hasWeight()) {
                return max(1, $default);
            }
            $total += $profile->weightGrams * (int) $item->quantity;
        }

        return max(1, min(self::MAX_PARCEL_GRAMS, $total ?: $default));
    }

    /**
     * Kilograms of one unit of each line, in the order's line order, for carriers that list every product
     * with its own weight (GHTK). A line without a weight takes an equal share of the parcel weight the
     * admin confirmed, so the lines always add up to that parcel instead of to an invented constant.
     *
     * @return array<int, float> order item id => kg per unit
     */
    public function unitKilograms(Order $order, int $parcelGrams): array
    {
        $order->loadMissing(['items.variant', 'items.product']);

        $known = 0;
        $unknownUnits = 0;
        foreach ($order->items as $item) {
            $profile = $this->forItem($item);
            $profile->hasWeight() ? $known += $profile->weightGrams * (int) $item->quantity : $unknownUnits += (int) $item->quantity;
        }
        $share = $unknownUnits > 0 ? max(1, intdiv(max(0, $parcelGrams - $known), $unknownUnits)) : 0;

        $weights = [];
        foreach ($order->items as $item) {
            $profile = $this->forItem($item);
            $weights[$item->id] = round(($profile->hasWeight() ? $profile->weightGrams : $share) / 1000, 3);
        }

        return $weights;
    }
}
