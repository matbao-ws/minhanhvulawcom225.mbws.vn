# AI Operating Contract — Laravel Ecommerce Core

Tài liệu này là hợp đồng làm việc dành cho mọi AI coding agent thao tác trong repository này.
Đọc tài liệu trước khi phân tích, sửa code, migration, test, API, admin UI hoặc deployment.

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
- CMS post, banner, đa ngôn ngữ, media và notification.
- Feature flags do support cấu hình; package/subscription cũ chỉ giữ tương thích dữ liệu lịch sử.

Storefront không nằm trong core. Không thêm React, Vue, Next.js, Nuxt, Inertia, Livewire,
GraphQL hoặc một frontend framework khác nếu người dùng không yêu cầu.

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

1. Đọc `README.md`, `composer.json`, file này và skill phù hợp trong `.agents/skills`.
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
- Nội dung HTML cho phép phải qua `HtmlSanitizer`.
- Upload phải validate MIME/kích thước, dùng folder allow-list và extension do server xác định.
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

Quy trình mặc định khi làm storefront:

```text
Lập page map
  -> dựng HTML mẫu bằng mock data
  -> duyệt giao diện
  -> cắt thành page/component dùng lại
  -> duyệt cấu trúc
  -> nối API public
  -> test tích hợp
```

- Không tự nối backend khi người dùng mới yêu cầu dựng HTML mẫu.
- Không thay đổi thiết kế đã duyệt trong lúc cắt component hoặc nối API.
- Không trộn storefront vào admin views.
- Nếu storefront nằm ở project/thư mục riêng, giữ nguyên kiến trúc của project đó.
- Tách mock fixtures khỏi HTML để có thể thay bằng API adapter theo từng phần.
- Chỉ loại bỏ mock data sau khi phần API tương ứng đã được kiểm chứng.

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

## 11. Cách báo cáo kết quả

Trả lời ngắn gọn theo thứ tự:

1. Kết quả đã hoàn thành.
2. File chính đã thay đổi.
3. Test/command đã chạy và kết quả.
4. Rủi ro, migration hoặc việc còn lại.

Không tuyên bố “đã hoàn tất” khi test bắt buộc chưa chạy hoặc đang lỗi liên quan.
