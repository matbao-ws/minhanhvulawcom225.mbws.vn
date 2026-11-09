@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $serviceDefaults = [
    'item-1' => ['name' => 'Luật sư Tranh tụng', 'badge' => 'Ưu tiên', 'badge_cls' => 'bg-red-50 text-brand-red', 'href' => route('client.litigation.alias', ['locale' => $locale])],
    'item-2' => ['name' => 'Tư vấn pháp lý thường xuyên', 'badge' => 'Doanh nghiệp', 'badge_cls' => 'bg-slate-100 text-slate-600', 'href' => route('client.enterprise.retainer', ['locale' => $locale])],
    'item-3' => ['name' => 'Dịch vụ Luật sư riêng', 'badge' => 'Cá nhân', 'badge_cls' => 'bg-slate-100 text-slate-600', 'href' => route('client.services.personal', ['locale' => $locale])],
    'item-4' => ['name' => 'Phòng pháp chế thuê ngoài', 'badge' => 'SME/Startup', 'badge_cls' => 'bg-slate-100 text-slate-600', 'href' => route('client.enterprise.outsource', ['locale' => $locale])],
    'item-5' => ['name' => 'Dịch vụ Thủ tục – Giấy phép', 'badge' => 'TRC/Giấy phép', 'badge_cls' => 'bg-slate-100 text-slate-600', 'href' => route('client.services.license', ['locale' => $locale])],
    'item-6' => ['name' => 'Đào tạo pháp lý nội bộ', 'badge' => 'In-house', 'badge_cls' => 'bg-slate-100 text-slate-600', 'href' => route('client.enterprise.training', ['locale' => $locale])],
  ];
@endphp
<div class="space-y-8" @if($canEdit) data-outline-group="news-sidebar" data-outline-kind="column" data-outline-group-label="Thanh bên tin tức" @endif>
  <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200" @if($canEdit) data-outline-group="news-sidebar-search" data-outline-kind="card" data-outline-group-label="Tìm Kiếm Bài Viết" @endif>
    <x-client::editable key="partials.news_sidebar.search_title" tag="h3" class="text-lg font-bold uppercase text-navy-900 border-l-4 border-brand-red pl-3 mb-5">Tìm Kiếm Bài Viết</x-client::editable>
    <form action="{{ route('client.news', ['locale' => $locale]) }}" method="get" class="flex gap-2">
      <input type="text" name="s" value="{{ $searchKeyword ?? request('s') }}" placeholder="Nhập từ khóa tìm kiếm..." class="w-full px-4 py-3 rounded-lg border border-slate-300 text-sm focus:ring-1 focus:ring-brand-red focus:border-brand-red outline-none" />
      <button type="submit" class="px-5 py-3 bg-brand-red hover:bg-brand-redDark text-white rounded-lg text-sm font-bold transition" aria-label="Tìm kiếm">
        <i class="fa-solid fa-magnifying-glass"></i>
      </button>
    </form>
  </div>

  <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200" @if($canEdit) data-outline-group="news-sidebar-services-card" data-outline-kind="card" data-outline-group-label="Dịch Vụ Trọng Tâm" @endif>
    <x-client::editable key="partials.news_sidebar.services_title" tag="h3" class="text-lg font-bold uppercase text-navy-900 border-l-4 border-brand-red pl-3 mb-5">Dịch Vụ Trọng Tâm</x-client::editable>
    <ul class="space-y-2.5 text-base" @if($canEdit) data-outline-group="news-sidebar-services" data-outline-kind="items" data-outline-group-label="Danh sách dịch vụ trọng tâm" @endif>
      @foreach($lists->items('partials.news_sidebar.services', array_keys($serviceDefaults)) as $id)
        @php
          $d = $serviceDefaults[$id] ?? ['name' => '', 'badge' => '', 'badge_cls' => 'bg-slate-100 text-slate-600', 'href' => '#'];
        @endphp
        <x-client::list-item list="partials.news_sidebar.services" :item="$id" tag="li">
          <a href="{{ $d['href'] }}" class="flex justify-between items-center text-slate-700 hover:text-brand-red py-2 border-b border-slate-100 font-medium">
            <x-client::editable :key="'partials.news_sidebar.services.' . $id . '.name'" tag="span">{{ $d['name'] }}</x-client::editable>
            <x-client::editable :key="'partials.news_sidebar.services.' . $id . '.badge'" tag="span" class="text-xs px-2.5 py-1 rounded-full font-semibold {{ $d['badge_cls'] }}">{{ $d['badge'] }}</x-client::editable>
          </a>
        </x-client::list-item>
      @endforeach
    </ul>
  </div>

  <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200" @if($canEdit) data-outline-group="news-sidebar-recent" data-outline-kind="card" data-outline-group-label="Bài Viết Mới Nhất" @endif>
    <x-client::editable key="partials.news_sidebar.recent_title" tag="h3" class="text-lg font-bold uppercase text-navy-900 border-l-4 border-brand-red pl-3 mb-5">Bài Viết Mới Nhất</x-client::editable>
    <div class="space-y-4">
      @foreach(($recentPosts ?? collect())->take(3) as $recentPost)
        @php
          $postTitle = $content->get($recentPost, 'title');
          $postSlug = $recentPost->localizedSlug($locale) ?? $recentPost->slug;
          $postThumb = $recentPost->image_url ?: asset('assets/uploads/2024/10/chay-quan-karaoke.jpg');
          $postDate = optional($recentPost->published_at ?? $recentPost->created_at)->format('d/m/Y') ?: '28/10/2024';
        @endphp
        <div class="flex gap-3.5 items-center">
          <img src="{{ $postThumb }}" alt="{{ $postTitle }}" class="w-20 h-20 rounded-lg object-cover flex-shrink-0" />
          <div>
            <h4 class="text-sm sm:text-base font-bold text-navy-900 hover:text-brand-red transition line-clamp-2 leading-snug">
              <a href="{{ route('client.posts.show', ['locale' => $locale, 'slug' => $postSlug]) }}">{{ $postTitle }}</a>
            </h4>
            <span class="text-xs text-slate-400 mt-1 block font-medium">{{ $postDate }}</span>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
