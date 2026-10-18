<?php

return [
    'codes' => [
        'catalog',
        'cart',
        'cod_order',
        'online_payment',
        'voucher',
        'review',
        'shipping',
        'zalo_oa',
        'cms_page',
        'banner',
        'menu',
        'multi_admin',
        'product_variant',
        'payment_vnpay',
        'multilingual',
        'analytics',
        'ai_seo',
        'ai_research',
    ],

    /*
    | A feature that only works while another is on (checkout needs the catalogue, VNPAY needs
    | online payment…). The features page says so on the card and dims it while a parent is off.
    | Informational: FeatureGate still reads each switch on its own.
    */
    'requires' => [
        'cart' => ['catalog'],
        'cod_order' => ['cart'],
        'online_payment' => ['cart'],
        'payment_vnpay' => ['online_payment'],
        'voucher' => ['catalog'],
        'review' => ['catalog'],
        'product_variant' => ['catalog'],
        'shipping' => ['catalog'],
        'ai_research' => ['ai_seo'],
    ],

    'groups' => [
        'ecommerce' => [
            'catalog',
            'cart',
            'cod_order',
            'online_payment',
            'voucher',
            'review',
            'shipping',
            'product_variant',
            'payment_vnpay',
        ],
        'non_ecommerce' => [
            'cms_page',
            'banner',
            'menu',
            'zalo_oa',
            'multi_admin',
            'multilingual',
            'analytics',
            'ai_seo',
            'ai_research',
        ],
    ],
];
