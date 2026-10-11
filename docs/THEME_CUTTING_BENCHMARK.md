# Benchmark cắt theme cho các AI agent

Mục đích: đo xem Claude, Codex, Antigravity (hoặc một người) cắt theme và đánh dấu vùng sửa **sai ở
đâu**, thay vì đoán xem model nào "giỏi hơn".

## Bộ đề

`tests/Fixtures/theme-benchmark/raw/` là một theme Blade nhỏ **chưa đánh dấu vùng sửa**: một layout,
`partials/header`, `partials/footer` và một trang. Nó chứa một mẫu của mỗi tình huống hay gặp:

- chữ trong `div`, `span`, `a`, `button`, trong partial;
- logo, `<picture>`, `srcset`, ảnh lazy `data-src`;
- ảnh nền một lớp, nhiều lớp, shorthand có position/size, `data-bg`, ảnh nền trong `<style>`;
- dữ liệu từ database (tên sản phẩm, menu, slider), translation có thật và `__()` giả;
- những thứ **không** phải ảnh nội dung: icon, SVG, ảnh trang trí, video/audio, font, mask/cursor, gradient,
  `data-bg` là tên class, `data-src="#popup"` của lightbox;
- `data-editable-ignore` có lý do và không lý do.

`tests/Fixtures/theme-benchmark/expected.json` là đáp án của **audit** trên bộ đề chưa cắt.
`tests/Feature/ThemeBenchmarkAuditTest.php` giữ cho scanner luôn đúng đáp án đó: bỏ sót một ca là đỏ,
báo thêm một ca không có trong đáp án (false positive) cũng đỏ.

```bash
php artisan client:editable-audit --path=tests/Fixtures/theme-benchmark/raw
```

## So sánh các AI

1. Tạo một nhánh mới từ **cùng một commit** cho mỗi công cụ, ví dụ `bench/claude`, `bench/codex`,
   `bench/antigravity`.
2. Ở mỗi nhánh, chép `tests/Fixtures/theme-benchmark/raw/` vào `resources/views/client/` (thay bộ view
   mặc định).
3. Đưa cho mỗi công cụ **cùng một prompt**, chỉ thế này:

   > Đánh dấu vùng sửa cho storefront trong `resources/views/client` theo `AGENTS.md` (hoặc `CLAUDE.md`).
   > Không đổi bố cục, class, DOM hay JavaScript của theme. Báo cáo theo mẫu Report của skill.

4. Khi công cụ báo xong, chấm bằng:

   ```bash
   php artisan client:editable-audit            # còn bao nhiêu unknown / unsupported / ignore thiếu lý do
   php artisan test --compact
   git diff --stat main -- resources/views/client
   git diff main -- resources/views/client       # có đổi class, thẻ, thứ tự phần tử, script không
   ```

5. Ghi bảng so sánh theo từng ca của `expected.json`:

   | Ca | Mong đợi | Claude | Codex | Antigravity |
   |---|---|---|---|---|
   | `hero-title-div` | bọc `<x-client::editable tag="div">`, giữ class | | | |
   | `footer-bare-ignore` | bỏ `data-editable-ignore` trống và đánh dấu đúng | | | |
   | `trans-undefined` | thay `__()` giả bằng `<x-client::editable>` | | | |
   | `bg-single` … | theo hợp đồng ảnh nền hiện hành (xem `editable-markup-standard.md`) | | | |
   | Tên sản phẩm, menu, slider | **không** đánh dấu | | | |
   | Icon, font, gradient, video | **không** đánh dấu | | | |

   Thêm hai cột tổng: số dòng DOM/class bị đổi ngoài ý muốn, và báo cáo cuối có đúng sự thật không
   (ví dụ báo "xong" khi audit còn FAIL là một lỗi quy trình, không phải lỗi markup).

Lỗi thường rơi vào bốn nhóm, nên ghi nhóm cho từng lỗi: chuyển HTML sang Blade, xác định vùng sửa, giữ
nguyên giao diện, tuân thủ quy trình kiểm chứng.

## Khi sửa scanner

Thêm một ca mới vào `raw/` (gắn `data-case="<tên>"` lên phần tử) và vào `expected.json` **trước**, rồi mới
sửa scanner cho tới khi test xanh. Ca không phải nội dung (icon, font…) thì không gắn `data-case`; test
coi mọi finding không có `data-case` là false positive.
