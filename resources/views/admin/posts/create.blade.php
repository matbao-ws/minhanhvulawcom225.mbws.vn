@extends('admin.layouts.app')

@section('title', __('admin.posts.create'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/select2/dist/css/select2.min.css') }}">
@endpush

@section('content')
    <x-admin.page-header :title="__('admin.posts.create')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.posts.index') }}">{{ __('admin.menu.blog') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.posts.create') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <!-- Form Section -->
    <form action="{{ route('admin.posts.store') }}" method="POST" enctype="multipart/form-data" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.posts._form')
    </form>
@endsection

@include('admin.posts._form-assets')
