@extends('layouts.app')

@section('title', __('messages.cart_title') . ' - ABCPRO VIETNAM')

@section('content')
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-extrabold text-primary-navy tracking-tight mb-8">
            {{ __('messages.cart_title') }}
        </h1>

        <!-- Cart View Controller (AlpineJS) -->
        <div x-data="cartPage()" x-init="loadCartItems()" class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
            
            <!-- Left: List of Cart Items (8 cols) -->
            <div class="lg:col-span-8 space-y-6">
                
                <!-- Empty Cart State -->
                <div x-show="cartItems.length === 0" class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm space-y-6">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto text-gray-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-500 font-light text-sm">{{ __('messages.empty_cart') }}</p>
                    <a href="{{ url('/') }}" class="inline-block bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase px-8 py-3.5 rounded-lg shadow transition-colors duration-300">
                        Quay lại trang chủ
                    </a>
                </div>

                <!-- Cart Table / List -->
                <div x-show="cartItems.length > 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" style="display: none;">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-150">
                                    <th class="p-6">{{ __('messages.product') }}</th>
                                    <th class="p-6 text-center">{{ __('messages.price') }}</th>
                                    <th class="p-6 text-center">{{ __('messages.quantity') }}</th>
                                    <th class="p-6 text-right">{{ __('messages.total') }}</th>
                                    <th class="p-6 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <template x-for="item in cartItems" :key="item.product_id">
                                    <tr class="align-middle">
                                        <!-- Product info -->
                                        <td class="p-6 flex items-center space-x-4">
                                            <div class="w-16 h-12 bg-gray-50 rounded overflow-hidden border border-gray-100 shrink-0">
                                                <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover">
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-primary-navy" x-text="item.name"></h4>
                                                <span class="text-[10px] text-brand-gold uppercase font-bold tracking-wider" x-text="item.category_name"></span>
                                            </div>
                                        </td>
                                        <!-- Price -->
                                        <td class="p-6 text-center font-semibold text-gray-700">
                                            <span x-text="formatCurrency(item.price)"></span>
                                        </td>
                                        <!-- Qty -->
                                        <td class="p-6">
                                            <div class="flex items-center justify-center border border-gray-300 rounded overflow-hidden h-9 w-28 mx-auto bg-gray-50">
                                                <button @click="updateQty(item.product_id, item.quantity - 1)" class="px-2.5 text-gray-500 font-bold hover:bg-gray-150 focus:outline-none h-full">-</button>
                                                <input type="number" :value="item.quantity" @change="updateQty(item.product_id, $event.target.value)" class="w-8 text-center bg-transparent border-none text-xs font-bold text-primary-navy focus:outline-none">
                                                <button @click="updateQty(item.product_id, item.quantity + 1)" class="px-2.5 text-gray-500 font-bold hover:bg-gray-150 focus:outline-none h-full">+</button>
                                            </div>
                                        </td>
                                        <!-- Total -->
                                        <td class="p-6 text-right font-bold text-primary-navy">
                                            <span x-text="formatCurrency(item.price * item.quantity)"></span>
                                        </td>
                                        <!-- Actions -->
                                        <td class="p-6 text-center">
                                            <button @click="removeItem(item.product_id)" class="text-gray-400 hover:text-red-500 transition-colors duration-300 focus:outline-none cursor-pointer" title="{{ __('messages.remove') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Right: Order Summary (4 cols) -->
            <div x-show="cartItems.length > 0" class="lg:col-span-4 bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6" style="display: none;">
                <h3 class="text-lg font-extrabold text-primary-navy tracking-tight border-b border-gray-100 pb-4">
                    {{ __('messages.order_summary') }}
                </h3>
                
                <div class="space-y-4 text-sm">
                    <div class="flex justify-between font-light text-gray-600">
                        <span>{{ __('messages.subtotal') }}</span>
                        <span x-text="formatCurrency(subtotal)"></span>
                    </div>
                    <div class="flex justify-between font-light text-gray-600">
                        <span>{{ __('messages.shipping_fee') }}</span>
                        <span class="text-emerald-600 font-semibold">{{ __('messages.free') }}</span>
                    </div>
                    <div class="border-t border-gray-100 pt-4 flex justify-between font-extrabold text-base text-primary-navy">
                        <span>{{ __('messages.total') }}</span>
                        <span x-text="formatCurrency(subtotal)"></span>
                    </div>
                </div>

                <a href="{{ url('/checkout') }}" class="w-full bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase py-4 rounded-lg shadow-lg hover:shadow-brand-gold/25 transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer text-center block">
                    {{ __('messages.proceed_to_checkout') }}
                </a>

                <a href="{{ url('/') }}" class="w-full text-center text-xs font-semibold text-gray-500 hover:text-brand-gold block transition-colors duration-300">
                    {{ __('messages.continue_shopping') }}
                </a>
            </div>

        </div>

    </div>
</section>
@endsection

@section('scripts')
<script>
    function cartPage() {
        const lookup = @json($products);
        const locale = '{{ app()->getLocale() }}';

        return {
            cartItems: [],
            get subtotal() {
                return this.cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            },
            loadCartItems() {
                const local = JSON.parse(localStorage.getItem('cart') || '[]');
                this.cartItems = local.map(item => {
                    const dbProd = lookup[item.product_id];
                    if (!dbProd) return null;
                    
                    // Helper to get translated fields
                    const getTranslation = (field, loc) => {
                        if (dbProd[field] && typeof dbProd[field] === 'object') {
                            return dbProd[field][loc] || dbProd[field]['vi'] || '';
                        }
                        try {
                            const parsed = JSON.parse(dbProd[field]);
                            return parsed[loc] || parsed['vi'] || '';
                        } catch(e) {
                            return dbProd[field] || '';
                        }
                    };

                    const name = getTranslation('name', locale);
                    const category_name = dbProd.category ? getTranslation('category_name', locale) : ''; // wait! dbProd.category might be a relation or ID.
                    // Let's just lookup category if needed, or fallback.
                    
                    return {
                        product_id: item.product_id,
                        quantity: item.quantity,
                        name: name,
                        category_name: dbProd.category ? (dbProd.category.name ? (dbProd.category.name[locale] || dbProd.category.name['vi']) : '') : '',
                        price: parseFloat(dbProd.price),
                        image_url: dbProd.image_url || '/images/products/placeholder.png'
                    };
                }).filter(i => i !== null);
            },
            updateQty(productId, qty) {
                qty = Math.max(1, parseInt(qty));
                this.$root.__x.$data.updateQuantity(productId, qty);
                // Reload
                this.loadCartItems();
            },
            removeItem(productId) {
                this.$root.__x.$data.removeFromCart(productId);
                this.loadCartItems();
            },
            formatCurrency(val) {
                const locale = '{{ app()->getLocale() }}';
                if (locale === 'en') {
                    const usd = Math.round((val / 25000) * 100) / 100;
                    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(usd);
                } else if (locale === 'ko') {
                    const krw = Math.round(val / 1800) * 100;
                    return new Intl.NumberFormat('ko-KR', { style: 'currency', currency: 'KRW', minimumFractionDigits: 0 }).format(krw);
                } else {
                    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', minimumFractionDigits: 0 }).format(val);
                }
            }
        }
    }
</script>
@endsection
