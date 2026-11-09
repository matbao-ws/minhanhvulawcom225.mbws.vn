@extends('client.layouts.app')

@section('title', 'Đội ngũ Luật sư & Cố vấn cấp cao - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Đội ngũ luật sư thành viên và cố vấn cấp cao giàu kinh nghiệm thực tế tại Công ty Luật TNHH MTV Minh Anh Vũ.')

@section('content')
  @php
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
    $profileDefaults = [
      'item-1' => [
        'image' => asset('assets/uploads/team/ls_pham_thi_thanh_thao_card.jpg'),
        'name' => 'Luật sư Phạm Thị Thanh Thảo',
        'role' => 'Luật sư Điều hành',
        'bio' => 'Cử nhân kinh tế, Cử nhân luật, Thạc sỹ Trường Đại học Luật TP.HCM. Luật sư đã có nhiều năm kinh nghiệm làm việc trong lĩnh vực pháp lý tại các doanh nghiệp khách sạn, logistic cảng biển và Văn phòng luật; tích lũy nền tảng thực tiễn đa dạng trong tư vấn hoạt động doanh nghiệp, tranh chấp lao động, kinh doanh thương mại, dân sự, đất đai, thừa kế...',
        'meta1_icon' => 'fa-graduation-cap',
        'meta1_text' => 'Thạc sỹ Luật - ĐH Luật TP.HCM',
        'meta2_icon' => 'fa-briefcase',
        'meta2_text' => 'Trực tiếp điều hành hoạt động chuyên môn MAV',
        'meta3_html' => 'Hotline: <strong class="text-navy-900">0397.999.123</strong>',
      ],
      'item-2' => [
        'image' => asset('assets/uploads/team/ls_nguyen_thanh_son_card.jpg'),
        'name' => 'Luật sư Nguyễn Thành Sơn',
        'role' => 'Giám đốc cấp cao',
        'bio' => 'Thạc sỹ Luật; nguyên Kiểm sát viên Viện kiểm sát nhân dân tối cao tại TP.HCM, nguyên Phó Viện trưởng Học viện Tư pháp TP.HCM. Luật sư đã có hơn 30 năm công tác trong ngành luật, trực tiếp tham gia xét xử rất nhiều vụ án lớn về hình sự, dân sự, kinh doanh thương mại.',
        'meta1_icon' => 'fa-graduation-cap',
        'meta1_text' => 'Thạc sỹ Luật học',
        'meta2_icon' => 'fa-certificate',
        'meta2_text' => 'Nguyên KSV VKSND Tối cao, Nguyên Phó Viện trưởng Học viện Tư pháp',
        'meta3_html' => 'Hotline: <strong class="text-navy-900">0397.999.123</strong>',
      ],
      'item-3' => [
        'image' => asset('assets/uploads/team/co_van_ha_thanh_thuy_card.jpg'),
        'name' => 'Bà Hà Thanh Thủy',
        'role' => 'Cố vấn cấp cao',
        'bio' => 'Luật gia Hà Thanh Thủy nguyên là Thẩm phán Tòa án nhân dân TP.HCM. Trong hơn 30 năm công tác tại các cơ quan pháp luật, luật gia đã trực tiếp tham gia xét xử nhiều loại án như dân sự, tranh chấp đất đai, kinh tế, hành chính, lao động và hôn nhân gia đình.',
        'meta1_icon' => 'fa-scale-balanced',
        'meta1_text' => 'Nguyên Thẩm phán TAND TP.HCM',
        'meta2_icon' => 'fa-award',
        'meta2_text' => 'Hơn 30 năm kinh nghiệm xét xử các loại án phức tạp',
        'meta3_html' => '',
      ],
      'item-4' => [
        'image' => asset('assets/uploads/team/co_van_le_thuy_dieu_uyen_card.jpg'),
        'name' => 'Bà Lê Thụy Diệu Uyên',
        'role' => 'Cố vấn cấp cao',
        'bio' => 'Thạc sĩ Kinh tế tại Nhật Bản và Cử nhân Luật tại Việt Nam, hiện đang là Nghiên cứu sinh. Nhiều năm giữ vị trí quản lý điều hành tại doanh nghiệp Nhật Bản; chuyên sâu Tư vấn Quản trị rủi ro doanh nghiệp, tái cấu trúc, mua bán sáp nhập M&A và củng cố nền tảng quản trị.',
        'meta1_icon' => 'fa-graduation-cap',
        'meta1_text' => 'ThS. Kinh tế (Nhật Bản), Cử nhân Luật (VN), NCS',
        'meta2_icon' => 'fa-chart-line',
        'meta2_text' => 'Cố vấn M&A và Quản trị rủi ro doanh nghiệp',
        'meta3_html' => '',
      ],
      'item-5' => [
        'image' => asset('assets/uploads/team/luat_gia_truong_thi_kim_hoa_card.jpg'),
        'name' => 'ThS. Luật gia Trương Thị Kim Hoa',
        'role' => 'Trưởng phòng Đăng ký – Giấy phép',
        'bio' => 'Thạc sỹ Luật học tại Trường Đại học Luật TP.HCM. Nguyên Công chức Tư pháp - hộ tịch tại Ủy ban nhân dân. Phụ trách Phòng Đăng ký – Giấy phép MAV: Giấy phép lưu trú người nước ngoài tại VN (TRC), Giấy phép đăng ký doanh nghiệp, Giấy phép lao động, Giấy phép đầu tư và SHTT.',
        'meta1_icon' => 'fa-graduation-cap',
        'meta1_text' => 'Thạc sỹ Luật học - ĐH Luật TP.HCM',
        'meta2_icon' => 'fa-passport',
        'meta2_text' => 'Trưởng phòng Đăng ký – Giấy phép & Xuất nhập cảnh',
        'meta3_html' => '',
      ],
      'item-6' => [
        'image' => asset('assets/uploads/team/chuyen_vien_huynh_phuong_nguyen_card.jpg'),
        'name' => 'Bà Huỳnh Phương Nguyên',
        'role' => 'Chuyên viên pháp lý',
        'bio' => 'Nhiều năm kinh nghiệm làm việc trong lĩnh vực pháp chế doanh nghiệp, rà soát hệ thống hợp đồng, xây dựng quy chế quản trị nội bộ và kiểm soát tuân thủ pháp lý doanh nghiệp.',
        'meta1_icon' => 'fa-briefcase',
        'meta1_text' => 'Phụ trách mảng Pháp chế doanh nghiệp',
        'meta2_icon' => '',
        'meta2_text' => '',
        'meta3_html' => '',
      ],
    ];
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Đội ngũ Luật sư & Cố vấn',
    'titleKey' => 'lawyers.banner.title',
    'bgKey' => 'lawyers.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Giới thiệu', 'url' => '/gioi-thieu'],
      ['label' => 'Đội ngũ nhân sự', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="lawyers-main" data-outline-label="Đội ngũ Luật sư & Cố vấn" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">

      <div class="text-center max-w-3xl mx-auto space-y-3" @if($canEdit) data-outline-group="lawyers-header" data-outline-kind="header" data-outline-group-label="Tiêu đề Đội ngũ" @endif>
        <x-client::editable key="lawyers.header.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Đội Ngũ Tinh Hoa</x-client::editable>
        <x-client::editable key="lawyers.header.title" tag="h2" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-navy-900 tracking-tight uppercase">LUẬT SƯ THÀNH VIÊN &amp; CỐ VẤN CẤP CAO</x-client::editable>
        <div class="w-20 h-1 bg-brand-red mx-auto rounded-full"></div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8" @if($canEdit) data-outline-group="lawyers-grid" data-outline-kind="items" data-outline-group-label="Danh sách Luật sư & Cố vấn" @endif>
        @foreach($lists->items('lawyers.profiles', array_keys($profileDefaults)) as $id)
          @php
            $d = $profileDefaults[$id] ?? $profileDefaults['item-1'];
          @endphp
          <x-client::list-item list="lawyers.profiles" :item="$id" class="bg-white rounded-2xl p-7 sm:p-8 shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-6 items-center sm:items-start hover:shadow-lg transition" data-outline-group="lawyer-card-{{ $id }}" data-outline-kind="card" data-outline-group-label="{{ $d['name'] }}">
            <x-client::editable-image :key="'lawyers.profiles.' . $id . '.image'" :src="$d['image']" :alt="$d['name']" class="w-48 sm:w-56 aspect-[4/5] rounded-xl object-cover object-top flex-shrink-0 shadow border border-slate-200" />
            <div class="space-y-3 text-center sm:text-left flex-1" @if($canEdit) data-outline-group="lawyer-content-{{ $id }}" data-outline-kind="content" data-outline-group-label="Thông tin nhân sự" @endif>
              <div>
                <x-client::editable :key="'lawyers.profiles.' . $id . '.name'" tag="h3" class="text-xl font-bold text-navy-900">{{ $d['name'] }}</x-client::editable>
                <x-client::editable :key="'lawyers.profiles.' . $id . '.role'" tag="span" class="text-sm uppercase font-bold text-brand-red tracking-wide">{{ $d['role'] }}</x-client::editable>
              </div>
              <x-client::editable :key="'lawyers.profiles.' . $id . '.bio'" tag="p" class="text-base text-slate-600 font-normal leading-relaxed text-justify">{{ $d['bio'] }}</x-client::editable>
              <div class="pt-2 text-sm text-slate-500 space-y-1.5 border-t border-slate-100 font-normal text-left">
                @if(!empty($d['meta1_text']))
                  <div>
                    <i class="fa-solid {{ $d['meta1_icon'] }} text-brand-red mr-2"></i>
                    <x-client::editable :key="'lawyers.profiles.' . $id . '.meta1'" tag="span">{{ $d['meta1_text'] }}</x-client::editable>
                  </div>
                @endif
                @if(!empty($d['meta2_text']))
                  <div>
                    <i class="fa-solid {{ $d['meta2_icon'] }} text-brand-red mr-2"></i>
                    <x-client::editable :key="'lawyers.profiles.' . $id . '.meta2'" tag="span">{{ $d['meta2_text'] }}</x-client::editable>
                  </div>
                @endif
                @if(!empty($d['meta3_html']))
                  <div>
                    <i class="fa-solid fa-phone text-brand-red mr-2"></i>
                    <x-client::editable :key="'lawyers.profiles.' . $id . '.meta3'" tag="span" :html="true">{!! $d['meta3_html'] !!}</x-client::editable>
                  </div>
                @endif
              </div>
            </div>
          </x-client::list-item>
        @endforeach
      </div>

    </div>
  </main>
@endsection
