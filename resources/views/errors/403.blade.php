@php
    $segments = request()->segments();
    if (!empty($segments) && in_array($segments[0], ['vi', 'en'])) {
        app()->setLocale($segments[0]);
    }
    $isAdminContext = $isAdminContext ?? (request()->is('*/admin*') || request()->is('admin*') || (request()->is('error-preview*') && request()->query('context') !== 'client'));
    $currentLocale = app()->getLocale();
    $adminDashboardUrl = $adminDashboardUrl ?? route('admin.dashboard', ['locale' => $currentLocale]);
    $clientHomeUrl = $clientHomeUrl ?? (\Illuminate\Support\Facades\Route::has('client.home') ? route('client.home') : null);
@endphp

@extends('errors.layout')

@section('title', __('errors.403.title'))
@section('code', '403')
@section('image', asset('admin-assets/images/icons/403.png'))
@section('badge', __('errors.403.badge'))
@section('heading', __('errors.403.heading'))
@php
    $rawMsg = ($exception && is_string($exception->getMessage())) ? trim($exception->getMessage()) : '';
    $ignoreMsg = empty($rawMsg) || in_array($rawMsg, ['Forbidden', 'This action is unauthorized.', 'Trang không tồn tại.'], true);
@endphp
@section('message', $ignoreMsg ? __('errors.403.message') : $rawMsg)
@section('tip', __('errors.403.tip'))

@section('actions')
    @if($isAdminContext)
        <a href="{{ $adminDashboardUrl }}" class="btn-cta btn-primary-cta" id="btn-dashboard-403">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="7" height="9" x="3" y="3" rx="1"></rect>
                <rect width="7" height="5" x="14" y="3" rx="1"></rect>
                <rect width="7" height="9" x="14" y="12" rx="1"></rect>
                <rect width="7" height="5" x="3" y="16" rx="1"></rect>
            </svg>
            <span>{{ __('errors.common.go_to_dashboard') }}</span>
        </a>

        <button type="button" onclick="safeGoBack()" class="btn-cta btn-secondary-cta" id="btn-back-403">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>{{ __('errors.common.go_back') }}</span>
        </button>
    @else
        <button type="button" onclick="safeGoBack()" class="btn-cta btn-primary-cta" id="btn-back-403">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>{{ __('errors.common.go_back') }}</span>
        </button>

        @if($clientHomeUrl)
            <a href="{{ $clientHomeUrl }}" class="btn-cta btn-secondary-cta" id="btn-home-403">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>{{ __('errors.common.back_to_home') }}</span>
            </a>
        @endif
    @endif
@endsection
