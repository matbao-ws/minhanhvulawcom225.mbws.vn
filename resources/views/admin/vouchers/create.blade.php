@extends('admin.layouts.app')

@section('title', __('admin.vouchers.create'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <h4 class="fw-semibold mb-0">{{ __('admin.vouchers.create') }}</h4>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.vouchers.store') }}" class="admin-form-with-sticky-actions">
        @csrf
        @include('admin.vouchers._form')
    </form>
@endsection
