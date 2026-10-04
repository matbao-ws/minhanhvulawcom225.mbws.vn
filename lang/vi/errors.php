<?php

return [
    'common' => [
        'back_to_home' => 'Về trang chủ',
        'go_to_dashboard' => 'Vào bảng điều khiển',
        'go_back' => 'Quay lại trang trước',
        'reload_page' => 'Tải lại trang',
        'contact_support' => 'Liên hệ hỗ trợ',
        'helpful_links' => 'Liên kết hữu ích:',
        'system_status' => 'Trạng thái hệ thống',
        'operational' => 'Đang hoạt động',
        'error_reference' => 'Mã tra cứu sự cố:',
        'technical_details' => 'Thông tin kỹ thuật chi tiết',
        'copyright' => 'Bản quyền thuộc về',
        'all_rights_reserved' => 'Tất cả các quyền được bảo lưu.',
    ],

    '403' => [
        'title' => '403 - Quyền truy cập bị từ chối',
        'badge' => 'Mã lỗi 403 • Truy cập bị từ chối',
        'heading' => 'Bạn không có quyền truy cập khu vực này',
        'message' => 'Rất tiếc, tài khoản của bạn không có đủ thẩm quyền để truy cập tài nguyên hoặc thực hiện hành động này. Vui lòng liên hệ quản trị viên nếu bạn cho rằng đây là một sự nhầm lẫn.',
        'tip' => 'Gợi ý: Kiểm tra lại tài khoản đang đăng nhập hoặc liên hệ quản trị viên để được cấp quyền truy cập.',
    ],

    '404' => [
        'title' => '404 - Không tìm thấy trang',
        'badge' => 'Mã lỗi 404 • Không tìm thấy trang',
        'heading' => 'Oops! Không tìm thấy trang bạn yêu cầu',
        'message' => 'Đường dẫn bạn vừa truy cập có thể đã bị thay đổi, xóa bỏ hoặc tạm thời không khả dụng. Vui lòng kiểm tra lại địa chỉ hoặc quay về trang chủ.',
        'tip' => 'Gợi ý: Kiểm tra lại chính tả trên thanh địa chỉ hoặc sử dụng các nút điều hướng bên dưới.',
    ],

    '419' => [
        'title' => '419 - Phiên làm việc đã hết hạn',
        'badge' => 'Mã lỗi 419 • Phiên làm việc hết hạn',
        'heading' => 'Phiên làm việc của bạn đã hết hạn',
        'message' => 'Phiên làm việc hoặc mã bảo mật biểu mẫu (CSRF Token) đã hết hiệu lực do không có thao tác trong một khoảng thời gian dài. Điều này nhằm đảm bảo an toàn cho dữ liệu của bạn.',
        'tip' => 'Gợi ý: Nhấn nút "Tải lại trang" bên dưới để làm mới phiên làm việc và thực hiện lại thao tác.',
    ],

    '429' => [
        'title' => '429 - Quá nhiều yêu cầu',
        'badge' => 'Mã lỗi 429 • Quá giới hạn yêu cầu',
        'heading' => 'Bạn đang thao tác quá nhanh',
        'message' => 'Hệ thống nhận thấy có quá nhiều yêu cầu được gửi liên tục từ thiết bị hoặc mạng của bạn trong thời gian ngắn. Yêu cầu tạm thời bị giới hạn để đảm bảo máy chủ hoạt động ổn định.',
        'tip' => 'Gợi ý: Vui lòng tạm dừng khoảng 30 - 60 giây rồi nhấn tải lại trang để tiếp tục.',
    ],

    '500' => [
        'title' => '500 - Sự cố máy chủ',
        'badge' => 'Mã lỗi 500 • Sự cố máy chủ nội bộ',
        'heading' => 'Đã xảy ra sự cố trên máy chủ',
        'message' => 'Máy chủ gặp lỗi bất ngờ trong quá trình xử lý yêu cầu của bạn. Đội ngũ kỹ thuật đã được thông báo và đang tiến hành khắc phục nhanh nhất có thể.',
        'tip' => 'Gợi ý: Bạn có thể thử tải lại trang sau giây lát hoặc quay lại trang trước.',
    ],

    '503' => [
        'title' => '503 - Hệ thống đang bảo trì',
        'badge' => 'Mã lỗi 503 • Tạm ngưng để bảo trì',
        'heading' => 'Hệ thống đang tạm ngưng để bảo trì',
        'message' => 'Dịch vụ tạm thời không khả dụng do hệ thống đang trong quá trình nâng cấp, bảo dưỡng định kỳ nhằm nâng cao trải nghiệm người dùng. Chúng tôi sẽ sớm hoạt động trở lại!',
        'tip' => 'Gợi ý: Vui lòng quay lại sau ít phút hoặc bấm nút tải lại bên dưới để kiểm tra trạng thái.',
    ],

    '505' => [
        'title' => '505 - Phiên bản HTTP không hỗ trợ',
        'badge' => 'Mã lỗi 505 • Phiên bản HTTP không hỗ trợ',
        'heading' => 'Phiên bản giao thức không được hỗ trợ',
        'message' => 'Máy chủ không hỗ trợ hoặc từ chối xử lý phiên bản giao thức HTTP mà trình duyệt của bạn đang sử dụng. Vui lòng kiểm tra lại cấu hình trình duyệt hoặc mạng kết nối.',
        'tip' => 'Gợi ý: Hãy thử tải lại trang hoặc nâng cấp trình duyệt web lên phiên bản mới nhất.',
    ],
];
