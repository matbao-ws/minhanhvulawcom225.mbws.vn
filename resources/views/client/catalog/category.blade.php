@extends('client.layouts.app')

@section('title', $metaTitle ?: $title)

@if($metaDescription)
    @section('meta_description', $metaDescription)
@endif

@section('content')
    <section class="client-shell client-page-head">
        {{-- Category name and description belong to the category record and are
             edited on its admin screen, so they carry no inline hooks. --}}
        <h1>{{ $title }}</h1>
        @if($description)
            <p>{!! $description !!}</p>
        @endif
    </section>

    @if($products->isEmpty())
        <div class="client-shell client-empty">
            <x-client::editable key="catalog.category.empty" tag="p">
                Danh mục này chưa có sản phẩm nào.
            </x-client::editable>
        </div>
    @else
        <div class="client-shell client-grid">
            {{-- Everything below comes from the database: no edit hooks, or the
                 same product name would be editable in two places. --}}
            @foreach($products as $product)
                @php
                    $name = $product->getTranslation('name', app()->getLocale(), false)
                        ?: $product->getTranslation('name', app(\App\Services\LanguageRegistry::class)->fallbackLocale(), false);
                @endphp
                <article class="client-card">
                    <img class="client-card__media"
                         src="{{ \App\Support\MediaUrl::resolve($product->image_url) ?: asset('images/placeholder.png') }}"
                         alt="{{ $name }}"
                         loading="lazy">
                    <div class="client-card__body">
                        <h2 class="client-card__title">{{ $name }}</h2>
                        <p class="client-card__meta client-card__price">
                            {{ number_format((float) $product->price, 0, ',', '.') }} ₫
                        </p>
                    </div>
                </article>
            @endforeach
        </div>

        @if($products->hasPages())
            <div class="client-shell client-pagination">{{ $products->links() }}</div>
        @endif
    @endif
@endsection
