@extends('layouts.app')

@section('content')
@php
    $locale = app()->getLocale();
    if ($locale === 'ko') {
        $bannerUrl = asset('images/site/banner_ko.png');
    } elseif ($locale === 'en') {
        $bannerUrl = asset('images/site/banner_en.png');
    } else {
        $bannerUrl = 'https://abc-oasis.com/wp-content/uploads/2025/12/banner-ctyocdao-1-1.png';
    }
@endphp
        <!-- 2. Hero Section -->
        <section class="relative bg-primary-navy text-white overflow-hidden py-24 md:py-32">
            <!-- Background Image with Overlay -->
            <div class="absolute inset-0 z-0">
                <img src="{{ $bannerUrl }}" alt="Architectural Building" class="w-full h-full object-cover object-center opacity-25">
                <div class="absolute inset-0 bg-gradient-to-r from-primary-navy via-primary-navy/95 to-transparent"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    
                    <!-- Left side: Hero Text -->
                    <div class="lg:col-span-7 space-y-6">
                        <div class="inline-flex items-center space-x-2 bg-brand-gold/15 border border-brand-gold/30 rounded-full px-4 py-1.5 text-xs font-bold text-brand-gold uppercase tracking-wider">
                            <span>{{ __('messages.hero_badge') }}</span>
                        </div>
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight tracking-tight">
                            {{ __('messages.hero_title') }}
                        </h1>
                        <p class="text-lg text-white/80 max-w-2xl font-light leading-relaxed">
                            {{ __('messages.hero_slogan') }}
                        </p>
                        <div class="pt-4 flex flex-wrap gap-4">
                            <a href="#products" class="bg-brand-gold hover:bg-brand-gold-dark text-primary-navy font-bold uppercase px-8 py-4 rounded shadow-lg hover:shadow-brand-gold/25 transition-all duration-300 transform hover:-translate-y-0.5">
                                {{ __('messages.discover_products') }}
                            </a>
                            <a href="#about" class="bg-transparent hover:bg-white/10 text-white border border-white/30 hover:border-white font-bold uppercase px-8 py-4 rounded transition-all duration-300">
                                {{ __('messages.learn_more') }}
                            </a>
                        </div>
                    </div>

                    <!-- Right side: Circular overlapping images inspired by abc-oasis.com -->
                    <div class="lg:col-span-5 flex items-center justify-center relative mt-10 lg:mt-0">
                        <div class="relative w-72 h-72 sm:w-96 sm:h-96">
                            
                            <!-- Circle 1 (Glasswool Water Free) -->
                            <div class="absolute top-0 left-0 w-44 h-44 sm:w-56 sm:h-56 rounded-full overflow-hidden border-4 border-brand-gold shadow-2xl z-20 transform hover:scale-105 transition-transform duration-500">
                                <img src="https://abc-oasis.com/wp-content/uploads/2025/11/GLASSWOOL-WATER-PREE.png" alt="Glasswool Water Free" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/10 hover:bg-transparent transition-colors duration-300"></div>
                            </div>
                            
                            <!-- Circle 2 (Ceramic Wool) -->
                            <div class="absolute bottom-0 right-0 w-40 h-40 sm:w-52 sm:h-52 rounded-full overflow-hidden border-4 border-white shadow-2xl z-10 transform hover:scale-105 transition-transform duration-500">
                                <img src="https://abc-oasis.com/wp-content/uploads/2025/11/anh-1-3.png" alt="Ceramic Wool" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/10 hover:bg-transparent transition-colors duration-300"></div>
                            </div>
                            
                            <!-- Circle 3 (Isopink) -->
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/4 -translate-y-1/2 w-32 h-32 sm:w-44 sm:h-44 rounded-full overflow-hidden border-4 border-brand-gold shadow-2xl z-0 transform hover:scale-105 transition-transform duration-500">
                                <img src="https://abc-oasis.com/wp-content/uploads/2025/11/anh-1-BIA-1-1.png" alt="Isopink Insulation" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/10 hover:bg-transparent transition-colors duration-300"></div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <!-- Wave divider bottom -->
            <div class="absolute bottom-0 left-0 right-0 translate-y-1/2 pointer-events-none z-10 overflow-hidden">
                <svg viewBox="0 0 1440 120" fill="none" class="w-full h-16 text-[#fcfbf9] fill-current">
                    <path d="M0,32L120,42.7C240,53,480,75,720,74.7C960,75,1200,53,1320,42.7L1440,32L1440,120L1320,120C1200,120,960,120,720,120C480,120,240,120,120,120L0,120Z"></path>
                </svg>
            </div>
        </section>

        <!-- 3. Company About Section -->
        <section id="about" class="py-24 bg-[#fcfbf9] relative z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center space-y-4 max-w-3xl mx-auto mb-16">
                    <span class="text-sm font-extrabold text-brand-gold uppercase tracking-widest block">{{ mb_strtoupper(__('messages.about'), 'UTF-8') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-primary-navy tracking-tight">
                        {{ __('messages.about_company_title') }}
                    </h2>
                    <div class="w-12 h-1 bg-brand-gold mx-auto"></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    
                    <!-- Content -->
                    <div class="space-y-6">
                        <p class="text-lg text-gray-700 leading-relaxed font-light">
                            {{ __('messages.about_text_1') }}
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            {{ __('messages.about_text_2') }}
                        </p>
                        <div class="pt-4">
                            <a href="#contact" class="inline-flex items-center space-x-2 text-primary-navy hover:text-brand-gold font-bold uppercase transition-colors duration-300 group">
                                <span>{{ __('messages.learn_more') }}</span>
                                <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Side Grid Stats -->
                    <div class="grid grid-cols-2 gap-6">
                        <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-100 text-center space-y-2 hover:shadow-md transition-all duration-300">
                            <span class="text-4xl sm:text-5xl font-extrabold text-brand-gold block">21+</span>
                            <span class="text-sm font-bold text-primary-navy uppercase tracking-wider block">{{ __('messages.experience_years') }}</span>
                        </div>
                        <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-100 text-center space-y-2 hover:shadow-md transition-all duration-300">
                            <span class="text-4xl sm:text-5xl font-extrabold text-brand-gold block">100%</span>
                            <span class="text-sm font-bold text-primary-navy uppercase tracking-wider block">{{ __('messages.imported_korea') }}</span>
                        </div>
                        <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-100 text-center space-y-2 hover:shadow-md transition-all duration-300">
                            <span class="text-4xl sm:text-5xl font-extrabold text-brand-gold block">500+</span>
                            <span class="text-sm font-bold text-primary-navy uppercase tracking-wider block">{{ __('messages.completed_projects') }}</span>
                        </div>
                        <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-100 text-center space-y-2 hover:shadow-md transition-all duration-300">
                            <span class="text-4xl sm:text-5xl font-extrabold text-brand-gold block">TOP 1</span>
                            <span class="text-sm font-bold text-primary-navy uppercase tracking-wider block">{{ __('messages.byucksan_partner') }}</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- 4. Split Grid Section -->
        <section class="w-full bg-[#f6f4ee]">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                
                <!-- Left: Full vertical image -->
                <div class="relative min-h-[350px] lg:min-h-[600px] overflow-hidden">
                    <img src="https://abc-oasis.com/wp-content/uploads/2022/06/1144-scaled.jpg" alt="Apartments Project" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-primary-navy/20"></div>
                </div>

                <!-- Right: 2x2 Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2">
                    
                    <!-- Box 1 (Text Dark) -->
                    <div class="bg-primary-navy text-white p-8 sm:p-12 flex flex-col justify-center space-y-4">
                        <span class="text-brand-gold text-xs font-extrabold uppercase tracking-widest">{{ __('messages.trusted_partner') }}</span>
                        <h3 class="text-2xl font-bold">{{ __('messages.exclusive_distributor') }}</h3>
                        <p class="text-white/70 text-sm leading-relaxed font-light">
                            {{ __('messages.exclusive_distributor_desc') }}
                        </p>
                        <a href="#contact" class="text-brand-gold text-xs font-bold uppercase tracking-wider hover:underline inline-flex items-center space-x-1">
                            <span>{{ __('messages.contact_partner') }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>

                    <!-- Box 2 (Image Wood Wall) -->
                    <div class="relative min-h-[250px] sm:min-h-0 overflow-hidden">
                        <img src="https://abc-oasis.com/wp-content/uploads/Baritone-1.jpg" alt="Wood Wall Exterior" class="absolute inset-0 w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                    </div>

                    <!-- Box 3 (Image Building Brick) -->
                    <div class="relative min-h-[250px] sm:min-h-0 overflow-hidden">
                        <img src="https://abc-oasis.com/wp-content/uploads/Terasolid-2.jpg" alt="Building Brick Facade" class="absolute inset-0 w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                    </div>

                    <!-- Box 4 (Text Dark) -->
                    <div class="bg-primary-dark text-white p-8 sm:p-12 flex flex-col justify-center space-y-4">
                        <span class="text-brand-gold text-xs font-extrabold uppercase tracking-widest">{{ __('messages.green_standard') }}</span>
                        <h3 class="text-2xl font-bold">{{ __('messages.quality_commitment') }}</h3>
                        <p class="text-white/70 text-sm leading-relaxed font-light">
                            {{ __('messages.quality_commitment_desc') }}
                        </p>
                        <a href="#products" class="text-brand-gold text-xs font-bold uppercase tracking-wider hover:underline inline-flex items-center space-x-1">
                            <span>{{ __('messages.view_product_catalog') }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>

                </div>

            </div>
        </section>

        <!-- 5. Featured Products with Dynamic Tabs -->
        <section id="products" class="py-24 bg-white" x-data="{ activeTab: '{{ $categories->first()?->slug ?? '' }}' }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Title -->
                <div class="text-center space-y-4 max-w-3xl mx-auto mb-16">
                    <span class="text-sm font-extrabold text-brand-gold uppercase tracking-widest block">{{ mb_strtoupper(__('messages.products'), 'UTF-8') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-primary-navy tracking-tight">
                        {{ __('messages.featured_products_title') }}
                    </h2>
                    <div class="w-12 h-1 bg-brand-gold mx-auto"></div>
                </div>

                <!-- Tabs header -->
                <div class="flex flex-wrap justify-center gap-3 sm:gap-6 border-b border-gray-100 pb-6 mb-12">
                    @foreach($categories as $category)
                        <button 
                            @click="activeTab = '{{ $category->slug }}'"
                            :class="activeTab === '{{ $category->slug }}' ? 'bg-primary-navy text-brand-gold border-primary-navy shadow-lg shadow-primary-navy/15' : 'bg-gray-50 text-gray-500 hover:text-primary-navy hover:bg-gray-100 border-gray-200'"
                            class="px-6 py-3 border rounded-full font-bold text-sm transition-all duration-300 focus:outline-none cursor-pointer"
                        >
                            {{ $category->getTranslation('name', app()->getLocale()) }}
                        </button>
                    @endforeach
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    @foreach($products as $product)
                        <div 
                            x-show="activeTab === '{{ $product->category->slug }}'"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col h-full group"
                            style="display: none;"
                        >
                            <!-- Product Image Container -->
                            <a href="{{ url('/products/' . $product->slug) }}" class="relative overflow-hidden aspect-[4/3] bg-gray-50 block">
                                <img 
                                    src="{{ $product->image_url ?? '/images/products/placeholder.png' }}" 
                                    alt="{{ $product->getTranslation('name', app()->getLocale()) }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                >

                                <div class="absolute inset-0 bg-primary-navy/10 group-hover:bg-transparent transition-colors duration-300"></div>
                            </a>

                            <!-- Product Content -->
                            <div class="p-6 flex flex-col flex-grow space-y-3">
                                <span class="text-[10px] font-extrabold text-brand-gold uppercase tracking-wider">
                                    {{ $product->category->getTranslation('name', app()->getLocale()) }}
                                </span>
                                <a href="{{ url('/products/' . $product->slug) }}" class="block">
                                    <h3 class="text-base font-bold text-primary-navy group-hover:text-brand-gold transition-colors duration-300 line-clamp-1">
                                        {{ $product->getTranslation('name', app()->getLocale()) }}
                                    </h3>
                                </a>
                                <p class="text-gray-500 text-xs leading-relaxed line-clamp-2">
                                    {{ $product->getTranslation('short_description', app()->getLocale()) }}
                                </p>
                                
                                <!-- Price & Button footer -->
                                <div class="pt-4 border-t border-gray-50 mt-auto">
                                    <a href="{{ url('/products/' . $product->slug) }}" class="bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy text-xs font-bold uppercase py-2.5 px-4 rounded transition-all duration-300 cursor-pointer shadow-sm hover:shadow-md block text-center w-full">
                                        {{ __('messages.view_details') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>
        </section>

        <!-- 6. Contact and Map Section -->
        <section id="contact" class="py-24 bg-gray-50 relative">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-stretch">
                    
                    <!-- Left: Contact Form -->
                    <div class="lg:col-span-5 bg-white p-8 sm:p-10 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between"
                         x-data="{ 
                             name: '', 
                             phone: '', 
                             email: '', 
                             message: '', 
                             loading: false, 
                             successMessage: '', 
                             errorMessage: '',
                             async submitForm() {
                                 this.loading = true;
                                 this.successMessage = '';
                                 this.errorMessage = '';
                                 try {
                                     const response = await fetch('/api/public/contact', {
                                         method: 'POST',
                                         headers: {
                                             'Content-Type': 'application/json',
                                             'Accept': 'application/json',
                                             'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                         },
                                         body: JSON.stringify({
                                             name: this.name,
                                             phone: this.phone,
                                             email: this.email,
                                             message: this.message
                                         })
                                     });
                                     const result = await response.json();
                                     if (response.ok && result.success) {
                                         this.successMessage = result.message;
                                         this.name = '';
                                         this.phone = '';
                                         this.email = '';
                                         this.message = '';
                                     } else {
                                         this.errorMessage = result.message || 'Gửi yêu cầu thất bại. Vui lòng kiểm tra lại.';
                                     }
                                 } catch (error) {
                                     this.errorMessage = 'Đã xảy ra lỗi kết nối. Vui lòng thử lại sau.';
                                 } finally {
                                     this.loading = false;
                                 }
                             }
                         }">
                        <div class="space-y-4">
                            <span class="text-xs font-extrabold text-brand-gold uppercase tracking-widest block">{{ mb_strtoupper(__('messages.contact'), 'UTF-8') }}</span>
                            <h2 class="text-3xl font-extrabold text-primary-navy">{{ __('messages.quote_request') }}</h2>
                            <p class="text-gray-500 text-sm">{{ __('messages.quote_desc') }}</p>
                        </div>

                        <form @submit.prevent="submitForm()" class="space-y-4 mt-6">
                            <div>
                                <label for="name" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.fullname') }}</label>
                                <input type="text" id="name" required x-model="name" class="w-full bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="phone" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.phone') }}</label>
                                    <input type="tel" id="phone" required x-model="phone" class="w-full bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                                </div>
                                <div>
                                    <label for="email" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.email') }}</label>
                                    <input type="email" id="email" x-model="email" class="w-full bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                                </div>
                            </div>
                            <div>
                                <label for="message" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.quote_content') }}</label>
                                <textarea id="message" rows="4" required x-model="message" placeholder="Ví dụ: Cần báo giá 200 cuộn Bông thủy tinh chống nước 50mm..." class="w-full bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300 resize-none"></textarea>
                            </div>
                            <button type="submit" :disabled="loading" class="w-full bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase py-3.5 rounded transition-all duration-300 cursor-pointer shadow disabled:opacity-50">
                                <span x-show="!loading">{{ __('messages.send_request') }}</span>
                                <span x-show="loading" style="display: none;">Đang gửi...</span>
                            </button>
                        </form>

                        <!-- Alert Success -->
                        <div x-show="successMessage" class="bg-emerald-50 text-emerald-800 p-4 rounded-lg text-sm border border-emerald-200 mt-4" x-transition style="display: none;">
                            <span x-text="successMessage"></span>
                        </div>
                        <!-- Alert Error -->
                        <div x-show="errorMessage" class="bg-rose-50 text-rose-800 p-4 rounded-lg text-sm border border-rose-200 mt-4" x-transition style="display: none;">
                            <span x-text="errorMessage"></span>
                        </div>
                    </div>

                    <!-- Right: Info cards & Map -->
                    <div class="lg:col-span-7 flex flex-col justify-between space-y-6">
                        <!-- Info cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            
                            <!-- Card 1: Office -->
                            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex flex-col items-center text-center space-y-3 h-full">
                                <div class="p-3 rounded bg-brand-gold/10 text-brand-gold shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div class="space-y-2 text-left w-full">
                                    <h4 class="font-bold text-primary-navy text-sm text-center">{{ __('messages.office') }}</h4>
                                    <div class="text-xs text-gray-500 leading-relaxed space-y-1.5">
                                        <p><strong>{{ __('messages.showroom_1') }}:</strong> {{ __('messages.showroom_1_address') }}</p>
                                        <p><strong>{{ __('messages.showroom_2') }}:</strong> {{ __('messages.showroom_2_address') }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Phone -->
                            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex flex-col items-center text-center space-y-3 h-full">
                                <div class="p-3 rounded bg-brand-gold/10 text-brand-gold shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                </div>
                                <div class="space-y-1">
                                    <h4 class="font-bold text-primary-navy text-sm">{{ __('messages.phone_number') }}</h4>
                                    <p class="text-xs text-gray-500 leading-relaxed select-all">+84 353 728 805<br>{{ __('messages.zalo_ready') }}</p>
                                </div>
                            </div>

                            <!-- Card 3: Email -->
                            <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex flex-col items-center text-center space-y-3 h-full">
                                <div class="p-3 rounded bg-brand-gold/10 text-brand-gold shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div class="space-y-1">
                                    <h4 class="font-bold text-primary-navy text-sm">{{ __('messages.email_support') }}</h4>
                                    <p class="text-xs text-gray-500 leading-relaxed select-all">jykim@abc-group.co.kr<br>{{ __('messages.contact_247') }}</p>
                                </div>
                            </div>

                        </div>

                        <!-- Google Map -->
                        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden h-72 sm:h-96 shadow-inner relative z-10">
                            <iframe 
                                src="https://maps.google.com/maps?q=Vivian%20Park%20Building%2C%20Th%E1%BB%A7%20%C4%90%E1%BB%A9c%2C%20H%E1%BB%93%20Ch%C3%AD%20Minh&t=&z=16&ie=UTF8&iwloc=&output=embed" 
                                width="100%" 
                                height="100%" 
                                style="border:0;" 
                                allowfullscreen="" 
                                class="w-full h-full"
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>

                    </div>

                </div>

            </div>
        </section>
@endsection
