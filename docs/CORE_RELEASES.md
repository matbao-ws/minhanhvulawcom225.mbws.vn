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
