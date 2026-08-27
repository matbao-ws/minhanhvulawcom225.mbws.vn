@extends('client.layouts.app')

@section('title', $title)

@if($description)
    @section('meta_description', strip_tags($description))
@endif

@section('content')
    <section class="client-shell client-page-head">
        {{-- Owned by the post category record; edited on its admin screen. --}}
        <h1>{{ $title }}</h1>
        @if($description)
            <p>{!! $description !!}</p>
        @endif
    </section>

    @if($posts->isEmpty())
        <div class="client-shell client-empty">
            <x-client::editable key="blog.category.empty" tag="p">
                Chuyên mục này chưa có bài viết nào.
            </x-client::editable>
        </div>
    @else
        <div class="client-shell client-grid">
            @foreach($posts as $post)
                @php
                    $fallback = app(\App\Services\LanguageRegistry::class)->fallbackLocale();
                    $postTitle = $post->getTranslation('title', app()->getLocale(), false)
                        ?: $post->getTranslation('title', $fallback, false);
                    $summary = $post->getTranslation('summary', app()->getLocale(), false)
                        ?: $post->getTranslation('summary', $fallback, false);
                @endphp
                <article class="client-card">
                    <img class="client-card__media"
                         src="{{ \App\Support\MediaUrl::resolve($post->image_url) ?: asset('images/placeholder.png') }}"
                         alt="{{ $postTitle }}"
                         loading="lazy">
                    <div class="client-card__body">
                        <h2 class="client-card__title">{{ $postTitle }}</h2>
                        @if($summary)
                            <p class="client-card__meta">{{ \Illuminate\Support\Str::limit(strip_tags($summary), 110) }}</p>
                        @endif
                        @if($post->published_at)
                            <p class="client-card__meta">{{ $post->published_at->format('d/m/Y') }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($posts->hasPages())
            <div class="client-shell client-pagination">{{ $posts->links() }}</div>
        @endif
    @endif
@endsection
