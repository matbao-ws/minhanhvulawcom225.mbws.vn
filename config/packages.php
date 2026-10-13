<?php

/*
|--------------------------------------------------------------------------
| Service packages (Hero tiers)
|--------------------------------------------------------------------------
|
| The package a project was sold with lives on the internal tool, looked up by
| the project's domain. App\Services\Packages\PackageSync turns it into
| `feature_settings` rows; everything else in the core keeps checking feature
| codes through FeatureGate and never a package code.
|
| PACKAGE_INFO_DOMAIN is the domain the tool knows the project by (usually
| the temporary *.mbws.vn one). It is deliberately separate from APP_URL so
| pointing the real domain at the site does not lose the package.
|
*/

return [
    'info_url' => env('PACKAGE_INFO_URL', 'https://tool.matbaows.com/server/get-package-info.php'),

    'domain' => env('PACKAGE_INFO_DOMAIN'),

    /*
    | Package code (as the tool returns it in `data.code`) => the feature codes
    | that package switches on. Codes missing from a list are switched off.
    | Every code must exist in config('features.codes'); a test enforces it.
    |
    | Draft from the Hero 1 → 7 sheet: confirm the package codes with the tool
    | before relying on HERO*.
    */
    'plans' => [
        'COBAN' => [
            'features' => ['catalog', 'cart', 'cod_order', 'online_payment', 'cms_page', 'banner', 'menu', 'zalo_oa', 'analytics'],
        ],
        // Hero 1 (Hiện diện): basic selling, VietQR, Zalo/Email alerts.
        'HERO1' => [
            'features' => ['catalog', 'cart', 'cod_order', 'online_payment', 'cms_page', 'banner', 'menu', 'zalo_oa', 'analytics'],
        ],
        // Hero 2 (Thương hiệu): + promotions/vouchers, reviews.
        'HERO2' => [
            'features' => [
                'catalog',
                'cart',
                'cod_order',
                'online_payment',
                'cms_page',
                'banner',
                'menu',
                'zalo_oa',
                'voucher',
                'review',
                'analytics',
            ],
        ],
        // Hero 3 (Cá nhân hoá): + variants V2, VNPAY, GHTK, multilingual.
        'HERO3' => [
            'features' => [
                'catalog',
                'cart',
                'cod_order',
                'online_payment',
                'cms_page',
                'banner',
                'menu',
                'zalo_oa',
                'voucher',
                'review',
                'product_variant',
                'payment_vnpay',
                'shipping',
                'multilingual',
                'analytics',
            ],
        ],
        // Hero 4 → 7: everything the core has today, keyword research (ai_research) included. Their extra items (loyalty,
        // ZNS, CRO, omni-channel, AI, Stripe/PayPal) do not exist in the core yet.
        'HERO4' => ['features' => '*'],
        'HERO5' => ['features' => '*'],
        'HERO6' => ['features' => '*'],
        'HERO7' => ['features' => '*'],
    ],

    /*
    | Quantity limits read from the tool's response. A missing, null or
    | non-positive value means "no limit" so a tool-side omission never locks a
    | shop out of its own admin.
    */
    'limits' => [
        // Products + posts together.
        'data_unit' => 'dataUnit',
        // numberBanner in the tool is the banner *design* service sold with the package, not a
        // cap on banners in the admin: the core does not read it.
        // Mails per calendar month, counted only while the shared SMTP is used.
        'email_monthly' => 'numberEmail',
    ],

    // How long a successful lookup is trusted, and how soon a failed one retries.
    'sync_ttl' => 3600,
    'retry_ttl' => 300,
];
