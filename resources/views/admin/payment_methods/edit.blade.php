@extends('admin.layouts.app')

@section('title', __('admin.payment_methods.edit_self_delivery'))

@section('content')
    <x-admin.page-header :title="__('admin.payment_methods.edit_self_delivery')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.payment-methods.index') }}">{{ __('admin.payment_methods.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.payment_methods.edit_title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <!-- Edit Form Card -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.payment_methods.info_shipping_partner') }}</h5>
            
            <form action="{{ route('admin.payment-methods.update', $method) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div data-tour="payment-method-form.name" class="col-md-12 mb-3">
                        <label class="form-label fw-semibold text-dark" for="name">{{ __('admin.payment_methods.name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-dark" id="name" name="name" 
                            value="{{ old('name', $method->name) }}" required>
                    </div>
                    <div data-tour="payment-method-form.description" class="col-md-12 mb-3">
                        <label class="form-label fw-semibold text-dark" for="description">{{ __('admin.payment_methods.fee') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control text-dark" id="description" name="description" rows="4" required>{{ old('description', $method->settings['description'] ?? '') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-2 border-top d-flex gap-2">
                    <button data-tour="payment-method-form.submit" type="submit" class="btn btn-primary px-4 fw-semibold">
                        {{ __('admin.payment_methods.update') }}
                    </button>
                    <a data-tour="payment-method-form.back" href="{{ route('admin.payment-methods.index') }}" class="btn btn-outline-secondary px-4">
                        {{ __('admin.payment_methods.back') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
