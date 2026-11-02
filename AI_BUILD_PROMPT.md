## Đọc skill trong `.agents/skills` trước khi làm

Repository có sẵn skill mô tả chi tiết từng luồng công việc. **Đọc file này xong, đọc tiếp skill
tương ứng với việc đang làm** — đừng bắt tay sửa code khi chưa đọc. Skill chứa quy trình, ràng buộc
và checklist kiểm chứng mà tài liệu này chỉ nêu ở mức nguyên tắc.

```text
.agents/skills/
  maintain-ecommerce-core/       sửa backend
    SKILL.md
    references/architecture.md         định vị tầng sở hữu logic
    references/domain-invariants.md    bất biến nghiệp vụ
    references/verification.md         chọn test và kiểm tra deploy
  connect-ecommerce-frontend/    dựng/cắt storefront và nối API
    SKILL.md
    references/theme-cut-acceptance.md    cổng nghiệm thu cắt theme: inventory, checklist, lỗi hay gặp, smoke test, báo cáo
    references/static-first-workflow.md   xác định đang ở stage nào
    references/theme-preflight.md         rà và chuẩn hoá theme/ trước khi cắt
    references/theme-data-seeding.md      seed dữ liệu thật có sẵn trong theme
    references/laravel-blade-cutting.md   khi có thư mục theme/
    references/inline-admin-editing.md    layout client, CMS Page, inline editor
    references/editable-markup-standard.md  quy chuẩn đánh dấu vùng sửa khi cắt theme
    references/api-contracts.md           trước khi nối dữ liệu thật
```

Định tuyến theo loại việc:

| Việc đang làm                                                                                                                                                                                                                              | Skill                          | Reference bắt buộc đọc thêm                                                                                                                                                                                                                                                                                                         |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Admin Blade, API public/admin, auth, role/permission, feature, đa ngôn ngữ, catalog/variant, checkout, promotion, voucher, đơn hàng, tồn kho, review, payment/VNPAY, shipping, webhook, notification, migration, seeder, command, test | `maintain-ecommerce-core`    | `architecture.md`; thêm `domain-invariants.md` khi chạm auth/tiền/kho/đơn/payment/webhook/review/secret; thêm `verification.md` khi chọn test hoặc kiểm tra deploy                                                                                                                                                        |
| Prototype giao diện, cắt`theme/` vào Blade, thay layout client, render CMS Page, inline editing, asset storefront, route/controller storefront, nối `/api/public`                                                                     | `connect-ecommerce-frontend` | **bắt buộc** `theme-cut-acceptance.md` khi cắt/cắt lại theme hoặc sửa view client (đọc trước khi làm và trước khi báo cáo); `static-first-workflow.md`; thêm `theme-preflight.md`, `theme-data-seeding.md` và `laravel-blade-cutting.md` khi có `theme/`; thêm `inline-admin-editing.md` khi tạo/thay layout client hoặc render Page; **bắt buộc** `editable-markup-standard.md` khi cắt `theme/` hoặc đánh dấu vùng sửa; thêm `api-contracts.md` trước khi nối dữ liệu thật |

Lưu ý:

- `agents/openai.yaml` trong mỗi skill chỉ là metadata hiển thị cho agent host, không phải chỉ dẫn
  làm việc. Không cần đọc để thực thi.
- Skill và tài liệu là văn bản; khi lệch với code thì áp dụng thứ tự ưu tiên ở mục 2. Đã gặp trường
  hợp skill mô tả tính năng đã bị xóa khỏi code — luôn đối chiếu trước khi tin.
- Việc không khớp bảng trên: vẫn đọc `maintain-ecommerce-core` làm mặc định.

## 1. Nhận diện hệ thống

Đây là Laravel 12 Ecommerce Admin Core dùng để triển khai từng website bán hàng độc lập:

```text
1 khách hàng = 1 Laravel project = 1 database
```

Không phải hệ thống multi-tenant. Không tự thêm `shop_id`, tenant middleware, tenant database
hoặc package multi-tenancy.

Core cung cấp:

- Admin Blade + Bootstrap tại `/{locale}/admin`.
- REST JSON API cho storefront tại `/api/public`.
- Customer authentication bằng Laravel Sanctum.
- Phân quyền admin theo role/permission.
- Catalog, biến thể V2, tồn kho, checkout, khuyến mãi, voucher và review.
- Vòng đời đơn hàng, hoàn tiền, payment transaction và VNPAY.
- Shipping configuration, GHTK push/webhook.
- CMS post, CMS page (HTML thuần, không có builder kéo-thả), banner, menu điều hướng storefront, đa
  ngôn ngữ, media và notification.
- Feature flags nằm ở `features`/`feature_settings`, được áp theo gói dịch vụ đồng bộ từ tool nội bộ
  (`package:sync`) và superadmin ghi đè được. Cụm SaaS billing cũ (`packages`, `package_features`,
  `project_subscription`, `addons`, `invoices`) **đã bị gỡ hoàn toàn** — đó là hoá đơn của bên bán
  core, không phải nghiệp vụ cửa hàng. Không dựng lại bảng gói trong database.

Core không có storefront mặc định. Khi repository có thư mục `theme/` ở root, coi đây là giao diện
HTML nguồn do người dùng cung cấp và cắt giao diện đó thành Laravel Blade ngay trong core:

```text
theme/                         nguồn thiết kế, không phải mã production
resources/views/client/       Blade storefront sau khi cắt
public/client-assets/         CSS, JavaScript, font và ảnh của storefront
```

Không biến theme HTML thành một ứng dụng JavaScript, không dựng lại toàn bộ markup bằng chuỗi HTML
trong JS và không tạo project frontend song song. Không thêm React, Vue, Next.js, Nuxt, Inertia,
Livewire, GraphQL hoặc một frontend framework khác nếu người dùng không yêu cầu rõ ràng.

## 2. Thứ tự nguồn sự thật

Khi tài liệu và code mâu thuẫn, dùng thứ tự ưu tiên sau:

1. Migration và database constraints hiện hành.
2. Route + middleware thực tế trong `bootstrap/app.php`, `routes/*.php`.
3. Service nghiệp vụ và model hiện hành.
4. FormRequest/controller/resource và test đang chạy.
5. `public/docs/openapi.json`.
6. README, prompt, comment hoặc tài liệu cũ.

Không sao chép hợp đồng từ tài liệu cũ mà chưa đối chiếu code. Khi thay đổi API, đồng bộ route,
validation, resource, test và OpenAPI trong cùng thay đổi.

## 3. Quy trình bắt buộc trước khi sửa

1. Đọc `README.md`, `composer.json`, file này và skill phù hợp trong `.agents/skills` cùng các
   reference của nó theo bảng định tuyến ở đầu tài liệu.
2. Chạy `git status --short`; mọi thay đổi có sẵn thuộc về người dùng.
3. Dùng `rg`/`rg --files` để tìm route, controller, service, model, migration và test liên quan.
4. Đọc đầy đủ luồng gọi trước khi chỉnh sửa, kể cả middleware và side effect.
5. Nêu kế hoạch ngắn: phạm vi, file dự kiến, rủi ro và cách kiểm chứng.
6. Chỉ sửa trong phạm vi yêu cầu; bảo toàn thay đổi không liên quan.
7. Sau khi sửa, chạy test mục tiêu trước rồi mới chạy regression rộng hơn.

Không đoán trạng thái phase. Repository hiện là một core đã có nhiều module; hãy xác định trạng
thái từ code và migration hiện tại.

## 4. Bản đồ kiến trúc

```text
Admin route       routes/admin.php
Public/API route  routes/api.php
Web route         routes/web.php
Bootstrap         bootstrap/app.php

Admin controller  app/Http/Controllers/Admin
API controller    app/Http/Controllers/Api
Auth controller   app/Http/Controllers/Auth
Validation        app/Http/Requests
API output        app/Http/Resources + app/Support/ApiResponse.php

Business logic    app/Services
Shared policy     app/Support
Persistence       app/Models + database/migrations
Admin UI          resources/views/admin
Client storefront resources/views/client
Translations      lang/* + localized database fields
Tests             tests/Unit + tests/Feature
API contract      public/docs/openapi.json
```

Luồng ưu tiên:

```text
Route/Middleware
  -> FormRequest hoặc request validation
  -> Controller orchestration
  -> Service/domain policy
  -> Model/database
  -> Resource/View
```

Giữ controller mỏng khi thêm logic mới. Không thực hiện query hoặc tính tiền trong Blade.

## 5. Các bất biến nghiệp vụ

### Tenant và feature

- Không thêm multi-tenancy.
- Dùng `FeatureGate`/middleware `feature:<code>`. `FeatureGate` đọc thẳng `feature_settings.is_enabled`
  và không tra cứu package nào.
- Gói dịch vụ (Hero 1–7, `COBAN`…) chỉ tồn tại ở **tầng đồng bộ**: `App\Services\Packages\PackageSync`
  đọc gói từ tool nội bộ theo `PACKAGE_INFO_DOMAIN`, map mã gói → feature code bằng `config/packages.php`
  rồi ghi `feature_settings.package_enabled` (và `is_enabled` cho row chưa bị ghi đè). Superadmin ghi đè
  được từng tính năng (`is_overridden`), lần sync sau giữ nguyên. Tool lỗi/không tìm thấy domain không
  bao giờ tắt tính năng; snapshot gần nhất nằm ở `project_settings.package_info`.
- Hạn mức gói (`App\Services\Packages\PackageLimits`): `data_unit` = sản phẩm + bài viết,
  `email_monthly` (chỉ đếm khi gửi qua SMTP dùng chung). Chặn ở model event `creating` nên form admin,
  import CSV và import WordPress đều bị áp; áp cả cho superadmin. Hạn mức **chỉ** lấy từ tool nội bộ, core không
  có chỗ sửa: nâng hạn mức = Sales nâng gói/ký lại hợp đồng → cập nhật tool → `package:sync`.
  Không có snapshot gói (chưa đặt `PACKAGE_INFO_DOMAIN` hoặc chưa sync thành công lần nào): production
  (`packages.required`, mặc định theo `APP_ENV=production`) chặn `data_unit` = 0, admin hiện cảnh báo,
  `content:import` từ chối; local/CI/test không giới hạn. `deploy.py` bắt nhập domain gói cho mỗi site
  và kiểm với tool trước khi lưu. Hạn mức trống/0 trong một gói đã sync vẫn là không giới hạn.
- Superadmin có cơ chế bypass feature hiện hành; không thay đổi ngầm.
- Feature bị tắt phải trả đúng hành vi web/API hiện có.

Sai — controller/view không bao giờ đọc mã gói:

```php
if ($packageCode === 'basic_2m') {
    // ...
}
```

Đúng:

```php
app(\App\Support\FeatureGate::class)->require('voucher');
```

### Admin và quyền

- Admin web phải đi qua `auth`, `admin`, feature gate và `can:<permission>` phù hợp.
- API admin dùng Sanctum ability `admin` và kiểm tra tài khoản admin đang active.
- Customer token không được truy cập admin; admin token không được checkout thay khách.
- Thao tác superadmin, role, impersonation và feature settings phải giữ audit/guard hiện có.
- `FoundationSeeder` tạo superadmin theo `ADMIN_EMAIL` **chỉ khi tài khoản chưa tồn tại**. Seeder
  chạy lại trên shop đang sống (sửa chữa, deploy có tick seed) nên không được ghi đè mật khẩu, tên
  hay `is_active` của tài khoản đó — làm vậy là trả login về giá trị `ADMIN_PASSWORD` cũ và khoá
  chủ shop khỏi chính admin của họ. Cả seeder đều **chỉ tạo khi chưa có** (`firstOrCreate`, không
  `updateOrCreate`): cài đặt shop, ngôn ngữ, menu chính, danh mục mặc định, vai trò Admin đã có thì
  giữ nguyên — upsert từng đưa tên shop, logo, liên hệ, SEO về mặc định mỗi lần deploy có seed.
