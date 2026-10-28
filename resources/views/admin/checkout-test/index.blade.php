@extends('admin.layouts.app')

@section('title', __('admin.checkout_test.title'))

@section('content')
    <x-admin.page-header :title="__('admin.checkout_test.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.checkout_test.title') }}</li>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-admin.action-button :href="route('admin.orders.index')" variant="light" icon="list">{{ __('admin.checkout_test.orders_list') }}</x-admin.action-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="alert alert-info d-flex gap-2 align-items-start small">
        <i class="ti ti-info-circle fs-5" aria-hidden="true"></i>
        <div>{!! __('admin.checkout_test.intro') !!}</div>
    </div>

    <form id="checkout-test-form" novalidate
          data-checkout-url="{{ url('/api/public/orders/checkout') }}"
          data-orders-url="{{ route('admin.orders.index') }}">
        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Khách hàng --}}
                <div class="card mb-4">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-semibold"><i class="ti ti-user me-1" aria-hidden="true"></i>{{ __('admin.checkout_test.customer') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="customer_name">{{ __('admin.checkout_test.full_name') }} <span class="text-danger">*</span></label>
                                <input id="customer_name" name="customer_name" class="form-control" required maxlength="255" placeholder="{{ __('admin.checkout_test.name_placeholder') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="customer_phone">{{ __('admin.orders.fields.phone') }} <span class="text-danger">*</span></label>
                                <input id="customer_phone" name="customer_phone" type="tel" class="form-control" required maxlength="20" placeholder="0901234567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="customer_email">Email <span class="text-danger">*</span></label>
                                <input id="customer_email" name="customer_email" type="email" class="form-control" required maxlength="255" placeholder="{{ __('admin.checkout_test.email_placeholder') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment_method">{{ __('admin.orders.fields.payment') }}</label>
                                <select id="payment_method" name="payment_method" class="form-select">
                                    <option value="cod">{{ __('admin.orders.cod') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Địa chỉ --}}
                <div class="card mb-4">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-semibold"><i class="ti ti-map-pin me-1" aria-hidden="true"></i>{{ __('admin.orders.fields.shipping_address') }}</h5></div>
                    <div class="card-body">
                        @if($ghnEnabled)
                            <div class="row g-3 mb-3" id="ghn-address"
                                 data-status-url="{{ route('admin.shipping.ghn.status') }}"
                                 data-provinces-url="{{ route('admin.shipping.ghn.provinces') }}"
                                 data-districts-url="{{ route('admin.shipping.ghn.districts', ['provinceId' => '__ID__']) }}"
                                 data-wards-url="{{ route('admin.shipping.ghn.wards', ['districtId' => '__ID__']) }}">
                                <div class="col-md-4">
                                    <label class="form-label" for="ghn-province">{{ __('admin.orders.ghn_province') }}</label>
                                    <select id="ghn-province" class="form-select" disabled><option value="">{{ __('admin.orders.show_js.loading') }}</option></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="ghn-district">{{ __('admin.orders.ghn_district') }}</label>
                                    <select id="ghn-district" class="form-select" disabled><option value="">{{ __('admin.orders.show_js.choose_province_first') }}</option></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="ghn-ward">{{ __('admin.orders.ghn_ward') }}</label>
                                    <select id="ghn-ward" class="form-select" disabled><option value="">{{ __('admin.orders.show_js.choose_district_first') }}</option></select>
                                </div>
                                <div class="col-12">
                                    <div id="ghn-codes" class="small text-muted" hidden>
                                        {{ __('admin.checkout_test.ghn_codes') }}
                                        <span class="badge bg-light text-dark border">{{ __('admin.orders.ghn_district') }} <strong data-role="district-id"></strong></span>
                                        <span class="badge bg-light text-dark border">{{ __('admin.orders.ghn_ward') }} <strong data-role="ward-code"></strong></span>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="shipping_address">{{ __('admin.checkout_test.address') }} <span class="text-danger">*</span></label>
                                <textarea id="shipping_address" name="shipping_address" class="form-control" rows="2" required maxlength="500" placeholder="{{ __('admin.checkout_test.address_placeholder') }}"></textarea>
                                @if($ghnEnabled)<div class="form-text">{{ __('admin.checkout_test.ghn_address_hint') }}</div>@endif
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">{{ __('admin.orders.form.notes') }}</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="1000"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sản phẩm --}}
                <div class="card mb-0">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold"><i class="ti ti-package me-1" aria-hidden="true"></i>{{ __('admin.orders.form.product') }}</h5>
                        <x-admin.action-button type="button" variant="outline-primary" size="sm" icon="plus" data-action="add-item">{{ __('admin.orders.form.add_product') }}</x-admin.action-button>
                    </div>
                    <div class="card-body">
                        @if($products->isEmpty())
                            <p class="text-muted mb-0">{{ __('admin.checkout_test.no_products') }}</p>
                        @endif
                        <div id="checkout-test-items" class="d-flex flex-column gap-3"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="position-sticky" style="top: 90px;">
                    @if($ghnEnabled)
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-semibold mb-0"><i class="ti ti-truck me-1" aria-hidden="true"></i>{{ __('admin.checkout_test.ghn_connection') }}</h6>
                                    <span id="ghn-status-badge" class="badge bg-secondary-subtle text-secondary">{{ __('admin.checkout_test.checking') }}</span>
                                </div>
                                <p id="ghn-status-message" class="small text-muted mb-0"></p>
                                @can('shipping.view')
                                    <a id="ghn-settings-link" href="{{ route('admin.shipping-partners.index') }}" class="small" hidden>{{ __('admin.checkout_test.open_shipping_settings') }}</a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    <div class="card mb-0">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3">{{ __('admin.checkout_test.summary') }}</h6>
                            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">{{ __('admin.checkout_test.line_count') }}</span><span data-role="line-count">0</span></div>
                            <div class="d-flex justify-content-between small mb-3"><span class="text-muted">{{ __('admin.checkout_test.subtotal') }}</span><span data-role="subtotal">0 ₫</span></div>
                            <p class="small text-muted">{{ __('admin.checkout_test.final_total_note') }}</p>
                            <x-admin.action-button type="submit" icon="shopping-cart" class="w-100" :disabled="$products->isEmpty()">{{ __('admin.checkout_test.submit') }}</x-admin.action-button>
                            <div id="checkout-test-result" class="mt-3" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <template id="checkout-test-item-template">
        <div class="border rounded p-3 position-relative" data-role="item">
            <div class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label small mb-1">{{ __('admin.orders.form.product') }}</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">{{ __('admin.orders.form.lookup.choose_product') }}</option>
                        @foreach($products as $product)
                            <option value="{{ $product['id'] }}">#{{ $product['id'] }} — {{ $product['name'] }}{{ $product['sku'] ? ' ('.$product['sku'].')' : '' }} — {{ number_format($product['price'], 0, ',', '.') }} ₫</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-7 col-md-3">
                    <label class="form-label small mb-1">{{ __('admin.orders.quantity') }}</label>
                    <input name="quantity" type="number" min="1" value="1" class="form-control" required>
                </div>
                <div class="col-5 col-md-2 text-end">
                    <button type="button" class="btn btn-outline-danger" data-action="remove-item" title="{{ __('admin.checkout_test.remove_line') }}" aria-label="{{ __('admin.checkout_test.remove_line') }}">
                        <i class="ti ti-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="row g-2 mt-1" data-role="options"></div>
        </div>
    </template>

    <script type="application/json" id="checkout-test-products">@json($products)</script>
@endsection

@push('scripts')
    <script>window.CheckoutTestText = @json(__('admin.checkout_test.js'));</script>
    <script src="{{ \App\Support\AssetVersion::url('admin-assets/js/checkout-test.js') }}" defer></script>
@endpush
