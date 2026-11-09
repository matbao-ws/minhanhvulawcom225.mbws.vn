@extends('client.layouts.app')

@php
  $locale = app()->getLocale();
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $postTitle = $title ?? $content->get($post, 'title');
  $postBody = $content->get($post, 'content');
  $shortBreadcrumb = \Illuminate\Support\Str::limit($postTitle, 30, '...');
@endphp

@section('title', ($seo->title ?? $postTitle).' - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')

@if(!empty($seo->description))
  @section('meta_description', $seo->description)
@endif

@section('content')
  @include('client.partials.page-banner', [
    'title' => $postTitle,
    'titleKey' => null,
    'bgKey' => 'blog.show.banner_bg',
    'breadcrumbs' => [
      ['label' => 'Học tập Nghiên cứu', 'url' => route('client.research', ['locale' => $locale])],
      ['label' => $shortBreadcrumb, 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="blog-show-main" data-outline-label="Chi tiết bài viết" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <article class="lg:col-span-8 bg-white rounded-2xl p-8 sm:p-12 border border-slate-200 shadow-sm space-y-6">
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-navy-900 leading-tight">{{ $postTitle }}</h1>
          <div class="flex items-center gap-4 text-xs sm:text-sm text-slate-500 pb-4 border-b border-slate-200">
            <span>
              <i class="fa-solid fa-user-tie text-brand-red mr-1.5"></i>
              <x-client::editable key="blog.show.author_label" tag="span">Luật sư MAV Law</x-client::editable>
            </span>
            <span>
              <i class="fa-solid fa-folder-open text-brand-red mr-1.5"></i>
              <x-client::editable key="blog.show.category_label" tag="span">Án lệ &amp; Nghiên cứu</x-client::editable>
            </span>
          </div>
          <div class="prose max-w-none text-slate-700 leading-relaxed text-base sm:text-lg space-y-4 text-justify font-normal">
            {!! $postBody !!}
          </div>
        </article>
        <div class="lg:col-span-4">
          @include('client.partials.news-sidebar')
        </div>
      </div>
    </div>
  </main>
@endsection
