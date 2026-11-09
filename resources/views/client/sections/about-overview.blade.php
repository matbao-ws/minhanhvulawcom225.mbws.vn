@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
@endphp
<!-- SECTION: VỀ MINH ANH VŨ (GIỚI THIỆU TỔNG QUAN) -->
<section class="py-16 sm:py-20 bg-white" @if($canEdit) data-outline-section="home-about-overview" data-outline-label="Về Minh Anh Vũ (Giới thiệu tổng quan)" @endif>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center" @if($canEdit) data-outline-group="home-about-grid" data-outline-kind="grid" data-outline-group-label="Bố cục giới thiệu" @endif>
      <div class="lg:col-span-6 space-y-6" @if($canEdit) data-outline-group="home-about-text" data-outline-kind="column" data-outline-group-label="Nội dung giới thiệu" @endif>
        <div class="space-y-1">
          <x-client::editable key="home.about.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Về chúng tôi</x-client::editable>
          <x-client::editable key="home.about.title" tag="h2" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-navy-900 tracking-tight">CÔNG TY LUẬT TNHH MTV MINH ANH VŨ</x-client::editable>
        </div>
        <x-client::editable key="home.about.slogan" tag="p" class="text-xl sm:text-2xl font-bold text-brand-red tracking-tight">"Quản trị rủi ro pháp lý cùng bạn"</x-client::editable>
        <x-client::editable key="home.about.p1" tag="p" class="text-slate-600 leading-relaxed text-base sm:text-lg font-normal text-justify">Công ty Luật TNHH MTV Minh Anh Vũ được hình thành trên nền tảng kết hợp giữa đội ngũ luật sư năng động, tận tâm và các chuyên gia có nhiều năm kinh nghiệm trong hoạt động tư vấn pháp lý, quản trị doanh nghiệp và giải quyết tranh chấp.</x-client::editable>
        <x-client::editable key="home.about.p2" tag="p" :html="true" class="text-slate-600 leading-relaxed text-base sm:text-lg font-normal text-justify">Tại Minh Anh Vũ, chúng tôi không chỉ tập trung vào việc giải quyết một vấn đề pháp lý cụ thể, mà còn chú trọng <strong>nhận diện và kiểm soát rủi ro ngay từ đầu</strong>. Mỗi phương án tư vấn đều được xây dựng trên cơ sở pháp luật, tính khả thi trong thực tiễn và mục tiêu dài hạn của khách hàng.</x-client::editable>
        <div class="pt-2 flex flex-wrap gap-4">
          <x-client::editable key="home.about.cta1" tag="a" :href="route('client.about', ['locale' => $locale])" class="px-7 py-3.5 bg-brand-red hover:bg-brand-redDark text-white font-bold rounded-lg text-sm uppercase tracking-wider shadow-md shadow-red-600/20 transition">Xem Giới Thiệu Chi Tiết &rarr;</x-client::editable>
          <x-client::editable key="home.about.cta2" tag="a" :href="route('client.cooperation', ['locale' => $locale])" class="px-7 py-3.5 bg-navy-900 hover:bg-navy-800 text-white font-bold rounded-lg text-sm uppercase tracking-wider shadow-md transition">Chính Sách Hợp Tác</x-client::editable>
        </div>
      </div>

      <div class="lg:col-span-6" @if($canEdit) data-outline-group="home-about-media" data-outline-kind="media" data-outline-group-label="Hình ảnh văn phòng" @endif>
        <div class="relative">
          <x-client::editable-image key="home.about.image" :src="asset('assets/uploads/2024/10/lich-su-thanh-lap.jpg')" alt="Văn phòng Minh Anh Vũ Law" class="w-full rounded-2xl shadow-xl border border-slate-200 object-cover" />
          <div class="absolute -bottom-6 -right-6 hidden sm:block bg-navy-900 text-white p-6 rounded-2xl shadow-2xl border border-navy-800 max-w-xs" @if($canEdit) data-outline-group="home-about-badge" data-outline-kind="card" data-outline-group-label="Huy hiệu cam kết" @endif>
            <x-client::editable key="home.about.badge_title" tag="span" class="block text-2xl font-bold text-brand-red mb-1">100% Tận Tâm</x-client::editable>
            <x-client::editable key="home.about.badge_desc" tag="p" class="text-xs text-slate-300 leading-relaxed">Đồng hành pháp lý tin cậy và giải pháp thực tiễn hiệu quả cao nhất cho bạn.</x-client::editable>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
