@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $areaDefaults = [
    'item-1' => [
      'image' => asset('assets/uploads/services/service_tranh_tung.jpg'),
      'alt' => 'Tranh tụng và tư vấn',
      'badge' => 'Mảng 01',
      'badge_cls' => 'bg-brand-red',
      'title' => 'Tranh Tụng & Tư Vấn',
      'href' => route('client.litigation', ['locale' => $locale]),
      'desc' => 'Đại diện và bảo vệ quyền, lợi ích hợp pháp tại Tòa án và Trọng tài thương mại trong các vụ việc tranh chấp phức tạp.',
      'icon_cls' => 'text-brand-red',
      'b1' => 'Tranh Chấp Công Ty & Cổ Đông',
      'b2' => 'Tranh Chấp Hợp Đồng Dân Sự & Thương Mại',
      'b3' => 'Tranh Chấp Quyền Thừa Kế, Di Chúc',
      'b4' => 'Tranh Chấp Lao Động & Hợp Đồng Làm Việc',
      'b5' => 'Tranh Chấp Hàng Hải & Vận Tải Quốc Tế',
      'cta_label' => 'Xem Chi Tiết Tranh Tụng →',
      'cta_cls' => 'bg-brand-red hover:bg-brand-redDark',
    ],
    'item-2' => [
      'image' => asset('assets/uploads/services/service_tu_van_thuong_xuyen.jpg'),
      'alt' => 'Pháp lý doanh nghiệp',
      'badge' => 'Mảng 02',
      'badge_cls' => 'bg-navy-900',
      'title' => 'Pháp Lý Doanh Nghiệp',
      'href' => route('client.enterprise', ['locale' => $locale]),
      'desc' => 'Giải pháp toàn diện đồng hành cùng doanh nghiệp kiểm soát rủi ro pháp lý, chuẩn hóa quy trình và tối ưu hóa vận hành.',
      'icon_cls' => 'text-navy-900',
      'b1' => 'Phòng pháp chế thuê ngoài trọn gói',
      'b2' => 'Tư vấn pháp luật hàng tháng định kỳ',
      'b3' => 'Kiểm tra pháp lý toàn diện “Legal Health Check”',
      'b4' => 'Huấn luyện & Đào tạo pháp luật nội bộ',
      'b5' => 'Quản trị rủi ro hợp đồng & đàm phán',
      'cta_label' => 'Xem Dịch Vụ Doanh Nghiệp →',
      'cta_cls' => 'bg-navy-900 hover:bg-navy-800',
    ],
    'item-3' => [
      'image' => asset('assets/uploads/services/service_thu_tuc_giay_phep.jpg'),
      'alt' => 'Dịch vụ pháp lý khác',
      'badge' => 'Mảng 03',
      'badge_cls' => 'bg-red-800',
      'title' => 'Dịch Vụ Pháp Lý Trọng Tâm',
      'href' => route('client.services', ['locale' => $locale]),
      'desc' => 'Hệ thống 17 dịch vụ thủ tục hành chính, giấy phép, thuế, di trú và dịch vụ luật sư cá nhân chuyên nghiệp, nhanh chóng.',
      'icon_cls' => 'text-brand-red',
      'b1' => 'Thành lập công ty, Hộ kinh doanh & Giải thể',
      'b2' => 'Giấy phép lao động, Visa, Thẻ tạm trú & Thường trú',
      'b3' => 'Dịch vụ di trú & Lý lịch tư pháp',
      'b4' => 'Báo cáo tài chính, Báo cáo thuế & Hoàn thuế TNCN',
      'b5' => 'Luật sư cá nhân & Hộ tịch khai sinh',
      'cta_label' => 'Xem Danh Mục 17 Dịch Vụ →',
      'cta_cls' => 'bg-brand-red hover:bg-brand-redDark',
    ],
  ];
