<?php

namespace App\Services;

use App\Models\LocalizedSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LocalizedSlugService
{
    public function __construct(private readonly LanguageRegistry $languages)
    {
    }

    /**
     * Synchronize canonical slugs without deleting aliases that may already be indexed.
     *
     * @param array<string, string|null> $slugs
     * @param array<string, string|null> $names
     */
    public function sync(Model $model, array $slugs, array $names = []): void
    {
        if (! Schema::hasTable('localized_slugs')) {
            return;
        }

        DB::transaction(function () use ($model, $slugs, $names): void {
            foreach ($this->languages->codes() as $locale) {
                if (! array_key_exists($locale, $slugs) && ! array_key_exists($locale, $names)) {
                    continue;
                }

                $source = trim((string) ($slugs[$locale] ?? '')) ?: trim((string) ($names[$locale] ?? ''));
                if ($source === '') {
                    continue;
                }

                $slug = $this->uniqueSlug($model, $locale, $source);
                $current = $model->localizedSlugs()
                    ->where('locale', $locale)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->first();

                if ($current?->slug === $slug) {
                    continue;
                }

                $model->localizedSlugs()
                    ->where('locale', $locale)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                $alias = $model->localizedSlugs()
                    ->where('locale', $locale)
                    ->where('slug', $slug)
                    ->first();

                if ($alias) {
                    $alias->update(['is_current' => true]);
                } else {
                    $model->localizedSlugs()->create([
                        'locale' => $locale,
                        'slug' => $slug,
                        'is_current' => true,
                    ]);
                }
            }
        });
    }

    public function find(string $modelClass, string|int $idOrSlug, string $locale): ?Model
    {
        if (ctype_digit((string) $idOrSlug)) {
            return $modelClass::query()->find((int) $idOrSlug);
        }

        if (Schema::hasTable('localized_slugs')) {
            $type = (new $modelClass())->getMorphClass();
            $localized = LocalizedSlug::query()
                ->where('sluggable_type', $type)
                ->where('locale', $locale)
                ->where('slug', $idOrSlug)
                ->first();

            if ($localized) {
                return $modelClass::query()->find($localized->sluggable_id);
            }
        }

        return $modelClass::query()->where('slug', $idOrSlug)->first();
    }

    private function uniqueSlug(Model $model, string $locale, string $value): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $candidate = $base;
        $counter = 2;
        $type = $model->getMorphClass();

        while (LocalizedSlug::query()
            ->where('sluggable_type', $type)
            ->where('locale', $locale)
            ->where('slug', $candidate)
            ->where(function ($query) use ($model) {
                $query->where('sluggable_id', '!=', $model->getKey());
            })
            ->exists()) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }
}
