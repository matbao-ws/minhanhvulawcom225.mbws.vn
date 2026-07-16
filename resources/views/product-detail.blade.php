@extends('layouts.app')

@section('title', $product->getTranslation('name', app()->getLocale()) . ' - ABCPRO VIETNAM')

@section('content')
<section class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumbs -->
        <nav class="flex mb-8 text-xs font-semibold text-gray-500 space-x-2 tracking-wider">
            <a href="{{ url('/') }}" class="hover:text-brand-gold">{{ mb_strtoupper(__('messages.home'), 'UTF-8') }}</a>
            <span>/</span>
            <span class="text-gray-400 uppercase">{{ $product->category->getTranslation('name', app()->getLocale()) }}</span>
            <span>/</span>
            <span class="text-primary-navy uppercase">{{ $product->getTranslation('name', app()->getLocale()) }}</span>
        </nav>

        <!-- Main Product Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 bg-white rounded-2xl p-6 sm:p-10 border border-gray-100 shadow-sm items-start">
            
            <!-- Left: Product Image -->
            <div class="lg:col-span-6 bg-gray-50 rounded-xl overflow-hidden aspect-[4/3] border border-gray-100">
                <img src="{{ $product->image_url ?? '/images/products/placeholder.png' }}" 
                     alt="{{ $product->getTranslation('name', app()->getLocale()) }}"
                     class="w-full h-full object-cover">
            </div>

            <!-- Right: Product Info -->
            <div class="lg:col-span-6 space-y-6" x-data="{ qty: 1 }">
                <span class="inline-block bg-brand-gold/15 text-brand-gold text-[10px] font-black uppercase px-3 py-1.5 rounded-full tracking-wider">
                    {{ $product->category->getTranslation('name', app()->getLocale()) }}
                </span>
                
                <h1 class="text-3xl font-extrabold text-primary-navy leading-tight">
                    {{ $product->getTranslation('name', app()->getLocale()) }}
                </h1>



                <!-- Short description -->
                <p class="text-gray-600 text-sm leading-relaxed font-light">
                    {{ $product->getTranslation('short_description', app()->getLocale()) }}
                </p>

                <!-- Contact for Quote Button -->
                <div class="pt-4 border-t border-gray-150">
                    <a href="{{ url('/#contact') }}" class="inline-flex items-center justify-center bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase px-8 h-12 rounded-lg shadow-lg hover:shadow-brand-gold/25 transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span>{{ __('messages.request_quote') }}</span>
                    </a>
                </div>

            </div>
        </div>

        <!-- Specifications and Details -->
        <div class="mt-12">
            
            <!-- Long description (Full width) -->
            <div class="bg-white rounded-2xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <h3 class="text-xl font-extrabold text-primary-navy tracking-tight border-b border-gray-100 pb-4">
                    {{ __('messages.about') }}
                </h3>
                <div class="prose max-w-none text-sm text-gray-600 leading-relaxed font-light space-y-4">
                    {!! $product->getTranslation('description', app()->getLocale()) !!}
                </div>
            </div>

        </div>

        <!-- Related Products -->
        @if($relatedProducts->count() > 0)
            <div class="mt-20 space-y-8">
                <h3 class="text-2xl font-extrabold text-primary-navy tracking-tight text-center sm:text-left">
                    {{ __('messages.related_products') }}
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    @foreach($relatedProducts as $relProduct)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col h-full group">
                            
                            <!-- Product Image -->
                            <a href="{{ url('/products/' . $relProduct->slug) }}" class="relative overflow-hidden aspect-[4/3] bg-gray-50 block">
                                <img src="{{ $relProduct->image_url ?? '/images/products/placeholder.png' }}" 
                                     alt="{{ $relProduct->getTranslation('name', app()->getLocale()) }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute inset-0 bg-primary-navy/10 group-hover:bg-transparent transition-colors duration-300"></div>
                            </a>

                            <!-- Product Content -->
                            <div class="p-6 flex flex-col flex-grow space-y-3">
                                <span class="text-[10px] font-extrabold text-brand-gold uppercase tracking-wider">
                                    {{ $relProduct->category->getTranslation('name', app()->getLocale()) }}
                                </span>
                                <a href="{{ url('/products/' . $relProduct->slug) }}" class="block">
                                    <h4 class="text-sm font-bold text-primary-navy group-hover:text-brand-gold transition-colors duration-300 line-clamp-1">
                                        {{ $relProduct->getTranslation('name', app()->getLocale()) }}
                                    </h4>
                                </a>
                                <p class="text-gray-500 text-xs leading-relaxed line-clamp-2">
                                    {{ $relProduct->getTranslation('short_description', app()->getLocale()) }}
                                </p>
                                
                                <!-- Price & Buy -->
                                <div class="pt-4 border-t border-gray-50 mt-auto">
                                    <a href="{{ url('/products/' . $relProduct->slug) }}" class="bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy text-[10px] font-bold uppercase py-2 px-3 rounded transition-all duration-300 cursor-pointer block text-center w-full">
                                        {{ __('messages.view_details') }}
                                    </a>
                                </div>
                            </div>

                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</section>
@endsection
