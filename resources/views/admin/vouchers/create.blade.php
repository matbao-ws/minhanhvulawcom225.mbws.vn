@extends('admin.layouts.app')

@section('title', __('admin.vouchers.create'))

@section('content')
        <x-admin.page-header :title="__('admin.vouchers.create')" />

    <form method="POST" action="{{ route('admin.vouchers.store') }}" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.vouchers._form')
    </form>
@endsection
