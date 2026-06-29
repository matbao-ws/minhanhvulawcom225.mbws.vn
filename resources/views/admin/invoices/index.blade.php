@extends('admin.layouts.app')

@section('title', __('admin.invoices.title'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.invoices.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.invoices.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoices List Card -->
    <div class="card">
        <div class="card-body border-bottom p-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.invoices.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('admin.invoices.search_placeholder') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.invoices.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="paid" @selected(request('status') === 'paid')>{{ __('admin.invoices.paid') }}</option>
                        <option value="pending" @selected(request('status') === 'pending')>{{ __('admin.invoices.pending') }}</option>
                        <option value="unpaid" @selected(request('status') === 'unpaid')>{{ __('admin.invoices.unpaid') }}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit" title="{{ __('admin.invoices.search') }}">
                        <i class="ti ti-search fs-5"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.invoices.invoice_number') }}</th>
                            <th>{{ __('admin.invoices.package') }}</th>
                            <th>{{ __('admin.invoices.amount') }}</th>
                            <th>{{ __('admin.invoices.billing_date') }}</th>
                            <th>{{ __('admin.invoices.due_date') }}</th>
                            <th>{{ __('admin.invoices.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <span class="fw-bold text-primary">{{ $invoice->invoice_number }}</span>
                                </td>
                                <td class="text-wrap">
                                    <h6 class="fw-semibold mb-0 fs-3">{{ $invoice->package_name }}</h6>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ number_format($invoice->amount, 0, ',', '.') }} ₫</span>
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
                                <td colspan="7" class="text-center py-5">
                                    <iconify-icon icon="solar:bill-list-broken" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                                    <p class="text-muted mb-0 fs-3">{{ __('admin.invoices.not_found') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($invoices->hasPages())
                <div class="card-footer bg-transparent border-top py-3">
                    {{ $invoices->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
