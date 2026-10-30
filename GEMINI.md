# Laravel Ecommerce Core

Đọc `AGENTS.md` ở thư mục gốc trước khi làm bất cứ gì. File đó trỏ tới hợp đồng làm việc
`AI_BUILD_PROMPT.md` và các skill trong `.agents/skills/`, và liệt kê các lệnh bắt buộc phải chạy
(trong đó có `php artisan client:editable-audit` khi cắt theme).

Dự án khách (repo có remote `core`) không sửa, thêm hay xoá file core (`CoreManifest::CORE`): git pre-commit
chặn và gateway khôi phục file sửa lén. Thay đổi riêng vào theme hoặc `extensions/` (xem `AGENTS.md`).
