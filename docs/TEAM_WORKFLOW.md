# Quy trình chuẩn cho team dev: làm website cho khách

Tài liệu này dành cho dev **làm dự án khách**: tạo dự án từ core, nối giao diện, deploy lên hosting
khách. Việc phát triển và phát hành **core** có quy trình riêng trong [GIT_WORKFLOW.md](GIT_WORKFLOW.md).

Lệnh gõ trong PowerShell hoặc terminal của VS Code.

---

## 0. Hiểu mô hình trong 1 phút

```
Repo core (M-t-Bao-WS/matbaows-core-admin)      ← trưởng nhóm sửa và phát hành
   │  team dev: chỉ ĐỌC (clone, lấy bản mới)
   │
   ├─ new-project.py ──► Repo web-khachA   ← dev làm giao diện ở đây ──deploy.py──► hosting khách A
   ├─ new-project.py ──► Repo web-khachB   ← ...                                   ──► hosting khách B
   └─ ...
```

- **Core** là phần chung của mọi website: admin, giỏ hàng, đơn hàng, thanh toán, API.
- **Theme** là giao diện riêng của từng khách. Đây là phần việc của team dev.
- Website khách **tự nhận bản cập nhật core** qua hệ thống cập nhật. Phần theme của dev không bị
  ghi đè.

### Quy tắc vàng

1. **Không sửa file core trong repo khách.** Chỉ làm trong thư mục theme (mục 3). Sửa file core
   khiến website đó **không nhận được bản cập nhật core nữa**, mất cả bản vá lỗi và bảo mật.
2. **Mỗi website khách = một repo riêng** trong org `M-t-Bao-WS`, tạo bằng `new-project.py`. Không
   copy thư mục bằng tay, không fork.
3. **Không commit và không gửi secret**: `.env`, mật khẩu database, mật khẩu FTP, token. Không dán
   chúng vào chat, kể cả chat với AI.

---

## 1. Thiết lập lần đầu (mỗi máy làm 1 lần)

### 1.1. Cài công cụ

- **Laragon** (có sẵn PHP 8.2+, MySQL, Composer). Dự án đặt trong `F:\laragon\www\`.
- **Git**: <https://git-scm.com>
- **GitHub CLI** (để tạo repo tự động):
  ```powershell
  winget install GitHub.cli
  ```
  Cài xong thì **mở terminal mới**.

### 1.2. Đăng nhập GitHub bằng tài khoản của chính bạn

```powershell
gh auth login
# GitHub.com → HTTPS → Yes (authenticate Git) → Login with a web browser
git config --global user.name  "Tên của bạn"
git config --global user.email "email-github-cua-ban@example.com"
git config --global fetch.prune true
```

Trưởng nhóm phải mời tài khoản của bạn vào org `M-t-Bao-WS`, team `dev`. Kiểm tra lời mời trong
email hoặc tại <https://github.com/orgs/M-t-Bao-WS/invitation>.

### 1.3. Clone core

```powershell
cd F:\laragon\www
git clone https://github.com/M-t-Bao-WS/matbaows-core-admin.git
```

Bạn chỉ có quyền **đọc** repo này: clone và `git pull` được, push thì GitHub từ chối. Đó là thiết
lập có chủ đích, không phải lỗi.

---

## 2. Nhận dự án khách mới

```powershell
cd F:\laragon\www\matbaows-core-admin
git pull                       # lấy core mới nhất
python new-project.py
```

Script hỏi lần lượt:

| Câu hỏi | Trả lời |
|---|---|
| Tên dự án | `web-<tênkhách>`, chữ thường, không dấu, nối bằng gạch ngang. Vd: `web-spico` |
| Tạo repo GitHub ở | Enter (org `M-t-Bao-WS`) |
| Database local | Enter (MySQL của Laragon) |
| Tạo luôn? | Enter |

Sau đó script hỏi **tài khoản admin** cho website local. Đây là tài khoản để bạn đăng nhập thử,
đặt mật khẩu gì cũng được.

Kết quả:

- Thư mục `F:\laragon\www\web-spico`, đã cài xong và chạy được.
- Repo private `M-t-Bao-WS/web-spico` trên GitHub, đã có code.
- Web local: `http://web-spico.test` (Laragon → Menu → **Reload** để nhận domain mới).
- Admin: `http://web-spico.test/vi/admin`.

