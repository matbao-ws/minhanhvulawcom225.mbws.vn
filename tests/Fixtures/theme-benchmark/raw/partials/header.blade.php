{{-- Theme benchmark: a header as a theme ships it, before any editable marking. --}}
<header class="site-header">
    <div class="topbar">
        <span data-case="header-hotline">Hotline tư vấn</span> <a href="tel:{{ $phone }}">{{ $phone }}</a>
    </div>
    <div class="header-main">
        <a href="/" class="logo"><img data-case="header-logo" src="/client-assets/images/logo.png" alt="Logo cửa hàng"></a>
        <nav class="main-nav">
            @foreach($menu as $item)
                <a href="{{ $item->url }}">{{ $item->label }}</a>
            @endforeach
        </nav>
        <a href="/gio-hang" class="cart"><img src="/client-assets/images/cart.svg" width="24" height="24" alt="Giỏ hàng"></a>
        <button type="button" class="menu-toggle" aria-label="Menu"><svg viewBox="0 0 24 24"><use href="#icon-menu"></use></svg></button>
        <a href="/vi" data-editable-ignore="bộ chuyển ngôn ngữ, không phải nội dung">VN</a>
        <a href="#" data-src="#popup-search" aria-label="Tìm kiếm"></a>
        <div class="overlay" data-bg="main-menu-overlay"></div>
    </div>
</header>
