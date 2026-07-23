<?php

namespace App\Services;

use App\Models\Language;
use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MultilingualSettings
{
    private const CACHE_KEY = 'multilingual.project_settings.v1';

    public const MODE_MANUAL = 'manual';

    public const MODE_GTRANSLATE = 'gtranslate';

    /** @return array<string, mixed> */
    public function get(): array
    {
        $stored = [];

        if (Schema::hasTable('project_settings')) {
            $stored = Cache::remember(self::CACHE_KEY, 300, function (): array {
                $value = ProjectSetting::query()
                    ->where('setting_key', 'multilingual')
                    ->first()
                    ?->setting_value;

                return is_array($value) ? $value : [];
            });
        }

        $settings = array_replace_recursive($this->defaults(), $stored);
        $mode = in_array($settings['mode'] ?? null, [self::MODE_MANUAL, self::MODE_GTRANSLATE], true)
            ? $settings['mode']
            : self::MODE_MANUAL;

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'mode' => $mode,
            'gtranslate' => [
                'target_locales' => collect(data_get($settings, 'gtranslate.target_locales', []))
                    ->filter(fn ($locale) => is_string($locale) && preg_match('/^[a-z]{2,3}$/', $locale))
                    ->unique()
                    ->values()
                    ->all(),
                'widget_look' => in_array(data_get($settings, 'gtranslate.widget_look'), ['float', 'dropdown_with_flags'], true)
                    ? data_get($settings, 'gtranslate.widget_look')
                    : 'float',
                'position' => in_array(data_get($settings, 'gtranslate.position'), ['bottom_left', 'bottom_right', 'top_left', 'top_right', 'inline'], true)
                    ? data_get($settings, 'gtranslate.position')
                    : 'bottom_right',
                'detect_browser_language' => (bool) data_get($settings, 'gtranslate.detect_browser_language', false),
                'native_language_names' => (bool) data_get($settings, 'gtranslate.native_language_names', true),
            ],
        ];
    }

    /** @param array<string, mixed> $settings */
    public function update(array $settings): void
    {
        ProjectSetting::query()->updateOrCreate(
            ['setting_key' => 'multilingual'],
            [
                'setting_value' => [
                    'enabled' => (bool) ($settings['enabled'] ?? false),
                    'mode' => $settings['mode'] ?? self::MODE_MANUAL,
                    'gtranslate' => [
                        'target_locales' => array_values(array_unique(data_get($settings, 'gtranslate.target_locales', []))),
                        'widget_look' => data_get($settings, 'gtranslate.widget_look', 'float'),
                        'position' => data_get($settings, 'gtranslate.position', 'bottom_right'),
                        'detect_browser_language' => (bool) data_get($settings, 'gtranslate.detect_browser_language', false),
                        'native_language_names' => (bool) data_get($settings, 'gtranslate.native_language_names', true),
                    ],
                ],
                'updated_at' => now(),
            ],
        );

        $this->forget();
    }

    public function enabled(): bool
    {
        return $this->get()['enabled'];
    }

    public function mode(): string
    {
        return $this->get()['mode'];
    }

    public function usesManualContent(): bool
    {
        return $this->enabled() && $this->mode() === self::MODE_MANUAL;
    }

    public function usesGTranslate(): bool
    {
        return $this->enabled() && $this->mode() === self::MODE_GTRANSLATE;
    }

    public function sourceLocale(): string
    {
        if (Schema::hasTable('languages')) {
            return Language::query()->where('is_default', true)->value('code')
                ?? Language::query()->where('is_active', true)->orderBy('sort_order')->value('code')
                ?? config('multilingual.default_locale', 'vi');
        }

        return config('multilingual.default_locale', 'vi');
    }

    /** @return array<string, mixed> */
    public function publicConfig(): array
    {
        return [
            'enabled' => $this->enabled(),
            'mode' => $this->enabled() ? $this->mode() : 'disabled',
            'source_locale' => $this->sourceLocale(),
            'widget' => $this->usesGTranslate() ? [
                'container_class' => 'gtranslate_wrapper',
                'script_url' => $this->widgetScriptUrl(),
                'settings' => $this->widgetSettings(),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function widgetSettings(): array
    {
        $settings = $this->get();
        $position = data_get($settings, 'gtranslate.position', 'bottom_right');
        $sourceLocale = $this->sourceLocale();
        $targetLocales = collect(data_get($settings, 'gtranslate.target_locales', []));

        if (Schema::hasTable('languages')) {
            $activeLocales = Language::query()->where('is_active', true)->pluck('regional', 'code');
            $targetLocales = $targetLocales
                ->filter(fn (string $locale) => $activeLocales->has($locale))
                ->map(fn (string $locale) => $this->toGTranslateLocale($locale, $activeLocales->get($locale)));
            $sourceRegional = $activeLocales->get($sourceLocale);
        }

        $widget = [
            'default_language' => $this->toGTranslateLocale($sourceLocale, $sourceRegional ?? null),
            'languages' => $targetLocales
                ->prepend($this->toGTranslateLocale($sourceLocale, $sourceRegional ?? null))
                ->unique()
                ->values()
                ->all(),
            'wrapper_selector' => '.gtranslate_wrapper',
            'native_language_names' => (bool) data_get($settings, 'gtranslate.native_language_names', true),
            'detect_browser_language' => (bool) data_get($settings, 'gtranslate.detect_browser_language', false),
        ];

        if ($position === 'inline') {
            $widget['switcher_horizontal_position'] = 'inline';
        } else {
            [$vertical, $horizontal] = explode('_', $position, 2);
            $widget['switcher_vertical_position'] = $vertical;
            $widget['switcher_horizontal_position'] = $horizontal;
        }

        return $widget;
    }

    public function widgetScriptUrl(): string
    {
        return data_get($this->get(), 'gtranslate.widget_look') === 'dropdown_with_flags'
            ? 'https://cdn.gtranslate.net/widgets/latest/dwf.js'
            : 'https://cdn.gtranslate.net/widgets/latest/float.js';
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'enabled' => (bool) config('multilingual.enabled', true),
            'mode' => config('multilingual.mode', self::MODE_MANUAL),
            'gtranslate' => [
                'target_locales' => ['en'],
                'widget_look' => 'float',
                'position' => 'bottom_right',
                'detect_browser_language' => false,
                'native_language_names' => true,
            ],
        ];
    }

    private function toGTranslateLocale(string $locale, ?string $regional = null): string
    {
        if ($locale !== 'zh') {
            return $locale;
        }

        return str_ends_with(strtoupper((string) $regional), '_TW') ? 'zh-TW' : 'zh-CN';
    }
}
