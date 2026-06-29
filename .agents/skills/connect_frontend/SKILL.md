---
name: Connect HTML/CSS/JS Frontend to Laravel Backend
description: Hướng dẫn kết nối các trang giao diện HTML/CSS/JS/Tailwind tĩnh (Frontend) vào hệ thống API & Routing của Laravel Ecommerce Core.
---

# Hướng dẫn kết nối Frontend (HTML/CSS/JS/Tailwind) với Backend

Bộ kỹ năng này hướng dẫn lập trình viên hoặc AI Agents cách tích hợp nhanh bộ giao diện tĩnh (HTML/CSS/JS/Tailwind) vào hệ thống REST API Public của dự án `ecommere-core`.

---

## 1. Bản đồ REST API Public sẵn có

Tất cả các API công khai đều nằm dưới tiền tố `/api/public/`. Tài liệu đặc tả đầy đủ có thể tương tác trực tiếp qua Swagger UI tại:
👉 `http://127.0.0.1:8000/api/docs`

### A. Luồng Xác Thực Khách Hàng (Customer Auth)
- **Đăng ký**: `POST /api/public/auth/register`
  - Payload: `{ name, email, password, password_confirmation }`
  - Response: Trả về token Sanctum và thông tin user.
- **Đăng nhập**: `POST /api/public/auth/login`
  - Payload: `{ email, password }`
  - Response: `{ token, user }`
- **Quên mật khẩu**: `POST /api/public/auth/forgot-password`
  - Payload: `{ email }`
- **Đặt lại mật khẩu**: `POST /api/public/auth/reset-password`
  - Payload: `{ email, token, password, password_confirmation }`
- **Thông tin tài khoản (Yêu cầu Token)**: `GET /api/public/auth/me`
  - Header: `Authorization: Bearer <token>`
- **Đăng xuất (Yêu cầu Token)**: `POST /api/public/auth/logout`

### B. Danh Mục & Sản Phẩm (Catalog)
- **Lấy cấu trúc danh mục (Tree)**: `GET /api/public/categories`
- **Lấy danh sách thương hiệu**: `GET /api/public/brands`
- **Lấy danh sách sản phẩm**: `GET /api/public/products`
  - Các tham số query: `q` (tìm kiếm), `category_id`, `brand_id`, `price_min`, `price_max`, `sort_by` (`price_asc`, `price_desc`).
- **Chi tiết sản phẩm**: `GET /api/public/products/{slug_or_id}` (Trả về thông tin chi tiết kèm các biến thể và bình luận đánh giá).
- **Gửi đánh giá/bình luận sản phẩm**: `POST /api/public/products/{id_or_slug}/reviews`
  - *Lưu ý*: Chỉ cho phép khách hàng đã mua sản phẩm này (đơn hàng không bị hủy) gửi đánh giá. Nếu chưa mua, API trả về lỗi `403`.
  - Payload: `{ customer_name, customer_email, rating, comment }`
    - Nếu đã đăng nhập (Sanctum): `customer_name` và `customer_email` không bắt buộc (tự động lấy từ User Profile).
    - Nếu chưa đăng nhập: `customer_name` và `customer_email` là bắt buộc.
    - `rating`: Điểm đánh giá (số nguyên từ 1 đến 5).
    - `comment`: Nội dung bình luận (tùy chọn, tối đa 1000 ký tự).



### C. Đơn Hàng & Giảm Giá (Checkout & Orders)
- **Kiểm tra Voucher**: `POST /api/public/vouchers/apply`
  - Payload: `{ code, subtotal }` -> Trả về số tiền giảm giá và thông tin voucher hợp lệ.
- **Đặt hàng (Checkout)**: `POST /api/public/orders/checkout`
  - Payload (Hỗ trợ cả Khách vãng lai và Thành viên):
    ```json
    {
      "customer_name": "Nguyen Van A",
      "customer_email": "customer@email.com",
      "customer_phone": "0987654321",
      "shipping_address": "123 Duong ABC, Can Tho",
      "province": "Cần Thơ",
      "district": "Ninh Kiều",
      "ward": "An Hòa",
      "notes": "Giao giờ hành chính",
      "payment_method": "cod", // hoặc "bank_transfer"
      "voucher_code": "DISCOUNT10", // tùy chọn
      "items": [
        {
          "product_id": 1,
          "product_variant_id": null, // nếu có chọn biến thể
          "quantity": 2
        }
      ]
    }
    ```
