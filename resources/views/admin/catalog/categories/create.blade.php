@extends('admin.layouts.app')

@section('title', __('catalog.categories.create'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <h4 class="fw-semibold mb-0">{{ __('catalog.categories.create') }}</h4>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @include('admin.catalog.categories._form')
    </form>
@endsection
