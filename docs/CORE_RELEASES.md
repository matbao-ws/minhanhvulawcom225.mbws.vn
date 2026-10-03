# Phát hành bản core

Hướng dẫn vận hành cho người phát hành core. Quy tắc (core/theme, migration expand/contract) nằm trong
`AI_BUILD_PROMPT.md`, mục "Cập nhật phiên bản core".

## Một lần: tạo khoá ký

Làm trên máy của bạn, **không** đặt file khoá trong thư mục repo:

```bash
php artisan core:keygen --private=C:/Users/<bạn>/core-signing.pem
```

Lệnh này ghi private key ra file, không in ra màn hình. Màn hình chỉ in một dòng public key.

1. GitHub → repo core → **Settings → Environments → New environment** tên `release`. Nên bật
   *Required reviewers* để mỗi lần phát hành cần một người duyệt.
2. Trong environment `release`, chọn **Add secret** tên `CORE_RELEASE_SIGNING_KEY`, dán **nội dung** file `.pem`.
3. Dán dòng public key vào `config/core_update.php` → `public_keys`, rồi commit.
4. Xoá file `.pem`, hoặc cất offline (USB, kho mật khẩu). Không commit, không dán vào chat.

Mất private key thì tạo cặp mới và phát hành một bản có **cả** public key cũ lẫn mới, ký bằng key cũ
nếu còn. Nếu không còn key cũ, các website phải được cập nhật tay một lần để nhận key mới.

## Mỗi lần phát hành

1. Sửa `VERSION` (semver). Tăng patch khi sửa lỗi, minor khi thêm tính năng tương thích, major khi
   có thay đổi phá tương thích.
2. Merge vào `main`, rồi gắn tag trùng với `VERSION`:

   ```bash
   git tag v1.1.0          # stable
   git tag v1.1.0-beta.1   # beta (VERSION cũng phải là 1.1.0-beta.1)
   git push origin v1.1.0
   ```

3. Workflow **Core release** sẽ test, build, ký và verify, rồi tạo GitHub Release gồm:

   | File | Nội dung |
   |---|---|
   | `release.json` | phiên bản, kênh, `requires.php`, `requires.min_from`, ảnh hưởng database, sha256 từng file core |
   | `release.json.sig` | chữ ký của `release.json` |
   | `core-X.Y.Z.zip` | chỉ file core |
   | `dependencies-<lock>.zip` | `vendor/`, chỉ phải tải lại khi `composer.lock` đổi |

Workflow không đẩy gì xuống website. Việc rollout là của update service (Phase C/D).

## `release.json` → `database`

- `migration`: có migration mới so với bản stable gần nhất.
- `rollback_safe`: `false` khi bản có migration `@contract-migration`. Gặp lỗi thì không thể chỉ trả
  code về bản cũ, nên không tự động rollout.
- `backup_required`: `true` khi có contract migration, hoặc khi build với `--backup-required`.

Muốn bắt website phải lên một bản trung gian trước, nâng `min_from` trong `config/core_update.php`.

## Kiểm tra một bản phát hành

```bash
php artisan core:verify-release path/to/downloaded-release
```

## Đưa bản phát hành tới các website (update service trên gateway)

Bản phát hành mới đi tới website theo thứ tự sau:

1. GitHub Release được tạo (tag). Mỗi giờ gateway chạy `core:releases:sync` và ghi bản đó với
   **rollout 0%**, tức chưa website nào nhận.
2. Trên gateway, tăng dần số website được nhận:

   ```bash
   php artisan core:project beta1.vn --pin=1.1.0              # thử trước trên 1 site
   php artisan core:release 1.1.0 --rollout=1                 # rồi 1% → 10 → 25 → 50 → 100
   php artisan core:fleet                                     # site nào chạy bản nào, bản nào lỗi
   php artisan core:release 1.1.0 --pause                     # dừng phát hành (--resume để tiếp)
   ```

   Khi có ≥3 website lỗi và tỷ lệ lỗi ≥20%, gateway tự **pause** bản đó.
3. Website hỏi gateway mỗi giờ. Admin thấy "Bản mới" trong **Cấu hình → Cập nhật phiên bản**
   (chỉ superadmin) và bấm cập nhật; cron cài trong vòng 1 phút. Website bật `core:project <domain>
   --auto=on` thì tự cài các bản patch/minor an toàn. Bản major hoặc bản cần backup luôn cần bấm tay.

Kênh của từng website: `core:project <domain> --channel=beta`. Bỏ một site ra khỏi mọi đợt phát hành:
`--exclude`. Tool nội bộ cũng đặt được qua `PATCH /internal/projects/{domain}` (`core_channel`,
`core_pinned_version`, `core_auto_update`, `core_excluded`).

Website cần: gateway đã kết nối (`MATBAO_AI_URL`/`MATBAO_AI_TOKEN`), cron `schedule:run` mỗi phút,
và đã chạy `core:status --baseline` một lần.

## Cập nhật một website bằng tay (SSH/terminal của Plesk/cPanel)

Lần đầu trên mỗi website, khi các file core đúng như bản đã phát hành:

```bash
php artisan core:status --baseline
```

Rồi cài bản mới. Dùng `--url` trỏ tới thư mục chứa `release.json` và các gói, hoặc `--from` trỏ tới
thư mục đã tải về:

```bash
php artisan core:update --from=/path/to/release --check   # chỉ kiểm tra: UPDATE_READY / UPDATE_BLOCKED
php artisan core:update --from=/path/to/release           # cài: UPDATE_INSTALLED / UPDATE_FAILED
php artisan core:rollback                                 # trả code của lần cập nhật gần nhất
```

- Chạy bằng PHP CLI, đúng user sở hữu file của site. Host có nhiều phiên bản PHP thì đặt
  `CORE_UPDATE_PHP_BINARY` (ví dụ `/opt/plesk/php/8.3/bin/php`) trong `.env`.
- Bản có `backup_required`: backup database trên Plesk/cPanel trước, rồi chạy thêm cờ `--backup-done`.
- `UPDATE_BLOCKED drift`: có file core bị sửa tay trên site. Chuyển chỗ sửa vào theme (hoặc vào core
  ở repo), trả file về như cũ, rồi `core:status` để kiểm tra lại.
- Cập nhật hỏng thì code cũ tự được trả lại và site được mở lại. Database giữ nguyên, vì migration chỉ
  được thêm (expand), code cũ vẫn chạy được trên schema mới.
- `storage/app/core/` chứa `state.json` (lần chạy gần nhất), `history.jsonl` và `backups/` (3 bản gần
  nhất, gồm cả `vendor/` cũ khi bản đó có đổi dependencies).
