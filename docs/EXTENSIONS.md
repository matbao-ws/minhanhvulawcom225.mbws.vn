# Viết extension cho website khách

Extension là **tính năng riêng của một website** — trang admin, bảng dữ liệu, tích hợp MISA/ERP,
báo cáo riêng — nằm trong `extensions/<id>/` của repo khách. **Không sửa file core nào**, nên website
vẫn nhận bản cập nhật core bình thường.

Thiết kế đầy đủ và lý do: [EXTENSIONS_DESIGN.md](EXTENSIONS_DESIGN.md). Extension mẫu chạy được:
`tests/Fixtures/extensions/example/` (trang ghi chú: bảng riêng, menu, quyền, form).

---

## 1. Khi nào dùng extension

| Yêu cầu của khách | Làm ở |
|---|---|
| Giao diện, trang storefront | Theme: `resources/views/client`, `routes/theme.php` |
| Logic riêng chỉ khách này có (đồng bộ MISA, quản lý đại lý...) | **Extension** |
| Tính năng nhiều khách sẽ cần / bán được | Core (báo trưởng nhóm), có công tắc bật theo khách |

## 2. Tạo extension

Trong thư mục dự án khách:

```bash
php artisan extension:make dealers --name="Quản lý đại lý"
php artisan extension:check dealers
```

`id` (ở đây `dealers`): chữ thường và số, 2–31 ký tự, **không đổi** sau khi đã chạy thật. Mọi tên khác
suy ra từ nó:

| Thứ | Giá trị |
|---|---|
| Thư mục | `extensions/dealers/` |
| Namespace PHP | `Extensions\Dealers\` → `extensions/dealers/src/` (core tự autoload, **không** sửa `composer.json`) |
| Bảng | `ext_dealers_*` |
| Quyền, key menu | `dealers.*` |
| View / bản dịch | `view('dealers::...')`, `__('dealers::file.key')` |
| Config (`config.php`) | `config('extensions.dealers.*')` |
| Route admin | `/{locale}/admin/ext/dealers/...`, tên `ext.dealers.*`, đã có sẵn đăng nhập admin |
| Route API | `/api/ext/dealers/...`, tên `ext.dealers.api.*` — **tự thêm xác thực** (webhook để public) |
| Route web | `/ext/dealers/...`, tên `ext.dealers.web.*` |

Cấu trúc:

```
extensions/dealers/
├── extension.json        id, tên, phiên bản, requires_core, provider, quyền
├── config.php            (tuỳ chọn)
├── src/                  DealersServiceProvider.php, Http/Controllers, Models...
├── routes/admin.php | api.php | web.php     (tuỳ chọn)
├── views/  lang/vi  lang/en
└── database/migrations/
```

## 3. `extension.json`

```json
{
  "schema": 1,
  "id": "dealers",
  "name": "Quản lý đại lý",
  "version": "1.0.0",
  "requires_core": ">=1.2.0 <2.0.0",
  "provider": "Extensions\\Dealers\\DealersServiceProvider",
  "permissions": [
    { "code": "dealers.view", "label": "Xem đại lý" },
    { "code": "dealers.manage", "label": "Thêm, sửa, xoá đại lý" }
  ]
}
```

- `requires_core`: các bản core extension chạy được. Core mới không thoả → **cập nhật bị chặn** (website
  không bao giờ cài core làm hỏng extension). Đặt theo cam kết ổn định ở mục 6: thường
  `>=<bản đang dùng> <<major tiếp theo>`.
- Tăng `version` mỗi lần đổi extension.

## 4. Provider: chỉ đăng ký logic

```php
class DealersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        AdminMenu::register('dealers', [
            'key' => 'dealers.index',
            'label' => __('dealers::menu.dealers'),
            'icon' => 'solar:handshake-line-duotone',
            'route' => 'ext.dealers.index',
            'permission' => 'dealers.view',
            'children' => [ /* ['key' => 'dealers.x', 'label' => ..., 'route' => ..., 'permission' => ...] */ ],
        ]);

        // Event::listen(...) cho sự kiện của core: có ở core 1.3.0.
    }
}
```

**Không** gọi `loadRoutesFrom`, `loadViewsFrom`, `loadMigrationsFrom`, `mergeConfigFrom`, và **không**
đăng ký route trong `src/` (`Route::get(...)` trong provider sẽ lách khỏi tiền tố `/ext/<id>` và
middleware admin): route chỉ nằm trong `routes/*.php`. Core tự nạp theo cùng thứ tự, tiền tố và
middleware cho mọi extension; `extension:check` báo lỗi nếu làm khác.

## 5. Quyền, menu, trang admin

- Khai báo quyền trong `extension.json`; quyền hiện ở **Vai trò** (nhóm "Tiện ích mở rộng") và ở quyền
  riêng của tài khoản. Không insert quyền bằng migration.
- Chặn route bằng `->middleware('can:dealers.view')`, trong view dùng `@can('dealers.manage')`.
- Trang admin `@extends('admin.layouts.app')` và dùng component có sẵn (`<x-admin.page-header>`...).
- Controller của route admin **không** nhận `$locale` (khác controller core): core đã đặt ngôn ngữ và
  gỡ `{locale}` khỏi tham số, nên viết thẳng `public function destroy(Note $note)`. `route()` vẫn tự
  điền ngôn ngữ hiện tại.
- Menu: `AdminMenu::register` (mục 4). Key trùng → extension bị đánh dấu lỗi, không ghi đè.

## 6. Được và không được dùng gì của core

Được dùng (API ổn định, chỉ đổi ở bản major, báo trước ít nhất một bản minor):

- `App\Extension\*` (hiện có `App\Extension\Registries\AdminMenu`);
- **đọc** các model `App\Models\Order`, `OrderItem`, `Product`, `ProductVariant`, `Category`, `Brand`,
  `Post`, `User` và tạo khoá ngoại tới chúng — **không** tự `->save()` đổi trạng thái đơn, tiền, tồn
  kho (nghiệp vụ đó sẽ có hợp đồng riêng); danh sách này là bề mặt tạm thời của 1.2.0;
- Laravel (`Illuminate\*`), `Carbon`, code của chính extension.

Không được: mọi thứ khác của `App\` (`App\Services\*`, `App\Support\*`, `App\Http\*`...), gọi sang
extension khác, và **thay** class của core (`bind`/`singleton`/`extend`/`instance`/`alias` lên class
`App\...`). Extension chỉ **thêm, nghe, đăng ký**. Cần thứ chưa có → xin thêm vào API của core.

## 7. Migration

- Chỉ tạo/sửa bảng **của mình**: `ext_dealers_*`. Không `Schema::table`/`drop`/`rename` hay `DB::table`
  trên bảng core hoặc bảng extension khác; không SQL thô; tên bảng là chuỗi cố định.
- Không thêm cột vào bảng core. Cần gắn dữ liệu vào sản phẩm/đơn: bảng riêng có khoá ngoại.
- Khoá ngoại tới bảng core: `->constrained('orders')->restrictOnDelete()` hoặc `->nullOnDelete()`
  theo nghiệp vụ, **tránh** `cascadeOnDelete()` (xoá dữ liệu core kéo theo dữ liệu extension hàng loạt).
- Theo luật expand/contract như core: trong `up()` chỉ thêm bảng/cột nullable/index; xoá hay đổi tên
  cột làm ở bản sau, đánh dấu `@contract-migration`.
- Chạy cùng migration của website: `php artisan migrate` (deploy tự chạy).

## 8. Kiểm tra và lưu

```bash
php artisan extension:check          # manifest, API được dùng, không thay class core, migration
```

`python team.py` → "Lưu & đẩy" tự chạy lệnh này khi `extensions/` có thay đổi và dừng nếu lỗi; CI của
repo khách cũng chạy nó. Đây là **hàng rào chống làm sai kiến trúc, không phải sandbox**: extension là
code chạy chung tiến trình với core, phải được review như code core.

## 9. Bật, tắt, cứu website

| Việc | Lệnh (trên máy chủ, trong thư mục website) |
|---|---|
| Xem trạng thái | `php artisan extension:list` · admin → Cấu hình → **Tiện ích mở rộng** (superadmin) |
| Tắt một extension | `php artisan extension:disable dealers --reason="lỗi MISA"` |
| Bật lại | `php artisan extension:enable dealers` |
| Tắt hết, chạy core trơn | `.env`: `EXTENSIONS_ENABLED=false` |

- Các lệnh `extension:*` không nạp extension nào, nên chạy được cả khi extension đang làm hỏng website.
- Extension lỗi (exception khi nạp, lỗi cú pháp...) chỉ **tự nó** bị bỏ qua, website vẫn chạy; lỗi hiện
  ở trang Tiện ích mở rộng và `extension:list`. Log (`storage/logs`) ghi id, phiên bản, giai đoạn
  (`register`/`resources`/`boot`/`routes`), class và thông báo lỗi — không ghi stack trace hay config,
  để không lộ khoá API.
- Trạng thái bật/tắt nằm trong `storage/app/core/extensions.json` của website — deploy và cập nhật core
  không ghi đè.

## 10. Khi core có bản mới

- Bản mới không thoả `requires_core` của một extension đang bật → **cập nhật bị chặn**, trang Cập nhật
  phiên bản ghi rõ extension nào. Cách xử lý: ra bản extension mới (sửa `requires_core` sau khi đã thử
  với core mới), hoặc tắt extension rồi cập nhật.
- Sau cập nhật, extension đang chạy tốt mà không nạp được nữa → bản cập nhật **tự quay về** bản cũ.
- Repo khách lấy core mới như thường (`team.py` → "Lấy bản core mới"); `extensions/` không bao giờ
  conflict với core.

## 11. Dùng lại cho khách khác

Chép nguyên `extensions/<id>/` sang repo khách kia: nhờ mọi tên suy ra từ `id`, nó chạy được ngay.
Extension dùng ở nhiều khách thì cân nhắc đưa vào core (có công tắc), để sửa một lần cho mọi website.
