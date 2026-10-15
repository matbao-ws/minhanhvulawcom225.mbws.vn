{{-- An AI page opened while the feature is locked for this user (EnsureAiAccess): what is missing
     and what to do, in place of a redirect to the dashboard. --}}
@extends('admin.layouts.app')

@section('title', $access['title'])

@section('content')
    <x-admin.page-header :title="__('ai.locked.features.'.$access['feature'])">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.locked.features.'.$access['feature']) }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <div class="card" data-ai-locked-page="{{ $access['reason'] }}">
        <div class="card-body text-center py-5 px-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 {{ $access['reason'] === 'permission' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning' }}" style="width: 64px; height: 64px;">
                <i class="ti ti-lock fs-8" aria-hidden="true"></i>
            </div>
            <h4 class="fw-semibold mb-2">{{ $access['title'] }}</h4>
            <p class="text-muted mx-auto mb-4" style="max-width: 520px;">{{ $access['message'] }}</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                @if($access['action_url'])
                    <a class="btn btn-primary" href="{{ $access['action_url'] }}" target="_blank" rel="noopener noreferrer">
                        <i class="ti ti-headset me-1" aria-hidden="true"></i>{{ $access['action_label'] }}
                    </a>
                @endif
                <a class="btn btn-light" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a>
            </div>
        </div>
    </div>
@endsection
