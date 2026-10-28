@extends('admin.layouts.app')
@section('title', __('catalog.promotions.edit_title'))
@section('content')
    <x-admin.page-header :title="__('catalog.promotions.edit_heading', ['name' => $promotion->name])" />
    <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}" class="admin-form-with-sticky-actions">@csrf @method('PUT') @include('admin.promotions._form')</form>
@endsection
