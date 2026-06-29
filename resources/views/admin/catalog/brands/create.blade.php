@extends('admin.layouts.app')

@section('title', __('catalog.brands.create'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <h4 class="fw-semibold mb-0">{{ __('catalog.brands.create') }}</h4>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.brands.store') }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @include('admin.catalog.brands._form')
    </form>
@endsection
