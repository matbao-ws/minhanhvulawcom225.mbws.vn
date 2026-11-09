@extends('client.layouts.app')

@section('title', 'Danh Mục Dịch Vụ Pháp Lý | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Hệ thống 17 dịch vụ pháp lý chi tiết: Thành lập công ty, Hộ kinh doanh, Giấy phép lao động, Visa, Thẻ tạm trú, Thẻ thường trú, Di trú, Thuế & Báo cáo tài chính, Luật sư cá nhân.')

@section('content')
  @php
    $canEdit = \App\Support\ClientEditorChrome::editorActive();

    $group1Defaults = [
      'item-1' => ['anchor' => 'thanh-lap-cong-ty', 'title' => 'Thành lập công ty', 'desc' => 'Tư vấn chọn loại hình doanh nghiệp (TNHH, Cổ phần), chuẩn bị hồ sơ pháp lý, đăng ký con dấu, mở tài khoản và thủ tục thuế ban đầu trọn gói nhanh chóng.'],
      'item-2' => ['anchor' => 'dang-ky-ho-kinh-doanh', 'title' => 'Đăng ký hộ kinh doanh', 'desc' => 'Hỗ trợ thành lập hộ kinh doanh cá thể tại UBND quận/huyện, đăng ký mã số thuế, chuẩn hóa địa điểm và ngành nghề kinh doanh đúng quy định.'],
      'item-3' => ['anchor' => 'tam-dung-kinh-doanh', 'title' => 'Tạm dừng kinh doanh', 'desc' => 'Thực hiện thông báo tạm ngừng hoạt động kinh doanh cho doanh nghiệp, chi nhánh, VPĐD đúng thời hạn quy định của Luật Doanh nghiệp.'],
      'item-4' => ['anchor' => 'giai-the-cong-ty', 'title' => 'Giải thể công ty', 'desc' => 'Thực hiện thủ tục đóng mã số thuế doanh nghiệp, quyết toán thuế, trả con dấu và hoàn tất thủ tục giải thể doanh nghiệp tại Sở Kế hoạch & Đầu tư.'],
      'item-5' => ['anchor' => 'pha-san-cong-ty', 'title' => 'Phá sản công ty', 'desc' => 'Tư vấn lập hồ sơ yêu cầu mở thủ tục phá sản, đại diện tham gia hội nghị chủ nợ, xử lý nghĩa vụ tài chính và bảo vệ quyền lợi hợp pháp theo Luật Phá sản.'],
    ];

    $group2Defaults = [
      'item-1' => ['anchor' => 'giay-phep-lao-dong', 'title' => 'Giấy phép lao động', 'desc' => 'Tư vấn và thực hiện thủ tục xin chấp thuận nhu cầu sử dụng lao động nước ngoài, xin cấp mới, cấp lại hoặc gia hạn Giấy phép lao động (Work Permit).'],
      'item-2' => ['anchor' => 'dich-vu-visa', 'title' => 'Dịch vụ visa', 'desc' => 'Xin cấp visa công tác (DN), visa đầu tư (ĐT), visa lao động (LĐ), visa thăm thân (TT) và gia hạn thị thực cho người nước ngoài tại Việt Nam.'],
      'item-3' => ['anchor' => 'the-tam-tru', 'title' => 'Thẻ tạm trú', 'desc' => 'Thủ tục xin cấp thẻ tạm trú (TRC) từ 1 đến 5 năm cho chuyên gia nước ngoài, nhà đầu tư, người lao động và thân nhân đi kèm.'],
      'item-4' => ['anchor' => 'the-thuong-tru', 'title' => 'Thẻ thường trú', 'desc' => 'Tư vấn hồ sơ và thủ tục xin cấp Thẻ thường trú tại Việt Nam cho các đối tượng đủ điều kiện theo quy định của Luật Xuất nhập cảnh.'],
      'item-5' => ['anchor' => 'dich-vu-di-tru', 'title' => 'Dịch vụ di trú', 'desc' => 'Tư vấn tổng thể các thủ tục di trú, định cư, xác nhận quốc tịch, giấy miễn thị thực và giải quyết các vướng mắc về nhập cảnh, xuất cảnh.'],
      'item-6' => ['anchor' => 'ly-lich-tu-phap', 'title' => 'Lý lịch tư pháp', 'desc' => 'Đại diện xin cấp Phiếu lý lịch tư pháp số 1 và số 2 nhanh chóng cho công dân Việt Nam và người nước ngoài cư trú tại Việt Nam.'],
    ];

    $group3Defaults = [
      'item-1' => ['anchor' => 'bao-cao-tai-chinh', 'title' => 'Báo cáo tài chính', 'desc' => 'Lập báo cáo tài chính năm, soát xét sổ sách kế toán, cân đối chi phí hợp lý hợp lệ và tư vấn kiểm toán báo cáo tài chính doanh nghiệp.'],
      'item-2' => ['anchor' => 'bao-cao-thue', 'title' => 'Báo cáo thuế', 'desc' => 'Dịch vụ kê khai thuế GTGT, thuế TNCN, thuế TNDN định kỳ theo tháng/quý; quyết toán thuế cuối năm chính xác, đúng hạn, tránh phạt chậm nộp.'],
      'item-3' => ['anchor' => 'hoan-thue-tncn', 'title' => 'Hoàn thuế thu nhập cá nhân', 'desc' => 'Thực hiện hồ sơ hoàn thuế TNCN cho cá nhân, chuyên gia nước ngoài và người có thu nhập cao từ tiền lương, tiền công nhanh chóng.'],
    ];

    $group4Defaults = [
      'item-1' => ['anchor' => 'luat-su-ca-nhan', 'title' => 'Luật sư cá nhân', 'desc' => 'Dịch vụ luật sư riêng đồng hành bảo vệ quyền lợi cá nhân và gia tộc trong các giao dịch bất động sản, thừa kế, tài sản riêng và đời sống dân sự.'],
      'item-2' => ['anchor' => 'linh-vuc-hanh-chinh', 'title' => 'Lĩnh vực Hành chính', 'desc' => 'Tư vấn khiếu nại, khởi kiện các quyết định hành chính, hành vi hành chính trái luật của cơ quan nhà nước; thực hiện các thủ tục hành chính phức tạp.'],
      'item-3' => ['anchor' => 'khai-sinh-ho-tich', 'title' => 'Làm Giấy Khai sinh - Hộ tịch', 'desc' => 'Hỗ trợ đăng ký khai sinh có yếu tố nước ngoài, đăng ký kết hôn, đính chính hộ tịch, nhận cha mẹ con và các thủ tục tư pháp hộ tịch khác.'],
    ];
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Hệ Thống Dịch Vụ Pháp Lý Trọng Tâm',
    'titleKey' => 'services.banner.title',
    'bgKey' => 'services.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Dịch Vụ', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="services-main" data-outline-label="Hệ thống Dịch vụ" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

        <div class="lg:col-span-8 space-y-12" @if($canEdit) data-outline-group="services-content" data-outline-kind="column" data-outline-group-label="Danh mục 17 dịch vụ" @endif>
          <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="services-overview" data-outline-kind="card" data-outline-group-label="Tổng quan dịch vụ" @endif>
            <x-client::editable key="services.overview.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Hệ sinh thái pháp lý MAV</x-client::editable>
            <x-client::editable key="services.overview.title" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900">Danh Mục 17 Dịch Vụ Pháp Lý Trọng Tâm</x-client::editable>
            <x-client::editable key="services.overview.desc" tag="p" class="text-slate-600 leading-relaxed text-base text-justify">Minh Anh Vũ cung cấp đầy đủ các dịch vụ từ đăng ký kinh doanh, giấy phép đầu tư, di trú người nước ngoài, quyết toán thuế cho đến các thủ tục tư pháp hộ tịch và luật sư riêng cá nhân.</x-client::editable>
          </div>

          <!-- Group 1 -->
          <div class="space-y-6" @if($canEdit) data-outline-group="services-group-1" data-outline-kind="grid" data-outline-group-label="Doanh Nghiệp & Đầu Tư" @endif>
            <div class="flex items-center gap-3 pb-3 border-b-2 border-brand-red">
              <div class="w-10 h-10 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg">
                <i class="fa-solid fa-briefcase"></i>
              </div>
              <x-client::editable key="services.group1.title" tag="h3" class="text-xl sm:text-2xl font-bold uppercase text-navy-900 tracking-tight">Doanh Nghiệp &amp; Đầu Tư</x-client::editable>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" @if($canEdit) data-outline-group="services-group-1-items" data-outline-kind="items" data-outline-group-label="Dịch vụ Doanh nghiệp & Đầu tư" @endif>
              @foreach($lists->items('services.group1.items', array_keys($group1Defaults)) as $id)
                @php
                  $d = $group1Defaults[$id] ?? $group1Defaults['item-1'];
                @endphp
                <x-client::list-item list="services.group1.items" :item="$id" id="{{ $d['anchor'] }}" class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition space-y-3 scroll-mt-28">
                  <h4 class="text-lg font-bold text-navy-900 flex items-center gap-2">
                    <i class="fa-solid fa-check text-brand-red text-sm"></i>
                    <x-client::editable :key="'services.group1.items.' . $id . '.title'" tag="span">{{ $d['title'] }}</x-client::editable>
                  </h4>
                  <x-client::editable :key="'services.group1.items.' . $id . '.desc'" tag="p" class="text-sm text-slate-600 leading-relaxed font-normal text-justify">{{ $d['desc'] }}</x-client::editable>
                  <div class="pt-2">
                    <x-client::editable :key="'services.group1.items.' . $id . '.cta'" tag="a" href="{{ route('client.contact', ['locale' => app()->getLocale()]) }}" class="text-xs uppercase font-bold text-brand-red hover:underline">Đăng ký tư vấn dịch vụ &rarr;</x-client::editable>
                  </div>
                </x-client::list-item>
              @endforeach
            </div>
          </div>

          <!-- Group 2 -->
          <div class="space-y-6" @if($canEdit) data-outline-group="services-group-2" data-outline-kind="grid" data-outline-group-label="Giấy Phép & Di Trú" @endif>
            <div class="flex items-center gap-3 pb-3 border-b-2 border-brand-red">
              <div class="w-10 h-10 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg">
                <i class="fa-solid fa-passport"></i>
              </div>
              <x-client::editable key="services.group2.title" tag="h3" class="text-xl sm:text-2xl font-bold uppercase text-navy-900 tracking-tight">Giấy Phép &amp; Di Trú</x-client::editable>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" @if($canEdit) data-outline-group="services-group-2-items" data-outline-kind="items" data-outline-group-label="Dịch vụ Giấy phép & Di trú" @endif>
              @foreach($lists->items('services.group2.items', array_keys($group2Defaults)) as $id)
                @php
                  $d = $group2Defaults[$id] ?? $group2Defaults['item-1'];
                @endphp
                <x-client::list-item list="services.group2.items" :item="$id" id="{{ $d['anchor'] }}" class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition space-y-3 scroll-mt-28">
                  <h4 class="text-lg font-bold text-navy-900 flex items-center gap-2">
                    <i class="fa-solid fa-check text-brand-red text-sm"></i>
                    <x-client::editable :key="'services.group2.items.' . $id . '.title'" tag="span">{{ $d['title'] }}</x-client::editable>
                  </h4>
                  <x-client::editable :key="'services.group2.items.' . $id . '.desc'" tag="p" class="text-sm text-slate-600 leading-relaxed font-normal text-justify">{{ $d['desc'] }}</x-client::editable>
                  <div class="pt-2">
                    <x-client::editable :key="'services.group2.items.' . $id . '.cta'" tag="a" href="{{ route('client.contact', ['locale' => app()->getLocale()]) }}" class="text-xs uppercase font-bold text-brand-red hover:underline">Đăng ký tư vấn dịch vụ &rarr;</x-client::editable>
                  </div>
                </x-client::list-item>
              @endforeach
            </div>
          </div>

          <!-- Group 3 -->
          <div class="space-y-6" @if($canEdit) data-outline-group="services-group-3" data-outline-kind="grid" data-outline-group-label="Kế Toán, Báo Cáo Thuế & Tài Chính" @endif>
            <div class="flex items-center gap-3 pb-3 border-b-2 border-brand-red">
              <div class="w-10 h-10 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg">
                <i class="fa-solid fa-file-invoice-dollar"></i>
              </div>
              <x-client::editable key="services.group3.title" tag="h3" class="text-xl sm:text-2xl font-bold uppercase text-navy-900 tracking-tight">Kế Toán, Báo Cáo Thuế &amp; Tài Chính</x-client::editable>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" @if($canEdit) data-outline-group="services-group-3-items" data-outline-kind="items" data-outline-group-label="Dịch vụ Kế toán & Thuế" @endif>
              @foreach($lists->items('services.group3.items', array_keys($group3Defaults)) as $id)
                @php
                  $d = $group3Defaults[$id] ?? $group3Defaults['item-1'];
                @endphp
                <x-client::list-item list="services.group3.items" :item="$id" id="{{ $d['anchor'] }}" class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition space-y-3 scroll-mt-28">
                  <h4 class="text-lg font-bold text-navy-900 flex items-center gap-2">
                    <i class="fa-solid fa-check text-brand-red text-sm"></i>
                    <x-client::editable :key="'services.group3.items.' . $id . '.title'" tag="span">{{ $d['title'] }}</x-client::editable>
                  </h4>
                  <x-client::editable :key="'services.group3.items.' . $id . '.desc'" tag="p" class="text-sm text-slate-600 leading-relaxed font-normal text-justify">{{ $d['desc'] }}</x-client::editable>
                  <div class="pt-2">
                    <x-client::editable :key="'services.group3.items.' . $id . '.cta'" tag="a" href="{{ route('client.contact', ['locale' => app()->getLocale()]) }}" class="text-xs uppercase font-bold text-brand-red hover:underline">Đăng ký tư vấn dịch vụ &rarr;</x-client::editable>
                  </div>
                </x-client::list-item>
              @endforeach
            </div>
          </div>

          <!-- Group 4 -->
          <div class="space-y-6" @if($canEdit) data-outline-group="services-group-4" data-outline-kind="grid" data-outline-group-label="Dân Sự, Hành Chính & Hộ Tịch" @endif>
            <div class="flex items-center gap-3 pb-3 border-b-2 border-brand-red">
              <div class="w-10 h-10 rounded-lg bg-red-50 text-brand-red flex items-center justify-center text-lg">
                <i class="fa-solid fa-landmark"></i>
              </div>
              <x-client::editable key="services.group4.title" tag="h3" class="text-xl sm:text-2xl font-bold uppercase text-navy-900 tracking-tight">Dân Sự, Hành Chính &amp; Hộ Tịch</x-client::editable>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" @if($canEdit) data-outline-group="services-group-4-items" data-outline-kind="items" data-outline-group-label="Dịch vụ Dân sự & Hộ tịch" @endif>
              @foreach($lists->items('services.group4.items', array_keys($group4Defaults)) as $id)
                @php
                  $d = $group4Defaults[$id] ?? $group4Defaults['item-1'];
                @endphp
                <x-client::list-item list="services.group4.items" :item="$id" id="{{ $d['anchor'] }}" class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition space-y-3 scroll-mt-28">
                  <h4 class="text-lg font-bold text-navy-900 flex items-center gap-2">
                    <i class="fa-solid fa-check text-brand-red text-sm"></i>
                    <x-client::editable :key="'services.group4.items.' . $id . '.title'" tag="span">{{ $d['title'] }}</x-client::editable>
                  </h4>
                  <x-client::editable :key="'services.group4.items.' . $id . '.desc'" tag="p" class="text-sm text-slate-600 leading-relaxed font-normal text-justify">{{ $d['desc'] }}</x-client::editable>
                  <div class="pt-2">
                    <x-client::editable :key="'services.group4.items.' . $id . '.cta'" tag="a" href="{{ route('client.contact', ['locale' => app()->getLocale()]) }}" class="text-xs uppercase font-bold text-brand-red hover:underline">Đăng ký tư vấn dịch vụ &rarr;</x-client::editable>
                  </div>
                </x-client::list-item>
              @endforeach
            </div>
          </div>

        </div>

        <div class="lg:col-span-4">
          @include('client.partials.service-sidebar', ['activeSlug' => 'dich-vu'])
        </div>

      </div>
    </div>
  </main>
@endsection
