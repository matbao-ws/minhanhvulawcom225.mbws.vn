@extends('client.layouts.app')

@section('title', 'Thử bộ công cụ sửa nội dung')

@section('content')
    <section class="client-shell client-page-head">
        {{-- Every region below is a real site block: what you save here goes into
             `site_blocks` exactly as it would on a production template. --}}
        <x-client::editable key="dev.sandbox.title" tag="h1">
            Thử bộ công cụ sửa nội dung
        </x-client::editable>

        <x-client::editable key="dev.sandbox.intro" tag="p">
            Đăng nhập bằng tài khoản có quyền pages.update, bấm “Sửa trực tiếp” ở thanh dưới cùng,
            rồi bấm vào bất kỳ vùng nào bên dưới. Thanh định dạng sẽ nổi lên ngay phía trên vùng đang mở.
        </x-client::editable>
    </section>

    <section class="client-shell client-sandbox">
        <h2>1. Định dạng chữ</h2>
        <p class="client-sandbox__note">
            Bôi đen một đoạn rồi thử Đậm, Nghiêng, Gạch chân, Gạch ngang, danh sách, liên kết,
            Xóa định dạng, Hoàn tác/Làm lại. Nút chữ <strong>P</strong> đổi cấp tiêu đề.
        </p>

        <x-client::editable key="dev.sandbox.rich" tag="p" html>
            Đoạn này lưu dạng HTML nên giữ được định dạng. Thử bôi đen vài chữ rồi bấm
            <strong>Đậm</strong>, hoặc đổi cả đoạn thành Tiêu đề 2 bằng menu cấp tiêu đề.
        </x-client::editable>

        <h2>2. Ẩn vùng và khôi phục</h2>
        <p class="client-sandbox__note">
            Nút con mắt gạch chéo ẩn vùng khỏi trang — thay đổi chờ bấm <strong>Lưu</strong>.
            Nút đồng hồ quay ngược gọi server ngay, trả vùng về đúng chữ mà template viết sẵn.
        </p>

        <x-client::editable key="dev.sandbox.hideable" tag="p">
            Vùng này để thử ẩn rồi khôi phục. Chữ bạn đang đọc là giá trị mặc định trong Blade,
            chưa có dòng nào trong database cho tới khi bạn sửa nó.
        </x-client::editable>

        <h2>3. Ảnh</h2>
        <p class="client-sandbox__note">
            Bấm vào ảnh để mở thư viện Media. Cần thêm quyền media.view.
        </p>

        <x-client::editable-image
            key="dev.sandbox.image"
            src="{{ asset('admin-assets/images/icons/emptydata.png') }}"
            alt="Ảnh mẫu"
            class="client-sandbox__image"
        />

        <h2>4. Vùng lặp: thêm và xóa ô</h2>
        <p class="client-sandbox__note">
            Rê chuột lên một ô sẽ thấy nút <strong>+</strong> để thêm ô ngay dưới. Ô do bạn thêm
            có nút <strong>×</strong> để xóa hẳn — ô gốc của template thì chỉ ẩn được, không xóa được.
            Thêm hoặc xóa xong tải lại trang để thấy kết quả.
        </p>

        <div class="client-sandbox__grid">
            <x-client::editable key="dev.sandbox.card" tag="div" class="client-sandbox__card">
                Ô nội dung gốc. Rê chuột lên đây rồi bấm dấu cộng để thêm ô mới bên dưới.
            </x-client::editable>
        </div>

        <h2>5. Section cha &amp; con</h2>
        <p class="client-sandbox__note">
            Mở <strong>Mục lục</strong> trên thanh công cụ. Section cha xếp lại được với nhau;
            section con chỉ xếp lại trong phạm vi cha của nó — mỗi cấp là một danh sách riêng,
            nên không có cách nào đẩy một section con sang cha khác. Đổi xong tải lại trang.
        </p>

        <x-client::section-list
            key="dev.sections"
            :sections="['intro', 'features', 'contact']"
        />
    </section>
@endsection
