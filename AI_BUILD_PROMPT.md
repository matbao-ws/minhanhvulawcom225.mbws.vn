# AI_BUILD_PROMPT.md

# Prompt cho AI Coding Agent - Build Laravel Ecommerce Core

Dùng file này khi muốn nhờ AI coding agent bắt đầu code trực tiếp trong repo hiện tại.

Yêu cầu AI coding agent phải đọc file này trước, sau đó đọc `AI_SYSTEM_SPEC.md`, rồi mới inspect project và code.

---

## Vai trò của AI

Bạn là senior Laravel engineer đang build một hệ thống **Laravel Ecommerce Core**.

Hệ thống dùng cho agency/web service để triển khai nhiều website bán hàng theo gói.

Mô hình v1:

```text
1 khách hàng = 1 Laravel project = 1 database riêng
```

Không làm multi-tenant ở v1.

Không thêm `shop_id`.

---

## Stack bắt buộc

```text
Laravel
Blade + Bootstrap cho admin
REST API JSON cho client website
MySQL/MariaDB
Eloquent ORM
Form Request validation
Service layer
API Resource / ApiResponse helper
FeatureGate theo gói
```

Không dùng React, Vue, Next.js, Nuxt, Inertia, Livewire, GraphQL nếu chưa được yêu cầu.

---

## File bắt buộc phải đọc trước

Đọc kỹ các file này trước khi code:

```text
AI_SYSTEM_SPEC.md
```

Nếu repo đã có các file sau thì cũng đọc:

```text
README.md
composer.json
routes/web.php
routes/api.php
routes/admin.php
app/Models/*
app/Http/Controllers/*
database/migrations/*
```

---

## Luật làm việc

Trước khi sửa code, hãy làm 4 bước:

```text
1. Inspect project structure.
2. Xác định project đang ở phase nào.
3. Liệt kê file sẽ tạo/sửa.
4. Chỉ code trong scope phase hiện tại.
```

Không tự ý đổi kiến trúc.

Không sửa file ngoài scope nếu không cần.

Không thêm dependency mới nếu không có lý do rõ ràng.

---

## Kiến trúc bắt buộc

Flow chuẩn:

```text
Controller -> FormRequest -> Service -> Model -> Resource/View
```

Admin:

```text
routes/admin.php
app/Http/Controllers/Admin
resources/views/admin
```

API:

```text
routes/api.php
app/Http/Controllers/Api
app/Http/Resources
```

Core logic:

```text
app/Services
```

Shared helpers:

```text
app/Support
```

---

## Coding Rules

Bắt buộc:

```text
- Controller mỏng.
- Không để business logic trong Controller.
- Không query database trong Blade.
- Không để logic tính tiền, voucher, stock trong Blade.
- Validation nằm trong FormRequest.
- API trả JSON theo chuẩn ApiResponse.
- Admin dùng Blade + Bootstrap.
- Tính năng theo gói phải check bằng FeatureGate.
- Không check trực tiếp package code trong logic.
- Checkout/payment/stock phải dùng DB transaction.
```

Sai:

```php
if ($packageCode === 'basic_2m') {
    // sai
}
```

Đúng:

```php
if (! feature_enabled('voucher')) {
    abort(403);
}
```

---

## Phase hiện tại cần code

Bắt đầu với **Phase 1: Foundation**.

### Mục tiêu Phase 1

Tạo nền Laravel core gồm:

```text
- Admin auth cơ bản nếu project chưa có
- Admin Bootstrap layout base
- ApiResponse helper
- FeatureGate service
- feature middleware
- packages table
- features table
- package_features table
- project_subscription table
- feature_settings table
- project_settings table
- roles table
- users table nếu chưa có
- seed package basic_2m và standard_4m
- seed feature codes
- routes/admin.php setup
```

---

## Phase 1 - Files cần tạo/sửa

Tùy project hiện tại, tạo/sửa các file sau.

### Config

```text
config/features.php
config/packages.php
```

### Support

```text
app/Support/ApiResponse.php
app/Support/FeatureGate.php
```

### Middleware

```text
app/Http/Middleware/EnsureFeatureEnabled.php
```

### Models

```text
app/Models/Package.php
app/Models/Feature.php
app/Models/PackageFeature.php
app/Models/ProjectSubscription.php
app/Models/FeatureSetting.php
app/Models/ProjectSetting.php
app/Models/Role.php
app/Models/User.php
```

### Migrations

```text
database/migrations/*_create_packages_table.php
database/migrations/*_create_features_table.php
database/migrations/*_create_package_features_table.php
database/migrations/*_create_project_subscription_table.php
database/migrations/*_create_feature_settings_table.php
database/migrations/*_create_project_settings_table.php
database/migrations/*_create_roles_table.php
```

Nếu users table đã tồn tại thì không tạo lại, chỉ thêm field cần thiết nếu hợp lý.

### Seeders

```text
database/seeders/FeatureSeeder.php
database/seeders/PackageSeeder.php
database/seeders/ProjectSubscriptionSeeder.php
database/seeders/DatabaseSeeder.php
```

### Routes

```text
routes/admin.php
routes/web.php
routes/api.php
```

### Admin Layout

```text
resources/views/admin/layouts/app.blade.php
resources/views/admin/layouts/header.blade.php
resources/views/admin/layouts/sidebar.blade.php
resources/views/admin/layouts/footer.blade.php
resources/views/admin/dashboard/index.blade.php
```

### Controllers

```text
app/Http/Controllers/Admin/DashboardController.php
```

---

## Feature Codes bắt buộc

