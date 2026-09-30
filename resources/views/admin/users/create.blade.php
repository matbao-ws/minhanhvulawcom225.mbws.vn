@extends('admin.layouts.app')

@section('title', __('admin.users.create'))

@section('content')
    <x-admin.page-header :title="__('admin.users.create')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.users.index') }}">{{ __('admin.users.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.users.create') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.users.store') }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @include('admin.users._form')
    </form>
@endsection

@include('admin.users._form-assets')
