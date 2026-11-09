@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $bannerTitle = $title ?? '';
  $bannerTitleKey = $titleKey ?? null;
  $bannerBgKey = $bgKey ?? 'partials.page_banner.bg';
  $bannerBreadcrumbs = $breadcrumbs ?? [];
@endphp
<section class="relative bg-navy-950 py-16 sm:py-20 text-white overflow-hidden border-b border-navy-800" @if($canEdit) data-outline-section="page-banner" data-outline-label="Banner đầu trang" @endif>
  <div class="absolute inset-0 bg-cover bg-center opacity-20 pointer-events-none" @clientBackground($bannerBgKey, "background-image: url('".asset('assets/uploads/2024/10/anh-nen-chuyen-muc.jpg')."');")></div>
  <div class="absolute inset-0 bg-gradient-to-r from-navy-950 via-navy-900/95 to-navy-950/90 pointer-events-none"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-3.5" @if($canEdit) data-outline-group="page-banner-content" data-outline-kind="content" data-outline-group-label="Nội dung Banner" @endif>
    @if($bannerTitleKey)
      <x-client::editable :key="$bannerTitleKey" tag="h1" class="text-3xl sm:text-4xl font-bold uppercase tracking-wide text-white">{{ $bannerTitle }}</x-client::editable>
    @else
      <h1 class="text-3xl sm:text-4xl font-bold uppercase tracking-wide text-white">{{ $bannerTitle }}</h1>
    @endif
    <div class="text-sm uppercase tracking-wider text-slate-300 font-medium" contenteditable="false">
      <x-client::editable key="partials.page_banner.home_label" tag="a" :href="\App\Support\LocaleUrl::home($locale)" class="hover:text-brand-red">Trang chủ</x-client::editable>
      @foreach($bannerBreadcrumbs as $bc)
        <span class="mx-2 text-slate-500" data-editable-ignore="dấu phân cách breadcrumb">/</span>
        @if(!empty($bc['url']))
          <a href="{{ $bc['url'] }}" class="hover:text-brand-red">{{ $bc['label'] }}</a>
        @else
          <span class="text-brand-red font-semibold">{{ $bc['label'] }}</span>
        @endif
      @endforeach
    </div>
  </div>
</section>
