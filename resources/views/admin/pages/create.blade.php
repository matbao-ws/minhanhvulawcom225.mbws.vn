@extends('admin.layouts.app')

@section('title', 'Thêm trang')

@section('content')
    <x-admin.page-header title="Thêm trang">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.pages.index') }}">Trang nội dung</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">Thêm trang</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.pages.store') }}" id="page-form">
        @csrf
        @include('admin.pages._form')
    </form>
@endsection
