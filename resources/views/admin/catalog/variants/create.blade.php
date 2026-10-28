@extends('admin.layouts.app')

@section('title', __('catalog.variants.create'))

@section('content')
        <x-admin.page-header :title="__('catalog.variants.create_for', ['name' => $product->name])" />

    <form method="POST" action="{{ route('admin.products.variants.store', $product) }}" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.catalog.variants._form')
    </form>
@endsection
