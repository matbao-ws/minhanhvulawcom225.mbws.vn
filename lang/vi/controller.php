<?php

return [
    'auth' => [
        'invalid_credentials' => 'Email hoặc mật khẩu không chính xác.',
        'reset_link_sent' => 'Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi.',
        'invalid_reset_token' => 'Token khôi phục mật khẩu không hợp lệ hoặc đã hết hạn.',
    ],
    'api' => [
        'invalid_data' => 'Dữ liệu không hợp lệ.',
        'email_unavailable' => 'Email không hợp lệ hoặc đã được sử dụng.',
        'registered' => 'Đăng ký tài khoản thành công.',
        'logged_in' => 'Đăng nhập thành công.',
        'logged_out' => 'Đăng xuất thành công.',
        'password_reset' => 'Đổi mật khẩu mới thành công.',
        'address_created' => 'Thêm địa chỉ mới thành công.',
        'address_updated' => 'Cập nhật địa chỉ thành công.',
        'address_deleted' => 'Xóa địa chỉ thành công.',
        'address_defaulted' => 'Đặt làm địa chỉ mặc định thành công.',
        'address_not_editable' => 'Không tìm thấy địa chỉ hoặc bạn không có quyền sửa địa chỉ này.',
        'address_not_deletable' => 'Không tìm thấy địa chỉ hoặc bạn không có quyền xóa địa chỉ này.',
        'address_not_actionable' => 'Không tìm thấy địa chỉ hoặc bạn không có quyền thao tác trên địa chỉ này.',
    ],
    'admin' => [
        'contact_deleted' => 'Đã xóa liên hệ.',
        'promotion_created' => 'Đã tạo chương trình khuyến mãi.',
        'promotion_updated' => 'Đã cập nhật chương trình khuyến mãi.',
        'promotion_deleted' => 'Đã xóa chương trình khuyến mãi.',
        'promotion_used' => 'Không thể xóa chương trình đã có lượt sử dụng. Hãy tắt chương trình thay vì xóa.',
        'promotion_variant_missing' => 'Có SKU không tồn tại.',
        'promotion_target_used' => 'Không thể bỏ một SKU đã có lượt flash sale. Hãy tắt chương trình hoặc giữ SKU đó.',
    ],
];
