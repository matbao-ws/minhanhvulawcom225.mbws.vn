@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $homeThumbMap = [
    'chay-quan-karaoke-chet-13-nguoi-trach-nhiem-thuoc-ve-ai' => asset('assets/uploads/services/service_tranh_tung.jpg'),
    'xu-phat-nguoi-quay-dang-clip-doan-xe-chu-tich-quoc-hoi' => asset('assets/uploads/services/service_tu_van_thuong_xuyen.jpg'),
    'chieu-bat-toi-pham-co-mot-khong-hai' => asset('assets/uploads/services/service_luat_su_rieng.jpg'),
  ];
@endphp
<!-- SECTION: TIN TỨC MỚI NHẤT -->
<section class="py-16 sm:py-20 bg-slate-50 border-t border-slate-200" @if($canEdit) data-outline-section="home-latest-news" data-outline-label="Tin Tức Mới Nhất" @endif>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">

    <div class="text-center max-w-3xl mx-auto space-y-3" @if($canEdit) data-outline-group="home-news-header" data-outline-kind="header" data-outline-group-label="Tiêu đề Tin Tức" @endif>
      <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold uppercase text-navy-900 tracking-tight relative inline-block">
        <x-client::editable key="home.latest_news.title" tag="span">Tin Tức Mới Nhất</x-client::editable>
        <span class="block w-20 h-1 bg-brand-red mx-auto mt-3 rounded-full"></span>
      </h2>
      <x-client::editable key="home.latest_news.subtitle" tag="p" class="text-base sm:text-lg text-slate-600 font-normal">Cập nhật kiến thức pháp lý mới nhất từ chuyên gia.</x-client::editable>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      @foreach(($featuredPosts ?? collect())->take(3) as $post)
        @include('client.partials.post-card', [
          'post' => $post,
          'imageHeight' => 'h-52',
          'thumbOverride' => $homeThumbMap[$post->slug] ?? null,
        ])
      @endforeach
    </div>

    <div class="text-center pt-4">
      <x-client::editable key="home.latest_news.cta" tag="a" :href="route('client.research', ['locale' => $locale])" class="inline-flex items-center gap-2 px-8 py-3.5 bg-navy-900 hover:bg-navy-800 text-white font-bold rounded-lg text-sm uppercase tracking-wider shadow-md transition">Xem Tất Cả Bài Viết Nghiên Cứu &rarr;</x-client::editable>
    </div>

  </div>
</section>
