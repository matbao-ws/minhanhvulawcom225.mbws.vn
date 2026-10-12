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

## Cắt theme và đánh dấu vùng sửa

Làm theo `connect-ecommerce-frontend/SKILL.md` và `references/editable-markup-standard.md`. Tóm tắt
những điều không được bỏ qua:

- Kiểm kê **toàn bộ** theme trước khi sửa: mọi trang, partial (header, footer), chữ, ảnh, ảnh nền,
  `<picture>`, slider và dữ liệu động.
- Giữ nguyên DOM, class, responsive và JavaScript của theme.
- Nội dung đã có nguồn ở database hoặc màn hình admin khác thì không tạo nguồn sửa thứ hai.
- Đánh dấu chữ và ảnh tĩnh bằng component của core (`<x-client::editable>`,
  `<x-client::editable-image>`), không tự viết `data-block-key`.
- Chỉ dùng `data-editable-ignore="<lý do>"` cho vùng thật sự cố ý để tĩnh. Không dùng nó để né audit.

**Không được báo xong khi chưa chạy và đạt:**

```bash
php artisan client:editable-audit   # phải kết thúc bằng RESULT: PASS
php artisan test --compact
```

Báo cáo theo mục "Report" của `connect-ecommerce-frontend/SKILL.md`.

## Lệnh kiểm chứng chung

```bash
php artisan test --compact tests/Feature/RelevantTest.php   # test mục tiêu trước
php artisan test --compact                                  # rồi mới regression
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
```

Không chạy `migrate:fresh`, `migrate:reset`, `db:wipe` hoặc seeder có truncate trên database không
xác định. Không in giá trị secret trong `.env`. Code và test có thẩm quyền cao hơn tài liệu.
