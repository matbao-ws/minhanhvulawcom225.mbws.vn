@extends('admin.layouts.app')

@section('title', __('admin.posts.edit'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/select2/dist/css/select2.min.css') }}">
@endpush

@section('content')
    <x-admin.page-header :title="__('admin.posts.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.posts.index') }}">{{ __('admin.menu.blog') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.posts.edit') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @include('admin.shared.ai-draft-review', ['post' => $post])

    <!-- Form Section -->
    <form action="{{ route('admin.posts.update', $post) }}" method="POST" enctype="multipart/form-data" class="admin-form-with-sticky-actions">
        @csrf
        @method('PUT')
        @include('admin.posts._form')
    </form>
@endsection

@include('admin.posts._form-assets')
