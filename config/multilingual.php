<?php

return [
    'enabled' => (bool) env('MULTILINGUAL_ENABLED', true),
    'mode' => env('MULTILINGUAL_MODE', 'manual'),

    // Content fallback is deliberately independent from Laravel's UI locale.
    'default_locale' => env('CONTENT_DEFAULT_LOCALE', 'vi'),
    'fallback_locale' => env('CONTENT_FALLBACK_LOCALE', 'vi'),

    // Languages the admin interface is offered in — the ones lang/ ships files for.
    // Any other active language is storefront content only: opening its
    // /{locale}/admin page redirects to admin_fallback_locale.
    'admin_locales' => ['vi', 'en'],
    'admin_fallback_locale' => env('ADMIN_FALLBACK_LOCALE', 'en'),

    // A region edited inline in one language only: do the other languages show that edit (true,
    // for themes written in one language) or their own template text (false, for themes whose
    // Blade is already translated — a /ja visitor must not read the Vietnamese edit)?
    'inline_edit_fallback' => (bool) env('INLINE_EDIT_FALLBACK', true),

    'translation' => [
        'provider' => env('TRANSLATION_PROVIDER', 'google'),
        'google' => [
            // Unofficial Google Translate web endpoint. It requires no API key,
            // but Google may rate-limit or change it without notice.
            'endpoint' => 'https://translate.googleapis.com/translate_a/single',
            'timeout' => (int) env('GOOGLE_TRANSLATE_TIMEOUT', 12),
        ],
        'cache_ttl' => (int) env('TRANSLATION_CACHE_TTL', 86400),
        'daily_character_limit' => (int) env('TRANSLATION_DAILY_CHAR_LIMIT', 500000),
    ],
];
