@extends('admin.layouts.app')

@section('title', __('admin.menus.items.edit'))

@section('content')
    <x-admin.page-header :title="__('admin.menus.items.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.menus.index') }}">{{ __('admin.menus.title') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.menus.items.index', $menu) }}">{{ $menu->name }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.menus.items.edit') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.menus.items.update', [$menu, $item]) }}" class="admin-form-with-sticky-actions">
        @csrf @method('PUT')
        @include('admin.menus.items._form')
    </form>
@endsection
