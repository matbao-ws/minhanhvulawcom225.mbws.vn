@extends('admin.layouts.app')

@section('title', __('catalog.brands.edit'))

@section('content')
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(135deg, #141e30 0%, #243b55 100%) !important;">
                <div class="card-body px-4 py-3">
                    <h4 class="fw-semibold mb-0 text-white">{{ __('catalog.brands.edit') }}</h4>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.brands.update', $brand) }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.catalog.brands._form')
    </form>
@endsection
