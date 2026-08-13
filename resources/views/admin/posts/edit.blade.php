@extends('admin.layouts.app')

@section('title', __('admin.posts.edit'))

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
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.posts.edit') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.posts.index') }}">{{ __('admin.menu.blog') }}</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.posts.edit') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Section -->
    <form action="{{ route('admin.posts.update', $post) }}" method="POST" enctype="multipart/form-data" class="admin-form-with-sticky-actions">
        @csrf
        @method('PUT')
        @include('admin.posts._form')
    </form>
@endsection

@include('admin.posts._form-assets')