- Khi vô hiệu hóa user hoặc thay đổi quyền nhạy cảm, xem xét thu hồi token/session qua
  `UserAccessService`.
- Console MatBao (gateway) đọc danh sách superadmin và đặt lại mật khẩu superadmin qua
  `POST /api/gateway/command` (`GatewayCommandController` → `App\Services\SuperadminAccounts`). Ký như
  webhook AI (`App\Services\AiGateway\GatewaySignature`, secret `MATBAO_AI_WEBHOOK_SECRET`), kèm
  `nonce` dùng một lần. Chỉ chạm tài khoản có role `is_superadmin`; danh sách không bao giờ trả dữ liệu
  mật khẩu; đặt lại thì thu hồi mọi session/token qua `UserAccessService` và ghi `ActivityLogger`.
  Hợp đồng nằm trong `tests/Fixtures/gateway-contract` (`site-command.*`).

Mã quyền là `<module>.<action>` (`products.view`, `orders.update`, `roles.delete`), khai báo **duy
nhất** trong `App\Support\PermissionRegistry`. Bảng `permissions` chỉ là hình chiếu của registry qua
`PermissionSeeder`/`php artisan permissions:sync`.

- Thêm quyền = thêm vào registry rồi gắn `can:<code>` vào route. `PermissionRegistryDriftTest` fail
  nếu route đòi quyền chưa khai báo, **hoặc** registry khai báo quyền không route nào kiểm tra. Đừng
  tạo quyền không có chỗ thực thi — `manage_roles` từng nằm chết trong bảng nhiều bản phát hành.
- Quyền của role nằm ở pivot `role_permission`. `$role->permissions` là thuộc tính đọc/ghi xuyên
  xuống pivot, trả `['*']` khi `roles.is_superadmin`.
- `user_permissions` cho phép cấp thêm/thu hồi theo từng tài khoản. Thứ tự trong `Gate::before`:
  superadmin → override → role.
- Ai cũng chỉ được cấp quyền mình đang có (`AuthorizesPermissionGrants`). Sửa role không được xoá
  quyền nằm ngoài tầm của người sửa, và không ai tự sửa override của chính mình.
- `features` và impersonation vẫn là `superadmin` thuần, không hạ xuống `can:`.
- Endpoint dùng chung cho nhiều hành động phải tự kiểm tra hành động nguy hiểm hơn: `bulk` chạy dưới
  `<module>.update` nhưng `action=delete` phải kiểm thêm `<module>.delete`.

### Menu điều hướng storefront

- `menus.key` là thứ layout dùng để gọi menu (`client.partials.navigation` gọi `primary`). Mỗi
  `menu_items` trỏ tới đúng một đích do cột `type` quyết định: `page`, `category`, `post_category`
  hoặc `url`. Đổi `type` phải xoá các cột đích còn lại.
- `App\Services\MenuService` là nơi duy nhất đọc và sắp xếp menu; cả Blade lẫn `/api/public/menus/{key}`
  đều đi qua đây. Không viết lại truy vấn menu trong controller hay Blade.
- `resolveUrl()` trả `null` khi chưa có route storefront cho đích (hiện là danh mục sản phẩm và
  chuyên mục bài viết). Client render chữ thường, không tạo link dẫn tới 404.
- URL tự nhập chỉ chấp nhận đường dẫn bắt đầu bằng `/` hoặc link `http`/`https`; `javascript:` và
  `data:` bị chặn ở cả FormRequest lẫn service.
- Kéo-thả chỉ đổi thứ tự trong cùng một cấp; đổi cấp cha đi qua form và bị chặn tạo vòng lặp.
- Navigation là block động render trên trang CMS nên wrapper phải giữ `contenteditable="false"`.

### Đa ngôn ngữ

- Admin route luôn có `{locale}`.
- **URL storefront của ngôn ngữ mặc định không có tiền tố** (`/chinh-sach/x`); ngôn ngữ khác giữ tiền tố
  (`/en/chinh-sach/x`); `/vi/...` cũ chuyển 301 sang URL không tiền tố (giữ query). Lý do: SEO — mỗi
  ngôn ngữ vẫn có URL riêng cho hreflang, còn ngôn ngữ chính có URL gọn. Cơ chế (`App\Support\LocaleUrl`):
  - Sinh URL: luôn dùng `route('client.…', ['locale' => …])`. Bộ định dạng đường dẫn đăng ký trong
    `AppServiceProvider` (`URL::formatPathUsing`) cắt tiền tố của ngôn ngữ mặc định cho route tên `client.*`,
    nên canonical, hreflang, sitemap, menu, site index tự đúng. **Không** tự ghép `url('/'.$locale…)`;
    nơi buộc phải ghép tay dùng `LocaleUrl::home()` / `LocaleUrl::switchTo()`.
  - Nhận URL: `bootstrap/app.php` nạp `routes/client.php` và `routes/theme.php` hai lần — dưới `{locale}`
    (tên `client.*`, middleware `defaultLocale.redirect`) và không tiền tố (tên `root.client.*`, middleware
    `useDefaultLocale` chèn `locale` mặc định vào **đầu** tham số route, nên controller vẫn là
    `show(string $locale, …)`). Theme khai route một lần trong `routes/theme.php`.
  - `/` đăng ký sau nhóm không tiền tố: theme có trang chủ `/` thì thắng, không thì vẫn chuyển sang `/admin`.
  - Tắt bằng `MULTILINGUAL_HIDE_DEFAULT_LOCALE=false`: mọi URL storefront quay về có tiền tố như cũ.
  - Đổi ngôn ngữ mặc định trong admin làm URL hai ngôn ngữ đổi chỗ cho nhau; chỉ nên đổi lúc site còn mới.
- Hai danh sách locale trong `LanguageRegistry`: `routable()` là mọi ngôn ngữ active (URL `/{locale}/…`,
  tab nội dung), `admin()` chỉ gồm ngôn ngữ có trong `config('multilingual.admin_locales')` (mặc định
  `vi`, `en` — những locale có file `lang/`). Mở trang admin bằng ngôn ngữ chỉ dành cho storefront
  (`/ja/admin/...`, GET không phải JSON) thì `ValidateLocalizedAdminPath` chuyển 302 sang
  `adminLocale()` (`admin_fallback_locale`, mặc định `en`). Request JSON và request ghi **không** bị
  chuyển: editor trên `/ja/…` gọi `/ja/admin/site-blocks…`, và lịch sử block đọc locale nội dung từ URL.
- Public API resolve locale bằng middleware `apiLocale`.
- Field dịch dùng pattern/model/service hiện có; không tự tạo cột ngôn ngữ rời rạc.
- Slug đa ngôn ngữ đi qua `LocalizedSlugService`/`HasLocalizedSlugs`.
- API success metadata phải giữ `meta.locale`.
- Chữ giao diện admin (view, controller, FormRequest admin, JS) luôn qua `__()` với key có ở **cả**
  `lang/vi` và `lang/en`; JS tĩnh nhận chữ từ Blade (`window.X = @json(__('…'))`). `AdminTranslationCoverageTest`
  fail khi có chữ tiếng Việt viết cứng, key thiếu, hoặc hai file lang lệch key. Ghi log/lịch sử bằng `__()`
  nghĩa là lưu theo ngôn ngữ của admin đang thao tác.
- Tên website, SEO trang chủ, địa chỉ trụ sở và tên/địa chỉ chi nhánh (Cài đặt) nhập theo từng ngôn ngữ nội
  dung qua `App\Support\LocalizedSiteSettings`: ngôn ngữ mặc định giữ chỗ cũ (`shop_name`, `seo.title`,
  `contact.address`…), ngôn ngữ khác nằm trong `translations` (tên website: khoá riêng `shop_name_translations`,
  vì `shop_name` là chuỗi). Tên website không đi qua nút Dịch. `SiteBranding` và `/api/public/settings` trả theo ngôn ngữ đang đọc, trống thì
  dùng bản mặc định; lưu khi đa ngôn ngữ tắt không xoá bản dịch đã có.

### Catalog và biến thể

- Catalog V2 chọn biến thể bằng một option value thuộc mỗi option group.
- Public checkout gửi `option_value_ids`; không tin `variant_id` do client tự chọn.
- Variant phải thuộc đúng product, active và đúng combination.
- SKU, giá, promotion và tồn kho được resolve phía server.

### Giá, checkout, promotion và voucher

- Không nhận giá bán, giá vốn, discount, shipping fee hoặc grand total từ client.
- Checkout phải tính lại toàn bộ giá từ database.
- Promotion tự động áp dụng trước; voucher tính trên subtotal sau promotion.
- Voucher phải kiểm tra thời gian, trạng thái, số lượng và giới hạn mỗi khách trong transaction.
  "Một khách" = khớp **bất kỳ** định danh nào: `user_id`, email chuẩn hoá (`Voucher::emailKey`, bỏ
  `+tag`, bỏ dấu chấm Gmail) hoặc số điện thoại chuẩn hoá (`Voucher::phoneKey`). Ghi `voucher_usages`
  phải kèm hai khoá này.
- Checkout/stock/payment cần `DB::transaction()` và row lock khi cạnh tranh dữ liệu.

### Đơn hàng và tồn kho

- Tạo đơn qua `Orders\OrderCreationService` với `Orders\OrderDraft`. Đây là nơi duy nhất định nghĩa
  `subtotal`, `promotion_discount`, `grand_total` và trình tự ghi đơn (order → items → reserve
  promotion → trừ kho → payment pending → status history). Cả public checkout và tạo đơn thủ công ở
  admin đều đi qua đây; không tự tính lại tiền hoặc tự ghi `Order::create` ở controller.
- Caller sở hữu `DB::transaction()` và row lock, đồng thời tự quyết phần khác biệt hợp lệ: cách chọn
  biến thể (`option_value_ids` ở public, SKU đã chọn ở admin), nguồn giảm giá thứ hai (voucher hay
  thủ công) và nguồn phí vận chuyển.
- Chuyển trạng thái qua `OrderStateTransitionService`.
- Huỷ đơn trả lại **cả ba** thứ đơn đã giữ: tồn kho, lượt voucher và suất khuyến mãi — qua
  `Orders\OrderCancellationService` (`cancel()` hoặc `releaseReservations()` khi caller đã tự đổi
  status). Không tự gọi `OrderStockService::restore()` cho đơn bị huỷ. Webhook lặp an toàn nhờ
  `orders.reservations_released_at`.
- Đơn VNPAY/SePay chưa thanh toán tự huỷ sau TTL (`config/orders.php`, mặc định 30 phút / 24 giờ) bởi
  `orders:expire-unpaid` mỗi 5 phút; COD không bao giờ tự huỷ. Checkout giới hạn số dòng và số lượng
  mỗi dòng (`orders.max_lines`, `orders.max_line_quantity`).
- Trừ/hoàn kho qua `OrderStockService`.
- Inventory movement phải idempotent; không cập nhật stock trực tiếp ở controller.
- `completed` và `cancelled` là trạng thái đơn hàng kết thúc theo policy hiện tại.
- Refund phải đi qua luồng refund để đối soát tiền, lịch sử và hoàn kho.

### Thanh toán

