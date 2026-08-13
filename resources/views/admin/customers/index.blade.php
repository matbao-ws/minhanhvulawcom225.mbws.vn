@extends('admin.layouts.app')

@section('title', __('admin.customers.title'))

@push('styles')
<style>
    .body-wrapper {
        margin-top: 82px !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        border-radius: 0 !important;
    }
    .body-wrapper > .container-fluid {
        padding-top: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .customer-filter-bar {
        background: #ffffff;
        border-bottom: 1px solid #ebf1f6;
        padding: 20px 24px;
        margin-bottom: 24px;
    }
    .customer-table-container {
        padding: 0 24px 24px 24px;
    }
</style>
@endpush

@section('content')
<div class="customer-filter-bar">
    <form method="GET" data-responsive-filters class="row g-2 align-items-center mb-0">
        <div class="col-md-6">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('admin.customers.search_placeholder') }}">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit"><iconify-icon icon="solar:magnifer-line-duotone" class="me-1"></iconify-icon>{{ __('admin.customers.search') }}</button>
        </div>
        @if(request()->filled('q'))
            <div class="col-auto"><a href="{{ route('admin.customers.index') }}" class="btn btn-light">{{ __('admin.customers.clear') }}</a></div>
        @endif
        <div class="col-auto ms-auto">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 fs-2">{{ trans_choice('admin.customers.total', $customers->total(), ['count' => number_format($customers->total())]) }}</span>
        </div>
    </form>
</div>

<div class="customer-table-container">
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-4">{{ __('admin.customers.customer') }}</th>
                        <th>{{ __('admin.customers.account') }}</th>
                        <th class="text-end">{{ __('admin.customers.orders') }}</th>
                        <th class="text-end">{{ __('admin.customers.completed_orders') }}</th>
                        <th class="text-end">{{ __('admin.customers.total_spent') }}</th>
                        <th>{{ __('admin.customers.last_order') }}</th>
                        <th class="pe-4"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold text-dark">{{ $customer->customer_name }}</div>
                                <div class="small text-muted">{{ $customer->customer_email }} · {{ $customer->customer_phone }}</div>
                            </td>
                            <td>
                                @if($customer->registered_user_id)
                                    <span class="badge bg-success-subtle text-success">{{ __('admin.customers.registered') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">{{ __('admin.customers.guest') }}</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ number_format($customer->total_orders) }}</td>
                            <td class="text-end">{{ number_format($customer->completed_orders) }}</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($customer->total_spent, 0, ',', '.') }} ₫</td>
                            <td class="text-nowrap">{{ \Carbon\Carbon::parse($customer->last_order_at)->format('d/m/Y H:i') }}</td>
                            <td class="text-end pe-4"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.customers.show', ['email' => $customer->customer_email]) }}">{{ __('admin.customers.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">{{ __('admin.customers.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($customers->hasPages())<div class="card-body border-top">{{ $customers->links() }}</div>@endif
    </div>
</div>
@endsection
