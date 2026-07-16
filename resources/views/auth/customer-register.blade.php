@extends('layouts.app')

@section('title', __('messages.register') . ' - ABCPRO VIETNAM')

@section('content')
<section class="py-20 bg-gray-50 flex items-center justify-center min-h-[70vh]">
    <div class="max-w-md w-full mx-auto px-4">
        <div class="bg-white rounded-2xl p-8 sm:p-10 border border-gray-100 shadow-sm space-y-6" 
             x-data="{
                email: '',
                loading: false,
                errorMessage: '',
                successMessage: '',
                async submitRegister() {
                    this.loading = true;
                    this.errorMessage = '';
                    this.successMessage = '';
                    try {
                        const response = await fetch('/api/public/auth/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                email: this.email
                            })
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload.success) {
                            this.errorMessage = payload.message || 'Không thể đăng ký tài khoản.';
                            return;
                        }
                        this.successMessage = payload.message || '{{ __("messages.register_success_msg") }}';
                        this.email = '';
                    } catch (error) {
                        this.errorMessage = 'Không thể kết nối tới máy chủ. Vui lòng thử lại.';
                    } finally {
                        this.loading = false;
                    }
                }
             }">
            <div class="text-center space-y-2">
                <h2 class="text-3xl font-extrabold text-primary-navy">{{ __('messages.create_account') }}</h2>
                <p class="text-gray-500 text-sm leading-relaxed">{{ __('messages.register_desc') }}</p>
            </div>

            <!-- Error message alert -->
            <div x-show="errorMessage" class="bg-rose-50 text-rose-800 p-4 rounded-lg text-sm border border-rose-200" x-transition style="display: none;">
                <span x-text="errorMessage"></span>
            </div>

            <!-- Success message alert -->
            <div x-show="successMessage" class="bg-emerald-50 text-emerald-800 p-4 rounded-lg text-sm border border-emerald-200" x-transition style="display: none;">
                <span x-text="successMessage"></span>
            </div>

            <form @submit.prevent="submitRegister()" class="space-y-4" x-show="!successMessage">
                <div>
                    <label for="email" class="block text-xs font-bold text-gray-600 uppercase mb-2">Email</label>
                    <input type="email" id="email" required x-model="email" class="w-full bg-gray-50 border border-gray-200 rounded px-4 py-3 text-sm focus:outline-none focus:border-brand-gold transition-colors duration-300">
                </div>

                <button type="submit" :disabled="loading" class="w-full bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase py-3.5 rounded transition-all duration-300 cursor-pointer shadow disabled:opacity-50 mt-2">
                    <span x-show="!loading">{{ __('messages.register') }}</span>
                    <span x-show="loading" style="display: none;">Đang xử lý...</span>
                </button>
            </form>

            <div class="text-center text-xs text-gray-500 pt-2 border-t border-gray-150">
                <span>{{ __('messages.has_account') }}</span>
                <a href="{{ route('login') }}" class="text-brand-gold font-bold hover:underline ml-1">{{ __('messages.login') }}</a>
            </div>
        </div>
    </div>
</section>
@endsection
