@extends('admin.layouts.app')

@section('title', __('catalog.categories.create'))

@section('content')
    <x-admin.page-header :title="__('catalog.categories.create')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.categories.index') }}">{{ __('catalog.categories.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('catalog.categories.create') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @include('admin.catalog.categories._form')
    </form>
@endsection
