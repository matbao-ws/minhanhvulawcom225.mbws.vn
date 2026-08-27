@extends('client.layouts.app')

@section('title', 'Thử bộ công cụ trên bố cục khó')

@section('content')
    {{--
        Layouts that break inline editors, on one page.

        Each block below is a pattern that has actually broken this kind of tool:
        a fixed control positioned from a scrolled page, a grid whose column count
        shifts when a child is added, a transformed ancestor that redefines what
        `position: fixed` is fixed to, and a clipping parent that swallows anything
        drawn outside it. The point is to fail here rather than on a customer site.
    --}}

    <section class="stress-hero">
        <div class="stress-hero__media" aria-hidden="true"></div>
        <div class="stress-hero__overlay">
            <x-client::editable key="stress.hero.eyebrow" tag="p" class="stress-hero__eyebrow">
                Bố cục khó
            </x-client::editable>
            <x-client::editable key="stress.hero.title" tag="h1" class="stress-hero__title">
                Chữ nằm đè lên ảnh nền, trong lớp phủ định vị tuyệt đối
            </x-client::editable>
            <x-client::editable key="stress.hero.lead" tag="p" class="stress-hero__lead" html>
                Vùng này nằm trong <strong>absolute overlay</strong> trên ảnh nền. Toolbar phải
                bám đúng vùng chứ không được chui xuống dưới lớp phủ.
            </x-client::editable>
        </div>
    </section>

    <section class="client-shell stress-block">
        <h2>A. Trang dài — cuộn rồi mới sửa</h2>
        <p class="stress-note">
            Cuộn xuống hẳn rồi mới bấm vào vùng dưới đây. Toolbar là <code>position: fixed</code>;
            nếu code cộng nhầm khoảng cuộn thì nó sẽ rơi ra ngoài màn hình.
        </p>
        <div class="stress-tall">
            <x-client::editable key="stress.scroll.text" tag="p">
                Vùng nằm sau một khoảng cuộn dài. Bấm vào đây khi đã cuộn sâu.
            </x-client::editable>
        </div>
    </section>

    <section class="client-shell stress-block">
        <h2>B. CSS Grid — thêm ô có làm vỡ cột không</h2>
        <p class="stress-note">
            Lưới <code>auto-fill</code>. Rê chuột lên một thẻ rồi bấm <strong>+</strong>:
            ô mới phải vào đúng lưới, không được phá số cột.
        </p>
        <div class="stress-grid">
            <x-client::editable key="stress.grid.card" tag="article" class="stress-card">
                Thẻ trong lưới. Thêm ô ngay dưới bằng dấu cộng.
            </x-client::editable>
        </div>
    </section>

    <section class="client-shell stress-block">
        <h2>C. Flex căn sát — vùng rỗng có làm sập hàng không</h2>
        <p class="stress-note">
            Ẩn một vùng trong hàng flex rồi bấm Lưu. Hàng không được sụp; bật
            <strong>Vùng đã ẩn</strong> phải lấy lại được chỗ bấm.
        </p>
        <div class="stress-flex">
            <x-client::editable key="stress.flex.a" tag="span" class="stress-pill">Một</x-client::editable>
            <x-client::editable key="stress.flex.b" tag="span" class="stress-pill">Hai</x-client::editable>
            <x-client::editable key="stress.flex.c" tag="span" class="stress-pill">Ba</x-client::editable>
        </div>
    </section>

    <section class="client-shell stress-block">
        <h2>D. Ancestor có transform</h2>
        <p class="stress-note">
            Thẻ cha dưới đây có <code>transform</code>. Nó tạo containing block mới, nên
            <code>position: fixed</code> bên trong sẽ neo vào nó thay vì vào màn hình.
            Toolbar phải nằm ngoài cây này mới không bị kéo theo.
        </p>
        <div class="stress-transformed">
            <x-client::editable key="stress.transform.text" tag="p">
                Vùng nằm trong thẻ cha đã bị transform. Toolbar vẫn phải bám đúng vị trí.
            </x-client::editable>
        </div>
    </section>

    <section class="client-shell stress-block">
        <h2>E. Cha cắt tràn (overflow: hidden)</h2>
        <p class="stress-note">
            Nút <strong>+</strong> và <strong>×</strong> là <code>absolute</code>. Nếu chúng bị
            vẽ bên trong thẻ cha đang cắt tràn thì sẽ mất tăm.
        </p>
        <div class="stress-clipped">
            <x-client::editable key="stress.clip.text" tag="p">
                Vùng nằm trong thẻ cha có overflow hidden.
            </x-client::editable>
        </div>
    </section>

    <section class="client-shell stress-block">
        <h2>F. Section lồng ba cấp</h2>
        <p class="stress-note">
            Mở <strong>Mục lục</strong>: mỗi cấp là một danh sách riêng, kéo thả không được
            phép vượt cấp.
        </p>
        <x-client::section-list
            key="stress.sections"
            :sections="['stress-intro', 'stress-features', 'stress-contact']"
        />
    </section>

    <section class="client-shell stress-block">
        <h2>G. Bảng</h2>
        <p class="stress-note">
            Ô trong bảng không nhận <code>display: inline-block</code> mà không phá cấu trúc bảng.
        </p>
        <table class="stress-table">
            <thead><tr><th>Gói</th><th>Giá</th></tr></thead>
            <tbody>
                <tr>
                    <td><x-client::editable key="stress.table.name" tag="span" :appendable="false">Cơ bản</x-client::editable></td>
                    <td><x-client::editable key="stress.table.price" tag="span" :appendable="false">1.000.000 ₫</x-client::editable></td>
                </tr>
            </tbody>
        </table>
    </section>

    <div class="stress-tall stress-tall--end" aria-hidden="true"></div>
@endsection
