<?php

return [
    'order' => [
        'subject' => 'Cập nhật trạng thái đơn hàng #:number',
        'heading' => 'CẬP NHẬT ĐƠN HÀNG',
        'order_number' => 'Mã đơn hàng',
        'hello' => 'Xin chào :name,',
        'intro' => 'Trạng thái đơn hàng của bạn vừa được cập nhật như sau:',
        'status' => [
            'pending' => ['label' => 'Chờ xử lý', 'message' => 'Đơn hàng của bạn đã được tiếp nhận và đang chờ xác nhận.'],
            'processing' => ['label' => 'Đang xử lý', 'message' => 'Đơn hàng đang được chuẩn bị và đóng gói để gửi đi.'],
            'completed' => ['label' => 'Đã hoàn thành', 'message' => 'Đơn hàng đã được giao thành công. Cảm ơn bạn đã mua sắm tại cửa hàng chúng tôi!'],
            'cancelled' => ['label' => 'Đã hủy', 'message' => 'Đơn hàng đã bị hủy bỏ khỏi hệ thống.'],
        ],
        'shipping_info' => 'Thông tin giao hàng',
        'recipient' => 'Người nhận', 'phone' => 'Số điện thoại', 'address' => 'Địa chỉ nhận', 'notes' => 'Ghi chú',
        'items' => 'Danh sách sản phẩm', 'product' => 'Sản phẩm', 'quantity' => 'SL', 'price' => 'Giá', 'variant' => 'Mẫu',
        'subtotal' => 'Tạm tính', 'discount' => 'Giảm giá', 'total' => 'Tổng cộng',
        'copyright' => 'Hệ thống E-commerce Core - Bản quyền © :year',
        'automatic' => 'Email này được gửi tự động, vui lòng không phản hồi.',
    ],
];
