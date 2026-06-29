@extends('admin.layouts.app')

@section('title', __('admin.posts.create'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/quill/dist/quill.snow.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/select2/dist/css/select2.min.css') }}">
    <style>
        .seo-checker-card {
            border: 1px solid rgba(22, 163, 74, 0.15);
            background-color: rgba(22, 163, 74, 0.02);
            border-radius: 8px;
        }
        .seo-rule-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        .seo-status-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
            margin-top: 3px;
        }
        .seo-status-red {
            background-color: #ef4444;
        }
        .seo-status-orange {
            background-color: #f97316;
        }
        .seo-status-green {
            background-color: #22c55e;
        }
        .seo-status-red + span {
            color: #ef4444;
        }
        .seo-status-orange + span {
            color: #f97316;
        }
        .seo-status-green + span {
            color: #22c55e;
        }
        .seo-progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
    </style>
@endpush

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.posts.create') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.posts.index') }}">{{ __('admin.menu.blog') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.posts.create') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Section -->
    <form action="{{ route('admin.posts.store') }}" method="POST" enctype="multipart/form-data" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.posts._form')
    </form>
@endsection

@include('admin.posts._form-assets')
