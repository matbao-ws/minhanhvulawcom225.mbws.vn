@extends('admin.layouts.app')
@section('title', 'Sửa chương trình khuyến mãi')
@section('content')
    <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;"><div class="card-body px-4 py-3"><h4 class="fw-semibold mb-0 text-white">Sửa chương trình: {{ $promotion->name }}</h4></div></div>
    <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}" class="admin-form-with-sticky-actions">@csrf @method('PUT') @include('admin.promotions._form')</form>
@endsection
