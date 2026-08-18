@extends('admin.layouts.app')

@section('title', __('admin.menus.edit'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h4 class="fw-semibold mb-0">{{ __('admin.menus.edit') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.menus.items.index', $menu) }}" class="btn btn-outline-primary">
                <i class="ti ti-list-tree me-1"></i>{{ __('admin.menus.manage_items') }}
            </a>
            <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="admin-form-with-sticky-actions">
        @csrf @method('PUT')
        @include('admin.menus._form')
    </form>
@endsection