Mở thư mục **dự án khách** trong VS Code (`F:\laragon\www\web-spico`), không mở repo core.

---

## 3. Nối giao diện: được sửa ở đâu

### ✅ Được sửa (thuộc về website khách)

| Thư mục / file | Dùng cho |
|---|---|
| `resources/views/client/` | Blade của giao diện: layout, trang chủ, sản phẩm, bài viết... |
| `public/client-assets/` | CSS, JS, ảnh, font của giao diện |
| `routes/theme.php` | Route riêng của giao diện: landing page, trang đặc biệt |
| `theme/` | Dành riêng cho website, không bị bản cập nhật core đụng tới |

### ❌ Không sửa (thuộc về core)

Toàn bộ phần còn lại, ví dụ: `app/`, `resources/views/admin/`, `routes/web.php`, `routes/api.php`,
`database/migrations/`, `config/`, `composer.json`, `public/admin-assets/`.

Cần thay đổi ở đó thì xem mục 3.2.

### 3.1. Cách làm giao diện

- Đọc hướng dẫn nối giao diện: `.agents/skills/connect-ecommerce-frontend/SKILL.md`. Nếu dùng AI
  (Claude Code...), AI tự tìm tới file này qua `CLAUDE.md`.
- Lấy dữ liệu (sản phẩm, danh mục, bài viết, menu, banner) qua các biến và service có sẵn hoặc
  `/api/public`. **Không** viết truy vấn database mới trong view.
- Chữ trên trang để chủ shop tự sửa được (inline editing), không gõ cứng vào Blade.

### 3.2. Khách yêu cầu thứ mà theme không làm được

| Yêu cầu | Làm gì |
|---|---|
| Chỉ khác giao diện / thêm trang | Tự làm trong theme |
| Tính năng mới mà khách khác cũng có thể cần (thanh toán, vận chuyển, khuyến mãi...) | **Báo trưởng nhóm**. Tính năng được làm trong core, có công tắc bật cho từng khách |
| Gặp lỗi của core (admin, giỏ hàng, đơn hàng...) | Mở **Issue** trên repo core: mô tả lỗi, các bước tái hiện, ảnh chụp. **Không** tự sửa trong repo khách |

---

## 4. Git trong repo khách

Repo khách có nhánh chính là `main`. Mỗi việc làm trên một nhánh riêng:

```powershell
git switch main
git pull
git switch -c feat/trang-chu          # đặt tên theo việc: feat/..., fix/...
# ... code, xem thử trên http://web-spico.test ...
git add -A
git commit -m "feat: trang chủ theo thiết kế"
git push -u origin feat/trang-chu
```

Lên GitHub mở **Pull Request** `feat/trang-chu` → `main`:

- CI tự chạy test. Phải có dấu ✅ mới merge.
- Dự án đông người: nhờ một người khác xem PR trước khi merge.
- Merge xong:
  ```powershell
  git switch main
  git pull
  git branch -d feat/trang-chu
  ```

Kiểm tra nhanh trước khi commit: `git status` **không được** có file nào ngoài các thư mục ở mục 3,
trừ khi đó là lần lấy bản core mới (mục 6).

---

## 5. Deploy lên hosting khách

Chạy **trong thư mục dự án khách**:

```powershell
cd F:\laragon\www\web-spico
git switch main
git pull
python deploy.py
```

### Lần đầu với một website

`deploy.py` hỏi domain, thông tin FTP, database của hosting (dán nguyên khối từ Plesk được) và email
nhận thông báo. Thông tin được lưu trên máy bạn để các lần sau không phải hỏi lại. `.env` production
trên hosting được tạo tự động: không cần, và không nên, sửa `.env` bằng tay.

Script cũng xin **GATEWAY_INTERNAL_KEY** để kết nối website với hệ thống Mắt Bão (gửi mail, AI, cập
nhật core). Key này chỉ trưởng nhóm giữ: **lần deploy đầu tiên của mỗi website, nhờ trưởng nhóm
chạy** hoặc ngồi cùng. Không có kết nối này thì website không gửi được email.

