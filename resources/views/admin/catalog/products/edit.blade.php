@extends('admin.layouts.app')

@section('title', __('catalog.products.edit'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/select2/dist/css/select2.min.css') }}">
@endpush

@section('content')
    <x-admin.page-header :title="__('catalog.products.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.products.index') }}">{{ __('admin.menu.products') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('catalog.products.edit') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.products.update', $product) }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.catalog.products._form')
    </form>
@endsection

@include('admin.catalog.products._form-assets')
