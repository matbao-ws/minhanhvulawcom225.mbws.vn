# AI_PHASE2_ADMIN_CORE_HARDENING_PROMPT.md

# Prompt cho AI Coding Agent - Phase 2: Hoàn thiện Admin Core trước khi đóng gói package

Dùng file này sau khi đã có:

```text
AI_SYSTEM_SPEC.md
AI_BUILD_PROMPT.md
AI_PHASE1_MIGRATIONS_AND_THEME_PROMPT.md
```

## Trạng thái hiện tại (tính đến 2026-07-18)

**Chưa có dòng code nào được sửa cho phase này.** Ngày 2026-07-18 mới chỉ thực hiện một buổi **audit/rà soát** toàn bộ admin core (đọc routes/admin.php, toàn bộ Controllers/Admin, Models liên quan) để liệt kê chức năng thiếu. Kết quả audit đó là nguồn gốc của file prompt này. Chưa có migration, model, controller, route hay view nào được tạo/sửa.

Mục tiêu file này: cầm tay chỉ việc cho AI coding agent (hoặc chính bạn) implement 3 nhóm **ưu tiên cao** đã audit ra, theo đúng convention hiện có của repo (Bootstrap 5 + Tabler icons, `Gate::before` role-permission đơn giản, migration style `foreignId()->constrained()->cascadeOnDelete()`, không dùng package ngoài nếu có thể tự viết nhỏ gọn).

**Ngoài phạm vi phase này (KHÔNG làm):**

- Không tích hợp thật với GHN/J&T/SPX/Viettel Post/Stripe/Sepay — các cổng này hiện chỉ có form cấu hình, **chưa có API key thật** nên chưa cắm logic push/webhook. Giữ nguyên as-is.
- Không đụng vào phần đã chạy tốt: GHTK shipping, VNPay, import/export sản phẩm CSV, global search, CMS blog, media library.
- Không làm các mục **ưu tiên trung bình/thấp** (SEO fields cho Product/Category, trang Customers riêng, sửa dashboard traffic chart giả, queue hoá email, scheduled tasks, bulk actions, chuẩn hoá attribute sản phẩm, tax/đa tiền tệ, email template editor, backup/restore) — danh sách đầy đủ nằm ở cuối file này để làm tiếp sau khi xong phase này.

---

# PHẦN A — Hệ thống phân quyền data-driven (làm trước tiên)

## Vì sao làm trước

Permission mới (`view_audit_log`, có thể thêm `refund_orders` sau) cần một registry thật thay vì mảng hardcode. Làm phần này trước để Phần B và C khai báo permission mới vào registry thay vì lại phải sửa tay `RoleController::getAvailablePermissions()`.

## Hiện trạng (đã đọc code, xác nhận)

- `app/Models/Role.php`: chỉ có `fillable = ['name','permissions']`, cast `permissions` là JSON array of string. Không có helper method nào.
- `app/Http/Controllers/Admin/RoleController.php::getAvailablePermissions()` (dòng 14-27): mảng hardcode đúng 9 permission. Giá trị label trong mảng này **thực ra không được dùng** — view `resources/views/admin/roles/_form.blade.php` lấy label qua `__('admin.roles.permissions.'.$key)` từ lang file, bỏ qua label trong mảng.
- Routes chỉ thực sự dùng 7/9 permission: `manage_users, manage_posts, manage_vouchers, manage_reviews, manage_settings, manage_orders, manage_products`. `manage_roles` bị gate bằng middleware `superadmin` riêng (không dùng `can:manage_roles`). `manage_banners` **không được dùng ở route nào** — route banners hiện đang nằm chung nhóm `can:manage_settings` (routes/admin.php dòng 105-107), đây là một lỗi/tồn đọng cần sửa trong phase này.
- `lang/vi/admin.php` và `lang/en/admin.php` thiếu key `admin.roles.permissions.manage_banners` — checkbox banner hiện hiển thị ra string key thô.
- `app/Providers/AppServiceProvider.php` dòng 52-67: `Gate::before` duy nhất, kiểm tra `$user->isSuperAdmin()` → `in_array('*', permissions)` → `in_array($ability, permissions)`.
- `User::isSuperAdmin()`: so sánh `$this->role->name === 'Superadmin'` (so sánh theo tên chuỗi, không có cột boolean riêng) — khá mong manh nếu sau này đổi tên role.
- Pattern đáng học theo trong chính repo này: hệ thống Feature flag (`config/features.php` + `app/Models/Feature.php` + `FeatureSeeder.php` dùng `updateOrCreate` + `app/Support/FeatureGate.php`). Đây chính là kiểu registry cần làm cho permission.