Cuối quá trình deploy, script tự kiểm tra kết nối: dòng `Kết nối gateway OK` là đạt.

### Các lần sau

`python deploy.py`, chọn domain đã lưu và Enter.

### ⚠️ Trước mỗi lần deploy: core trong repo không được cũ hơn trên website

Website khách tự cập nhật core. Nếu repo của bạn vẫn giữ core cũ, deploy sẽ **chép đè core cũ lên
website** và làm hỏng nó.

1. Vào admin website khách → menu **Cập nhật phiên bản**, xem phiên bản đang chạy, vd `1.2.0`.
2. Mở file `VERSION` trong repo khách.
3. Nếu `VERSION` nhỏ hơn bản trên website, làm mục 6 trước rồi mới deploy.

---

## 6. Lấy bản core mới về repo khách

Khi trưởng nhóm báo có bản core mới, hoặc trước khi deploy (mục 5):

```powershell
cd F:\laragon\www\web-spico
git switch main
git pull
git switch -c chore/core-1.2.0
git fetch core --tags
git merge v1.2.0
```

- Vì theme và core nằm ở các thư mục khác nhau, lệnh merge thường **không có conflict**.
- Nếu có conflict ở file core: bạn đã lỡ sửa file core. **Chọn bản của core** cho file đó
  (`git checkout --theirs <file>`), rồi báo trưởng nhóm điều bạn cần.
- Cài và chạy lại:
  ```powershell
  composer install
  php artisan migrate
  php artisan optimize:clear
  ```
- Mở web local kiểm tra giao diện, rồi `git push` và mở PR vào `main` như mục 4.

---

## 7. Bàn giao cho khách: checklist

- [ ] Đúng logo, tên shop, màu sắc, favicon (admin → **Cấu hình chung**).
- [ ] Mọi trang của giao diện hiển thị được trên điện thoại.
- [ ] Đặt thử một đơn: email xác nhận tới **cả khách mua và chủ shop** (kiểm tra cả Spam).
- [ ] Form liên hệ gửi được.
- [ ] Đã tạo tài khoản admin cho khách; tài khoản thử của bạn đã xoá hoặc đổi mật khẩu.
- [ ] Trang **Cập nhật phiên bản** trong admin không có cảnh báo "file core đã bị sửa".
- [ ] Code mới nhất đã merge vào `main` trên GitHub.

---

## 8. Lỗi hay gặp

| Hiện tượng | Nguyên nhân / cách xử lý |
|---|---|
| `new-project.py`: "Chưa có GitHub CLI" | Cài `gh` (mục 1.1), mở terminal mới, `gh auth login` |
| `new-project.py`: "Tạo repo GitHub không được" | Tên repo đã có trong org, hoặc bạn chưa vào org. Dự án local vẫn được tạo; báo trưởng nhóm |
| `git push` lên repo core báo 403 | Đúng thiết lập: team dev chỉ đọc core. Làm trong repo khách |
| `http://web-xxx.test` không mở được | Laragon → Menu → Reload; kiểm tra Apache/Nginx đang chạy |
| Website khách không gửi email | Chưa kết nối gateway ở lần deploy đầu. Báo trưởng nhóm |
| Trang Cập nhật phiên bản báo "file core đã bị sửa", không cập nhật được | Có người sửa file core trong repo khách. Báo trưởng nhóm, không tự xử lý trên hosting |
| Deploy xong website lỗi 500 | Chụp màn hình + giờ xảy ra, báo trưởng nhóm. Không sửa trực tiếp trên hosting |

---

## 9. Tóm tắt một vòng

```powershell
# Dự án mới
cd F:\laragon\www\matbaows-core-admin;  git pull;  python new-project.py

# Mỗi việc
cd F:\laragon\www\web-spico
git switch main;  git pull;  git switch -c feat/ten-viec
# ... code theme ...
git add -A;  git commit -m "feat: ...";  git push -u origin feat/ten-viec
# → PR vào main, CI ✅, merge

# Deploy
git switch main;  git pull
# (VERSION cũ hơn website? → git fetch core --tags; git merge vX.Y.Z)
python deploy.py
```
