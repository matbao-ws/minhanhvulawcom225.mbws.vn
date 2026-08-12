<?php

namespace App\Models\Concerns;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Keeps media columns portable: stored as a root-relative path, read back as a
 * URL built from the current APP_URL. See {@see MediaUrl}.
 */
trait HasMediaUrls
{
    protected function imageUrl(): Attribute
    {
        return self::mediaUrlAttribute();
    }

    protected static function mediaUrlAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?string => MediaUrl::resolve(is_string($value) ? $value : null),
            set: fn (mixed $value): ?string => MediaUrl::toStorable(is_string($value) ? $value : null),
        );
    }
}
