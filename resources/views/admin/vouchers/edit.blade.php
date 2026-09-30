@extends('admin.layouts.app')

@section('title', __('admin.vouchers.edit'))

@section('content')
        <x-admin.page-header :title="__('admin.vouchers.edit') . ': ' . $voucher->code" />

    <form method="POST" action="{{ route('admin.vouchers.update', $voucher) }}" class="admin-form-with-sticky-actions">
        @csrf
        @method('PUT')
        @include('admin.vouchers._form')
    </form>
@endsection
