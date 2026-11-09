@extends('client.layouts.app')

@section('title', 'Tranh Tụng & Tư Vấn Giải Quyết Tranh Chấp | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Dịch vụ Luật sư Tranh tụng và Tư vấn giải quyết tranh chấp: Tranh chấp công ty, hợp đồng dân sự & thương mại, thừa kế di chúc, lao động, hàng hải.')

@section('content')
  @php
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
    $itemDefaults = [
      'item-1' => [
        'anchor' => 'tranh-chap-cong-ty',
        'title' => '1. Tranh Chấp Công Ty & Cổ Đông',
        'icon' => 'fa-building',
        'desc' => 'Giải quyết các mâu thuẫn phát sinh nội bộ doanh nghiệp: tranh chấp quyền biểu quyết, tư cách thành viên/cổ đông, hiệu lực nghị quyết ĐHĐCĐ/HĐTV, chuyển nhượng phần vốn góp và bồi thường thiệt hại của người quản lý doanh nghiệp.',
      ],
      'item-2' => [
        'anchor' => 'tranh-chap-hop-dong',
        'title' => '2. Tranh Chấp Hợp Đồng Dân Sự & Thương Mại',
        'icon' => 'fa-file-signature',
        'desc' => 'Tư vấn và đại diện giải quyết các vụ kiện vi phạm hợp đồng mua bán hàng hóa, cung ứng dịch vụ, hợp đồng hợp tác đầu tư (BCC), hợp đồng vay tài sản, vi phạm tiến độ thanh toán, phạt vi phạm và yêu cầu bồi thường thiệt hại.',
      ],
      'item-3' => [
        'anchor' => 'thua-ke-di-chuc',
        'title' => '3. Tranh Chấp Quyền Thừa Kế, Di Chúc',
        'icon' => 'fa-scroll',
        'desc' => 'Bảo vệ quyền lợi hợp pháp trong việc phân chia di sản thừa kế theo di chúc và theo pháp luật; khởi kiện yêu cầu chia thừa kế đất đai, nhà ở, cổ phần; xác định hiệu lực của di chúc và hàng thừa kế hợp pháp.',
      ],
      'item-4' => [
        'anchor' => 'tranh-chap-lao-dong',
        'title' => '4. Tranh Chấp Lao Động',
        'icon' => 'fa-user-tie',
        'desc' => 'Đại diện cho Người sử dụng lao động hoặc Người lao động trong các vụ án đơn phương chấm dứt HĐLĐ trái pháp luật, kỷ luật sa thải, nợ lương, chế độ bảo hiểm và tranh chấp cam kết bảo mật thông tin (NDA/NCA).',
      ],
      'item-5' => [
        'anchor' => 'tranh-chap-hang-hai',
        'title' => '5. Tranh Chấp Hàng Hải & Vận Tải Biển',
        'icon' => 'fa-anchor',
        'desc' => 'Tư vấn xử lý các tranh chấp phát sinh từ hợp đồng thuê tàu (Charter Party), vận đơn đường biển (Bill of Lading), tổn thất chung, hư hỏng mất mát hàng hóa và giải quyết tai nạn đâm va trên biển tại Tòa án hoặc Trọng tài.',
      ],
    ];
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Tranh Tụng & Tư Vấn Giải Quyết Tranh Chấp',
    'titleKey' => 'litigation.banner.title',
    'bgKey' => 'litigation.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Tranh tụng & Tư vấn', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="litigation-main" data-outline-label="Tranh tụng & Tư vấn" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

        <div class="lg:col-span-8 space-y-8" @if($canEdit) data-outline-group="litigation-content" data-outline-kind="column" data-outline-group-label="Nội dung Tranh tụng" @endif>
          <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="litigation-overview" data-outline-kind="card" data-outline-group-label="Tổng quan dịch vụ" @endif>
            <x-client::editable key="litigation.overview.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Tổng quan dịch vụ</x-client::editable>
            <x-client::editable key="litigation.overview.title" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900">Chiến Lược Tranh Tụng &amp; Giải Quyết Tranh Chấp Toàn Diện</x-client::editable>
            <x-client::editable key="litigation.overview.desc" tag="p" class="text-slate-600 leading-relaxed text-base text-justify">Với sự tham gia trực tiếp của đội ngũ luật sư giàu kinh nghiệm tranh tụng thực chiến và các Cố vấn cấp cao nguyên là Thẩm phán, Kiểm sát viên, Minh Anh Vũ tự hào mang đến cho khách hàng đường lối tố tụng chuẩn xác, vững chắc và bảo vệ tối đa quyền lợi trong mọi giai đoạn.</x-client::editable>
          </div>

          <div class="space-y-6" @if($canEdit) data-outline-group="litigation-items" data-outline-kind="items" data-outline-group-label="5 lĩnh vực tranh chấp" @endif>
            @foreach($lists->items('litigation.items', array_keys($itemDefaults)) as $id)
              @php
                $d = $itemDefaults[$id] ?? $itemDefaults['item-1'];
              @endphp
              <x-client::list-item list="litigation.items" :item="$id" id="{{ $d['anchor'] }}" class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4 scroll-mt-28" data-outline-group="litigation-item-{{ $id }}" data-outline-kind="card" data-outline-group-label="{{ $d['title'] }}">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 rounded-xl bg-red-50 text-brand-red flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid {{ $d['icon'] }}"></i>
                  </div>
                  <x-client::editable :key="'litigation.items.' . $id . '.title'" tag="h3" class="text-xl sm:text-2xl font-bold text-navy-900">{{ $d['title'] }}</x-client::editable>
                </div>
                <x-client::editable :key="'litigation.items.' . $id . '.desc'" tag="p" class="text-base text-slate-600 leading-relaxed text-justify font-normal">{{ $d['desc'] }}</x-client::editable>
                <div class="pt-2">
                  <x-client::editable :key="'litigation.items.' . $id . '.cta'" tag="a" :href="route('client.contact', ['locale' => app()->getLocale()])" class="inline-flex items-center gap-2 text-brand-red font-bold hover:underline text-sm uppercase">Liên hệ luật sư phụ trách mảng này &rarr;</x-client::editable>
                </div>
              </x-client::list-item>
            @endforeach
          </div>
        </div>

        <div class="lg:col-span-4">
          @include('client.partials.service-sidebar', ['activeSlug' => 'tranh-tung-va-tu-van'])
        </div>

      </div>
    </div>
  </main>
@endsection
