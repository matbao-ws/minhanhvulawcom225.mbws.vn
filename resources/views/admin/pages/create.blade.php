@extends('admin.layouts.app')

@section('title', __('admin.pages.create'))

@section('content')
    <x-admin.page-header :title="__('admin.pages.create')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.pages.index') }}">{{ __('admin.pages.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.pages.create') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.pages.store') }}" id="page-form">
        @csrf
        @include('admin.pages._form')
    </form>
@endsection
