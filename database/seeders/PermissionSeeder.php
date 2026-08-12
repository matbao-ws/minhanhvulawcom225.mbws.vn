<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['code' => 'manage_products', 'name' => 'Quản lý sản phẩm', 'group' => 'catalog'],
            ['code' => 'manage_orders', 'name' => 'Quản lý đơn hàng', 'group' => 'orders'],
            ['code' => 'view_customers', 'name' => 'Xem hồ sơ khách hàng', 'group' => 'orders'],
            ['code' => 'view_audit_log', 'name' => 'Xem nhật ký hoạt động', 'group' => 'system'],
            ['code' => 'manage_vouchers', 'name' => 'Quản lý mã giảm giá', 'group' => 'marketing'],
            ['code' => 'manage_banners', 'name' => 'Quản lý banner', 'group' => 'marketing'],
            ['code' => 'manage_posts', 'name' => 'Quản lý bài viết', 'group' => 'marketing'],
            ['code' => 'manage_pages', 'name' => 'Quản lý trang nội dung', 'group' => 'marketing'],
            ['code' => 'manage_reviews', 'name' => 'Quản lý đánh giá', 'group' => 'marketing'],
            ['code' => 'manage_contacts', 'name' => 'Quản lý liên hệ', 'group' => 'marketing'],
            ['code' => 'manage_users', 'name' => 'Quản lý tài khoản', 'group' => 'users'],
            ['code' => 'manage_roles', 'name' => 'Quản lý vai trò và phân quyền', 'group' => 'users'],
            ['code' => 'manage_settings', 'name' => 'Cấu hình website', 'group' => 'settings'],
            ['code' => 'manage_media', 'name' => 'Quản lý thư viện tệp', 'group' => 'system'],
            ['code' => 'manage_languages', 'name' => 'Quản lý ngôn ngữ', 'group' => 'settings'],
            ['code' => 'translate_content', 'name' => 'Dịch nội dung tự động', 'group' => 'system'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['code' => $permission['code']], $permission);
        }
    }
}