Seed các feature code này:

```text
catalog
cart
cod_order
online_payment
voucher
review
zalo_oa
cms_page
banner
menu
multi_admin
inventory_log
max_products
max_admin_users
```

Không được tự đổi tên thành code khác.

---

## Package Presets

Seed package `basic_2m`:

```text
catalog = true
cart = true
cod_order = true
online_payment = false
voucher = false
review = false
zalo_oa = false
cms_page = false
banner = true
menu = true
multi_admin = false
inventory_log = false
max_products = 50
max_admin_users = 1
```

Seed package `standard_4m`:

```text
catalog = true
cart = true
cod_order = true
online_payment = true
voucher = true
review = true
zalo_oa = true
cms_page = true
banner = true
menu = true
multi_admin = true
inventory_log = true
max_products = 200
max_admin_users = 3
```

Sau khi seed, project mặc định dùng `basic_2m`.

---

## Database Schema Phase 1

### packages

```text
id
code unique
name
price
description nullable
is_active boolean
timestamps
```

### features

```text
id
code unique
name
description nullable
value_type string
timestamps
```

### package_features

```text
id
package_id foreign
feature_id foreign
is_enabled boolean
limit_value nullable string
config nullable json
timestamps

unique(package_id, feature_id)
```

### project_subscription

```text
id
package_id foreign
status string
started_at date nullable
expired_at date nullable
timestamps
```

### feature_settings

```text
id
feature_code unique
is_enabled boolean
limit_value nullable string
config nullable json
updated_at
```

### project_settings

```text
id
setting_key unique
setting_value nullable json
updated_at
```

### roles

```text
id
name
permissions nullable json
timestamps
```

### users

Use Laravel default if available. Ensure it can support admin login.

Recommended fields:

```text
id
role_id nullable
name
email unique
password
avatar_url nullable
is_active boolean default true
last_login_at nullable
timestamps
```

If Laravel default `password` exists, do not rename it to `password_hash`.

---

## ApiResponse Helper

Create:

```php
namespace App\Support;

class ApiResponse
{
    public static function success($data = null, ?string $message = null, array $meta = [])
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    public static function error(string $message, int $status = 400, array $errors = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
```

---

## FeatureGate Service

Create:

```php
namespace App\Support;

use App\Models\FeatureSetting;

class FeatureGate
{
    public function enabled(string $featureCode): bool
    {
        $setting = FeatureSetting::query()
            ->where('feature_code', $featureCode)
            ->first();

        return $setting && (bool) $setting->is_enabled;
    }

    public function limit(string $featureCode): ?int
    {
        $setting = FeatureSetting::query()
            ->where('feature_code', $featureCode)
            ->first();

        if (! $setting || $setting->limit_value === null) {
            return null;
        }

        return (int) $setting->limit_value;
    }

    public function require(string $featureCode): void
    {
        if (! $this->enabled($featureCode)) {
            abort(403, 'Tính năng này không khả dụng trong gói hiện tại.');
        }
    }
}
```

---

## Feature Middleware

Create middleware:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\FeatureGate;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        app(FeatureGate::class)->require($feature);

        return $next($request);
    }
}
```

Register middleware alias:

```php
'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
```

Use route example:

```php
Route::middleware('feature:voucher')->group(function () {
    // voucher routes
});
```

---

## Blade Helper

Add helper if project has helper file system.

```php
function feature_enabled(string $code): bool
{
    return app(\App\Support\FeatureGate::class)->enabled($code);
}
```

If no helper system exists, use:

```blade
@if(app(\App\Support\FeatureGate::class)->enabled('voucher'))
    ...
@endif
```

Do not invent a helper system without explaining the file and Composer autoload update.

---

## Admin Sidebar Requirements

Admin sidebar must hide/show menu based on features.

Minimum menu:

```text
Dashboard
Products - feature: catalog
Categories - feature: catalog
Orders - always visible or feature: cod_order
Customers - always visible
Banners - feature: banner
Menus - feature: menu
Pages - feature: cms_page
Vouchers - feature: voucher
Payment Settings - feature: online_payment
Reviews - feature: review
Zalo OA - feature: zalo_oa
Settings
```

---

## REST API Phase 1

Only create health/settings placeholder API for Phase 1.

Example:

```text
GET /api/public/health
GET /api/public/settings
```

Response:

```json
{
  "success": true,
  "message": null,
  "data": {
    "app": "Laravel Ecommerce Core"
  },
  "meta": {}
}
```

Do not implement product/order API in Phase 1.

---

## Acceptance Criteria Phase 1

Phase 1 is done when:

```text
- Project can run migrations.
- Project can run seeders.
- packages/features/package_features exist.
- feature_settings is seeded from basic_2m by default.
- FeatureGate can check enabled features.
- feature middleware works.
- Admin layout loads.
- Sidebar hides disabled features.
- Public health API returns standard JSON.
- No React/Vue/Next/Inertia/Livewire added.
- No shop_id added.
```

---

## After Phase 1

Stop and report:

```text
- Files created/modified
- Commands to run
- Notes about any assumptions
- Next recommended phase
```

Do not continue to Phase 2 unless explicitly asked.

---

## Commands to suggest after coding

Suggest these commands if applicable:

```bash
composer install
php artisan migrate:fresh --seed
php artisan serve
```

If helper autoload changed:

```bash
composer dump-autoload
```

---

## Output Format For AI Agent

When done, respond with:

```md
## Summary
...

## Files changed
...

## Commands to run
...

## Verified / Not verified
...

## Next phase
...
```
