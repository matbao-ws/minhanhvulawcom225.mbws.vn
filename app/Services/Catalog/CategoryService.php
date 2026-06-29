<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryService
{
    public function __construct(private readonly \App\Services\CloudinaryService $cloudinaryService)
    {
    }
    public function create(array $data): Category
    {
        return Category::query()->create($this->payload($data));
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($this->payload($data, $category));

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function reorder(array $ids, int $startOrder = 0): void
    {
        foreach (array_values($ids) as $index => $id) {
            Category::query()
                ->whereKey($id)
                ->update(['sort_order' => $startOrder + $index]);
        }
    }

    private function payload(array $data, ?Category $category = null): array
    {
        $name = $this->translationValue($data['name'] ?? null, $category, 'name');
        $baseSlug = ($data['slug'] ?? null) ?: ($name[app()->getLocale()] ?? $name[$this->fallbackLocale()] ?? reset($name));
        $imageUrl = $this->imageUrl($data['image_file'] ?? null, $category);

        return [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $name,
            'slug' => $this->uniqueSlug((string) $baseSlug, $category?->id),
            'description' => $this->translationValue($data['description'] ?? null, $category, 'description'),
            'image_url' => $imageUrl,
            'sort_order' => (int) ($data['sort_order'] ?? $category?->sort_order ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function imageUrl(?UploadedFile $file, ?Category $category): ?string
    {
        if (! $file) {
            return $category?->image_url;
        }

        return $this->cloudinaryService->uploadFile($file, 'categories');
    }

    private function translationValue(?string $value, ?Category $category, string $attribute): array
    {
        $translations = $category?->getTranslations($attribute) ?? [];
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

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
