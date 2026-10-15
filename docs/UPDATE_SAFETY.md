# Những file giữ cho việc cập nhật core hoạt động

Hệ thống cập nhật thay **file core** trên mọi website và không đụng **file của website**. Tài liệu
này nói file nào thuộc bên nào, sửa nhầm thì chuyện gì xảy ra.

- **Phần A** cho dev làm website khách (repo khách, hosting).
- **Phần B** cho người phát triển core (repo `matbaows-core-admin`).

Nguồn sự thật là `app/Support/CoreManifest.php`. Bảng dưới đây chỉ tóm tắt; hai bên lệch nhau thì tin
file đó. Hỏi nhanh một đường dẫn thuộc bên nào: `php artisan core:manifest --owner=<đường dẫn>`
(in `core`, `dependencies` hoặc `site`).

---

## A. Dev làm website khách

### Sửa file core → website đó không cập nhật được nữa

Mỗi website ghi lại checksum các file core lúc cài (`storage/app/core/installed.json`). Một file
core bị sửa trên website, hoặc trong repo khách rồi deploy lên, là **lệch (drift)**: bản cập nhật sau
**từ chối chạy** để không ghi đè mất phần đã sửa. Website kẹt ở bản cũ, không nhận bản vá lỗi, bảo mật.
Trang **Cập nhật phiên bản** trong admin hiện cảnh báo "N file core đã bị sửa trực tiếp trên website".

`python team.py` → "Lưu & đẩy" chặn sẵn việc commit file core (đọc đúng danh sách trong
`CoreManifest.php`).

| ❌ Không sửa: thuộc core                                                                                        | ✅ Được sửa: thuộc website                                      |
| ------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| `app/`, `config/`, `database/`, `lang/`                                                                    | `resources/views/client/`                                          |
| `routes/admin.php`, `routes/api.php`, `routes/client.php`, `routes/console.php`, `routes/web.php`        | `routes/theme.php`                                                 |
| `resources/views/admin/`, `api/`, `auth/`, `components/`, `core/`, `emails/`, `errors/`, `vendor/` | `public/client-assets/` (trừ `vendor/`)                         |
| `public/admin-assets/`, `public/client-assets/vendor/`, `public/docs/`, `public/index.php`                 | `theme/`, `extensions/` ([EXTENSIONS.md](EXTENSIONS.md))          |
| `artisan`, `composer.json`, `composer.lock`, `VERSION`                                                     | `.env`, `.htaccess`, `public/.htaccess`, `public/robots.txt` |
| `bootstrap/app.php`, `bootstrap/providers.php`                                                                 | `storage/` (dữ liệu của website)                                |
| `vendor/` (gói dependencies, cập nhật riêng khi `composer.lock` đổi)                                     |                                                                      |

Cần thay đổi ở cột trái: báo trưởng nhóm, hoặc mở Issue trên repo core. Thay đổi được làm trong core
(có công tắc bật/tắt nếu chỉ vài khách cần) và mọi website nhận qua bản cập nhật.

### Trên hosting: không xoá, không sửa

| Thứ                                                  | Vì sao                                                                                                                                                                                                                                                                                   |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `storage/app/core/`                                 | Mốc file core, trạng thái cập nhật, bản sao lưu để quay về bản cũ, trạng thái bật/tắt extension (`extensions.json`). Mất thì website không cập nhật được, cập nhật hỏng thì không khôi phục được. `deploy.py` không bao giờ upload thư mục này |
| `MATBAO_AI_URL`, `MATBAO_AI_TOKEN` trong `.env` | Kết nối gateway: hỏi bản mới, tải gói, gửi mail. Do`deploy.py` ghi                                                                                                                                                                                                              |
| `CORE_UPDATE_ENABLED` trong `.env`                | `false` thì website không bao giờ cập nhật                                                                                                                                                                                                                                         |
| File core sửa tay qua FTP / File Manager             | Gây lệch như trên                                                                                                                                                                                                                                                                     |

