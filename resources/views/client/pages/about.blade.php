@extends('client.layouts.app')

@section('title', 'Giới thiệu chung về Công ty Luật Minh Anh Vũ - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Công ty Luật TNHH MTV Minh Anh Vũ (MAV Law) - Quản trị rủi ro pháp lý cùng bạn. Tầm nhìn sứ mệnh và các giá trị cốt lõi.')

@section('content')
  @php
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
    $coreValueDefaults = [
      'item-1' => [
        'icon' => 'fa-heart',
        'title' => 'Tận Tâm & Trách Nhiệm',
        'desc' => 'Luôn đặt sự an tâm và quyền lợi dài hạn của khách hàng lên hàng đầu. Lắng nghe, thấu hiểu và đồng hành giải quyết thấu đáo đến cùng từng vụ việc.',
      ],
      'item-2' => [
        'icon' => 'fa-award',
        'title' => 'Chuyên Nghiệp & Minh Bạch',
        'desc' => 'Tuân thủ chuẩn mực đạo đức nghề nghiệp luật sư; quy trình làm việc khoa học; minh bạch về chi phí, phương án xử lý và tiến độ thực hiện.',
      ],
      'item-3' => [
        'icon' => 'fa-lightbulb',
        'title' => 'Giải Pháp & Hiệu Quả',
        'desc' => 'Không đưa ra lý thuyết suông. Mọi ý kiến tư vấn đều gắn liền với tính khả thi trong thực tiễn xét xử, quản trị và mục tiêu cụ thể của khách hàng.',
      ],
    ];
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Giới thiệu về Công ty Luật Minh Anh Vũ',
    'titleKey' => 'about.banner.title',
    'bgKey' => 'about.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Giới thiệu', 'url' => '/gioi-thieu'],
      ['label' => 'Giới thiệu chung', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-white flex-1" @if($canEdit) data-outline-section="about-main" data-outline-label="Nội dung Giới thiệu" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">

      <!-- Câu chuyện thành lập -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center" @if($canEdit) data-outline-group="about-story" data-outline-kind="grid" data-outline-group-label="Câu chuyện thành lập" @endif>
        <div class="lg:col-span-6 space-y-6" @if($canEdit) data-outline-group="about-story-text" data-outline-kind="column" data-outline-group-label="Nội dung câu chuyện" @endif>
          <div class="space-y-1">
            <x-client::editable key="about.story.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Hành trình &amp; Giá trị</x-client::editable>
            <x-client::editable key="about.story.title" tag="h2" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-navy-900 tracking-tight">CÔNG TY LUẬT TNHH MTV MINH ANH VŨ</x-client::editable>
          </div>
          <x-client::editable key="about.story.slogan" tag="p" class="text-xl sm:text-2xl font-bold text-brand-red tracking-tight">"Quản trị rủi ro pháp lý cùng bạn"</x-client::editable>
          <x-client::editable key="about.story.p1" tag="p" class="text-slate-600 leading-relaxed text-base sm:text-lg font-normal text-justify">Công ty Luật TNHH MTV Minh Anh Vũ được hình thành trên nền tảng kết hợp giữa đội ngũ luật sư năng động, tận tâm và các chuyên gia có nhiều năm kinh nghiệm trong hoạt động tư vấn pháp lý, quản trị doanh nghiệp và giải quyết tranh chấp.</x-client::editable>
          <x-client::editable key="about.story.p2" tag="p" class="text-slate-600 leading-relaxed text-base sm:text-lg font-normal text-justify">Với định hướng xây dựng một tổ chức hành nghề luật chuyên nghiệp, minh bạch và thực tiễn, Minh Anh Vũ cung cấp các giải pháp pháp lý toàn diện cho cá nhân, doanh nghiệp, nhà đầu tư trong và ngoài nước.</x-client::editable>
          <x-client::editable key="about.story.p3" tag="p" :html="true" class="text-slate-600 leading-relaxed text-base sm:text-lg font-normal text-justify">Tại Minh Anh Vũ, chúng tôi không chỉ tập trung vào việc giải quyết một vấn đề pháp lý cụ thể, mà còn chú trọng <strong>nhận diện và kiểm soát rủi ro ngay từ đầu</strong>. Mỗi phương án tư vấn đều được xây dựng trên cơ sở pháp luật, tính khả thi trong thực tiễn và mục tiêu dài hạn của khách hàng.</x-client::editable>
        </div>

        <div class="lg:col-span-6" @if($canEdit) data-outline-group="about-story-media" data-outline-kind="media" data-outline-group-label="Ảnh giới thiệu" @endif>
          <x-client::editable-image key="about.story.image" :src="asset('assets/uploads/2024/10/lich-su-thanh-lap.jpg')" alt="Giới thiệu MAV Law" class="w-full rounded-2xl shadow-xl border border-slate-200 object-cover" />
        </div>
      </div>

      <!-- TẦM NHÌN VÀ SỨ MỆNH -->
      <div id="tam-nhin-su-menh" class="pt-8" @if($canEdit) data-outline-group="about-vision-mission" data-outline-kind="grid" data-outline-group-label="Tầm nhìn & Sứ mệnh" @endif>
        <div class="text-center max-w-3xl mx-auto mb-12 space-y-3" @if($canEdit) data-outline-group="about-vm-header" data-outline-kind="header" data-outline-group-label="Tiêu đề Tầm nhìn & Sứ mệnh" @endif>
          <x-client::editable key="about.vm.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Định vị tương lai</x-client::editable>
          <x-client::editable key="about.vm.title" tag="h2" class="text-2xl sm:text-3xl lg:text-4xl font-bold uppercase text-navy-900 tracking-tight">TẦM NHÌN &amp; SỨ MỆNH</x-client::editable>
          <div class="w-20 h-1 bg-brand-red mx-auto rounded-full"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200 space-y-4 hover:shadow-lg transition" @if($canEdit) data-outline-group="about-vision-card" data-outline-kind="card" data-outline-group-label="Tầm Nhìn Chiến Lược" @endif>
            <div class="w-14 h-14 rounded-full bg-red-100 text-brand-red flex items-center justify-center text-2xl shadow-sm">
              <i class="fa-solid fa-eye"></i>
            </div>
            <x-client::editable key="about.vision.title" tag="h3" class="text-2xl font-bold text-navy-900">Tầm Nhìn Chiến Lược</x-client::editable>
            <x-client::editable key="about.vision.desc" tag="p" class="text-base text-slate-600 leading-relaxed text-justify">Minh Anh Vũ hướng tới mục tiêu trở thành một tổ chức hành nghề luật chuẩn mực, uy tín và thực tiễn hàng đầu tại Việt Nam. Chúng tôi kiến tạo một hệ sinh thái dịch vụ pháp lý toàn diện, nơi mỗi khách hàng cá nhân hay doanh nghiệp đều tìm thấy điểm tựa pháp lý vững vàng để an tâm phát triển.</x-client::editable>
          </div>

          <div class="p-8 rounded-2xl bg-navy-900 text-white space-y-4 shadow-xl border border-navy-800" @if($canEdit) data-outline-group="about-mission-card" data-outline-kind="card" data-outline-group-label="Sứ Mệnh Phụng Sự" @endif>
            <div class="w-14 h-14 rounded-full bg-brand-red text-white flex items-center justify-center text-2xl shadow-lg shadow-red-600/30">
              <i class="fa-solid fa-bullseye"></i>
            </div>
            <x-client::editable key="about.mission.title" tag="h3" class="text-2xl font-bold text-white">Sứ Mệnh Phụng Sự</x-client::editable>
            <x-client::editable key="about.mission.desc" tag="p" class="text-base text-slate-300 leading-relaxed text-justify">Sứ mệnh của Minh Anh Vũ là đồng hành và bảo vệ tối đa quyền, lợi ích hợp pháp của khách hàng bằng tinh thần trách nhiệm cao nhất; kiểm soát và hạn chế rủi ro pháp lý từ sớm; biến những quy định pháp luật phức tạp thành các giải pháp rõ ràng, an toàn và dễ thực thi.</x-client::editable>
          </div>
        </div>
      </div>

      <!-- GIÁ TRỊ CỐT LÕI -->
      <div id="gia-tri-cot-loi" class="pt-8" @if($canEdit) data-outline-group="about-core-values" data-outline-kind="grid" data-outline-group-label="Giá trị cốt lõi" @endif>
        <div class="text-center max-w-3xl mx-auto mb-12 space-y-3" @if($canEdit) data-outline-group="about-values-header" data-outline-kind="header" data-outline-group-label="Tiêu đề Giá trị cốt lõi" @endif>
          <x-client::editable key="about.core_values.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Nguyên tắc hành nghề</x-client::editable>
          <x-client::editable key="about.core_values.title" tag="h2" class="text-2xl sm:text-3xl lg:text-4xl font-bold uppercase text-navy-900 tracking-tight">GIÁ TRỊ CỐT LÕI</x-client::editable>
          <div class="w-20 h-1 bg-brand-red mx-auto rounded-full"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8" @if($canEdit) data-outline-group="about-values-grid" data-outline-kind="items" data-outline-group-label="3 giá trị cốt lõi" @endif>
          @foreach($lists->items('about.core_values.cards', array_keys($coreValueDefaults)) as $id)
            @php
              $d = $coreValueDefaults[$id] ?? $coreValueDefaults['item-1'];
            @endphp
            <x-client::list-item list="about.core_values.cards" :item="$id" class="p-8 rounded-2xl bg-white border border-slate-200 text-center space-y-4 shadow-sm hover:shadow-lg transition" data-outline-group="about-value-card-{{ $id }}" data-outline-kind="card" data-outline-group-label="{{ $d['title'] }}">
              <div class="w-14 h-14 rounded-full bg-red-50 text-brand-red flex items-center justify-center text-2xl mx-auto shadow-sm">
                <i class="fa-solid {{ $d['icon'] }}"></i>
              </div>
              <x-client::editable :key="'about.core_values.cards.' . $id . '.title'" tag="h3" class="text-xl font-bold text-navy-900">{{ $d['title'] }}</x-client::editable>
              <x-client::editable :key="'about.core_values.cards.' . $id . '.desc'" tag="p" class="text-sm text-slate-600 leading-relaxed text-justify">{{ $d['desc'] }}</x-client::editable>
            </x-client::list-item>
          @endforeach
        </div>
      </div>

    </div>
  </main>
@endsection
