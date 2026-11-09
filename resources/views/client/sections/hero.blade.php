@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $slideDefaults = [
    'item-1' => [
      'image' => asset('assets/uploads/banners/hero_hinh_su.jpg'),
      'alt' => 'MAV Law - Quản trị rủi ro pháp lý cùng bạn',
      'badge' => 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
      'title' => 'QUẢN TRỊ RỦI RO PHÁP LÝ CÙNG BẠN',
      'desc' => 'Đồng hành cùng khách hàng nhận diện và kiểm soát rủi ro từ sớm, giải quyết tranh chấp hiệu quả và bảo vệ tối đa quyền, lợi ích hợp pháp của cá nhân và doanh nghiệp.',
      'cta1_label' => 'Tranh Tụng & Tư Vấn →',
      'cta1_href' => route('client.litigation', ['locale' => $locale]),
      'cta2_label' => 'Tìm Hiểu Về MAV',
      'cta2_href' => route('client.about', ['locale' => $locale]),
    ],
    'item-2' => [
      'image' => asset('assets/uploads/banners/hero_doanh_nghiep.jpg'),
      'alt' => 'Tư vấn pháp lý doanh nghiệp',
      'badge' => 'GIẢI PHÁP CHO DOANH NGHIỆP & STARTUP',
      'title' => 'PHÁP CHẾ THUÊ NGOÀI & TƯ VẤN HÀNG THÁNG',
      'desc' => 'Rà soát sức khỏe pháp lý Legal Health Check, chuẩn hóa hợp đồng, dự liệu tình huống pháp lý và tạo lập nền tảng pháp lý an toàn để doanh nghiệp phát triển bền vững.',
      'cta1_label' => 'Xem Dịch Vụ Doanh Nghiệp →',
      'cta1_href' => route('client.enterprise', ['locale' => $locale]),
      'cta2_label' => 'Hợp Tác Kinh Doanh',
      'cta2_href' => route('client.cooperation', ['locale' => $locale]),
    ],
  ];
@endphp
<!-- HERO SLIDER SECTION -->
<section class="relative bg-navy-950 text-white overflow-hidden min-h-[540px] sm:min-h-[600px] flex items-center" @if($canEdit) data-outline-section="home-hero" data-outline-label="Banner trình chiếu (Hero Slider)" @endif>
  <div class="hero-slider relative w-full h-[540px] sm:h-[600px]" @if($canEdit) data-outline-group="home-hero-slides" data-outline-kind="items" data-outline-group-label="Danh sách Slide" @endif>
    @foreach($lists->items('home.hero.slides', array_keys($slideDefaults)) as $id)
      @php
        $d = $slideDefaults[$id] ?? $slideDefaults['item-1'];
      @endphp
      <x-client::list-item list="home.hero.slides" :item="$id" class="hero-slide {{ $loop->first ? 'active opacity-100' : 'opacity-0 pointer-events-none' }} absolute inset-0 transition-opacity duration-1000 ease-in-out" data-outline-group="home-hero-slide-{{ $id }}" data-outline-kind="slide" data-outline-group-label="Slide {{ $loop->iteration }}">
        <x-client::editable-image :key="'home.hero.slides.' . $id . '.image'" :src="$d['image']" :alt="$d['alt']" class="w-full h-full object-cover object-center filter brightness-[0.78]" />
        <div class="absolute inset-0 bg-gradient-to-r from-navy-950/90 via-navy-900/60 to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 flex items-center">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="max-w-2xl bg-white/95 backdrop-blur-md p-8 sm:p-11 rounded-2xl shadow-2xl border-l-8 border-brand-red space-y-4" @if($canEdit) data-outline-group="home-hero-content-{{ $id }}" data-outline-kind="content" data-outline-group-label="Nội dung Slide {{ $loop->iteration }}" @endif>
              <x-client::editable :key="'home.hero.slides.' . $id . '.badge'" tag="span" class="inline-block px-3 py-1 bg-red-100 text-brand-red text-xs sm:text-sm font-bold uppercase tracking-wider rounded-md">{{ $d['badge'] }}</x-client::editable>
              <x-client::editable :key="'home.hero.slides.' . $id . '.title'" tag="h2" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-navy-900 leading-tight uppercase tracking-tight">{{ $d['title'] }}</x-client::editable>
              <x-client::editable :key="'home.hero.slides.' . $id . '.desc'" tag="p" class="text-base sm:text-lg text-slate-700 font-normal leading-relaxed text-justify">{{ $d['desc'] }}</x-client::editable>
              <div class="pt-3 flex flex-wrap gap-4">
                <x-client::editable :key="'home.hero.slides.' . $id . '.cta1'" tag="a" :href="$d['cta1_href']" class="px-7 py-3.5 bg-brand-red hover:bg-brand-redDark text-white text-sm font-bold uppercase tracking-wider rounded-lg shadow-lg shadow-red-600/30 transition">{{ $d['cta1_label'] }}</x-client::editable>
                <x-client::editable :key="'home.hero.slides.' . $id . '.cta2'" tag="a" :href="$d['cta2_href']" class="px-7 py-3.5 bg-navy-900 hover:bg-navy-800 text-white text-sm font-bold uppercase tracking-wider rounded-lg shadow-lg transition">{{ $d['cta2_label'] }}</x-client::editable>
              </div>
            </div>
          </div>
        </div>
      </x-client::list-item>
    @endforeach
  </div>

  <!-- Carousel Controls -->
  <button type="button" class="hero-prev absolute left-4 sm:left-8 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-navy-900/70 hover:bg-brand-red text-white flex items-center justify-center transition z-20 shadow-md" aria-label="Slide trước">
    <i class="fa-solid fa-chevron-left text-lg"></i>
  </button>
  <button type="button" class="hero-next absolute right-4 sm:right-8 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-navy-900/70 hover:bg-brand-red text-white flex items-center justify-center transition z-20 shadow-md" aria-label="Slide tiếp theo">
    <i class="fa-solid fa-chevron-right text-lg"></i>
  </button>
  <div class="hero-dots absolute bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20"></div>
</section>
