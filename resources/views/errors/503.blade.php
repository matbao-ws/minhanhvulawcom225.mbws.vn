@php
    $segments = request()->segments();
    if (!empty($segments) && app(\App\Services\LanguageRegistry::class)->supports($segments[0])) {
        app()->setLocale($segments[0]);
    }
    $isAdminContext = $isAdminContext ?? (request()->is('*/admin*') || request()->is('admin*') || (request()->is('error-preview*') && request()->query('context') !== 'client'));
    $currentLocale = app()->getLocale();
    $adminDashboardUrl = $adminDashboardUrl ?? route('admin.dashboard', ['locale' => $currentLocale]);
    $clientHomeUrl = $clientHomeUrl ?? (\Illuminate\Support\Facades\Route::has('client.home') ? route('client.home', ['locale' => $currentLocale]) : null);
@endphp

@extends('errors.layout')

@section('title', __('errors.503.title'))
@section('code', '503')
@section('image', asset('admin-assets/images/icons/503.png'))
@section('badge', __('errors.503.badge'))
@section('heading', __('errors.503.heading'))
@php
    $rawMsg = ($exception && is_string($exception->getMessage())) ? trim($exception->getMessage()) : '';
    $ignoreMsg = empty($rawMsg) || in_array($rawMsg, ['Service Unavailable', 'Trang không tồn tại.'], true);
@endphp
@section('message', $ignoreMsg ? __('errors.503.message') : $rawMsg)
@section('tip', __('errors.503.tip'))

@section('actions')
    <button type="button" onclick="window.location.reload()" class="btn-cta btn-primary-cta" id="btn-reload-503">
        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
        </svg>
        <span>{{ __('errors.common.reload_page') }}</span>
    </button>

    @if($isAdminContext)
        <a href="{{ $adminDashboardUrl }}" class="btn-cta btn-secondary-cta" id="btn-dashboard-503">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="7" height="9" x="3" y="3" rx="1"></rect>
                <rect width="7" height="5" x="14" y="3" rx="1"></rect>
                <rect width="7" height="9" x="14" y="12" rx="1"></rect>
                <rect width="7" height="5" x="3" y="16" rx="1"></rect>
            </svg>
            <span>{{ __('errors.common.go_to_dashboard') }}</span>
        </a>
    @elseif($clientHomeUrl)
        <a href="{{ $clientHomeUrl }}" class="btn-cta btn-secondary-cta" id="btn-home-503">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>{{ __('errors.common.back_to_home') }}</span>
        </a>
    @else
        <button type="button" onclick="safeGoBack()" class="btn-cta btn-secondary-cta" id="btn-back-503">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>{{ __('errors.common.go_back') }}</span>
        </button>
    @endif
@endsection
