# Extension layer — thiết kế Giai đoạn 1 (core 1.2.0)

Trạng thái: **bản thiết kế chờ duyệt**. Chưa có code. Duyệt xong mới làm trên nhánh `feat/extensions`.

Mục tiêu: website khách có **logic riêng** (trang admin, bảng dữ liệu, tích hợp MISA/ERP...) mà
**không sửa file core**, nên core vẫn cập nhật được. Ba vùng sở hữu độc lập:

| Vùng | Ở đâu | Ai quản lý | Bản cập nhật core |
|---|---|---|---|
| Core | `app/`, `config/`, `routes/*.php` của core, `resources/views/admin`... | Mắt Bão, có version | Ghi đè |
| Theme | `resources/views/client`, `public/client-assets`, `routes/theme.php` | Từng website | Không bao giờ đụng |
| Extension | `extensions/<id>/` | Từng website | Không bao giờ đụng |

**Bản chất của extension:** extension là **code ứng dụng được tin cậy**, chạy chung tiến trình PHP
với core. Nó *có thể* làm mọi thứ PHP làm được (xoá dữ liệu, ghi file). Các quy tắc và lệnh kiểm tra
dưới đây là **hàng rào chống làm sai kiến trúc**, không phải sandbox bảo mật. Code extension phải
được review như code core.

---

