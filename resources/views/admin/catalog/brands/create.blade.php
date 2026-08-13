@extends('admin.layouts.app')

@section('title', __('catalog.brands.create'))

@section('content')
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <h4 class="fw-semibold mb-0 text-white">{{ __('catalog.brands.create') }}</h4>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.brands.store') }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @include('admin.catalog.brands._form')
    </form>
@endsection
