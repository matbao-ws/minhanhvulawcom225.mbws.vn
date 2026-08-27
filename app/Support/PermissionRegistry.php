<?php

namespace App\Support;

/**
 * The canonical catalogue of admin permissions.
 *
 * Code — not the database — owns this list. `permissions:sync` and
 * {@see \Database\Seeders\PermissionSeeder} project it into the `permissions`
 * table, and PermissionRegistryDriftTest asserts that every code declared here
 * is actually enforced by a route and that no route demands a code that is
 * missing here. Adding a permission therefore means adding it in one place.
 *
 * Codes are `<module>.<action>`. Only actions that a real route enforces are
 * declared; inventing unenforced codes is what left `manage_roles` dead for
 * several releases.
 */
class PermissionRegistry
{
    /**
     * Modules, in the order the role form renders them.
     *
     * @var array<string, array{group: string, label: string, actions: array<int, string>}>
     */
    private const MODULES = [
        'products' => ['group' => 'catalog', 'label' => 'Sản phẩm, danh mục và thương hiệu', 'actions' => ['view', 'create', 'update', 'delete']],
        'orders' => ['group' => 'orders', 'label' => 'Đơn hàng', 'actions' => ['view', 'create', 'update']],
        'customers' => ['group' => 'orders', 'label' => 'Hồ sơ khách hàng', 'actions' => ['view']],
        'posts' => ['group' => 'content', 'label' => 'Bài viết và chuyên mục', 'actions' => ['view', 'create', 'update', 'delete']],
        'pages' => ['group' => 'content', 'label' => 'Trang nội dung', 'actions' => ['view', 'create', 'update', 'delete']],
        'banners' => ['group' => 'content', 'label' => 'Banner', 'actions' => ['view', 'create', 'update', 'delete']],
        'menus' => ['group' => 'content', 'label' => 'Menu điều hướng', 'actions' => ['view', 'create', 'update', 'delete']],
        'vouchers' => ['group' => 'marketing', 'label' => 'Mã giảm giá', 'actions' => ['view', 'create', 'update', 'delete']],
        'promotions' => ['group' => 'marketing', 'label' => 'Khuyến mãi', 'actions' => ['view', 'create', 'update', 'delete']],
        'reviews' => ['group' => 'marketing', 'label' => 'Đánh giá', 'actions' => ['view', 'update', 'delete']],
        'contacts' => ['group' => 'marketing', 'label' => 'Liên hệ', 'actions' => ['view', 'update', 'delete']],
        'users' => ['group' => 'users', 'label' => 'Tài khoản quản trị', 'actions' => ['view', 'create', 'update', 'delete']],
        'roles' => ['group' => 'users', 'label' => 'Vai trò và phân quyền', 'actions' => ['view', 'create', 'update', 'delete']],
        'settings' => ['group' => 'settings', 'label' => 'Cấu hình website', 'actions' => ['view', 'update']],
        'shipping' => ['group' => 'settings', 'label' => 'Đối tác vận chuyển', 'actions' => ['view', 'create', 'update', 'delete']],
        'payments' => ['group' => 'settings', 'label' => 'Phương thức thanh toán', 'actions' => ['view', 'create', 'update', 'delete']],
        'languages' => ['group' => 'settings', 'label' => 'Ngôn ngữ', 'actions' => ['view', 'create', 'update']],
        'media' => ['group' => 'system', 'label' => 'Thư viện tệp', 'actions' => ['view', 'create', 'delete']],
        'activity_logs' => ['group' => 'system', 'label' => 'Nhật ký hoạt động', 'actions' => ['view']],
        'translations' => ['group' => 'system', 'label' => 'Dịch nội dung tự động', 'actions' => ['use']],
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'view' => 'Xem',
        'create' => 'Thêm mới',
        'update' => 'Chỉnh sửa',
        'delete' => 'Xoá',
        'use' => 'Sử dụng',
    ];

