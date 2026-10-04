# Quy trình chuẩn cho team dev: làm website cho khách

Tài liệu này dành cho dev **làm dự án khách**: tạo dự án từ core, nối giao diện, deploy lên hosting
khách. Việc phát triển và phát hành **core** có quy trình riêng trong [GIT_WORKFLOW.md](GIT_WORKFLOW.md).

Lệnh gõ trong PowerShell hoặc terminal của VS Code.

> **Cách nhanh:** chạy `python team.py` trong repo core hoặc trong thư mục dự án khách. Tool có menu
> làm sẵn các bước ở mục 1, 2, 4, 5, 6: thiết lập máy, tạo dự án, bắt đầu việc, lưu & đẩy (chặn file
> core), lấy bản core mới, deploy (chặn khi core cũ hơn website). Các mục dưới đây giải thích tool
> đang làm gì, và là cách làm tay khi cần.

---

## 0. Hiểu mô hình trong 1 phút

```
Repo core (M-t-Bao-WS/matbaows-core-admin)      ← trưởng nhóm sửa và phát hành
   │  team dev: chỉ ĐỌC (clone, lấy bản mới)
   │
   ├─ team.py ───────► Repo web-khachA   ← dev làm giao diện ở đây ──deploy.py──► hosting khách A
   ├─ team.py ───────► Repo web-khachB   ← ...                                   ──► hosting khách B
   └─ ...
```

- **Core** là phần chung của mọi website: admin, giỏ hàng, đơn hàng, thanh toán, API.
- **Theme** là giao diện riêng của từng khách. Đây là phần việc của team dev.
- Website khách **tự nhận bản cập nhật core** qua hệ thống cập nhật. Phần theme của dev không bị
  ghi đè.

### Quy tắc vàng

1. **Không sửa file core trong repo khách.** Chỉ làm trong thư mục theme (mục 3). Sửa file core
   khiến website đó **không nhận được bản cập nhật core nữa**, mất cả bản vá lỗi và bảo mật.
2. **Mỗi website khách = một repo riêng** trong org `M-t-Bao-WS`, tạo bằng `team.py`. Không
   copy thư mục bằng tay, không fork.
3. **Không commit và không gửi secret**: `.env`, mật khẩu database, mật khẩu FTP, token. Không dán
   chúng vào chat, kể cả chat với AI.

---

## 1. Thiết lập lần đầu (mỗi máy làm 1 lần)

### 1.1. Cài công cụ

Chỉ cần cài sẵn **Git** và **Python 3**. Phần còn lại `team.py` tự lo:

```powershell
git clone https://github.com/M-t-Bao-WS/matbaows-core-admin.git
cd matbaows-core-admin
python team.py        # thiếu công cụ thì tự thiết lập máy
```

Mục **Thiết lập máy** sẽ:

| Công cụ | Tool làm gì |
|---|---|
| PHP 8.2+ | Tìm trong PATH, Laragon (mọi ổ đĩa), XAMPP, Homebrew. Không có thì đề nghị cài 1 lệnh: Windows cài **Laragon** qua `winget`, Linux cài gói `php-*` qua `apt`/`dnf`, macOS qua `brew` |
| Extension PHP | Kiểm tra `zip`, `curl`, `mbstring`, `pdo_mysql`, `pdo_sqlite`...; thiếu thì chỉ đúng file `php.ini` cần sửa |
| Composer | Không cần cài: tự tải `composer.phar` vào `~/.matbaows` (kiểm tra checksum) |
| GitHub CLI | Đề nghị cài (`winget` / `apt` / `brew`) và đăng nhập |
| MySQL | Không bắt buộc: không có MySQL đang chạy thì dự án mới dùng **SQLite** |

Laragon có nhưng gõ `php` báo không nhận lệnh: bình thường, Laragon chỉ thêm PHP vào PATH khi bật
*Menu → Tools → Path → Add Laragon to Path*. `team.py` vẫn tự tìm ra Laragon, không cần bật.

Linux Ubuntu 22.04 chỉ có PHP 8.1: thêm `sudo add-apt-repository ppa:ondrej/php` rồi chạy lại.

Cài xong thứ gì thì **mở terminal mới** rồi chạy lại `python team.py`.

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

### 1.3. Repo core

Repo đã clone ở mục 1.1. Bạn chỉ có quyền **đọc** repo này: clone và `git pull` được, push thì GitHub từ chối. Đó là thiết
lập có chủ đích, không phải lỗi.

---

## 2. Nhận dự án khách mới

