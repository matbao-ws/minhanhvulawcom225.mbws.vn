@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $brandName = $siteBranding['name'] ?? 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ';
  $contact = $siteBranding['contact'] ?? [];
  $phone = !empty($contact['phone']) ? $contact['phone'] : '0397.999.123';
  $phoneHref = 'tel:'.preg_replace('/[^0-9+]/', '', $phone);
@endphp
<!-- Top Header with Logo, Company Name & Slogan (MAV Law Style) -->
<header class="client-header" contenteditable="false">
  <div class="bg-white border-b border-slate-200 py-3.5" @if($canEdit) data-outline-section="header" data-outline-label="Đầu trang" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
      <!-- Logo & Brand Info -->
      <a href="{{ \App\Support\LocaleUrl::home($locale) }}" class="flex items-center gap-4 text-center md:text-left group client-brand">
        <div class="bg-brand-red text-white w-14 h-14 rounded-xl flex items-center justify-center shadow-md shadow-red-600/20 flex-shrink-0 group-hover:scale-105 transition">
          <i class="fa-solid fa-scale-balanced text-3xl"></i>
        </div>
        <div>
          <span class="block text-xl sm:text-2xl font-bold tracking-tight text-navy-900 uppercase">{{ $brandName }}</span>
          <x-client::editable key="partials.header.slogan" tag="span" class="block text-sm sm:text-base font-semibold text-brand-red tracking-wide mt-0.5">Quản trị rủi ro pháp lý cùng bạn</x-client::editable>
        </div>
      </a>

      <!-- Right Quick Info -->
      <div class="hidden lg:flex items-center gap-7" @if($canEdit) data-outline-group="header-quick-info" data-outline-kind="row" data-outline-group-label="Thông tin nhanh đầu trang" @endif>
        <div class="flex items-center gap-3.5" @if($canEdit) data-outline-group="header-hotline-card" data-outline-kind="card" data-outline-group-label="Hotline 24/7" @endif>
          <div class="w-11 h-11 rounded-full bg-red-50 text-brand-red flex items-center justify-center text-lg shadow-sm">
            <i class="fa-solid fa-phone-volume"></i>
          </div>
          <div @if($canEdit) data-outline-group="header-hotline-content" data-outline-kind="content" data-outline-group-label="Nội dung Hotline" @endif>
            <x-client::editable key="partials.header.hotline_label" tag="span" class="block text-xs text-slate-500 font-medium uppercase tracking-wider">Hotline 24/7</x-client::editable>
            <a href="{{ $phoneHref }}" class="text-base font-bold text-navy-900 hover:text-brand-red transition">{{ $phone }}</a>
          </div>
        </div>

        <div class="flex items-center gap-3.5" @if($canEdit) data-outline-group="header-hours-card" data-outline-kind="card" data-outline-group-label="Giờ làm việc" @endif>
          <div class="w-11 h-11 rounded-full bg-slate-100 text-navy-900 flex items-center justify-center text-lg shadow-sm">
            <i class="fa-solid fa-clock"></i>
          </div>
          <div @if($canEdit) data-outline-group="header-hours-content" data-outline-kind="content" data-outline-group-label="Nội dung Giờ làm việc" @endif>
            <x-client::editable key="partials.header.hours_label" tag="span" class="block text-xs text-slate-500 font-medium uppercase tracking-wider">Giờ làm việc</x-client::editable>
            <x-client::editable key="partials.header.hours_value" tag="span" class="text-base font-bold text-navy-900">08:00 - 17:30</x-client::editable>
          </div>
        </div>

        <div @if($canEdit) data-outline-group="header-cta-card" data-outline-kind="card" data-outline-group-label="Nút Đặt Lịch Hẹn" @endif>
          <a href="{{ route('client.contact', ['locale' => $locale]) }}" class="px-6 py-3 bg-brand-red hover:bg-brand-redDark text-white font-semibold rounded-lg text-sm uppercase tracking-wider shadow-md shadow-red-600/25 transition inline-flex items-center">
            <i class="fa-solid fa-calendar-check mr-2"></i>
            <x-client::editable key="partials.header.cta_button" tag="span">Đặt Lịch Hẹn</x-client::editable>
          </a>
        </div>
      </div>

      <!-- Mobile Hamburger Button -->
      <button id="mobile-menu-btn" type="button" class="lg:hidden p-2.5 rounded-lg text-navy-900 hover:bg-slate-100 focus:outline-none" aria-label="Toggle Navigation">
        <i class="fa-solid fa-bars text-2xl"></i>
      </button>
    </div>
    </div>
  </div>
