**Bạn:** Tạo đơn hàng cần những tham số bắt buộc nào?

**AI:** ### Tóm tắt ngắn (production)
- Endpoint: `POST https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create`   
- Headers bắt buộc: `Content-Type: application/json`, `Token`, `ShopId` 

### Các tham số bắt buộc (cần gửi trong body JSON)
Các trường bắt buộc tối thiểu để GHN chấp nhận tạo đơn thường bao gồm:
- `to_name` — tên người nhận   
- `to_phone` — số điện thoại người nhận   
- `to_address` — địa chỉ giao chi tiết   
- `to_ward_code` — mã phường/xã giao (lấy từ API phường)   
- `to_district_id` — mã quận/huyện giao (lấy từ API quận)   
- `service_type_id` — loại dịch vụ (ví dụ `2` cho <20kg, `5` cho >=20kg) — quyết định tuyến và phí   
- `weight` — khối lượng kiện (gram) (phải khác 0) — dùng để tính phí và chọn service 

Ghi chú: các trường gửi thêm (hàng hóa, COD, kích thước, địa chỉ trả hàng, …) có thể cần tùy kịch bản; để tính phí trước khi tạo đơn, dùng endpoint tính phí (`/shipping-order/fee`) — được yêu cầu các trường như `weight`, `to_district_id`, `to_ward_code`, `service_type_id` .

### Ví dụ request (payload tối giản)
```json
{
  "to_name": "Nguyen Van A",
  "to_phone": "0912345678",
  "to_address": "123 Đường A, Phường B",
  "to_ward_code": "00123",
  "to_district_id": 456,
  "service_type_id": 2,
  "weight": 500
}
```

Nếu bạn muốn, tôi có thể gửi đoạn ví dụ đầy đủ hơn (kèm `items`, `cod_amount`, `from_ward_code`/`from_district_id`, v.v.) hoặc liệt kê toàn bộ các trường của tài liệu `Tạo đơn`. Bạn muốn tôi làm gì tiếp theo?

- [Tạo đơn](/vi/docs/order/create)
- [Tính phí](/vi/docs/order/calculate-fee)

**Bạn:** xem đơn hàng đã tạo khi sài sandbox

**AI:** Được — có thể xem đơn tạo trên môi trường sandbox (staging) bằng API của GHN.

- Staging base URL: `https://dev-online-gateway.ghn.vn` (môi trường dành cho phát triển / sandbox) .  
- Dùng endpoint chi tiết đơn để xem dữ liệu đơn (gồm `created_date`, `leadtime`, `items`, v.v.) và kiểm tra lỗi `Order not found` hoặc quyền thiếu nếu cần .

Ví dụ yêu cầu (staging) — thay `ORDER_CODE`, `YOUR_TOKEN`, `YOUR_SHOP_ID`:
```bash
curl -X GET "https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail?order_code=ORDER_CODE" \
  -H "Token: YOUR_TOKEN" \
  -H "ShopId: YOUR_SHOP_ID" \
  -H "Content-Type: application/json"
```

- Header bắt buộc: `Token`, `ShopId`, `Content-Type: application/json`.  
- Nếu order không tồn tại API trả lỗi (Order not found) hoặc 403 khi thiếu quyền xem đơn .  
- Nếu bạn muốn kiểm thử tạo đơn trước khi xem: tạo đơn trên staging bằng `POST /shiip/public-api/v2/shipping-order/create` tại `https://dev-online-gateway.ghn.vn` (ví dụ ở tài liệu tạo đơn) fileciteturn0file2.

Bạn muốn tôi làm gì tiếp theo?

- [Tổng quan API](/vi/docs/getting-started/overview)
- [Order Info](/vi/docs/order/info)
- [Tạo đơn](/vi/docs/order/create)

**Bạn:** api lấy địa chỉ tỉnh thành mới

**AI:** ### Lấy danh sách Tỉnh/Thành (mô hình mới — 2 cấp)
- Endpoint (production): `GET https://online-gateway.ghn.vn/shiip/public-api/v3/master-data/province/all`   
- Headers bắt buộc: `Token`, `ShopId`, `Content-Type: application/json`   
- Tham số tùy chọn: `offset` (Int, mặc định `0`), `limit` (Int, tối đa `200`; bỏ hoặc `0` để lấy tất cả — hiện ~34 tỉnh/thành) 

Ghi chú quan trọng: đây là “mô hình địa chính 2 cấp (mới)” trả `name` để bạn truyền thẳng vào `to_province_name` khi tạo đơn với `is_new_to_address: true` (xem phần tạo đơn)  

Ví dụ request:
```bash
curl -X GET "https://online-gateway.ghn.vn/shiip/public-api/v3/master-data/province/all?offset=0&limit=200" \
  -H "Token: YOUR_TOKEN" \
  -H "ShopId: YOUR_SHOP_ID" \
  -H "Content-Type: application/json"
```

Ví dụ phần trả về (rút gọn):
```json
{
  "code": 200,
  "message": "Success",
  "data": [
    { "id": 1, "name": "Hà Nội" },
    { "id": 2, "name": "TP. Hồ Chí Minh" }
  ]
}
```
(Trường chi tiết response đầy đủ và tên trường thực tế có trong tài liệu endpoint) 

Nếu bạn cần: tôi có thể gửi ví dụ curl đầy đủ với token giả lập hoặc hướng dẫn cách dùng kết quả này khi gọi `POST /shipping-order/create` kèm `is_new_to_address: true`.

- [Lấy Tỉnh/Thành (Mới)](/vi/docs/master-data/get-province-new)
- [Tạo đơn](/vi/docs/order/create)