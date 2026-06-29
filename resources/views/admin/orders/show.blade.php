@extends('admin.layouts.app')

@section('title', __('admin.orders.order_details') . ' ' . $order->order_number)

@push('styles')
<style>
    @media print {
        /* Hide everything except the order sheet */
        header, .sidebar-link, .preloader, #main-wrapper > aside, .page-wrapper > header,
        .print-btn-header, footer, .dark-transparent, .status-card {
            display: none !important;
        }
        
        body, #main-wrapper, .page-wrapper, .body-wrapper, .container-fluid {
            margin: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
            background: #ffffff !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }
        
        .order-print-area {
            border: none !important;
            padding: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
        }
    }
    
    .order-stamp {
        border: 3px solid;
        padding: 8px 16px;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 1.3rem;
        display: inline-block;
        transform: rotate(-7deg);
        border-radius: 4px;
        opacity: 0.85;
    }
    .stamp-completed {
        border-color: #2ec37e;
        color: #2ec37e;
    }
    .stamp-processing {
        border-color: #00bcd4;
        color: #00bcd4;
    }
    .stamp-pending {
        border-color: #ffaa00;
        color: #ffaa00;
    }
    .stamp-cancelled {
        border-color: #ef4444;
        color: #ef4444;
    }
</style>
@endpush

