<?php

namespace App\Models\Concerns;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Records edited with the admin SEO box (admin.shared.seo-box): posts, products, policy pages.
 * Their columns: seo_keys, seo_score, canonical_url, robots_index, robots_follow, og_title and
 * og_description (translatable), og_image, schema_faq.
 */
trait HasSeoBox
{
    /**
     * The image shown when the record is shared: stored relative, read as a URL (MediaUrl).
     *
     * @return Attribute<?string, ?string>
     */
    protected function ogImage(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?string => MediaUrl::resolve(is_string($value) ? $value : null),
            set: fn (mixed $value): ?string => MediaUrl::toStorable(is_string($value) ? $value : null),
        );
    }
}
