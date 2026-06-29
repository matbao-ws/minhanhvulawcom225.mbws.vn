<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandService
{
    public function __construct(private readonly \App\Services\CloudinaryService $cloudinaryService)
    {
    }
    public function create(array $data): Brand
    {
        return Brand::query()->create($this->payload($data));
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($this->payload($data, $brand));

        return $brand->refresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }

    public function reorder(array $ids, int $startOrder = 0): void
    {
        foreach (array_values($ids) as $index => $id) {
            Brand::query()
                ->whereKey($id)
                ->update(['sort_order' => $startOrder + $index]);
        }
    }

    private function payload(array $data, ?Brand $brand = null): array
    {
        $name = $this->translationValue($data['name'] ?? null, $brand, 'name');
        $baseSlug = ($data['slug'] ?? null) ?: ($name[app()->getLocale()] ?? $name[$this->fallbackLocale()] ?? reset($name));
        $imageUrl = $this->imageUrl($data['image_file'] ?? null, $brand);

        return [
            'name' => $name,
            'slug' => $this->uniqueSlug((string) $baseSlug, $brand?->id),
            'description' => $this->translationValue($data['description'] ?? null, $brand, 'description'),
            'image_url' => $imageUrl,
            'sort_order' => (int) ($data['sort_order'] ?? $brand?->sort_order ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function imageUrl(?UploadedFile $file, ?Brand $brand): ?string
    {
        if (! $file) {
            return $brand?->image_url;
        }

        return $this->cloudinaryService->uploadFile($file, 'brands');
    }

    private function translationValue(?string $value, ?Brand $brand, string $attribute): array
    {
        $translations = $brand?->getTranslations($attribute) ?? [];
        $locale = app()->getLocale() ?: $this->fallbackLocale();
        $fallbackLocale = $this->fallbackLocale();
        $value = is_string($value) ? trim($value) : '';

        if ($value !== '') {
            $translations[$locale] = $value;
        }

        if ($locale !== $fallbackLocale && $value !== '' && empty($translations[$fallbackLocale])) {
            $translations[$fallbackLocale] = $value;
        }

        return array_filter($translations, fn ($translation) => $translation !== null && $translation !== '');
    }

    private function fallbackLocale(): string
    {
        return config('app.fallback_locale', config('app.locale', 'en'));
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value) ?: Str::random(8);
        $base = $slug;
        $counter = 2;

        while (Brand::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
