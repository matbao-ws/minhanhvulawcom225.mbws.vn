@extends('admin.layouts.app')

@section('title', __('catalog.variants.create'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <h4 class="fw-semibold mb-0">{{ __('catalog.variants.create') }}: {{ $product->name }}</h4>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.variants.store', $product) }}" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.catalog.variants._form')
    </form>
@endsection