- **Lịch sử đơn hàng (Yêu cầu Token)**: `GET /api/public/orders`
- **Tra cứu đơn hàng không cần đăng nhập**: `GET /api/public/orders/track?order_number=XYZ&contact=0987654321`

### D. Quản lý địa chỉ giao hàng (Customer Addresses - Yêu cầu Token)
- **Lấy danh sách địa chỉ**: `GET /api/public/addresses`
- **Thêm địa chỉ mới**: `POST /api/public/addresses`
  - Payload: `{ customer_name, customer_phone, address, is_default }`
- **Cập nhật địa chỉ**: `PUT /api/public/addresses/{id}`
  - Payload: `{ customer_name, customer_phone, address, is_default }`
- **Xóa địa chỉ**: `DELETE /api/public/addresses/{id}`
- **Đặt làm mặc định**: `PATCH /api/public/addresses/{id}/set-default`

---

## 2. Đoạn mã JS mẫu để tích hợp nhanh (Axios / Fetch)

### Cấu hình Client API chung (ví dụ `api.js`):
```javascript
const API_BASE_URL = 'http://127.0.0.1:8000/api/public';

// Hàm gửi request tiện ích hỗ trợ đính kèm Bearer Token tự động
async function apiRequest(endpoint, method = 'GET', data = null) {
    const token = localStorage.getItem('customer_token');
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const config = {
        method,
        headers
    };
    if (data && method !== 'GET') {
        config.body = JSON.stringify(data);
    }

    const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Đã có lỗi xảy ra');
    }
    return response.json();
}
```

### Luồng Đăng nhập & Lưu Token:
```javascript
async function loginCustomer(email, password) {
    try {
        const res = await apiRequest('/auth/login', 'POST', { email, password });
        // Lưu token vào localStorage để dùng cho các request sau
        localStorage.setItem('customer_token', res.token);
        alert('Đăng nhập thành công! Chào ' + res.user.name);
        window.location.href = '/profile.html';
    } catch (err) {
        alert('Lỗi: ' + err.message);
    }
}
```

### Luồng Hiển Thị Sản Phẩm ra HTML (Tailwind):
```javascript
async function loadProducts() {
    try {
        const data = await apiRequest('/products');
        const container = document.getElementById('product-list');
        container.innerHTML = ''; // Clear skeleton
        
        data.data.forEach(product => {
            container.innerHTML += `
                <div class="bg-white p-4 rounded-lg shadow">
                    <img src="${product.image_url || 'placeholder.png'}" class="w-full h-48 object-cover rounded">
                    <h3 class="mt-2 font-bold">${product.name}</h3>
                    <p class="text-gray-600">${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(product.price)}</p>
                    <a href="product-detail.html?slug=${product.slug}" class="mt-3 block text-center bg-blue-600 text-white py-2 rounded">Xem chi tiết</a>
                </div>
            `;
        });
    } catch (err) {
        console.error(err);
    }
}
```

---

## 3. Quy trình từng bước nối HTML tĩnh với Backend

1. **Chuẩn bị cấu trúc**: Đưa các file HTML tĩnh vào thư mục `public/` của dự án (hoặc chạy một project Frontend độc lập và gọi API dạng CORS).
2. **Khởi tạo API Client**: Sử dụng Fetch hoặc Axios và cấu hình `API_BASE_URL` trỏ về dự án Laravel.
3. **Kết nối Authentication**: Tích hợp form Đăng ký/Đăng nhập để nhận token, lưu trữ token vào `localStorage`.
4. **Đổ dữ liệu (Render)**: Gọi API lấy Danh mục, Sản phẩm để hiển thị lên trang chủ, trang danh sách sản phẩm.
5. **Chi tiết sản phẩm**: Đọc tham số `?slug=...` từ URL của trang chi tiết, gọi API `GET /products/{slug}` để lấy đầy đủ hình ảnh, giá cả, danh sách biến thể (kích thước, màu sắc).
6. **Xử lý Giỏ hàng (Client-side Cart)**: Lưu giỏ hàng gồm danh sách `id`, `variant_id`, `quantity` vào `localStorage` của trình duyệt.
7. **Luồng Thanh toán (Checkout)**: Thu thập thông tin giao hàng của khách, kết hợp với các sản phẩm trong giỏ hàng từ `localStorage`, gọi API `/orders/checkout` để hoàn tất tạo đơn hàng.
