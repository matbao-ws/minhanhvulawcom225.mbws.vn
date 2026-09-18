@extends('admin.layouts.app')

@section('title', __('admin.menus.items.create'))

@section('content')
    <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
        <div class="card-body px-4 py-3">
            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.menus.items.create') }}</h4>
            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.menus.index') }}">{{ __('admin.menus.title') }}</a></li>
                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.menus.items.index', $menu) }}">{{ $menu->name }}</a></li>
                    <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.menus.items.create') }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.menus.items.store', $menu) }}" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.menus.items._form')
    </form>
@endsection
