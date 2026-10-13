@extends('admin.layouts.app')

@section('title', __('admin.customers.title'))

@section('content')
    <x-admin.page-header :title="__('admin.customers.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.customers.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <span data-tour="customers.count" class="badge bg-primary-subtle text-primary px-3 py-2 fs-2">
            {{ trans_choice('admin.customers.total', $customers->total(), ['count' => number_format($customers->total())]) }}
        </span>
        {{-- Carries the current filters so the file matches what is on screen. --}}
        <a data-tour="customers.export" href="{{ route('admin.customers.export', request()->only('q')) }}" class="btn btn-outline-primary">
            <i class="ti ti-file-download me-1"></i>{{ __('admin.customers.export') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body border-bottom p-3 p-md-4">
            <button class="btn btn-outline-primary d-md-none w-100 d-flex align-items-center justify-content-between"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#customer-filters"
                    aria-expanded="{{ request()->filled('q') ? 'true' : 'false' }}"
                    aria-controls="customer-filters">
                <span><i class="ti ti-adjustments-horizontal me-2"></i>{{ __('admin.customers.filters') }}</span>
                <i class="ti ti-chevron-down"></i>
            </button>

            <div class="collapse d-md-block {{ request()->filled('q') ? 'show' : '' }}" id="customer-filters">
                <form method="GET" class="row g-2 align-items-end mt-1 mt-md-0">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold text-muted mb-1" for="customer-search">{{ __('admin.customers.search') }}</label>
                        <input data-tour="customers.search" type="search" id="customer-search" name="q" class="form-control" value="{{ request('q') }}"
                               placeholder="{{ __('admin.customers.search_placeholder') }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <x-admin.action-button type="submit" icon="search" class="w-100" aria-label="{{ __('admin.customers.search') }}"><span class="d-md-none">{{ __('admin.customers.search') }}</span></x-admin.action-button>
                    </div>
                    @if(request()->filled('q'))
                        <div class="col-12 col-md-2">
                            <a href="{{ route('admin.customers.index') }}" class="btn btn-light w-100">{{ __('admin.customers.clear') }}</a>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div data-tour="customers.table" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.customers.customer') }}</th>
                            <th data-tour="customers.account">{{ __('admin.customers.account') }}</th>
                            <th class="text-end">{{ __('admin.customers.orders') }}</th>
                            <th class="text-end">{{ __('admin.customers.completed_orders') }}</th>
                            <th class="text-end">{{ __('admin.customers.total_spent') }}</th>
                            <th>{{ __('admin.customers.last_order') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    @if($customers->isNotEmpty())
                        <tbody>
                            @foreach($customers as $customer)
                                <tr class="text-nowrap">
                                    <td class="ps-4 text-wrap">
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold text-dark">{{ $customer->customer_name }}</span>
                                            <span class="text-muted small">{{ $customer->customer_email }}</span>
                                            @if($customer->customer_phone)
                                                <span class="text-muted small">{{ $customer->customer_phone }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($customer->registered_user_id)
                                            <span class="badge bg-success-subtle text-success fw-semibold fs-2">{{ __('admin.customers.registered') }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary fw-semibold fs-2">{{ __('admin.customers.guest') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($customer->total_orders) }}</td>
                                    <td class="text-end">{{ number_format($customer->completed_orders) }}</td>
                                    <td class="text-end">
                                        <span class="fw-semibold text-primary">{{ number_format($customer->total_spent, 0, ',', '.') }} ₫</span>
                                    </td>
                                    <td>
                                        <span class="fs-3 text-muted">{{ \Carbon\Carbon::parse($customer->last_order_at)->format('d-m-Y H:i') }}</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a data-tour="customers.details" href="{{ route('admin.customers.show', ['email' => $customer->customer_email]) }}"
                                           class="btn btn-sm btn-outline-primary fw-semibold">
                                            <i class="ti ti-eye me-1 fs-4"></i>{{ __('admin.customers.details') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>

            @if($customers->isEmpty())
                @include('admin.shared.empty-state', [
                    'emptyId' => 'customers-empty-state',
                    'emptyImage' => asset('admin-assets/images/icons/empty-khachhang.png'),
                    'emptyMessage' => __('admin.customers.empty'),
                ])
            @endif

            @if($customers->total() > 0)
                <div class="card-footer bg-transparent border-top py-3">
                    @include('admin.shared.pagination', ['paginator' => $customers])
                </div>
            @endif
        </div>
    </div>
@endsection
