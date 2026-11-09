@php
  $locale = app()->getLocale();
  $postTitle = $content->get($post, 'title');
  $postSummary = $content->get($post, 'summary');
  $postSlug = $post->localizedSlug($locale) ?? $post->slug;
  $postUrl = route('client.posts.show', ['locale' => $locale, 'slug' => $postSlug]);
  $postThumb = ($thumbOverride ?? null) ?: ($post->image_url ?: asset('assets/uploads/services/service_tranh_tung.jpg'));
  $catName = $post->category ? $content->get($post->category, 'name') : 'Nghiên cứu pháp lý';
  $imgH = $imageHeight ?? 'h-52';
  $h3Cls = $titleClass ?? 'text-lg font-bold text-navy-900 group-hover:text-brand-red transition line-clamp-2';
  $defaultMeta = match ($post->slug) {
    'chay-quan-karaoke-chet-13-nguoi-trach-nhiem-thuoc-ve-ai' => 'Kiến thức pháp luật',
    'xu-phat-nguoi-quay-dang-clip-doan-xe-chu-tich-quoc-hoi' => 'Nghị định 15/2020',
    'chieu-bat-toi-pham-co-mot-khong-hai' => 'Án lệ & Thực tiễn',
    default => 'Nghiên cứu & Án lệ',
  };
  $metaText = $metaLabel ?? $defaultMeta;
@endphp
<article class="bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group">
  <div class="{{ $imgH }} overflow-hidden relative">
    <img src="{{ $postThumb }}" alt="{{ $postTitle }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
    <div class="absolute top-3 left-3 bg-navy-900 text-white text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-md shadow">
      {{ $catName }}
    </div>
  </div>
  <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
    <div>
      <h3 class="{{ $h3Cls }}">
        <a href="{{ $postUrl }}">{{ $postTitle }}</a>
      </h3>
      <p class="text-sm text-slate-600 font-normal mt-2.5 leading-relaxed line-clamp-3 text-justify">
        {{ $postSummary }}
      </p>
    </div>
    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-sm">
      <span class="text-xs text-slate-400"><i class="fa-regular fa-clock mr-1"></i> {{ $metaText }}</span>
      <a href="{{ $postUrl }}" class="font-bold text-brand-red hover:underline flex items-center gap-1 text-xs uppercase tracking-wider">
        <span data-editable-ignore="nhãn đọc tiếp card bài viết">Đọc Tiếp</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
      </a>
    </div>
  </div>
</article>