@section('content')
    <!-- Print & Navigation Header (hidden when printing) -->
    <div class="d-flex align-items-center justify-content-between mb-4 print-btn-header">
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="ti ti-arrow-left fs-4"></i>{{ __('catalog.actions.back') }}
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="ti ti-printer fs-4"></i>{{ __('admin.invoices.print') }}
            </button>
        </div>
    </div>

    <!-- Success Notification -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show print-btn-header" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-check fs-5"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Update Status Card (hidden when printing) -->
    <div class="card mb-4 status-card border border-opacity-10 border-primary shadow-none">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3 text-primary d-flex align-items-center gap-2">
                <i class="ti ti-edit fs-5"></i>{{ __('admin.orders.update_status') }}
            </h5>
            <form action="{{ route('admin.orders.update-status', $order) }}" method="POST" class="row g-3 align-items-end">
                @csrf
                @method('PATCH')
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.orders.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="pending" @selected($order->status === 'pending')>{{ __('admin.orders.statuses.pending') }}</option>
                        <option value="processing" @selected($order->status === 'processing')>{{ __('admin.orders.statuses.processing') }}</option>
                        <option value="completed" @selected($order->status === 'completed')>{{ __('admin.orders.statuses.completed') }}</option>
                        <option value="cancelled" @selected($order->status === 'cancelled')>{{ __('admin.orders.statuses.cancelled') }}</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.orders.fields.payment_status') }}</label>
                    <select name="payment_status" class="form-select">
                        <option value="pending" @selected($order->payment_status === 'pending')>{{ __('admin.orders.payment_statuses.pending') }}</option>
                        <option value="paid" @selected($order->payment_status === 'paid')>{{ __('admin.orders.payment_statuses.paid') }}</option>
                        <option value="failed" @selected($order->payment_status === 'failed')>{{ __('admin.orders.payment_statuses.failed') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        {{ __('admin.orders.update_status') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Order Sheet -->
    <div class="card order-print-area border border-opacity-10 border-primary" style="box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05);">
        <div class="card-body p-4 p-md-5">
            <!-- Header Section -->
            <div class="row align-items-start mb-5 gap-4 gap-md-0">
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="{{ asset('admin-assets/images/logos/favicon.png') }}" alt="logo" width="35" height="35">
                        <span class="fs-6 fw-bold text-primary">{{ strtoupper(config('app.name', 'Laravel Ecommerce Store')) }}</span>
                    </div>
                    <p class="text-muted mb-0 fs-3">Địa chỉ: Cửa hàng trực tuyến chính thức</p>
                    <p class="text-muted mb-0 fs-3">Email liên hệ: support@store.com</p>
                </div>
                <div class="col-md-5 text-md-end">
                    <h1 class="text-primary fw-bolder mb-3 fs-8">{{ __('admin.orders.order_details') }}</h1>
                    <p class="mb-1 fs-3"><strong class="text-dark">{{ __('admin.orders.fields.order_number') }}:</strong> {{ $order->order_number }}</p>
                    <p class="mb-1 fs-3"><strong class="text-dark">{{ __('admin.orders.fields.order_date') }}:</strong> {{ $order->created_at->format('d-m-Y H:i') }}</p>
                    <p class="mb-0 fs-3"><strong class="text-dark">{{ __('admin.orders.payment_method') }}:</strong> 
                        @if($order->payment_method === 'cod')
                            {{ __('admin.orders.cod') }}
                        @elseif($order->payment_method === 'bank_transfer')
                            {{ __('admin.invoices.bank_transfer') }}
                        @else
                            {{ __('admin.orders.online_payment') }}
                        @endif
                    </p>
                </div>
            </div>

            <hr class="my-4">

            <!-- Customer & Stamp Section -->
            <div class="row mb-5 gap-4 gap-md-0">
                <div class="col-md-6">
                    <h6 class="fw-semibold text-muted mb-3 fs-2 uppercase text-uppercase">{{ __('admin.orders.customer_info') }}</h6>
                    <h5 class="fw-bold text-primary mb-2 fs-4">{{ $order->customer_name }}</h5>
                    <p class="text-muted mb-1 fs-3"><strong>{{ __('admin.orders.fields.phone') }}:</strong> {{ $order->customer_phone }}</p>
                    <p class="text-muted mb-1 fs-3"><strong>Email:</strong> {{ $order->customer_email }}</p>
                    <p class="text-muted mb-3 fs-3"><strong>{{ __('admin.orders.fields.shipping_address') }}:</strong> {{ $order->shipping_address }}</p>

                    <h6 class="fw-semibold text-muted mb-3 fs-2 uppercase text-uppercase">{{ __('admin.orders.carrier_info') }}</h6>
                    @if($order->tracking_number)
                        <p class="text-dark mb-1 fs-3"><strong>{{ __('admin.orders.carrier') }}:</strong> 
                            <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">{{ strtoupper($order->shipping_carrier) }}</span>
                        </p>
                        <p class="text-dark mb-1 fs-3"><strong>{{ __('admin.orders.tracking_number') }}:</strong> 
                            <span class="text-primary font-monospace fw-bold fs-3">{{ $order->tracking_number }}</span>
                        </p>
                        <p class="text-dark mb-0 fs-3"><strong>{{ __('admin.orders.fields.actual_shipping_fee') }}:</strong> 
                            <span class="text-danger fw-bold fs-3">{{ number_format($order->shipping_fee, 0, ',', '.') }} ₫</span>
                        </p>
                    @else
                        <p class="text-muted mb-2 fs-3">{{ __('admin.orders.not_shipped') }}</p>
                        @if($order->status !== 'cancelled' && $isGhtkEnabled)
                            <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 mt-1 fw-semibold py-2 px-3" data-bs-toggle="modal" data-bs-target="#pushShippingModal">
                                <i class="ti ti-truck fs-4"></i> {{ __('admin.orders.create_ghtk') }}
                            </button>
                        @endif
                    @endif
                </div>
                <div class="col-md-6 text-md-end">
                    <h6 class="fw-semibold text-muted mb-3 fs-2 uppercase text-uppercase">{{ __('admin.orders.fields.status') }}</h6>
                    <div class="mt-2">
                        @if($order->status === 'completed')
                            <div class="order-stamp stamp-completed">{{ __('admin.orders.statuses.completed') }}</div>
                        @elseif($order->status === 'processing')
                            <div class="order-stamp stamp-processing">{{ __('admin.orders.statuses.processing') }}</div>
                        @elseif($order->status === 'cancelled')
                            <div class="order-stamp stamp-cancelled">{{ __('admin.orders.statuses.cancelled') }}</div>
                        @else
                            <div class="order-stamp stamp-pending">{{ __('admin.orders.statuses.pending') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-5 border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-nowrap">
                            <th class="ps-3">{{ __('admin.orders.item') }}</th>
                            <th>{{ __('catalog.fields.sku') }}</th>
                            <th class="text-end">{{ __('admin.orders.price') }}</th>
                            <th class="text-center">{{ __('admin.orders.quantity') }}</th>
                            <th class="text-end pe-3">{{ __('admin.orders.subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td class="ps-3 py-3">
                                    <h6 class="fw-semibold mb-1 fs-3">{{ $item->product_name }}</h6>
                                    @if($item->variant_name)
                                        <span class="badge bg-secondary-subtle text-secondary fw-semibold fs-1">{{ $item->variant_name }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted fs-3">{{ $item->sku ?: '-' }}</span>
                                </td>
                                <td class="text-end fw-semibold">{{ number_format($item->price, 0, ',', '.') }} ₫</td>
                                <td class="text-center fw-semibold">{{ $item->quantity }}</td>
                                <td class="text-end fw-bold text-primary pe-3">{{ number_format($item->total, 0, ',', '.') }} ₫</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Grand Total Block -->
            <div class="row justify-content-end mb-4">
                <div class="col-md-6 col-lg-5 text-md-end">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted fs-3">{{ __('admin.orders.subtotal') }}:</span>
                        <span class="fw-semibold fs-3">{{ number_format($order->subtotal, 0, ',', '.') }} ₫</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted fs-3">{{ __('admin.orders.discount') }}:</span>
                        <span class="fw-semibold text-danger fs-3">-{{ number_format($order->discount, 0, ',', '.') }} ₫</span>
                    </div>
                    <div class="d-flex justify-content-between py-3">
                        <span class="fw-bold text-dark fs-4">{{ __('admin.orders.grand_total') }}:</span>
                        <span class="fw-bolder text-primary fs-6">{{ number_format($order->grand_total, 0, ',', '.') }} ₫</span>
                    </div>
                </div>
            </div>

            <!-- Note section -->
            @if($order->notes)
                <div class="p-3 bg-light rounded mb-4">
                    <p class="mb-0 fs-3 text-muted"><strong>{{ __('admin.orders.customer_notes') }}:</strong> {{ $order->notes }}</p>
                </div>
            @endif

            <hr class="my-4">

            <!-- Footer Details -->
            <div class="row align-items-center">
                <div class="col-md-8">
                    <p class="text-muted mb-0 fs-2">
                        {{ __('admin.orders.current_payment_status') }} <strong>
                            @if($order->payment_status === 'paid')
                                {{ __('admin.orders.payment_statuses.paid') }}
                            @elseif($order->payment_status === 'pending')
                                {{ __('admin.orders.payment_statuses.pending') }}
                            @else
                                {{ __('admin.orders.payment_statuses.failed') }}
                            @endif
                        </strong>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="text-muted mb-0 fs-2">{{ __('admin.orders.thank_you') }}</p>
                </div>
            </div>
        </div>
    </div>

    @if($isGhtkEnabled && !$order->tracking_number)
    <!-- Push Shipping Modal -->
    <div class="modal fade" id="pushShippingModal" tabindex="-1" aria-labelledby="pushShippingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold text-dark" id="pushShippingModalLabel">{{ __('admin.orders.create_ghtk') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="pushShippingForm" action="{{ route('admin.orders.push-shipping', $order) }}" method="POST">
                    @csrf
                    <input type="hidden" name="carrier" value="ghtk">
                    <div class="modal-body text-start">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">{{ __('admin.orders.fields.shipping_address') }}</label>
                            <input type="text" class="form-control bg-light text-dark" value="{{ $order->shipping_address }}" readonly>
                        </div>
                        
                        <!-- Address details for shipping parsing -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold text-dark" for="shipping_province">{{ __('admin.orders.fields.province') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-dark" id="shipping_province" name="province" value="Hồ Chí Minh" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold text-dark" for="shipping_district">{{ __('admin.orders.fields.district') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-dark" id="shipping_district" name="district" value="Quận 1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold text-dark" for="shipping_ward">{{ __('admin.orders.fields.ward') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-dark" id="shipping_ward" name="ward" value="Phường Bến Nghé" required>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-semibold text-dark" for="shipping_weight">{{ __('admin.orders.fields.weight') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control text-dark" id="shipping_weight" name="weight" value="500" min="1" required>
                            <div class="form-text">{{ __('admin.orders.weight_help') }}</div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('catalog.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('admin.orders.confirm_push') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Push Shipping Form AJAX handling
            const pushShippingForm = document.getElementById('pushShippingForm');
            if (pushShippingForm) {
                pushShippingForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: "{{ __('admin.orders.ghtk_connecting') }}",
                        text: "{{ __('admin.orders.please_wait') }}",
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData(pushShippingForm);

                    fetch(pushShippingForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => { throw err; });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: "{{ __('admin.orders.ghtk_success') }}",
                                text: data.message || 'Đơn hàng đã được đẩy sang GHTK.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: "{{ __('admin.orders.ghtk_failed') }}",
                                text: data.message || 'Có lỗi xảy ra.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        let errMsg = 'Không thể kết nối đến máy chủ.';
                        if (error.errors) {
                            errMsg = Object.values(error.errors).flat().join('\n');
                        } else if (error.message) {
                            errMsg = error.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: "{{ __('admin.settings.error') }}",
                            text: errMsg
                        });
                    });
                });
            }
        });
    </script>
@endpush
