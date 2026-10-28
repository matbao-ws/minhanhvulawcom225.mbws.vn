@extends('admin.layouts.app')

@section('title', __('catalog.variants.edit'))

@section('content')
        <x-admin.page-header :title="__('catalog.variants.edit_for', ['name' => $product->name])" />

    <form method="POST" action="{{ route('admin.products.variants.update', [$product, $variant]) }}" class="admin-form-with-sticky-actions">
        @csrf
        @method('PUT')
        @include('admin.catalog.variants._form')
    </form>
@endsection