## Việc cần làm

### 1. Migration `permissions` table

```php
Schema::create('permissions', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->string('name');
    $table->string('group')->default('general'); // catalog, orders, marketing, users, settings, system
    $table->text('description')->nullable();
    $table->timestamps();
});
```

### 2. Migration alter `roles` table — thêm cờ hệ thống để hết lệ thuộc vào so sánh tên

```php
Schema::table('roles', function (Blueprint $table) {
    if (! Schema::hasColumn('roles', 'is_system')) {
        $table->boolean('is_system')->default(false)->after('name');
    }
});
```

Data migration đi kèm (hoặc chỉnh sửa trực tiếp trong migration data cũ `2026_06_25_122139_create_superadmin_role_and_assign_to_admin.php` bằng migration mới nối tiếp): set `is_system = true` cho role `Superadmin` hiện có.

### 3. Model `Permission`

```php
class Permission extends Model
{
    protected $fillable = ['code', 'name', 'group', 'description'];
}
```

### 4. Cập nhật `Role` model

```php
public function hasPermission(string $ability): bool
{
    if (in_array('*', $this->permissions ?? [])) {
        return true;
    }
    return in_array($ability, $this->permissions ?? []);
}
```

### 5. Cập nhật `User::isSuperAdmin()`

```php
public function isSuperAdmin(): bool
{
    if (! $this->role) {
        return false;
    }
    return $this->role->is_system === true || $this->role->name === 'Superadmin';
}
```

(Giữ fallback theo tên để không phá dữ liệu cũ nếu migration `is_system` chưa chạy kịp ở project nào đó.)

### 6. Seeder `PermissionSeeder.php` — theo đúng pattern `FeatureSeeder`

```php
$permissions = [
    ['code' => 'manage_products', 'name' => 'Quản lý sản phẩm (Catalog)', 'group' => 'catalog'],
    ['code' => 'manage_orders', 'name' => 'Quản lý đơn hàng', 'group' => 'orders'],
    ['code' => 'view_audit_log', 'name' => 'Xem nhật ký hoạt động', 'group' => 'orders'],
    ['code' => 'manage_vouchers', 'name' => 'Quản lý mã giảm giá', 'group' => 'marketing'],
    ['code' => 'manage_banners', 'name' => 'Quản lý Banner', 'group' => 'marketing'],
    ['code' => 'manage_posts', 'name' => 'Quản lý bài viết (CMS)', 'group' => 'marketing'],
    ['code' => 'manage_reviews', 'name' => 'Quản lý bình luận & đánh giá', 'group' => 'marketing'],
    ['code' => 'manage_users', 'name' => 'Quản lý tài khoản', 'group' => 'users'],
    ['code' => 'manage_roles', 'name' => 'Quản lý vai trò & phân quyền', 'group' => 'users'],
    ['code' => 'manage_settings', 'name' => 'Cấu hình website', 'group' => 'settings'],
];
foreach ($permissions as $p) {
    Permission::updateOrCreate(['code' => $p['code']], $p);
}
```

Gọi seeder này trong `FoundationSeeder` (giống cách `FeatureSeeder` được gọi).

### 7. Sửa `RoleController.php`

- Xoá `getAvailablePermissions()` hardcode.
- Thay bằng: `Permission::orderBy('group')->get()->groupBy('group')`.
- Validation whitelist trong `store()`/`update()`: `'permissions.*' => 'in:' . Permission::pluck('code')->implode(',')`.

### 8. Sửa view `_form.blade.php`

- Loop theo nhóm (`@foreach($permissionsByGroup as $group => $items)`), mỗi nhóm là một card/section có tiêu đề nhóm (Catalog, Đơn hàng, Marketing, Người dùng, Cấu hình...).
- Label hiển thị: ưu tiên `__('admin.roles.permissions.'.$item->code)` nếu `Lang::has()` trả về true, fallback về `$item->name` nếu chưa có bản dịch — để permission mới thêm sau này không cần sửa lang file ngay mới hiển thị được.

### 9. Sửa `routes/admin.php` — vá lỗi banners

Đổi route banners (dòng 104-107) từ đang nằm trong nhóm `can:manage_settings` sang nhóm riêng dùng `can:manage_banners`:

```php
Route::middleware(['feature:banner', 'can:manage_banners'])->group(function () {
    Route::resource('banners', \App\Http\Controllers\Admin\BannerController::class)->except(['show']);
});
```

Bổ sung key `admin.roles.permissions.manage_banners` vào cả `lang/vi/admin.php` và `lang/en/admin.php`.

