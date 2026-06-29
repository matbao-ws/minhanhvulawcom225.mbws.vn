@extends('admin.layouts.app')

@section('title', __('catalog.products.edit'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/quill/dist/quill.snow.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/dropzone/dist/min/dropzone.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/select2/dist/css/select2.min.css') }}">
@endpush

@section('content')
    <div class="card card-body py-3">
        <div class="row align-items-center">
            <div class="col-12">
                <div class="d-sm-flex align-items-center justify-space-between">
                    <h4 class="mb-4 mb-sm-0 card-title">{{ __('catalog.products.edit') }}</h4>
                    <nav aria-label="breadcrumb" class="ms-auto">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item d-flex align-items-center">
                                <a class="text-muted text-decoration-none d-flex" href="{{ route('admin.dashboard') }}">
                                    <iconify-icon icon="solar:home-2-line-duotone" class="fs-6"></iconify-icon>
                                </a>
                            </li>
                            <li class="breadcrumb-item" aria-current="page">
                                <span class="badge fw-medium fs-2 bg-primary-subtle text-primary">{{ __('catalog.products.edit') }}</span>
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.update', $product) }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.catalog.products._form')
    </form>
@endsection

@include('admin.catalog.products._form-assets')
