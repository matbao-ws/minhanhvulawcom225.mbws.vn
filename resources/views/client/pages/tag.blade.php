@extends('client.layouts.app')

@php
  $canEdit = \App\Support\ClientEditorChrome::editorActive();
  $slug = $tagSlug ?? 'luat-gia-dinh';
  $keyPrefix = 'tag.'.str_replace('-', '_', $slug);
  $d = $tagData ?? [
    'title' => 'Chủ đề: Hôn nhân & Gia đình',
    'breadcrumb' => 'Hôn nhân & Gia đình',
    'description' => 'Hỏi đáp pháp luật hôn nhân gia đình.',
    'heading' => 'Tư vấn Hôn nhân & Gia đình',
    'body' => 'MAV Law đồng hành giải quyết các vụ việc ly hôn, phân chia tài sản và thỏa thuận nuôi con trong khuôn khổ dịch vụ Luật sư Tranh tụng và Luật sư riêng.',
  ];
@endphp

@section('title', $d['title'].' - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', $d['description'])

@section('content')
  @include('client.partials.page-banner', [
    'title' => $d['title'],
    'titleKey' => $keyPrefix.'.banner_title',
    'bgKey' => $keyPrefix.'.banner_bg',
    'breadcrumbs' => [
      ['label' => 'Học tập Nghiên cứu', 'url' => route('client.research', ['locale' => app()->getLocale()])],
      ['label' => $d['breadcrumb'], 'url' => null],
    ],
  ])

  <main class="py-16 sm:py-20 bg-slate-50 flex-1" @if($canEdit) data-outline-section="tag-main" data-outline-label="{{ $d['title'] }}" @endif>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <div class="lg:col-span-8 space-y-8">
          <div class="bg-white rounded-2xl p-8 border border-slate-200" @if($canEdit) data-outline-group="tag-card" data-outline-kind="card" data-outline-group-label="{{ $d['heading'] }}" @endif>
            <x-client::editable :key="$keyPrefix . '.heading'" tag="h3" class="text-xl font-bold text-navy-900 mb-2">{{ $d['heading'] }}</x-client::editable>
            <x-client::editable :key="$keyPrefix . '.body'" tag="p" class="text-slate-600 leading-relaxed">{{ $d['body'] }}</x-client::editable>
            <div class="pt-4">
              <x-client::editable :key="$keyPrefix . '.cta'" tag="a" :href="route('client.litigation', ['locale' => app()->getLocale()])" class="text-brand-red font-bold">Xem dịch vụ Tranh tụng &rarr;</x-client::editable>
            </div>
          </div>
        </div>
        <div class="lg:col-span-4">
          @include('client.partials.news-sidebar')
        </div>
      </div>
    </div>
  </main>
@endsection