## 1. `extension.json` — schema v1

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
  ],
  "description": "Danh sách đại lý và hoa hồng theo đơn.",
  "author": "Mat Bao WS"
}
```

| Trường | Bắt buộc | Quy tắc |
|---|---|---|
| `schema` | ✅ | `1`. Đổi định dạng manifest thì tăng số này |
| `id` | ✅ | `^[a-z][a-z0-9]{1,30}$` (chữ thường, số; **không** gạch ngang/gạch dưới). **Trùng tên thư mục** `extensions/<id>/`. **Không đổi** sau khi đã chạy production. Không thuộc danh sách tên dành riêng (mục 5) |
| `name` | ✅ | Tên hiển thị. Không dùng để suy ra gì |
| `version` | ✅ | Semver `x.y.z` |
| `requires_core` | ✅ | Ràng buộc kiểu composer, cùng cú pháp `App\Support\CoreUpdate\Semver` đang hỗ trợ: `>=1.2.0 <2.0.0`, `^1.2`, `~1.2`, `a \|\| b` |
| `provider` | ✅ | FQCN nằm trong namespace `Extensions\<Studly(id)>\` |
| `permissions` | ❌ | Mỗi `code` dạng `<id>.<action>`, `action` `^[a-z][a-z0-9_]*$`; `label` tiếng Việt |
| `description`, `author` | ❌ | Chỉ để hiển thị |

Manifest **không** chứa cấu hình chạy (menu, route, khoá API...). Menu đăng ký trong code (cần
`route()`), cấu hình nằm ở `extensions/<id>/config.php` và `.env`.

Ánh xạ từ `id` (một chiều, không bao giờ từ `name`):

| Thứ | Giá trị cho `id = dealers` |
|---|---|
| Thư mục | `extensions/dealers/` |
| Namespace PHP | `Extensions\Dealers\` → `extensions/dealers/src/` |
| Tiền tố bảng | `ext_dealers_` |
| Tiền tố quyền, menu, slot | `dealers.` |
| Namespace view / lang | `dealers::` |
| Config | `config('extensions.dealers.*')` |
| Tên route | `ext.dealers.*` |
| URL admin / api | `/{locale}/admin/ext/dealers/...`, `/api/ext/dealers/...` |

Cấu trúc thư mục (do `php artisan extension:make <id>` sinh ra):

```
extensions/dealers/
├── extension.json
├── README.md
├── config.php                  (tuỳ chọn)
├── src/DealersServiceProvider.php
├── routes/admin.php | web.php | api.php     (tuỳ chọn, từng file)
├── views/
├── lang/vi/ , lang/en/
└── database/migrations/
```

---

## 2. Vòng đời: discover → validate → compatibility → register → boot

Do `App\Extension\ExtensionManager` thực hiện, gọi từ một service provider của core
(`App\Providers\ExtensionServiceProvider`, thêm vào `bootstrap/providers.php`).

```
discover    đọc extensions/*/extension.json (chỉ thư mục con trực tiếp, không theo symlink ra ngoài)
   ↓
validate    schema v1 (mục 1); id == tên thư mục; provider đúng namespace; không trùng id
   ↓
state       bị tắt (mục 4)? → dừng, trạng thái "disabled"
   ↓
compat      requires_core thoả VERSION hiện tại? không → dừng, trạng thái "incompatible"
   ↓
register    autoloader cho đúng Extensions\<Studly>\ → src/ (chặn ".." và ký tự lạ trong tên class);
            provider->register() trong try/catch
   ↓
boot        provider->boot(); nạp routes, views, lang, config, migrations; trong try/catch
   ↓
loaded      | lỗi ở bất kỳ bước nào → trạng thái "failed" + ghi log, extension đó bị bỏ qua
```

- Extension **không hợp lệ / tắt / không tương thích / lỗi** không làm sập website: core vẫn chạy,
  chỉ extension đó vắng mặt. Lỗi hiện ở trang **Extensions** trong admin và `extension:list`.
- Không bắt được lỗi "chết hẳn" (hết bộ nhớ, segfault) — khi đó dùng chế độ an toàn (mục 4).
- Kết quả discover + validate được cache trong tiến trình (một request đọc manifest một lần).
  Không cache ra file ở Giai đoạn 1: số extension mỗi website nhỏ.
- `core:health` (chạy sau mỗi lần cập nhật) báo lỗi nếu một extension **đang bật** rơi vào "failed":
  bản cập nhật làm hỏng extension thì được **khôi phục** về bản cũ.

### Kiểm tra tương thích trước khi cập nhật

`CoreUpdater::check()` thêm một blocker, đọc **thẳng file manifest** (không boot extension, không
cần code bản mới):

```
với mỗi extension đang bật:
    nếu target_version (release.json) không thoả requires_core:
        blocker "extensions: <name> <version> cần core <requires_core>, bản cập nhật là <target>"
```

Trang **Cập nhật phiên bản** hiện rõ:

```
Không thể cập nhật 1.9.4 → 2.0.0 vì extension:
  Quản lý đại lý (dealers) 1.4.0 — tương thích core >=1.5.0 <2.0.0
Cập nhật extension, hoặc tắt nó (php artisan extension:disable dealers), rồi thử lại.
```

---

## 3. Extension API công khai

### Được dùng

| Namespace / class | Ghi chú |
|---|---|
| `App\Extension\**` | **API ổn định.** Giai đoạn 1: `Registries\AdminMenu`, `Registries\Permissions`, `Support\ExtensionContext`. Giai đoạn 2 thêm `Events\*`, `Slots\*` |
| `App\Models\Order`, `OrderItem`, `Product`, `ProductVariant`, `Category`, `Brand`, `Post`, `User` | Được **đọc** và tạo quan hệ (khoá ngoại). **Không** tự đổi trạng thái đơn, tiền, tồn kho bằng `->save()` — các thay đổi nghiệp vụ sẽ có hợp đồng riêng ở giai đoạn sau |
| `Illuminate\*`, `Symfony\*`, `Psr\*`, `Carbon\*` | Framework |
| `Extensions\<Studly(id)>\*` | Code của chính nó. **Không** gọi sang extension khác |
| View `admin.layouts.app` và các component `<x-admin.*>` hiện có (`resources/views/components/admin`) | Để trang extension giống admin |

### Không được dùng

Mọi thứ còn lại của `App\` (`App\Services\*`, `App\Support\*`, `App\Http\*`, `App\Console\*`, model
khác...). Cần thì xin thêm vào API công khai — một lần, cho mọi website.

### Cam kết ổn định

- Thứ trong `App\Extension\**` và danh sách model ở trên **không đổi tên / đổi chữ ký** ở bản patch
  hoặc minor.
- Muốn thay: thêm cái mới, đánh dấu cái cũ `@deprecated` (ghi bản sẽ bỏ) ít nhất một bản minor, chỉ
  xoá ở bản **major**. Ghi chú phát hành liệt kê mọi thay đổi API extension.
- Extension đặt `requires_core` theo đó, vd `>=1.2.0 <2.0.0`.

### Kiểm tra (`php artisan extension:check`)

Quét `extensions/*/src/**`, `routes/**`, `database/**` (phân tích tĩnh, token PHP):

- `use App\...` / `\App\...` ngoài danh sách được dùng → **lỗi**;
- `->bind(`, `->singleton(`, `->extend(`, `->instance(`, `->alias(` với tham số là class `App\...` → **lỗi**
  (extension chỉ ADD / LISTEN / REGISTER, không REPLACE);
- migration vi phạm quyền sở hữu bảng (mục 5) → **lỗi**.

`team.py` ("Lưu & đẩy") chạy lệnh này khi có thay đổi trong `extensions/`; CI của repo khách chạy nó
ở mỗi PR. Nhắc lại: đây là **guardrail**, không phải sandbox.

---

## 4. Bật / tắt và cứu website

### Trạng thái

- Mặc định: extension có mặt và hợp lệ thì **bật**.
- Tắt được lưu trong `storage/app/core/extensions.json` (thuộc website, không bị deploy hay cập nhật
  ghi đè):
  ```json
  { "disabled": { "dealers": { "at": "2026-10-04T10:00:00+07:00", "by": "cli", "reason": "lỗi MISA" } } }
  ```
- Dùng **file, không dùng database**: tắt được kể cả khi database hoặc chính extension đang làm
  website lỗi.

### Lệnh

| Lệnh | Việc |
|---|---|
| `php artisan extension:list` | id, tên, version, tương thích, bật/tắt, trạng thái (loaded / disabled / incompatible / failed + lỗi) |
| `php artisan extension:check [id]` | Manifest, namespace, autoload, tương thích, quyền sở hữu bảng, va chạm quyền/menu, API bị cấm |
| `php artisan extension:disable <id> [--reason=]` | Ghi vào file trạng thái |
| `php artisan extension:enable <id>` | Xoá khỏi danh sách tắt (vẫn phải hợp lệ và tương thích mới chạy) |
| `php artisan extension:make <id>` | Sinh khung thư mục chuẩn (mục 1) |

### Chế độ an toàn

- Các lệnh `extension:*` **không bao giờ boot extension**: luôn chạy được để cứu website.
- `.env`: `EXTENSIONS_ENABLED=false` → không nạp extension nào (website chạy core trơn). Dùng khi một
  extension làm sập cả `artisan`.
- Trang admin **Extensions** (superadmin): danh sách và trạng thái như `extension:list`. Giai đoạn 1
  chỉ xem; bật/tắt bằng lệnh.

---

## 5. Quyền sở hữu và va chạm

Quy tắc chung: **mọi tên do extension tạo ra mang tiền tố `<id>`**; trùng là **lỗi rõ ràng**, không
bao giờ âm thầm ghi đè.

| Thứ | Extension `dealers` được | Va chạm / vi phạm |
|---|---|---|
| Bảng | Tạo, sửa, xoá bảng `ext_dealers_*`; index; khoá ngoại **trỏ tới** bảng core | `Schema::table/drop/rename` trên bảng core hoặc `ext_<id khác>_*` → `extension:check` báo lỗi. Migration của extension cũng theo luật expand/contract như core (`MigrationSafety`) |
| Dữ liệu bảng core | Đọc; thêm dòng qua model được phép | Không thêm cột vào bảng core. Metadata nhỏ cho đối tượng core dùng `extension_data` (Giai đoạn 2) |
| Quyền | `dealers.*`, khai báo trong `extension.json` | Code không có tiền tố `<id>.` → manifest không hợp lệ. Hiện trong form vai trò ở nhóm "Extensions" |
| Menu admin | `AdminMenu::register('dealers', key: 'dealers.index', ...)` | Key trùng, hoặc không có tiền tố `<id>.` → exception khi đăng ký → extension "failed" |
| Route | Tên `ext.dealers.*`; URL admin/api có tiền tố bắt buộc (mục 1) | URL web trùng route core: route core thắng; `extension:check` cảnh báo |
| View, lang, config | `dealers::`, `config('extensions.dealers')` | — |
| `id` | Duy nhất | Dành riêng, không được dùng: `admin`, `api`, `app`, `auth`, `client`, `core`, `custom`, `emails`, `errors`, `ext`, `extension`, `extensions`, `laravel`, `mail`, `notifications`, `pagination`, `theme`, `vendor`, `validation` |

### Quyền: nguồn sự thật

- Core: `App\Support\PermissionRegistry` (như hiện nay).
- Extension: mảng `permissions` trong `extension.json` của extension **đã nạp**.
- `PermissionRegistry::definitions()` trả về cả hai; `permissions:sync` ghi vào bảng `permissions`.
- `permissions:sync --prune` **không xoá** quyền của extension còn thư mục trên website (kể cả đang
  tắt), để tắt tạm không làm mất phân quyền của vai trò.
- Extension **không** tự insert quyền bằng migration.

### Menu admin

```php
AdminMenu::register('dealers', [
    'key' => 'dealers.index',
    'label' => __('dealers::menu.dealers'),
    'icon' => 'solar:handshake-line-duotone',
    'route' => 'ext.dealers.index',
    'permission' => 'dealers.view',
    'children' => [
        ['key' => 'dealers.commissions', 'label' => '...', 'route' => 'ext.dealers.commissions', 'permission' => 'dealers.view'],
    ],
]);
```

Sidebar của core vẽ các mục đăng ký **sau** menu core, trước "Nhật ký hoạt động"; ẩn mục người dùng
không có quyền (cùng cổng quyền `Gate` như menu core). Extension không bao giờ sửa file sidebar.

---

## 6. Thay đổi ở core trong Giai đoạn 1

| Thay đổi | File |
|---|---|
| `extensions/**` vào `SITE_OWNED` (cấm cứng: bản phát hành ghi vào đó bị từ chối) | `app/Support/CoreManifest.php` |
| Bộ nạp, manifest, trạng thái, autoloader | `app/Extension/ExtensionManager.php`, `ExtensionManifest.php`, `ExtensionState.php`, `ExtensionAutoloader.php` |
| API công khai | `app/Extension/Registries/AdminMenu.php`, `Permissions.php`, `app/Extension/Support/ExtensionContext.php` |
| Provider | `app/Providers/ExtensionServiceProvider.php`, `bootstrap/providers.php` |
| Lệnh | `app/Console/Commands/Extension{List,Check,Enable,Disable,Make}.php` |
| Blocker tương thích | `app/Support/CoreUpdate/CoreUpdater.php` (`check()`), `core:health` |
| Quyền | `app/Support/PermissionRegistry.php`, `SyncPermissions` (`--prune`), form vai trò |
| Sidebar + trang Extensions | `resources/views/admin/layouts/sidebar.blade.php`, `resources/views/admin/extensions/index.blade.php`, route, `lang/{vi,en}/admin.php` |
| Kiểm tra migration extension | `app/Support/MigrationSafety.php` (thêm luật sở hữu bảng) |
| `deploy.py` bỏ qua `storage/app/core/` khi đóng gói | **sửa lỗi có sẵn**: hiện file mốc, trạng thái cập nhật và (sắp tới) trạng thái extension trên máy dev bị upload đè lên website |
| `team.py`: chạy `extension:check` khi `extensions/` đổi; CI repo khách thêm bước này | `team.py` |
| Extension mẫu `extensions/example/` (trang admin, menu, quyền, bảng `ext_example_notes`) | dùng trong test; **không** đưa vào bản phát hành / dự án khách mới |
| Tài liệu cho dev | `docs/EXTENSIONS.md` (cách viết), cập nhật `UPDATE_SAFETY.md`, `TEAM_WORKFLOW.md` |

## 7. Test (trước khi phát hành 1.2.0)

- Manifest: hợp lệ; thiếu trường; `id` sai mẫu / khác thư mục / dành riêng; provider sai namespace;
  quyền sai tiền tố.
- Vòng đời: tắt → không nạp; `requires_core` không thoả → không nạp; provider ném lỗi ở `register` và
  ở `boot` → website vẫn 200, extension "failed"; `EXTENSIONS_ENABLED=false`.
- Autoloader: chỉ nạp class đúng namespace của extension đã nạp; chặn `..`.
- Updater: blocker khi bản đích không thoả `requires_core`; không blocker khi extension đang tắt.
- Quyền: có trong form vai trò; `Gate` cho/không cho; `--prune` giữ quyền của extension đang tắt.
- Menu: hiện đúng quyền; key trùng → "failed".
- Route admin/api có tiền tố và middleware admin (chưa đăng nhập → về trang đăng nhập).
- Migration: tạo `ext_example_*` chạy; `Schema::table('orders', ...)` → `extension:check` lỗi.
- `extension:check`: `use App\Services\X` → lỗi; `app()->bind(App\...)` → lỗi.
- Không có thư mục `extensions/` → mọi thứ như trước (toàn bộ test hiện có vẫn xanh).

## 8. Ngoài phạm vi Giai đoạn 1

Sự kiện nghiệp vụ, slot trong màn hình core, `extension_data` (Giai đoạn 2 — core 1.3.0); hợp đồng
phí ship/thanh toán, lịch chạy của extension, trang cấu hình riêng (Giai đoạn 3, khi có nhu cầu thật);
cài/cập nhật extension từ xa (không có kế hoạch).
