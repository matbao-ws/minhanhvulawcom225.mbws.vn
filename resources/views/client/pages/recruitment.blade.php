@extends('client.layouts.app')

@section('title', 'Tuyển Dụng & Đào Tạo - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Thông báo tuyển dụng luật sư thành viên, chuyên viên pháp lý và tiếp nhận thực tập sinh tại Công ty Luật TNHH MTV Minh Anh Vũ.')

@section('content')
  @php
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
    $jobDefaults = [
      'item-1' => [
        'badge' => 'Vị trí 01',
        'type' => 'Toàn thời gian / Bán thời gian',
        'title' => 'Thực Tập Sinh Pháp Lý (Sinh viên Luật trong & ngoài nước)',
        'desc' => 'Được trực tiếp hướng dẫn bởi Luật sư Điều hành, nguyên Thẩm phán và Kiểm sát viên; tham gia hỗ trợ hồ sơ thực tế.',
      ],
      'item-2' => [
        'badge' => 'Vị trí 02',
        'type' => 'Toàn thời gian',
        'title' => 'Người Tập Sự Hành Nghề Luật Sư',
        'desc' => 'Học hỏi kỹ năng tranh tụng thực chiến, nghiên cứu án lệ, soạn thảo đơn từ tố tụng và rà soát hợp đồng thương mại.',
      ],
      'item-3' => [
        'badge' => 'Vị trí 03',
        'type' => 'Toàn thời gian',
        'title' => 'Luật Sư Thành Viên & Chuyên Viên Pháp Lý',
        'desc' => 'Môi trường làm việc nhân văn, chuyên nghiệp, thu nhập hấp dẫn và cơ hội thăng tiến lên cấp quản lý.',
      ],
    ];
  @endphp

  @include('client.partials.page-banner', [
    'title' => 'Tuyển Dụng & Đào Tạo',
    'titleKey' => 'recruitment.banner.title',
    'bgKey' => 'recruitment.banner.bg',
    'breadcrumbs' => [
      ['label' => 'Tuyển dụng', 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="recruitment-main" data-outline-label="Tuyển dụng & Đào tạo" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

        <div class="lg:col-span-8 bg-white rounded-2xl p-8 sm:p-10 shadow-sm border border-slate-200 space-y-8" @if($canEdit) data-outline-group="recruitment-content" data-outline-kind="column" data-outline-group-label="Nội dung Tuyển dụng" @endif>
          <div class="rounded-xl overflow-hidden shadow-sm">
            <x-client::editable-image key="recruitment.hero.image" :src="asset('assets/uploads/services/recruitment_banner.jpg')" alt="Tuyển dụng Luật Minh Anh Vũ" class="w-full h-72 object-cover" />
          </div>

          <div class="space-y-3">
            <x-client::editable key="recruitment.header.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Cơ hội nghề nghiệp</x-client::editable>
            <x-client::editable key="recruitment.header.title" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900 tracking-tight">THÔNG BÁO TUYỂN DỤNG &amp; TIẾP NHẬN THỰC TẬP</x-client::editable>
            <x-client::editable key="recruitment.header.slogan" tag="p" class="font-accent text-2xl text-brand-red font-semibold">Đồng hành cùng Minh Anh Vũ – Nơi phát triển sự nghiệp pháp lý vững chắc.</x-client::editable>
          </div>

          <div class="text-slate-600 font-normal space-y-5 leading-relaxed text-justify text-base sm:text-lg">
            <x-client::editable key="recruitment.intro.p1" tag="p">Công ty Luật TNHH MTV MINH ANH VŨ thường xuyên tạo điều kiện cho các sinh viên Luật trong và ngoài nước đến Công ty thực tập để học hỏi và tích lũy kinh nghiệm thực tiễn.</x-client::editable>
            <x-client::editable key="recruitment.intro.p2" tag="p">Trong quá trình thực tập, các sinh viên xuất sắc luôn được chào đón để trở thành thành viên chính thức của Công ty. Với khả năng phát triển nghề nghiệp và tiến trình phát triển dự kiến trong tương lai, chúng tôi định hướng xây dựng quy mô tổ chức chuyên nghiệp, hiện đại và chuẩn mực.</x-client::editable>
            <x-client::editable key="recruitment.intro.p3" tag="p" :html="true">Do đó, nếu các bạn có mong muốn được thực tập, tập sự hành nghề luật sư hoặc muốn trở thành thành viên chính thức của <strong>Công ty Luật TNHH MTV Minh Anh Vũ</strong>, xin vui lòng liên hệ hoặc gửi Hồ sơ về văn phòng chúng tôi.</x-client::editable>
          </div>

          <!-- Recruitment Cards -->
          <div class="space-y-4 pt-4" @if($canEdit) data-outline-group="recruitment-jobs" data-outline-kind="items" data-outline-group-label="Các vị trí tuyển dụng" @endif>
            @foreach($lists->items('recruitment.jobs', array_keys($jobDefaults)) as $id)
              @php
                $d = $jobDefaults[$id] ?? $jobDefaults['item-1'];
              @endphp
              <x-client::list-item list="recruitment.jobs" :item="$id" class="p-6 rounded-xl bg-slate-50 border border-slate-200 hover:border-brand-red transition space-y-2" data-outline-group="recruitment-job-{{ $id }}" data-outline-kind="card" data-outline-group-label="{{ $d['title'] }}">
                <div class="flex items-center justify-between">
                  <x-client::editable :key="'recruitment.jobs.' . $id . '.badge'" tag="span" class="text-xs uppercase font-bold text-brand-red bg-red-50 px-2.5 py-1 rounded">{{ $d['badge'] }}</x-client::editable>
                  <x-client::editable :key="'recruitment.jobs.' . $id . '.type'" tag="span" class="text-xs text-slate-500 font-medium">{{ $d['type'] }}</x-client::editable>
                </div>
                <x-client::editable :key="'recruitment.jobs.' . $id . '.title'" tag="h3" class="text-lg font-bold text-navy-900">{{ $d['title'] }}</x-client::editable>
                <x-client::editable :key="'recruitment.jobs.' . $id . '.desc'" tag="p" class="text-sm text-slate-600 font-normal">{{ $d['desc'] }}</x-client::editable>
              </x-client::list-item>
            @endforeach
          </div>

          <div class="p-6 rounded-xl bg-navy-900 text-white space-y-3" @if($canEdit) data-outline-group="recruitment-apply" data-outline-kind="card" data-outline-group-label="Cách thức nộp hồ sơ" @endif>
            <x-client::editable key="recruitment.apply.title" tag="h4" class="text-lg font-bold text-white">Cách Thức Nộp Hồ Sơ:</x-client::editable>
            <x-client::editable key="recruitment.apply.desc" tag="p" :html="true" class="text-sm text-slate-300 leading-relaxed">Ứng viên vui lòng gửi CV và Thư ứng tuyển về hòm thư điện tử: <strong class="text-white">trolyluatsu@minhanhvulaw.com</strong> (Tiêu đề: [ỨNG TUYỂN] - Họ và tên - Vị trí ứng tuyển). Hoặc nộp trực tiếp tại Văn phòng Công ty: 60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh.</x-client::editable>
          </div>
        </div>

        <div class="lg:col-span-4">
          @include('client.partials.news-sidebar')
        </div>
      </div>
    </div>
  </main>
@endsection
