@extends('admin.layouts.app')

@section('title', __('admin.shipping_partners.add_custom_partner'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.shipping_partners.add_self_delivery') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.shipping-partners.index') }}">{{ __('admin.shipping_partners.title') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.shipping_partners.add_self_delivery') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Form Card -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.shipping_partners.info_self_delivery') }}</h5>
            
            <form action="{{ route('admin.shipping-partners.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-dark" for="name">{{ __('admin.shipping_partners.name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-dark" id="name" name="name" 
                            value="{{ old('name') }}" placeholder="Ví dụ: Tự giao hàng nhanh nội thành" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-dark" for="fee">{{ __('admin.shipping_partners.fee') }} <span class="text-danger">*</span></label>
                        <input type="number" class="form-control text-dark" id="fee" name="fee" 
                            value="{{ old('fee', 30000) }}" placeholder="Ví dụ: 30000" min="0" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-dark" for="phone">{{ __('admin.shipping_partners.phone') }}</label>
                        <input type="text" class="form-control text-dark" id="phone" name="phone" 
                            value="{{ old('phone') }}" placeholder="Số điện thoại tài xế hoặc hotline...">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-dark" for="account_name">{{ __('admin.shipping_partners.description') }}</label>
                        <input type="text" class="form-control text-dark" id="account_name" name="account_name" 
                            value="{{ old('account_name') }}" placeholder="Ghi chú thêm thông tin tài khoản hoặc khu vực...">
                    </div>
                </div>

                <div class="mt-4 pt-2 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        {{ __('admin.shipping_partners.save') }}
                    </button>
                    <a href="{{ route('admin.shipping-partners.index') }}" class="btn btn-outline-secondary px-4">
                        {{ __('admin.shipping_partners.back') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
