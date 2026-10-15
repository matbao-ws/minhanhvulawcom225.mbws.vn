<?php

return [
    /*
    | IndexNow: tell Bing (and the other IndexNow engines: Yandex, Naver, Seznam…) the moment a
    | post, product or policy page is published, changed or removed, instead of waiting for a
    | crawl. Production only (App\Services\Seo\IndexNow). Google does not use IndexNow.
    */
    'indexnow' => (bool) env('SEO_INDEXNOW', true),

    'indexnow_endpoint' => 'https://api.indexnow.org/indexnow',
];
