@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $activeSlug = $activeSlug ?? '';
  $contact = $siteBranding['contact'] ?? [];
  $phone = !empty($contact['phone']) ? $contact['phone'] : '0397.999.123';
  $phoneHref = 'tel:'.preg_replace('/[^0-9+]/', '', $phone);

  $serviceDefaults = [
    'item-1' => ['slug' => 'tranh-tung-va-tu-van', 'name' => 'Tranh tụng & Tư vấn tranh chấp', 'href' => route('client.litigation', ['locale' => $locale])],
    'item-2' => ['slug' => 'doanh-nghiep', 'name' => 'Pháp lý Doanh nghiệp & Pháp chế', 'href' => route('client.enterprise', ['locale' => $locale])],
    'item-3' => ['slug' => 'dich-vu', 'name' => 'Dịch vụ Giấy phép, Thuế & Di trú', 'href' => route('client.services', ['locale' => $locale])],
    'item-4' => ['slug' => 'hop-tac-kinh-doanh', 'name' => 'Hợp tác kinh doanh', 'href' => route('client.cooperation', ['locale' => $locale])],
    'item-5' => ['slug' => 'hoc-tap-nghien-cuu', 'name' => 'Học tập & Nghiên cứu pháp lý', 'href' => route('client.research', ['locale' => $locale])],
    'item-6' => ['slug' => 'lien-he', 'name' => 'Liên hệ Luật sư tư vấn', 'href' => route('client.contact', ['locale' => $locale])],
  ];
@endphp
<div class="space-y-8" @if($canEdit) data-outline-group="service-sidebar" data-outline-kind="column" data-outline-group-label="Thanh bên dịch vụ" @endif>
  <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200" @if($canEdit) data-outline-group="service-sidebar-links-card" data-outline-kind="card" data-outline-group-label="Dịch Vụ Trọng Tâm" @endif>
    <x-client::editable key="partials.service_sidebar.title" tag="h3" class="text-lg font-bold uppercase text-navy-900 border-l-4 border-brand-red pl-3 mb-5">Dịch Vụ Trọng Tâm</x-client::editable>
    <ul class="space-y-3" @if($canEdit) data-outline-group="service-sidebar-links" data-outline-kind="items" data-outline-group-label="Danh sách dịch vụ trọng tâm" @endif>
      @foreach($lists->items('partials.service_sidebar.links', array_keys($serviceDefaults)) as $id)
        @php
          $d = $serviceDefaults[$id] ?? ['slug' => '', 'name' => '', 'href' => '#'];
          $isActive = $d['slug'] !== '' && $d['slug'] === $activeSlug;
          $activeCls = $isActive
              ? 'bg-navy-900 text-white font-bold border-l-4 border-brand-red'
              : 'bg-slate-50 hover:bg-slate-100 text-navy-900 font-medium';
          $iconCls = $isActive ? 'text-brand-red' : 'text-slate-400';
        @endphp
        <x-client::list-item list="partials.service_sidebar.links" :item="$id" tag="li">
          <a href="{{ $d['href'] }}" class="flex items-center justify-between p-3.5 rounded-lg border border-slate-200 text-base transition {{ $activeCls }}">
            <x-client::editable :key="'partials.service_sidebar.links.' . $id . '.name'" tag="span">{{ $d['name'] }}</x-client::editable>
            <i class="fa-solid fa-chevron-right text-xs {{ $iconCls }}"></i>
          </a>
        </x-client::list-item>
      @endforeach
    </ul>
  </div>

  <div class="bg-navy-900 rounded-xl p-6 text-white text-center space-y-4 shadow-xl border border-navy-800" @if($canEdit) data-outline-group="service-sidebar-emergency" data-outline-kind="card" data-outline-group-label="Cần Tư Vấn Khẩn Cấp" @endif>
    <div class="w-14 h-14 rounded-full bg-brand-red flex items-center justify-center mx-auto text-2xl shadow-lg shadow-red-600/30">
      <i class="fa-solid fa-headset"></i>
    </div>
    <x-client::editable key="partials.service_sidebar.emergency_title" tag="h4" class="text-xl font-bold text-white">Cần Tư Vấn Khẩn Cấp?</x-client::editable>
    <x-client::editable key="partials.service_sidebar.emergency_desc" tag="p" class="text-sm text-slate-300 leading-relaxed">Luật sư chuyên ngành của Luật Minh Anh Vũ luôn sẵn sàng lắng nghe và hỗ trợ bạn 24/7.</x-client::editable>
    <div class="pt-2">
      <a href="{{ $phoneHref }}" class="inline-flex items-center justify-center gap-2 w-full py-3.5 bg-brand-red hover:bg-brand-redDark font-bold rounded-lg text-base text-white shadow-lg transition">
        <i class="fa-solid fa-phone"></i> {{ $phone }}
      </a>
    </div>
  </div>

  <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200" @if($canEdit) data-outline-group="service-sidebar-form-card" data-outline-kind="card" data-outline-group-label="Đặt Lịch Tư Vấn" @endif>
    <x-client::editable key="partials.service_sidebar.form_title" tag="h3" class="text-lg font-bold uppercase text-navy-900 border-l-4 border-brand-red pl-3 mb-5">Đặt Lịch Tư Vấn</x-client::editable>
    <form action="{{ route('client.contact.submit', ['locale' => $locale]) }}" method="post" class="consultation-form space-y-3.5" data-ajax-form="true">
      @csrf
      <div>
        <input type="text" name="fullname" required placeholder="Họ và tên của bạn *" class="w-full px-4 py-3 rounded-lg border border-slate-300 text-sm focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none" />
      </div>
      <div>
        <input type="tel" name="phone" required placeholder="Số điện thoại liên hệ *" class="w-full px-4 py-3 rounded-lg border border-slate-300 text-sm focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none" />
      </div>
      <div>
        <textarea rows="3" name="message" placeholder="Tóm tắt yêu cầu cần luật sư hỗ trợ..." class="w-full px-4 py-3 rounded-lg border border-slate-300 text-sm focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none"></textarea>
      </div>
      <x-client::editable key="partials.service_sidebar.form_submit" tag="button" type="submit" class="w-full py-3.5 rounded-lg bg-brand-red hover:bg-brand-redDark text-white font-bold text-sm uppercase tracking-wider transition shadow-md shadow-red-600/20">Gửi Yêu Cầu</x-client::editable>
    </form>
  </div>
</div>
