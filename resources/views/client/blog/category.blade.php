@extends('client.layouts.app')

@section('title', $seo->title ?? 'Học Tập & Nghiên Cứu Pháp Lý | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')

@if(!empty($seo->description))
  @section('meta_description', $seo->description)
@endif

@section('content')
  @php
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
    $bannerTitle = $title ?? 'Học Tập & Nghiên Cứu Pháp Lý';
  @endphp

  @include('client.partials.page-banner', [
    'title' => $bannerTitle,
    'titleKey' => null,
    'bgKey' => 'blog.category.banner_bg',
    'breadcrumbs' => [
      ['label' => $bannerTitle, 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="blog-category-main" data-outline-label="Học tập & Nghiên cứu" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

        <div class="lg:col-span-8 space-y-8" @if($canEdit) data-outline-group="blog-category-content" data-outline-kind="column" data-outline-group-label="Danh sách bài viết" @endif>
          <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-4" @if($canEdit) data-outline-group="blog-category-intro" data-outline-kind="card" data-outline-group-label="Giới thiệu chuyên mục" @endif>
            <x-client::editable key="blog.category.eyebrow" tag="span" class="text-brand-red font-bold text-sm uppercase tracking-wider">Góc nghiên cứu</x-client::editable>
            <x-client::editable key="blog.category.heading" tag="h2" class="text-2xl sm:text-3xl font-bold text-navy-900">Kiến Thức Pháp Luật, Án Lệ &amp; Hỏi Đáp</x-client::editable>
            @if(!empty($description))
              <div class="text-slate-600 leading-relaxed text-base text-justify">{!! $description !!}</div>
            @else
              <x-client::editable key="blog.category.desc" tag="p" class="text-slate-600 leading-relaxed text-base text-justify">Các bài viết phân tích tình huống pháp lý thực tiễn, bình luận án lệ và giải đáp vướng mắc pháp luật thường gặp do đội ngũ Luật sư Minh Anh Vũ trực tiếp nghiên cứu và chia sẻ.</x-client::editable>
            @endif
          </div>

          @if($posts->isEmpty())
            <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm text-center">
              <x-client::editable key="blog.category.empty" tag="p" class="text-slate-600">Chuyên mục này chưa có bài viết nào.</x-client::editable>
            </div>
          @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              @foreach($posts as $post)
                @include('client.partials.post-card', [
                  'post' => $post,
                  'imageHeight' => 'h-56',
                  'titleClass' => 'text-xl font-bold text-navy-900 group-hover:text-brand-red transition',
                  'metaLabel' => 'Nghiên cứu & Án lệ',
                ])
              @endforeach
            </div>

            @if($posts->hasPages())
              <div class="pt-6">{{ $posts->links() }}</div>
            @endif
          @endif
        </div>

        <div class="lg:col-span-4">
          @include('client.partials.news-sidebar')
        </div>

      </div>
    </div>
  </main>
@endsection
