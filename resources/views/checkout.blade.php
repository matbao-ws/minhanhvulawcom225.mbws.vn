@extends('layouts.app')

@section('title', __('messages.checkout_title') . ' - ABCPRO VIETNAM')

@section('content')
<section class="py-16 bg-gray-50" x-data="checkoutPage()" x-init="loadCartItems()">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-extrabold text-primary-navy tracking-tight mb-8">
            {{ __('messages.checkout_title') }}
        </h1>

        <!-- Form and summary container -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
            
            <!-- Left: Shipping info form (7 cols) -->
            <div class="lg:col-span-7 bg-white rounded-2xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <h3 class="text-xl font-extrabold text-primary-navy tracking-tight border-b border-gray-100 pb-4">
                    {{ __('messages.billing_details') }}
                </h3>

                <form @submit.prevent="submitCheckout()" class="space-y-4">
                    <div>
                        <label for="c_name" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.fullname') }}</label>
                        <input type="text" id="c_name" required x-model="customer_name" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="c_phone" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.phone') }}</label>
                            <input type="tel" id="c_phone" required x-model="customer_phone" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                        </div>
                        <div>
                            <label for="c_email" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.email') }}</label>
                            <input type="email" id="c_email" required x-model="customer_email" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                        </div>
                    </div>

                    <div>
                        <label for="c_address" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.shipping_address') }}</label>
                        <input type="text" id="c_address" required x-model="shipping_address" placeholder="Ví dụ: Số 12 Đường ABC, Phường X, Quận Y" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                    </div>

                    <div>
                        <label for="c_notes" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.notes') }}</label>
                        <textarea id="c_notes" rows="3" x-model="notes" placeholder="Ghi chú thêm về địa chỉ giao hàng hoặc thời gian nhận hàng..." class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300 resize-none"></textarea>
                    </div>

                    <!-- Payment methods selection -->
                    <div class="space-y-3 pt-4 border-t border-gray-100">
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.payment_method') }}</label>
                        
                        <div class="grid grid-cols-1 gap-3">
                            
                            <!-- COD -->
                            <label :class="payment_method === 'cod' ? 'border-brand-gold bg-brand-gold/5' : 'border-gray-200 bg-white'" class="border rounded-xl p-4 flex items-center space-x-3 cursor-pointer transition-colors duration-300">
                                <input type="radio" name="payment" value="cod" x-model="payment_method" class="text-primary-navy focus:ring-primary-navy">
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-primary-navy">Thanh toán khi nhận hàng (COD)</span>
                                    <span class="text-xs text-gray-500 font-light">Thanh toán tiền mặt trực tiếp cho nhân viên giao hàng</span>
                                </div>
                            </label>

                            <!-- Bank Transfer -->
                            <label :class="payment_method === 'bank_transfer' ? 'border-brand-gold bg-brand-gold/5' : 'border-gray-200 bg-white'" class="border rounded-xl p-4 flex items-center space-x-3 cursor-pointer transition-colors duration-300">
                                <input type="radio" name="payment" value="bank_transfer" x-model="payment_method" class="text-primary-navy focus:ring-primary-navy">
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-primary-navy">Chuyển khoản ngân hàng</span>
                                    <span class="text-xs text-gray-500 font-light">Chuyển khoản qua số tài khoản công ty (Nhân viên sẽ liên hệ hướng dẫn)</span>
                                </div>
                            </label>

                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" :disabled="loading || cartItems.length === 0" class="w-full bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase py-4 rounded-lg shadow-lg hover:shadow-brand-gold/25 transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer text-center block disabled:opacity-50 mt-6">
                        <span x-show="!loading">{{ __('messages.place_order') }}</span>
                        <span x-show="loading" style="display: none;">Đang tạo đơn hàng...</span>
                    </button>
                </form>

                <!-- Feedback Message -->
                <div x-show="errorMessage" class="bg-rose-50 text-rose-800 p-4 rounded-lg text-sm border border-rose-200 mt-4" x-transition style="display: none;">
                    <span x-text="errorMessage"></span>
                </div>
            </div>

            <!-- Right: Order Summary (5 cols) -->
            <div class="lg:col-span-5 bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
                <h3 class="text-xl font-extrabold text-primary-navy tracking-tight border-b border-gray-100 pb-4">
                    {{ __('messages.order_summary') }}
                </h3>

                <!-- Cart summary items -->
                <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto pr-2">
                    <template x-for="item in cartItems" :key="item.product_id">
                        <div class="py-4 flex items-center justify-between space-x-4">
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="w-12 h-10 bg-gray-50 rounded border border-gray-100 overflow-hidden shrink-0">
                                    <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover">
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-primary-navy truncate" x-text="item.name"></h4>
                                    <span class="text-[9px] text-gray-500 font-medium">Số lượng: <span x-text="item.quantity"></span></span>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-primary-navy shrink-0" x-text="formatCurrency(item.price * item.quantity)"></span>
                        </div>
                    </template>
                </div>

                <!-- Final Calculations -->
                <div class="border-t border-gray-100 pt-6 space-y-4 text-sm">
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

            </div>

        </div>

    </div>
</section>
@endsection

@section('scripts')
<script>
    function checkoutPage() {
        const lookup = @json($products);
        const locale = '{{ app()->getLocale() }}';

        return {
            cartItems: [],
            customer_name: '',
            customer_phone: '',
            customer_email: '',
            shipping_address: '',
            notes: '',
            payment_method: 'cod',
            loading: false,
            errorMessage: '',
            get subtotal() {
                return this.cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            },
            loadCartItems() {
                const local = JSON.parse(localStorage.getItem('cart') || '[]');
                this.cartItems = local.map(item => {
                    const dbProd = lookup[item.product_id];
                    if (!dbProd) return null;
                    
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

                    return {
                        product_id: item.product_id,
                        quantity: item.quantity,
                        name: getTranslation('name', locale),
                        price: parseFloat(dbProd.price),
                        image_url: dbProd.image_url || '/images/products/placeholder.png'
                    };
                }).filter(i => i !== null);
            },
            async submitCheckout() {
                if (this.cartItems.length === 0) return;
                
                this.loading = true;
                this.errorMessage = '';
                
                const itemsPayload = this.cartItems.map(item => ({
                    product_id: item.product_id,
                    variant_id: null,
                    quantity: item.quantity
                }));

                const payload = {
                    customer_name: this.customer_name,
                    customer_phone: this.customer_phone,
                    customer_email: this.customer_email,
                    shipping_address: this.shipping_address,
                    payment_method: this.payment_method,
                    notes: this.notes,
                    items: itemsPayload
                };

                try {
                    const response = await fetch('/api/public/orders/checkout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(payload)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        // Clear cart
                        this.$root.__x.$data.clearCart();
                        // Redirect to success page
                        const orderNumber = result.data.order_number;
                        window.location.href = `/order-success/${orderNumber}`;
                    } else {
                        this.errorMessage = result.message || 'Tạo đơn hàng thất bại. Vui lòng thử lại.';
                    }
                } catch(error) {
                    this.errorMessage = 'Lỗi kết nối mạng. Vui lòng kiểm tra lại đường truyền.';
                } finally {
                    this.loading = false;
                }
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
