@extends('admin.layouts.app')

@section('title', __('catalog.brands.edit'))

@section('content')
    <x-admin.page-header :title="__('catalog.brands.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.brands.index') }}">{{ __('catalog.brands.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('catalog.brands.edit') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.brands.update', $brand) }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.catalog.brands._form')
    </form>
@endsection
