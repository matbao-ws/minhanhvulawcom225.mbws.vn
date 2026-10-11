{{-- Theme benchmark: footer, with copy, database menus and decorative artwork. --}}
<footer class="site-footer">
    <img src="/client-assets/images/wave.svg" alt="" aria-hidden="true" class="footer-wave">
    <div class="footer-cols">
        <p data-case="footer-copyright">© 2026 Công ty Cổ phần ABC</p>
        <p data-case="footer-email">lienhe@shop.vn</p>
        <p data-case="footer-bare-ignore" data-editable-ignore>Chữ bị né bằng ignore không lý do</p>
        <ul class="footer-menu">
            @foreach($footerMenu as $item)
                <li><a href="{{ $item->url }}">{{ $item->label }}</a></li>
            @endforeach
        </ul>
    </div>
</footer>
