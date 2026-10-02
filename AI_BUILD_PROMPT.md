

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
    references/static-first-workflow.md   xác định đang ở stage nào
    references/laravel-blade-cutting.md   khi có thư mục theme/
    references/inline-admin-editing.md    layout client, CMS Page, inline editor
    references/editable-markup-standard.md  quy chuẩn đánh dấu vùng sửa khi cắt theme
    references/api-contracts.md           trước khi nối dữ liệu thật
```

Định tuyến theo loại việc:

| Việc đang làm                                                                                                                                                                                                                              | Skill                          | Reference bắt buộc đọc thêm                                                                                                                                                                                                  |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Admin Blade, API public/admin, auth, role/permission, feature, đa ngôn ngữ, catalog/variant, checkout, promotion, voucher, đơn hàng, tồn kho, review, payment/VNPAY, shipping, webhook, notification, migration, seeder, command, test | `maintain-ecommerce-core`    | `architecture.md`; thêm `domain-invariants.md` khi chạm auth/tiền/kho/đơn/payment/webhook/review/secret; thêm `verification.md` khi chọn test hoặc kiểm tra deploy                                                 |
| Prototype giao diện, cắt`theme/` vào Blade, thay layout client, render CMS Page, inline editing, asset storefront, route/controller storefront, nối `/api/public`                                                                     | `connect-ecommerce-frontend` | `static-first-workflow.md`; thêm `laravel-blade-cutting.md` khi có `theme/`; thêm `inline-admin-editing.md` khi tạo/thay layout client hoặc render Page; **bắt buộc** `editable-markup-standard.md` khi cắt `theme/` hoặc đánh dấu vùng sửa; thêm `api-contracts.md` trước khi nối dữ liệu thật |

Lưu ý:

- `agents/openai.yaml` trong mỗi skill chỉ là metadata hiển thị cho agent host, không phải chỉ dẫn
  làm việc. Không cần đọc để thực thi.
- Skill và tài liệu là văn bản; khi lệch với code thì áp dụng thứ tự ưu tiên ở mục 2. Đã gặp trường
  hợp skill mô tả tính năng đã bị xóa khỏi code — luôn đối chiếu trước khi tin.
- Việc không khớp bảng trên: vẫn đọc `maintain-ecommerce-core` làm mặc định.

## 1. Nhận diện hệ thống

Đây là Laravel 12 Ecommerce Admin Core dùng để triển khai từng website bán hàng độc lập:

```text
1 khách hàng = 1 Laravel project = 1 database riêng
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
- Hạn mức gói (`App\Services\Packages\PackageLimits`): `data_unit` = sản phẩm + bài viết, `banner`,
  `email_monthly` (chỉ đếm khi gửi qua SMTP dùng chung). Chặn ở model event `creating` nên form admin,
  import CSV và import WordPress đều bị áp; áp cả cho superadmin. Hạn mức **chỉ** lấy từ tool nội bộ, core không
  có chỗ sửa: nâng hạn mức = Sales nâng gói/ký lại hợp đồng → cập nhật tool → `package:sync`.
  Không có snapshot gói thì không giới hạn.
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
  chủ shop khỏi chính admin của họ.
- Khi vô hiệu hóa user hoặc thay đổi quyền nhạy cảm, xem xét thu hồi token/session qua
  `UserAccessService`.

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
- Public API resolve locale bằng middleware `apiLocale`.
- Field dịch dùng pattern/model/service hiện có; không tự tạo cột ngôn ngữ rời rạc.
- Slug đa ngôn ngữ đi qua `LocalizedSlugService`/`HasLocalizedSlugs`.
- API success metadata phải giữ `meta.locale`.

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
- Không trả `cost_price`, secret, token, SMTP password hoặc reviewer email qua public API.
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
```

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

- Layout chung phải include `client.partials.admin-bar` đúng một lần, gần cuối `<body>`, rồi
  `client.partials.inline-blocks` ngay sau. Không xóa, sao chép hoặc nhúng lại toàn bộ mã editor vào
  từng page.
- **Edit mode có đúng một chủ sở hữu: `admin-bar`.** Nó quyết định vùng nào đang mở, giữ các thay đổi
  đang chờ, và công bố `window.clientBlocks` (`isEditing`, `activeRegion`, `markDirty`, `status`,
  `on('open'|'close'|'mode')`, `urls`, `csrf`, `locale`). `inline-blocks` chỉ là thanh công cụ nhìn
  vào trạng thái đó. Hai chủ sở hữu trên cùng `[data-block-key]` sẽ cùng tưởng mình biết vùng nào
  đang mở, và bên đoán sai ghi đè DOM của bên kia.
- Lệnh định dạng và "ẩn vùng" đi theo luồng chờ **Lưu** như một lần gõ phím. Chỉ ba thao tác cấu
  trúc gọi server ngay — khôi phục nội dung gốc, thêm ô, xoá ô — vì không cái nào diễn đạt được
  bằng "HTML mà vùng này sẽ có sau khi Lưu".
- Icon trong admin bar và toolbar phải là **SVG inline**, không dùng icon font. Storefront của một
  dự án bất kỳ có thể không nạp font nào, và glyph thiếu biến mọi nút thành ô trống.
- Trang CMS do `Page` quản lý phải render qua contract hiện có với `$page`, `$title`, `$html`,
  `$metaTitle`, `$metaDescription` và vùng nội dung `client-page-{id}`; không hard-code lại nội dung
  Page vào Blade. Page chỉ lưu HTML (`published_html`); không có CSS riêng theo trang.
- URL storefront của Page là `/{locale}/chinh-sach/{slug}` (route `client.pages.show`), dùng chung
  một view `client.pages.show`; `/{locale}/pages/{slug}` chỉ còn là redirect 301. Không đưa slug Page
  ra gốc `/{locale}/{slug}` — route bắt mọi slug sẽ nuốt `/{locale}/admin`.
- Trong admin, Page hiển thị là **"Trang chính sách"** và form soạn được nội dung bằng Quill (đã có
  sẵn trong admin). Ô nội dung của mỗi locale chỉ được submit khi editor của locale đó thực sự bị sửa;
  `PageContentService` merge các locale gửi lên vào bản đang lưu. Lưu tiêu đề/SEO mà không chạm
  editor không bao giờ đẩy HTML đã lưu qua Quill — Quill làm phẳng markup do inline editor lưu.
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
- `ConvertEmptyStringsToNull` biến `""` thành `null` trước validation; FormRequest phải hoàn nguyên.
- Lưu qua `admin.site-blocks.update`, khôi phục qua `admin.site-blocks.restore` — cùng quyền
  `pages.update` và throttle `admin-page-inline` với inline page. Ảnh cần thêm `media.view`.
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
