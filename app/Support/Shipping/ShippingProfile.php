<?php

namespace App\Support\Shipping;

/**
 * What a carrier needs to know about one unit of goods. Today only the weight; a length, width and
 * height are added here (and to ShippingProfileResolver) when a carrier needs them, so callers keep
 * asking for "the profile" and never read product columns themselves.
 */
final readonly class ShippingProfile
{
    public function __construct(
        /** Grams of one unit, as it is sent; null when nobody entered it. */
        public ?int $weightGrams = null,
    ) {}

    public static function unknown(): self
    {
        return new self;
    }

    public function hasWeight(): bool
    {
        return $this->weightGrams !== null && $this->weightGrams >= 1;
    }
}