---

# PHẦN B — Nhật ký hoạt động admin (Activity Log)

## Hiện trạng

Không có bất kỳ hạ tầng audit/activity log nào trong repo (đã grep xác nhận: không có `spatie/laravel-activitylog`, không bảng nào tên `*log*`/`*history*`/`*audit*`). Mọi thao tác đổi role, đổi settings, đổi trạng thái đơn hàng hiện **ghi đè âm thầm, không lưu vết**.

## Việc cần làm

### 1. Migration `admin_activity_logs`

```php
Schema::create('admin_activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('action'); // created | updated | deleted | status_changed | refunded | ...
    $table->string('subject_type')->nullable();
    $table->unsignedBigInteger('subject_id')->nullable();
    $table->string('description');
    $table->json('changes')->nullable(); // ['old' => [...], 'new' => [...]]
    $table->string('ip_address')->nullable();
    $table->timestamp('created_at')->nullable();

    $table->index(['subject_type', 'subject_id']);
});
```

Chỉ `created_at`, không cần `updated_at` (log là bất biến, không sửa lại).

### 2. Model `AdminActivityLog`

```php
class AdminActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'description', 'changes', 'ip_address'];
    protected $casts = ['changes' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function subject() { return $this->morphTo(); }
}
```

### 3. Service `app/Services/ActivityLogger.php`

```php
class ActivityLogger
{
    public static function log(string $action, ?Model $subject, string $description, array $changes = []): void
    {
        AdminActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'changes' => $changes ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
```

Gọi tường minh tại điểm hành động (không dùng model observer tự động — giữ đúng phong cách repo hiện tại là code rõ ràng, không "magic").

**Nơi cần gọi `ActivityLogger::log()` trong phase này:**

- `RoleController@store/update/destroy` — log tạo/sửa/xoá role kèm `changes` (permissions cũ/mới).
- `SettingController@update` — log các trường đã đổi.
- `OrderController@updateStatus` và refund mới ở Phần C — log đổi trạng thái/hoàn tiền.

*(Wiring cho các module còn lại — catalog, users, vouchers... — để lại cho phase sau, không bắt buộc ở phase này.)*

### 4. Route + Controller xem log

```php
Route::middleware('can:view_audit_log')->group(function () {
    Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
});
```

`ActivityLogController@index`: paginate, filter theo `user_id`, `action`, `subject_type`, khoảng ngày. View đơn giản dạng bảng, badge màu theo `action`, cùng style Bootstrap 5 + Tabler icon như các trang admin khác.

---

# PHẦN C — Vòng đời đơn hàng (Order lifecycle)

## Hiện trạng (đã đọc code, xác nhận)

- `OrderController.php` chỉ có `index`, `show`, `updateStatus`, `pushShipping`. Route chỉ expose `index`/`show` (routes/admin.php dòng 114) — **admin không tạo được đơn thủ công**.
- `updateStatus()` (dòng ~64-83) không có logic hoàn kho, không lưu lịch sử — chỉ update field + gửi mail nếu status đổi.
- Nơi duy nhất có logic hoàn kho hiện tại là `PublicController::checkout()` dòng ~364-379, trong nhánh rollback khi tạo URL thanh toán VNPay thất bại:

```php
foreach ($order->items as $item) {
    $product = Product::find($item->product_id);
    if ($product && $product->manage_stock) {
        $product->increment('stock_quantity', $item->quantity);
    }
}
```

Đây là pattern cần tách thành method dùng chung.

- Giảm kho lúc checkout (`PublicController.php` dòng ~338-346) chỉ trừ ở cấp `Product`, gated bởi `manage_stock` — **không đụng tới `ProductVariant::stock_quantity`** dù cột này tồn tại. Method hoàn kho mới phải soi gương chính xác logic này (chỉ Product, không sửa gap biến thể trong phase này).
- `WebhookController::handleGHTK()` map các mã trạng thái GHTK dạng hoàn/trả hàng (11, 13, 20, 21) về `cancelled` nhưng **không hoàn kho**.
- Không có thư viện PDF nào (`dompdf`/`snappy`) trong `composer.json`. `resources/views/admin/orders/show.blade.php` đã có sẵn pattern in ấn qua trình duyệt (`@media print` CSS + nút `window.print()`) — tái dùng pattern này cho phiếu đóng gói/hoá đơn, **không cài thêm thư viện PDF**.

## Việc cần làm

### 1. Migration `order_status_histories`