</header>

<!-- Main Navigation Bar (7 mục chuẩn theo yêu cầu của khách hàng) -->
@include('client.partials.navigation', ['menuKey' => 'primary', 'activeNav' => $activeNav ?? ''])

<!-- Mobile Drawer -->
<div id="mobile-drawer" class="fixed inset-0 z-50 hidden transition-opacity duration-300" contenteditable="false">
  <div id="drawer-backdrop" class="fixed inset-0 bg-navy-950/80 backdrop-blur-sm"></div>
  <div class="fixed inset-y-0 right-0 max-w-xs w-full bg-white shadow-2xl p-6 flex flex-col justify-between z-10 overflow-y-auto">
    <div>
      <div class="flex items-center justify-between pb-5 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <div class="bg-brand-red text-white p-2 rounded-md">
            <i class="fa-solid fa-scale-balanced text-lg"></i>
          </div>
          <x-client::editable key="partials.header.mobile_brand" tag="span" class="font-bold text-navy-900 tracking-wide text-base">MINH ANH VŨ</x-client::editable>
        </div>
        <button id="close-drawer-btn" type="button" class="p-2 text-slate-500 hover:text-slate-900 rounded-md" aria-label="Close Navigation">
          <i class="fa-solid fa-xmark text-2xl"></i>
        </button>
      </div>
      @php
        $mobileItems = app(\App\Services\MenuService::class)->tree('primary');
      @endphp
      <ul class="mt-6 space-y-1 text-base font-medium">
        <li><x-client::editable key="partials.header.mobile_home" tag="a" :href="\App\Support\LocaleUrl::home($locale)" class="block px-4 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 font-semibold">Trang chủ</x-client::editable></li>
        @foreach($mobileItems as $mItem)
          <li>
            <a href="{{ $mItem['url'] ?: '#' }}" class="block px-4 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 {{ $mItem['children']->isNotEmpty() ? 'font-bold text-navy-900' : 'font-semibold' }}">{{ $mItem['label'] }}</a>
            @if($mItem['children']->count() > 6)
              <ul class="pl-4 space-y-1 text-sm text-slate-600">
                <li><x-client::editable key="partials.header.mobile_srv_1" tag="a" :href="route('client.services', ['locale' => $locale]).'#thanh-lap-cong-ty'" class="block py-1.5 hover:text-brand-red">• Doanh nghiệp &amp; Đầu tư</x-client::editable></li>
                <li><x-client::editable key="partials.header.mobile_srv_2" tag="a" :href="route('client.services', ['locale' => $locale]).'#giay-phep-lao-dong'" class="block py-1.5 hover:text-brand-red">• Giấy phép &amp; Di trú</x-client::editable></li>
                <li><x-client::editable key="partials.header.mobile_srv_3" tag="a" :href="route('client.services', ['locale' => $locale]).'#bao-cao-thue'" class="block py-1.5 hover:text-brand-red">• Thuế, Kế toán &amp; Hành chính</x-client::editable></li>
              </ul>
            @elseif($mItem['children']->isNotEmpty())
              <ul class="pl-4 space-y-1 text-sm text-slate-600">
                @foreach($mItem['children'] as $mChild)
                  <li><a href="{{ $mChild['url'] ?: '#' }}" class="block py-1.5 hover:text-brand-red">• {{ $mChild['label'] }}</a></li>
                @endforeach
              </ul>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
    <div class="pt-6 border-t border-slate-200 space-y-3">
      <a href="{{ $phoneHref }}" class="flex items-center justify-center gap-2 w-full py-3.5 bg-red-50 text-brand-red font-bold rounded-lg border border-red-200 text-base">
        <i class="fa-solid fa-phone"></i> {{ $phone }}
      </a>
      <a href="{{ route('client.contact', ['locale' => $locale]) }}" class="block text-center w-full py-3.5 bg-brand-red hover:bg-brand-redDark text-white font-bold rounded-lg shadow-md text-base">
        {{ $contact['cta_mobile'] ?? 'Đặt Lịch Tư Vấn' }}
      </a>
    </div>
  </div>
</div>
