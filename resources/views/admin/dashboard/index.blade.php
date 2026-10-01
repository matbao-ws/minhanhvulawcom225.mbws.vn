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
    .dashboard-metric-card {
        position: relative;
        min-height: 148px;
        overflow: hidden;
        border: 1px solid #edf0f6;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(44, 58, 87, 0.06);
    }
    .dashboard-metric-card .card-body {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 48px minmax(0, 1fr);
        grid-template-rows: 48px 26px 18px;
        column-gap: 12px;
        align-content: start;
        min-height: 146px;
        padding: 17px 18px !important;
    }
    .dashboard-metric-card .card-body > div.d-flex {
        display: contents !important;
    }
    .dashboard-metric-card .card-icon {
        grid-column: 1;
        grid-row: 1;
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        border-radius: 12px;
    }
    .dashboard-metric-card .card-body > div.d-flex > span { grid-column: 2; grid-row: 1; align-self: center; padding-top: 0; font-size: 14px !important; font-weight: 700 !important; }
    .dashboard-metric-card h3 { grid-column: 2; grid-row: 2; position: relative; z-index: 1; align-self: center; margin: 0 !important; transform: translateY(-5px); font-size: 25px !important; line-height: 1; }
    .dashboard-metric-card p { grid-column: 2; grid-row: 3; position: relative; z-index: 1; align-self: center; margin: 0 !important; color: #8190a8 !important; font-size: 12px !important; }
    .dashboard-metric-card__wave { position: absolute; right: 0; bottom: 0; left: 0; width: 100%; height: 38px; pointer-events: none; }
    .dashboard-metric-card--revenue .dashboard-metric-card__wave { color: #9af0c2; }
    .dashboard-metric-card--pending .dashboard-metric-card__wave { color: #ffd36d; }
    .dashboard-metric-card--processing .dashboard-metric-card__wave { color: #ff9bb0; }
    .dashboard-metric-card--completed .dashboard-metric-card__wave { color: #8edcff; }
    .dashboard-package-usage {
        padding: 14px 18px;
        border: 1px solid #e8edf8;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(44, 58, 87, 0.05);
    }
    .dashboard-package-usage__intro { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .dashboard-package-usage__intro-content { display: flex; min-width: 0; align-items: center; gap: 12px; }
    .dashboard-package-usage__icon {
        display: inline-flex;
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #713df2;
        background: linear-gradient(145deg, #f2edff, #e9e0ff);
    }
    .dashboard-package-usage__title { color: #d11e28; font-size: 14px; font-weight: 800; }
    .dashboard-package-usage__title-row { display: flex; align-items: center; gap: 10px; }
    .dashboard-package-usage__badge {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        border: 1px solid #e3d9ff;
        border-radius: 999px;
        color: #713df2;
        background: #f5f1ff;
        font-size: 12px;
        font-weight: 800;
    }
    .dashboard-package-usage__item {
        min-width: 0;
        height: 100%;
        padding: 14px;
        border: 1px solid #edf1f8;
        border-radius: 12px;
        background: #fbfcff;
    }
    .dashboard-package-usage__meta { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 7px; color: #8190a8; font-size: 12px; font-weight: 600; }
    .dashboard-package-usage__meta strong { color: #627391; font-weight: 800; white-space: nowrap; }
    .dashboard-package-usage .progress { height: 5px !important; border-radius: 999px; background-color: #e8edf5; }
    .dashboard-package-usage .progress-bar { border-radius: inherit; background-color: #d11e28 !important; }
    @media (max-width: 991.98px) { .dashboard-package-usage__intro { margin-bottom: 12px; } }
    @media (max-width: 575.98px) {
        .dashboard-package-usage__intro { align-items: flex-start; flex-direction: column; }
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

    .dashboard-welcome-banner {
        position: relative;
        min-height: 194px;
        padding: 28px 36px;
        overflow: hidden;
        border-radius: 16px;
        background: #fff7f7 url('{{ asset('admin-assets/images/backgrounds/bg-brecumb-dashboard.png') }}') center / cover no-repeat;
    }
    .dashboard-welcome-content {
        position: relative;
        z-index: 1;
        max-width: 60%;
    }
    .dashboard-welcome-banner h1 {
        color: #101d48;
        font-size: clamp(26px, 2.2vw, 36px) !important;
        font-weight: 700 !important;
        line-height: 1.15;
    }
    .dashboard-welcome-banner p {
        color: #53627e !important;
    }
    .dashboard-welcome-features {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 24px;
    }
    .dashboard-welcome-feature {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        color: #53627e;
        font-size: 13px;
        font-weight: 600;
    }
    .dashboard-welcome-feature-icon {
        display: inline-flex;
        width: 34px;
        height: 34px;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(168, 94, 111, 0.10);
    }
    .dashboard-welcome-mascot {
        position: absolute;
        right: 1.5%;
        /* The PNG includes transparent space under the mascot. */
        bottom: -44px;
        width: min(48%, 620px);
        height: calc(100% + 55px);
        object-fit: contain;
        object-position: bottom right;
    }
    @media (max-width: 991.98px) {
        .dashboard-welcome-banner { padding: 24px; }
        .dashboard-welcome-content { max-width: 63%; }
        .dashboard-welcome-mascot { width: 46%; right: -2%; bottom: -36px; height: calc(100% + 36px); }
    }
    @media (max-width: 767.98px) {
        .dashboard-welcome-banner { min-height: 0; padding: 24px 20px; }
        .dashboard-welcome-content { max-width: none; }
        .dashboard-welcome-mascot { display: none; }
        .dashboard-welcome-features { gap: 10px 16px; }
    }
</style>
@endpush

@section('content')
    <section class="dashboard-welcome-banner mb-4" aria-labelledby="dashboard-welcome-title">
        <div class="dashboard-welcome-content">
            {{-- <p class="mb-1 fw-semibold">{{ __('admin.dashboard_page.welcome_greeting') }}</p> --}}
            <h1 id="dashboard-welcome-title" class="mb-1">{{ __('admin.dashboard_page.welcome_title') }}</h1>
            <p class="mb-4">{{ __('admin.dashboard_page.welcome_description') }}</p>
            <div class="dashboard-welcome-features">
                <span class="dashboard-welcome-feature"><span class="dashboard-welcome-feature-icon text-danger"><iconify-icon icon="solar:chart-2-bold-duotone" class="fs-5"></iconify-icon></span>{{ __('admin.dashboard_page.welcome_features.revenue') }}</span>
                <span class="dashboard-welcome-feature"><span class="dashboard-welcome-feature-icon text-primary"><iconify-icon icon="solar:bag-4-bold-duotone" class="fs-5"></iconify-icon></span>{{ __('admin.dashboard_page.welcome_features.orders') }}</span>
                <span class="dashboard-welcome-feature"><span class="dashboard-welcome-feature-icon text-success"><iconify-icon icon="solar:users-group-rounded-bold-duotone" class="fs-5"></iconify-icon></span>{{ __('admin.dashboard_page.welcome_features.customers') }}</span>
                <span class="dashboard-welcome-feature"><span class="dashboard-welcome-feature-icon text-warning"><iconify-icon icon="solar:shop-2-bold-duotone" class="fs-5"></iconify-icon></span>{{ __('admin.dashboard_page.welcome_features.growth') }}</span>
            </div>
        </div>
        <img class="dashboard-welcome-mascot" src="{{ asset('admin-assets/images/backgrounds/icon-homme.png') }}" alt="" aria-hidden="true">
    </section>

    @if(! empty($packageUsage['rows']))
        <section class="dashboard-package-usage mb-4" aria-label="{{ __('admin.package.usage_title') }}">
            <div class="dashboard-package-usage__intro mb-3">
                <div class="dashboard-package-usage__intro-content">
                    <span class="dashboard-package-usage__icon"><iconify-icon icon="solar:database-bold-duotone" class="fs-6"></iconify-icon></span>
                    <div>
                        <div class="dashboard-package-usage__title-row">
                            <div class="dashboard-package-usage__title">{{ __('admin.package.usage_title') }}</div>
                            <form method="POST" action="{{ route('admin.dashboard.package-usage.refresh') }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none d-inline-flex align-items-center gap-1" title="{{ __('admin.package.refresh') }}">
                                    <i class="ti ti-refresh" aria-hidden="true"></i>{{ __('admin.package.refresh') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <span class="dashboard-package-usage__badge"><i class="ti ti-crown" aria-hidden="true"></i>{{ $packageUsage['package'] ?? __('admin.package.no_package') }}</span>
            </div>
            <div class="row g-3">
                @foreach($packageUsage['rows'] as $row)
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="dashboard-package-usage__item">
                            <div class="dashboard-package-usage__meta">
                                <span>{{ $row['label'] }}</span>
                                <strong>{{ $row['used'] }} / {{ $row['limit'] ?? __('admin.package.unlimited') }}</strong>
                            </div>
                            @if($row['percent'] !== null)
                                <div class="progress" role="progressbar" aria-label="{{ $row['label'] }}" aria-valuenow="{{ $row['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ $row['percent'] }}%"></div>
                                </div>
                            @else
                                <div class="progress" aria-hidden="true"><div class="progress-bar" style="width: 0%"></div></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($analyticsEnabled ?? false)
        @include('admin.dashboard._analytics')
    @endif

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
    <div class="row g-3 mb-4">
        <!-- Revenue Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="dashboard-metric-card dashboard-metric-card--revenue h-100">
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
                    <svg class="dashboard-metric-card__wave" viewBox="0 0 300 38" preserveAspectRatio="none" aria-hidden="true"><path d="M0 25C24 10 45 14 65 22s40 10 62 1 40-12 63-2 42 9 61-3 35-7 49 0v20H0Z" fill="currentColor" fill-opacity=".42"/></svg>
                </div>
            </div>
        </div>

        <!-- Pending Orders Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="dashboard-metric-card dashboard-metric-card--pending h-100">
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
                    <svg class="dashboard-metric-card__wave" viewBox="0 0 300 38" preserveAspectRatio="none" aria-hidden="true"><path d="M0 19c20-12 38-12 57 1s36 8 54-3 40-7 59 4 38 7 58-3 44-8 72 2v18H0Z" fill="currentColor" fill-opacity=".43"/></svg>
                </div>
            </div>
        </div>

        <!-- Processing Orders Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="dashboard-metric-card dashboard-metric-card--processing h-100">
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
                    <svg class="dashboard-metric-card__wave" viewBox="0 0 300 38" preserveAspectRatio="none" aria-hidden="true"><path d="M0 21c19-13 40-8 61 1s40 8 60-2 38-11 57 0 42 8 60-2 40-8 62 0v20H0Z" fill="currentColor" fill-opacity=".44"/></svg>
                </div>
            </div>
        </div>

        <!-- Completed Orders Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="dashboard-metric-card dashboard-metric-card--completed h-100">
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
                    <svg class="dashboard-metric-card__wave" viewBox="0 0 300 38" preserveAspectRatio="none" aria-hidden="true"><path d="M0 20c20-10 40-8 61 1s39 9 58-1 40-10 59 0 39 9 59-2 40-8 63 1v19H0Z" fill="currentColor" fill-opacity=".46"/></svg>
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
{{-- Loaded for the order charts and for the analytics section, which a role without orders.view may still see. --}}
@if($canViewOrders || ($analyticsEnabled ?? false))
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endif
@if($canViewOrders)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Revenue & Orders Trend Chart
        const revenueChartOptions = {
            chart: {
                height: 350,
                width: '100%',
                type: 'line',
                toolbar: { show: false },
                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
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
                    fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif'
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
                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
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
                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif'
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
                                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
                                fontWeight: 600,
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            },
                            value: {
                                show: true,
                                fontSize: '20px',
                                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
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
                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
                fontWeight: 600
            },
            tooltip: {
                style: {
                    fontSize: '13px',
                    fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif'
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
                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
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
                style: { fontSize: '13px', fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif' },
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
                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
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
                fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif',
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
                style: { fontSize: '13px', fontFamily: 'Ubuntu Admin, Ubuntu, Segoe UI, Arial, sans-serif' },
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
