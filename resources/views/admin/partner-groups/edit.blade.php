@extends('admin.layouts.app')

@section('title', __('partners.groups.edit'))

@section('content')
    <x-admin.page-header :title="__('partners.groups.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.partner-groups.index') }}">{{ __('partners.groups.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('partners.groups.edit') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.partner-groups.update', $group) }}" class="admin-form-with-sticky-actions">
        @csrf
        @method('PUT')
        @include('admin.partner-groups._form')
    </form>
@endsection
