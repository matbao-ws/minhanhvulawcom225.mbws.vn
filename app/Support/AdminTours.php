<?php

namespace App\Support;

/**
 * The guided tours of the admin (driver.js, public/admin-assets/js/admin-tour.js): which page
 * has one, and its steps in order. A step points at the element carrying `data-tour="<target>"`,
 * or at nothing (a centred popover). Its text is lang/<locale>/tours.php under the step's key.
 *
 * A step marked optional points at something only some admins or some states have (a feature,
 * a permission, a shipping carrier); the browser skips any step whose element is not on the page.
 * AdminToursTest renders each page and fails when a required target is missing, so a view change
 * cannot silently break a tour.
 *
 * Bump VERSION when tours change enough to be worth showing again: each admin then sees them
 * once more.
 */
final class AdminTours
{
    public const VERSION = 1;

    /** Route name => tour. Pages sharing a form share its tour. */
    public const ROUTES = [
        'admin.dashboard' => 'dashboard',
        'admin.products.index' => 'products_index',
        'admin.products.create' => 'products_form',
        'admin.products.edit' => 'products_form',
        'admin.orders.index' => 'orders_index',
        'admin.orders.show' => 'orders_show',
        'admin.posts.index' => 'posts_index',
        'admin.categories.index' => 'categories_index',
        'admin.customers.index' => 'customers_index',
        'admin.vouchers.index' => 'vouchers_index',
        'admin.settings.index' => 'settings',
    ];

    /**
     * Tour => steps: [text key, data-tour target or null, popover side, optional].
     *
     * @var array<string, list<array{0: string, 1: ?string, 2?: string, 3?: bool}>>
     */
    private const TOURS = [
        'dashboard' => [
            ['dashboard.intro', null],
            ['layout.sidebar', 'layout.sidebar', 'right'],
            ['layout.search', 'layout.search', 'bottom'],
            ['layout.notifications', 'layout.notifications', 'bottom'],
            ['dashboard.welcome', 'dashboard.welcome', 'bottom'],
            ['dashboard.stats', 'dashboard.stats', 'bottom', true],
            ['dashboard.charts', 'dashboard.charts', 'top', true],
            ['dashboard.recent_orders', 'dashboard.recent-orders', 'top', true],
            ['layout.help', 'layout.help', 'bottom'],
        ],
        'products_index' => [
            ['products_index.intro', null],
            ['products_index.create', 'products.create', 'bottom'],
            ['products_index.filters', 'products.filters', 'bottom'],
            ['products_index.select_all', 'products.select-all', 'right'],
            ['products_index.table', 'products.table', 'top'],
            ['layout.help', 'layout.help', 'bottom'],
        ],
        'products_form' => [
            ['products_form.intro', null],
            ['products_form.languages', 'product-form.languages', 'bottom'],
            ['products_form.media', 'product-form.media', 'top'],
            ['products_form.variants', 'product-form.variants', 'top'],
            ['products_form.pricing', 'product-form.pricing', 'top'],
            ['products_form.side', 'product-form.side', 'left', true],
            ['form.save', 'form.save', 'top'],
        ],
        'orders_index' => [
            ['orders_index.intro', null],
            ['orders_index.create', 'orders.create', 'bottom'],
            ['orders_index.filters', 'orders.filters', 'bottom', true],
            ['orders_index.table', 'orders.table', 'top'],
        ],
        'orders_show' => [
            ['orders_show.intro', null],
            ['orders_show.toolbar', 'order.toolbar', 'bottom'],
            ['orders_show.status', 'order.status', 'right'],
            ['orders_show.ship', 'order.ship', 'bottom', true],
            ['orders_show.history', 'order.history', 'left'],
            ['orders_show.sheet', 'order.sheet', 'top'],
        ],
        'posts_index' => [
            ['posts_index.intro', null],
            ['posts_index.create', 'posts.create', 'bottom'],
            ['posts_index.ai_draft', 'posts.ai-draft', 'bottom', true],
            ['posts_index.filters', 'posts.filters', 'bottom'],
            ['posts_index.select_all', 'posts.select-all', 'right'],
            ['posts_index.table', 'posts.table', 'top'],
        ],
        'categories_index' => [
            ['categories_index.intro', null],
            ['categories_index.create', 'categories.create', 'bottom'],
            ['categories_index.filters', 'categories.filters', 'bottom'],
            ['categories_index.select_all', 'categories.select-all', 'right'],
            ['categories_index.table', 'categories.table', 'top'],
        ],
        'customers_index' => [
            ['customers_index.intro', null],
            ['customers_index.count', 'customers.count', 'bottom'],
            ['customers_index.export', 'customers.export', 'bottom'],
            ['customers_index.search', 'customers.search', 'bottom', true],
            ['customers_index.table', 'customers.table', 'top'],
        ],
        'vouchers_index' => [
            ['vouchers_index.intro', null],
            ['vouchers_index.create', 'vouchers.create', 'bottom'],
            ['vouchers_index.filters', 'vouchers.filters', 'bottom'],
            ['vouchers_index.select_all', 'vouchers.select-all', 'right'],
            ['vouchers_index.table', 'vouchers.table', 'top'],
        ],
        'settings' => [
            ['settings.intro', null],
            ['settings.tabs', 'settings.tabs', 'bottom'],
            ['settings.general', 'settings.general', 'top'],
            ['settings.save', 'settings.save', 'top'],
        ],
    ];

    /** The tour of the page this route renders, ready for the browser, or null when it has none. */
    public static function forRoute(?string $route, ?int $userId): ?array
    {
        $tour = $route !== null ? (self::ROUTES[$route] ?? null) : null;
        if ($tour === null) {
            return null;
        }

        return [
            'id' => $tour,
            'version' => self::VERSION,
            'user' => $userId,
            'steps' => array_map(fn (array $step) => [
                'target' => $step[1],
                'side' => $step[2] ?? 'bottom',
                // driver.js renders these as HTML: send text only.
                'title' => e(__("tours.{$step[0]}.title")),
                'description' => e(__("tours.{$step[0]}.description")),
            ], self::TOURS[$tour]),
            'ui' => [
                'next' => e(__('tours.ui.next')),
                'prev' => e(__('tours.ui.prev')),
                'done' => e(__('tours.ui.done')),
                'skip' => e(__('tours.ui.skip')),
                'progress' => e(__('tours.ui.progress')),
            ],
        ];
    }

    /** @return list<string> every tour id */
    public static function tours(): array
    {
        return array_keys(self::TOURS);
    }

    /**
     * @return list<array{key: string, target: ?string, optional: bool}>
     */
    public static function steps(string $tour): array
    {
        return array_map(fn (array $step) => [
            'key' => $step[0],
            'target' => $step[1],
            'optional' => $step[3] ?? false,
        ], self::TOURS[$tour]);
    }
}
