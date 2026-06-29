@extends('admin.layouts.app')

@section('title', __('admin.invoices.view_invoice') . ' ' . $invoice->invoice_number)

@push('styles')
<style>
    @media print {
        /* Hide everything except the invoice sheet */
        header, .sidebar-link, .preloader, #main-wrapper > aside, .page-wrapper > header,
        .print-btn-header, footer, .dark-transparent {
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
        
        .invoice-print-area {
            border: none !important;
            padding: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
        }
    }
    
    .invoice-stamp {
        border: 3px solid;
        padding: 8px 16px;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 1.5rem;
        display: inline-block;
        transform: rotate(-10deg);
        border-radius: 4px;
        opacity: 0.85;
    }
    .stamp-paid {
        border-color: #2ec37e;
        color: #2ec37e;
    }
    .stamp-pending {
        border-color: #ffaa00;
        color: #ffaa00;
    }
    .stamp-unpaid {
        border-color: #ef4444;
        color: #ef4444;
    }
</style>
@endpush

@section('content')
    <!-- Print Action Header (hidden when printing) -->
    <div class="d-flex align-items-center justify-content-between mb-4 print-btn-header">
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="ti ti-arrow-left fs-4"></i>{{ __('admin.back') }}
        </a>
        <div class="d-flex gap-2">
            <form action="{{ route('admin.invoices.send-email', $invoice) }}" method="POST" style="display:inline;" id="sendInvoiceEmailForm">
                @csrf
                <button type="submit" class="btn btn-info d-flex align-items-center gap-2">
                    <i class="ti ti-mail fs-4"></i>{{ __('admin.invoices.send_email') }}
                </button>
            </form>
            <button onclick="window.print();" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="ti ti-printer fs-4"></i>{{ __('admin.invoices.print') }}
            </button>
        </div>
    </div>

    <!-- Invoice Sheet -->
    <div class="card invoice-print-area border border-opacity-10 border-primary" style="box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05);">
        <div class="card-body p-4 p-md-5">
            <!-- Header Section -->
            <div class="row align-items-start mb-5 gap-4 gap-md-0">
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="{{ asset('admin-assets/images/logos/favicon.png') }}" alt="logo" width="35" height="35">
                        <span class="fs-6 fw-bold text-primary">LARAVEL ECOMMERCE AGENCY</span>
                    </div>
                    <p class="text-muted mb-0 fs-3">Cần Thơ, Việt Nam</p>
                    <p class="text-muted mb-0 fs-3">Email: billing@agency.com</p>
                    <p class="text-muted mb-0 fs-3">Website: agency.com</p>
                </div>
                <div class="col-md-5 text-md-end">
                    <h1 class="text-primary fw-bolder mb-3 fs-8">{{ __('admin.invoices.invoice') }}</h1>
                    <p class="mb-1 fs-3"><strong class="text-dark">{{ __('admin.invoices.invoice_number') }}:</strong> {{ $invoice->invoice_number }}</p>
                    <p class="mb-1 fs-3"><strong class="text-dark">{{ __('admin.invoices.billing_date') }}:</strong> {{ $invoice->billing_date->format('d-m-Y') }}</p>
                    <p class="mb-0 fs-3"><strong class="text-dark">{{ __('admin.invoices.due_date') }}:</strong> {{ $invoice->due_date->format('d-m-Y') }}</p>
                </div>
            </div>

            <hr class="my-4">

            <!-- Bill To Section -->
            <div class="row mb-5 gap-4 gap-md-0">
                <div class="col-md-6">
                    <h6 class="fw-semibold text-muted mb-3 fs-2 uppercase">{{ __('admin.invoices.billed_to') }}</h6>
                    <h5 class="fw-bold text-primary mb-2 fs-4">{{ auth()->user()->name }}</h5>
                    <p class="text-muted mb-1 fs-3">Email: {{ auth()->user()->email }}</p>
                    <p class="text-muted mb-1 fs-3">Cửa hàng: {{ config('app.name', 'Laravel Ecommerce Core') }}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6 class="fw-semibold text-muted mb-3 fs-2 uppercase">{{ __('admin.invoices.payment_status') }}</h6>
                    <div class="mt-2">
                        @if($invoice->status === 'paid')
                            <div class="invoice-stamp stamp-paid">{{ __('admin.invoices.paid') }}</div>
                        @elseif($invoice->status === 'pending')
                            <div class="invoice-stamp stamp-pending">{{ __('admin.invoices.pending') }}</div>
                        @else
                            <div class="invoice-stamp stamp-unpaid">{{ __('admin.invoices.unpaid') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-5 border rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">{{ __('admin.invoices.description') }}</th>
                            <th class="text-end">{{ __('admin.invoices.unit_price') }}</th>
                            <th class="text-end pe-3">{{ __('admin.invoices.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3 py-4">
                                <h6 class="fw-semibold mb-1 fs-4">{{ $invoice->package_name }}</h6>
                                <span class="text-muted fs-3">{{ __('admin.invoices.service_fee') }} ({{ __('admin.invoices.cycle') }}: {{ $invoice->billing_date->format('d/m/Y') }} - {{ $invoice->due_date->format('d/m/Y') }})</span>
                            </td>
                            <td class="text-end py-4 fw-semibold">{{ number_format($invoice->amount, 0, ',', '.') }} ₫</td>
                            <td class="text-end py-4 fw-bold text-primary pe-3">{{ number_format($invoice->amount, 0, ',', '.') }} ₫</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Grand Total Block -->
            <div class="row justify-content-end mb-4">
                <div class="col-md-5 col-lg-4 text-md-end">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted fs-3">{{ __('admin.invoices.subtotal') }}</span>
                        <span class="fw-semibold fs-3">{{ number_format($invoice->amount, 0, ',', '.') }} ₫</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted fs-3">{{ __('admin.invoices.vat') }}</span>
                        <span class="fw-semibold fs-3">0 ₫</span>
                    </div>
                    <div class="d-flex justify-content-between py-3">
                        <span class="fw-bold text-dark fs-5">{{ __('admin.invoices.grand_total') }}</span>
                        <span class="fw-bolder text-primary fs-6">{{ number_format($invoice->amount, 0, ',', '.') }} ₫</span>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <!-- Footer Details -->
            <div class="row align-items-center">
                <div class="col-md-8">
                    @if($invoice->status === 'paid')
                        <p class="text-muted mb-0 fs-2">
                            * Phương thức thanh toán: <strong>
                                {{ $invoice->payment_method === 'bank_transfer' ? __('admin.invoices.bank_transfer') : Str::headline($invoice->payment_method) }}
                            </strong>
                        </p>
                    @else
                        <p class="text-muted mb-0 fs-2">
                            {!! __('admin.invoices.payment_instruction', ['date' => $invoice->due_date->format('d-m-Y')]) !!}
                        </p>
                    @endif
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="text-muted mb-0 fs-2">{{ __('admin.invoices.thank_you') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('sendInvoiceEmailForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: '{{ __('admin.invoices.sending_email') }}',
                        text: '{{ __('admin.invoices.please_wait') }}',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('{{ __('admin.invoices.sending_failed') }}');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '{{ __('admin.success') }}',
                                text: data.message,
                                timer: 2500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __('admin.error') }}',
                                text: data.message || '{{ __('admin.invoices.sending_failed') }}'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __('admin.connection_error') }}',
                            text: error.message || '{{ __('admin.failed_to_connect') }}'
                        });
                    });
                });
            }
        });
    </script>
@endpush
