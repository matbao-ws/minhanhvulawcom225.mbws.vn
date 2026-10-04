# Quy trình Git: từ code local tới website khách

> Tài liệu này dành cho người phát triển và phát hành **core**. Team dev làm website khách (tạo dự án,
> nối giao diện, deploy) đọc [TEAM_WORKFLOW.md](TEAM_WORKFLOW.md).

Các lệnh dưới đây gõ trong PowerShell hoặc terminal của VS Code, tại thư mục repo
(`F:\laragon\www\matbaows-core-admin` cho core, `F:\laragon\www\MatBaoAI-SEO` cho gateway).

---

## 0. Nắm cấu trúc trước

### Các nhánh của repo core (`matbaows-core-admin`)

| Nhánh | Dùng để | Push lên thì chuyện gì xảy ra |
|---|---|---|
| `feat/...`, `fix/...` | Làm một tính năng hoặc sửa một lỗi | CI chạy test khi mở PR |
| `dev` | Gom các tính năng đã xong | CI chạy test → **tự deploy staging** `corewbws-staging.webwow.vn` |
| `main` | Code đã sẵn sàng phát hành | CI chạy test. **Không** tự đẩy xuống website khách |
| tag `vX.Y.Z` | Một bản phát hành | CI build, ký và tạo **GitHub Release** → gateway nạp bản đó (rollout 0%) |

### Repo gateway (`MatBaoAI-SEO`)

| Nhánh | Push lên thì |
|---|---|
| `feat/...` | Không có gì |
| `main` | CI build và **tự deploy** lên `aimbw.webwow.vn` |

### Ba quy tắc vàng

1. **Không code thẳng trên `main` hoặc `dev`.** Luôn tạo nhánh `feat/...` hoặc `fix/...`.
2. **Luôn chạy `git pull` trước khi tạo nhánh mới hoặc gắn tag.**
3. **Không commit secret**: `.env`, file `.pem`, file key JSON, token. Đừng mở hay bôi đen chúng trong
   VS Code khi đang chat với AI.

---

## 1. Bắt đầu một việc mới

```bash
git status                      # phải sạch: "nothing to commit, working tree clean"
git switch dev
git pull origin dev             # lấy dev mới nhất
git switch -c feat/ten-tinh-nang
```

Đặt tên nhánh ngắn gọn, viết thường, nối bằng gạch ngang:
- `feat/chong-spam-lien-he`: tính năng mới
- `fix/sai-phi-ship-ghtk`: sửa lỗi
- `docs/huong-dan-git`: chỉ sửa tài liệu

> Nếu `git status` **không sạch** vì có code đang làm dở, xem mục 8.1.

---

## 2. Trong lúc code

### Kiểm tra trước khi commit

```bash
php artisan test --compact tests/Feature/TenTestLienQuan.php   # test phần vừa sửa
php artisan test --compact                                     # toàn bộ test
php -l duong/dan/file-vua-sua.php                              # kiểm tra cú pháp
```

Có thêm migration thì nhớ: **chỉ được thêm** (bảng mới, cột nullable hoặc có default, index). Không
xoá hay đổi tên cột, không dùng `->change()`. Test `CoreManifestTest` sẽ chặn nếu vi phạm. Xem
`AI_BUILD_PROMPT.md`, mục "Cập nhật phiên bản core".

### Commit

```bash
git status                       # xem file nào đã thay đổi
git diff                         # xem chi tiết đã sửa gì
git add duong/dan/file1 duong/dan/file2     # chọn đúng file của việc này
git commit -m "feat: chống spam form liên hệ"
```

- Nên `git add` từng file, tránh `git add .`, để không lỡ commit file rác hoặc `.env`.
- Mở đầu commit message bằng một tiền tố: `feat:` (tính năng), `fix:` (sửa lỗi), `docs:` (tài liệu),
  `test:`, `refactor:`, `chore:` (việc vặt). Phần sau viết ngắn, nói **làm gì**.
- Commit nhỏ, mỗi commit một ý. Một nhánh có nhiều commit là bình thường.

### Đẩy nhánh lên GitHub

```bash
git push -u origin feat/ten-tinh-nang     # lần đầu
git push                                  # những lần sau
```

---

## 3. Đưa vào `dev` qua Pull Request

1. Vào GitHub repo → hiện nút **Compare & pull request** → bấm vào.
2. **base: `dev`** ← **compare: `feat/ten-tinh-nang`**. Kiểm tra kỹ base là `dev`, không phải `main`.
3. Viết tiêu đề và mô tả ngắn: làm gì, test ra sao.
4. Chờ CI chạy xong và báo xanh ✔. Đỏ thì mở log, sửa trên nhánh, commit rồi push lại; PR tự cập nhật.
5. Bấm **Merge pull request**.
6. CI của `dev` tự deploy lên **staging**. Vào `corewbws-staging.webwow.vn` kiểm tra lại bằng tay.

Sau khi merge, dọn dẹp ở máy:

