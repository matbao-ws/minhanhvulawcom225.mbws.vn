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
`AI_BUILD_PROMPT.md` và các skill.

## Vài điều dễ sai nhất

- Không multi-tenant. Không thêm React/Vue/Inertia/Livewire/Vite khi chưa được yêu cầu rõ.
- **Không có page builder.** GrapesJS và `PageBuilderService` đã bị gỡ; inline editing dùng
  `contenteditable` thuần. Đừng dựng lại.
- Media của site lưu **path tương đối** qua `App\Support\MediaUrl`; không ghi URL tuyệt đối vào DB.
- Truy vấn đọc phục vụ cả API và Blade phải nằm trong service dùng chung
  (`Catalog\ProductQueryService`), không viết inline trong controller.
- Tạo đơn đi qua `Orders\OrderCreationService`; không tự tính lại tiền ở controller.
- Code và test có thẩm quyền cao hơn tài liệu. Khi lệch, tin code và sửa tài liệu.

## Lệnh kiểm chứng

```bash
php artisan test --compact tests/Feature/RelevantTest.php   # test mục tiêu trước
php artisan test --compact                                  # rồi mới regression
php -l path/to/changed.php
```

Không chạy `migrate:fresh`, `migrate:reset`, `db:wipe` hoặc seeder có truncate trên database không
xác định. Không in giá trị secret trong `.env`.
