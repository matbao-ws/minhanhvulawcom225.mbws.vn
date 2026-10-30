<?php

// Guided tours of the admin (App\Support\AdminTours). Plain text: it is escaped before display.
return [
    'form' => [
        'save' => ['title' => 'Lưu thay đổi', 'description' => 'Nút lưu luôn nằm ở cuối màn hình. Thay đổi chỉ được ghi khi bạn bấm lưu.'],
    ],

    'layout' => [
        'sidebar' => ['title' => 'Menu quản trị', 'description' => 'Mọi khu vực quản lý nằm ở đây: sản phẩm, đơn hàng, nội dung, khách hàng và cài đặt. Bạn chỉ thấy những mục mình có quyền dùng.'],
        'search' => ['title' => 'Tìm nhanh', 'description' => 'Tìm sản phẩm, đơn hàng, bài viết hay trang quản trị bằng từ khoá, không cần mở từng menu.'],
        'notifications' => ['title' => 'Thông báo', 'description' => 'Đơn hàng mới, cảnh báo hạn mức gói và các việc cần xử lý hiện ở đây.'],
        'help' => ['title' => 'Xem lại hướng dẫn', 'description' => 'Bấm nút này bất cứ lúc nào để xem lại hướng dẫn của trang đang mở.'],
    ],

    'ui' => [
        'start' => 'Hướng dẫn sử dụng trang này',
        'next' => 'Tiếp →',
        'prev' => '← Lùi',
        'done' => 'Hoàn tất',
        'skip' => 'Bỏ qua',
        'progress' => 'Bước {{current}}/{{total}}',
    ],

    'products_index' => [
        'intro' => ['title' => 'Danh sách sản phẩm', 'description' => 'Quản lý toàn bộ sản phẩm bán trên website: giá, tồn kho, biến thể và trạng thái hiển thị.'],
        'create' => ['title' => 'Thêm sản phẩm', 'description' => 'Mở form tạo một sản phẩm mới.'],
        'import' => ['title' => 'Nhập từ file CSV', 'description' => 'Nhập nhiều sản phẩm cùng lúc từ file CSV, theo mẫu chuẩn hoặc mẫu WordPress. Trong hộp nhập có nút tải file mẫu.'],
        'export' => ['title' => 'Xuất ra file', 'description' => 'Tải danh sách sản phẩm ra file CSV, theo bộ lọc đang chọn.'],
        'filters' => ['title' => 'Lọc và tìm kiếm', 'description' => 'Tìm theo tên, SKU hoặc đường dẫn; lọc theo danh mục, thương hiệu và trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều sản phẩm để hiển thị, tạm ẩn hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách', 'description' => 'Mỗi dòng có nút xem, sửa và xoá. Khi không lọc, kéo biểu tượng ở đầu dòng để đổi thứ tự hiển thị.'],
    ],

    'products_form' => [
        'intro' => ['title' => 'Thông tin sản phẩm', 'description' => 'Điền thông tin từ trên xuống. Chỉ tên và giá bán là bắt buộc, phần còn lại có thể bổ sung sau.'],
        'languages' => ['title' => 'Nội dung theo ngôn ngữ', 'description' => 'Mỗi tab là một ngôn ngữ của website. Tên ở ngôn ngữ mặc định là bắt buộc; đường dẫn để trống sẽ tự tạo từ tên.'],
        'description' => ['title' => 'Mô tả sản phẩm', 'description' => 'Soạn mô tả chi tiết bằng trình soạn thảo. Nút Phóng to mở trình soạn thảo toàn màn hình.'],
        'seo' => ['title' => 'SEO', 'description' => 'Điểm SEO, từ khóa chính và các tiêu chí cần sửa; xem trước Google, nội dung khi chia sẻ, canonical và cho phép Google index.'],
        'media' => ['title' => 'Thư viện ảnh', 'description' => 'Thêm nhiều ảnh phụ để hiển thị trong thư viện ảnh của trang chi tiết sản phẩm.'],
        'variants' => ['title' => 'Biến thể và SKU', 'description' => 'Bật Có biến thể rồi khai báo nhóm như Màu sắc, Kích thước. Khi lưu, các tổ hợp SKU còn thiếu được tạo tự động.'],
        'pricing' => ['title' => 'Giá bán', 'description' => 'Giá bán, giá so sánh (gạch ngang) và giá vốn. Giá khách trả sau khuyến mãi luôn do hệ thống tính.'],
        'side' => ['title' => 'Ảnh đại diện và trạng thái', 'description' => 'Chọn ảnh đại diện của sản phẩm và đặt hiển thị hay tạm ẩn trên website.'],
        'details' => ['title' => 'Phân loại và tồn kho', 'description' => 'Danh mục, thương hiệu, mã SKU, số lượng tồn, bật quản lý tồn kho và đánh dấu sản phẩm nổi bật.'],
    ],

    'products_show' => [
        'intro' => ['title' => 'Chi tiết sản phẩm', 'description' => 'Tổng quan một sản phẩm và nơi quản lý các SKU biến thể của nó.'],
        'actions' => ['title' => 'Quay lại và sửa', 'description' => 'Về danh sách sản phẩm hoặc mở form sửa sản phẩm.'],
        'summary' => ['title' => 'Ảnh và trạng thái', 'description' => 'Ảnh đại diện, trạng thái hiển thị, nhãn nổi bật và mô tả ngắn.'],
        'stats' => ['title' => 'Giá, tồn kho, biến thể', 'description' => 'Giá bán, tổng tồn kho (cộng theo SKU nếu có biến thể) và số biến thể.'],
        'details' => ['title' => 'Thông tin phân loại', 'description' => 'Danh mục, thương hiệu, SKU và ngày đăng.'],
        'description' => ['title' => 'Mô tả', 'description' => 'Mô tả chi tiết đang hiển thị trên website.'],
        'options' => ['title' => 'Thuộc tính', 'description' => 'Khai báo nhóm thuộc tính như Màu sắc, Kích thước và các giá trị của chúng.'],
        'generate' => ['title' => 'Tạo tổ hợp', 'description' => 'Tạo tự động mọi SKU còn thiếu từ các giá trị thuộc tính. SKU đã có được giữ nguyên.'],
        'create_variant' => ['title' => 'Thêm SKU', 'description' => 'Tạo tay một SKU cho một tổ hợp thuộc tính cụ thể.'],
        'variants' => ['title' => 'Danh sách SKU', 'description' => 'Giá, tồn kho và trạng thái của từng SKU, với nút sửa và xoá ở mỗi dòng.'],
    ],

    'products_options' => [
        'intro' => ['title' => 'Thuộc tính biến thể', 'description' => 'Khai báo các nhóm thuộc tính và giá trị trước, sau đó tạo các tổ hợp SKU ở trang sản phẩm.'],
        'back' => ['title' => 'Quay lại sản phẩm', 'description' => 'Về trang chi tiết sản phẩm mà không lưu.'],
        'languages' => ['title' => 'Ngôn ngữ', 'description' => 'Chuyển ngôn ngữ để nhập tên nhóm và tên giá trị cho từng ngôn ngữ của website.'],
        'groups' => ['title' => 'Nhóm và giá trị', 'description' => 'Mỗi nhóm có tên, kiểu hiển thị (danh sách, màu, ảnh) và các giá trị. Không xoá được giá trị đang có SKU dùng; hãy tắt nó thay vì xoá.'],
        'add_group' => ['title' => 'Thêm nhóm', 'description' => 'Thêm một nhóm thuộc tính mới, ví dụ Kích thước hoặc Dung lượng.'],
    ],

    'variants_form' => [
        'intro' => ['title' => 'SKU biến thể', 'description' => 'Một SKU là một tổ hợp thuộc tính cụ thể của sản phẩm, có giá và tồn kho riêng.'],
        'no_options' => ['title' => 'Chưa có thuộc tính', 'description' => 'Sản phẩm cần có thuộc tính trước khi tạo SKU. Bấm Cấu hình thuộc tính để khai báo.'],
        'combination' => ['title' => 'Tổ hợp thuộc tính', 'description' => 'Chọn một giá trị cho mỗi nhóm. Hệ thống không cho tạo hai SKU trùng tổ hợp.'],
        'name' => ['title' => 'Tên hiển thị', 'description' => 'Tên riêng theo từng ngôn ngữ, không bắt buộc. Để trống để tự lấy từ tổ hợp.'],
        'sku' => ['title' => 'Mã SKU', 'description' => 'Mã riêng của biến thể, bắt buộc. Bên cạnh có ô mã vạch.'],
        'price' => ['title' => 'Giá của SKU', 'description' => 'Để trống giá bán để dùng giá của sản phẩm. Có thêm giá niêm yết và giá vốn.'],
        'stock' => ['title' => 'Tồn kho và khối lượng', 'description' => 'Số lượng tồn của riêng SKU này và khối lượng tính bằng gram.'],
        'image' => ['title' => 'Ảnh và thứ tự', 'description' => 'Đường dẫn ảnh riêng của SKU và thứ tự hiển thị.'],
        'status' => ['title' => 'Trạng thái', 'description' => 'Tick Đang kinh doanh để bán SKU này. Mỗi sản phẩm chỉ có một SKU mặc định; đánh dấu SKU này sẽ bỏ đánh dấu SKU khác.'],
    ],

    'categories_index' => [
        'intro' => ['title' => 'Danh mục sản phẩm', 'description' => 'Sắp xếp sản phẩm thành nhóm, có thể lồng danh mục con, để khách dễ tìm.'],
        'create' => ['title' => 'Thêm danh mục', 'description' => 'Tạo danh mục mới, có thể đặt làm danh mục con.'],
        'filters' => ['title' => 'Lọc danh mục', 'description' => 'Tìm theo tên, lọc theo danh mục cha, trạng thái và nháp. Khi đang lọc, danh sách hiển thị phẳng và tạm tắt kéo thả.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều danh mục để hiển thị, tạm ẩn hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Kéo để sắp xếp', 'description' => 'Danh mục con nằm dưới danh mục cha. Kéo biểu tượng ở đầu dòng để đổi thứ tự hiển thị trên website.'],
        'row_actions' => ['title' => 'Sửa nhanh, sửa, xoá', 'description' => 'Menu ở mỗi dòng: Sửa nhanh mở hộp sửa ngay trên trang, Sửa mở form đầy đủ.'],
    ],

    'categories_form' => [
        'intro' => ['title' => 'Thông tin danh mục', 'description' => 'Đặt tên, mô tả, danh mục cha và ảnh cho danh mục.'],
        'languages' => ['title' => 'Nội dung theo ngôn ngữ', 'description' => 'Mỗi tab là một ngôn ngữ của website. Tên ở ngôn ngữ mặc định là bắt buộc.'],
        'name' => ['title' => 'Tên và đường dẫn', 'description' => 'Tên danh mục; đường dẫn bên cạnh để trống sẽ tự tạo từ tên.'],
        'description' => ['title' => 'Mô tả', 'description' => 'Đoạn giới thiệu ngắn cho danh mục.'],
        'parent' => ['title' => 'Danh mục cha', 'description' => 'Chọn danh mục cha để biến đây thành danh mục con; để Không có nếu là danh mục gốc.'],
        'image' => ['title' => 'Ảnh danh mục', 'description' => 'Ảnh đại diện của danh mục.'],
        'visible' => ['title' => 'Hiển thị', 'description' => 'Bỏ tick để tạm ẩn danh mục khỏi website.'],
        'draft' => ['title' => 'Lưu nháp', 'description' => 'Đánh dấu nội dung chưa hoàn thiện. Không ảnh hưởng tới việc hiển thị.'],
    ],

    'brands_index' => [
        'intro' => ['title' => 'Thương hiệu', 'description' => 'Các thương hiệu để gắn cho sản phẩm và lọc sản phẩm theo thương hiệu.'],
        'create' => ['title' => 'Thêm thương hiệu', 'description' => 'Tạo một thương hiệu mới.'],
        'filters' => ['title' => 'Tìm thương hiệu', 'description' => 'Tìm theo tên thương hiệu.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều thương hiệu để hiển thị, tạm ẩn hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách thương hiệu', 'description' => 'Trạng thái và số sản phẩm của từng thương hiệu. Kéo biểu tượng ở đầu dòng để đổi thứ tự.'],
        'row_actions' => ['title' => 'Sửa nhanh, sửa, xoá', 'description' => 'Menu ở mỗi dòng: Sửa nhanh mở hộp sửa ngay trên trang, Sửa mở form đầy đủ.'],
    ],

    'brands_form' => [
        'intro' => ['title' => 'Thông tin thương hiệu', 'description' => 'Đặt tên, mô tả và logo cho thương hiệu.'],
        'languages' => ['title' => 'Nội dung theo ngôn ngữ', 'description' => 'Mỗi tab là một ngôn ngữ của website. Tên ở ngôn ngữ mặc định là bắt buộc.'],
        'name' => ['title' => 'Tên và đường dẫn', 'description' => 'Tên thương hiệu; đường dẫn bên cạnh để trống sẽ tự tạo từ tên.'],
        'description' => ['title' => 'Mô tả', 'description' => 'Đoạn giới thiệu ngắn về thương hiệu.'],
        'logo' => ['title' => 'Logo', 'description' => 'Ảnh đại diện của thương hiệu.'],
        'draft' => ['title' => 'Lưu nháp', 'description' => 'Tick Lưu nháp để ẩn thương hiệu khỏi website cho tới khi bỏ tick.'],
    ],

    'reviews_index' => [
        'intro' => ['title' => 'Đánh giá sản phẩm', 'description' => 'Đánh giá khách gửi từ website. Đánh giá mới hiển thị ngay, bạn có thể ẩn, sửa hoặc xoá.'],
        'filters' => ['title' => 'Lọc đánh giá', 'description' => 'Tìm theo tên, email hoặc nội dung; lọc theo sản phẩm, số sao và trạng thái.'],
        'table' => ['title' => 'Danh sách đánh giá', 'description' => 'Sản phẩm, người đánh giá (thành viên hay khách), số sao, nội dung và ngày gửi.'],
        'visibility' => ['title' => 'Hiện hoặc ẩn', 'description' => 'Bật tắt công tắc để hiện hoặc ẩn đánh giá trên website, lưu ngay không cần bấm lưu.'],
        'row_actions' => ['title' => 'Sửa và xoá', 'description' => 'Sửa nội dung đánh giá hoặc xoá hẳn đánh giá.'],
    ],

    'promotions_index' => [
        'intro' => ['title' => 'Khuyến mãi và Flash Sale', 'description' => 'Giảm giá tự động theo sản phẩm hoặc SKU, khách không cần nhập mã.'],
        'create' => ['title' => 'Tạo chương trình', 'description' => 'Tạo một chương trình khuyến mãi hoặc Flash Sale mới.'],
        'filters' => ['title' => 'Lọc chương trình', 'description' => 'Tìm theo tên và lọc theo loại: khuyến mãi tự động hoặc Flash Sale.'],
        'table' => ['title' => 'Danh sách chương trình', 'description' => 'Mức giảm, phạm vi, thời gian, số lượt đã dùng trên tổng suất và trạng thái đang chạy hay đã dừng.'],
        'row_actions' => ['title' => 'Sửa và xoá', 'description' => 'Sửa hoặc xoá chương trình ở mỗi dòng.'],
    ],

    'promotions_form' => [
        'intro' => ['title' => 'Chương trình khuyến mãi', 'description' => 'Giá giảm được áp tự động lên sản phẩm. Mã giảm giá, nếu có, được tính tiếp trên giá đã khuyến mãi.'],
        'languages' => ['title' => 'Tên và mô tả', 'description' => 'Tên và mô tả chương trình theo từng ngôn ngữ. Tên ở ngôn ngữ mặc định là bắt buộc.'],
        'kind' => ['title' => 'Loại chương trình', 'description' => 'Khuyến mãi tự động hoặc Flash Sale, dùng để phân loại và lọc danh sách.'],
        'discount' => ['title' => 'Cách giảm và giá trị', 'description' => 'Giảm theo phần trăm, trừ một số tiền, hoặc chốt một giá cố định cho mỗi SKU. Nhập giá trị ở ô bên cạnh.'],
        'priority' => ['title' => 'Ưu tiên', 'description' => 'Khi nhiều chương trình cùng áp cho một SKU, số ưu tiên lớn hơn thắng; bằng nhau thì lấy giá sau giảm thấp nhất. Bên cạnh là số lượng mua tối thiểu.'],
        'limits' => ['title' => 'Giới hạn suất', 'description' => 'Tổng số sản phẩm được bán với giá khuyến mãi, và suất riêng cho mỗi sản phẩm hoặc SKU đã chọn. Để trống là không giới hạn.'],
        'schedule' => ['title' => 'Thời gian chạy', 'description' => 'Ngày giờ bắt đầu và kết thúc. Để trống bắt đầu là chạy ngay, để trống kết thúc là không hạn.'],
        'scope' => ['title' => 'Phạm vi áp dụng', 'description' => 'Áp cho toàn bộ sản phẩm, hoặc chỉ các sản phẩm và SKU được chọn. Chọn sản phẩm là áp cho mọi SKU của nó.'],
        'active' => ['title' => 'Kích hoạt', 'description' => 'Bỏ tick để tạm dừng chương trình mà không cần xoá.'],
    ],

    'vouchers_index' => [
        'intro' => ['title' => 'Mã giảm giá', 'description' => 'Tạo mã để khách nhập khi thanh toán.'],
        'create' => ['title' => 'Tạo mã', 'description' => 'Đặt mức giảm, điều kiện đơn tối thiểu, số lượt dùng và thời hạn.'],
        'filters' => ['title' => 'Lọc mã', 'description' => 'Tìm theo mã hoặc tên và lọc theo trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều mã để bật, tắt hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách mã', 'description' => 'Mức giảm, đơn tối thiểu, số lượt đã dùng, thời hạn và tình trạng: đang chạy, sắp diễn ra, hết hạn hay hết lượt.'],
        'row_actions' => ['title' => 'Sửa và xoá', 'description' => 'Sửa hoặc xoá mã ở mỗi dòng.'],
    ],

    'vouchers_form' => [
        'intro' => ['title' => 'Thông tin mã giảm giá', 'description' => 'Khai báo mã, mức giảm và điều kiện áp dụng khi khách thanh toán.'],
        'code' => ['title' => 'Mã', 'description' => 'Mã khách nhập khi thanh toán, tự chuyển thành chữ hoa và không được trùng mã khác.'],
        'languages' => ['title' => 'Tên và mô tả', 'description' => 'Tên và mô tả theo từng ngôn ngữ. Tên ở ngôn ngữ mặc định là bắt buộc.'],
        'type' => ['title' => 'Loại và giá trị', 'description' => 'Giảm theo phần trăm (tối đa 100%) hoặc một số tiền cố định, nhập giá trị ở ô bên cạnh.'],
        'min_order' => ['title' => 'Đơn tối thiểu', 'description' => 'Giá trị đơn tối thiểu để dùng mã. Với mã phần trăm, ô bên cạnh giới hạn số tiền giảm tối đa.'],
        'quantity' => ['title' => 'Tổng số lượt', 'description' => 'Số lượt dùng tối đa của mã. Để trống là không giới hạn.'],
        'per_user' => ['title' => 'Lượt mỗi khách', 'description' => 'Số lần một khách được dùng mã, nhận diện theo tài khoản, email hoặc số điện thoại. Để trống là không giới hạn.'],
        'active' => ['title' => 'Kích hoạt', 'description' => 'Tắt để tạm ngưng mã mà không cần xoá.'],
        'schedule' => ['title' => 'Thời hạn', 'description' => 'Ngày giờ bắt đầu và kết thúc. Để trống là không giới hạn thời gian.'],
    ],

    'orders_index' => [
        'intro' => ['title' => 'Đơn hàng', 'description' => 'Mọi đơn đặt từ website và đơn tạo tay đều ở đây, mới nhất ở trên.'],
        'create' => ['title' => 'Tạo đơn hàng', 'description' => 'Tạo đơn thay khách, ví dụ khi khách đặt qua điện thoại.'],
        'search' => ['title' => 'Tìm đơn', 'description' => 'Tìm theo mã đơn, tên, số điện thoại hoặc email của khách.'],
        'filters' => ['title' => 'Lọc đơn', 'description' => 'Lọc theo trạng thái đơn, trạng thái thanh toán và trạng thái giao hàng, rồi bấm nút tìm.'],
        'table' => ['title' => 'Danh sách đơn', 'description' => 'Mỗi dòng cho biết khách, ngày đặt, tổng tiền, thanh toán, giao hàng và trạng thái đơn.'],
        'details' => ['title' => 'Xem chi tiết', 'description' => 'Mở đơn để đổi trạng thái, ghi nhận hoàn tiền, tạo vận đơn hoặc in phiếu.'],
    ],

    'orders_show' => [
        'intro' => ['title' => 'Chi tiết đơn hàng', 'description' => 'Xử lý một đơn từ lúc nhận đến lúc giao xong.'],
        'toolbar' => ['title' => 'Quay lại và in', 'description' => 'Quay về danh sách hoặc in phiếu đơn hàng.'],
        'status' => ['title' => 'Cập nhật trạng thái', 'description' => 'Đổi trạng thái đơn và thanh toán rồi bấm cập nhật. Khách nhận email khi trạng thái đơn thay đổi.'],
        'note' => ['title' => 'Ghi chú nội bộ', 'description' => 'Ghi chú đi kèm lần cập nhật này, lưu vào lịch sử đơn. Khách không thấy.'],
        'refunds' => ['title' => 'Hoàn tiền', 'description' => 'Ghi nhận hoàn toàn phần hoặc một phần theo sản phẩm cho đơn đã thanh toán. Hàng hoàn được cộng lại vào kho.'],
        'history' => ['title' => 'Lịch sử đơn', 'description' => 'Mỗi lần đổi trạng thái: trạng thái mới, ai làm, lúc nào và ghi chú.'],
        'sheet' => ['title' => 'Phiếu đơn hàng', 'description' => 'Thông tin khách, địa chỉ giao, phương thức thanh toán và tình trạng giao hàng. Đây là phần được in.'],
        'ship' => ['title' => 'Tạo vận đơn', 'description' => 'Gửi đơn sang GHTK hoặc GHN để lấy mã vận đơn. Nút chỉ hiện khi đã bật một đơn vị vận chuyển.'],
        'items' => ['title' => 'Sản phẩm và tổng tiền', 'description' => 'Các sản phẩm khách mua, đơn giá, số lượng và thành tiền; bên dưới là giảm giá, phí giao và tổng cộng.'],
    ],

    'orders_create' => [
        'intro' => ['title' => 'Tạo đơn thủ công', 'description' => 'Tạo đơn thay khách, ví dụ khi khách đặt qua điện thoại. Giá sản phẩm do hệ thống tính.'],
        'customer' => ['title' => 'Thông tin khách', 'description' => 'Gõ từ 2 ký tự vào tên, email hoặc số điện thoại để tìm khách cũ và tự điền thông tin.'],
        'address' => ['title' => 'Địa chỉ giao hàng', 'description' => 'Địa chỉ nhận hàng của khách, bắt buộc.'],
        'payment' => ['title' => 'Phương thức thanh toán', 'description' => 'Chỉ hiện các phương thức đang bật trong Cấu hình thanh toán.'],
        'discount' => ['title' => 'Giảm giá thủ công', 'description' => 'Số tiền giảm thêm, áp dụng sau khuyến mãi đang chạy. Không được lớn hơn tiền hàng.'],
        'shipping_fee' => ['title' => 'Phí giao hàng', 'description' => 'Phí giao cộng vào đơn, nhập tay.'],
        'items' => ['title' => 'Sản phẩm', 'description' => 'Mỗi dòng: chọn danh mục (và thương hiệu nếu muốn) để lọc, rồi chọn sản phẩm, biến thể và số lượng.'],
        'add_item' => ['title' => 'Thêm sản phẩm', 'description' => 'Thêm một dòng sản phẩm. Nút × ở cuối dòng để xoá dòng đó.'],
        'submit' => ['title' => 'Tạo đơn hàng', 'description' => 'Lưu đơn. Hệ thống kiểm tra tồn kho và trừ kho ngay khi tạo.'],
    ],

    'customers_index' => [
        'intro' => ['title' => 'Khách hàng', 'description' => 'Những người đã đặt hàng, gom theo email trên đơn.'],
        'count' => ['title' => 'Tổng số khách', 'description' => 'Số khách đã từng đặt hàng, theo bộ lọc đang chọn.'],
        'export' => ['title' => 'Xuất danh sách', 'description' => 'Tải danh sách khách ra file CSV, theo bộ lọc đang chọn.'],
        'search' => ['title' => 'Tìm khách', 'description' => 'Tìm theo tên, email hoặc số điện thoại.'],
        'table' => ['title' => 'Danh sách khách', 'description' => 'Số đơn, đơn hoàn thành, tổng chi tiêu và lần mua gần nhất của từng khách. Tổng chi tiêu chỉ tính đơn hoàn thành.'],
        'account' => ['title' => 'Tài khoản', 'description' => 'Cho biết khách đã đăng ký tài khoản trên website hay chỉ mua với tư cách khách vãng lai.'],
        'details' => ['title' => 'Hồ sơ khách', 'description' => 'Mở hồ sơ: thông tin liên hệ, số liệu mua hàng và toàn bộ đơn của khách.'],
    ],

    'customers_show' => [
        'intro' => ['title' => 'Hồ sơ khách hàng', 'description' => 'Mọi thứ về một khách, tổng hợp từ các đơn mang email của họ.'],
        'account' => ['title' => 'Tài khoản', 'description' => 'Cho biết khách đã có tài khoản trên website hay chưa.'],
        'contact' => ['title' => 'Thông tin liên hệ', 'description' => 'Email, số điện thoại, ngày đặt đơn đầu tiên và gần nhất.'],
        'metrics' => ['title' => 'Tổng quan mua hàng', 'description' => 'Số đơn, tỉ lệ hoàn thành, tổng chi tiêu và giá trị trung bình mỗi đơn hoàn thành.'],
        'orders' => ['title' => 'Lịch sử đơn hàng', 'description' => 'Tất cả đơn của khách, mới nhất ở trên, kèm trạng thái đơn và thanh toán.'],
        'order_details' => ['title' => 'Mở đơn', 'description' => 'Mở chi tiết một đơn để xử lý.'],
    ],

    'payment_methods_index' => [
        'intro' => ['title' => 'Cấu hình thanh toán', 'description' => 'Chọn những cách khách có thể trả tiền khi đặt hàng.'],
        'create' => ['title' => 'Thêm phương thức', 'description' => 'Thêm một phương thức thanh toán ngoại tuyến của riêng bạn, chỉ gồm tên và hướng dẫn.'],
        'table' => ['title' => 'Danh sách phương thức', 'description' => 'Các phương thức có sẵn và phương thức bạn tự thêm.'],
        'type' => ['title' => 'Phân loại', 'description' => 'Kết nối API là cổng thanh toán trực tuyến; Ngoại tuyến là cách bạn tự thu tiền.'],
        'status' => ['title' => 'Bật hoặc tắt', 'description' => 'Gạt công tắc để khách thấy hay không thấy phương thức này. Chuyển khoản và SePay cần cấu hình đủ trước khi bật.'],
        'actions' => ['title' => 'Cấu hình và sửa', 'description' => 'Cấu hình kết nối cho từng phương thức. Phương thức tự thêm còn có thêm sửa và xoá.'],
    ],

    'payment_methods_form' => [
        'intro' => ['title' => 'Phương thức ngoại tuyến', 'description' => 'Một cách thanh toán bạn tự thu tiền, ví dụ ví điện tử hay chuyển khoản riêng.'],
        'name' => ['title' => 'Tên phương thức', 'description' => 'Tên khách thấy khi chọn cách thanh toán. Bắt buộc.'],
        'description' => ['title' => 'Mô tả và hướng dẫn', 'description' => 'Hướng dẫn khách cách thanh toán, tối đa 1000 ký tự. Bắt buộc.'],
        'submit' => ['title' => 'Lưu', 'description' => 'Lưu phương thức. Phương thức mới tạo ở trạng thái tắt; bật nó ở danh sách.'],
        'back' => ['title' => 'Quay lại', 'description' => 'Về danh sách phương thức mà không lưu.'],
    ],

    'shipping_partners_index' => [
        'intro' => ['title' => 'Đối tác vận chuyển', 'description' => 'Các đơn vị giao hàng của shop: hãng kết nối API và đối tác bạn tự giao.'],
        'create' => ['title' => 'Thêm đối tác tự giao', 'description' => 'Thêm một đối tác tự giao hàng với phí đồng giá.'],
        'table' => ['title' => 'Danh sách đối tác', 'description' => 'Mã, tên, thông tin liên hệ, loại và trạng thái của từng đối tác.'],
        'type' => ['title' => 'Phân loại', 'description' => 'Kết nối API là hãng vận chuyển như GHTK, GHN; Tự giao là đối tác bạn tự quản lý.'],
        'status' => ['title' => 'Bật hoặc tắt', 'description' => 'Gạt công tắc để bật đối tác. Hãng kết nối API cần cấu hình đủ thông tin trước khi bật.'],
        'actions' => ['title' => 'Cấu hình và sửa', 'description' => 'Hãng kết nối API có mục cấu hình kết nối; đối tác tự giao có sửa và xoá.'],
    ],

    'shipping_partners_form' => [
        'intro' => ['title' => 'Đối tác tự giao', 'description' => 'Một đối tác giao hàng bạn tự quản lý, không kết nối API.'],
        'name' => ['title' => 'Tên đối tác', 'description' => 'Tên của đối tác giao hàng. Bắt buộc.'],
        'fee' => ['title' => 'Phí đồng giá', 'description' => 'Phí giao hàng cố định của đối tác, tính bằng VND. Bắt buộc.'],
        'phone' => ['title' => 'Số điện thoại', 'description' => 'Số của tài xế hoặc đường dây nóng, không bắt buộc.'],
        'note' => ['title' => 'Mô tả, ghi chú', 'description' => 'Ghi chú thêm như khu vực giao hoặc thông tin tài khoản. Hiện ở cột thông tin trong danh sách.'],
        'submit' => ['title' => 'Lưu', 'description' => 'Lưu đối tác. Đối tác mới tạo ở trạng thái tắt; bật nó ở danh sách.'],
        'back' => ['title' => 'Quay lại', 'description' => 'Về danh sách đối tác mà không lưu.'],
    ],

    'redirects_index' => [
        'intro' => ['title' => 'Chuyển hướng & lỗi 404', 'description' => 'Link cũ (ví dụ từ website WordPress trước đây) tự chuyển 301 sang trang mới, để Google giữ thứ hạng. Chỉ áp dụng khi link cũ không còn trang nào trả lời.'],
        'tabs' => ['title' => 'Chuyển hướng và Lỗi 404', 'description' => 'Tab Lỗi 404 liệt kê những link khách hoặc Google mở mà không có trang, nhiều lượt nhất ở trên. Bấm "Tạo chuyển hướng" để xử lý từng link.'],
        'add' => ['title' => 'Thêm chuyển hướng', 'description' => 'Nhập link cũ, link mới và kiểu: 301 (chuyển hẳn, giữ thứ hạng), 302/307 (tạm thời) hoặc 410 (đã xoá hẳn).'],
        'import' => ['title' => 'Nhập và xuất CSV', 'description' => 'Nhập nhiều chuyển hướng một lần từ file CSV (link cũ, link mới, mã), hoặc tải danh sách hiện có về máy.'],
        'search' => ['title' => 'Tìm và lọc', 'description' => 'Tìm theo link cũ hoặc link mới; lọc theo nguồn: tạo tay, import WordPress hoặc từ lỗi 404.'],
        'table' => ['title' => 'Danh sách', 'description' => 'Chuyển hướng tạo khi import WordPress tự đi theo trang mới, kể cả khi bạn đổi slug sau này. Cột Lượt cho biết link cũ còn được truy cập bao nhiêu.'],
    ],

    'contact_submissions_index' => [
        'intro' => ['title' => 'Liên hệ', 'description' => 'Tin nhắn khách gửi qua form liên hệ trên website, mới nhất ở trên.'],
        'tabs' => ['title' => 'Hộp thư và Spam', 'description' => 'Tin bị bộ lọc chống spam giữ lại nằm ở tab Spam, không vào hộp thư và không gửi email báo.'],
        'search' => ['title' => 'Tìm liên hệ', 'description' => 'Tìm theo tên, số điện thoại, email hoặc nội dung.'],
        'read_filter' => ['title' => 'Lọc đã đọc', 'description' => 'Chỉ xem tin chưa đọc hoặc đã đọc.'],
        'table' => ['title' => 'Danh sách liên hệ', 'description' => 'Tin chưa đọc in đậm, có chấm xanh. Bấm vào một tin để đọc đủ nội dung, số điện thoại, email và thông tin thêm.'],
        'select_all' => ['title' => 'Chọn nhiều để xoá', 'description' => 'Tick ô ở từng tin, hoặc "Chọn tất cả" cho cả trang, rồi bấm "Xóa đã chọn" trên thanh vừa hiện.'],
        'read' => ['title' => 'Đã đọc / chưa đọc', 'description' => 'Mở một tin là tự đánh dấu đã đọc. Bấm nút này để đánh dấu lại là chưa đọc.'],
        'actions' => ['title' => 'Xoá và gỡ spam', 'description' => 'Xoá tin. Ở tab Spam, bấm "Không phải spam" để đưa tin của khách thật về hộp thư. Nút Gọi, Zalo, Email ở trên để liên hệ lại khách ngay.'],
    ],

    'notifications_index' => [
        'intro' => ['title' => 'Thông báo hệ thống', 'description' => 'Toàn bộ thông báo: đơn hàng mới, đánh giá sản phẩm và thành viên mới đăng ký.'],
        'search' => ['title' => 'Tìm thông báo', 'description' => 'Tìm theo mã đơn, tên khách, số điện thoại, email hoặc nội dung đánh giá.'],
        'type' => ['title' => 'Loại thông báo', 'description' => 'Chỉ xem đơn hàng mới, đánh giá hoặc thành viên mới.'],
        'list' => ['title' => 'Danh sách thông báo', 'description' => 'Mới nhất ở trên. Bấm một thông báo để mở đơn, đánh giá hoặc thành viên đó. Bạn chỉ thấy loại mình có quyền xem.'],
    ],

    'email_design' => [
        'intro' => ['title' => 'Giao diện email', 'description' => 'Chỉnh cách mọi email của shop trông và viết: email đơn hàng gửi khách, đơn mới và liên hệ gửi bạn, đặt lại mật khẩu.'],
        'layout' => ['title' => 'Kiểu bố cục', 'description' => 'Chọn một trong ba bố cục. Cả bộ email dùng chung bố cục này.'],
        'brand' => ['title' => 'Màu và logo', 'description' => 'Màu nhấn tô nút, thanh tiến trình và tổng tiền. Logo mặc định là logo của shop; chọn logo riêng nếu email cần bản khác.'],
        'footer' => ['title' => 'Chân email', 'description' => 'Lời chào, mạng xã hội, chính sách đổi trả và câu hoàn tiền. Địa chỉ và hotline lấy từ Cài đặt chung.'],
        'emails' => ['title' => 'Từng email', 'description' => 'Bật tắt từng email và viết lại tiêu đề, lời mở đầu. Bấm các ô {biến} để chèn tên khách, mã đơn, tổng tiền.'],
        'preview' => ['title' => 'Xem trước', 'description' => 'Email hiện ngay khi bạn sửa, bằng một đơn hàng mẫu. Đổi Máy tính / Điện thoại để xem hai khổ.'],
    ],
    'notification_settings' => [
        'intro' => ['title' => 'Cấu hình thông báo', 'description' => 'Chọn kênh báo cho bạn khi có đơn mới. Trang tự lưu khi bạn đổi một ô hoặc gạt công tắc, không có nút lưu.'],
        'zalo_oa' => ['title' => 'Zalo Official Account', 'description' => 'Nơi lưu thông tin kết nối Zalo OA: App ID, Template ID, Secret Key và token.'],
        'zalo_personal' => ['title' => 'Zalo cá nhân', 'description' => 'Gửi tin báo đơn mới tới Zalo của bạn qua Zalo Bot. Cần Bot Token và Chat ID trước khi bật.'],
        'chat_id' => ['title' => 'Lấy Chat ID', 'description' => 'Nhập Bot Token rồi bấm nút này để chọn tài khoản Zalo nhận thông báo.'],
        'smtp' => ['title' => 'Email (SMTP)', 'description' => 'Bật để gửi email báo đơn mới tới email nhận thông báo. Cần tài khoản, mật khẩu ứng dụng và email nhận.'],
        'mail_source' => ['title' => 'Nguồn gửi email', 'description' => 'Cho biết email của website đang được gửi bằng cấu hình nào.'],
        'smtp_advanced' => ['title' => 'Cấu hình nâng cao', 'description' => 'Mở để đổi máy chủ SMTP, cổng, mã hoá và tên, địa chỉ người gửi.'],
        'dashboard' => ['title' => 'Thông báo trên trang quản trị', 'description' => 'Bật tắt thông báo trong trang quản trị, âm thanh và tự làm mới khi có đơn mới. Nút loa để nghe thử.'],
    ],

    'posts_index' => [
        'intro' => ['title' => 'Bài viết', 'description' => 'Tin tức và bài blog của website. Từ đây bạn viết bài mới, nhập bài từ WordPress và quản lý các bài đã có.'],
        'create' => ['title' => 'Viết bài mới', 'description' => 'Mở trang soạn bài: tiêu đề, nội dung, ảnh đại diện, chuyên mục và SEO.'],
        'ai_draft' => ['title' => 'Soạn nháp bằng AI', 'description' => 'Đưa ý chính, AI viết bản nháp để bạn sửa và đăng.'],
        'import_wordpress' => ['title' => 'Nhập từ WordPress', 'description' => 'Tải file XML xuất từ WordPress (tối đa 20MB). Ảnh trong bài được tải về thư viện Media; bài trùng slug có thể bỏ qua hoặc cập nhật.'],
        'filters' => ['title' => 'Lọc bài viết', 'description' => 'Tìm theo tiêu đề hoặc slug, lọc theo chuyên mục và trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều bài để hiển thị, tạm ẩn hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách bài viết', 'description' => 'Mỗi dòng có chuyên mục, ngày đăng, điểm SEO và trạng thái. Bấm tiêu đề hoặc nút sửa để mở bài, nút thùng rác để xoá.'],
    ],

    'posts_form' => [
        'intro' => ['title' => 'Soạn bài viết', 'description' => 'Cột trái là nội dung và SEO, cột phải là ảnh, trạng thái, chuyên mục và điểm SEO.'],
        'general' => ['title' => 'Nội dung theo ngôn ngữ', 'description' => 'Mỗi tab là một ngôn ngữ, với tiêu đề, slug và tóm tắt riêng. Ngôn ngữ chính bắt buộc có tiêu đề và nội dung.'],
        'editor' => ['title' => 'Trình soạn nội dung', 'description' => 'Soạn bài với tiêu đề, chữ đậm, danh sách, liên kết và ảnh. Có thể dán từ website khác; định dạng lạ sẽ được loại bỏ.'],
        'seo_meta' => ['title' => 'Xem trước trên Google', 'description' => 'Bài viết hiện trên Google ra sao. Bấm Sửa tiêu đề & mô tả SEO để chỉnh cho ngôn ngữ đang xem; tiêu đề nên dưới 60 ký tự.'],
        'seo' => ['title' => 'Cài đặt SEO', 'description' => 'Từ khoá chính, link canonical, cho phép Google index và theo link, kèm bản xem trước kết quả tìm kiếm.'],
        'thumbnail' => ['title' => 'Ảnh đại diện', 'description' => 'Bấm vào khung để chọn ảnh từ thư viện Media hoặc tải ảnh mới lên.'],
        'status' => ['title' => 'Trạng thái', 'description' => 'Chọn hiển thị hoặc tạm ẩn bài trên website.'],
        'category' => ['title' => 'Chuyên mục', 'description' => 'Xếp bài vào một chuyên mục để người đọc dễ tìm. Có thể để trống.'],
        'seo_analysis' => ['title' => 'Phân tích SEO', 'description' => 'Điểm SEO và danh sách tiêu chí cập nhật ngay khi bạn gõ, theo từ khoá chính. Điểm được lưu cùng bài.'],
    ],

    'post_categories_index' => [
        'intro' => ['title' => 'Chuyên mục bài viết', 'description' => 'Nhóm bài viết theo chủ đề, có thể có chuyên mục con.'],
        'create' => ['title' => 'Thêm chuyên mục', 'description' => 'Tạo chuyên mục mới, có thể đặt dưới một chuyên mục cha.'],
        'search' => ['title' => 'Tìm chuyên mục', 'description' => 'Tìm chuyên mục gốc theo tên hoặc slug; chuyên mục con hiện ngay bên dưới.'],
        'table' => ['title' => 'Kéo để sắp xếp', 'description' => 'Kéo biểu tượng ở đầu dòng để đổi thứ tự. Chuyên mục con chỉ đổi chỗ trong cùng nhóm của nó.'],
        'row_actions' => ['title' => 'Thao tác từng dòng', 'description' => 'Mở menu này để sửa nhanh trong cửa sổ nhỏ, sửa chi tiết hoặc xoá chuyên mục.'],
    ],

    'post_categories_form' => [
        'intro' => ['title' => 'Thông tin chuyên mục', 'description' => 'Đặt tên, mô tả và vị trí của chuyên mục trong cây chuyên mục.'],
        'languages' => ['title' => 'Các ngôn ngữ', 'description' => 'Mỗi tab là một ngôn ngữ của website. Tên ở ngôn ngữ chính là bắt buộc.'],
        'content' => ['title' => 'Tên, slug và mô tả', 'description' => 'Slug để trống sẽ tự tạo từ tên. Ở tab ngôn ngữ phụ, nút Dịch (nếu được cấp quyền) điền sẵn bản dịch từ ngôn ngữ chính.'],
        'parent' => ['title' => 'Chuyên mục cha', 'description' => 'Chọn chuyên mục cha để biến đây thành chuyên mục con, hoặc để trống.'],
        'draft' => ['title' => 'Lưu dạng nháp', 'description' => 'Tick ô này để tạm ẩn chuyên mục trên website.'],
    ],

    'pages_index' => [
        'intro' => ['title' => 'Trang chính sách', 'description' => 'Các trang như đổi trả, bảo mật, vận chuyển, thanh toán. Mỗi trang có địa chỉ /chinh-sach/slug trên website.'],
        'create' => ['title' => 'Tạo trang', 'description' => 'Tạo một trang chính sách mới với tiêu đề, nội dung và SEO.'],
        'filters' => ['title' => 'Lọc trang', 'description' => 'Tìm theo tiêu đề hoặc slug, lọc theo đã xuất bản hay bản nháp.'],
        'table' => ['title' => 'Danh sách trang', 'description' => 'Mỗi dòng có nút chỉnh sửa và xoá. Trang đã xoá được chuyển vào thùng rác.'],
        'view' => ['title' => 'Xem và sửa trên website', 'description' => 'Mở trang đã xuất bản trên website. Khi có quyền, bấm Sửa trực tiếp trên thanh quản trị để sửa chữ ngay tại trang rồi bấm Lưu.'],
    ],

    'pages_form' => [
        'intro' => ['title' => 'Soạn trang chính sách', 'description' => 'Nhập tiêu đề, nội dung và SEO cho từng ngôn ngữ. Nội dung cũng có thể sửa trực tiếp trên website bằng nút Sửa trực tiếp.'],
        'toolbar' => ['title' => 'Lối tắt', 'description' => 'Quay lại danh sách, xem trang trên website (khi đã xuất bản) hoặc lưu ngay.'],
        'languages' => ['title' => 'Các ngôn ngữ', 'description' => 'Mỗi tab là một ngôn ngữ. Tiêu đề ở ngôn ngữ chính là bắt buộc.'],
        'content' => ['title' => 'Nội dung', 'description' => 'Soạn nội dung trang. Nếu không chạm vào ô này, nội dung đã lưu được giữ nguyên khi bạn lưu tiêu đề hay SEO.'],
        'seo' => ['title' => 'SEO', 'description' => 'Điểm SEO, từ khóa chính và các tiêu chí cần sửa; xem trước Google, nội dung khi chia sẻ, canonical và cho phép Google index.'],
        'status' => ['title' => 'Trạng thái', 'description' => 'Bản nháp chưa hiện trên website; chọn Xuất bản để khách xem được.'],
        'save' => ['title' => 'Lưu trang', 'description' => 'Bấm để lưu mọi thay đổi. Thay đổi chỉ được ghi khi bạn bấm lưu.'],
        'revisions' => ['title' => 'Phiên bản gần đây', 'description' => 'Các bản đã lưu trước đây của trang, tối đa 20 bản gần nhất. Bấm Khôi phục để đưa trang về bản đó.'],
    ],

    'partners_index' => [
        'intro' => ['title' => 'Đối tác', 'description' => 'Logo khách hàng và đối tác hiện ở các dải logo trên website. Đây là nơi duy nhất sửa các logo này.'],
        'create' => ['title' => 'Thêm đối tác', 'description' => 'Chọn logo, nhập tên và chọn nhóm để đưa một logo mới lên website.'],
        'groups' => ['title' => 'Nhóm đối tác', 'description' => 'Mỗi nhóm là một dải logo trên website. Tạo, đổi tên hoặc xoá nhóm ở đây.'],
        'tabs' => ['title' => 'Xem theo nhóm', 'description' => 'Bấm vào một nhóm để chỉ xem logo của nhóm đó. Trong một nhóm, kéo biểu tượng ở đầu dòng để đổi thứ tự hiển thị.'],
        'filters' => ['title' => 'Tìm và lọc', 'description' => 'Tìm theo tên đối tác, lọc logo đang hiện hoặc đang ẩn.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều đối tác để hiện, ẩn hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách đối tác', 'description' => 'Logo, tên, nhóm và trạng thái của từng đối tác. Công tắc ở cột trạng thái ẩn hoặc hiện logo ngay.'],
    ],

    'partners_form' => [
        'intro' => ['title' => 'Thông tin đối tác', 'description' => 'Một đối tác là một logo trong dải logo của website.'],
        'logo' => ['title' => 'Logo', 'description' => 'Bấm để chọn ảnh từ Thư viện tệp hoặc tải ảnh mới. Ảnh PNG nền trong suốt hiển thị đẹp nhất.'],
        'name' => ['title' => 'Tên đối tác', 'description' => 'Tên hiện khi ảnh không tải được và giúp Google hiểu ảnh.'],
        'group' => ['title' => 'Nhóm', 'description' => 'Chọn dải logo mà đối tác này thuộc về.'],
        'url' => ['title' => 'Đường liên kết', 'description' => 'Không bắt buộc. Có địa chỉ thì khách bấm vào logo sẽ mở trang của đối tác.'],
        'status' => ['title' => 'Hiện trên website', 'description' => 'Tắt để tạm ẩn logo mà không phải xoá.'],
    ],

    'partner_groups_index' => [
        'intro' => ['title' => 'Nhóm đối tác', 'description' => 'Mỗi nhóm là một dải logo trên website, ví dụ Khách hàng tiêu biểu hay Đối tác chiến lược.'],
        'create' => ['title' => 'Thêm nhóm', 'description' => 'Tạo một nhóm mới. Giao diện website cần biết mã nhóm để hiện dải logo đó.'],
        'table' => ['title' => 'Danh sách nhóm', 'description' => 'Tên, mã và số đối tác của từng nhóm. Nhóm còn đối tác thì không xoá được.'],
    ],

    'partner_groups_form' => [
        'intro' => ['title' => 'Thông tin nhóm', 'description' => 'Đặt tên để bạn dễ nhận ra nhóm trong trang quản trị.'],
        'name' => ['title' => 'Tên nhóm', 'description' => 'Chỉ hiện trong trang quản trị.'],
        'key' => ['title' => 'Mã nhóm', 'description' => 'Giao diện website dùng mã này để lấy logo. Để trống thì tạo tự động từ tên. Sau khi lưu không đổi được.'],
    ],

    'banners_index' => [
        'intro' => ['title' => 'Banner', 'description' => 'Ảnh quảng cáo hiển thị ở các vị trí trên website, như slider trang chủ.'],
        'create' => ['title' => 'Thêm banner', 'description' => 'Tải ảnh banner lên và chọn vị trí hiển thị.'],
        'filters' => ['title' => 'Lọc banner', 'description' => 'Lọc theo vị trí hiển thị và trạng thái.'],
        'select_all' => ['title' => 'Thao tác hàng loạt', 'description' => 'Tick chọn nhiều banner để hiển thị, tạm ẩn hoặc xoá cùng lúc.'],
        'table' => ['title' => 'Danh sách banner', 'description' => 'Ảnh, liên kết, vị trí và thứ tự của từng banner, xếp theo thứ tự tăng dần. Mỗi dòng có nút sửa và xoá.'],
    ],

    'banners_form' => [
        'intro' => ['title' => 'Thông tin banner', 'description' => 'Chọn ảnh, gắn liên kết và chọn nơi banner xuất hiện.'],
        'image' => ['title' => 'Ảnh banner', 'description' => 'Chọn ảnh từ thư viện Media hoặc tải ảnh JPG, PNG, WEBP tối đa 2MB. Khi sửa, để trống thì giữ ảnh cũ.'],
        'title' => ['title' => 'Tiêu đề', 'description' => 'Tiêu đề hoặc mô tả ngắn, không bắt buộc.'],
        'link' => ['title' => 'Liên kết', 'description' => 'Trang mà khách được đưa tới khi bấm vào banner, không bắt buộc.'],
        'position' => ['title' => 'Vị trí hiển thị', 'description' => 'Slider trang chủ, cột bên trang chủ hoặc khu khuyến mãi.'],
        'sort_order' => ['title' => 'Thứ tự', 'description' => 'Số nhỏ đứng trước.'],
        'status' => ['title' => 'Bật hoặc tắt', 'description' => 'Tắt để tạm ẩn banner mà không phải xoá.'],
        'save' => ['title' => 'Lưu banner', 'description' => 'Bấm để lưu. Thay đổi chỉ được ghi khi bạn bấm nút này.'],
    ],

    'menus_index' => [
        'intro' => ['title' => 'Menu điều hướng', 'description' => 'Các menu hiển thị trên website, như menu chính hay menu chân trang.'],
        'create' => ['title' => 'Thêm menu', 'description' => 'Tạo một menu mới, sau đó thêm các mục cho nó.'],
        'table' => ['title' => 'Danh sách menu', 'description' => 'Tên, mã menu mà giao diện dùng để gọi, số mục và trạng thái.'],
        'items' => ['title' => 'Quản lý mục menu', 'description' => 'Thêm, sửa và sắp xếp các liên kết bên trong menu.'],
        'edit' => ['title' => 'Sửa menu', 'description' => 'Đổi tên, mã hoặc bật tắt cả menu.'],
    ],

    'menus_form' => [
        'intro' => ['title' => 'Thông tin menu', 'description' => 'Một menu là một nhóm liên kết. Các liên kết được thêm ở trang Quản lý mục menu.'],
        'name' => ['title' => 'Tên menu', 'description' => 'Tên để bạn nhận ra menu trong trang quản trị, ví dụ Menu chính.'],
        'key' => ['title' => 'Mã menu', 'description' => 'Mã mà giao diện dùng để hiển thị đúng menu, ví dụ primary hoặc footer. Chỉ chữ thường, số và dấu gạch ngang.'],
        'active' => ['title' => 'Hiển thị', 'description' => 'Menu đang ẩn sẽ không hiện trên website.'],
        'items' => ['title' => 'Quản lý mục menu', 'description' => 'Đi tới danh sách liên kết của menu này.'],
    ],

    'menu_items_index' => [
        'intro' => ['title' => 'Mục của menu', 'description' => 'Các liên kết trong menu, xếp theo cấp cha và con.'],
        'create' => ['title' => 'Thêm mục', 'description' => 'Thêm liên kết tới trang chính sách, danh mục, chuyên mục hoặc đường dẫn tự nhập.'],
        'tree' => ['title' => 'Cây menu', 'description' => 'Mỗi mục hiện loại liên kết và đường dẫn. Mục có nhãn đỏ là đã mất đối tượng liên kết.'],
        'drag' => ['title' => 'Kéo để sắp xếp', 'description' => 'Kéo biểu tượng này để đổi thứ tự trong cùng một cấp. Muốn đổi mục cha, hãy sửa mục đó.'],
        'edit' => ['title' => 'Sửa và xoá', 'description' => 'Sửa một mục, hoặc xoá mục cùng các mục con bên dưới.'],
    ],

    'menu_items_form' => [
        'intro' => ['title' => 'Mục menu', 'description' => 'Một liên kết trong menu: nhãn, đích đến và vị trí trong cây.'],
        'label' => ['title' => 'Nhãn hiển thị', 'description' => 'Chữ hiện trên menu, nhập cho từng ngôn ngữ. Ngôn ngữ chính là bắt buộc.'],
        'type' => ['title' => 'Liên kết tới', 'description' => 'Chọn loại đích: trang chính sách, danh mục sản phẩm, chuyên mục bài viết hoặc đường dẫn tự nhập.'],
        'parent' => ['title' => 'Mục cha', 'description' => 'Đặt mục này dưới một mục khác để tạo menu con, hoặc để ở cấp cao nhất.'],
        'target' => ['title' => 'Chọn đích đến', 'description' => 'Ô này đổi theo loại liên kết. Đường dẫn tự nhập phải bắt đầu bằng / hoặc là link http/https.'],
        'new_tab' => ['title' => 'Mở ở tab mới', 'description' => 'Bật khi liên kết dẫn ra website khác.'],
        'active' => ['title' => 'Hiển thị', 'description' => 'Tắt để tạm ẩn mục này trên website.'],
    ],

    'media_index' => [
        'intro' => ['title' => 'Thư viện Media', 'description' => 'Nơi lưu ảnh, PDF và video dùng cho sản phẩm, bài viết, banner và trang.'],
        'upload' => ['title' => 'Tải tệp lên', 'description' => 'Kéo thả hoặc chọn nhiều tệp cùng lúc. Ảnh và PDF tối đa 10MB, video MP4 tối đa 50MB. Tệp vào thư mục đang chọn.'],
        'folders' => ['title' => 'Thư mục', 'description' => 'Xem tệp theo thư mục, như posts hay banners, hoặc xem tất cả.'],
        'storage' => ['title' => 'Dung lượng', 'description' => 'Dung lượng lưu trữ đã dùng so với hạn mức, khi có số liệu.'],
        'cloud' => ['title' => 'Nơi lưu trữ', 'description' => 'Cho biết tệp đang lưu trên Cloudinary hay trên máy chủ. Admin có quyền cài đặt có thể kết nối Cloudinary tại đây.'],
        'files' => ['title' => 'Danh sách tệp', 'description' => 'Mỗi tệp có nút copy liên kết và nút xoá.'],
        'select_all' => ['title' => 'Chọn nhiều tệp', 'description' => 'Chọn tất cả hoặc tick từng tệp để xoá cùng lúc.'],
        'view' => ['title' => 'Sắp xếp và kiểu xem', 'description' => 'Sắp xếp theo ngày hoặc tên, chuyển giữa dạng lưới và danh sách.'],
    ],

    'languages_index' => [
        'intro' => ['title' => 'Ngôn ngữ nội dung', 'description' => 'Các ngôn ngữ của website, dùng cho URL, nội dung và dữ liệu API.'],
        'preferences' => ['title' => 'Ưu tiên nội dung', 'description' => 'Chọn ngôn ngữ chính để nhập nội dung và ngôn ngữ dự phòng.'],
        'default' => ['title' => 'Ngôn ngữ mặc định', 'description' => 'Ngôn ngữ nhập nội dung chính; các ô bắt buộc trong form thuộc ngôn ngữ này.'],
        'fallback' => ['title' => 'Ngôn ngữ dự phòng', 'description' => 'Dùng khi bản dịch đang xem bị trống. Phải khác ngôn ngữ mặc định.'],
        'save_preferences' => ['title' => 'Lưu ưu tiên', 'description' => 'Bấm để áp dụng ngôn ngữ mặc định và dự phòng vừa chọn.'],
        'add' => ['title' => 'Thêm ngôn ngữ', 'description' => 'Nhập mã gồm 2 hoặc 3 chữ thường (ví dụ zh), tên tiếng Anh và tên bản địa. Không cần sửa database.'],
        'list' => ['title' => 'Ngôn ngữ hiện có', 'description' => 'Sửa tên, thứ tự, đường dẫn cờ và bật tắt từng ngôn ngữ. Ngôn ngữ mặc định và dự phòng không tắt được.'],
        'row_save' => ['title' => 'Lưu từng dòng', 'description' => 'Mỗi ngôn ngữ có nút lưu riêng; chỉ dòng đó được cập nhật.'],
    ],

    'dashboard' => [
        'intro' => ['title' => 'Chào mừng đến trang quản trị', 'description' => 'Hướng dẫn ngắn này giới thiệu các phần chính. Dùng nút Tiếp/Lùi hoặc phím mũi tên để di chuyển, Bỏ qua để đóng.'],
        'welcome' => ['title' => 'Lối tắt', 'description' => 'Đi nhanh tới các khu vực dùng nhiều nhất: sản phẩm, đơn hàng và khách hàng.'],
        'package' => ['title' => 'Hạn mức gói', 'description' => 'Mức đã dùng so với hạn mức của gói dịch vụ, như sản phẩm và bài viết, banner. Bấm Làm mới để cập nhật số liệu.'],
        'analytics' => ['title' => 'Lượt truy cập website', 'description' => 'Số liệu Google Analytics theo khoảng thời gian bạn chọn ở góc phải. Cần nhập Property ID trong Cài đặt, tab Google Analytics.'],
        'stats' => ['title' => 'Số liệu bán hàng', 'description' => 'Doanh thu tháng này từ đơn hoàn thành, và số đơn đang chờ, đang xử lý, đã hoàn thành.'],
        'charts' => ['title' => 'Biểu đồ', 'description' => 'Doanh thu và số đơn từng ngày trong tuần này, cùng tỉ lệ đơn theo trạng thái.'],
        'recent_orders' => ['title' => 'Đơn hàng gần đây', 'description' => 'Những đơn mới nhất. Bấm "Xem tất cả" để mở danh sách đơn hàng.'],
    ],

    'settings' => [
        'intro' => ['title' => 'Cài đặt website', 'description' => 'Thông tin chung của cửa hàng: tên, logo, liên hệ, mã nhúng và kết nối Google.'],
        'tabs' => ['title' => 'Các nhóm cài đặt', 'description' => 'Cài đặt chia thành nhiều tab. Bấm một tab để mở nhóm đó.'],
        'general' => ['title' => 'Thông tin chung', 'description' => 'Tên website (bắt buộc), logo, favicon, cùng tiêu đề và mô tả SEO mặc định hiển thị trên Google.'],
        'tab_contact' => ['title' => 'Nút liên hệ', 'description' => 'Điện thoại, email, địa chỉ và bản đồ của trụ sở, các chi nhánh khác và link mạng xã hội.'],
        'tab_multilingual' => ['title' => 'Đa ngôn ngữ', 'description' => 'Bật đa ngôn ngữ và chọn cách dịch: thủ công theo nội dung hoặc widget GTranslate. Chỉ superadmin thấy tab này.'],
        'tab_embed' => ['title' => 'Mã nhúng', 'description' => 'Mã chèn vào phần header và footer của website, ví dụ mã theo dõi. Chỉ superadmin sửa được.'],
        'tab_analytics' => ['title' => 'Google Analytics', 'description' => 'Nhập Measurement ID, Property ID và địa chỉ Search Console để xem lượt truy cập ở trang tổng quan và dùng Việc SEO tuần này.'],
        'save' => ['title' => 'Lưu cài đặt', 'description' => 'Một nút lưu chung cho mọi tab. Bấm để áp dụng thay đổi cho website.'],
    ],

    'users_index' => [
        'intro' => ['title' => 'Tài khoản quản trị', 'description' => 'Tài khoản của nhân viên đăng nhập trang quản trị. Khách mua hàng nằm ở mục Khách hàng.'],
        'create' => ['title' => 'Thêm tài khoản', 'description' => 'Tạo tài khoản cho nhân viên và chọn vai trò cho họ.'],
        'filters' => ['title' => 'Tìm và lọc', 'description' => 'Tìm theo tên hoặc email, lọc theo vai trò và trạng thái hoạt động hay tạm khoá.'],
        'table' => ['title' => 'Danh sách tài khoản', 'description' => 'Vai trò, trạng thái và lần đăng nhập gần nhất của từng tài khoản.'],
        'row_actions' => ['title' => 'Thao tác', 'description' => 'Khoá hoặc mở khoá, sửa và xoá tài khoản. Đăng nhập nhanh vào tài khoản đó chỉ dành cho superadmin.'],
    ],

    'users_form' => [
        'intro' => ['title' => 'Thông tin tài khoản', 'description' => 'Tạo hoặc sửa tài khoản nhân viên. Mục có dấu * là bắt buộc.'],
        'account' => ['title' => 'Tên và email', 'description' => 'Họ tên và email dùng để đăng nhập. Mỗi email chỉ dùng cho một tài khoản.'],
        'password' => ['title' => 'Mật khẩu', 'description' => 'Tối thiểu 12 ký tự, có chữ hoa, chữ thường, số và ký tự đặc biệt. Khi sửa, để trống nếu không muốn đổi.'],
        'avatar' => ['title' => 'Ảnh đại diện', 'description' => 'Bấm vào ảnh tròn để chọn ảnh, tối đa 2MB.'],
        'role' => ['title' => 'Vai trò', 'description' => 'Vai trò quyết định tài khoản được dùng những chức năng nào. Vai trò được quản lý ở mục Vai trò.'],
        'status' => ['title' => 'Kích hoạt', 'description' => 'Bỏ tick thì tài khoản tạm thời không đăng nhập được.'],
        'overrides' => ['title' => 'Quyền riêng', 'description' => 'Ngoại lệ so với vai trò: Cấp thêm một quyền vai trò không có, hoặc Thu hồi một quyền vai trò đang có. Để Theo vai trò nếu không cần.'],
    ],

    'roles_index' => [
        'intro' => ['title' => 'Vai trò và phân quyền', 'description' => 'Mỗi vai trò là một bộ quyền. Gán vai trò cho tài khoản để quyết định họ được làm gì.'],
        'create' => ['title' => 'Thêm vai trò', 'description' => 'Tạo vai trò mới và chọn các quyền cho nó.'],
        'table' => ['title' => 'Danh sách vai trò', 'description' => 'Tên vai trò và số tài khoản đang dùng vai trò đó.'],
        'permissions' => ['title' => 'Quyền của vai trò', 'description' => 'Các quyền vai trò đang có, gom theo từng chức năng.'],
        'row_actions' => ['title' => 'Sửa và xoá', 'description' => 'Sửa hoặc xoá vai trò. Không xoá được vai trò đang có tài khoản dùng; vai trò hệ thống không có nút này.'],
    ],

    'roles_form' => [
        'intro' => ['title' => 'Thiết lập vai trò', 'description' => 'Đặt tên vai trò rồi bật những quyền nó được dùng.'],
        'name' => ['title' => 'Tên vai trò', 'description' => 'Ví dụ: Nhân viên bán hàng. Tên không được trùng với vai trò khác.'],
        'permissions' => ['title' => 'Chọn quyền', 'description' => 'Mỗi dòng là một chức năng với các quyền Xem, Thêm, Sửa, Xoá, Dùng. Bạn chỉ cấp được quyền mà chính mình đang có.'],
        'group_toggle' => ['title' => 'Chọn cả nhóm', 'description' => 'Bật để chọn mọi quyền trong nhóm, tắt để bỏ hết.'],
        'other_actions' => ['title' => 'Quyền khác', 'description' => 'Quyền nằm ngoài 5 cột, ví dụ áp dụng gợi ý của AI.'],
    ],

    'activity_logs' => [
        'intro' => ['title' => 'Nhật ký hoạt động', 'description' => 'Ghi lại thao tác của các tài khoản quản trị: ai làm gì, lúc nào.'],
        'delete_all' => ['title' => 'Xoá nhật ký', 'description' => 'Xoá mọi bản ghi khớp bộ lọc đang chọn, hoặc toàn bộ khi chưa lọc. Không khôi phục được; chỉ superadmin.'],
        'filters' => ['title' => 'Lọc nhật ký', 'description' => 'Lọc theo người thực hiện, loại thao tác và đối tượng bị tác động.'],
        'dates' => ['title' => 'Khoảng ngày', 'description' => 'Chọn từ ngày và đến ngày rồi bấm Lọc.'],
        'select_all' => ['title' => 'Chọn nhiều bản ghi', 'description' => 'Tick để chọn các bản ghi trên trang rồi xoá cùng lúc. Chỉ superadmin.'],
        'table' => ['title' => 'Danh sách hoạt động', 'description' => 'Thời gian, người thực hiện, thao tác, đối tượng và mô tả của từng hoạt động.'],
        'changes' => ['title' => 'Thay đổi', 'description' => 'Các giá trị đã đổi trong thao tác đó, nếu có ghi lại.'],
    ],

    'features' => [
        'intro' => ['title' => 'Cấu hình tính năng', 'description' => 'Bật hoặc tắt từng tính năng của website và xem hạn mức của gói dịch vụ. Chỉ superadmin vào được trang này.'],
        'sync' => ['title' => 'Đồng bộ gói', 'description' => 'Lấy lại gói dịch vụ từ tool nội bộ để áp tính năng và hạn mức mới nhất.'],
        'package' => ['title' => 'Gói dịch vụ', 'description' => 'Gói đang liên kết và lần đồng bộ gần nhất. Khi chưa liên kết gói, tính năng được bật tắt tay.'],
        'limits' => ['title' => 'Hạn mức', 'description' => 'Mức đã dùng so với hạn mức của gói. Chỉ để xem: muốn nâng hạn mức cần nâng cấp gói.'],
        'tabs' => ['title' => 'Nhóm tính năng', 'description' => 'Tính năng chia làm hai nhóm: website bán hàng và website không bán hàng.'],
        'group_switch' => ['title' => 'Bật tắt cả nhóm', 'description' => 'Bật hoặc tắt mọi tính năng trong nhóm chỉ với một lần bấm.'],
        'card' => ['title' => 'Từng tính năng', 'description' => 'Gạt công tắc để bật hoặc tắt tính năng. Thay đổi được lưu ngay.'],
        'source' => ['title' => 'Theo gói hay ghi đè', 'description' => 'Theo gói: trạng thái lấy từ gói dịch vụ. Ghi đè: đã đổi tay; bấm Về theo gói để trả lại.'],
    ],

    'extensions' => [
        'intro' => ['title' => 'Tiện ích mở rộng', 'description' => 'Các tiện ích trong thư mục extensions/ của website và tình trạng từng cái. Chỉ superadmin vào được trang này.'],
        'stats' => ['title' => 'Tổng quan', 'description' => 'Tổng số tiện ích, số đang chạy, số cần xử lý và số đang tắt.'],
        'list' => ['title' => 'Danh sách tiện ích', 'description' => 'Tên, phiên bản, phiên bản core yêu cầu và trạng thái. Tiện ích lỗi có kèm lý do hoặc mã tra cứu.'],
        'core' => ['title' => 'Phiên bản core', 'description' => 'Phiên bản hệ thống đang chạy, dùng để kiểm tra tiện ích có tương thích không.'],
        'about' => ['title' => 'Giới thiệu', 'description' => 'Trang này chỉ để xem; không bật tắt tiện ích tại đây.'],
        'commands' => ['title' => 'Lệnh quản lý', 'description' => 'Bật, tắt hay kiểm tra tiện ích bằng lệnh php artisan trên máy chủ, kể cả khi tiện ích làm hỏng trang quản trị.'],
    ],

    'core_update' => [
        'intro' => ['title' => 'Cập nhật phiên bản', 'description' => 'Xem phiên bản đang chạy và cài bản mới do Mắt Bão phát hành.'],
        'check' => ['title' => 'Kiểm tra cập nhật', 'description' => 'Hỏi máy chủ cập nhật xem có bản mới cho website không.'],
        'current' => ['title' => 'Phiên bản đang chạy', 'description' => 'Số phiên bản hiện tại và tình trạng file hệ thống. File hệ thống bị sửa tay sẽ chặn cập nhật.'],
        'offer' => ['title' => 'Bản cập nhật', 'description' => 'Bản mới kèm ghi chú phát hành và nút cập nhật. Website tạm bảo trì khoảng 1–2 phút khi cài; nếu lỗi sẽ tự quay về bản đang chạy.'],
        'rollback' => ['title' => 'Quay lại bản trước', 'description' => 'Trả code về bản trước lần cập nhật gần nhất, dữ liệu giữ nguyên. Chỉ superadmin.'],
        'access' => ['title' => 'Quyền tự cập nhật', 'description' => 'Cho phép hoặc không cho admin của website tự cập nhật. Chỉ superadmin thấy công tắc này.'],
        'history' => ['title' => 'Lịch sử', 'description' => 'Các lần cài, lỗi và quay lại gần đây.'],
    ],

    'ai_bulk_index' => [
        'intro' => ['title' => 'SEO hàng loạt', 'description' => 'AI xử lý nhiều nội dung một lần. Kết quả chờ bạn duyệt, không tự lưu.'],
        'locale' => ['title' => 'Ngôn ngữ', 'description' => 'Chọn ngôn ngữ của nội dung cần xử lý. Chỉ hiện khi website có nhiều ngôn ngữ.'],
        'kind_meta' => ['title' => 'Tiêu đề và mô tả SEO', 'description' => 'AI soạn tiêu đề và mô tả SEO cho sản phẩm, bài viết, trang chính sách đang để trống. Tối đa 100 nội dung mỗi lần.'],
        'kind_seo_fix' => ['title' => 'Sửa theo điểm SEO', 'description' => 'Bài viết có từ khoá chính nhưng trượt quy tắc về tiêu đề, mô tả hoặc đoạn mở đầu.'],
        'kind_alt' => ['title' => 'Ảnh thiếu alt', 'description' => 'AI nhìn ảnh và viết mô tả cho các ảnh trong nội dung đang thiếu alt. Tối đa 20 ảnh mỗi lần.'],
        'estimate' => ['title' => 'Ước tính rồi chạy', 'description' => 'Bấm Ước tính lượt AI để xem số lượt cần dùng. Nếu còn đủ lượt, nút Chạy AI sẽ hiện ra.'],
        'history' => ['title' => 'Các lần chạy gần đây', 'description' => 'Bấm Xem ở một lần chạy để duyệt và áp dụng kết quả.'],
    ],

    'ai_bulk_show' => [
        'intro' => ['title' => 'Duyệt kết quả AI', 'description' => 'Kết quả của một lần chạy SEO hàng loạt. Sửa nếu cần rồi áp dụng.'],
        'summary' => ['title' => 'Thông tin lần chạy', 'description' => 'Loại việc, trạng thái, số nội dung và số lượt AI ước tính.'],
        'running' => ['title' => 'Đang chạy', 'description' => 'AI chạy nền. Tải lại trang sau ít phút để xem kết quả.'],
        'select_all' => ['title' => 'Chọn tất cả', 'description' => 'Mọi mục được chọn sẵn. Bỏ tick ở đây để bỏ chọn hết.'],
        'item' => ['title' => 'Từng kết quả', 'description' => 'Sửa chữ ngay trong ô nếu cần, bỏ tick mục không muốn dùng.'],
        'apply' => ['title' => 'Áp dụng', 'description' => 'Ghi các mục đã chọn vào nội dung. Nội dung bị sửa sau khi chạy được giữ nguyên.'],
    ],

    'ai_post_draft' => [
        'intro' => ['title' => 'Viết bài bằng AI', 'description' => 'AI viết bản nháp từ thông tin bạn đưa và lưu ở trạng thái chưa đăng. Bạn đọc lại, sửa rồi tự đăng.'],
        'notice' => ['title' => 'Lưu ý', 'description' => 'AI không bịa giá, số liệu hay cam kết; chỗ thiếu thông tin được đánh dấu để bạn bổ sung.'],
        'tab_brief' => ['title' => 'Viết từ thông tin doanh nghiệp', 'description' => 'Điền chủ đề, người đọc và các ý chính để AI viết thành bài (4 lượt AI).'],
        'keyword' => ['title' => 'Từ khoá chính', 'description' => '2–5 từ người ta hay gõ trên Google, ví dụ: ống nhựa uPVC. Không phải một câu.'],
        'business' => ['title' => 'Thông tin doanh nghiệp', 'description' => 'Bạn là ai, bán gì, ở đâu, điểm khác biệt. Được nhớ cho lần viết sau.'],
        'key_points' => ['title' => 'Ý chính', 'description' => 'Mỗi ý một dòng, chỉ những gì bạn chắc chắn đúng.'],
        'style' => ['title' => 'Giọng văn và độ dài', 'description' => 'Chọn giọng văn và số chữ mong muốn của bài.'],
        'submit' => ['title' => 'Viết bản nháp', 'description' => 'AI viết trong khoảng 30–50 giây rồi mở bài nháp trong trang sửa bài viết.'],
        'tab_notes' => ['title' => 'Viết từ ghi chú', 'description' => 'Dán ghi chú, gạch đầu dòng hoặc bản ghi lời nói để AI viết thành bài (3 lượt AI cho mỗi 1.500 chữ).'],
    ],

    'ai_research' => [
        'intro' => ['title' => 'Từ khoá và đối thủ', 'description' => 'Xem đối thủ lên Google với từ khoá nào mà website mình chưa có, chọn chủ đề để viết và theo dõi thứ hạng mỗi tuần.'],
        'competitors' => ['title' => 'Đối thủ', 'description' => 'Các website đối thủ và tình trạng lấy dữ liệu của từng website.'],
        'suggest' => ['title' => 'Gợi ý đối thủ', 'description' => 'Tìm các website đang lên Google cùng từ khoá với bạn.'],
        'add_competitor' => ['title' => 'Thêm đối thủ', 'description' => 'Nhập tên miền, ví dụ doithu.vn. Danh sách từ khoá của họ có sau khoảng một phút.'],
        'gaps' => ['title' => 'Từ khoá còn thiếu', 'description' => 'Từ khoá đối thủ có mà bạn chưa có, kèm lượt tìm và độ khó. Bấm Theo dõi để theo dõi thứ hạng của từ đó.'],
        'topics' => ['title' => 'Gợi ý chủ đề', 'description' => 'AI gom các từ khoá thành chủ đề bài viết (2 lượt AI).'],
        'ranks' => ['title' => 'Theo dõi thứ hạng', 'description' => 'Vị trí của bạn trên Google cho từng từ khoá, kiểm tra mỗi sáng thứ Hai.'],
        'add_keywords' => ['title' => 'Thêm từ khoá', 'description' => 'Nhập mỗi dòng một từ khoá để đưa vào theo dõi.'],
    ],

    'ai_seo_tasks' => [
        'intro' => ['title' => 'Việc SEO tuần này', 'description' => 'Các trang đáng tối ưu nhất, xếp theo số click có thể tăng thêm, dựa trên Google Search Console.'],
        'setup' => ['title' => 'Kết nối Search Console', 'description' => 'Trang chỉ có dữ liệu khi website đã kết nối Google Search Console.'],
        'connect' => ['title' => 'Mở Cài đặt', 'description' => 'Đi tới tab Google Analytics trong Cài đặt để nhập địa chỉ Search Console.'],
        'stats' => ['title' => 'Tổng quan 28 ngày', 'description' => 'Lượt click, lượt hiển thị, tỷ lệ click và vị trí trung bình, so với 28 ngày trước.'],
        'task' => ['title' => 'Một việc cần làm', 'description' => 'Mỗi thẻ là một trang, kèm loại vấn đề, số liệu và từ khoá người dùng tìm. Bấm Mở trang sửa để sửa nội dung.'],
        'advice' => ['title' => 'AI giải thích', 'description' => 'AI giải thích vấn đề và gợi ý cách sửa, mỗi lần dùng 1 lượt AI.'],
        'mark' => ['title' => 'Đã xong hoặc bỏ qua', 'description' => 'Đã xong: việc ẩn đi, hiện lại sau 4 tuần nếu Google vẫn thấy vấn đề. Bỏ qua: ẩn trong 3 tháng.'],
    ],
];
