@extends('admin.layouts.app')

@section('title', __('admin.dashboard'))

@push('styles')
<style>
    .stat-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    }
    .card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .chart-container {
        min-height: 350px;
        position: relative;
        width: 100%;
        overflow: hidden;
    }

    /* Dashboard specific card overrides */
    .stat-card .text-muted {
        font-size: 15px !important;
        color: #5a6a85 !important;
        font-weight: 600 !important;
    }
    .stat-card h3 {
        font-size: 28px !important;
        color: #2a3547 !important;
        font-weight: 700 !important;
    }
    .stat-card p.small, .stat-card p span {
        font-size: 13.5px !important;
        font-weight: 600 !important;
    }
    
    /* Headings and card titles */
    .card-body h5 {
        font-size: 18px !important;
        color: #2a3547 !important;
        font-weight: 700 !important;
    }
    .card-body p {
        font-size: 15px !important;
        color: #5a6a85 !important;
    }
</style>
@endpush

@section('content')
    <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.dashboard') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.dashboard') }}</a>
                                    </li>
                                    <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.dashboard') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!$canViewOrders)
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="card-icon bg-primary-subtle text-primary flex-shrink-0">
                        <iconify-icon icon="solar:shield-user-bold-duotone" class="fs-6"></iconify-icon>
                    </div>
                    <div>
                        <h5 class="fw-semibold mb-1">{{ __('admin.dashboard_page.limited_title') }}</h5>
                        <p class="text-muted mb-0">{{ __('admin.dashboard_page.limited_description') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @else
    <!-- Stat Cards Row -->
    <div class="row mb-4">
        <!-- Revenue Card -->
        <div class="col-xl-3 col-sm-6 mb-4 mb-xl-0">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">{{ __('admin.dashboard_page.monthly_revenue') }}</span>
                        <div class="card-icon bg-success-subtle text-success">
                            <iconify-icon icon="solar:dollar-minimalistic-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['monthly_revenue'], 0, ',', '.') }} ₫</h3>
                    <p class="text-success small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:round-arrow-right-up-bold-duotone" class="fs-4"></iconify-icon>
                        <span>{{ __('admin.dashboard_page.today_revenue', ['amount' => number_format($metrics['today_revenue'], 0, ',', '.')]) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Pending Orders Card -->
        <div class="col-xl-3 col-sm-6 mb-4 mb-xl-0">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">{{ __('admin.dashboard_page.pending_orders') }}</span>
                        <div class="card-icon bg-warning-subtle text-warning">
                            <iconify-icon icon="solar:clock-circle-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['pending_orders']) }}</h3>
                    <p class="text-warning small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:bell-bing-bold-duotone" class="fs-4"></iconify-icon>
                        <span>{{ __('admin.dashboard_page.today_pending', ['count' => number_format($metrics['today_pending_orders'])]) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Processing Orders Card -->
        <div class="col-xl-3 col-sm-6 mb-4 mb-sm-0">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">{{ __('admin.dashboard_page.processing_orders') }}</span>
                        <div class="card-icon bg-primary-subtle text-primary">
                            <iconify-icon icon="solar:play-circle-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['processing_orders_count']) }}</h3>
                    <p class="text-primary small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:refresh-bold-duotone" class="fs-4"></iconify-icon>
                        <span>{{ __('admin.dashboard_page.today_processing', ['count' => number_format($metrics['today_processing_orders'])]) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Completed Orders Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">{{ __('admin.dashboard_page.completed_orders') }}</span>
                        <div class="card-icon bg-info-subtle text-info">
                            <iconify-icon icon="solar:clipboard-check-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['completed_orders_count']) }}</h3>
                    <p class="text-info small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:pie-chart-bold-duotone" class="fs-4"></iconify-icon>
                        <span>{{ __('admin.dashboard_page.completed_rate', ['rate' => $metrics['completed_rate']]) }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Revenue Trend Chart -->
        <div class="col-lg-8 mb-4 mb-lg-0" style="min-width: 0;">
            <div class="card shadow-sm h-100 mb-0" style="min-width: 0;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="fw-semibold text-dark mb-1">{{ __('admin.dashboard_page.revenue_orders_chart') }}</h5>
                            <p class="text-muted small mb-0">{{ __('admin.dashboard_page.weekly_data') }}</p>
                        </div>
                    </div>
                    <div id="revenueChart" class="chart-container"></div>
                </div>
            </div>
        </div>

        <!-- Status Breakdown Chart -->
        <div class="col-lg-4" style="min-width: 0;">
            <div class="card shadow-sm h-100 mb-0" style="min-width: 0;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="fw-semibold text-dark mb-1">{{ __('admin.dashboard_page.order_status') }}</h5>
                            <p class="text-muted small mb-0">{{ __('admin.dashboard_page.percentage_distribution') }}</p>
                        </div>
                    </div>
                    <div id="statusChart" class="chart-container d-flex align-items-center justify-content-center"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Reports Row -->
    <div class="row mb-4">
        <!-- Annual Revenue Report -->
        <div class="col-lg-6 mb-4 mb-lg-0" style="min-width: 0;">
            <div class="card shadow-sm h-100 mb-0" style="min-width: 0;">
                <div class="card-body p-0">
                    <div class="p-4 d-flex align-items-center justify-content-between border-bottom">
                        <h5 class="fw-semibold text-dark mb-0">{{ __('admin.dashboard_page.annual_revenue_report', ['year' => $annualChart['year']]) }}</h5>
                        <span class="badge bg-success-subtle text-success">{{ __('admin.dashboard_page.full_year') }}</span>
                    </div>
                    <div class="p-4">
                        <div id="annualRevenueChart" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Admin Activity Report -->
        <div class="col-lg-6" style="min-width: 0;">
            <div class="card shadow-sm h-100 mb-0" style="min-width: 0;">
                <div class="card-body p-0">
                    <div class="p-4 d-flex align-items-center justify-content-between border-bottom">
                        <h5 class="fw-semibold text-dark mb-0">{{ __('admin.dashboard_page.weekly_activity_report') }}</h5>
                        <span class="badge bg-primary-subtle text-primary">{{ __('admin.dashboard_page.total_activities', ['total' => number_format($activityChart['total'])]) }}</span>
                    </div>
                    <div class="p-4">
                        <div id="weeklyTrafficChart" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders and VIP Customers Row -->
    <div class="row mb-4">
        <!-- Recent Orders Table -->
        <div class="col-lg-7 mb-4 mb-lg-0">
            <div class="card shadow-sm h-100 mb-0">
                <div class="card-body p-0">
                    <div class="p-4 d-flex align-items-center justify-content-between border-bottom">
                        <h5 class="fw-semibold text-dark mb-0">{{ __('admin.dashboard_page.recent_orders') }}</h5>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-light text-primary fw-semibold px-3">{{ __('admin.dashboard_page.view_all') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-nowrap text-muted">
                                    <th class="ps-4 fw-semibold small">{{ __('admin.dashboard_page.order_number') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.dashboard_page.customer') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.dashboard_page.grand_total') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.dashboard_page.payment') }}</th>
                                    <th class="fw-semibold small">{{ __('admin.dashboard_page.status') }}</th>
                                    <th class="pe-4"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                    <tr class="text-nowrap">
                                        <td class="ps-4">
                                            <span class="fw-bold text-primary">{{ $order->order_number }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-semibold text-dark">{{ $order->customer_name }}</span>
                                                <span class="text-muted small">{{ $order->customer_phone }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary">{{ number_format($order->grand_total, 0, ',', '.') }} ₫</span>
                                        </td>
                                        <td>
                                            @if($order->payment_status === 'paid')
                                                <span class="badge bg-success-subtle text-success fw-semibold fs-1">{{ __('admin.orders.payment_statuses.paid') }}</span>
                                            @elseif($order->payment_status === 'pending')
                                                <span class="badge bg-warning-subtle text-warning fw-semibold fs-1">{{ __('admin.orders.payment_statuses.pending') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger fw-semibold fs-1">{{ __('admin.orders.payment_statuses.failed') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($order->status === 'completed')
                                                <span class="badge bg-success text-white fw-semibold fs-1">{{ __('admin.orders.statuses.completed') }}</span>
                                            @elseif($order->status === 'processing')
                                                <span class="badge bg-info text-white fw-semibold fs-1">{{ __('admin.orders.statuses.processing') }}</span>
                                            @elseif($order->status === 'cancelled')
                                                <span class="badge bg-danger text-white fw-semibold fs-1">{{ __('admin.orders.statuses.cancelled') }}</span>
                                            @else
                                                <span class="badge bg-warning text-white fw-semibold fs-1">{{ __('admin.orders.statuses.pending') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary fw-semibold px-2 py-1">{{ __('admin.dashboard_page.details') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <iconify-icon icon="solar:bill-list-broken" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                            <p class="mb-0 small">{{ __('admin.dashboard_page.no_orders') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top VIP Customers -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100 mb-0">
                <div class="card-body p-0">
                    <div class="p-4 d-flex align-items-center justify-content-between border-bottom">
                        <h5 class="fw-semibold text-dark mb-0">{{ __('admin.dashboard_page.vip_customers') }}</h5>
                        <span class="badge bg-warning-subtle text-warning">{{ __('admin.dashboard_page.vip_badge') }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-nowrap text-muted">
                                    <th class="ps-4 fw-semibold small">{{ __('admin.dashboard_page.customer') }}</th>
                                    <th class="fw-semibold small text-end">{{ __('admin.dashboard_page.order_count') }}</th>
                                    <th class="fw-semibold small text-end pe-4">{{ __('admin.dashboard_page.total_spent') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topCustomers as $customer)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-warning-subtle rounded-circle d-flex align-items-center justify-content-center text-warning fw-bold" style="width: 36px; height: 36px;">
                                                    {{ strtoupper(substr($customer->customer_name, 0, 1)) }}
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-semibold text-dark">{{ $customer->customer_name }}</span>
                                                    <span class="text-muted small">{{ $customer->customer_phone ?: $customer->customer_email }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold text-dark">{{ number_format($customer->total_orders) }}</td>
                                        <td class="text-end fw-semibold text-success pe-4">{{ number_format($customer->total_spent, 0, ',', '.') }} ₫</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <iconify-icon icon="solar:users-group-two-rounded-broken" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                            <p class="mb-0 small">{{ __('admin.dashboard_page.no_vip_data') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @endif
@endsection

@push('scripts')
@if($canViewOrders)
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Revenue & Orders Trend Chart
        const revenueChartOptions = {
            chart: {
                height: 350,
                width: '100%',
                type: 'line',
                toolbar: { show: false },
                fontFamily: 'Quicksand, sans-serif',
                zoom: { enabled: false }
            },
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: [3, 3]
            },
            series: [{
                name: '{{ __('admin.dashboard_page.revenue') }}',
                type: 'area',
                data: {!! json_encode($chart['revenue']) !!}
            }, {
                name: '{{ __('admin.dashboard_page.orders') }}',
                type: 'line',
                data: {!! json_encode($chart['orders']) !!}
            }],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: [0.35, 1],
                    opacityTo: [0.05, 1],
                    stops: [0, 90, 100]
                }
            },
            colors: ['#0d6efd', '#fd7e14'],
            xaxis: {
                categories: {!! json_encode($chart['dates']) !!},
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: { colors: '#6c757d', fontSize: '13px', fontWeight: 600 },
                    rotate: -45,
                    rotateAlways: false,
                    hideOverlappingLabels: true
                }
            },
            yaxis: [
                {
                    seriesName: '{{ __('admin.dashboard_page.revenue') }}',
                    labels: {
                        formatter: function (value) {
                            return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                        },
                        style: { colors: '#6c757d', fontSize: '12px', fontWeight: 600 }
                    }
                },
                {
                    seriesName: '{{ __('admin.dashboard_page.orders') }}',
                    opposite: true,
                    labels: {
                        formatter: function (value) {
                            if (value % 1 === 0) {
                                return value;
                            }
                            return '';
                        },
                        style: { colors: '#6c757d', fontSize: '12px', fontWeight: 600 }
                    }
                }
            ],
            tooltip: {
                shared: true,
                intersect: false,
                style: {
                    fontSize: '13px',
                    fontFamily: 'Quicksand, sans-serif'
                },
                y: {
                    formatter: function (value, { seriesIndex }) {
                        if (seriesIndex === 0) {
                            return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                        }
                        return value + ' {{ __('admin.dashboard_page.units.orders') }}';
                    }
                }
            },
            grid: {
                borderColor: 'rgba(0,0,0,0.05)',
                strokeDashArray: 4,
                yaxis: {
                    lines: { show: true }
                },
                padding: {
                    left: 20,
                    right: 20
                }
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                offsetY: 10,
                fontSize: '13px',
                fontFamily: 'Quicksand, sans-serif',
                fontWeight: 600,
                markers: {
                    radius: 12
                }
            }
        };

        const revenueChart = new ApexCharts(document.querySelector("#revenueChart"), revenueChartOptions);
        revenueChart.render();

        // 2. Status Pie Chart
        const statusChartOptions = {
            chart: {
                type: 'donut',
                height: 320,
                width: '100%',
                fontFamily: 'Quicksand, sans-serif'
            },
            series: {!! json_encode($statusChart['series']) !!},
            labels: {!! json_encode($statusChart['labels']) !!},
            colors: ['#ffc107', '#0dcaf0', '#198754', '#dc3545'], // pending, processing, completed, cancelled
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: '{{ __('admin.dashboard_page.total_orders') }}',
                                fontSize: '15px',
                                fontFamily: 'Quicksand, sans-serif',
                                fontWeight: 600,
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            },
                            value: {
                                show: true,
                                fontSize: '20px',
                                fontFamily: 'Quicksand, sans-serif',
                                fontWeight: 700
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                offsetY: 0,
                fontSize: '13px',
                fontFamily: 'Quicksand, sans-serif',
                fontWeight: 600
            },
            tooltip: {
                style: {
                    fontSize: '13px',
                    fontFamily: 'Quicksand, sans-serif'
                },
                y: {
                    formatter: function (value) {
                        return value + ' {{ __('admin.dashboard_page.units.orders') }}';
                    }
                }
            }
        };

        const statusChart = new ApexCharts(document.querySelector("#statusChart"), statusChartOptions);
        statusChart.render();

        // 3. Annual Revenue Chart
        const annualRevenueChartOptions = {
            chart: {
                height: 320,
                width: '100%',
                type: 'area',
                toolbar: { show: false },
                fontFamily: 'Quicksand, sans-serif',
                zoom: { enabled: false }
            },
            colors: ['#13deb9'],
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.3,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            markers: {
                size: 5,
                colors: ['#13deb9'],
                strokeColors: '#fff',
                strokeWidth: 2,
                hover: { size: 7 }
            },
            series: [{
                name: '{{ __('admin.dashboard_page.total_revenue') }}',
                data: {!! json_encode($annualChart['data']) !!}
            }],
            xaxis: {
                categories: [
                    '{{ __('admin.dashboard_page.months.Jan') }}',
                    '{{ __('admin.dashboard_page.months.Feb') }}',
                    '{{ __('admin.dashboard_page.months.Mar') }}',
                    '{{ __('admin.dashboard_page.months.Apr') }}',
                    '{{ __('admin.dashboard_page.months.May') }}',
                    '{{ __('admin.dashboard_page.months.Jun') }}',
                    '{{ __('admin.dashboard_page.months.Jul') }}',
                    '{{ __('admin.dashboard_page.months.Aug') }}',
                    '{{ __('admin.dashboard_page.months.Sep') }}',
                    '{{ __('admin.dashboard_page.months.Oct') }}',
                    '{{ __('admin.dashboard_page.months.Nov') }}',
                    '{{ __('admin.dashboard_page.months.Dec') }}'
                ],
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: { colors: '#6c757d', fontSize: '13px', fontWeight: 600 }
                }
            },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                    },
                    style: { colors: '#6c757d', fontSize: '12px', fontWeight: 600 }
                }
            },
            tooltip: {
                theme: 'light',
                x: { show: true },
                style: { fontSize: '13px', fontFamily: 'Quicksand, sans-serif' },
                y: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                    }
                }
            },
            grid: {
                borderColor: 'rgba(0,0,0,0.05)',
                strokeDashArray: 4
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '13px',
                fontFamily: 'Quicksand, sans-serif',
                fontWeight: 600
            }
        };

        const annualRevenueChart = new ApexCharts(document.querySelector("#annualRevenueChart"), annualRevenueChartOptions);
        annualRevenueChart.render();

        // 4. Weekly Admin Activity Chart
        const weeklyActivityChartOptions = {
            chart: {
                height: 320,
                width: '100%',
                type: 'area',
                toolbar: { show: false },
                fontFamily: 'Quicksand, sans-serif',
                zoom: { enabled: false }
            },
            colors: ['#539bff'],
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            markers: {
                size: 5,
                colors: ['#539bff'],
                strokeColors: '#fff',
                strokeWidth: 2,
                hover: { size: 7 }
            },
            series: [{
                name: '{{ __('admin.dashboard_page.total_activities_label') }}',
                data: {!! json_encode($activityChart['data']) !!}
            }],
            xaxis: {
                categories: {!! json_encode($activityChart['dates']) !!},
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: { colors: '#6c757d', fontSize: '13px', fontWeight: 600 }
                }
            },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return value;
                    },
                    style: { colors: '#6c757d', fontSize: '12px', fontWeight: 600 }
                }
            },
            tooltip: {
                theme: 'light',
                style: { fontSize: '13px', fontFamily: 'Quicksand, sans-serif' },
                y: {
                    formatter: function (value) {
                        return value + ' {{ __('admin.dashboard_page.units.activities') }}';
                    }
                }
            },
            grid: {
                borderColor: 'rgba(0,0,0,0.05)',
                strokeDashArray: 4
            }
        };

        const weeklyActivityChart = new ApexCharts(document.querySelector("#weeklyTrafficChart"), weeklyActivityChartOptions);
        weeklyActivityChart.render();

        // 5. Trigger resize event when sidebar expands or collapses
        document.querySelectorAll('.sidebartoggler').forEach(function (el) {
            el.addEventListener('click', function () {
                setTimeout(function () {
                    window.dispatchEvent(new Event('resize'));
                }, 300);
            });
        });
    });
</script>
@endif
@endpush
