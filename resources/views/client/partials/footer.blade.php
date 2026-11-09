@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $brandName = $siteBranding['name'] ?? 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ';
  $contact = $siteBranding['contact'] ?? [];
  $phone = !empty($contact['phone']) ? $contact['phone'] : '0397.999.123';
  $phoneHref = 'tel:'.preg_replace('/[^0-9+]/', '', $phone);
  $email = !empty($contact['email']) ? $contact['email'] : 'trolyluatsu@minhanhvulaw.com';
  $address = !empty($contact['address']) ? $contact['address'] : '60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh';
  $socials = $socialLinks ?? ['facebook' => '#', 'youtube' => '#', 'linkedin' => '#', 'tiktok' => '#'];

  $litigationUrl = route('client.litigation', ['locale' => $locale]);
  $enterpriseUrl = route('client.enterprise', ['locale' => $locale]);
  $servicesUrl = route('client.services', ['locale' => $locale]);

  $col2Defaults = [
    'item-1' => ['label' => 'Tranh Chấp Công Ty', 'href' => $litigationUrl.'#tranh-chap-cong-ty'],
    'item-2' => ['label' => 'Tranh Chấp Hợp Đồng', 'href' => $litigationUrl.'#tranh-chap-hop-dong'],
    'item-3' => ['label' => 'Tranh Chấp Quyền Thừa Kế', 'href' => $litigationUrl.'#thua-ke-di-chuc'],
    'item-4' => ['label' => 'Phòng pháp chế thuê ngoài', 'href' => $enterpriseUrl.'#phap-che-thue-ngoai'],
    'item-5' => ['label' => 'Tư vấn pháp luật hàng tháng', 'href' => $enterpriseUrl.'#tu-van-hang-thang'],
    'item-6' => ['label' => 'Legal Health Check', 'href' => $enterpriseUrl.'#legal-health-check'],
  ];

  $col3Defaults = [
    'item-1' => ['label' => 'Giới thiệu chung MAV', 'href' => route('client.about', ['locale' => $locale])],
    'item-2' => ['label' => 'Hợp tác kinh doanh', 'href' => route('client.cooperation', ['locale' => $locale])],
    'item-3' => ['label' => 'Thành lập & Giấy phép công ty', 'href' => $servicesUrl.'#thanh-lap-cong-ty'],
    'item-4' => ['label' => 'Dịch vụ visa & Di trú', 'href' => $servicesUrl.'#dich-vu-di-tru'],
    'item-5' => ['label' => 'Báo cáo thuế & Tài chính', 'href' => $servicesUrl.'#bao-cao-thue'],
    'item-6' => ['label' => 'Học tập Nghiên cứu', 'href' => route('client.research', ['locale' => $locale])],
    'item-7' => ['label' => 'Liên hệ Luật sư', 'href' => route('client.contact', ['locale' => $locale])],
  ];
