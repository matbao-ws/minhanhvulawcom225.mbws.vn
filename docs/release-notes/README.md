# Ghi chú phát hành

Mỗi bản phát hành có một file `<phiên bản>.md` ở đây (vd `1.2.0.md`), viết **cho chủ shop**: họ đọc nó
trên trang **Cập nhật phiên bản** trước khi bấm cập nhật.

- `php artisan core:release` đưa nội dung vào `release.json` (phần có chữ ký, không sửa được dọc đường);
  GitHub Release cũng dùng nó làm mô tả. Bản beta `1.2.0-beta.1` dùng `1.2.0-beta.1.md` nếu có, không thì
  `1.2.0.md`.
- **Bản stable bắt buộc có file này**: workflow phát hành dừng nếu thiếu.
- Viết tiếng Việt, ngắn, theo điều chủ shop thấy được; không ghi tên file, nhánh, chi tiết kỹ thuật.
  Markdown đơn giản: tiêu đề `##`, gạch đầu dòng, **đậm**. Tối đa 10.000 ký tự.

Mẫu:

```markdown
## Tính năng mới
- Tạo vận đơn Giao Hàng Nhanh ngay khi xác nhận đơn.

## Cải thiện
- Trang lỗi mới, rõ ràng hơn cho khách mua hàng.

## Sửa lỗi
- Email form liên hệ gửi đúng địa chỉ chủ shop.
```

Bản đã phát hành mà thiếu ghi chú (vd 1.1.0): đặt trên gateway, không cần phát hành lại —
`docs/CORE_ROLLOUT.md`, mục "Ghi chú phát hành".
