# Đưa bản core mới tới website khách (ghim, mở dần, dừng)

Dành cho người phát hành core. Đọc sau khi đã gắn tag và CI tạo xong GitHub Release
([GIT_WORKFLOW.md](GIT_WORKFLOW.md), mục 4).

---

## 1. Hiểu trong 1 phút

Một bản mới (vd `1.1.0`) lên gateway (`aimbw.webwow.vn`) ở mức **mở 0%**: **chưa website nào được
nhận**. Đó là cố ý: bản lỗi không chạm tới khách nào cho tới khi bạn quyết định.

Từ đó có hai cách cho website nhận bản mới:

```
Bản 1.1.0 vừa phát hành: mở 0%
├─ core-admin.mbws.vn   ← GHIM vào 1.1.0  → được nhận (thử trước trên 1 site)
├─ website khách A      → chưa
└─ website khách B      → chưa

Thử ổn → MỞ DẦN: 10% → 50% → 100% số website
```

| Từ | Nghĩa |
|---|---|
| **Ghim** (`--pin`) | Chỉ định riêng một website nhận đúng một bản, bất kể bản đó đang mở bao nhiêu % |
| **Mở dần** (`--rollout`) | Cho một tỷ lệ % website thấy bản mới. Mỗi website luôn rơi vào cùng một nhóm, nên tăng % thì nhóm cũ vẫn giữ, chỉ thêm website mới |
| **Tạm dừng** (`--pause`) | Ngừng phát bản đó. Gateway **tự tạm dừng** khi có từ 3 website cài lỗi và chiếm ≥ 20% số website đã thử |
| **Thu hồi** (`--withdraw`) | Bỏ hẳn bản đó, không website nào được nhận nữa |

Website chỉ **thấy** bản mới; cài hay không vẫn do admin của website bấm **Cập nhật** (trừ website
bật cập nhật tự động, mục 6). Cài lỗi thì website tự quay về bản cũ.

---

## 2. Vào máy chủ gateway

Gateway là VPS riêng (nơi có `/opt/matbaows`), **không phải** máy chủ Plesk 252 chứa website khách.

```bash
ssh root@<IP-VPS-gateway>
cd /opt/matbaows
```

Mọi lệnh dưới đây chạy tại đó, dạng `docker compose exec gateway php artisan ...`.

---

## 3. Nạp bản mới và xem tình hình

```bash
# Gateway có bản mới chưa? (bảng trên: các bản; bảng dưới: website nào đang chạy bản nào)
docker compose exec gateway php artisan core:fleet

# Chưa thấy bản mới: nạp ngay từ GitHub (bình thường webhook tự nạp trong vài giây)
docker compose exec gateway php artisan core:releases:sync
```

`core:releases:sync` in `Added at 0% rollout: 1.1.0` là đã nạp; `No new release.` là đã có từ trước.

---

## 4. Thử trên một website (ghim)

```bash
docker compose exec gateway php artisan core:project core-admin.mbws.vn --pin=1.1.0
```

Rồi vào admin website đó → menu **Cập nhật phiên bản**:

1. Bấm **Kiểm tra** → thấy bản `1.1.0` và nút **Cập nhật lên 1.1.0**.
2. Bấm **Cập nhật**. Website vào chế độ bảo trì khoảng 1 phút; cron của máy chủ chạy việc cập nhật.
3. Tải lại trang: cuối menu trái hiện **Phiên bản core 1.1.0** là xong. Kiểm tra các trang chính
   (trang chủ, sản phẩm, giỏ hàng, đặt thử một đơn, admin).

Lỗi thì trang Cập nhật phiên bản ghi bước hỏng; website đã tự quay về bản cũ.

---

## 5. Mở cho các website khác

Thử ổn thì gỡ ghim (để website thử đi theo các bản sau như mọi website), rồi mở dần:

```bash
docker compose exec gateway php artisan core:project core-admin.mbws.vn --unpin

docker compose exec gateway php artisan core:release 1.1.0 --rollout=10    # ~10% website
# đợi 1–2 ngày, xem core:fleet cột "failed"
docker compose exec gateway php artisan core:release 1.1.0 --rollout=50
docker compose exec gateway php artisan core:release 1.1.0 --rollout=100   # tất cả
```

Sau mỗi bước, `core:fleet` cho biết bao nhiêu website đã cài (`installed`) và bao nhiêu lỗi (`failed`).

---

## 6. Các lệnh khác

| Việc | Lệnh |
|---|---|
| Dừng phát ngay (có sự cố) | `core:release 1.1.0 --pause` |
| Phát lại sau khi dừng | `core:release 1.1.0 --resume` |
| Bỏ hẳn bản lỗi | `core:release 1.1.0 --withdraw` |
| Không cho một website nhận bản nào nữa | `core:project <domain> --exclude` (bỏ: `--include`) |
| Cho website tự cài bản mới, không cần bấm (trong lần kiểm tra mỗi giờ; bản major vẫn phải bấm tay) | `core:project <domain> --auto=on` (tắt: `--auto=off`) |
| Website thử bản beta | `core:project <domain> --channel=beta` (về lại: `--channel=stable`) |
| Xem một website đang thế nào | `core:project <domain>` (không kèm tuỳ chọn) |

Mỗi lệnh đều dạng `docker compose exec gateway php artisan <lệnh>`.

---

## 7. Bấm Kiểm tra mà không thấy bản mới

Đi theo thứ tự:

1. **GitHub có Release chưa?** Tab Releases của repo core phải có `v1.1.0` với 4 file
   (`release.json`, `release.json.sig`, `core-1.1.0.zip`, `dependencies-....zip`). Không có: xem tab
   Actions. Lỗi `Tag v1.1.0 does not match VERSION ...` nghĩa là tag gắn vào commit mà `VERSION`
   chưa đổi; xoá tag (`git tag -d v1.1.0; git push origin :refs/tags/v1.1.0`), merge `dev` → `main`,
   `git pull`, kiểm tra `VERSION` rồi gắn lại.
2. **Gateway có bản đó chưa?** `core:fleet`; chưa có thì `core:releases:sync`.
3. **Website được nhận chưa?** Bản mới ở 0%: phải **ghim** (mục 4) hoặc mở rollout (mục 5).
   `core:project <domain>` cho biết website có bị `excluded`, đang ở kênh nào, ghim bản nào.
4. **Website kết nối được gateway không?** Trên máy chủ website:
   `php artisan gateway:check` phải báo `Kết nối gateway OK`.

## 8. Bấm Cập nhật mà không chạy

- **Không có cron.** Bấm Cập nhật chỉ xếp việc; cron chạy nó trong vòng 1 phút. Website trên máy chủ
  252 dùng cron chung ([TEAM_WORKFLOW.md](TEAM_WORKFLOW.md), phụ lục A): `matbaows-cron --list` phải
  hiện website với `CÓ`.
- **"Chưa ghi mốc file core"**: deploy lại website một lần bằng `deploy.py` (ghi
  `storage/app/core/installed.json`).
- **"N file core đã bị sửa trực tiếp trên website"**: có người sửa file core trên hosting. Đưa thay đổi
  đó vào theme hoặc vào core, rồi chạy `php artisan core:status --baseline` trên website.
