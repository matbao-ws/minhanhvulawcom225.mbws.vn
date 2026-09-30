@extends('admin.layouts.app')
@section('title', 'Sửa chương trình khuyến mãi')
@section('content')
    <x-admin.page-header :title="'Sửa chương trình: ' . $promotion->name" />
    <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}" class="admin-form-with-sticky-actions">@csrf @method('PUT') @include('admin.promotions._form')</form>
@endsection
