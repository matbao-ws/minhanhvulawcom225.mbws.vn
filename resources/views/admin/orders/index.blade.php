@extends('admin.layouts.app')

@section('title', __('admin.menu.orders'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.menu.orders') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.menu.orders') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders List Card -->
    <div class="card">
        <div class="card-body border-bottom p-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('admin.orders.search_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.orders.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="pending" @selected(request('status') === 'pending')>{{ __('admin.orders.statuses.pending') }}</option>
                        <option value="processing" @selected(request('status') === 'processing')>{{ __('admin.orders.statuses.processing') }}</option>
                        <option value="completed" @selected(request('status') === 'completed')>{{ __('admin.orders.statuses.completed') }}</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('admin.orders.statuses.cancelled') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.orders.fields.payment_status') }}</label>
                    <select name="payment_status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="pending" @selected(request('payment_status') === 'pending')>{{ __('admin.orders.payment_statuses.pending') }}</option>
                        <option value="paid" @selected(request('payment_status') === 'paid')>{{ __('admin.orders.payment_statuses.paid') }}</option>
                        <option value="failed" @selected(request('payment_status') === 'failed')>{{ __('admin.orders.payment_statuses.failed') }}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit" title="{{ __('catalog.actions.search') }}">
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
                            <th class="ps-4">{{ __('admin.orders.fields.order_number') }}</th>
                            <th>{{ __('admin.orders.fields.customer') }}</th>
                            <th>{{ __('admin.orders.fields.order_date') }}</th>
                            <th>{{ __('admin.orders.fields.total') }}</th>
                            <th>{{ __('admin.orders.fields.payment') }}</th>
                            <th>{{ __('admin.orders.fields.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <span class="fw-bold text-primary">{{ $order->order_number }}</span>
                                </td>
                                <td class="text-wrap">
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark">{{ $order->customer_name }}</span>
                                        <span class="text-muted small text-dark">{{ $order->customer_phone }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="fs-3 text-muted text-dark">{{ $order->created_at->format('d-m-Y H:i') }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-primary">{{ number_format($order->grand_total, 0, ',', '.') }} ₫</span>
                                </td>
                                <td>
                                    @if($order->payment_status === 'paid')
                                        <span class="badge bg-success-subtle text-success fw-semibold fs-2">
                                            {{ __('admin.orders.payment_statuses.paid') }}
                                        </span>
                                    @elseif($order->payment_status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning fw-semibold fs-2">
                                            {{ __('admin.orders.payment_statuses.pending') }}
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger fw-semibold fs-2">
                                            {{ __('admin.orders.payment_statuses.failed') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($order->status === 'completed')
                                        <span class="badge bg-success text-white fw-semibold fs-2">
                                            {{ __('admin.orders.statuses.completed') }}
                                        </span>
                                    @elseif($order->status === 'processing')
                                        <span class="badge bg-info text-white fw-semibold fs-2">
                                            {{ __('admin.orders.statuses.processing') }}
                                        </span>
                                    @elseif($order->status === 'cancelled')
                                        <span class="badge bg-danger text-white fw-semibold fs-2">
                                            {{ __('admin.orders.statuses.cancelled') }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning text-white fw-semibold fs-2">
                                            {{ __('admin.orders.statuses.pending') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary fw-semibold">
                                        <i class="ti ti-eye me-1 fs-4"></i>{{ __('admin.orders.details') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <iconify-icon icon="solar:bill-list-broken" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                                    <p class="text-muted mb-0 fs-3">{{ __('admin.orders.not_found') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="card-footer bg-transparent border-top py-3">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
