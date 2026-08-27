@extends('admin.layouts.app')

@section('title', __('admin.customers.profile_title'))

@section('content')
@php
    $completionRate = (int) $metrics->total_orders > 0
        ? round(((int) $metrics->completed_orders / (int) $metrics->total_orders) * 100)
        : 0;
    $averageCompletedOrder = (int) $metrics->completed_orders > 0
        ? (float) $metrics->total_spent / (int) $metrics->completed_orders
        : 0;
    $firstOrderAt = $metrics->first_order_at ? \Carbon\Carbon::parse($metrics->first_order_at) : null;
    $lastOrderAt = $metrics->last_order_at ? \Carbon\Carbon::parse($metrics->last_order_at) : null;

    $statusClasses = [
        'pending' => 'bg-warning-subtle text-warning',
        'processing' => 'bg-info-subtle text-info',
        'completed' => 'bg-success-subtle text-success',
        'cancelled' => 'bg-danger-subtle text-danger',
    ];
    $paymentClasses = [
        'pending' => 'bg-warning-subtle text-warning',
        'paid' => 'bg-success-subtle text-success',
        'failed' => 'bg-danger-subtle text-danger',
        'partially_refunded' => 'bg-info-subtle text-info',
        'refunded' => 'bg-secondary-subtle text-secondary',
    ];

    $metricCards = [
        [
            'label' => __('admin.customers.metric_total_orders'),
            'value' => number_format($metrics->total_orders),
            'hint' => __('admin.customers.metric_total_orders_hint'),
            'icon' => 'ti ti-shopping-bag',
            'tone' => 'primary',
        ],
        [
            'label' => __('admin.customers.metric_completion'),
            'value' => number_format($completionRate).'%',
            'hint' => __('admin.customers.metric_completion_hint', [
                'done' => number_format($metrics->completed_orders),
                'total' => number_format($metrics->total_orders),
            ]),
            'icon' => 'ti ti-circle-check',
            'tone' => 'success',
        ],
        [
            'label' => __('admin.customers.metric_spent'),
            'value' => number_format($metrics->total_spent, 0, ',', '.').' ₫',
            'hint' => __('admin.customers.metric_spent_hint'),
            'icon' => 'ti ti-wallet',
            'tone' => 'warning',
        ],
        [
            'label' => __('admin.customers.metric_average'),
            'value' => number_format($averageCompletedOrder, 0, ',', '.').' ₫',
            'hint' => __('admin.customers.metric_average_hint'),
            'icon' => 'ti ti-chart-line',
            'tone' => 'info',
        ],
    ];

    $contactRows = [
        ['icon' => 'ti ti-mail', 'label' => __('admin.customers.export_columns.email'), 'value' => $customerEmail],
        ['icon' => 'ti ti-phone', 'label' => __('admin.customers.export_columns.phone'), 'value' => $customerPhone ?: __('admin.customers.no_data')],
        ['icon' => 'ti ti-calendar', 'label' => __('admin.customers.first_order'), 'value' => $firstOrderAt?->format('d/m/Y H:i') ?? __('admin.customers.no_data')],
        ['icon' => 'ti ti-clock', 'label' => __('admin.customers.last_order'), 'value' => $lastOrderAt?->format('d/m/Y H:i') ?? __('admin.customers.no_order')],
    ];
@endphp

    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ $customerName }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.customers.index') }}">{{ __('admin.customers.title') }}</a></li>
                                    <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.customers.profile_kicker') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <a href="{{ route('admin.customers.index') }}" class="btn btn-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('admin.customers.back_to_list') }}
        </a>
        <span class="badge {{ $registeredCustomer ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} px-3 py-2 fs-2">
            {{ $registeredCustomer ? __('admin.customers.has_account') : __('admin.customers.no_account') }}
        </span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-body border-bottom p-3 p-md-4">
                    <h5 class="fw-semibold mb-1">{{ __('admin.customers.contact_info') }}</h5>
                    <p class="text-muted mb-0 fs-2">{{ __('admin.customers.contact_hint') }}</p>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        @foreach($contactRows as $row)
                            <div class="d-flex align-items-start gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary" style="width: 36px; height: 36px;">
                                    <i class="{{ $row['icon'] }} fs-5"></i>
                                </span>
                                <div>
                                    <div class="text-muted fs-2">{{ $row['label'] }}</div>
                                    <div class="fw-semibold text-dark text-break">{{ $row['value'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($registeredCustomer)
                        <div class="alert alert-success mt-4 mb-0 py-2 px-3 fs-2">
                            <i class="ti ti-discount-check me-1"></i>
                            {{ __('admin.customers.registered_since', ['date' => $registeredCustomer->created_at?->format('d/m/Y')]) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-body border-bottom p-3 p-md-4">
                    <h5 class="fw-semibold mb-1">{{ __('admin.customers.spend_overview') }}</h5>
                    <p class="text-muted mb-0 fs-2">{{ __('admin.customers.spend_hint') }}</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($metricCards as $card)
                            <div class="col-sm-6 col-lg-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <span class="text-muted fs-2">{{ $card['label'] }}</span>
                                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-{{ $card['tone'] }}-subtle text-{{ $card['tone'] }}" style="width: 32px; height: 32px;">
                                            <i class="{{ $card['icon'] }}"></i>
                                        </span>
                                    </div>
                                    <div class="fw-bolder fs-6 text-dark">{{ $card['value'] }}</div>
                                    <div class="text-muted fs-2 mt-1">{{ $card['hint'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body border-bottom p-3 p-md-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="fw-semibold mb-1">{{ __('admin.customers.order_history') }}</h5>
                <p class="text-muted mb-0 fs-2">
                    {{ __('admin.customers.order_count_summary', ['count' => number_format($metrics->total_orders)]) }}
                </p>
            </div>
            <span class="text-muted fs-2">{{ __('admin.customers.newest_first') }}</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.customers.order_number') }}</th>
                            <th>{{ __('admin.customers.status') }}</th>
                            <th>{{ __('admin.customers.payment_status') }}</th>
                            <th class="text-end">{{ __('admin.customers.grand_total') }}</th>
                            <th>{{ __('admin.customers.last_order') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    @if($orders->isNotEmpty())
                        <tbody>
                            @foreach($orders as $order)
                                <tr class="text-nowrap">
                                    <td class="ps-4">
                                        <span class="fw-bold text-primary">{{ $order->order_number }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusClasses[$order->status] ?? 'bg-secondary-subtle text-secondary' }} fw-semibold fs-2">
                                            {{ __('admin.orders.statuses.'.$order->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $paymentClasses[$order->payment_status] ?? 'bg-secondary-subtle text-secondary' }} fw-semibold fs-2">
                                            {{ __('admin.orders.payment_statuses.'.$order->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-semibold text-primary">{{ number_format($order->grand_total, 0, ',', '.') }} ₫</span>
                                    </td>
                                    <td>
                                        <span class="fs-3 text-muted">{{ $order->created_at->format('d-m-Y H:i') }}</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary fw-semibold">
                                            <i class="ti ti-eye me-1 fs-4"></i>{{ __('admin.customers.details') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>

            @if($orders->isEmpty())
                @include('admin.shared.empty-state', [
                    'emptyId' => 'customer-orders-empty-state',
                    'emptyMessage' => __('admin.customers.empty'),
                ])
            @endif

            @if($orders->hasPages())
                <div class="card-footer bg-transparent border-top py-3">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
