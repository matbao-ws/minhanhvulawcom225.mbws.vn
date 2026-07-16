@extends('layouts.app')

@section('title', __('messages.order_success') . ' - ABCPRO VIETNAM')

@section('content')
<section class="py-20 bg-gray-50 flex items-center justify-center min-h-[60vh]">
    <div class="max-w-md w-full mx-auto px-4">
        
        <div class="bg-white rounded-2xl p-8 sm:p-10 border border-gray-100 shadow-lg text-center space-y-6">
            
            <!-- Success Icon -->
            <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto border-4 border-emerald-100 shadow-inner">
                <svg class="w-10 h-10 animate-pulse" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <!-- Title -->
            <div class="space-y-2">
                <h1 class="text-2xl font-extrabold text-primary-navy tracking-tight">
                    {{ __('messages.order_success') }}
                </h1>
                <p class="text-xs text-gray-500 font-light leading-relaxed">
                    {{ __('messages.order_success_thank_you') }}
                </p>
                <div class="inline-block bg-brand-gold/15 border border-brand-gold/30 rounded-lg px-4 py-2 font-black text-brand-gold text-lg tracking-wider">
                    {{ $order->order_number }}
                </div>
            </div>

            <!-- Details list -->
            <div class="border-t border-b border-gray-100 py-4 text-left text-xs space-y-2.5">
                <div class="flex justify-between">
                    <span class="text-gray-400 font-medium">Khách hàng:</span>
                    <span class="text-primary-navy font-bold">{{ $order->customer_name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400 font-medium">Số điện thoại:</span>
                    <span class="text-primary-navy font-semibold">{{ $order->customer_phone }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400 font-medium">Tổng thanh toán:</span>
                    <span class="text-brand-gold font-extrabold">{{ app()->getLocale() === 'en' ? '$' . number_format($order->grand_total / 25000, 2) : (app()->getLocale() === 'ko' ? number_format(round($order->grand_total / 18, -2), 0, '.', ',') . '원' : number_format($order->grand_total, 0, ',', '.') . ' đ') }}</span>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-gray-400 font-medium shrink-0 mr-4">Địa chỉ giao:</span>
                    <span class="text-primary-navy font-light text-right leading-relaxed">{{ $order->shipping_address }}</span>
                </div>
            </div>

            <p class="text-xs text-gray-500 font-light leading-relaxed italic">
                {{ __('messages.order_success_desc') }}
            </p>

            <a href="{{ url('/') }}" class="w-full bg-primary-navy hover:bg-brand-gold text-white hover:text-primary-navy font-bold uppercase py-3.5 rounded-lg shadow-md hover:shadow-brand-gold/20 transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer block text-center">
                Tiếp tục mua sắm
            </a>

        </div>

    </div>
</section>
@endsection
