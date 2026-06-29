@extends('admin.layouts.app')

@section('title', 'Cửa hàng Addons - Kích hoạt tính năng nâng cao')

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">Cửa hàng tính năng (Addons Store)</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Addons</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="card mb-4">
        <div class="card-body p-0">
            <ul class="nav nav-tabs border-0 p-2" role="tablist">
                <li class="nav-item">
                    <a class="nav-link d-flex {{ $activeTab === 'addons' ? 'active' : '' }} fw-semibold py-3 fs-3" data-bs-toggle="tab" href="#addons2" role="tab">
                        <span><i class="ti ti-apps fs-5"></i></span>
                        <span class="ms-2">Tính năng nâng cao</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex {{ $activeTab === 'invoices' ? 'active' : '' }} fw-semibold py-3 fs-3" data-bs-toggle="tab" href="#invoices2" role="tab">
                        <span><i class="ti ti-receipt fs-5"></i></span>
                        <span class="ms-2">Lịch sử hóa đơn</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Addons Tab Pane -->
        <div class="tab-pane {{ $activeTab === 'addons' ? 'active show' : '' }}" id="addons2" role="tabpanel">
            <div class="row">
                @forelse($addons as $addon)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm border border-light-subtle rounded-4 overflow-hidden position-relative transition-all" style="hover: transform: translateY(-4px)">
                            @if($addon->is_purchased)
                                <span class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bl-4 small fw-semibold d-flex align-items-center gap-1" style="border-bottom-left-radius: 12px;">
                                    <i class="ti ti-circle-check"></i> Đã kích hoạt
                                </span>
                            @else
                                <span class="position-absolute top-0 end-0 bg-secondary-subtle text-muted px-3 py-1 rounded-bl-4 small fw-semibold" style="border-bottom-left-radius: 12px;">
                                    Chưa sở hữu
                                </span>
                            @endif

                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <!-- Addon Icon representation based on code -->
                                    <div class="mb-3">
                                        @if($addon->code === 'shipping_api')
                                            <div class="bg-primary-subtle text-primary p-3 rounded-3 d-inline-block">
                                                <i class="ti ti-truck-delivery fs-7"></i>
                                            </div>
                                        @elseif($addon->code === 'vnpay')
                                            <div class="bg-info-subtle text-info p-3 rounded-3 d-inline-block">
                                                <i class="ti ti-credit-card fs-7"></i>
                                            </div>
                                        @elseif($addon->code === 'sepay')
                                            <div class="bg-success-subtle text-success p-3 rounded-3 d-inline-block">
                                                <i class="ti ti-qrcode fs-7"></i>
                                            </div>
                                        @elseif($addon->code === 'stripe')
                                            <div class="bg-indigo-subtle text-indigo p-3 rounded-3 d-inline-block">
                                                <i class="ti ti-brand-stripe fs-7"></i>
                                            </div>
                                        @else
                                            <div class="bg-light p-3 rounded-3 d-inline-block">
                                                <i class="ti ti-apps fs-7"></i>
                                            </div>
                                        @endif
                                    </div>

                                    <h5 class="fw-bold text-dark mb-2">{{ $addon->name }}</h5>
                                    <p class="text-muted small mb-4" style="min-height: 48px;">{{ $addon->description }}</p>
                                </div>

                                <div>
                                    <div class="d-flex align-items-center justify-content-between border-top pt-3">
                                        <div>
                                            <span class="text-muted small block">Giá bán</span>
                                            <h5 class="fw-bold text-primary mb-0">{{ number_format($addon->price, 0, ',', '.') }} VND</h5>
                                        </div>
                                        @if($addon->is_purchased)
                                            <button class="btn btn-outline-success btn-sm rounded-pill px-3" disabled>
                                                <i class="ti ti-check"></i> Sẵn sàng sử dụng
                                            </button>
                                        @else
                                            <button class="btn btn-primary btn-sm rounded-pill px-4" onclick="buyAddon('{{ $addon->id }}', '{{ $addon->name }}', '{{ $addon->price }}')">
                                                Mua ngay
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <i class="ti ti-info-circle fs-8 text-muted mb-2"></i>
                        <p class="text-muted">Không có addon nào hiện tại.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Invoices Tab Pane -->
        <div class="tab-pane {{ $activeTab === 'invoices' ? 'active show' : '' }}" id="invoices2" role="tabpanel">
            <div class="card shadow-sm border border-light-subtle rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom">
                        <h5 class="fw-semibold text-dark mb-0">{{ __('admin.invoices.title') }}</h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-nowrap text-muted">
                                    <th class="ps-4 fw-semibold small">{{ __('admin.invoices.invoice_number') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.invoices.package') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.invoices.amount') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.invoices.billing_date') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.invoices.due_date') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.invoices.status') }}</th>
                                    <th class="pe-4 text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $invoice)
                                    <tr class="text-nowrap">
                                        <td class="ps-4">
                                            <span class="fw-bold text-primary">{{ $invoice->invoice_number }}</span>
                                        </td>
                                        <td class="text-wrap">
                                            <h6 class="fw-semibold mb-0 fs-3">{{ $invoice->addon ? $invoice->addon->name : ($invoice->package_name ?? '-') }}</h6>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ number_format($invoice->amount, 0, ',', '.') }} ₫</span>
                                        </td>
                                        <td>
                                            <span class="fs-3 text-muted">{{ $invoice->billing_date->format('d-m-Y') }}</span>
                                        </td>
                                        <td>
                                            <span class="fs-3 text-muted">{{ $invoice->due_date->format('d-m-Y') }}</span>
                                        </td>
                                        <td>
                                            @if($invoice->status === 'paid')
                                                <span class="badge bg-success-subtle text-success fw-semibold fs-2">
                                                    {{ __('admin.invoices.paid') }}
                                                </span>
                                            @elseif($invoice->status === 'pending')
                                                <span class="badge bg-warning-subtle text-warning fw-semibold fs-2">
                                                    {{ __('admin.invoices.pending') }}
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger fw-semibold fs-2">
                                                    {{ __('admin.invoices.unpaid') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary fw-semibold">
                                                <i class="ti ti-eye me-1 fs-4"></i>{{ __('admin.invoices.view_invoice') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <iconify-icon icon="solar:bill-list-broken" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                                            <p class="text-muted mb-0 fs-3">{{ __('admin.invoices.not_found') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    @if($invoices->hasPages())
                        <div class="p-4 border-top">
                            {{ $invoices->appends(['tab' => 'invoices'])->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Sepay QR Payment Modal -->
    <div class="modal fade" id="paymentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="paymentModalLabel">Thanh toán qua chuyển khoản Sepay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closePaymentModalBtn"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="paymentLoading" class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted">Đang tạo cổng thanh toán...</p>
                    </div>

                    <div id="paymentContent" style="display: none;">
                        <div class="row g-4 align-items-stretch">
                            <!-- Left Column: QR Code -->
                            <div class="col-md-5 text-center d-flex flex-column align-items-center justify-content-center py-2 payment-qr-col">
                                <h6 class="text-dark fw-bold mb-3">Mã QR Thanh Toán</h6>
                                <!-- QR Code Container -->
                                <div class="p-3 bg-light rounded-4 border border-dashed border-2 d-inline-block">
                                    <img id="paymentQrImg" src="" alt="Sepay QR Code" style="max-height: 240px; max-width: 100%; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-2 text-primary small fw-semibold mt-3">
                                    <div class="spinner-grow spinner-grow-sm text-primary" role="status"></div>
                                    <span>Đang chờ chuyển khoản...</span>
                                </div>
                            </div>

                            <!-- Right Column: Information -->
                            <div class="col-md-7 ps-md-4 d-flex flex-column justify-content-between">
                                <div>
                                    <h5 class="text-dark fw-bold mb-2" id="addonPurchaseName">Tên Addon</h5>
                                    <p class="text-muted small mb-3">Vui lòng quét mã QR hoặc chuyển khoản ngân hàng chính xác số tiền và nội dung dưới đây để tự động kích hoạt tính năng.</p>

                                    <!-- Manual Bank Details -->
                                    <div class="text-start bg-light p-3 rounded-4 mb-0 small border border-light-subtle">
                                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light">
                                            <span class="text-muted">Ngân hàng:</span>
                                            <span class="fw-bold text-dark">{{ $bankName }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light">
                                            <span class="text-muted">Số tài khoản:</span>
                                            <span class="fw-bold text-dark font-monospace" id="bankAccountNum">{{ $accountNum }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light">
                                            <span class="text-muted">Chủ tài khoản:</span>
                                            <span class="fw-bold text-dark text-end">{{ $accountHolder }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light">
                                            <span class="text-muted">Số tiền:</span>
                                            <span class="fw-bold text-primary fs-4" id="paymentAmount">0 VND</span>
                                        </div>
                                        <div class="p-3 bg-warning-subtle text-warning-emphasis rounded-3 border border-warning-subtle mt-3">
                                            <div class="small fw-semibold mb-1">Nội dung chuyển khoản (Bắt buộc):</div>
                                            <div class="fw-bold font-monospace text-danger fs-4 text-center mt-1 p-2 bg-white rounded border" id="transferSyntax" style="letter-spacing: 0.5px;">ADDONPAID...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Success state -->
                    <div id="paymentSuccess" class="text-center py-5" style="display: none;">
                        <div class="text-success mb-4">
                            <i class="ti ti-circle-check" style="font-size: 5rem;"></i>
                        </div>
                        <h3 class="fw-bold text-success mb-2">Kích hoạt thành công!</h3>
                        <p class="text-muted px-3 mb-4">Cảm ơn bạn! Tính năng nâng cao đã được thanh toán và tự động kích hoạt trên website của bạn.</p>
                        <button type="button" class="btn btn-primary rounded-pill px-5 py-2 fw-semibold" onclick="window.location.reload()">Bắt đầu sử dụng</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    @media (min-width: 768px) {
        .payment-qr-col {
            border-right: 1px solid var(--bs-border-color-translucent);
        }
    }
    @media (max-width: 767.98px) {
        .payment-qr-col {
            border-bottom: 1px solid var(--bs-border-color-translucent);
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    let pollInterval = null;
    let paymentModal = null;

    document.addEventListener('DOMContentLoaded', function () {
        paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        
        document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function () {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        });
    });

    function buyAddon(addonId, name, price) {
        // Reset state
        document.getElementById('paymentLoading').style.display = 'block';
        document.getElementById('paymentContent').style.display = 'none';
        document.getElementById('paymentSuccess').style.display = 'none';
        document.getElementById('closePaymentModalBtn').style.display = 'block';
        
        paymentModal.show();

        fetch(`/vi/admin/addons/${addonId}/checkout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('addonPurchaseName').innerText = name;
                document.getElementById('paymentQrImg').src = data.qr_code_url;
                document.getElementById('paymentAmount').innerText = new Intl.NumberFormat('vi-VN').format(price) + ' VND';
                document.getElementById('transferSyntax').innerText = data.transfer_syntax;

                document.getElementById('paymentLoading').style.display = 'none';
                document.getElementById('paymentContent').style.display = 'block';

                // Start polling invoice status
                startPolling(data.invoice.id);
            } else {
                alert(data.message || 'Lỗi khởi tạo hóa đơn.');
                paymentModal.hide();
            }
        })
        .catch(err => {
            console.error(err);
            alert('Có lỗi xảy ra khi tạo giao dịch.');
            paymentModal.hide();
        });
    }

    function startPolling(invoiceId) {
        if (pollInterval) clearInterval(pollInterval);

        pollInterval = setInterval(function () {
            fetch(`/vi/admin/addons/invoices/${invoiceId}/status`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.payment_status === 'paid') {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    
                    // Show success
                    document.getElementById('paymentContent').style.display = 'none';
                    document.getElementById('paymentSuccess').style.display = 'block';
                    document.getElementById('closePaymentModalBtn').style.display = 'none';
                }
            })
            .catch(err => console.error('Error checking payment status', err));
        }, 3000);
    }
</script>
@endpush
