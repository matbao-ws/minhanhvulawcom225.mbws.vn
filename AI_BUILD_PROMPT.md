# AI Operating Contract — Laravel Ecommerce Core

Tài liệu này là hợp đồng làm việc dành cho mọi AI coding agent thao tác trong repository này.
Đọc tài liệu trước khi phân tích, sửa code, migration, test, API, admin UI hoặc deployment.

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
    references/api-contracts.md           trước khi nối dữ liệu thật
```

Định tuyến theo loại việc:

| Việc đang làm | Skill | Reference bắt buộc đọc thêm |
|---|---|---|
| Admin Blade, API public/admin, auth, role/permission, feature, đa ngôn ngữ, catalog/variant, checkout, promotion, voucher, đơn hàng, tồn kho, review, payment/VNPAY, shipping, webhook, notification, migration, seeder, command, test | `maintain-ecommerce-core` | `architecture.md`; thêm `domain-invariants.md` khi chạm auth/tiền/kho/đơn/payment/webhook/review/secret; thêm `verification.md` khi chọn test hoặc kiểm tra deploy |
| Prototype giao diện, cắt `theme/` vào Blade, thay layout client, render CMS Page, inline editing, asset storefront, route/controller storefront, nối `/api/public` | `connect-ecommerce-frontend` | `static-first-workflow.md`; thêm `laravel-blade-cutting.md` khi có `theme/`; thêm `inline-admin-editing.md` khi tạo/thay layout client hoặc render Page; thêm `api-contracts.md` trước khi nối dữ liệu thật |

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
- CMS post, CMS page (HTML thuần, không có builder kéo-thả), banner, đa ngôn ngữ, media và
  notification.
- Feature flags do support cấu hình; package/subscription cũ chỉ giữ tương thích dữ liệu lịch sử.

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
- Dùng `FeatureGate`/middleware `feature:<code>`, không hard-code package code.
- Superadmin có cơ chế bypass feature hiện hành; không thay đổi ngầm.
- Feature bị tắt phải trả đúng hành vi web/API hiện có.

Sai:

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
- Khi vô hiệu hóa user hoặc thay đổi quyền nhạy cảm, xem xét thu hồi token/session qua
  `UserAccessService`.

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
- Migration phải an toàn với dữ liệu hiện hữu và có `down()` hợp lý.
- Không thêm dependency nếu framework/core hiện có giải quyết được.
- Không đổi kiến trúc hoặc public contract ngoài phạm vi mà không báo rõ.

## 7. An toàn dữ liệu và deployment

Không chạy các lệnh sau trên database không xác định:

```text
migrate:fresh
migrate:reset
db:wipe
DemoSeeder
seeder cũ có truncate/delete hàng loạt
```

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

Cơ chế hiện hành là inline editing bằng `contenteditable` thuần của browser, không thư viện ngoài.
Khi cắt hoặc thay layout client, phải giữ nguyên cơ chế này:

- Layout chung phải include `client.partials.admin-bar` đúng một lần, gần cuối `<body>`. Không xóa,
  sao chép hoặc nhúng lại toàn bộ mã editor vào từng page.
- Trang CMS do `Page` quản lý phải render qua contract hiện có với `$page`, `$title`, `$html`,
  `$metaTitle`, `$metaDescription` và vùng nội dung `client-page-{id}`; không hard-code lại nội dung
  Page vào Blade. Page chỉ lưu HTML (`published_html`); không có CSS riêng theo trang.
- Chỉ admin đang active, có `role_id` và quyền `manage_pages` mới thấy admin bar và **Sửa trực
  tiếp**. Guest, customer và admin thiếu quyền không được thấy markup, asset hoặc endpoint editor.
- Lưu trực tiếp phải gọi `admin.pages.inline-update` bằng session + CSRF, đi qua
  `InlinePageUpdateRequest`, `PageContentService::updateLocale()`, `PageHtmlSanitizer`, revision và
  activity log; giữ throttle `admin-page-inline`. Không ghi database từ JavaScript và không tạo
  public write API.
- Payload lưu chỉ gồm `content_locale` và `published_html`. Save phải bảo toàn các locale khác và
  metadata của Page.
- Block động (dữ liệu từ DB) render trên trang CMS phải server-render kèm `contenteditable="false"`
  ở wrapper để nằm ngoài vùng sửa.
- Ảnh trong Page phải chọn và thay được từ thư viện Media ngay trong inline editor. Chỉ admin có
  thêm `manage_media` mới thấy công cụ ảnh; upload dùng route admin hiện có, giữ popup mở sau upload
  và giữ phân trang thư viện.
- Theme CSS không được che admin bar/editor; giữ namespace selector và z-index hiện có. Client asset
  vẫn tách khỏi admin asset.
- Với product/category/post hoặc tài nguyên không do `Page` quản lý, không lưu qua endpoint của
  Page. Dùng đúng editor/domain route của tài nguyên; chỉ thêm inline save khi backend đã có
  validation, authorization, revision/audit tương ứng.

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