@endphp
<!-- SECTION: LĨNH VỰC HOẠT ĐỘNG (3 CỤM LỚN: TRANH TỤNG - DOANH NGHIỆP - DỊCH VỤ) -->
<section class="py-16 sm:py-20 bg-white border-t border-slate-200" @if($canEdit) data-outline-section="home-practice-areas" data-outline-label="Lĩnh vực hoạt động chính" @endif>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-14">

    <div class="text-center max-w-3xl mx-auto space-y-3" @if($canEdit) data-outline-group="home-practice-header" data-outline-kind="header" data-outline-group-label="Tiêu đề lĩnh vực" @endif>
      <x-client::editable key="home.practice_areas.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Hệ thống dịch vụ chuyên sâu</x-client::editable>
      <x-client::editable key="home.practice_areas.title" tag="h2" class="text-2xl sm:text-3xl lg:text-4xl font-bold uppercase text-navy-900 tracking-tight">LĨNH VỰC HOẠT ĐỘNG CHÍNH</x-client::editable>
      <div class="w-20 h-1 bg-brand-red mx-auto rounded-full"></div>
      <x-client::editable key="home.practice_areas.subtitle" tag="p" class="text-slate-600 text-base sm:text-lg font-normal">Minh Anh Vũ cung cấp giải pháp pháp lý toàn diện, bảo vệ quyền lợi hợp pháp tối đa cho cá nhân và doanh nghiệp.</x-client::editable>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" @if($canEdit) data-outline-group="home-practice-grid" data-outline-kind="grid" data-outline-group-label="3 mảng hoạt động chính" @endif>
      @foreach($lists->items('home.practice_areas.cards', array_keys($areaDefaults)) as $id)
        @php
          $d = $areaDefaults[$id] ?? $areaDefaults['item-1'];
        @endphp
        <x-client::list-item list="home.practice_areas.cards" :item="$id" class="bg-slate-50 rounded-2xl p-8 border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group" data-outline-group="home-practice-card-{{ $id }}" data-outline-kind="card" data-outline-group-label="{{ $d['title'] }}">
          <div class="space-y-6" @if($canEdit) data-outline-group="home-practice-content-{{ $id }}" data-outline-kind="content" data-outline-group-label="Nội dung mảng" @endif>
            <div class="h-48 rounded-xl overflow-hidden relative" @if($canEdit) data-outline-group="home-practice-media-{{ $id }}" data-outline-kind="media" data-outline-group-label="Hình ảnh mảng" @endif>
              <x-client::editable-image :key="'home.practice_areas.cards.' . $id . '.image'" :src="$d['image']" :alt="$d['alt']" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-transparent to-transparent pointer-events-none"></div>
              <x-client::editable :key="'home.practice_areas.cards.' . $id . '.badge'" tag="span" class="absolute bottom-3 left-4 {{ $d['badge_cls'] }} text-white text-xs font-bold uppercase tracking-wider px-3 py-1 rounded">{{ $d['badge'] }}</x-client::editable>
            </div>
            <div>
              <h3 class="text-xl font-bold text-navy-900 uppercase tracking-tight group-hover:text-brand-red transition">
                <x-client::editable :key="'home.practice_areas.cards.' . $id . '.title'" tag="a" :href="$d['href']">{{ $d['title'] }}</x-client::editable>
              </h3>
              <x-client::editable :key="'home.practice_areas.cards.' . $id . '.desc'" tag="p" class="text-sm text-slate-600 font-normal mt-2 leading-relaxed">{{ $d['desc'] }}</x-client::editable>
            </div>
            <ul class="space-y-2.5 text-sm text-slate-700 pt-2 border-t border-slate-200">
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check {{ $d['icon_cls'] }} text-xs"></i>
                <x-client::editable :key="'home.practice_areas.cards.' . $id . '.b1'" tag="span">{{ $d['b1'] }}</x-client::editable>
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check {{ $d['icon_cls'] }} text-xs"></i>
                <x-client::editable :key="'home.practice_areas.cards.' . $id . '.b2'" tag="span">{{ $d['b2'] }}</x-client::editable>
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check {{ $d['icon_cls'] }} text-xs"></i>
                <x-client::editable :key="'home.practice_areas.cards.' . $id . '.b3'" tag="span">{{ $d['b3'] }}</x-client::editable>
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check {{ $d['icon_cls'] }} text-xs"></i>
                <x-client::editable :key="'home.practice_areas.cards.' . $id . '.b4'" tag="span">{{ $d['b4'] }}</x-client::editable>
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check {{ $d['icon_cls'] }} text-xs"></i>
                <x-client::editable :key="'home.practice_areas.cards.' . $id . '.b5'" tag="span">{{ $d['b5'] }}</x-client::editable>
              </li>
            </ul>
          </div>
          <div class="pt-6">
            <x-client::editable :key="'home.practice_areas.cards.' . $id . '.cta'" tag="a" :href="$d['href']" class="inline-flex items-center justify-center w-full py-3 {{ $d['cta_cls'] }} text-white font-bold text-sm uppercase rounded-lg transition shadow-md">{{ $d['cta_label'] }}</x-client::editable>
          </div>
        </x-client::list-item>
      @endforeach
    </div>
  </div>
</section>