Lỡ sửa rồi: đưa thay đổi về theme (hoặc nhờ đưa vào core), khôi phục file core như cũ (deploy lại
bằng `deploy.py` từ repo khách sạch), website hết cảnh báo. Chỉ khi chắc chắn file core trên website
đúng như bản phát hành mới chạy `php artisan core:status --baseline` để ghi mốc lại.

---

## B. Người phát triển core

Các file dưới đây **là** hệ thống cập nhật. Một bản phát hành mang theo bộ cập nhật hỏng thì các
website đã cài nó **không tự cập nhật được nữa**, kể cả để nhận bản sửa: phải deploy tay từng website.
Sửa chúng phải qua mục "Quy trình an toàn" ở cuối.

### Mức nguy hiểm cao nhất

| File / thứ                                                                                                      | Vai trò                                                                       | Sửa sai thì                                                                                                                                                                                                                                                |
| ---------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `config/core_update.php` → `public_keys`                                                                    | Khoá công khai kiểm tra chữ ký mọi bản phát hành                      | Sai hoặc thiếu khoá:**mọi bản sau bị từ chối** ("chữ ký không hợp lệ"). Đổi khoá phải xoay vòng: phát hành một bản có **cả khoá cũ lẫn mới**, ký bằng khoá mới từ bản sau, bỏ khoá cũ ở một bản sau nữa |
| Secret`CORE_RELEASE_SIGNING_KEY` (GitHub)                                                                      | Khoá riêng ký bản phát hành                                              | Mất: không ký được bản mới. Lộ: kẻ khác ký được bản giả → đổi khoá ngay theo cách xoay vòng ở trên                                                                                                                                  |
| `app/Support/CoreUpdate/` (`CoreUpdater`, `CoreUpdateService`, `ReleaseVerifier`, `ReleaseDownloader`) | Tải, kiểm tra chữ ký/sha256, sao lưu, cài, khôi phục, báo về gateway | Cài hỏng nửa chừng, không quay về được, hoặc chấp nhận gói không hợp lệ                                                                                                                                                                      |
| `app/Support/CoreManifest.php`                                                                                 | Danh sách file core (`CORE`) và file website (`SITE_OWNED`)              | Bớt nhầm: file đó không được cập nhật nữa. Thêm nhầm thư mục của website: bản cập nhật**ghi đè theme của khách**. Thêm thư mục core mới thì thêm vào `CORE` kèm test trong `CoreManifestTest`                       |

### Mức nguy hiểm cao

