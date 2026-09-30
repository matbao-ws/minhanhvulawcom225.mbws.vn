@extends('admin.layouts.app')
@section('title', 'Tạo chương trình khuyến mãi')
@section('content')
    <x-admin.page-header title="Tạo chương trình khuyến mãi / Flash Sale" />
    <form method="POST" action="{{ route('admin.promotions.store') }}" class="admin-form-with-sticky-actions">@csrf @include('admin.promotions._form')</form>
@endsection
