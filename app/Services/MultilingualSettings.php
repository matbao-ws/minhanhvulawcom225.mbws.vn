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
                'source_locale' => is_string(data_get($settings, 'gtranslate.source_locale')) && !empty(data_get($settings, 'gtranslate.source_locale'))
                    ? data_get($settings, 'gtranslate.source_locale')
                    : $this->defaultSystemSourceLocale(),
                'target_locales' => collect(data_get($settings, 'gtranslate.target_locales', []))
                    ->filter(fn ($locale) => is_string($locale) && ($locale === 'all' || preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/i', $locale)))
                    ->unique()
                    ->values()
                    ->all(),
                'widget_look' => in_array(data_get($settings, 'gtranslate.widget_look'), ['float', 'dropdown_with_flags', 'flags_dropdown', 'dropdown', 'flags', 'flags_name', 'flags_code', 'lang_names', 'lang_codes', 'globe', 'popup', 'popup_search', 'uswds'], true)
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
                        'source_locale' => data_get($settings, 'gtranslate.source_locale', $this->defaultSystemSourceLocale()),
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
        $settings = $this->get();
        if (($settings['mode'] ?? null) === self::MODE_GTRANSLATE) {
            $configured = data_get($settings, 'gtranslate.source_locale');
            if (is_string($configured) && !empty($configured)) {
                return $configured;
            }
        }

        return $this->defaultSystemSourceLocale();
    }

    public function defaultSystemSourceLocale(): string
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
        $rawTargetLocales = collect(data_get($settings, 'gtranslate.target_locales', []));
        if ($rawTargetLocales->contains('all')) {
            $rawTargetLocales = collect(array_keys(self::allGTranslateLanguages()));
        }

        $activeLocales = collect();
        if (Schema::hasTable('languages')) {
            $activeLocales = Language::query()->where('is_active', true)->pluck('regional', 'code');
        }

        $sourceRegional = $activeLocales->get($sourceLocale);
        $mappedDefault = $this->toGTranslateLocale($sourceLocale, $sourceRegional ?? null);

        $targetLocales = $rawTargetLocales->map(function (string $locale) use ($activeLocales) {
            $regional = $activeLocales->get($locale);
            return $this->toGTranslateLocale($locale, $regional);
        });

        $widget = [
            'default_language' => $mappedDefault,
            'languages' => $targetLocales
                ->prepend($mappedDefault)
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
        $look = data_get($this->get(), 'gtranslate.widget_look', 'float');
        $map = [
            'float' => 'https://cdn.gtranslate.net/widgets/latest/float.js',
            'dropdown_with_flags' => 'https://cdn.gtranslate.net/widgets/latest/dwf.js',
            'flags_dropdown' => 'https://cdn.gtranslate.net/widgets/latest/fd.js',
            'dropdown' => 'https://cdn.gtranslate.net/widgets/latest/dropdown.js',
            'flags' => 'https://cdn.gtranslate.net/widgets/latest/flags.js',
            'flags_name' => 'https://cdn.gtranslate.net/widgets/latest/fn.js',
            'flags_code' => 'https://cdn.gtranslate.net/widgets/latest/fc.js',
            'lang_names' => 'https://cdn.gtranslate.net/widgets/latest/ln.js',
            'lang_codes' => 'https://cdn.gtranslate.net/widgets/latest/lc.js',
            'globe' => 'https://cdn.gtranslate.net/widgets/latest/globe.js',
            'popup' => 'https://cdn.gtranslate.net/widgets/latest/popup.js',
            'popup_search' => 'https://cdn.gtranslate.net/widgets/latest/ps.js',
            'uswds' => 'https://cdn.gtranslate.net/widgets/latest/uswds.js',
        ];

        return $map[$look] ?? $map['float'];
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string> */
    public static function allGTranslateLanguages(): array
    {
        return [
            'en' => 'English',
            'vi' => 'Vietnamese (Tiếng Việt)',
            'zh-CN' => 'Chinese Simplified (Trung Giản thể)',
            'zh-TW' => 'Chinese Traditional (Trung Phồn thể)',
            'ja' => 'Japanese (Tiếng Nhật)',
            'ko' => 'Korean (Tiếng Hàn)',
            'fr' => 'French (Tiếng Pháp)',
            'de' => 'German (Tiếng Đức)',
            'es' => 'Spanish (Tây Ban Nha)',
            'ru' => 'Russian (Tiếng Nga)',
            'ar' => 'Arabic (Tiếng Ả Rập)',
            'th' => 'Thai (Tiếng Thái)',
            'id' => 'Indonesian (Indonesia)',
            'ms' => 'Malay (Mã Lai)',
            'it' => 'Italian (Tiếng Ý)',
            'pt' => 'Portuguese (Bồ Đào Nha)',
            'nl' => 'Dutch (Hà Lan)',
            'pl' => 'Polish (Ba Lan)',
            'tr' => 'Turkish (Thổ Nhĩ Kỳ)',
            'hi' => 'Hindi (Ấn Độ)',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'cs' => 'Czech',
            'el' => 'Greek',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'uk' => 'Ukrainian',
            'bg' => 'Bulgarian',
            'hr' => 'Croatian',
            'sr' => 'Serbian',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'he' => 'Hebrew',
            'fa' => 'Persian',
            'ur' => 'Urdu',
            'bn' => 'Bengali',
            'ta' => 'Tamil',
            'te' => 'Telugu',
            'kn' => 'Kannada',
            'ml' => 'Malayalam',
            'mr' => 'Marathi',
            'gu' => 'Gujarati',
            'pa' => 'Punjabi',
            'my' => 'Myanmar (Burmese)',
            'km' => 'Khmer',
            'lo' => 'Lao',
            'tl' => 'Filipino',
            'af' => 'Afrikaans',
            'sq' => 'Albanian',
            'am' => 'Amharic',
            'hy' => 'Armenian',
            'az' => 'Azerbaijani',
            'eu' => 'Basque',
            'be' => 'Belarusian',
            'bs' => 'Bosnian',
            'ca' => 'Catalan',
            'ceb' => 'Cebuano',
            'ny' => 'Chichewa',
            'co' => 'Corsican',
            'et' => 'Estonian',
            'eo' => 'Esperanto',
            'fy' => 'Frisian',
            'gl' => 'Galician',
            'ka' => 'Georgian',
            'ht' => 'Haitian Creole',
            'ha' => 'Hausa',
            'haw' => 'Hawaiian',
            'hmn' => 'Hmong',
            'is' => 'Icelandic',
            'ig' => 'Igbo',
            'ga' => 'Irish',
            'jw' => 'Javanese',
            'kk' => 'Kazakh',
            'ku' => 'Kurdish (Kurmanji)',
            'ky' => 'Kyrgyz',
            'la' => 'Latin',
            'lv' => 'Latvian',
            'lt' => 'Lithuanian',
            'lb' => 'Luxembourgish',
            'mk' => 'Macedonian',
            'mg' => 'Malagasy',
            'mt' => 'Maltese',
            'mi' => 'Maori',
            'mn' => 'Mongolian',
            'ne' => 'Nepali',
            'ps' => 'Pashto',
            'sm' => 'Samoan',
            'gd' => 'Scottish Gaelic',
            'sn' => 'Shona',
            'sd' => 'Sindhi',
            'si' => 'Sinhala',
            'so' => 'Somali',
            'st' => 'Sesotho',
            'su' => 'Sundanese',
            'sw' => 'Swahili',
            'tg' => 'Tajik',
            'uz' => 'Uzbek',
            'cy' => 'Welsh',
            'xh' => 'Xhosa',
            'yi' => 'Yiddish',
            'yo' => 'Yoruba',
            'zu' => 'Zulu',
        ];
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
