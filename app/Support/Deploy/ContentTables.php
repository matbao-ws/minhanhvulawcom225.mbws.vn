<?php

namespace App\Support\Deploy;

/**
 * What a first deploy may carry from the developer's database to the website's.
 *
 * An ALLOWLIST: the catalogue, the content and the shop's own settings a developer enters while
 * building the site. Never people (accounts, customers, orders, reviews, contacts), never what the
 * package sync or the seed own (features, permissions, roles), never sessions, queues or logs, and
 * never anything encrypted with APP_KEY — the website has its own key, so payment and shipping
 * settings, mail and Cloudinary credentials would not decrypt there.
 */
final class ContentTables
{
    /** Imported in this order, cleared in reverse. Parents before children. */
    public const TABLES = [
        'languages',
        'project_settings',
        'categories',
        'brands',
        'products',
        'product_images',
        'product_option_groups',
        'product_option_values',
        'product_variants',
        'product_variant_option_values',
        'post_categories',
        'posts',
        'pages',
        'page_revisions',
        'localized_slugs',
        'banners',
        'menus',
        'menu_items',
        'site_blocks',
        'site_block_revisions',
        'site_lists',
        'site_sections',
        'site_section_revisions',
        'promotions',
        'promotion_targets',
        'vouchers',
    ];

    /** project_settings is shared with secrets and package state: only these keys travel. */
    public const SETTING_KEYS = [
        'shop_name',
        'logo_url',
        'favicon_url',
        'contact',
        'theme',
        'seo',
        'social_links',
        'multilingual',
        'embed_header',
        'embed_footer',
        'google_analytics',
    ];

    /**
     * Any row here means the website is no longer new: an import would overwrite a live shop.
     * Accounts are checked separately (the seed's superadmin is allowed).
     */
    public const LIVE_DATA_TABLES = ['orders', 'products', 'posts', 'pages'];

    /** Usage counted by the developer's test orders, which do not travel. */
    public const RESET_COUNTERS = [
        'vouchers' => 'used_count',
        'promotions' => 'used_count',
        'promotion_targets' => 'used_count',
    ];
}