| File / thứ                                                                                                                                                                  | Vai trò                                                        | Sửa sai thì                                                                                                                                                                                                                                                                                |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Console/Commands/CorePoll.php`, `CoreRunRequested.php`, `CoreUpdate.php`, `CoreRollback.php`, `CoreHealth.php`, `CoreStatus.php`, `CoreVerifyRelease.php` | Lệnh cron và updater gọi                                     | Bấm Cập nhật không chạy;`core:health` sai thì mọi lần cài bị coi là hỏng và khôi phục. **`core:health` phải luôn nhận `--extensions=`**: updater của bản cũ gọi `core:health` của bản mới với tuỳ chọn này                                        |
| `app/Support/Extensions/`, `app/Providers/ExtensionServiceProvider.php`, `config/extensions.php`                                                                       | Nạp extension, kiểm tra tương thích trước khi cập nhật | Extension không nạp, hoặc cập nhật không chặn được core làm hỏng extension                                                                                                                                                                                                       |
| `app/Extension/**` (API công khai cho extension)                                                                                                                          | Hợp đồng với code extension trên các website              | Đổi tên / chữ ký ở bản patch hay minor làm hỏng extension đang chạy. Chỉ đổi ở bản major,`@deprecated` trước ít nhất một bản minor ([EXTENSIONS_DESIGN.md](EXTENSIONS_DESIGN.md), mục 3)                                                                             |
| `routes/console.php`: dòng `core:poll`, `core:run-requested`                                                                                                          | Lịch chạy                                                     | Xoá: cập nhật không bao giờ được thực hiện                                                                                                                                                                                                                                         |
| `app/Services/AiGateway/GatewayClient.php`, `config/ai_gateway.php`                                                                                                      | Nói chuyện với gateway                                       | Website không hỏi được bản mới, không tải được gói                                                                                                                                                                                                                              |
| `app/Support/MigrationSafety.php` và quy tắc migration                                                                                                                   | Chặn migration phá dữ liệu                                  | Cập nhật hỏng chỉ trả**code** về bản cũ, không rollback database: migration xoá/đổi tên cột làm bản cũ chạy lỗi sau khi khôi phục. Chỉ thêm bảng, cột nullable/có default, index. Bỏ cột cũ làm ở bản **contract** sau (`@contract-migration`) |
| `config/core_update.php` → `min_from`                                                                                                                                   | Bản cũ nhất được nhảy thẳng lên bản này              | Đặt cao quá: website cũ bị chặn; thấp quá: website bỏ qua bản trung gian bắt buộc                                                                                                                                                                                                |
| `VERSION`                                                                                                                                                                  | Số phiên bản                                                 | Phải trùng tag (`v` + VERSION) và tăng dần, nếu không CI dừng hoặc website coi là "không mới hơn"                                                                                                                                                                             |
| `composer.json`, `composer.lock`                                                                                                                                         | Gói dependencies                                               | Đổi lock sinh gói`dependencies-*.zip` mới; gói thiếu extension hoặc cần PHP cao hơn thì website không đủ điều kiện cài                                                                                                                                                    |
| `.github/workflows/release.yml`                                                                                                                                            | Kiểm tra tag, đóng gói, ký, tạo Release                   | Release thiếu file hoặc ký sai: gateway không nạp, website từ chối                                                                                                                                                                                                                    |
| `app/Http/Controllers/Admin/CoreUpdateController.php`, `resources/views/admin/core-update/`                                                                              | Trang Cập nhật phiên bản                                    | Không bấm cập nhật / quay về được từ admin                                                                                                                                                                                                                                          |

### Ngoài repo core

| Thứ                                                       | Ở đâu                                                               | Vai trò                                                                                                         |
| ---------------------------------------------------------- | ---------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `CORE_RELEASES_REPO`, `CORE_RELEASES_TOKEN`            | `/opt/matbaows/env/gateway.env` trên VPS gateway                    | Gateway đọc Release từ GitHub. Đổi tên repo / tổ chức thì sửa ở đây, token phải đọc được repo |
| Webhook "Releases" của repo core                          | GitHub → Settings → Webhooks                                         | Báo gateway ngay khi có bản mới (không có thì gateway tự hỏi mỗi giờ)                                 |
| `deploy.py` (bước ghi mốc `core:status --baseline`) | Repo core                                                              | Website deploy xong có mốc để cập nhật                                                                     |
| `infra/plesk-cron.sh`                                    | Repo core, cài ở`/usr/local/bin/matbaows-cron` trên máy chủ 252 | Cron chạy việc cập nhật cho mọi website core trên máy chủ                                                |

### Quy trình an toàn khi sửa phần B

1. Chạy test mục tiêu rồi toàn bộ:
   ```bash
   php artisan test --compact tests/Feature/CoreUpdaterTest.php tests/Feature/CoreUpdateServiceTest.php \
       tests/Feature/CoreReleaseTest.php tests/Feature/CoreManifestTest.php tests/Feature/CoreCommandTest.php
   php artisan test --compact
   ```
2. Phát hành, **ghim một website thử** và cập nhật ([CORE_ROLLOUT.md](CORE_ROLLOUT.md), mục 4).
3. Từ website thử đó **cập nhật tiếp lên một bản sau nữa** (vd một bản patch chỉ đổi `VERSION`): chứng
   minh bộ cập nhật mới tự cập nhật được chính nó. Bỏ bước này là rủi ro lớn nhất.
4. Ổn rồi mới mở rollout cho các website khác.
