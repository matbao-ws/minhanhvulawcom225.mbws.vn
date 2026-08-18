@extends('admin.layouts.app')

@section('title', __('admin.menus.items.create'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-semibold mb-1">{{ __('admin.menus.items.create') }}</h4>
            <p class="text-muted mb-0">{{ $menu->name }}</p>
        </div>
        <a href="{{ route('admin.menus.items.index', $menu) }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.menus.items.store', $menu) }}" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.menus.items._form')
    </form>
@endsection