- `PaymentTransactionService` là nguồn ghi nhận transaction/idempotency.
- VNPAY IPN là nguồn thay đổi payment state; browser return chỉ xác minh và hiển thị kết quả.
- SePay không redirect mà theo dõi tài khoản ngân hàng: checkout trả mã thanh toán + ảnh VietQR,
  webhook `POST /api/webhooks/sepay` là nơi duy nhất chuyển đơn sang `paid`. Xác thực bằng
  `Authorization: Apikey` hoặc HMAC-SHA256 trên `{timestamp}.{raw_body}`; idempotent theo trường
  `id` của SePay. Mã thanh toán là `order_number` bỏ dấu phân cách — đổi định dạng `order_number`
  phải sửa `Order::NUMBER_PREFIX`/`NUMBER_RANDOM_LENGTH`, nếu không webhook lặng lẽ hết khớp đơn.
- Luôn kiểm tra chữ ký, TMN code, order, payment method, amount và transaction state.
- Không log hash secret, access token hoặc credential.
- Payment mock chỉ được hoạt động trong `local`/`testing` và khi flag cho phép.

### Shipping và webhook

- Chỉ gọi endpoint đối tác trong allow-list hiện hành.
- GHTK webhook yêu cầu token, realtime tracking active, validation và idempotency.
- Không mở query-token webhook nếu không có yêu cầu tương thích rõ ràng.
- Side effect gửi mail/notification chỉ xảy ra sau commit khi phụ thuộc transaction.

### Review

- Chỉ khách có đơn `completed` chứa sản phẩm mới được review.
- Mỗi khách chỉ được review một lần cho mỗi sản phẩm.
- Nhận diện member bằng `user_id` và email tài khoản; guest bằng email đơn hàng.
- Giữ transaction/lock chống tạo trùng đồng thời.
- Public resource không để lộ email reviewer.

### Dữ liệu nhạy cảm và nội dung

- Settings tích hợp và payment payload nhạy cảm phải dùng encrypted cast/persistence hiện có.
- **Giao diện email** (Quản trị › Giao diện email, `EmailDesignController`, quyền `settings.view`/`settings.update`):
  `App\Services\EmailAppearance` lưu một setting `email_appearance` gồm bố cục (`classic`/`minimal`/`banner`),
  màu nhấn, logo/ảnh bìa (path tương đối qua `MediaUrl`), chân email, và cho từng email trong
  `EmailAppearance::EMAILS` việc bật/tắt cùng tiêu đề, dòng tiêu đề, lời mở đầu riêng. Chữ của shop là văn
  bản thuần (escape khi render, biến dạng `{ten_khach}`), chỉ dùng cho ngôn ngữ mặc định; ô trống dùng câu trong
  `lang/{locale}/emails.php`. Đặt lại mật khẩu và email thử luôn gửi. Mọi email kế thừa `emails.layout` (bảng
  + inline style, không flex/float); dữ liệu email đơn hàng dựng sẵn ở `App\Mail\OrderEmailData` từ số tiền đã
  lưu của đơn, không tính lại trong Blade. Chủ shop nhận `OwnerNewOrderMail`, không phải bản sao email của khách.
  Bản xem trước dùng `App\Mail\EmailPreviewSamples` (đơn trong bộ nhớ, không bao giờ lưu).
- Cấu hình mail resolve ở `App\Services\MailSettings` theo thứ tự: admin (`notification_settings.smtp`)
  khi đã bật và điền đủ → **mail relay MatBao** trên gateway khi có `MATBAO_AI_URL` + `MATBAO_AI_TOKEN`
  (mailer `matbao`, transport `App\Mail\Transport\MatbaoGatewayTransport` gửi `POST v1/mail/send`;
  gateway ép địa chỉ From, chặn quota email theo gói, reply-to về email chủ shop) → `.env` là dự phòng
  cuối. Website **không bao giờ** nhận mật khẩu SMTP dùng chung; không đọc lại SMTP export. Quota email
  hiển thị trong `PackageLimits` lấy từ `v1/mail/usage` của gateway, không đếm ở core. Service được áp khi
  container resolve `mail.manager`, nên **mọi** email đều dùng chung một nguồn — không cấu hình mailer
  inline tại chỗ gửi. Laravel 11+ dựng transport từ `scheme`, không đọc `encryption`; đổi cấu hình xong
  chạy `php artisan mail:refresh-smtp` (flush + `queue:restart`). Email quên mật khẩu đi qua queue
  (`QueuedResetPassword`), nên production cần queue worker.
- Google Analytics 4: số liệu đọc qua gateway (`App\Services\Analytics\AnalyticsReport` →
  `App\Services\AiGateway\GatewayClient`); service account nằm **chỉ** trên gateway, website không giữ
  private key. Measurement ID/Property ID của từng shop nằm ở `project_settings.google_analytics` (Cài
  đặt → Google Analytics) và được đẩy lên gateway qua `v1/project/connections`. Lỗi trả lý do
  `AnalyticsUnavailable`, không bao giờ đưa nguyên văn lỗi Google ra UI; token gateway không log, không
  in ra (`analytics:check`). gtag không gắn cho nhân viên (`role_id`), khung preview
  (`client_chrome=off`) hoặc khi feature `analytics` tắt.
- AI SEO Copilot (feature `ai_seo`, quyền `ai.use`): AI chỉ **đề xuất** qua gateway (`GatewayClient::task()`,
  luôn kèm `Idempotency-Key`); website tự dựng đầu vào từ record — trình duyệt chỉ gửi loại + id — và chỉ gửi nội
  dung công khai dạng text, không bao giờ gửi dữ liệu khách mua hàng. Đề xuất lưu ở `ai_suggestions` cùng giá trị
  cũ; chỉ ghi khi admin bấm Áp dụng, cần thêm quyền update của chính record, chỉ ghi đúng các trường SEO của
  locale đó, và từ chối (409) nếu trường đã bị sửa từ lúc đề xuất. Áp dụng ghi `ActivityLogger` và báo sự kiện
  (`applied`/`applied_after_edit` + `edit_distance`/`rejected`) về gateway; lỗi báo sự kiện không bao giờ chặn
  việc áp dụng. Thêm loại record = thêm vào `MetaSuggestionService::ENTITIES`, không viết luồng ghi riêng.
  **AI biên tập** (`ContentEditService`) gửi bản nội dung **đã lưu**, chạy kết quả qua đúng sanitizer của record
  (Page: `PageHtmlSanitizer`, còn lại `HtmlSanitizer`) trước khi hiển thị, và **loại bỏ** kết quả nếu danh sách
  `href`/`src` khác bản gốc — AI không được thêm, bỏ hay đổi link/ảnh. Page ghi qua
  `PageContentService::updateLocale()` (revision); suggestion của luồng này không áp dụng được qua endpoint meta và
  ngược lại.
  **Viết bài bằng AI** (`PostDraftService`, `admin.ai.post-drafts.*`): chỉ nhận brief có đủ thông tin doanh nghiệp
  + ý chính (hoặc ghi chú của chủ shop) — không viết từ một từ khoá; luôn tạo bài **chưa đăng**
    (`is_active=false`), bỏ mọi link/ảnh AI tự thêm rồi qua `HtmlSanitizer`, đi qua `package.limit:data_unit` như tạo
    bài thường. Trang sửa bài hiện "thông tin còn thiếu / câu cần kiểm chứng" tới khi bài được đăng. AI không bao giờ
    tự đăng bài.
    **FAQ** (`FaqService`) nối phần hỏi–đáp vào cuối nội dung, mọi chữ được escape. **Alt ảnh** (`AltTextService`) chỉ đọc
    ảnh từ disk `public` của site hoặc Cloudinary của chính site rồi gửi bytes — không bao giờ tải URL lạ (SSRF); chặn
    xung đột theo từng ảnh. **Sửa theo điểm SEO** (`SeoFixService`, chỉ bài viết): `PostSeoAnalyzer` quyết định quy tắc nào
    trượt, điểm trước/sau do code tính lại; đoạn mở bài sửa không được đổi link/ảnh. **Mô tả sản phẩm**
    (`ProductDescriptionService`) gửi tên, danh mục, thương hiệu, thuộc tính, mô tả ngắn — **không gửi giá/tồn kho**.
    Mọi luồng ghi qua `HandlesAiSuggestions::writeBody()` (Page: `PageContentService`). Form danh mục **cố ý không có** ô
    meta (`CatalogCrudTest`), nên không có Gợi ý meta cho danh mục.
    **Việc SEO tuần này** (`admin.ai.seo-tasks.*`): gateway xếp hạng việc từ Search Console; **AI khắc phục**
    (`SeoTaskFixer`) chỉ ghép các công cụ có sẵn theo loại việc (`low_ctr` → meta có kèm truy vấn GSC thật;
    `striking_distance` → `QueryGapService` viết thêm một đoạn + FAQ từ truy vấn dạng câu hỏi; `on_page` → meta/
    đoạn bổ sung/alt theo lỗi), chạy **từng bước một request**, áp dụng qua `apply()` của từng công cụ (không luồng
    ghi riêng) và chỉ khi admin bấm Áp dụng. `declining`/`intent_mismatch` không cho AI tự sửa. Áp dụng xong đánh dấu
    việc `done` kèm `applied_by=ai` + `tools` để gateway đo **trước/sau 28 ngày so với phần còn lại của site**
    (`v1/seo-tasks/outcomes`); "tự sửa" đánh dấu `applied_by=owner`. Đoạn bổ sung do website dựng HTML từ text
    (escape), chèn trước FAQ.
- Không trả `cost_price`, secret, token, SMTP password hoặc reviewer email qua public API.
- Lỗi 500 (mã `ERR-…` trên trang lỗi) và extension lỗi (`EXT-…`) được gửi tóm tắt lên gateway qua
  `App\Support\ErrorReporting\GatewayErrorReporter` (`POST v1/errors`), để console nội bộ tra theo mã.
  Chỉ gửi **sau khi** response đã đi, timeout ngắn, tối đa `PER_MINUTE` lần/phút, mọi lỗi khi gửi bị nuốt:
  báo lỗi không bao giờ được làm hỏng hay chậm trang của khách. Chỉ gửi mã, method, path (**không** query
  string), route, loại exception, file:dòng, câu lỗi đã che email/số điện thoại, phiên bản core —
  không bao giờ body, cookie, header hay stack trace (chúng ở log của hosting, cùng mã). Tắt bằng
  `MATBAO_AI_REPORT_ERRORS=false`. Báo cáo cập nhật core gửi kèm mã `UPD-…` trang cập nhật hiện.
- Nội dung HTML cho phép phải qua sanitizer: `HtmlSanitizer` cho rich text (post, mô tả sản phẩm),
  `PageHtmlSanitizer` cho HTML của `Page`. Không tự viết sanitizer mới.
- Upload phải validate MIME/kích thước, dùng folder allow-list và extension do server xác định.
- Media của chính site lưu path tương đối và resolve khi đọc qua `App\Support\MediaUrl`; không ghi
  URL tuyệt đối vào database, vì host sẽ sai khi đổi `APP_URL`/deploy. Ảnh ngoài (Cloudinary, CDN)
  giữ nguyên URL tuyệt đối. Khi cần path trên disk để xoá file, dùng `MediaUrl::publicDiskPath()`
  thay vì tự cắt chuỗi từ URL.
- Không đưa secret vào source, test fixture production, log hoặc error response.

## 6. Quy tắc triển khai

- Ưu tiên FormRequest cho input admin/API có cấu trúc; giữ style hiện có khi thay đổi nhỏ.
- Dùng validated data; không truyền toàn bộ `$request->all()` vào `create()`/`update()`.
- Dùng Eloquent binding/query có parameter; không nối input vào raw SQL.
- Dùng API Resource để kiểm soát output public.
- Giữ response envelope:

```json
{
  "success": true,
  "message": null,
  "data": {},
  "meta": {
    "locale": "vi"
  }
}
```

- Dùng HTTP status đúng nghĩa: `401`, `403`, `404`, `409`, `422`, `429`, `500`.
- Với thay đổi database, tạo migration tiến; không sửa migration đã deploy để thay schema.
- **Migration không được seed dữ liệu demo.** `create_invoices_table` từng chèn ba hoá đơn mẫu
  trong `up()`, nên dự án mới vừa cài đã thấy hoá đơn giả trong admin của khách (bảng đó nay đã bị
  gỡ cùng cụm SaaS billing). Dữ liệu khởi tạo
  bắt buộc đi qua seeder; migration chỉ chèn dòng cấu hình mà thiếu nó thì hệ thống không chạy
  (phương thức thanh toán, đối tác vận chuyển, ngôn ngữ mặc định).
- Quan hệ cha–con phải có khoá ngoại thật, không chỉ dựa vào tầng ứng dụng. Rule chọn theo ngữ
  nghĩa: `cascade` khi con vô nghĩa nếu thiếu cha, `set null` khi con đã chụp dữ liệu riêng và
  sống độc lập được, `restrict` khi xoá cha là thao tác nhầm. Khi thêm ràng buộc vào bảng đã có
  dữ liệu, dọn dòng mồ côi ngay trong cùng migration; chọn xoá hay set null theo **nullability của
  cột**, không theo delete-rule. `down()` phải `dropForeign(['cột'])` dạng mảng — SQLite trong test
  không gỡ được khoá ngoại theo tên constraint.
- Migration phải an toàn với dữ liệu hiện hữu và có `down()` hợp lý.
- Không thêm dependency nếu framework/core hiện có giải quyết được.
- Không đổi kiến trúc hoặc public contract ngoài phạm vi mà không báo rõ.

### Danh sách admin cập nhật tại chỗ (`admin-ajax.js`)

`public/admin-assets/js/admin-ajax.js` (JS thuần, nạp ở layout admin) cho danh sách đổi dữ liệu mà
không tải lại trang. Nó **không** quyết định gì: server trả lời y như submit thường (cùng route,
`can:`, validation, `back()->with(...)`), script chỉ thay phần danh sách từ trang trả về.

- View đánh dấu phần thay đổi bằng `data-ajax-list="<tên>"` (bảng, thanh thao tác hàng loạt, phân
  trang). Form GET trỏ về chính trang đó và link trong phần đó cùng trang (phân trang, sắp xếp) chỉ
  thay phần này, URL vẫn theo bộ lọc (F5, gửi link, nút Back đúng).
- Form ghi (xoá, công tắc hiện/ẩn, thao tác hàng loạt) nằm **trong** phần đó, hoặc có
  `data-ajax-form` (modal sửa nhanh), được gửi bằng `fetch`. Flash hiện thành toast nhờ
  `<script data-admin-flash>` trong `admin.layouts.toast`. `data-no-ajax` để giữ submit thường (vd.
  đăng nhập nhanh).
- Script của trang gắn vào dòng phải đăng ký qua `AdminAjax.ready(fn)` (chạy lại cho phần mới thay)
  hoặc lắng nghe ở `document`. Script tự xác nhận rồi gửi form thì gọi `AdminAjax.submit(form)`.
- Đọc `action`/`target` của form bằng `getAttribute`: input tên `action` (form bulk) che thuộc tính
  cùng tên.
- Công tắc hiện/ẩn một dòng là `admin.shared.status-switch`, đi qua endpoint `bulk` sẵn có của module
  với một id. Sửa nhanh giá/tồn kho ở danh sách sản phẩm qua `ProductService::quickUpdate()` (khoá
  dòng, chỉ sản phẩm không có biến thể).

### Hướng dẫn sử dụng từng trang admin (driver.js)

Người dùng đã yêu cầu rõ, nên **driver.js** (MIT, bản 1.8.0) được phép cho riêng tính năng này:
`public/admin-assets/libs/driver.js/` là file tĩnh vendor sẵn, không npm, không bước build.

- Trang nào có hướng dẫn và các bước theo thứ tự khai báo ở **một nơi duy nhất** là
  `app/Support/AdminTours/tours.php`, do `App\Support\AdminTours` đọc. Chữ hiển thị nằm ở `lang/{vi,en}/tours.php`, là văn bản thuần và
  được escape trước khi đưa vào driver.js (thư viện này render bằng `innerHTML`).
- Mỗi bước trỏ tới phần tử có `data-tour="<target>"` trong view. Đổi hoặc xoá phần tử đó thì phải
  sửa bước tương ứng: `AdminToursTest` render từng trang và fail khi thiếu target bắt buộc. Phần tử
  chỉ có với một số quyền, tính năng hoặc trạng thái thì đánh dấu bước là optional; trình duyệt tự
  bỏ qua bước không tìm thấy phần tử.
- Chỉ trang có hướng dẫn mới nạp driver.js. Hướng dẫn **không bao giờ tự bật**: chỉ chạy khi admin
  bấm nút "?" trên header.
- **Mọi trang trong sidebar phải có hướng dẫn** (`AdminToursTest` fail nếu thiếu). Thêm trang mới:
  thêm route và các bước vào `AdminTours/tours.php`, thêm text cho cả hai ngôn ngữ, gắn `data-tour`
  vào view. Không viết hướng dẫn bằng JS riêng trong từng view.

## 7. An toàn dữ liệu và deployment

Không chạy các lệnh sau trên database không xác định:

```text
migrate:fresh
migrate:reset
db:wipe
seeder cũ có truncate/delete hàng loạt
```

CI/CD (`.github/workflows/ci.yml`): mọi PR và push `main`/`dev` chạy toàn bộ test trên PHP 8.3; push
`dev` mà test đạt thì tự deploy lên **staging** `corewbws-staging.webwow.vn` (nơi test webhook VNPay,
SePay, GHTK bằng key sandbox). `main` không tự deploy ra website khách — rollout qua tool. Staging chạy
bằng Docker: `Dockerfile` (serversideup/php 8.3 fpm-nginx) được CI build, smoke test, đẩy lên GHCR rồi
deploy qua `infra/compose.yml` + `infra/deploy.sh` của repo MatBaoAI-SEO (web container tự chạy
`migrate --force` và cache khi khởi động; worker và scheduler là container riêng). `.env` mount lúc chạy,
không bao giờ đóng vào image. Hướng dẫn dựng server và secrets GitHub: `docs/DEPLOY.md` của MatBaoAI-SEO.

Quy trình cài core:

```bash
php artisan core:install
php artisan core:check
```

