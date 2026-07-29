<?php

namespace App\Services;

use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LanguageRegistry
{
    private const CACHE_KEY = 'multilingual.active_languages.v1';

    private const ADMIN_CACHE_KEY = 'multilingual.admin_languages.v1';

    public function __construct(private readonly MultilingualSettings $settings) {}

    public function active(): Collection
    {
        if (! $this->settings->usesManualContent()) {
            return $this->defaultLanguageOnly();
        }

        if (! Schema::hasTable('languages')) {
            return $this->configuredLanguages();
        }

        return Cache::remember(self::CACHE_KEY, 300, fn () => Language::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get());
    }

    public function codes(): array
    {
        return $this->active()->pluck('code')->all();
    }

    /**
     * Locales available for the admin interface are independent from the
     * selected content translation mode. Disabling multilingual content must
     * not make an existing localized admin URL return 404.
     */
    public function admin(): Collection
    {
        if (! Schema::hasTable('languages')) {
            return $this->configuredLanguages();
        }

        return Cache::remember(self::ADMIN_CACHE_KEY, 300, fn () => Language::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get());
    }

    public function adminCodes(): array
    {
        return $this->admin()->pluck('code')->all();
    }

    public function supportsAdmin(?string $locale): bool
    {
        return is_string($locale) && in_array($this->normalize($locale), $this->adminCodes(), true);
    }

    public function supports(?string $locale): bool
    {
        return is_string($locale) && in_array($this->normalize($locale), $this->codes(), true);
    }

    public function resolve(?string $locale): string
    {
        $locale = $this->normalize((string) $locale);

        return $this->supports($locale) ? $locale : $this->defaultLocale();
    }

    public function defaultLocale(): string
    {
        return $this->admin()->firstWhere('is_default', true)?->code
            ?? $this->admin()->first()?->code
            ?? config('multilingual.default_locale', 'vi');
    }

    public function fallbackLocale(): string
    {
        return $this->admin()->firstWhere('is_content_fallback', true)?->code
            ?? $this->defaultLocale();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::ADMIN_CACHE_KEY);
    }

    public function normalize(string $locale): string
    {
        $locale = str_replace('_', '-', trim($locale));
        $parts = explode('-', $locale);

        return strtolower($parts[0] ?? '');
    }

    private function configuredLanguages(): Collection
    {
        return collect(config('laravellocalization.supportedLocales', []))
            ->map(fn (array $language, string $code) => (object) [
                'code' => $code,
                'name' => $language['name'] ?? $code,
                'native_name' => $language['native'] ?? $code,
                'regional' => $language['regional'] ?? null,
                'flag_path' => null,
                'is_active' => true,
                'is_default' => $code === config('multilingual.default_locale', 'vi'),
                'is_content_fallback' => $code === config('multilingual.fallback_locale', 'vi'),
                'sort_order' => 0,
            ])->values();
    }

    private function defaultLanguageOnly(): Collection
    {
        if (Schema::hasTable('languages')) {
            $language = Language::query()->where('is_default', true)->first()
                ?? Language::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();

            if ($language) {
                return collect([$language]);
            }
        }

        $languages = $this->configuredLanguages();
        $defaultLocale = config('multilingual.default_locale', 'vi');

        return $languages->where('code', $defaultLocale)->values()->whenEmpty(
            fn (Collection $collection) => $languages->take(1)->values(),
        );
    }
}