```powershell
cd F:\laragon\www\matbaows-core-admin
python team.py        # chọn 1. Tạo dự án khách mới
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
| `public/client-assets/` (trừ `vendor/`) | CSS, JS, ảnh, font của giao diện |
| `routes/theme.php` | Route riêng của giao diện: landing page, trang đặc biệt |
| `theme/` | Dành riêng cho website, không bị bản cập nhật core đụng tới |

### ❌ Không sửa (thuộc về core)

Danh sách đầy đủ là hằng `CORE` trong `app/Support/CoreManifest.php`, ví dụ: `app/`,
`resources/views/admin/`, `resources/views/components/`, `routes/web.php`, `routes/api.php`,
`database/migrations/`, `config/`, `lang/`, `composer.json`, `public/admin-assets/`,
`public/client-assets/vendor/`. `team.py` đọc đúng danh sách này khi kiểm tra trước lúc lưu.

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

Website trên máy chủ Plesk 252 **không cần tạo cron riêng**: cron chung của máy chủ (phụ lục A) tự
nhận website mới trong vòng 10 phút. Website ở hosting khác thì phải có cron chạy
`php artisan schedule:run` mỗi phút, nếu không, bấm "Cập nhật" trong admin sẽ không chạy.

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
| Tạo dự án: "Chưa có GitHub CLI" | `python team.py` → Thiết lập máy (cài và đăng nhập `gh`) |
| Tạo dự án: "Tạo repo GitHub không được" | Tên repo đã có trong org, hoặc bạn chưa vào org. Dự án local vẫn được tạo; báo trưởng nhóm |
| `git push` lên repo core báo 403 | Đúng thiết lập: team dev chỉ đọc core. Làm trong repo khách |
| `http://web-xxx.test` không mở được | Laragon → Menu → Reload; kiểm tra Apache/Nginx đang chạy |
| Website khách không gửi email | Chưa kết nối gateway ở lần deploy đầu. Báo trưởng nhóm |
| Trang Cập nhật phiên bản báo "file core đã bị sửa", không cập nhật được | Có người sửa file core trong repo khách. Báo trưởng nhóm, không tự xử lý trên hosting |
| Deploy xong website lỗi 500 | Chụp màn hình + giờ xảy ra, báo trưởng nhóm. Không sửa trực tiếp trên hosting |

---

## 9. Tóm tắt một vòng

Bằng tool:

```powershell
cd F:\laragon\www\matbaows-core-admin;  python team.py   # 1 Tạo dự án khách (lần đầu tự thiết lập máy)
cd F:\laragon\www\web-spico;            python team.py   # 1 Bắt đầu việc → code → 2 Lưu & đẩy
                                                          # → merge PR → 3 Dọn nhánh → 5 Deploy
```

Bằng tay:

```powershell
# Dự án mới
cd F:\laragon\www\matbaows-core-admin;  python team.py   # 1. Tạo dự án khách mới

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

---

## Phụ lục A. Cron chung trên máy chủ Plesk 252 (trưởng nhóm làm 1 lần)

Website cần cron chạy mỗi phút để: thực hiện bản cập nhật core khi admin bấm "Cập nhật", kiểm tra
bản mới, đồng bộ gói, hết hạn voucher. Thay vì tạo cron cho từng website, máy chủ có **một** cron
chung: `infra/plesk-cron.sh`.

- **Chỉ chạy website do hệ thống cập nhật quản lý**: có `artisan`, `app/Support/CoreManifest.php`
  **và** `storage/app/core/installed.json` (file mốc do `deploy.py` và mỗi lần cập nhật core ghi ra).
  Dự án cũ không có file mốc nên **không bao giờ bị đụng tới**; WordPress, Laravel khác cũng vậy.
- Bỏ qua website đã có cron riêng chạy `schedule:run` (không chạy hai lần), và website có file
  `storage/app/core/no-shared-cron` (muốn loại một website: tạo file này).
- Chạy `php artisan schedule:run` **bằng tài khoản sở hữu website**, không bằng root.
- Chống quá tải: tối đa 4 website cùng lúc, độ ưu tiên CPU/ổ đĩa thấp (truy cập web của khách được
  ưu tiên); lượt phút trước chưa xong thì phút này bỏ qua; máy chủ tải cao (load > số CPU × 2) thì
  hoãn; danh sách website quét lại 10 phút một lần.
- Chỉ ghi lỗi và các phút bị bỏ qua vào `/var/log/matbaows-cron.log`.

Cài (SSH vào máy chủ 252 bằng root):

```bash
curl -fsSL -H "Authorization: token <github token đọc repo core>"   https://raw.githubusercontent.com/M-t-Bao-WS/matbaows-core-admin/main/infra/plesk-cron.sh -o /tmp/plesk-cron.sh
# hoặc: chép file infra/plesk-cron.sh lên máy chủ bằng SFTP
sudo bash /tmp/plesk-cron.sh --install
```

Kiểm tra và gỡ:

```bash
sudo matbaows-cron --list          # mọi website core tìm thấy, và website nào được chạy (cột CHẠY?)
sudo tail -f /var/log/matbaows-cron.log
sudo matbaows-cron --uninstall
```

Cấu hình thêm (không bắt buộc) trong `/etc/matbaows-cron.conf`: `PHP=/opt/plesk/php/8.3/bin/php`
(mặc định: bản PHP 8.2+ mới nhất của Plesk), `JOBS=4`, `LOAD_MAX=16`.

Không tạo thêm cron từng website trong Plesk cho các website này: hai cron cùng chạy là thừa.