@endphp
<!-- Footer Section (MAV Law Standardized Typography) -->
<footer class="bg-navy-950 text-slate-300 mt-auto border-t border-navy-800 client-footer" @if($canEdit) data-outline-section="footer" data-outline-label="Chân trang" @endif>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10" @if($canEdit) data-outline-group="footer-columns" data-outline-kind="grid" data-outline-group-label="Các cột chân trang" @endif>

      <!-- Col 1: Legal Entity Info -->
      <div class="space-y-4" @if($canEdit) data-outline-group="footer-col-entity" data-outline-kind="column" data-outline-group-label="Thông tin pháp nhân" @endif>
        <div class="flex items-center gap-3">
          <div class="bg-brand-red text-white p-2.5 rounded-lg shadow">
            <i class="fa-solid fa-scale-balanced text-2xl"></i>
          </div>
          <x-client::editable key="partials.footer.short_brand" tag="span" class="text-xl font-bold tracking-wider text-white uppercase">MINH ANH VŨ</x-client::editable>
        </div>
        <div class="text-sm text-slate-400 space-y-2.5 leading-relaxed">
          <p><strong class="text-white text-base">{{ $brandName }}</strong></p>
          <x-client::editable key="partials.footer.license" tag="p" :html="true">Giấy ĐKHĐ số: <strong class="text-slate-200">79.2026.02.5342/TP/ĐKHĐ</strong> do Sở Tư pháp TP.HCM cấp.</x-client::editable>
          <x-client::editable key="partials.footer.slogan" tag="p" class="font-accent text-lg text-brand-red font-semibold">"Quản trị rủi ro pháp lý cùng bạn"</x-client::editable>
        </div>
        <div class="flex space-x-3 pt-2" contenteditable="false">
          <a href="{{ $socials['facebook'] ?? '#' }}" class="w-10 h-10 rounded-full bg-navy-900 hover:bg-brand-red hover:text-white flex items-center justify-center transition border border-navy-800 text-sm" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="{{ $socials['youtube'] ?? '#' }}" class="w-10 h-10 rounded-full bg-navy-900 hover:bg-brand-red hover:text-white flex items-center justify-center transition border border-navy-800 text-sm" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
          <a href="{{ $socials['linkedin'] ?? '#' }}" class="w-10 h-10 rounded-full bg-navy-900 hover:bg-brand-red hover:text-white flex items-center justify-center transition border border-navy-800 text-sm" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
          <a href="{{ $socials['tiktok'] ?? '#' }}" class="w-10 h-10 rounded-full bg-navy-900 hover:bg-brand-red hover:text-white flex items-center justify-center transition border border-navy-800 text-sm" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
        </div>
      </div>

      <!-- Col 2: Practice Areas Links (Tranh tụng & Doanh nghiệp) -->
      <div @if($canEdit) data-outline-group="footer-col-practice" data-outline-kind="column" data-outline-group-label="Tranh tụng & Doanh nghiệp" @endif>
        <x-client::editable key="partials.footer.col2_title" tag="h3" class="text-white font-bold text-base sm:text-lg uppercase tracking-wider mb-5 border-l-4 border-brand-red pl-3">Tranh tụng &amp; Doanh nghiệp</x-client::editable>
        <ul class="space-y-3 text-sm" @if($canEdit) data-outline-group="footer-col2-list" data-outline-kind="items" data-outline-group-label="Liên kết Tranh tụng & Doanh nghiệp" @endif>
          @foreach($lists->items('partials.footer.col2_links', array_keys($col2Defaults)) as $id)
            @php
              $d = $col2Defaults[$id] ?? ['label' => '', 'href' => '#'];
            @endphp
            <x-client::list-item list="partials.footer.col2_links" :item="$id" tag="li">
              <a href="{{ $d['href'] }}" class="hover:text-brand-red transition flex items-center">
                <i class="fa-solid fa-chevron-right text-xs mr-2.5 text-brand-red"></i>
                <x-client::editable :key="'partials.footer.col2_links.' . $id . '.label'" tag="span">{{ $d['label'] }}</x-client::editable>
              </a>
            </x-client::list-item>
          @endforeach
        </ul>
      </div>

      <!-- Col 3: Quick Navigation -->
      <div @if($canEdit) data-outline-group="footer-col-quicknav" data-outline-kind="column" data-outline-group-label="Dịch vụ & Hợp tác" @endif>
        <x-client::editable key="partials.footer.col3_title" tag="h3" class="text-white font-bold text-base sm:text-lg uppercase tracking-wider mb-5 border-l-4 border-brand-red pl-3">Dịch vụ &amp; Hợp tác</x-client::editable>
        <ul class="space-y-3 text-sm" @if($canEdit) data-outline-group="footer-col3-list" data-outline-kind="items" data-outline-group-label="Liên kết Dịch vụ & Hợp tác" @endif>
          @foreach($lists->items('partials.footer.col3_links', array_keys($col3Defaults)) as $id)
            @php
              $d = $col3Defaults[$id] ?? ['label' => '', 'href' => '#'];
            @endphp
            <x-client::list-item list="partials.footer.col3_links" :item="$id" tag="li">
              <a href="{{ $d['href'] }}" class="hover:text-brand-red transition flex items-center">
                <i class="fa-solid fa-angle-right text-xs mr-2.5 text-slate-500"></i>
                <x-client::editable :key="'partials.footer.col3_links.' . $id . '.label'" tag="span">{{ $d['label'] }}</x-client::editable>
              </a>
            </x-client::list-item>
          @endforeach
        </ul>
      </div>

      <!-- Col 4: Contact info & Headquarters -->
      <div @if($canEdit) data-outline-group="footer-col-contact" data-outline-kind="column" data-outline-group-label="Trụ sở chính" @endif>
        <x-client::editable key="partials.footer.col4_title" tag="h3" class="text-white font-bold text-base sm:text-lg uppercase tracking-wider mb-5 border-l-4 border-brand-red pl-3">Trụ sở chính</x-client::editable>
        <ul class="space-y-3 text-sm">
          <li class="flex items-start">
            <i class="fa-solid fa-location-dot text-brand-red mt-1 mr-3 flex-shrink-0 text-base"></i>
            <span>{{ $address }}</span>
          </li>
          <li class="flex items-center">
            <i class="fa-solid fa-phone text-brand-red mr-3 flex-shrink-0 text-base"></i>
            <span>
              <x-client::editable key="partials.footer.hotline_prefix" tag="span">Hotline:</x-client::editable>
              <a href="{{ $phoneHref }}" class="text-brand-red font-bold">{{ $phone }}</a>
            </span>
          </li>
          <li class="flex items-center">
            <i class="fa-solid fa-envelope text-brand-red mr-3 flex-shrink-0 text-base"></i>
            <span>{{ $email }}</span>
          </li>
          <li class="flex items-center">
            <i class="fa-solid fa-globe text-brand-red mr-3 flex-shrink-0 text-base"></i>
            <x-client::editable key="partials.footer.website" tag="span">www.minhanhvulaw.com</x-client::editable>
          </li>
        </ul>
      </div>
    </div>

    <!-- Copyright Bottom -->
    <div class="mt-14 pt-8 border-t border-navy-800 flex flex-col sm:flex-row justify-between items-center text-sm text-slate-400 gap-4">
      <x-client::editable key="partials.footer.copyright" tag="p">© 2026 CÔNG TY LUẬT TNHH MTV MINH ANH VŨ. Quản trị rủi ro pháp lý cùng bạn.</x-client::editable>
      <x-client::editable key="partials.footer.credit" tag="p" :html="true" class="flex items-center gap-1">Thiết kế &amp; Vận hành bởi <span class="text-brand-red font-semibold">Mắt Bão WS</span></x-client::editable>
    </div>
  </div>
</footer>
