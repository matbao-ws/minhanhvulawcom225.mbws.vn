<?php

return [
    'supportedLocales' => [
        'vi' => [
            'name' => 'Vietnamese',
            'script' => 'Latn',
            'native' => 'Tiếng Việt',
            'regional' => 'vi_VN',
        ],
        'en' => [
            'name' => 'English',
            'script' => 'Latn',
            'native' => 'English',
            'regional' => 'en_US',
        ],
        'ko' => [
            'name' => 'Korean',
            'script' => 'Kore',
            'native' => '한국어',
            'regional' => 'ko_KR',
        ],
    ],

    'useAcceptLanguageHeader' => false,
    'hideDefaultLocaleInURL' => false,
    'localesOrder' => ['vi', 'en', 'ko'],
    'localesMapping' => [],
    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),
    'urlsIgnored' => ['/api/*', '/up', '/storage/*'],
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
