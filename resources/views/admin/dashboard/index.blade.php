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
    }
</style>
@endpush

@section('content')
    <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-9">
                            <h4 class="fw-semibold mb-8">{{ __('admin.dashboard') }}</h4>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item">
                                        <a class="text-muted text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.admin') }}</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">{{ __('admin.dashboard') }}</li>
                                </ol>
                            </nav>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards Row -->
    <div class="row mb-4">
        <!-- Revenue Card -->
        <div class="col-xl-3 col-sm-6 mb-4 mb-xl-0">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">Doanh thu</span>
                        <div class="card-icon bg-success-subtle text-success">
                            <iconify-icon icon="solar:dollar-minimalistic-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['total_revenue'], 0, ',', '.') }} ₫</h3>
                    <p class="text-success small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:round-arrow-right-up-bold-duotone" class="fs-4"></iconify-icon>
                        <span>Hôm nay: +{{ number_format($metrics['today_revenue'], 0, ',', '.') }} ₫</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Total Orders Card -->
        <div class="col-xl-3 col-sm-6 mb-4 mb-xl-0">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">Tổng đơn hàng</span>
                        <div class="card-icon bg-primary-subtle text-primary">
                            <iconify-icon icon="solar:cart-large-4-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['total_orders']) }}</h3>
                    <p class="text-primary small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:bell-bing-bold-duotone" class="fs-4"></iconify-icon>
                        <span>Hôm nay: +{{ number_format($metrics['today_orders']) }} đơn</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Completed Orders Card -->
        <div class="col-xl-3 col-sm-6 mb-4 mb-sm-0">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">Đơn thành công</span>
                        <div class="card-icon bg-info-subtle text-info">
                            <iconify-icon icon="solar:clipboard-check-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['completed_orders']) }}</h3>
                    <p class="text-info small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:pie-chart-bold-duotone" class="fs-4"></iconify-icon>
                        <span>Hoàn thành: {{ $metrics['completed_rate'] }}%</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Average Order Value (AOV) Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="card stat-card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-muted fs-3">Đơn trung bình (AOV)</span>
                        <div class="card-icon bg-warning-subtle text-warning">
                            <iconify-icon icon="solar:wallet-money-bold-duotone" class="fs-6"></iconify-icon>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($metrics['aov'], 0, ',', '.') }} ₫</h3>
                    <p class="text-warning small mb-0 d-flex align-items-center gap-1">
                        <iconify-icon icon="solar:clock-circle-bold-duotone" class="fs-4"></iconify-icon>
                        <span>Đang xử lý: {{ $metrics['processing_orders'] }} đơn</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Revenue Trend Chart -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="fw-semibold text-dark mb-1">Thống kê doanh thu & đơn hàng</h5>
                            <p class="text-muted small mb-0">Dữ liệu trong 30 ngày qua</p>
                        </div>
                    </div>
                    <div id="revenueChart" class="chart-container"></div>
                </div>
            </div>
        </div>

        <!-- Status Breakdown Chart -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="fw-semibold text-dark mb-1">Trạng thái đơn hàng</h5>
                            <p class="text-muted small mb-0">Phân bố theo phần trăm</p>
                        </div>
                    </div>
                    <div id="statusChart" class="chart-container d-flex align-items-center justify-content-center"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Best Sellers and VIP Customers Row -->
    <div class="row mb-4">
        <!-- Top Selling Products -->
        <div class="col-lg-7 mb-4 mb-lg-0">
            <div class="card shadow-sm h-100 mb-0">
                <div class="card-body p-0">
                    <div class="p-4 d-flex align-items-center justify-content-between border-bottom">
                        <h5 class="fw-semibold text-dark mb-0">Sản phẩm bán chạy nhất</h5>
                        <span class="badge bg-primary-subtle text-primary">Top 5</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-nowrap text-muted">
                                    <th class="ps-4 fw-semibold small">Sản phẩm</th>
                                    <th class="fw-semibold small text-end">Số lượng bán</th>
                                    <th class="fw-semibold small text-end pe-4">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topProducts as $prod)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-light rounded p-1 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <iconify-icon icon="solar:box-line-duotone" class="fs-6 text-muted"></iconify-icon>
                                                </div>
                                                <div class="d-flex flex-column" style="max-width: 250px;">
                                                    <span class="fw-semibold text-dark text-truncate">{{ $prod->product_name }}</span>
                                                    <span class="text-muted small">ID: {{ $prod->product_id }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold text-dark">{{ number_format($prod->total_quantity) }}</td>
                                        <td class="text-end fw-semibold text-primary pe-4">{{ number_format($prod->total_revenue, 0, ',', '.') }} ₫</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <iconify-icon icon="solar:box-minimalistic-broken" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                            <p class="mb-0 small">Chưa có dữ liệu sản phẩm bán chạy.</p>
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
                        <h5 class="fw-semibold text-dark mb-0">Khách hàng VIP thân thiết</h5>
                        <span class="badge bg-warning-subtle text-warning">VIP</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-nowrap text-muted">
                                    <th class="ps-4 fw-semibold small">Khách hàng</th>
                                    <th class="fw-semibold small text-end">Số đơn</th>
                                    <th class="fw-semibold small text-end pe-4">Tổng chi tiêu</th>
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
                                            <p class="mb-0 small">Chưa có dữ liệu khách hàng VIP.</p>
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

    <!-- Details Row -->
    <div class="row">
        <!-- Recent Orders Table -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card shadow-sm mb-0">
                <div class="card-body p-0">
                    <div class="p-4 d-flex align-items-center justify-content-between border-bottom">
                        <h5 class="fw-semibold text-dark mb-0">Đơn hàng mới nhất</h5>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-light text-primary fw-semibold px-3">Xem tất cả</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-nowrap text-muted">
                                    <th class="ps-4 fw-semibold small">Mã đơn</th>
                                    <th class="fw-semibold small">Khách hàng</th>
                                    <th class="fw-semibold small">Tổng tiền</th>
                                    <th class="fw-semibold small">Thanh toán</th>
                                    <th class="fw-semibold small">Trạng thái</th>
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
                                                <span class="badge bg-success-subtle text-success fw-semibold fs-1">Đã thanh toán</span>
                                            @elseif($order->payment_status === 'pending')
                                                <span class="badge bg-warning-subtle text-warning fw-semibold fs-1">Chờ thanh toán</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger fw-semibold fs-1">Thất bại</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($order->status === 'completed')
                                                <span class="badge bg-success text-white fw-semibold fs-1">Thành công</span>
                                            @elseif($order->status === 'processing')
                                                <span class="badge bg-info text-white fw-semibold fs-1">Đang xử lý</span>
                                            @elseif($order->status === 'cancelled')
                                                <span class="badge bg-danger text-white fw-semibold fs-1">Đã hủy</span>
                                            @else
                                                <span class="badge bg-warning text-white fw-semibold fs-1">Chờ tiếp nhận</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary fw-semibold px-2 py-1">Chi tiết</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <iconify-icon icon="solar:bill-list-broken" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                            <p class="mb-0 small">Chưa có đơn hàng nào.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Pane: Subscription Detail & Quick Stats -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold text-dark mb-4 pb-2 border-bottom">Hệ thống & Đăng ký</h5>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted">{{ __('admin.current_package') }}</span>
                            <span class="fw-bold text-dark">{{ optional($subscription?->package)->name ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted">{{ __('admin.subscription_status') }}</span>
                            <span class="badge bg-primary-subtle text-primary fw-semibold">{{ $subscription?->status ? ucfirst($subscription->status) : __('admin.inactive') }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted">{{ __('admin.enabled_features') }}</span>
                            <span class="badge bg-light text-dark fw-bold">{{ $enabledFeatureCount }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Revenue & Orders Trend Chart
        const revenueChartOptions = {
            chart: {
                height: 350,
                type: 'area',
                toolbar: { show: false },
                fontFamily: 'inherit',
                zoom: { enabled: false }
            },
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: [3, 3]
            },
            series: [{
                name: 'Doanh thu (₫)',
                type: 'area',
                data: {!! json_encode($chart['revenue']) !!}
            }, {
                name: 'Số đơn hàng',
                type: 'line',
                data: {!! json_encode($chart['orders']) !!}
            }],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: [0.35, 0.05],
                    opacityTo: [0.05, 0.05],
                    stops: [0, 90, 100]
                }
            },
            colors: ['#0d6efd', '#ffc107'],
            xaxis: {
                categories: {!! json_encode($chart['dates']) !!},
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: { colors: '#6c757d', fontSize: '10px' },
                    rotate: -45,
                    rotateAlways: false,
                    hideOverlappingLabels: true
                }
            },
            yaxis: [
                {
                    labels: {
                        formatter: function (value) {
                            return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                        },
                        style: { colors: '#6c757d' }
                    }
                },
                {
                    opposite: true,
                    labels: {
                        formatter: function (value) {
                            return Math.floor(value);
                        },
                        style: { colors: '#6c757d' }
                    }
                }
            ],
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (value, { seriesIndex }) {
                        if (seriesIndex === 0) {
                            return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                        }
                        return value + ' đơn';
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
                position: 'top',
                horizontalAlign: 'right',
                offsetY: -10,
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
                fontFamily: 'inherit'
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
                                label: 'Tổng số đơn',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                offsetY: 0
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return value + ' đơn';
                    }
                }
            }
        };

        const statusChart = new ApexCharts(document.querySelector("#statusChart"), statusChartOptions);
        statusChart.render();
    });
</script>
@endpush