```bash
git switch dev
git pull origin dev
git branch -d feat/ten-tinh-nang          # xoá nhánh local đã merge
```

---

## 4. Phát hành bản mới cho website khách

Làm khi `dev` đã gom đủ tính năng và đã thử trên staging.

### 4.1. Tăng số phiên bản

Chọn số mới theo semver `MAJOR.MINOR.PATCH`:

| Thay đổi | Ví dụ | Tăng |
|---|---|---|
| Chỉ sửa lỗi | 1.0.1 → **1.0.2** | PATCH |
| Thêm tính năng, không phá cái cũ | 1.0.2 → **1.1.0** | MINOR |
| Thay đổi lớn: phá theme cũ, contract migration | 1.1.0 → **2.0.0** | MAJOR |

```bash
git switch dev
git pull origin dev
git switch -c chore/release-1.0.2
# mở file VERSION, sửa thành 1.0.2 (chỉ một dòng, không có chữ v)
git add VERSION
git commit -m "chore: release 1.0.2"
git push -u origin chore/release-1.0.2
```
Tạo PR `chore/release-1.0.2` → `dev` và merge.

### 4.2. Đưa `dev` lên `main`

Trên GitHub tạo PR **base: `main`** ← **compare: `dev`**, chờ CI xanh rồi merge.

Nếu PR báo conflict mà bạn chắc chắn `dev` là bản đúng, làm ở máy:

```bash
git switch main
git pull origin main
git merge origin/dev            # thường tự merge được; còn conflict thì xem mục 8.3
git push origin main
```

### 4.3. Gắn tag phát hành

```bash
git fetch origin
git show origin/main:VERSION    # phải ra đúng số định phát hành, vd 1.0.2
git tag v1.0.2 origin/main      # gắn vào main trên GitHub, không cần checkout
git push origin v1.0.2
```

- Tag = `v` + đúng nội dung file `VERSION`. Lệch nhau thì CI dừng với lỗi
  `Tag v1.0.2 does not match VERSION ...`.
- Bản beta: `VERSION` là `1.1.0-beta.1`, tag là `v1.1.0-beta.1`.

### 4.4. Theo dõi CI

GitHub → tab **Actions** → workflow **Core release**. Xanh nghĩa là tab **Releases** đã có `v1.0.2`
kèm 4 file (`release.json`, `release.json.sig`, `core-1.0.2.zip`, `dependencies-....zip`).

---

## 5. Cho website khách nhận bản mới (trên server gateway)

> Hướng dẫn đầy đủ (ghim, mở dần, dừng, xử lý khi không thấy bản mới): [CORE_ROLLOUT.md](CORE_ROLLOUT.md).

Gateway tự nạp bản mới: ngay lập tức nếu đã bật webhook, chậm nhất trong vòng 1 giờ nếu chưa.
Bản mới bắt đầu ở **0%**, tức chưa site nào nhận.

```bash
ssh root@<server>
cd /opt/matbaows
docker compose exec gateway php artisan core:fleet                       # có bản 1.0.2 chưa?
docker compose exec gateway php artisan core:releases:sync               # (nếu chưa có) nạp ngay
```

Mở dần:

```bash
# 1. Thử trước trên 1 site
docker compose exec gateway php artisan core:project site-thu.vn --pin=1.0.2
#    → vào admin site đó: Cấu hình → Cập nhật phiên bản → bấm "Cập nhật lên 1.0.2"
#    → chờ khoảng 1 phút, kiểm tra website

# 2. Ổn thì mở rộng
docker compose exec gateway php artisan core:release 1.0.2 --rollout=10
docker compose exec gateway php artisan core:release 1.0.2 --rollout=50
docker compose exec gateway php artisan core:release 1.0.2 --rollout=100

# 3. Gỡ ghim site thử để nó theo các bản sau
docker compose exec gateway php artisan core:project site-thu.vn --unpin
```

Có sự cố thì dừng phát hành ngay:

```bash
docker compose exec gateway php artisan core:release 1.0.2 --pause
```

Site đã cài lỗi tự quay lại code cũ. Muốn tự quay lại bằng tay: admin site → **Quay lại bản trước**.

---

## 6. Repo gateway (`MatBaoAI-SEO`)

Cách làm giống core nhưng đơn giản hơn: không có `dev`, không có tag.

```bash
cd F:\laragon\www\MatBaoAI-SEO
git switch main
git pull origin main
git switch -c feat/ten-tinh-nang
# ... code, test: php artisan test --compact ...
git add <file>
git commit -m "feat: ..."
git push -u origin feat/ten-tinh-nang
```
Tạo PR → `main` và merge. CI tự deploy lên `aimbw.webwow.vn`. Migration tự chạy khi container khởi động.

Kiểm tra trên server:

```bash
cd /opt/matbaows
docker compose exec gateway php artisan migrate:status | tail -5
```

---

## 7. Sửa lỗi gấp trên production (hotfix)

