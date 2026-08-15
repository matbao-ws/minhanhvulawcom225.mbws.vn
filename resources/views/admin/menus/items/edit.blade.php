@extends('admin.layouts.app')

@section('title', __('admin.menus.items.edit'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-semibold mb-1">{{ __('admin.menus.items.edit') }}</h4>
            <p class="text-muted mb-0">{{ $menu->name }}</p>
        </div>
        <a href="{{ route('admin.menus.items.index', $menu) }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.menus.items.update', [$menu, $item]) }}" class="admin-form-with-sticky-actions">
        @csrf @method('PUT')
        @include('admin.menus.items._form')
    </form>
@endsection