```php
Schema::create('order_status_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
    $table->string('from_status')->nullable();
    $table->string('to_status');
    $table->string('from_payment_status')->nullable();
    $table->string('to_payment_status')->nullable();
    $table->text('note')->nullable();
    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('created_at')->nullable();
});
```

### 2. Migration `order_refunds` + `order_refund_items` (hỗ trợ hoàn một phần)

```php
Schema::create('order_refunds', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->string('type')->default('partial'); // full | partial
    $table->text('reason')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});

Schema::create('order_refund_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_refund_id')->constrained('order_refunds')->cascadeOnDelete();
    $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
    $table->unsignedInteger('quantity');
    $table->decimal('amount', 15, 2);
});
```

Đây là **ghi nhận thủ công** (admin xác nhận đã hoàn tiền qua chuyển khoản/tiền mặt), không gọi API hoàn tiền cổng thanh toán thật — vì chưa có API key, đúng như bạn lưu ý.

### 3. Migration mở rộng `payment_status`

Không đổi kiểu cột (vẫn là string, validate ở controller — đúng convention hiện tại). Chỉ cập nhật rule validate để chấp nhận thêm `refunded`, `partially_refunded`.

### 4. Models mới

`OrderStatusHistory` (`belongsTo(Order::class)`, `belongsTo(User::class, 'changed_by')`), `OrderRefund` (`belongsTo(Order::class)`, `hasMany(OrderRefundItem::class)`), `OrderRefundItem` (`belongsTo(OrderRefund::class)`, `belongsTo(OrderItem::class)`).

Thêm vào `Order` model: `historyEntries()` (hasMany OrderStatusHistory), `refunds()` (hasMany OrderRefund), và quan hệ `user()` (`belongsTo(User::class)`) đang **bị thiếu dù có cột `user_id`** — thêm luôn vì cần cho việc gộp khách hàng đăng ký ở phase sau.

### 5. Service `app/Services/OrderStockService.php` (tách logic hoàn kho dùng chung)

```php
class OrderStockService
{
    public function restore(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product && $product->manage_stock) {
                $product->increment('stock_quantity', $item->quantity);
            }
        }
    }

    public function restorePartial(Order $order, array $refundItems): void
    {
        // $refundItems: [['order_item_id' => x, 'quantity' => y], ...]
        foreach ($refundItems as $ri) {
            $item = $order->items()->find($ri['order_item_id']);
            if (!$item) continue;
            $product = Product::find($item->product_id);
            if ($product && $product->manage_stock) {
                $product->increment('stock_quantity', $ri['quantity']);
            }
        }
    }
}
```

`PublicController::checkout()` nên gọi lại service này thay vì lặp code (thay đoạn rollback dòng ~364-379 bằng `app(OrderStockService::class)->restore($order)`), tránh 2 nơi giữ cùng logic.

### 6. Sửa `OrderController::updateStatus()`

- Ghi `OrderStatusHistory` mỗi lần đổi status/payment_status (kể cả khi không đổi mail, vẫn nên log nếu có thay đổi thật sự).
- Nếu `to_status === 'cancelled'` và trạng thái cũ chưa phải `cancelled`: gọi `OrderStockService::restore($order)`.
- Gọi `ActivityLogger::log('status_changed', $order, ...)` (Phần B).
- Thêm field `note` optional trong form đổi trạng thái (lý do đổi) — lưu vào `order_status_histories.note`, không đổi cột `orders.notes` hiện tại (giữ nguyên vai trò ghi chú của khách).

### 7. Thêm `OrderController@create`, `@store` (tạo đơn thủ công)

- Form: chọn/nhập khách hàng (tên/email/phone/địa chỉ), thêm sản phẩm (product/variant + số lượng, autocomplete tái dùng cơ chế `SearchController` hoặc endpoint đơn giản mới), nhập giảm giá thủ công.
- `store()`: validate tương tự `PublicController::checkout()` (đã đọc kỹ rule ở đó để tái dùng đúng field), tính `subtotal/discount/grand_total`, sinh `order_number` cùng format hiện tại (`'ORD-' . Str::random(10)` — kiểm tra lại đúng format thực tế trong `PublicController::checkout()` trước khi copy), trừ kho giống checkout (chỉ Product-level, theo `manage_stock`), ghi `OrderStatusHistory` khởi tạo (`from_status = null`, `to_status = 'pending'`).
- Route: thêm `create`/`store` vào `Route::resource('orders', ...)->only(['index','show','create','store'])` trong routes/admin.php (dòng 114), vẫn trong nhóm `can:manage_orders`.

### 8. Thêm `OrderController@refund` (POST) + view

