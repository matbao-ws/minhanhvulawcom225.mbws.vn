@extends('admin.layouts.app')

@section('title', __('admin.payment_methods.edit_self_delivery'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.payment_methods.edit_self_delivery') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.payment-methods.index') }}">{{ __('admin.payment_methods.title') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.payment_methods.edit_title') }}</li>
                        </ol>
                    </nav>
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
