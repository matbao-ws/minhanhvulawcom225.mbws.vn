@extends('admin.layouts.app')

@section('title', __('admin.payment_methods.edit_self_delivery'))

@section('content')
    <!-- Header Card -->
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.payment_methods.edit_self_delivery') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.payment-methods.index') }}">{{ __('admin.payment_methods.title') }}</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.payment_methods.edit_title') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form Card -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.payment_methods.info_shipping_partner') }}</h5>
            
            <form action="{{ route('admin.payment-methods.update', $method) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold text-dark" for="name">{{ __('admin.payment_methods.name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-dark" id="name" name="name" 
                            value="{{ old('name', $method->name) }}" required>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold text-dark" for="description">{{ __('admin.payment_methods.fee') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control text-dark" id="description" name="description" rows="4" required>{{ old('description', $method->settings['description'] ?? '') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-2 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        {{ __('admin.payment_methods.update') }}
                    </button>
                    <a href="{{ route('admin.payment-methods.index') }}" class="btn btn-outline-secondary px-4">
                        {{ __('admin.payment_methods.back') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
