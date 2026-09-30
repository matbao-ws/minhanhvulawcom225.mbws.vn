@extends('admin.layouts.app')

@section('title', __('admin.menus.edit'))

@section('content')
    <x-admin.page-header :title="__('admin.menus.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.menus.index') }}">{{ __('admin.menus.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.menus.edit') }}</li>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-admin.action-button :href="route('admin.menus.items.index', $menu)" variant="light" icon="list-tree">{{ __('admin.menus.manage_items') }}</x-admin.action-button>
        </x-slot:actions>
    </x-admin.page-header>


    <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="admin-form-with-sticky-actions">
        @csrf @method('PUT')
        @include('admin.menus._form')
    </form>
@endsection
