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
     * Every active language, whatever the content translation mode. These are the
     * locales a /{locale}/… URL may carry: disabling multilingual content must not
     * make an existing localized URL return 404.
     */
    public function routable(): Collection
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

    public function supportsRoute(?string $locale): bool
    {
        return is_string($locale)
            && in_array($this->normalize($locale), $this->routable()->pluck('code')->all(), true);
    }

    /**
     * Languages the admin interface is offered in.
     *
     * Narrower than {@see routable()}: a language added for the storefront has
     * content tabs in admin forms, but no lang/ files to translate the admin itself.
     */
    public function admin(): Collection
    {
        $allowed = (array) config('multilingual.admin_locales', []);
        $languages = $this->routable();
        $admin = $languages->filter(fn ($language) => in_array($language->code, $allowed, true))->values();

        // A misconfigured list must not lock everyone out of the admin.
        return $admin->isNotEmpty()
            ? $admin
            : $languages->where('code', $this->defaultLocale())->values();
    }

    public function adminCodes(): array
    {
        return $this->admin()->pluck('code')->all();
    }

    public function supportsAdmin(?string $locale): bool
    {
        return is_string($locale) && in_array($this->normalize($locale), $this->adminCodes(), true);
    }

    /**
     * The admin locale to use for $locale: itself when the admin is offered in it,
     * otherwise admin_fallback_locale, then the default language, then any.
     */
    public function adminLocale(?string $locale): string
    {
        $candidates = [
            $this->normalize((string) $locale),
            (string) config('multilingual.admin_fallback_locale', 'en'),
            $this->defaultLocale(),
        ];

        foreach ($candidates as $candidate) {
            if ($this->supportsAdmin($candidate)) {
                return $candidate;
            }
        }

        return $this->adminCodes()[0] ?? $this->defaultLocale();
    }

    /**
     * The admin locale for a link that carries none (/admin, the owner's new order mail): the one
     * the admin last worked in, else the shop's default language, and only then
     * admin_fallback_locale. Without this, /admin always opened in English.
     */
    public function adminEntryLocale(?string $remembered = null): string
    {
        foreach ([$remembered, $this->defaultLocale()] as $candidate) {
            if ($this->supportsAdmin($candidate)) {
                return $this->normalize((string) $candidate);
            }
        }

        return $this->adminLocale(null);
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
        return $this->routable()->firstWhere('is_default', true)?->code
            ?? $this->routable()->first()?->code
            ?? config('multilingual.default_locale', 'vi');
    }

    public function fallbackLocale(): string
    {
        return $this->routable()->firstWhere('is_content_fallback', true)?->code
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