- Form trên trang `show`: chọn loại (toàn phần/một phần), nếu một phần thì chọn item + số lượng hoàn.
- Validate: tổng tiền hoàn ≤ `grand_total` trừ đã hoàn trước đó; số lượng hoàn theo item ≤ số lượng đã mua trừ đã hoàn trước đó.
- Logic: tạo `OrderRefund` + `OrderRefundItem[]`, cập nhật `payment_status` (`refunded` nếu hoàn toàn phần bằng full `grand_total`, `partially_refunded` nếu còn thiếu), gọi `OrderStockService::restorePartial()` hoặc `::restore()` tương ứng, ghi `ActivityLogger::log('refunded', $order, ...)`.
- Route mới: `Route::post('orders/{order}/refund', ...)->name('orders.refund')` trong nhóm `can:manage_orders` (không cần permission riêng `refund_orders` ở phase này — dùng chung `manage_orders` để tránh nở permission quá sớm).

### 9. View — timeline lịch sử + khu vực hoàn tiền + in phiếu

- Trong `resources/views/admin/orders/show.blade.php`: thêm block timeline hiển thị `order->historyEntries` (mốc thời gian, người đổi, from→to, note) — dùng style card/list đã có trong theme (badge trạng thái tương tự đang dùng ở bảng orders/index).
- Thêm card "Hoàn tiền" hiển thị danh sách `order->refunds` đã tạo + form tạo refund mới (modal, theo pattern SweetAlert2 đang dùng ở modal push-shipping).
- Nút "In phiếu đóng gói" tái dùng đúng pattern `@media print` + `window.print()` đã có (có thể thêm query param hoặc class để ẩn/hiện phần giá tiền khi in phiếu đóng gói so với khi in hoá đơn, tránh việc kho khi soạn hàng nhìn thấy giá).

---

# Thứ tự thực hiện (bắt buộc, không nhảy cóc)

```text
1. Phần A — Permission registry (bảng permissions + seeder + sửa RoleController/views + vá lỗi manage_banners)
2. Phần B — Activity log (bảng + model + service), wiring vào RoleController (đã sửa ở bước 1) và SettingController
3. Phần C — Order lifecycle (2 migration mới + service hoàn kho + create/store + refund + view), wiring ActivityLogger vào updateStatus/refund
```

Vì sao theo thứ tự này: Phần C dùng lại `ActivityLogger` từ Phần B; Phần B và C đều khai báo permission mới (`view_audit_log`) nên registry ở Phần A phải có trước để không phải sửa lại `RoleController` hai lần.

---

# Sau khi xong Phase 2 — backlog còn lại (ưu tiên trung bình → thấp)

Không làm trong phase này, để lại cho Phase 3+:

**Trung bình:**

- SEO fields (`meta_title`, `meta_description`) cho `Product`/`Category` (Post đã có, catalog thì chưa — bất nhất).
- Trang "Customers" riêng khỏi "Users" (staff), sửa dashboard top-customers group theo `user_id` thay vì tên/email/sđt.
- Sửa biểu đồ "Website traffic" trên dashboard đang là số giả cứng (`DashboardController.php` khoảng dòng 139).
- Queue hoá email (tạo `app/Jobs`, chuyển `Mail::send()` đồng bộ sang `ShouldQueue`).
- Scheduled tasks (`routes/console.php` đang là mặc định Laravel) — hết hạn voucher tự động, nhắc gia hạn subscription, xử lý giỏ hàng bỏ quên.
- Tích hợp thật GHN/J&T/SPX/Viettel/Stripe/Sepay — **chờ có API key**.

**Thấp:**

- Bulk actions (xoá/sửa giá hàng loạt).
- Chuẩn hoá attribute sản phẩm (bảng `attributes`/`attribute_values` thay vì JSON tự do trên variant).
- Tax/VAT, đa tiền tệ.
- Trình soạn email template.
- Backup/restore.
- Bổ sung `lang/vi/validation.php` (đang thiếu, rơi về mặc định âm thầm); hoàn thiện hoặc bỏ locale `ko` (mới có 3/6 file lang).

---

# Final Output Required (sau khi implement Phase 2)

```md
## Summary
...

## Migrations created
...

## Models created/updated
...

## Controllers/Routes created/updated
...

## Views created/updated
...

## Commands to run
php artisan migrate
php artisan db:seed --class=PermissionSeeder

## Notes
...

## Next recommended phase
Phase 3 — hạng mục ưu tiên trung bình (xem danh sách backlog ở trên)
```

Không làm sang các hạng mục Phase 3 khi chưa được yêu cầu rõ ràng.