Khi site khách đang lỗi mà `dev` có nhiều thứ chưa sẵn sàng phát hành:

```bash
git fetch origin
git switch -c fix/loi-gap origin/main      # tạo nhánh từ main, không từ dev
# ... sửa, test ...
# sửa VERSION: tăng PATCH, vd 1.0.2 → 1.0.3
git add <file> VERSION
git commit -m "fix: ..."
git push -u origin fix/loi-gap
```

1. PR `fix/loi-gap` → **`main`**, merge.
2. Gắn tag `v1.0.3` theo mục 4.3, rồi rollout theo mục 5.
3. Đưa bản sửa về `dev` để lần phát hành sau không mất nó: PR `main` → `dev`, hoặc
   `git switch dev && git pull && git merge origin/main && git push origin dev`.

---

## 8. Lỗi hay gặp

### 8.1. "Your local changes would be overwritten" / "Please commit your changes or stash them"

Bạn có code chưa commit mà lại đổi nhánh hoặc pull. **Đừng xoá code.** Cách an toàn nhất là đưa phần
đang làm sang một nhánh riêng:

```bash
git switch -c feat/viec-dang-lam     # mang theo toàn bộ thay đổi chưa commit
git add <file>
git commit -m "wip: ..."             # wip = đang làm dở
```
Rồi mới `git switch main` / `git pull` như bình thường.

Nếu chỉ muốn cất tạm vài phút:
```bash
git stash push -m "dang lam shipping"   # cất
# ... làm việc khác ...
git stash pop                           # lấy lại
```

### 8.2. "Your branch is behind 'origin/...'"

Máy bạn cũ hơn GitHub. Chạy `git pull origin <nhánh>` khi không còn thay đổi chưa commit.

### 8.3. Conflict khi merge

Git đánh dấu những chỗ xung đột trong file:

```text
<<<<<<< HEAD
code trên nhánh hiện tại
=======
code từ nhánh đang merge vào
>>>>>>> origin/dev
```

1. `git status` liệt kê các file conflict (mục *both modified*).
2. Mở từng file trong VS Code, chọn **Accept Current / Incoming / Both**, rồi xoá hết các dấu
   `<<<<<<<`, `=======`, `>>>>>>>`.
3. Chạy test: `php artisan test --compact`.
4. `git add <file>` cho từng file đã sửa, rồi `git commit` (Git tự điền message merge).
5. Muốn huỷ merge, quay lại như trước: `git merge --abort`.

### 8.4. Gắn nhầm tag, hoặc CI báo "Tag does not match VERSION"

Workflow dừng sớm nên chưa có Release. Xoá tag rồi gắn lại:

```bash
git tag -d v1.0.2
git push origin :refs/tags/v1.0.2
git fetch origin
git show origin/main:VERSION          # kiểm tra
git tag v1.0.2 origin/main
git push origin v1.0.2
```

Nếu Release **đã được tạo** mà bản đó hỏng: đừng xoá tag. Chạy
`core:release 1.0.2 --withdraw` trên gateway, rồi phát hành bản mới hơn (1.0.3).

### 8.5. Lỡ commit `.env` hoặc secret

1. **Đổi ngay** secret đó: tạo key hay token mới, thu hồi cái cũ. Xoá khỏi Git không làm secret hết lộ.
2. Gỡ file khỏi Git nhưng giữ lại trên máy: `git rm --cached .env`, rồi commit.
3. Nếu đã push lên GitHub, coi như secret đã lộ, xem bước 1.

### 8.6. Commit nhầm nhánh (chưa push)

```bash
git switch -c feat/nhanh-dung        # nhánh mới mang theo commit vừa làm
git switch dev
git reset --hard origin/dev          # đưa dev về như GitHub, CHỈ khi dev chưa push commit đó
```
`reset --hard` xoá thay đổi chưa commit trên nhánh hiện tại. Chạy `git status` trước khi dùng.

### 8.7. Xem lại lịch sử

```bash
git log --oneline -10                 # 10 commit gần nhất
git log --oneline origin/main -5      # main trên GitHub
git branch -a                         # mọi nhánh, cả trên GitHub
git tag -l "v*"                       # các bản đã phát hành
```

---

## 9. Bảng tóm tắt một vòng đầy đủ

```text
git switch dev && git pull origin dev
git switch -c feat/x
   … code … test …
git add <file> && git commit -m "feat: …"
git push -u origin feat/x
   GitHub: PR feat/x → dev → CI xanh → Merge          (staging tự cập nhật)
   … kiểm tra staging …
   sửa VERSION (nhánh chore/release-…) → PR → dev
   GitHub: PR dev → main → CI xanh → Merge
git fetch origin && git show origin/main:VERSION
git tag vX.Y.Z origin/main && git push origin vX.Y.Z  (CI tạo Release)
   Server gateway: core:project <site> --pin=X.Y.Z → thử
                   core:release X.Y.Z --rollout=10 → 50 → 100
```
