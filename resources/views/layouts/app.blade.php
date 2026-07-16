<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', __('messages.hero_title') . ' - ' . __('messages.hero_badge'))</title>

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Favicon -->
        <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}">

        <!-- AlpineJS for reactivity (tabs, mobile menu, cart count) -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

        <style>
            body {
                font-family: 'Montserrat', sans-serif;
            }
            h1, h2, h3, h4, h5, h6 {
                font-family: 'Montserrat', sans-serif;
            }
        </style>
        @yield('styles')
    </head>
    <body class="bg-[#fcfbf9] text-gray-800 selection:bg-brand-gold selection:text-primary-navy" 
          x-data="globalCart()" 
          x-init="initCart()">

        <!-- 1. Header Section -->
        <header class="sticky top-0 z-50 bg-primary-navy/95 backdrop-blur-md border-b border-white/10 text-white transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    
                    <!-- Logo ABCPRO VIETNAM -->
                    <a href="{{ url('/') }}" class="flex items-center">
                        <img src="{{ asset('images/site/logo.png') }}" alt="ABCPRO VIETNAM" class="h-10 w-auto object-contain">
                    </a>

                    <!-- Desktop Navigation Links -->
                    <nav class="hidden md:flex space-x-8 text-sm font-semibold tracking-wide">
                        <a href="{{ url('/') }}" class="hover:text-brand-gold transition-colors duration-300">{{ mb_strtoupper(__('messages.home'), 'UTF-8') }}</a>
                        <a href="{{ url('/#about') }}" class="text-white/80 hover:text-brand-gold transition-colors duration-300">{{ mb_strtoupper(__('messages.about'), 'UTF-8') }}</a>
                        <a href="{{ url('/#products') }}" class="text-white/80 hover:text-brand-gold transition-colors duration-300">{{ mb_strtoupper(__('messages.products'), 'UTF-8') }}</a>
                        <a href="{{ url('/#contact') }}" class="text-white/80 hover:text-brand-gold transition-colors duration-300">{{ mb_strtoupper(__('messages.contact'), 'UTF-8') }}</a>
                    </nav>

                    <!-- Header Options (Admin, Language flags, Cart) -->
                    <div class="hidden md:flex items-center space-x-6">
                        <!-- Lang switcher -->
                        <div class="flex items-center space-x-2 border-r border-white/20 pr-6">
                            @foreach(\Mcamara\LaravelLocalization\Facades\LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                                <a href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}" 
                                   class="w-5 h-4 overflow-hidden rounded shadow {{ app()->getLocale() === $localeCode ? '' : 'opacity-60 hover:opacity-100' }} hover:scale-110 transition-transform duration-300" 
                                   title="{{ $properties['native'] }}">
                                    @if($localeCode === 'vi')
                                        <img src="https://flagcdn.com/w40/vn.png" alt="VN" class="w-full h-full object-cover">
                                    @elseif($localeCode === 'en')
                                        <img src="https://flagcdn.com/w40/gb.png" alt="EN" class="w-full h-full object-cover">
                                    @elseif($localeCode === 'ko')
                                        <img src="https://flagcdn.com/w40/kr.png" alt="KR" class="w-full h-full object-cover">
                                    @endif
                                </a>
                            @endforeach
                        </div>
                        

                    </div>

                    <!-- Mobile Menu Button -->
                    <div class="flex items-center md:hidden">
                        <button @click="mobileMenu = !mobileMenu" class="text-white hover:text-brand-gold focus:outline-none p-2" aria-label="Toggle menu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" x-show="!mobileMenu"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" x-show="mobileMenu" style="display: none;"></path>
                            </svg>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div x-show="mobileMenu" x-transition class="md:hidden bg-primary-navy border-t border-white/10" style="display: none;">
                <div class="px-2 pt-2 pb-4 space-y-1 sm:px-3 text-center">
                    <a href="{{ url('/') }}" class="block px-3 py-2 text-base font-semibold text-brand-gold">{{ mb_strtoupper(__('messages.home'), 'UTF-8') }}</a>
                    <a href="{{ url('/#about') }}" @click="mobileMenu = false" class="block px-3 py-2 text-base font-semibold text-white/80 hover:text-brand-gold">{{ mb_strtoupper(__('messages.about'), 'UTF-8') }}</a>
                    <a href="{{ url('/#products') }}" @click="mobileMenu = false" class="block px-3 py-2 text-base font-semibold text-white/80 hover:text-brand-gold">{{ mb_strtoupper(__('messages.products'), 'UTF-8') }}</a>
                    <a href="{{ url('/#contact') }}" @click="mobileMenu = false" class="block px-3 py-2 text-base font-semibold text-white/80 hover:text-brand-gold">{{ mb_strtoupper(__('messages.contact'), 'UTF-8') }}</a>
                    <div class="flex justify-center space-x-4 py-3 border-t border-white/10 mt-3">
                        @foreach(\Mcamara\LaravelLocalization\Facades\LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                            <a href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}" 
                               class="w-5 h-4 overflow-hidden rounded shadow {{ app()->getLocale() === $localeCode ? '' : 'opacity-60' }}" 
                               title="{{ $properties['native'] }}">
                                @if($localeCode === 'vi')
                                    <img src="https://flagcdn.com/w40/vn.png" alt="VN" class="w-full h-full object-cover">
                                @elseif($localeCode === 'en')
                                    <img src="https://flagcdn.com/w40/gb.png" alt="EN" class="w-full h-full object-cover">
                                @elseif($localeCode === 'ko')
                                    <img src="https://flagcdn.com/w40/kr.png" alt="KR" class="w-full h-full object-cover">
                                @endif
                            </a>
                        @endforeach
                    </div>

                </div>
            </div>
        </header>

        <!-- Content Placeholder -->
        <main>
            @yield('content')
        </main>

        <!-- 7. Footer Section -->
        <footer class="bg-primary-navy text-white pt-20 pb-10 relative z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-12 pb-16 border-b border-white/10">
                    
                    <!-- Column 1: Brand Info (4 cols) -->
                    <div class="lg:col-span-4 space-y-6">
                        <!-- Logo ABCPRO VIETNAM -->
                        <a href="{{ url('/') }}" class="flex items-center">
                            <img src="{{ asset('images/site/logo.png') }}" alt="ABCPRO VIETNAM" class="h-12 w-auto object-contain">
                        </a>
                        <p class="text-white/60 text-sm leading-relaxed font-light">
                            {{ __('messages.about_text_2') }}
                        </p>
                        <!-- Social Icons -->
                        <div class="flex space-x-3 pt-2">
                            <a href="#" class="w-9 h-9 rounded-full bg-white/5 border border-white/15 hover:bg-brand-gold hover:text-primary-navy transition-all duration-300 flex items-center justify-center text-white/80" title="Facebook">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <a href="#" class="w-9 h-9 rounded-full bg-white/5 border border-white/15 hover:bg-brand-gold hover:text-primary-navy transition-all duration-300 flex items-center justify-center text-white/80" title="Twitter">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" />
                                </svg>
                            </a>
                            <a href="#" class="w-9 h-9 rounded-full bg-white/5 border border-white/15 hover:bg-brand-gold hover:text-primary-navy transition-all duration-300 flex items-center justify-center text-white/80" title="YouTube">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M19.812 5.418c.861.23 1.538.907 1.768 1.768C21.998 8.746 22 12 22 12s0 3.255-.42 4.814a2.436 2.436 0 01-1.768 1.768c-1.56.419-7.813.419-7.813.419s-6.255 0-7.813-.419a2.437 2.437 0 01-1.769-1.768C2 15.254 2 12 2 12s0-3.254.42-4.814a2.436 2.436 0 011.768-1.768C5.746 5 12 5 12 5s6.255 0 7.813.418zM9.75 15.002L15.5 12 9.75 9v6.002z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Column 2: Quick Links (3 cols) -->
                    <div class="lg:col-span-3 lg:col-start-6 space-y-6">
                        <h4 class="text-sm font-bold uppercase tracking-wider text-brand-gold">{{ mb_strtoupper(__('messages.services'), 'UTF-8') }}</h4>
                        <ul class="space-y-3 text-sm font-light text-white/70">
                            <li><a href="{{ url('/') }}" class="hover:text-brand-gold transition-colors duration-300">{{ __('messages.home') }}</a></li>
                            <li><a href="{{ url('/#about') }}" class="hover:text-brand-gold transition-colors duration-300">{{ __('messages.about') }}</a></li>
                            <li><a href="{{ url('/#products') }}" class="hover:text-brand-gold transition-colors duration-300">{{ __('messages.products') }}</a></li>
                            <li><a href="{{ url('/#contact') }}" class="hover:text-brand-gold transition-colors duration-300">{{ __('messages.contact') }}</a></li>
                        </ul>
                    </div>

                    <!-- Column 3: Contact details (5 cols) -->
                    <div class="lg:col-span-3 space-y-6">
                        <h4 class="text-sm font-bold uppercase tracking-wider text-brand-gold">{{ __('messages.subscribe') }}</h4>
                        <p class="text-white/60 text-sm font-light leading-relaxed">
                            {{ __('messages.subscribe_desc') }}
                        </p>
                        
                        <div class="flex items-stretch rounded overflow-hidden border border-white/20 focus-within:border-brand-gold transition-colors duration-300">
                            <input type="email" placeholder="Email..." class="bg-white/5 px-4 py-2.5 text-sm w-full focus:outline-none placeholder-white/30">
                            <button class="bg-brand-gold hover:bg-brand-gold-dark text-primary-navy px-4 font-bold flex items-center justify-center cursor-pointer transition-colors duration-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Copyright -->
                <div class="pt-10 flex flex-col sm:flex-row items-center justify-between text-xs text-white/55 font-light">
                    <p>© Copyright 2026 A&O Corporation. All rights reserved.</p>
                    <p>{{ __('messages.copyright') }} <a href="https://www.matbao.ws" target="_blank">Mắt Bão WS</a></p>
                </div>

            </div>
        </footer>

        <!-- 8. Floating Action Buttons (Zalo / Call) -->
        <div class="fixed bottom-6 right-6 z-50 flex flex-col space-y-3">
            <!-- Call Hotline -->
            <a href="tel:0353728805" class="w-12 h-12 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-110 transition-all duration-300" title="Gọi điện">
                <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
            </a>
            <!-- Chat Zalo -->
            <a href="https://zalo.me/0353728805" target="_blank" class="w-12 h-12 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-110 transition-all duration-300" title="Chat Zalo">
                <span class="text-xs font-black tracking-tighter">Zalo</span>
            </a>
        </div>

        <script>
            function globalCart() {
                return {
                    mobileMenu: false,
                    cart: [],
                    customer: null,
                    get cartCount() {
                        return this.cart.reduce((sum, item) => sum + item.quantity, 0);
                    },
                    initCart() {
                        this.cart = JSON.parse(localStorage.getItem('cart') || '[]');
                        const stored = localStorage.getItem('customer');
                        if (stored) {
                            this.customer = JSON.parse(stored);
                        }
                    },
                    logout() {
                        localStorage.removeItem('customer');
                        localStorage.removeItem('customer_token');
                        this.customer = null;
                        window.location.href = '{{ url('/') }}';
                    },
                    addToCart(productId, quantity = 1, variantId = null) {
                        productId = parseInt(productId);
                        if (variantId) variantId = parseInt(variantId);
                        
                        const existing = this.cart.find(item => item.product_id === productId && item.variant_id === variantId);
                        if (existing) {
                            existing.quantity += parseInt(quantity);
                        } else {
                            this.cart.push({
                                product_id: productId,
                                quantity: parseInt(quantity),
                                variant_id: variantId
                            });
                        }
                        localStorage.setItem('cart', JSON.stringify(this.cart));
                        alert('{{ __("messages.added_to_cart_success") }}');
                    },
                    updateQuantity(productId, quantity, variantId = null) {
                        productId = parseInt(productId);
                        if (variantId) variantId = parseInt(variantId);
                        
                        const item = this.cart.find(item => item.product_id === productId && item.variant_id === variantId);
                        if (item) {
                            item.quantity = Math.max(1, parseInt(quantity));
                            localStorage.setItem('cart', JSON.stringify(this.cart));
                        }
                    },
                    removeFromCart(productId, variantId = null) {
                        productId = parseInt(productId);
                        if (variantId) variantId = parseInt(variantId);
                        
                        this.cart = this.cart.filter(item => !(item.product_id === productId && item.variant_id === variantId));
                        localStorage.setItem('cart', JSON.stringify(this.cart));
                    },
                    clearCart() {
                        this.cart = [];
                        localStorage.removeItem('cart');
                    }
                }
            }
        </script>
        @yield('scripts')
    </body>
</html>
