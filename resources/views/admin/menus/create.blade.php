@extends('admin.layouts.app')

@section('title', __('admin.menus.create'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h4 class="fw-semibold mb-0">{{ __('admin.menus.create') }}</h4>
        <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.menus.store') }}" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.menus._form')
    </form>
@endsection
