<?php

// Guided tours of the admin (App\Support\AdminTours). Plain text: it is escaped before display.
return [
    'ui' => [
        'start' => 'Hướng dẫn sử dụng trang này',
        'next' => 'Tiếp →',
        'prev' => '← Lùi',
        'done' => 'Hoàn tất',
        'skip' => 'Bỏ qua',
        'progress' => 'Bước {{current}}/{{total}}',
    ],

    'layout' => [
        'sidebar' => ['title' => 'Menu quản trị', 'description' => 'Mọi khu vực quản lý nằm ở đây: sản phẩm, đơn hàng, nội dung, khách hàng và cài đặt. Bạn chỉ thấy những mục mình có quyền dùng.'],
        'search' => ['title' => 'Tìm nhanh', 'description' => 'Tìm sản phẩm, đơn hàng, bài viết hay trang quản trị bằng từ khoá, không cần mở từng menu.'],
        'notifications' => ['title' => 'Thông báo', 'description' => 'Đơn hàng mới, cảnh báo hạn mức gói và các việc cần xử lý hiện ở đây.'],
        'help' => ['title' => 'Xem lại hướng dẫn', 'description' => 'Bấm nút này bất cứ lúc nào để xem lại hướng dẫn của trang đang mở.'],
    ],

    'form' => [
        'save' => ['title' => 'Lưu thay đổi', 'description' => 'Nút lưu luôn nằm ở cuối màn hình. Thay đổi chỉ được ghi khi bạn bấm lưu.'],
    ],

    'dashboard' => [
        'intro' => ['title' => 'Chào mừng đến trang quản trị', 'description' => 'Hướng dẫn ngắn này giới thiệu các phần chính. Dùng nút Tiếp/Lùi hoặc phím mũi tên để di chuyển, Bỏ qua để đóng.'],
        'welcome' => ['title' => 'Lối tắt', 'description' => 'Đi nhanh tới các khu vực dùng nhiều nhất: sản phẩm, đơn hàng và khách hàng.'],
        'stats' => ['title' => 'Số liệu bán hàng', 'description' => 'Doanh thu và số đơn theo từng trạng thái, cập nhật theo đơn thực tế.'],
        'charts' => ['title' => 'Biểu đồ', 'description' => 'Doanh thu theo thời gian và tỉ lệ đơn theo trạng thái.'],
        'recent_orders' => ['title' => 'Đơn hàng gần đây', 'description' => 'Những đơn mới nhất. Bấm "Xem tất cả" để mở danh sách đơn hàng.'],
    ],

    'products_index' => [
        'intro' => ['title' => 'Danh sách sản phẩm', 'description' => 'Quản lý toàn bộ sản phẩm bán trên website.'],
        'create' => ['title' => 'Thêm sản phẩm', 'description' => 'Tạo sản phẩm mới. Bên cạnh có nút nhập và xuất sản phẩm từ file.'],
        'filters' => ['title' => 'Lọc và tìm kiếm', 'description' => 'Tìm theo tên hoặc mã, lọc theo danh mục, thương hiệu và trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều sản phẩm để bật, tắt hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách', 'description' => 'Mỗi dòng có nút xem, sửa và xoá sản phẩm.'],
    ],

    'products_form' => [
        'intro' => ['title' => 'Thông tin sản phẩm', 'description' => 'Điền thông tin từ trên xuống. Mục nào chưa có có thể bổ sung sau.'],
        'languages' => ['title' => 'Nội dung theo ngôn ngữ', 'description' => 'Tên, mô tả và SEO được nhập riêng cho từng ngôn ngữ của website.'],
        'media' => ['title' => 'Hình ảnh', 'description' => 'Thêm ảnh sản phẩm từ thư viện Media hoặc tải ảnh mới lên.'],
        'variants' => ['title' => 'Biến thể', 'description' => 'Sản phẩm có màu, kích cỡ…? Khai báo thuộc tính để có từng SKU với giá và tồn kho riêng.'],
        'pricing' => ['title' => 'Giá bán', 'description' => 'Giá bán, giá so sánh (gạch ngang) và giá vốn. Giá cuối cùng khách trả, sau khuyến mãi, luôn do hệ thống tính.'],
        'side' => ['title' => 'Trạng thái và phân loại', 'description' => 'Ảnh đại diện, hiển thị hay ẩn, danh mục, thương hiệu và mã SKU.'],
    ],

    'orders_index' => [
        'intro' => ['title' => 'Đơn hàng', 'description' => 'Mọi đơn đặt từ website và đơn tạo tay đều ở đây.'],
        'create' => ['title' => 'Tạo đơn hàng', 'description' => 'Tạo đơn thay khách, ví dụ khi khách đặt qua điện thoại.'],
        'filters' => ['title' => 'Lọc đơn', 'description' => 'Lọc theo trạng thái đơn, thanh toán và giao hàng, hoặc tìm theo mã đơn và thông tin khách.'],
        'table' => ['title' => 'Danh sách đơn', 'description' => 'Bấm "Xem" ở mỗi dòng để mở chi tiết và xử lý đơn.'],
    ],

    'orders_show' => [
        'intro' => ['title' => 'Chi tiết đơn hàng', 'description' => 'Xử lý một đơn từ lúc nhận đến lúc giao xong.'],
        'toolbar' => ['title' => 'Quay lại và in', 'description' => 'Quay về danh sách hoặc in phiếu đơn hàng.'],
        'status' => ['title' => 'Cập nhật trạng thái', 'description' => 'Đổi trạng thái đơn và thanh toán. Khách nhận email khi trạng thái thay đổi.'],
        'ship' => ['title' => 'Tạo vận đơn', 'description' => 'Gửi đơn sang đơn vị vận chuyển đã kết nối để lấy mã vận đơn.'],
        'history' => ['title' => 'Lịch sử đơn', 'description' => 'Mọi thay đổi của đơn: ai làm, lúc nào.'],
        'sheet' => ['title' => 'Phiếu đơn hàng', 'description' => 'Thông tin khách, sản phẩm và tổng tiền của đơn.'],
    ],

    'posts_index' => [
        'intro' => ['title' => 'Bài viết', 'description' => 'Tin tức và bài blog của website.'],
        'create' => ['title' => 'Viết bài mới', 'description' => 'Soạn một bài viết mới.'],
        'ai_draft' => ['title' => 'Soạn nháp bằng AI', 'description' => 'Đưa ý chính, AI viết bản nháp để bạn sửa và đăng.'],
        'filters' => ['title' => 'Lọc bài viết', 'description' => 'Tìm theo tiêu đề, lọc theo chuyên mục và trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều bài để đăng, ẩn hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách bài viết', 'description' => 'Mỗi dòng có nút sửa và xoá bài.'],
    ],

    'categories_index' => [
        'intro' => ['title' => 'Danh mục sản phẩm', 'description' => 'Sắp xếp sản phẩm thành nhóm để khách dễ tìm.'],
        'create' => ['title' => 'Thêm danh mục', 'description' => 'Tạo danh mục mới, có thể đặt làm danh mục con.'],
        'filters' => ['title' => 'Lọc danh mục', 'description' => 'Tìm theo tên, lọc theo danh mục cha và trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều danh mục để bật, tắt hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Kéo để sắp xếp', 'description' => 'Kéo thả các dòng để đổi thứ tự hiển thị trên website.'],
    ],

    'customers_index' => [
        'intro' => ['title' => 'Khách hàng', 'description' => 'Những người đã đặt hàng, gom theo thông tin trên đơn; ai có tài khoản trên website được đánh dấu.'],
        'count' => ['title' => 'Tổng số khách', 'description' => 'Số khách đã từng đặt hàng.'],
        'export' => ['title' => 'Xuất danh sách', 'description' => 'Tải danh sách khách ra file, theo bộ lọc đang chọn.'],
        'search' => ['title' => 'Tìm khách', 'description' => 'Tìm theo tên, email hoặc số điện thoại.'],
        'table' => ['title' => 'Danh sách khách', 'description' => 'Số đơn, đơn hoàn thành, tổng chi tiêu và lần mua gần nhất của từng khách.'],
    ],

    'vouchers_index' => [
        'intro' => ['title' => 'Mã giảm giá', 'description' => 'Tạo mã để khách nhập khi thanh toán.'],
        'create' => ['title' => 'Tạo mã', 'description' => 'Đặt mức giảm, điều kiện đơn tối thiểu, số lượt dùng và thời hạn.'],
        'filters' => ['title' => 'Lọc mã', 'description' => 'Tìm theo mã và lọc theo trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều mã để bật, tắt hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách mã', 'description' => 'Số lượt đã dùng và thời hạn của từng mã.'],
    ],

    'settings' => [
        'intro' => ['title' => 'Cài đặt website', 'description' => 'Thông tin chung của cửa hàng.'],
        'tabs' => ['title' => 'Các nhóm cài đặt', 'description' => 'Chuyển giữa thông tin chung, liên hệ, mã nhúng và Analytics.'],
        'general' => ['title' => 'Thông tin chung', 'description' => 'Tên website, logo, favicon và mô tả hiển thị trên Google.'],
        'save' => ['title' => 'Lưu cài đặt', 'description' => 'Bấm lưu để áp dụng thay đổi cho website.'],
    ],
];
