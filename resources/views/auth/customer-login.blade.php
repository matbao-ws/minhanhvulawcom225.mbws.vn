@extends('layouts.app')

@section('title', __('messages.login') . ' - ABCPRO VIETNAM')

@section('content')
<section class="py-20 bg-gray-50 flex items-center justify-center min-h-[70vh]">
    <div class="max-w-md w-full mx-auto px-4">
        <div class="bg-white rounded-2xl p-8 sm:p-10 border border-gray-100 shadow-sm space-y-6" 
             x-data="{
                email: '',
                password: '',
                loading: false,
                errorMessage: '',
                async submitLogin() {
                    this.loading = true;
                    this.errorMessage = '';
                    try {
                        const response = await fetch('/api/public/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                email: this.email,
                                password: this.password
                            })
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload.success) {
                            this.errorMessage = payload.message || 'Mật khẩu hoặc email không chính xác.';
                            return;
                        }
                        // Save login info
                        localStorage.setItem('customer', JSON.stringify(payload.data.user));
                        localStorage.setItem('customer_token', payload.data.token);
                        // Redirect to homepage
                        window.location.href = '{{ url('/') }}';
                    } catch (error) {
                        this.errorMessage = 'Không thể kết nối tới máy chủ. Vui lòng thử lại.';
                    } finally {
                        this.loading = false;
                    }
                }
             }">
            <div class="text-center space-y-2">
                <h2 class="text-3xl font-extrabold text-primary-navy">{{ __('messages.login') }}</h2>
                <p class="text-gray-500 text-sm">{{ __('messages.auth_welcome') }}</p>
            </div>

            <!-- Error message alert -->
            <div x-show="errorMessage" class="bg-rose-50 text-rose-800 p-4 rounded-lg text-sm border border-rose-200" x-transition style="display: none;">
                <span x-text="errorMessage"></span>
            </div>

            <form @submit.prevent="submitLogin()" class="space-y-4">
                <div>
                    <label for="email" class="block text-xs font-bold text-gray-600 uppercase mb-2">Email</label>
                    <input type="email" id="email" required x-model="email" class="w-full bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                </div>
                <div>
                    <label for="password" class="block text-xs font-bold text-gray-600 uppercase mb-2">{{ __('messages.password') }}</label>
                    <input type="password" id="password" required x-model="password" class="w-full bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                </div>

                <button type="submit" :disabled="loading" class="w-full bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase py-3.5 rounded transition-all duration-300 cursor-pointer shadow disabled:opacity-50 mt-2">
                    <span x-show="!loading">{{ __('messages.login') }}</span>
                    <span x-show="loading" style="display: none;">Đang xử lý...</span>
                </button>
            </form>

            <div class="text-center text-xs text-gray-500 pt-2 border-t border-gray-150">
                <span>{{ __('messages.no_account') }}</span>
                <a href="{{ route('register') }}" class="text-brand-gold font-bold hover:underline ml-1">{{ __('messages.register') }}</a>
            </div>
        </div>
    </div>
</section>
@endsection
