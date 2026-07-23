<?php

return [
    'enabled' => (bool) env('MULTILINGUAL_ENABLED', true),
    'mode' => env('MULTILINGUAL_MODE', 'manual'),

    // Content fallback is deliberately independent from Laravel's UI locale.
    'default_locale' => env('CONTENT_DEFAULT_LOCALE', 'vi'),
    'fallback_locale' => env('CONTENT_FALLBACK_LOCALE', 'vi'),

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
