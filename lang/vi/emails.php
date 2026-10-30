<?php

return [
    // Defaults for each email (App\Services\EmailAppearance); a shop overrides them in
    // Quản trị › Giao diện email. {ten_khach}-style variables are filled in when sending.
    'appearance' => [
        'order_placed' => [
            'subject' => 'Đã nhận đơn #{ma_don} – cảm ơn bạn!',
            'heading' => 'Cảm ơn bạn, {ten_khach}! Shop đã nhận đơn.',
            'intro' => 'Shop sẽ liên hệ xác nhận trong thời gian sớm nhất rồi bắt đầu chuẩn bị hàng. Mỗi lần đơn đổi trạng thái, bạn đều nhận email như thế này.',
        ],
        'order_preparing' => [
            'subject' => 'Đơn #{ma_don} đã được xác nhận',
            'heading' => 'Shop đang chuẩn bị đơn của bạn',
            'intro' => 'Đơn hàng đã được xác nhận và đang được đóng gói. Shop sẽ báo bạn khi hàng bắt đầu được giao.',
        ],
        'order_shipping' => [
            'subject' => 'Đơn #{ma_don} đang trên đường giao',
            'heading' => 'Đơn hàng đã rời kho',
            'intro' => '{don_vi_van_chuyen} đang chuyển đơn tới bạn. Mã vận đơn: {ma_van_don}.',
        ],
        'order_completed' => [
            'subject' => 'Đơn #{ma_don} đã giao thành công',
            'heading' => 'Hàng đã tới tay bạn',
            'intro' => 'Cảm ơn bạn đã tin chọn {ten_shop}. Nếu sản phẩm có vấn đề, trả lời email này để shop hỗ trợ.',
        ],
        'order_cancelled' => [
            'subject' => 'Đơn #{ma_don} đã được huỷ',
            'heading' => 'Đơn #{ma_don} đã được huỷ',
            'intro' => 'Đơn hàng không còn được xử lý. Nếu bạn không yêu cầu huỷ hoặc muốn đặt lại, liên hệ shop hoặc trả lời email này.',
        ],
        'owner_new_order' => [
            'subject' => 'Đơn mới #{ma_don} · {tong_tien}',
            'heading' => '{tong_tien} từ {ten_khach}',
            'intro' => 'Khách cũng đã nhận email xác nhận đơn. Gọi xác nhận sớm giúp giảm đơn bom hàng.',
        ],
        'contact' => [
            'subject' => 'Liên hệ mới: {ten_nguoi_gui}',
            'heading' => '{ten_nguoi_gui} cần bạn tư vấn',
            'intro' => 'Bấm Trả lời trong hộp thư để gửi thẳng cho khách. Liên hệ này đã lưu trong Quản trị › Liên hệ.',
        ],
        'reset_password' => [
            'subject' => 'Đặt lại mật khẩu tài khoản {ten_shop}',
            'heading' => 'Đặt lại mật khẩu',
            'intro' => 'Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Bấm nút dưới đây để chọn mật khẩu mới.',
        ],
        'settings_test' => [
            'subject' => '[{ten_shop}] Email thử cấu hình gửi thư',
            'heading' => 'Cấu hình gửi email hoạt động',
            'intro' => 'Đây là email thử từ trang quản trị {ten_shop}. Email đơn hàng gửi khách, email báo đơn mới và liên hệ gửi bạn sẽ đi theo đúng cấu hình dưới đây.',
        ],
    ],

    // Fixed wording inside the templates.
    'ui' => [
        'eyebrow' => [
            'order_placed' => 'Đã nhận đơn', 'order_preparing' => 'Đã xác nhận', 'order_shipping' => 'Đang giao hàng',
            'order_completed' => 'Đã giao thành công', 'order_cancelled' => 'Đơn đã huỷ',
            'owner_new_order' => 'Đơn hàng mới', 'contact' => 'Liên hệ mới từ website',
        ],
        'steps' => ['Đã đặt', 'Đang chuẩn bị', 'Đang giao', 'Đã giao'],
        'order_number' => 'Mã đơn', 'placed_at' => 'Đặt lúc', 'items' => 'Sản phẩm', 'qty' => 'SL',
        'subtotal' => 'Tạm tính', 'voucher' => 'Mã giảm giá', 'promotion' => 'Khuyến mãi', 'shipping_fee' => 'Phí vận chuyển',
        'free' => 'Miễn phí', 'total' => 'Tổng cộng', 'customer_pays' => 'Khách trả',
        'ship_to' => 'Giao đến', 'payment_shipping' => 'Thanh toán & vận chuyển', 'notes' => 'Ghi chú',
        'tracking' => 'Mã vận đơn', 'track' => 'Theo dõi vận đơn', 'tracking_later' => 'Mã vận đơn gửi kèm khi bắt đầu giao',
        'refund_title' => 'Đã thanh toán trước?',
        'refund_default' => 'Shop sẽ liên hệ để hoàn tiền cho bạn.',
        'help' => 'Cần đổi địa chỉ hay hỏi về đơn? Trả lời email này hoặc gọi :phone.',
        'help_no_phone' => 'Cần đổi địa chỉ hay hỏi về đơn? Trả lời email này để shop hỗ trợ.',
        'open_order' => 'Mở đơn trong quản trị', 'call_customer' => 'Gọi khách', 'call_back' => 'Gọi lại khách', 'reply' => 'Trả lời email',
        'name' => 'Họ tên', 'phone' => 'Điện thoại', 'email' => 'Email', 'sent_at' => 'Gửi lúc :time',
        'reset_button' => 'Đặt mật khẩu mới', 'reset_expires' => 'Liên kết dùng được một lần và hết hạn sau :minutes phút.',
        'reset_fallback' => 'Không bấm được nút? Dán liên kết này vào trình duyệt:',
        'reset_ignore' => 'Không phải bạn yêu cầu? Bỏ qua email này, mật khẩu hiện tại vẫn giữ nguyên.',
        'test_via' => 'Gửi qua', 'test_own_smtp' => 'SMTP riêng của shop', 'test_relay' => 'Mail relay MatBao', 'test_default' => 'Cấu hình mặc định của máy chủ',
        'test_host' => 'Máy chủ', 'test_encryption' => 'Mã hoá', 'test_from' => 'Gửi từ', 'test_to' => 'Nhận thông báo',
        'test_spam' => 'Thấy email này trong thư mục Spam? Đánh dấu "Không phải spam" và thêm địa chỉ gửi vào danh bạ để các email sau vào hộp thư chính.',
        'hotline' => 'Hotline',
        'footer_customer' => 'Bạn nhận email này vì đã đặt hàng tại :shop.',
        'footer_owner' => 'Email nội bộ gửi tới chủ shop :shop.',
        'footer_admin' => 'Email bảo mật gửi tới tài khoản của bạn tại :shop.',
    ],
];
