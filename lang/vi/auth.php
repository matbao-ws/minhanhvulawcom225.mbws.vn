<?php

return [
    'failed' => 'Thông tin đăng nhập không chính xác hoặc tài khoản đã bị khóa.',
    'password' => 'Mật khẩu không chính xác.',
    'throttle' => 'Bạn đăng nhập quá nhiều lần. Vui lòng thử lại sau :seconds giây.',
    'login' => [
        'title' => 'Đăng nhập',
        'heading' => 'Đăng nhập quản trị',
        'email' => 'Email',
        'email_placeholder' => 'Nhập email của bạn',
        'password' => 'Mật khẩu',
        'password_placeholder' => 'Nhập mật khẩu',
        'forgot_password' => 'Quên mật khẩu?',
        'remember' => 'Ghi nhớ đăng nhập',
        'submit' => 'Đăng nhập',
        'processing' => 'Đang đăng nhập...',
        'success' => 'Đăng nhập thành công.',
        'request_failed' => 'Không thể gửi yêu cầu đăng nhập. Vui lòng thử lại.',
        'email_not_found' => 'Email này chưa tồn tại trong hệ thống.',
        'inactive' => 'Tài khoản này đang bị khóa hoặc chưa được kích hoạt.',
        'password_incorrect' => 'Mật khẩu không chính xác.',
        'unauthorized' => 'Tài khoản của bạn không có quyền truy cập trang quản trị.',

        'slides' => [
            [
                'title' => 'Quản trị ecommerce tập trung',
                'description' => 'Theo dõi cấu hình gói, tính năng và dữ liệu vận hành từ một khu vực quản trị.',
            ],
            [
                'title' => 'Nền tảng mở rộng theo module',
                'description' => 'Bật tắt tính năng theo gói dịch vụ mà không thay đổi kiến trúc lõi.',
            ],
            [
                'title' => 'Sẵn sàng cho API client',
                'description' => 'Core Laravel phục vụ admin Blade và REST API cho website bán hàng.',
            ],
        ],
    ],
    'forgot' => [
        'title' => 'Quên mật khẩu',
        'description' => 'Nhập email đang liên kết với tài khoản. Hệ thống sẽ gửi cho bạn liên kết để đặt lại mật khẩu.',
        'submit' => 'Gửi liên kết đặt lại mật khẩu',
        'processing' => 'Đang gửi...',
        'back_to_login' => 'Quay lại đăng nhập',
        'request_failed' => 'Không thể gửi yêu cầu đặt lại mật khẩu. Vui lòng thử lại.',
        'slides' => [
            ['title' => 'Bảo vệ quyền truy cập quản trị'],
            ['title' => 'Đặt lại mật khẩu qua email'],
            ['title' => 'Tiếp tục quản trị ecommerce core'],
        ],
    ],
    'reset' => [
        'title' => 'Đặt lại mật khẩu',
        'heading' => 'Đặt lại mật khẩu',
        'password' => 'Mật khẩu mới',
        'password_confirmation' => 'Nhập lại mật khẩu',
        'submit' => 'Cập nhật mật khẩu',
    ],
    'logout' => [
        'success' => 'Đăng xuất thành công.',
    ],
];
