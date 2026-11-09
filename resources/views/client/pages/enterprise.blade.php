@extends('client.layouts.app')

@section('title', 'Pháp Lý Doanh Nghiệp | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Dịch vụ pháp lý doanh nghiệp chuyên nghiệp: Phòng pháp chế thuê ngoài, Tư vấn pháp luật hàng tháng, Kiểm tra pháp lý Legal Health Check, Huấn luyện Đào tạo pháp luật.')

@section('content')
  @php
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
    $itemDefaults = [
      'item-1' => [
        'anchor' => 'phap-che-thue-ngoai',
        'title' => '1. Phòng Pháp Chế Thuê Ngoài Trọn Gói',
        'icon' => 'fa-users-gear',
        'desc' => 'Cung cấp toàn bộ chức năng của một phòng pháp chế chuyên nghiệp cho doanh nghiệp vừa và nhỏ (SME), Startup: rà soát hợp đồng thường nhật, chuẩn hóa biểu mẫu pháp lý, giải quyết thắc mắc lao động, tư vấn thủ tục hành chính với chi phí chỉ bằng một phần nhỏ chi phí duy trì nhân sự nội bộ.',
      ],
      'item-2' => [
        'anchor' => 'tu-van-hang-thang',
        'title' => '2. Tư Vấn Pháp Luật Hàng Tháng',
        'icon' => 'fa-calendar-days',
        'desc' => 'Gói dịch vụ đồng hành liên tục theo tháng, dành cho các doanh nghiệp có nhu cầu pháp lý thường xuyên: tư vấn bằng văn bản/email, tham gia họp đàm phán hợp đồng quan trọng, cập nhật chính sách pháp luật mới ảnh hưởng trực tiếp tới ngành nghề kinh doanh.',
      ],
      'item-3' => [
        'anchor' => 'legal-health-check',
        'title' => '3. Kiểm Tra Pháp Lý Toàn Diện “Legal Health Check”',
        'icon' => 'fa-stethoscope',
        'desc' => 'Dịch vụ rà soát tổng thể sức khỏe pháp lý của doanh nghiệp: đánh giá mức độ tuân thủ pháp luật về hồ sơ doanh nghiệp, cơ cấu sở hữu vốn, tài sản trí tuệ, hệ thống hợp đồng, quy chế lao động nội bộ, phòng ngừa rủi ro bị xử phạt hoặc tranh chấp tiềm ẩn.',
      ],
      'item-4' => [
        'anchor' => 'huan-luyen-dao-tao',
        'title' => '4. Huấn Luyện & Đào Tạo Pháp Luật Nội Bộ',
        'icon' => 'fa-chalkboard-user',
        'desc' => 'Thiết kế chương trình đào tạo in-house theo nhu cầu thực tế của từng doanh nghiệp: Kỹ năng soạn thảo và đàm phán hợp đồng kinh tế, Pháp luật lao động cho phòng Nhân sự, Quản trị rủi ro pháp lý dành cho Ban Giám đốc và Cấp quản lý.',
      ],
    ];
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Dịch Vụ Pháp Lý Doanh Nghiệp',
    'titleKey' => 'enterprise.banner.title',
    'bgKey' => 'enterprise.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Doanh Nghiệp', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="enterprise-main" data-outline-label="Pháp lý Doanh nghiệp" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

        <div class="lg:col-span-8 space-y-8" @if($canEdit) data-outline-group="enterprise-content" data-outline-kind="column" data-outline-group-label="Nội dung Doanh nghiệp" @endif>
          <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="enterprise-overview" data-outline-kind="card" data-outline-group-label="Giải pháp doanh nghiệp" @endif>
            <x-client::editable key="enterprise.overview.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Giải pháp doanh nghiệp</x-client::editable>
            <x-client::editable key="enterprise.overview.title" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900">Nền Tảng Pháp Lý Vững Vàng Cho Doanh Nghiệp Phát Triển</x-client::editable>
            <x-client::editable key="enterprise.overview.desc" tag="p" class="text-slate-600 leading-relaxed text-base text-justify">Minh Anh Vũ hiểu rằng pháp chế không phải là rào cản cản trở kinh doanh, mà là công cụ bảo vệ thành quả và gia tăng giá trị cạnh tranh cho doanh nghiệp. Chúng tôi cung cấp các giải pháp tối ưu giúp chủ doanh nghiệp an tâm tập trung mở rộng thị trường.</x-client::editable>
          </div>

          <div class="space-y-6" @if($canEdit) data-outline-group="enterprise-items" data-outline-kind="items" data-outline-group-label="4 giải pháp doanh nghiệp" @endif>
            @foreach($lists->items('enterprise.items', array_keys($itemDefaults)) as $id)
              @php
                $d = $itemDefaults[$id] ?? $itemDefaults['item-1'];
              @endphp
              <x-client::list-item list="enterprise.items" :item="$id" id="{{ $d['anchor'] }}" class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4 scroll-mt-28" data-outline-group="enterprise-item-{{ $id }}" data-outline-kind="card" data-outline-group-label="{{ $d['title'] }}">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 rounded-xl bg-red-50 text-brand-red flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid {{ $d['icon'] }}"></i>
                  </div>
                  <x-client::editable :key="'enterprise.items.' . $id . '.title'" tag="h3" class="text-xl sm:text-2xl font-bold text-navy-900">{{ $d['title'] }}</x-client::editable>
                </div>
                <x-client::editable :key="'enterprise.items.' . $id . '.desc'" tag="p" class="text-base text-slate-600 leading-relaxed text-justify font-normal">{{ $d['desc'] }}</x-client::editable>
                <div class="pt-2">
                  <x-client::editable :key="'enterprise.items.' . $id . '.cta'" tag="a" :href="route('client.contact', ['locale' => app()->getLocale()])" class="inline-flex items-center gap-2 text-brand-red font-bold hover:underline text-sm uppercase">Nhận báo giá dịch vụ doanh nghiệp &rarr;</x-client::editable>
                </div>
              </x-client::list-item>
            @endforeach
          </div>
        </div>

        <div class="lg:col-span-4">
          @include('client.partials.service-sidebar', ['activeSlug' => 'doanh-nghiep'])
        </div>

      </div>
    </div>
  </main>
@endsection
