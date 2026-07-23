<?php

namespace App\Models\Concerns;

use App\Models\LocalizedSlug;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

trait HasLocalizedSlugs
{
    public static function bootHasLocalizedSlugs(): void
    {
        static::deleting(function ($model): void {
            if (Schema::hasTable('localized_slugs')) {
                $model->localizedSlugs()->delete();
            }
        });
    }

    public function localizedSlugs(): MorphMany
    {
        return $this->morphMany(LocalizedSlug::class, 'sluggable');
    }

    public function canonicalSlug(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $slug = $this->localizedSlug($locale);

        return $slug ?: (string) $this->getAttribute('slug');
    }

    public function localizedSlug(string $locale): ?string
    {
        $loaded = $this->relationLoaded('localizedSlugs')
            ? $this->localizedSlugs->first(fn (LocalizedSlug $slug) => $slug->locale === $locale && $slug->is_current)
            : $this->localizedSlugs()->where('locale', $locale)->where('is_current', true)->first();

        return $loaded?->slug;
    }
}
