# Laravel Ecommerce Core — chỉ dẫn cho AI agent

File này là điểm vào cho Codex, Antigravity và các agent đọc `AGENTS.md`. Claude Code đọc `CLAUDE.md`
với cùng định tuyến; Gemini đọc `GEMINI.md`, file đó chỉ trỏ về đây. Cả ba chỉ là con trỏ: nội dung
thật nằm trong `AI_BUILD_PROMPT.md` và các skill.

## Bắt buộc: đọc hợp đồng làm việc trước khi sửa bất cứ gì

1. Đọc trọn `AI_BUILD_PROMPT.md`: bất biến nghiệp vụ, thứ tự nguồn sự thật, quy trình kiểm chứng,
   Definition of Done.
2. Mở skill theo việc đang làm, rồi đọc các file `references/` mà bảng định tuyến ở đầu
   `AI_BUILD_PROMPT.md` yêu cầu. Đường dẫn `references/` tính từ thư mục của skill.

| Việc | Skill |
|---|---|
| Backend: admin, API, auth/quyền, catalog, checkout, đơn hàng, kho, payment, shipping, migration, test | `.agents/skills/maintain-ecommerce-core/SKILL.md` |
| Storefront: cắt theme vào Blade, layout client, CMS Page, inline editing, nối `/api/public` | `.agents/skills/connect-ecommerce-frontend/SKILL.md` |

`.agents/skills/build-inline-storefront-editing/` là tài liệu nội bộ của tính năng đã có sẵn trong core.
Không dùng nó để cắt theme, và không dựng hệ thống chỉnh sửa thứ hai.

## Dự án khách không sửa file core

Trong dự án khách (repo có remote `core`), file thuộc `CoreManifest::CORE` (app/, config/, routes của core,
views admin/core…, docs, các file hướng dẫn này) **không được sửa, thêm hay xoá**. Git pre-commit
(`.githooks/pre-commit`) từ chối commit có file core, và gateway MatBao phát hiện file sửa lén rồi khôi
phục về bản gốc. Thay đổi riêng của website: giao diện vào `resources/views/client/**`,
`public/client-assets/css/**`, `routes/theme.php`; tính năng vào `extensions/<id>/`. Không vượt hook
(`--no-verify`, sửa hook). Cần đổi core thì báo trưởng nhóm. Chi tiết: `AI_BUILD_PROMPT.md` mục 7.

## Cắt theme và đánh dấu vùng sửa

Làm theo `connect-ecommerce-frontend/SKILL.md`, **bắt đầu bằng `references/theme-cut-acceptance.md`**
(thứ tự làm, bảng chủ sở hữu dữ liệu, các mẫu theme làm hỏng bộ sửa, smoke test, mẫu báo cáo) và
`references/editable-markup-standard.md`. Tóm tắt những điều không được bỏ qua:

- Kiểm kê **toàn bộ** theme trước khi sửa, ghi thành bảng: mọi trang/trạng thái → route → Blade, mọi
  section của từng trang, và khối nào thuộc module admin nào.
- Refactor, không dán: một layout, partial header/footer, component cho thứ lặp lại giữa các trang.
  Layout có `@include('core.head')` và `@include('core.editor')`.
- Giữ nguyên DOM, class, responsive và JavaScript của theme.
- **Dữ liệu từ database không bao giờ là vùng sửa trực tiếp** (sản phẩm, bài viết, danh mục, thương
  hiệu, đối tác, menu, banner, tên/logo shop): in thẳng từ service, sửa ở màn admin.
- Đánh dấu chữ và ảnh tĩnh bằng component của core (`<x-client::editable>`,
  `<x-client::editable-image>`), không tự viết `data-block-key`.
- Mọi dãy đơn vị lặp do template viết (card, bước, lợi ích, link footer) là `@foreach` trên
  `$lists->items(…)` với `<x-client::list-item>` là phần tử ngoài cùng — thiếu là không nhân bản được.
- Chỉ dùng `data-editable-ignore="<lý do>"` hay `{{-- editor:fixed-run <lý do> --}}` cho thứ thật sự
  cố ý để tĩnh. Không dùng chúng để né audit.
- Chạy smoke test bộ sửa trong trình duyệt (§5 của theme-cut-acceptance); bước nào không chạy được thì
  ghi "chưa kiểm", không ghi "đạt".

**Không được báo xong khi chưa chạy và đạt:**

```bash
php artisan client:editable-audit   # phải kết thúc bằng RESULT: PASS
php artisan test --compact
```

**Trước khi báo cáo, một agent khác review độc lập**: mở phiên mới với đúng một câu ở đầu
`references/theme-cut-review.md` (Claude Code: subagent `theme-cut-reviewer`). Agent review chỉ đọc,
trả ĐẠT/CHƯA ĐẠT kèm `file:dòng`; sửa hết lỗi chặn rồi review lại bằng phiên mới cho tới khi ĐẠT.

Báo cáo theo mẫu §8 của `references/theme-cut-acceptance.md`, đủ mọi dòng, kèm kết luận review.

## Lệnh kiểm chứng chung

```bash
php artisan test --compact tests/Feature/RelevantTest.php   # test mục tiêu trước
php artisan test --compact                                  # rồi mới regression
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
```

Không chạy `migrate:fresh`, `migrate:reset`, `db:wipe` hoặc seeder có truncate trên database không
xác định. Không in giá trị secret trong `.env`. Code và test có thẩm quyền cao hơn tài liệu.
