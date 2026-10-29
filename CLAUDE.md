# Laravel Ecommerce Core

## Bắt buộc: đọc hợp đồng làm việc trước khi sửa bất cứ gì

`AI_BUILD_PROMPT.md` là hợp đồng làm việc của repository này. **Đọc trọn file đó trước khi phân
tích, sửa code, migration, test, API, admin UI hoặc deployment.** Nó chứa bất biến nghiệp vụ, thứ tự
nguồn sự thật, quy trình kiểm chứng và Definition of Done.

Đọc xong, mở tiếp skill tương ứng với việc đang làm theo bảng định tuyến ở đầu `AI_BUILD_PROMPT.md`:

| Việc | Skill |
|---|---|
| Backend: admin, API, auth/quyền, catalog, checkout, đơn hàng, kho, payment, shipping, migration, test | `.agents/skills/maintain-ecommerce-core/SKILL.md` |
| Storefront: cắt theme vào Blade, layout client, CMS Page, inline editing, nối `/api/public` | `.agents/skills/connect-ecommerce-frontend/SKILL.md` |

Mỗi skill có thư mục `references/` với chi tiết bắt buộc đọc thêm — bảng trong
`AI_BUILD_PROMPT.md` nói rõ trường hợp nào cần file nào.

Không suy diễn nội dung hợp đồng từ file này. Đây chỉ là con trỏ; bản đầy đủ nằm trong
`AI_BUILD_PROMPT.md` và các skill. `AGENTS.md` (Codex, Antigravity) và `GEMINI.md` là con trỏ cùng
định tuyến cho công cụ khác; sửa định tuyến thì sửa cả ba.

`.agents/skills/build-inline-storefront-editing/` là tài liệu nội bộ của tính năng đã có sẵn. Cắt theme
và đánh dấu vùng sửa dùng `connect-ecommerce-frontend`; không dựng hệ thống chỉnh sửa thứ hai.

## Vài điều dễ sai nhất

- **Dự án khách không sửa file core** (`CoreManifest::CORE`): hook Claude Code và git pre-commit chặn,
  gateway phát hiện file sửa lén. Thay đổi riêng vào theme hoặc `extensions/`; xem `AI_BUILD_PROMPT.md` mục 7.
- Không multi-tenant. Không thêm React/Vue/Inertia/Livewire/Vite khi chưa được yêu cầu rõ.
- **Không có page builder.** GrapesJS và `PageBuilderService` đã bị gỡ; sửa chữ dùng
  `contenteditable` thuần, không thư viện. Đừng dựng lại.
- SortableJS và Alpine **được phép**, chỉ cho panel của editor, vendor tĩnh trong
  `public/client-assets/vendor/`, nạp trong cổng quyền. `admin-bar` vẫn là chủ sở hữu duy nhất của
  edit mode; panel đăng ký vào luồng Lưu qua `clientBlocks.registerSaver`.
- **Cắt theme bắt đầu bằng** `connect-ecommerce-frontend/references/theme-cut-acceptance.md`: kiểm
  kê toàn bộ trang/section trước, refactor (không dán), smoke test bộ sửa, rồi giao cho subagent
  `theme-cut-reviewer` (ngữ cảnh mới, chỉ đọc) review tới khi ĐẠT, báo cáo đủ mẫu.
- **Dữ liệu từ database không bao giờ là vùng sửa trực tiếp** (sản phẩm, bài viết, danh mục, thương
  hiệu, đối tác, menu, banner, tên/logo shop). Dãy đơn vị lặp do template viết phải là
  `<x-client::list-item>` ngoài cùng của `@foreach`. `client:editable-audit` FAIL cả hai.
- Hiển thị section (variant, màu nền, căn lề, spacing) lưu **token** trong `site_sections`, không bao
  giờ lưu tên class. `App\Support\SectionRegistry` là nguồn sự thật duy nhất và map token → class.
- Media của site lưu **path tương đối** qua `App\Support\MediaUrl`; không ghi URL tuyệt đối vào DB.
- Truy vấn đọc phục vụ cả API và Blade phải nằm trong service dùng chung
  (`Catalog\ProductQueryService`), không viết inline trong controller.
- Tạo đơn đi qua `Orders\OrderCreationService`; không tự tính lại tiền ở controller.
- Hai quy tắc trên và ranh giới `app/Extension` được `tests/Feature/ArchitectureTest.php` kiểm.
  Ngoại lệ chỉ thêm sau review, kèm lý do, trong `tests/Support/Architecture/ArchitectureRules.php`.
- Code và test có thẩm quyền cao hơn tài liệu. Khi lệch, tin code và sửa tài liệu.

## Lệnh kiểm chứng

```bash
php artisan test --compact tests/Feature/RelevantTest.php   # test mục tiêu trước
php artisan test --compact                                  # rồi mới regression
vendor/bin/pint --test                                      # CI chặn nếu sai format
vendor/bin/phpstan analyse --memory-limit=1G                # CI chặn lỗi mới ngoài baseline
php artisan client:editable-audit                           # bắt buộc khi đụng resources/views/client
php -l path/to/changed.php
```

Cắt theme hay sửa view storefront: không báo xong khi `client:editable-audit` chưa `RESULT: PASS`.
Checklist nghiệm thu và mẫu báo cáo nằm ở mục Verify/Report của `connect-ecommerce-frontend/SKILL.md`.

Không chạy `migrate:fresh`, `migrate:reset`, `db:wipe` hoặc seeder có truncate trên database không
xác định. Không in giá trị secret trong `.env`.