    /**
     * Coarse pre-2026-08 codes mapped onto the granular codes that replaced
     * them. The migration replays this so an existing role keeps exactly the
     * access it had; nobody gains or loses a screen during the upgrade.
     *
     * @var array<string, array<int, string>>
     */
    private const LEGACY_CODES = [
        'manage_products' => ['products.view', 'products.create', 'products.update', 'products.delete'],
        'manage_orders' => ['orders.view', 'orders.create', 'orders.update'],
        'view_customers' => ['customers.view'],
        'manage_posts' => ['posts.view', 'posts.create', 'posts.update', 'posts.delete'],
        'manage_pages' => ['pages.view', 'pages.create', 'pages.update', 'pages.delete'],
        'manage_banners' => ['banners.view', 'banners.create', 'banners.update', 'banners.delete'],
        // One coarse code guarded both screens, so it expands into both modules.
        'manage_vouchers' => [
            'vouchers.view', 'vouchers.create', 'vouchers.update', 'vouchers.delete',
            'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
        ],
        'manage_reviews' => ['reviews.view', 'reviews.update', 'reviews.delete'],
        'manage_contacts' => ['contacts.view', 'contacts.update', 'contacts.delete'],
        'manage_users' => ['users.view', 'users.create', 'users.update', 'users.delete'],
        // `manage_roles` and `manage_languages` are deliberately absent. Both
        // screens were superadmin-only, so those codes could be ticked in the
        // role form and granted nothing. Expanding them now would hand real
        // access to roles that never had it — an upgrade must not promote
        // anybody. The new `roles.*` and `languages.*` have to be granted on
        // purpose.
        'manage_settings' => [
            'settings.view', 'settings.update',
            'shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete',
            'payments.view', 'payments.create', 'payments.update', 'payments.delete',
        ],
        'manage_media' => ['media.view', 'media.create', 'media.delete'],
        'view_audit_log' => ['activity_logs.view'],
        'translate_content' => ['translations.use'],
    ];

    /**
     * Every permission code, in catalogue order.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        $codes = [];
        foreach (self::MODULES as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $codes[] = "{$module}.{$action}";
            }
        }

        return $codes;
    }

    /**
     * Rows ready for `permissions` upserts.
     *
     * @return array<int, array{code: string, name: string, group: string, description: string}>
     */
    public static function definitions(): array
    {
        $rows = [];
        foreach (self::MODULES as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $rows[] = [
                    'code' => "{$module}.{$action}",
                    'name' => self::ACTION_LABELS[$action].' — '.$definition['label'],
                    'group' => $definition['group'],
                    'description' => $definition['label'],
                ];
            }
        }

        return $rows;
    }

    /** @return array<string, array{group: string, label: string, actions: array<int, string>}> */
    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    public static function has(string $code): bool
    {
        return in_array($code, self::codes(), true);
    }

    /**
     * Granular codes replacing a coarse legacy code. Unknown codes resolve to
     * nothing so stale data cannot smuggle in access.
     *
     * @return array<int, string>
     */
    public static function expandLegacy(string $code): array
    {
        return self::LEGACY_CODES[$code] ?? [];
    }

    /** @return array<string, array<int, string>> */
    public static function legacyMap(): array
    {
        return self::LEGACY_CODES;
    }

    /**
     * Codes a wildcard role expands to.
     *
     * `roles.*` is withheld because migration 2026_07_22_010000 established
     * that a blanket grant must not hand out permission management. `languages.*`
     * is withheld because that screen was superadmin-only before the granular
     * rewrite, and expanding a wildcard into it would widen access during an
     * upgrade. Both remain grantable explicitly.
     *
     * @return array<int, string>
     */
    public static function wildcardCodes(): array
    {
        return array_values(array_filter(
            self::codes(),
            static fn (string $code): bool => ! str_starts_with($code, 'roles.')
                && ! str_starts_with($code, 'languages.'),
        ));
    }
}
