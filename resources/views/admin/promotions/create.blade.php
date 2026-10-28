@extends('admin.layouts.app')
@section('title', __('catalog.promotions.create_title'))
@section('content')
    <x-admin.page-header :title="__('catalog.promotions.create_heading')" />
    <form method="POST" action="{{ route('admin.promotions.store') }}" class="admin-form-with-sticky-actions">@csrf @include('admin.promotions._form')</form>
@endsection