Production yêu cầu tối thiểu:

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY riêng
HTTPS
document root trỏ vào public/
queue worker được giám sát
schedule:run mỗi phút
QUEUE_AFTER_COMMIT=true
```

Không đọc hoặc in giá trị secret trong `.env`. Chỉ kiểm tra tên key hoặc trạng thái boolean khi
cần thiết.

### Cập nhật phiên bản core: core và theme

Mỗi website là một bản cài riêng nhưng **core** là chung: một bản phát hành thay file core trên mọi
website, không đụng phần của website. Phiên bản nằm trong file `VERSION` (semver).

- `App\Support\CoreManifest` là nguồn sự thật duy nhất, dạng **allowlist**: chỉ đường dẫn trong
  `CoreManifest::CORE` là core (app, config, database, lang, routes của core, views admin/api/auth/
  components/core/emails/errors/vendor, `public/admin-assets`, `public/client-assets/vendor`, `public/docs`,
  `public/index.php`, `artisan`, composer.json/lock, `VERSION`, `.agents/skills`, `AI_BUILD_PROMPT.md`,
  `CLAUDE.md`, `README.md`, `docs/`). Skill và tài liệu hướng dẫn AI là core: mọi website nhận bản mới qua
  bản cập nhật. Sửa hoặc **thêm** file trong các đường dẫn đó ngay trên website là lệch file core và chặn
  lần cập nhật sau — sửa ở repo này. Ghi chú riêng của một dự án đặt ngoài các đường dẫn core (ví dụ
  `PROJECT_NOTES.md` ở root). `vendor/` là gói dependencies riêng,
  chỉ phát hành khi composer.lock đổi. Mọi thứ khác thuộc website và bản cập nhật không bao giờ ghi:
  theme `resources/views/client/**`, `public/client-assets/css`, `routes/theme.php`, `extensions/**`,
  `.env`, `storage`, `.htaccess`, robots, logo. Thêm thư mục core mới thì phải thêm vào allowlist (kèm test).
- **Extension** (`extensions/<id>/`, `docs/EXTENSIONS.md`, thiết kế `docs/EXTENSIONS_DESIGN.md`): logic
  riêng của một website — trang admin, bảng `ext_<id>_*`, quyền `<id>.*` — không sửa file core.
  `App\Support\Extensions\ExtensionManager` + `App\Providers\ExtensionServiceProvider` sở hữu vòng đời
  (extension lỗi chỉ tự nó bị bỏ qua); `App\Extension\**` là API công khai ổn định (chỉ đổi ở bản major);
  `CoreUpdater::check()` chặn bản core không thoả `requires_core` của extension đang bật. Bỏ thư mục
  `extensions/` thì hệ thống chạy y như không có extension layer.
- **Không sửa file core trên từng website.** Thay đổi riêng của khách đi vào theme; route riêng của theme
  vào `routes/theme.php` (core nạp cả dưới `{locale}` lẫn không tiền tố cho ngôn ngữ mặc định, tên `client.`); component Blade riêng vào
  `resources/views/client/components` (cùng namespace `x-client::`, ghi đè được). Cần đổi hành vi core
  thì sửa core ở repo này để mọi website cùng nhận. Layout theme chỉ include `core.head` và
  `core.editor`, không chép partial của core.
- `php artisan core:status` báo phiên bản và file core bị sửa tay so với lúc cài (checksum trong
  `storage/app/core/installed.json`); `--baseline` ghi mốc lần đầu hoặc sau khi đã gỡ chỗ lệch.
  Website lệch thì bản cập nhật dừng, không ghi đè. `php artisan core:manifest` in danh sách file core
  kèm sha256 (dùng khi build bản phát hành; `--owner=path` cho biết một file thuộc bên nào).
- **Chặn sửa core trong dự án khách bằng công cụ, không chỉ bằng lời dặn** (`.claude/hooks/core-guard.php`,
  đọc cùng `CoreManifest`; chỉ tác dụng trong clone có remote `core`, repo core thì sửa bình thường):
  hook `PreToolUse` của Claude Code (`.claude/settings.json`) từ chối Edit/Write vào file core;
  `.githooks/pre-commit` từ chối commit có file core, trừ khi đang merge một bản core và file đúng nội
  dung bản đó; `composer install` tự trỏ `core.hooksPath` về `.githooks`. Cấu hình Claude riêng của dự án
  đặt ở `.claude/settings.local.json`. Bị chặn thì đưa thay đổi vào theme/extension, **không** tìm cách
  vượt (`--no-verify`, sửa hook) — file sửa lén vẫn bị gateway phát hiện.
- Website báo số file core bị sửa và tên file kèm lần hỏi bản mới mỗi giờ (`drift`, `drift_files` trên
  `GET v1/core/update`); console MatBao hiện tình trạng "Sửa core". Chỉ **console MatBao** khôi phục được
  (`core.restore` qua `POST /api/gateway/command` → `core:run-requested` → `CoreRestorer`): tải lại đúng bản
  website đang chạy, kiểm chữ ký, sao lưu file bị sửa vào `storage/app/core/drift-backups/<thời điểm>/`
  rồi đặt lại bản gốc, báo `restored`. Admin của website **cố ý không có** nút khôi phục.
- **Migration expand/contract.** Cập nhật hỏng thì chỉ trả **code** về bản cũ, không bao giờ
  `migrate:rollback` trên website khách; vậy schema mới phải chạy được với code bản trước. Trong `up()`
  chỉ thêm bảng, cột nullable/có default, index, dữ liệu. Không xóa/đổi tên cột hoặc bảng, không
  `->change()`, không truncate. Bỏ cột cũ làm ở một bản **contract** sau, khi mọi website đã chạy code
  không còn dùng nó: migration đó ghi `@contract-migration` kèm lý do và bản phát hành chứa nó không
  rollback-safe (cần backup, không tự rollout). `App\Support\MigrationSafety` +
  `CoreManifestTest` chặn vi phạm trong CI với migration từ `MigrationSafety::POLICY_FROM`.
- **Bản phát hành có chữ ký.** Tag `vX.Y.Z` (khớp `VERSION`) chạy `.github/workflows/release.yml`:
  test, `composer install --no-dev`, `core:release` build `release.json` + `release.json.sig` +
  `core-X.Y.Z.zip` + `dependencies-{lock}.zip`, `core:verify-release`, rồi đăng GitHub Release.
  Kênh theo tag: `-dev`/`-alpha` → dev, hậu tố khác → beta, không hậu tố → stable. Chữ ký ECDSA P-256
  qua ext-openssl; private key chỉ nằm trong secret `CORE_RELEASE_SIGNING_KEY`, public key trong
  `config/core_update.php`. `App\Support\CoreUpdate\ReleaseVerifier` từ chối gói sai chữ ký, artifact
  hỏng, file ngoài danh sách, đường dẫn thoát thư mục, hoặc bất kỳ đường dẫn nào thuộc
  `CoreManifest::SITE_OWNED` (kể cả khi allowlist của bản phát hành khai báo nó). Vận hành:
  `docs/CORE_RELEASES.md`.
- **Cài trên website: `php artisan core:update`** (chỉ CLI/cron, không gọi từ request web).
  `App\Support\CoreUpdate\CoreUpdater`: `--check` liệt kê lý do chặn (`UPDATE_BLOCKED`: chữ ký,
  không mới hơn, `min_from`, PHP, lệch file core, cần backup, thiếu gói vendor, không ghi được, thiếu
  dung lượng, đang khoá, lần trước bị gián đoạn). Khi cài: khoá → sao lưu đúng các file sẽ ghi/xoá →
  giải nén vào staging → `down` → thay `vendor/` (chỉ khi composer.lock đổi) và file core (ghi file tạm
  rồi rename) → trong **tiến trình mới**: `package:discover`, `migrate --force`, `optimize:clear`,
  (`optimize` nếu site đang cache config), `core:health` → ghi `installed.json` → `up`. Lỗi sau khi bắt
  đầu thay file thì tự trả code cũ và `up`; **không bao giờ `migrate:rollback`**. `core:rollback` trả
  code của lần cập nhật gần nhất (sau lỗi, sau khi bị gián đoạn, hoặc bằng tay). Trạng thái, lịch sử,
  backup (giữ 3 bản) nằm trong `storage/app/core`. `core:health` phải nhanh, không side effect, và
  luôn tồn tại: bản cập nhật gọi nó để quyết định giữ hay trả lại.
- **Update service trên gateway** quyết định website nào nhận bản nào, khi nào: kênh, ghim phiên bản,
  rollout theo %, pause; website vẫn tự verify mọi gói. `App\Support\CoreUpdate\CoreUpdateService`:
  `core:poll` mỗi giờ vào một phút riêng của website (`pollMinute()`, rải đều trong giờ) hỏi
  `GET v1/core/update`, chỉ tự cài khi gateway trả `auto`. Trang
  `admin/core-update` chỉ **xếp lịch** (`storage/app/core/requested.json`). Giống WordPress, admin của
  website (quyền `settings.update`) xem trang và bấm cập nhật được, trừ khi superadmin tắt công tắc trên
  trang đó (`App\Support\CoreUpdate\CoreUpdateAccess`, `project_settings.core_update_admin_access`,
  không có dòng = bật). Rollback, baseline và công tắc luôn chỉ superadmin. Còn `core:run-requested`
  chạy bằng cron mỗi phút; không bao giờ cài trong request web. Mọi lần cài, lỗi, quay lại đều báo
  về `POST v1/core/report`. `CORE_UPDATE_ENABLED=false` tắt cả hai lệnh (staging Docker, máy dev).

## 8. Chiến lược test

Với mỗi thay đổi:

1. Viết test tái hiện lỗi hoặc contract mới.
2. Test happy path, validation, authorization và boundary case.
3. Với tiền/stock/payment/webhook, test idempotency và transaction rollback.
4. Với tài nguyên user-owned, test IDOR bằng user khác.
5. Với public output, test không rò rỉ field nhạy cảm.
6. Chạy file test mục tiêu.
7. Chạy toàn bộ suite nếu phạm vi cho phép.

Lệnh chuẩn:

```bash
php artisan test --compact tests/Feature/RelevantTest.php
php artisan test --compact
php -l path/to/changed.php
php composer.phar validate --no-check-publish
php composer.phar audit --locked --no-interaction
vendor/bin/pint --test                                  # CI chặn nếu sai format; sửa bằng vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G            # CI chặn lỗi mới ngoài phpstan-baseline.neon
```

`phpstan-baseline.neon` chỉ được thu nhỏ: sửa xong lỗi thì xoá entry tương ứng, không tự sinh lại
baseline để che lỗi mới.

Nếu full suite đã lỗi do thay đổi có sẵn, không sửa file ngoài scope để “làm xanh”. Báo chính xác:
test nào lỗi, lỗi có liên quan hay không và test mục tiêu đã đạt gì.

## 9. Đồng bộ API và storefront

Khi người dùng đặt giao diện vào `theme/` ở root, quy trình mặc định là:

```text
Kiểm kê toàn bộ theme/
  -> lập page map và asset map
  -> giữ nguyên giao diện HTML đã duyệt
  -> cắt shell/partial/component Blade
  -> tạo route + controller storefront Laravel
  -> thay mock bằng dữ liệu backend
  -> nối JavaScript chỉ cho tương tác bất đồng bộ
  -> test route, asset và giao diện
```

- `theme/` chỉ là input tham chiếu. Không dùng trực tiếp nó làm production output và không chỉnh bản
  nguồn trừ khi người dùng yêu cầu.
- Tạo storefront tại `resources/views/client`, tách tối thiểu thành `layouts`, `partials`,
  `components` và các page Blade.
- Chuyển link và asset tĩnh sang `route()`, `asset()`, `@csrf`, `old()` cùng Blade directive phù
  hợp; không để link `.html` hoặc asset relative từ `theme/` trong view production. Project không
  dùng Vite/npm build — asset là file tĩnh trong `public/`; không thêm `@vite` hoặc bước build
  frontend nếu người dùng không yêu cầu.
- Đưa asset storefront vào `public/client-assets`; không trộn vào `public/admin-assets`.
- Markup sản phẩm, danh mục, bài viết, giỏ hàng và đơn hàng phải nằm trong Blade component/loop;
  không dựng lại toàn bộ các vùng này bằng chuỗi `innerHTML` trong JavaScript.
- JavaScript chỉ phụ trách menu, slider, chọn biến thể, cập nhật giỏ hàng, API bất đồng bộ và trạng
  thái loading/error; dùng một API client chung khi cần.
- Với render đầu trang cùng origin, ưu tiên controller/service Laravel truyền dữ liệu vào Blade;
  không gọi HTTP ngược từ PHP vào chính `/api/public`.
- Hệ quả bắt buộc của quy tắc trên: khi một truy vấn đọc phục vụ **cả** API và Blade, logic đó phải
  nằm trong service dùng chung, không viết inline trong controller. Vì Blade không được gọi API của
  chính mình, viết inline nghĩa là sẽ có hai bản lọc/sắp xếp/eager-load rồi lệch nhau. Ví dụ hiện có:
  `Catalog\ProductQueryService` là nơi duy nhất định nghĩa cách storefront lọc và load sản phẩm —
  thêm filter mới thì sửa ở đó, không sửa trong controller.
- Service đọc nhận mảng filter (dùng đúng tên tham số công khai), không nhận `Request`, để controller
  Blade và test gọi được mà không cần HTTP.
- Không thay đổi thiết kế đã duyệt trong lúc cắt component hoặc nối API.
- Không trộn storefront vào admin views.
- Chỉ giữ storefront ở project riêng khi người dùng chỉ định rõ thư mục/project đích khác.
- Nếu không có `theme/` và không có storefront hiện hữu, không tự phát minh giao diện.
- Chỉ loại bỏ mock data sau khi phần API tương ứng được kiểm chứng.

### SEO storefront

- Thẻ SEO đến từ `@include('core.head')` (partial `core.partials.seo-head`): `robots`, canonical,
  hreflang, Open Graph/Twitter, JSON-LD `Organization` và mã **Cài đặt → Embed** phần header. Phần
  footer của Embed đến từ `@include('core.editor')`. Embed là script thô chạy cả trong editor của admin,
  nên **chỉ superadmin** sửa được; `settings.update` không chạm tới hai khoá này. Layout theme **không** include partial SEO nào
  khác và không có composer riêng: mọi thứ nằm sau hai dòng `core.*`, nên site đang chạy nhận bản
  sửa qua cập nhật core (`resources/views/client/**` thuộc website, cập nhật không bao giờ chạm tới).
- `<title>` và meta description vẫn do layout theme in (`@yield('title')`,
  `@yield('meta_description')`): theme cắt trước khi có partial này đã in chúng, và hai thẻ
  `<title>` còn tệ hơn không có. Page Blade đặt hai section này từ `$seo->title` / `$seo->description`.
- Controller storefront truyền `$seo`:
  - trang có record + route `{locale}/…/{slug}`: `SeoMetaFactory::forRecord()`;
  - **trang chi tiết sản phẩm của theme**: `SeoMetaFactory::forProduct($product)` với sản phẩm lấy từ
    `ProductQueryService::findActiveDetail()` — có JSON-LD `Product` (giá/`AggregateOffer` theo biến
    thể đang bán, còn hàng theo đúng luật tồn kho của checkout, `AggregateRating` chỉ từ review đang
    hiện) và breadcrumb qua danh mục;
  - **trang bài viết của theme**: `SeoMetaFactory::forPost($post)` — tôn trọng `seo_title`,
    `seo_description`, `robots_index`/`robots_follow`, `canonical_url` của bài; thẻ chia sẻ dùng
    `og_title`/`og_description`/`og_image` riêng của bài nếu có (rỗng thì theo tiêu đề/mô tả SEO và ảnh
    đại diện); JSON-LD theo `schema_type` (`Post::SCHEMA_TYPES`: Article, BlogPosting, NewsArticle) và
    `FAQPage` dựng từ mục "Câu hỏi thường gặp" có thật trong bài (`App\Support\Seo\FaqSchema`, tắt bằng
    `schema_faq`). Không bịa câu hỏi chỉ để có schema.
    Trang không truyền `$seo` vẫn có thẻ, dựng từ hai section trên. Không tự viết canonical, hreflang
    hay `robots` trong page Blade.
- Route chi tiết của theme đặt tên `client.products.show` / `client.posts.show` với tham số `{slug}`:
  khi đó chúng có canonical/hreflang đúng và tự vào `sitemap.xml`. Đặt tên khác thì trang vẫn có thẻ
  (canonical là URL đang mở) nhưng không vào sitemap.
- Trang tĩnh của theme (route GET tên `client.*` chỉ có tham số `{locale}`: trang chủ, giới thiệu, liên
  hệ…) tự vào sitemap ở mọi ngôn ngữ, trừ route chuyển hướng và trang riêng tư (giỏ hàng, thanh toán,
  tài khoản, đăng nhập, tìm kiếm, đơn hàng…). Loại thêm bằng `->defaults('sitemap', false)`. Không viết
  sitemap riêng trong theme.
- Bản ghi chỉ khai báo những ngôn ngữ nó **có chữ** (`App\Support\Seo\RecordLocales`, theo title/name):
  hreflang và sitemap bỏ ngôn ngữ chưa dịch; mở bản ghi ở ngôn ngữ chưa dịch thì canonical trỏ về ngôn
  ngữ fallback đang hiển thị.
- Chỉ **production** được index (`SeoMetaFactory::indexable()`): staging/local trả `noindex` trong
  meta và `Disallow: /` trong `robots.txt`. Trang được index còn kèm `max-snippet:-1, max-image-preview:large, max-video-preview:-1` (`SeoMeta::robots()`), để Google hiện ảnh lớn ở Search và
  Discover; thẻ X là `summary_large_image` khi trang có ảnh.
- **Khung SEO admin dùng chung** cho bài viết, sản phẩm và trang chính sách: partial
  `admin.shared.seo-box` (+ `admin-assets/js/forms/seo-box.js`, `admin-assets/css/seo-box.css`), cấu hình
  bằng tham số (trường tiêu đề/mô tả SEO, selector tiêu đề/slug/thân bài/ảnh, tab ngôn ngữ). Phía server:
  `App\Support\Seo\SeoBox` (luật validate, chỉ lưu trường form có gửi để import/API không xoá mất,
  tính `seo_score`). Loại record mới muốn có khung thì thêm vào `SeoBox::MODELS` và dùng lại partial,
  không chép khung thứ hai. `SeoMetaFactory::forProduct()`/`forPage()` đọc robots, canonical, trường
  chia sẻ và FAQ của record giống `forPost()`.
- **IndexNow** (`App\Services\Seo\IndexNow`, `config/seo.php`, `SEO_INDEXNOW`): bài viết/sản phẩm/trang
  công khai và cho index khi được tạo, sửa, ẩn hoặc xoá thì URL được gom và gửi **một lần mỗi request**
  (job `SubmitIndexNow`), chỉ ở production. Khoá suy ra từ `APP_KEY`, file `/{khoá}.txt` do
  `SeoFileController::indexNowKey` trả. Google không dùng IndexNow (Bing, Yandex, Naver… có).
- Điểm SEO bài viết tính ở **hai nơi phải khớp nhau**: `App\Services\PostSeoAnalyzer` (server, lưu
  `seo_score`) và `analyzeSEO` trong `admin/posts/_form-assets.blade.php` (trình duyệt). Trọng số chỉ khai
  báo ở `PostSeoAnalyzer::WEIGHTS` (cộng đủ 100) và được đưa vào JS bằng `@json`. Thêm/sửa tiêu chí thì sửa
  cả hai bên và nhóm hiển thị (`$seoGroups` trong `admin/posts/_form.blade.php`).
- JSON-LD chỉ dựng từ dữ liệu có thật qua `App\Support\Seo\StructuredData`, in bằng
  `StructuredData::script()` (escape `<`/`>` để tên shop không đóng được thẻ `<script>`). Không bịa
  rating, giá hay tồn kho cho schema; sản phẩm giá 0 ("liên hệ") không có `offers`.
- `robots.txt` và `sitemap.xml` là route Laravel (`SeoFileController`, `SitemapService`, view
  `core.seo.sitemap`). **Không đặt file cùng tên trong `public/`** — web server trả file tĩnh trước
  khi tới Laravel. `public/robots.txt` thuộc website nên cập nhật core không xoá nó: site cài trước
  bản này phải xoá tay một lần. Sitemap cache 1 giờ (`SitemapService::flush()`).

### Chuyển hướng 301 và chuyển site từ WordPress

Mục tiêu: site khách chuyển từ WordPress sang không mất thứ hạng. Mỗi link cũ phải dẫn tới đúng
trang mới bằng 301, và tiêu đề/mô tả Google đang hiển thị phải được giữ nguyên.

- `App\Services\Seo\RedirectService` chỉ được gọi từ handler `NotFoundHttpException` trong
  `bootstrap/app.php`: redirect **chỉ áp dụng khi không route/record nào trả lời URL**. Trang đang tồn
  tại không bao giờ bị chuyển hướng, và request bình thường không tốn query nào. Không chuyển nó thành
  middleware chạy mọi request.
- Bảng `redirects`: `source_path` lưu dạng chuẩn hoá (`RedirectService::normalize`: decode, chữ thường,
  `/` đầu, bỏ `/` cuối, query sắp xếp). `redirectable_*` trỏ tới record đích, nên đích **đi theo slug
  hiện tại** (`urlFor()` theo route theme `client.posts.show`, `client.products.show`, `client.pages.show`,
  `client.categories.show`, `client.post-categories.show`). `target_url` là đích dự phòng hoặc đích tự
  nhập. URL nội bộ lưu dạng **tương đối**, để dữ liệu dời được từ staging sang domain thật. Mã 301, 302,
  307, 410; tự trỏ về chính nó thì bỏ qua (chống lặp); query của khách (utm…) đi kèm theo.
- URL vẫn trả 404 được ghi vào `not_found_logs` (tối đa `NOT_FOUND_LOG_LIMIT` dòng; `redirects:prune-404`
  chạy hằng tuần, giữ 90 ngày). Không ghi admin, `/api`, asset, và các đường dò lỗ hổng (`wp-login.php`,
  `.env`, `*.php`…).
- Admin `admin/redirects` (quyền `redirects.*`): danh sách, tab Lỗi 404 (bấm "Tạo chuyển hướng"), nhập/xuất
  CSV `source,target,status`. Chuyển hướng admin tạo tay (`origin = manual`) **không bao giờ** bị import
  ghi đè.
- Import WordPress (WXR ở admin bài viết và `wp:import` qua REST) bắt buộc:
  - đọc SEO qua `App\Services\WordPressSeoMeta`. Rank Math (`rank_math_*`) ưu tiên trước Yoast
    (`_yoast_wpseo_*`). Biến mẫu `%title%`/`%%sitename%%`… được thay bằng chữ thật, biến không biết thì
    bỏ. `rank_math_robots` đọc bằng so chuỗi, **không `unserialize()`** dữ liệu export. Canonical trỏ
    về chính bài thì bỏ. REST lấy `yoast_head_json`, hoặc `/rankmath/v1/getHead` khi site bật Headless
    của Rank Math;
  - gọi `RedirectService::remember()` cho mọi bài, trang, sản phẩm, danh mục (kể cả bản bị bỏ qua vì
    trùng) và ảnh `/wp-content/uploads/...` đã tải về;
  - sau khi import hết, sửa link nội bộ trong nội dung đã import sang URL mới (`rewriteLinks()`), để
    không tốn một bước chuyển hướng mỗi lần click.

### Trình soạn thảo trong admin

Mọi ô nội dung HTML trong admin (bài viết, sản phẩm, trang chính sách, mô tả danh mục/thương hiệu/
chuyên mục, kể cả modal sửa nhanh) dùng **một** trình soạn thảo: Jodit (MIT, vendor tĩnh trong
`public/admin-assets/libs/jodit/`, bản dịch tiếng Việt nằm trong `admin-assets/js/forms/rich-editor.js`).

- Form chỉ khai báo: một field ẩn được submit và `<div class="rich-editor" data-target="{id field}" data-preset="full|page|basic">` chứa HTML đã lọc, rồi `@include('admin.shared.rich-editor-assets')`.
  Không tự gọi `Jodit.make`, không nạp thư viện editor khác.
- Script khác chạm vào editor qua `AdminRichEditor.forTarget(id)` (`getHTML`/`setHTML`/`getText`) và
  sự kiện `rich-editor:change`; không đọc DOM bên trong Jodit.
- Ảnh chèn từ thư viện Media (nút ảnh mở `media-picker`), không upload hay base64 trong editor.
- Mọi upload đi qua `CloudinaryService::uploadFile()`, nơi `Media\ImageOptimizer` xoay theo EXIF,
  giới hạn cạnh dài (`media.optimize.max_dimension`), chuyển JPG/PNG/WebP sang WebP (PNG lossless) và
  bỏ metadata; thư mục `settings` giữ định dạng gốc. Kết quả không nhỏ hơn thì giữ file gốc. Không
  lưu file upload bằng đường khác để né bước này.
- HTML từ editor lưu qua `HtmlSanitizer::cleanRichText()`: chỉ giữ CSS inline trong `RICH_TEXT_CSS`,
  iframe chỉ YouTube/Vimeo, `<figure>/<figcaption>` cho chú thích ảnh. `clean()` (không style inline)
  vẫn dành cho import và chữ AI viết. Site block storefront lưu qua `cleanSiteBlock()`: như `clean()`
  cộng đúng một style là `color` trên `<span>` (nút màu chữ của inline editor); mọi style khác vẫn bị
  bỏ để vùng giữ font, cỡ chữ, khoảng cách của theme. Không chuyển site block sang `cleanRichText()`. Thêm định
  dạng mới thì mở rộng sanitizer kèm test, không nới bằng cách tắt lọc.

### Sửa trực tiếp trên storefront

Core **không có** page builder kéo-thả. Tính năng đó đã bị loại bỏ cùng GrapesJS,
`PageBuilderService` và các cột `builder_data`, `published_css`, `schema_version`. Không dựng lại nó
và không thêm thư viện editor mới nếu người dùng không yêu cầu rõ ràng.

Thêm ô nội dung vào một vùng lặp (`site_lists`) **không phải** page builder và không mâu thuẫn với đoạn
trên: template vẫn quyết định ô trông thế nào và vùng nằm ở đâu, database chỉ lưu danh sách id. Ô thêm
vào render thành sibling mang đúng tag và class của ô gốc, nên không thể sinh ra layout mà thiết kế
chưa từng có — biên tập viên thêm được thành viên thứ năm, không thêm được một section mới.

Cơ chế hiện hành là inline editing bằng `contenteditable` thuần của browser, không thư viện ngoài.
Khi cắt hoặc thay layout client, phải giữ nguyên cơ chế này:

- Layout chung include editor bằng **một dòng** `@include('core.editor')`, đúng một lần, gần cuối
  `<body>` (con trực tiếp của `<body>`), và `@include('core.head')` trong `<head>`. Editor là core
  (`resources/views/core/`): không liệt kê từng partial, không sao chép hay nhúng lại mã editor vào
  theme hoặc từng page — bản cập nhật core thêm/đổi panel thì theme tự có.
- **Edit mode có đúng một chủ sở hữu: `admin-bar`.** Nó quyết định vùng nào đang mở, giữ các thay đổi
  đang chờ, và công bố `window.clientBlocks` (`isEditing`, `activeRegion`, `markDirty`, `status`,
  `on('open'|'close'|'mode')`, `urls`, `csrf`, `locale`). `inline-blocks` chỉ là thanh công cụ nhìn
  vào trạng thái đó. Hai chủ sở hữu trên cùng `[data-block-key]` sẽ cùng tưởng mình biết vùng nào
  đang mở, và bên đoán sai ghi đè DOM của bên kia.
- Lệnh định dạng và "ẩn vùng" đi theo luồng chờ **Lưu** như một lần gõ phím. Chỉ ba thao tác cấu
  trúc gọi server ngay — khôi phục nội dung gốc, thêm ô, xoá ô — vì không cái nào diễn đạt được
  bằng "HTML mà vùng này sẽ có sau khi Lưu". Thêm ô (`<x-client::list-add>`) tải lại trang để
  Blade render ô mới, nên bị chặn khi còn thay đổi chờ Lưu (`clientBlocks.hasPending()`).
- Toolbar còn có: nhãn vị trí ("Section › Ô › Tiêu đề “…”") làm tiêu đề menu "…", căn lề lưu **token**
  (`site_blocks.align`: left/center/right/justify → class `client-align-*` trong `core.head`), ✨ **Sửa
  với AI** (`admin.ai.rewrite` → gateway `content.rewrite`, chỉ trả chữ thuần; trình duyệt chèn bằng
  `insertText` nên đi theo luồng chờ Lưu và Ctrl+Z được; chỉ hiện khi `AiAccess::check()` cho phép), và
  menu "…". **Nhân bản thông minh** nhân bản đơn vị nhỏ nhất mà template đã lặp:
  - Vùng nằm trong ô của vùng lặp (`<x-client::list-item>`): nhân bản **cả ô**, chèn ngay sau ô gốc
    trong cùng thẻ cha, nên grid/flex của theme tự xuống hàng. Ô có thêm Chuyển lên / Chuyển xuống /
    Xoá ô.
  - Vùng chữ đứng riêng (mọi `<x-client::editable>`, trừ vùng nằm trong link/nút): "Nhân bản đoạn
    này" thêm một bản sao vào danh sách `{key}.extra` của nó, render ngay sau vùng gốc với **đúng tag
    và class** của vùng gốc (cùng cơ chế ô thêm). Bản sao là một mục của danh sách đó nên lại nhân bản,
    chuyển, xoá được; vùng gốc thì chỉ ẩn/khôi phục.
  - Cả hai đi qua `site-lists/items/duplicate` (không có `item` = bản sao của vùng gốc, đặt đầu danh
    sách) và ghi nội dung đang hiển thị qua `updateLocale()` (cùng bộ lọc); bị chặn khi còn thay đổi
    chờ Lưu. **Không tải lại trang** (sẽ thoát chế độ chỉnh sửa): trình duyệt chèn bản sao DOM của
    nguồn với khoá đã chuyển sang mục mới — đúng thứ Blade sẽ render, vì nội dung nguồn là thứ vừa lưu.
  - Không nhân bản/di chuyển một phần tử HTML bất kỳ (wrapper, section, card chưa đánh dấu
    `list-item`), không ẩn theo màn hình, không cỡ chữ tự do, không inspector CSS — đó là page builder.
    Muốn card nhân bản được thì khi cắt giao diện phải bọc từng card bằng `<x-client::list-item>`.
  - Đây là **bước bắt buộc của việc cắt theme**, không phải việc làm thêm sau: mọi dãy đơn vị lặp do
    template viết (card, bước, lợi ích, cột link footer…) là `@foreach` trên `$lists->items(…)` với
    `<x-client::list-item>` là phần tử ngoài cùng. `php artisan client:editable-audit` (khối "Editor
    structure", `App\Support\EditorStructureScanner`) FAIL khi layout thiếu `core.head`/`core.editor`,
    khi dãy lặp chưa là list-item hoặc list-item nằm sâu hơn phần tử ngoài cùng. Dãy cố ý cố định ghi
    `{{-- editor:fixed-run <lý do> --}}` ngay trước `@foreach`; thiếu lý do cũng FAIL.
  - Theme cắt **trước** khi có các luật cấu trúc này giữ lỗi cũ trong
    `resources/views/client/.editor-structure-baseline.json` (`client:editable-audit --baseline`,
    `team.py` tự ghi một lần khi lấy core mới): lỗi cũ không chặn, lỗi mới vẫn chặn. Theme cắt mới
    hoặc cắt lại **không bao giờ** dùng baseline.
- **Dữ liệu lấy từ database không bao giờ là vùng sửa trực tiếp.** Sản phẩm, bài viết, danh mục,
  thương hiệu, đối tác (module Partners, theo nhóm), menu, banner, tên/logo shop in thẳng từ service
  của chúng và chỉ sửa ở màn admin. Bọc chúng trong `x-client::editable`/`editable-image` làm bản sửa
  nằm trong `site_blocks` và che bản ghi: admin và API giữ giá trị cũ, trang hiện giá trị mới. Audit
  FAIL khi key, src hay nội dung của một vùng sửa đọc thuộc tính model (`$product->name`). Khối dữ
  liệu có quản lý mà chưa có module thì báo người dùng, không giả bằng vùng sửa. Bảng chủ sở hữu và
  cổng nghiệm thu cắt theme: `connect-ecommerce-frontend/references/theme-cut-acceptance.md`.
- Màu chữ: nút màu trên toolbar (bảng màu, màu vừa dùng, chọn màu tự do, bỏ màu) ghi
  `<span style="color">`; vùng "chữ đơn" được định dạng màu hay xuống dòng (Shift+Enter) chuyển thành
  HTML. Enter trong vùng chữ đơn vẫn là "xong".
- Icon trong admin bar và toolbar phải là **SVG inline**, không dùng icon font. Storefront của một
  dự án bất kỳ có thể không nạp font nào, và glyph thiếu biến mọi nút thành ô trống.
- Trang CMS do `Page` quản lý phải render qua contract hiện có với `$page`, `$title`, `$html`,
  `$metaTitle`, `$metaDescription` và vùng nội dung `client-page-{id}`; không hard-code lại nội dung
  Page vào Blade. Page chỉ lưu HTML (`published_html`); không có CSS riêng theo trang.
- URL storefront của Page là `/chinh-sach/{slug}` cho ngôn ngữ mặc định, `/{locale}/chinh-sach/{slug}`
  cho ngôn ngữ khác (route `client.pages.show`), dùng chung một view `client.pages.show`; `/pages/{slug}`
  chỉ còn là redirect 301 thẳng tới URL đó. Không đưa slug Page ra gốc (`/{slug}` hay `/{locale}/{slug}`)
  — route bắt mọi slug sẽ nuốt `/admin`, `/api` và `/{locale}/admin`.
- Trong admin, Page hiển thị là **"Trang chính sách"** và form soạn được nội dung bằng trình soạn thảo
  chung của admin (Jodit, xem mục trình soạn thảo admin). Ô nội dung của mỗi locale chỉ được submit
  khi editor của locale đó thực sự bị sửa (`data-sync="edited"`); `PageContentService` merge các locale
  gửi lên vào bản đang lưu. Lưu tiêu đề/SEO mà không chạm editor không bao giờ đẩy HTML đã lưu qua
  editor admin — editor chuẩn hoá lại markup do inline editor lưu.
- Vùng sửa được tìm bằng id `client-page-{id}`, dự phòng bằng thuộc tính
  `data-client-editable-root` để layout theme tự đánh dấu wrapper của mình. Không tìm thấy vùng nào
  thì editor thoát im lặng và nút **Sửa trực tiếp** không hiện — không được để script ném lỗi vì sẽ
  kéo sập cả media picker.
- Cổng quyền nằm ở `User::canEditClientContent()`; cả page view lẫn `admin-bar` dùng chung hàm đó.
  Guest, customer và admin thiếu quyền không được thấy markup, asset, `data-*` hook hoặc endpoint
  editor.
- Editor **không tự lưu**. Thay đổi chờ nút **Lưu**; **Huỷ** khôi phục ảnh chụp lúc bật edit mode;
  tắt edit mode không lưu cũng không xoá, chỉ báo còn thay đổi chưa lưu. Không thêm auto-save theo
  `focusout`/`pagehide` — nó biến thao tác nhầm thành ghi vĩnh viễn không hoàn tác được.
- Trạng thái "đã sửa" phải bắt từ sự kiện soạn thảo thật (`input`/`paste`/`cut`/`drop`), tuyệt đối
  không so `innerHTML` với ảnh chụp lúc tải trang: script của theme (slider nhân bản slide,
  lazy-load đổi `src`) sẽ bị hiểu là chỉnh sửa rồi ghi markup sinh ra vào database, và lần tải sau
  lại nhân bản tiếp.
- Click phải chặn ở **pha capture** trên `document` kèm `stopImmediatePropagation`. Theme hay gắn
  `onclick` lightbox/tab/modal vào chính phần tử đang sửa; nghe ở pha bubble sẽ để modal của theme
  bật đè lên media picker.
- Lưu trực tiếp phải gọi `admin.pages.inline-update` bằng session + CSRF, đi qua
  `InlinePageUpdateRequest`, `PageContentService::updateLocale()`, `PageHtmlSanitizer`, revision và
  activity log; giữ throttle `admin-page-inline`. Không ghi database từ JavaScript và không tạo
  public write API.
- Payload lưu chỉ gồm `content_locale` và `published_html`. Save phải bảo toàn các locale khác và
  metadata của Page.
- Block động (dữ liệu từ DB) render trên trang CMS phải server-render kèm `contenteditable="false"`
  ở wrapper để nằm ngoài vùng sửa.
- Ảnh trong Page phải chọn và thay được từ thư viện Media ngay trong inline editor. Chỉ admin có
  thêm `media.view` mới thấy công cụ ảnh; upload dùng route admin hiện có, giữ popup mở sau upload
  và giữ phân trang thư viện.
- Theme CSS không được che admin bar/editor; giữ namespace selector và z-index hiện có. Client asset
  vẫn tách khỏi admin asset. Nút ẩn theo điều kiện phải có `#client-admin-bar [hidden] { display: none !important }` — quy tắc `display` kèm `!important` của tác giả thắng `[hidden]` mặc định của
  trình duyệt, thiếu dòng này thì nút Lưu/Huỷ hiện thường trực.
- Với product/category/post hoặc tài nguyên không do `Page` quản lý, không lưu qua endpoint của
  Page. Dùng đúng editor/domain route của tài nguyên; chỉ thêm inline save khi backend đã có
  validation, authorization, revision/audit tương ứng.

#### Vùng nội dung tĩnh của template (`site_blocks`)

Ngoài CMS Page, template storefront có thể đánh dấu từng vùng tĩnh cho sửa tại chỗ:

```blade
<x-client::editable key="catalog.category.empty" tag="p">Danh mục này chưa có sản phẩm nào.</x-client::editable>
<x-client::editable-image key="home.hero.image" src="{{ asset('client-assets/img/hero.jpg') }}" alt="…" />
```

- **Slot trong Blade là giá trị mặc định.** Chỉ có dòng trong `site_blocks` sau khi admin sửa vùng
  đó, nên cài mới render đúng thiết kế mà không cần seed gì.
- Key dạng `<trang>.<khu vực>.<ô>`, chữ thường, phân cách bằng dấu chấm. **Đổi key là mất nội dung
  đã lưu** — vùng lặng lẽ quay về mặc định.
- **Khối lấy từ database không được đánh dấu**: thẻ sản phẩm, bài viết, tên danh mục, menu, settings.
  Chúng có màn admin riêng; hai chỗ sửa cùng một trường chính là lỗi mà cơ chế này sinh ra để tránh.
- Chuỗi rỗng là giá trị thật (ẩn vùng), khác hẳn "chưa từng sửa". `SiteContentService::value()` trả
  `null` cho cả hai nên phải hỏi `isCleared()`. Đọc/ghi locale phải qua cột thô
  (`SiteBlock::rawTranslations()`), vì `getTranslations()` của spatie lọc mất chuỗi rỗng còn
  `setTranslations()` không xoá được locale.
- Vùng mới sửa ở một ngôn ngữ: ngôn ngữ khác hiện bản sửa đó (mặc định, cho theme viết một ngôn
  ngữ) hay chữ template của chính nó (`INLINE_EDIT_FALLBACK=false`, cho theme đã dịch sẵn từng ngôn
  ngữ — khách `/ja` không được đọc chữ tiếng Việt vừa sửa). Đặt theo theme, trong `.env` của website.
- Editor là JavaScript viết trong Blade: `EditorScriptSyntaxTest` chạy `node --check` mọi script
  inline của trang đang sửa. Vùng chữ thuần lưu `textContent`; vùng định dạng lưu qua
  `cleanEditedHtml()` (bỏ `<span style>` trình duyệt tự chèn, bỏ đậm/nghiêng/gạch chân thành class
  `client-weight-normal`/`client-style-normal`/`client-decoration-none`, CSS ở `core.head`). Panel
  mới của editor phải thêm id vào `EDITOR_CHROME` và quy tắc chống in hoa trong `admin-bar`.
- `ConvertEmptyStringsToNull` biến `""` thành `null` trước validation; FormRequest phải hoàn nguyên.
- Lưu qua `admin.site-blocks.update`, khôi phục qua `admin.site-blocks.restore` — cùng quyền
  `pages.update` và throttle `admin-page-inline` với inline page. Ảnh cần thêm `media.view`.
- **Ảnh nền là loại `background`**, viết trên chính phần tử của theme bằng
  `@clientBackground('<key>', "<nguyên chuỗi style gốc>")` (lazy: `['lazy' => 'data-bg', 'src' => …]`).
  `App\Support\ClientBackground` chỉ thay phần bên trong **đúng một** `url()` ảnh nội dung; gradient,
  position, size, repeat và các khai báo khác giữ nguyên từng ký tự. 0 hoặc ≥2 `url()` thì ném lỗi
  khi render — ca đó sửa tay. Không có thao tác ẩn ảnh nền.
- **`<picture>`/srcset/lazy** đi qua `editable-image` (`srcset`, `sizes`, `:sources`, `picture-class`,
  `lazy`, `placeholder`). Chưa thay hoặc đã khôi phục: in đúng markup gốc của theme. Đã thay: mọi
  `srcset` và `<source>` trỏ về ảnh mới, không xoá phần tử nào.
- **Quy tắc URL ảnh** (cả `image` và `background`), kiểm ở `SiteBlockUpdateRequest` và kiểm lại khi
  render (`ClientBackground::isSafeUrl`): chuỗi rỗng, path bắt đầu bằng một `/`, hoặc `http(s)://host/…`;
  không khoảng trắng, nháy, ngoặc, `\`, `<`, `>`, `;`, ký tự điều khiển. `javascript:`, `data:`,
  `//host` bị 422. Lưu path tương đối qua `MediaUrl`. Ghi, khôi phục và khôi phục revision của
  `image`/`background` đều cần `media.view`.
- **Sửa lồng nhau:** phần tử có ảnh nền mang `data-client-background`, **không** mang
  `data-block-key`, nên không bao giờ thành một vùng nuốt click của con. Ảnh nền chỉ mở từ nút
  "Đổi ảnh nền" (editor chrome, gắn vào `<body>`); chữ và ảnh bên trong vẫn là vùng độc lập, và
  scanner vẫn quét toàn bộ phần tử con.
- Revision gộp trong 10 phút; lần lưu đầu của block mới không có gì để snapshot.
- **Không viết literal `<x-client::…>` trong comment Blade.** Blade biên dịch cả trong comment JS/CSS
  và để hở `if` không đóng — trang vẫn render nhưng rò output buffer.

#### Hai thư viện được phép cho editor

Người dùng đã yêu cầu rõ ràng, nên hai thư viện sau **được phép** — đây là ngoại lệ có giới hạn của
quy tắc "không thêm thư viện editor mới", không phải giấy phép mở:

```text
public/client-assets/vendor/sortable.min.js   kéo-thả trong panel Mục lục
public/client-assets/vendor/alpine.min.js     panel cài đặt section và panel lịch sử
```

- File tĩnh, vendor sẵn. **Không npm, không Vite, không bước build** — như mọi asset client khác.
- Nạp từ trong cổng quyền của `admin-bar`, nên khách và customer không tải thêm một byte nào. Thẻ
  `<script>` nằm trong `admin-bar` chứ không trong layout: bar là partial duy nhất mà layout thay thế
  buộc phải include, nên editor không thể lên mà thiếu thư viện.
- Alpine **chỉ** cho panel editor. Phần còn lại của editor là vanilla và giữ nguyên như vậy; đừng
  viết lại `admin-bar`, `inline-blocks` hay `inline-outline` bằng Alpine.
- `admin-bar` vẫn là **chủ sở hữu duy nhất** của edit mode. Panel mới đăng ký vào luồng Lưu/Huỷ của
  bar qua `clientBlocks.registerSaver({ pending, save, revert })` + `refreshPending()`, không tự gọi
  server. Tuyệt đối không gọi `markDirty()` để làm nút Lưu hiện lên: cờ đó cũng kích hoạt
  `savePageBody()`, nên trên trang CMS nó sẽ ghi lại toàn bộ `published_html` chỉ vì ai đó đổi màu
  nền một section.
- Bẫy thứ tự khởi động, gặp mọi lần: bản CDN của Alpine tự `start()` trong một microtask ngay khi
  script chạy, mà `defer` đặt việc đó **trước** `DOMContentLoaded` — nơi bar publish
  `window.clientBlocks`. Nên `alpine:init` và `init()` của mọi component chạy trước. Đăng ký component
  ngay (`Alpine.data` phải gọi trước `start`), còn phần phụ thuộc bar thì chờ bar xuất hiện.
- Panel không được dùng `x-show` để ẩn/hiện: `x-show` ghi `display: none` inline, và inline thua rule
  `!important` của tác giả — mà `display` của editor buộc phải `!important`, nếu không CSS theme thắng
  và panel ship ra không có style. Dùng class `.is-open` như panel Mục lục.
- Panel phải là **con trực tiếp của `<body>`** và không đặt directive nào lên `[data-block-key]`.

#### Hiển thị section (`site_sections`)

Ngoài nội dung, template có thể cho editor đổi **cách một section render** — nhưng chỉ trong số các
hình dạng section đã biết cách render sẵn:

```text
site_sections            key = "<list key>.<tên section>", variant, settings (token), is_visible
site_section_revisions   snapshot, coalesce 10 phút như site_blocks
```

- `App\Support\SectionRegistry` là **nguồn sự thật duy nhất** cho variant/settings mà mỗi section cho
  phép, và cho map token → class. Thêm quyền chọn = sửa registry, không sửa Blade hay DB.
- **DB lưu token, không bao giờ lưu tên class.** `background: "primary"`, không phải
  `"client-section--bg-primary"`. Đổi màu của một token sau này là sửa một dòng CSS, không phải UPDATE
  mọi dòng đã chọn nó.
- **Không có default.** Section chưa ai sửa render y hệt template và không mang class thêm nào —
  đó là điều giữ cho cài mới hiển thị đúng thiết kế. Kéo theo: không có variant nghĩa là "như
  template", vì `null` đã mang nghĩa đó.
- Màu tự chọn không thể là token: nó đi bằng CSS custom property inline, giá trị validate
  `/^#[0-9a-f]{6}$/i` ở **cả** FormRequest **và** service.
- Registry được kiểm cả lượt **đọc** lẫn lượt **ghi**: token đã rút khỏi registry phải ngừng tác dụng
  ngay, kể cả khi nó nổi lên từ một revision cũ.
- Ghi qua `admin.site-sections.update` (PATCH) và `admin.site-sections.restore` (DELETE) — cùng quyền
  `pages.update` và throttle `admin-page-inline` với inline page. `SectionRegistryDriftTest` fail nếu
  registry khai báo section không có Blade, hoặc token có class mà `storefront.css` chưa định nghĩa.

#### Lịch sử và rollback

`site_block_revisions` và `site_section_revisions` đọc qua `admin.site-blocks.revisions.*` và
`admin.site-sections.revisions.*`, cùng cổng quyền với đường ghi — lịch sử nêu tên admin đã sửa site,
không phải thứ khách được xem.

- **Rollback cưỡng bức snapshot của chính giá trị nó thay thế**, bỏ qua coalescing 10 phút. Coalescing
  đúng cho việc gõ chữ; rollback mà bỏ qua checkpoint sẽ là thao tác duy nhất trong editor không hoàn
  tác được. Đây cũng là lý do duy nhất khiến rollback được phép gọi server ngay.
- **Đích lấy từ revision, không bao giờ từ request.** Nhận thêm key cạnh revision id là mở đường cho
  request giả ghi snapshot của vùng A lên vùng B mà audit log không thấy gì.
- Rollback một vùng chỉ ghi locale đang xem. Revision không có locale đó thì **từ chối**, không ghi
  chuỗi rỗng — chuỗi rỗng nghĩa là "cố ý ẩn", một chỉ thị khác hẳn.

#### Xem trước theo khổ máy

Iframe cùng URL với cờ `?client_chrome=off`, không dùng `transform`/`zoom`: media query tính theo
viewport, nên trang bị scale vẫn báo chiều rộng desktop và mọi breakpoint đứng nguyên chỗ cũ.

- Cờ đó **chỉ được phép bỏ** editor, không bao giờ bật. `App\Support\ClientEditorChrome::editorActive()`
  giữ quyền ở vế trái phép AND, nên không giá trị nào của cờ đưa editor tới người không có quyền.
- Mọi component và partial storefront hỏi đúng hàm đó, không chỉ layout: một class chỉ emit cho editor
  sẽ làm khung xem trước khác với thứ khách thấy — đúng điều duy nhất mà một bản xem trước không được
  phép làm.
- Khung xem trước là read-only. Sửa trong đó là đặt hai chủ sở hữu edit mode trên hai document.

Khi thay đổi public API:

- Cập nhật `routes/api.php`, validation, service, resource và test.
- Cập nhật `public/docs/openapi.json`.
- Kiểm tra `/api/docs`.
- Giữ tương thích nếu không có yêu cầu breaking change.
- Không để frontend gửi field mà backend bỏ qua hoặc cấm.

Storefront phải coi server là nguồn sự thật cho giá, tồn kho, voucher, payment và order state.

## 10. Definition of Done

Một thay đổi chỉ hoàn tất khi:

- Đáp ứng đúng yêu cầu, không mở rộng ngoài scope.
- Giữ các bất biến ở mục 5.
- Có validation, authorization và output filtering phù hợp.
- Có test mới hoặc giải thích cụ thể vì sao không cần.
- Test mục tiêu đạt; regression được chạy hoặc nêu rõ chưa chạy.
- Không có syntax error, debug code, secret hoặc TODO tạm.
- Migration/queue/scheduler/deployment impact được báo rõ.
- OpenAPI được đồng bộ nếu public API thay đổi.
- Layout client mới vẫn giữ admin bar và luồng inline editing cho CMS Page; có test guest/customer
  không thấy editor, admin đúng quyền lưu được và nội dung nguy hiểm bị lọc.
- Media mới lưu path tương đối, không có URL tuyệt đối trỏ về host của chính site trong database.

## 11. Cách báo cáo kết quả

Trả lời ngắn gọn theo thứ tự:

1. Kết quả đã hoàn thành.
2. File chính đã thay đổi.
3. Test/command đã chạy và kết quả.
4. Rủi ro, migration hoặc việc còn lại.

Không tuyên bố “đã hoàn tất” khi test bắt buộc chưa chạy hoặc đang lỗi liên quan.

Tài liệu này là hợp đồng làm việc dành cho mọi AI coding agent thao tác trong repository này.

Đọc tài liệu trước khi phân tích, sửa code, migration, test, API, admin UI hoặc deployment.
