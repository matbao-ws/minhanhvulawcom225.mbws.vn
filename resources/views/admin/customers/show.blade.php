@extends('admin.layouts.app')

@section('title', __('admin.customers.profile_title'))

@push('styles')
<style>
    .customer-profile {
        --customer-ink: #172033;
        --customer-muted: #65738a;
        --customer-line: #e7ebf3;
        --customer-soft: #f6f8fc;
        font-family: 'Quicksand', sans-serif !important;
        color: var(--customer-ink);
    }
    .customer-profile .customer-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px !important;
        font-weight: 700;
    }
    .customer-profile .profile-hero {
        background: linear-gradient(115deg, #18243b 0%, #263b61 63%, #315691 100%);
        border-radius: 13px;
        box-shadow: 0 10px 24px rgba(25, 45, 78, .13);
        overflow: hidden !important;
    }
    .customer-profile .profile-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        border: 2px solid rgba(255,255,255,.24);
        border-radius: 15px;
        background: rgba(255,255,255,.13);
        color: #fff;
        font-size: 20px !important;
        font-weight: 800;
        letter-spacing: .04em;
    }
    .customer-profile .profile-kicker,
    .customer-profile .metric-label,
    .customer-profile .info-label,
    .customer-profile .orders-heading-copy {
        font-size: 12px !important;
        font-weight: 800;
        letter-spacing: .055em;
        text-transform: uppercase;
    }
    .customer-profile .profile-kicker { color: rgba(255,255,255,.65); }
    .customer-profile .profile-name {
        color: #fff;
        font-size: 23px !important;
        font-weight: 800 !important;
        letter-spacing: -.025em;
        line-height: 1.16;
    }
    .customer-profile .profile-contact {
        color: rgba(255,255,255,.78);
        font-size: 14px !important;
        font-weight: 600;
    }
    .customer-profile .profile-meta-card {
        min-width: 205px;
        border: 1px solid rgba(255,255,255,.15);
        border-radius: 12px;
        background: rgba(10, 20, 40, .17);
        color: #fff;
    }
    .customer-profile .profile-meta-card .label { color: rgba(255,255,255,.62); font-size: 12px !important; font-weight: 700; }
    .customer-profile .profile-meta-card .value { color: #fff; font-size: 14px !important; font-weight: 800; }
    .customer-profile .profile-card,
    .customer-profile .metric-card,
    .customer-profile .orders-card {
        border: 1px solid var(--customer-line);
        border-radius: 12px;
        box-shadow: 0 5px 18px rgba(30, 49, 80, .045);
        overflow: hidden !important;
    }
    .customer-profile .profile-card .card-header,
    .customer-profile .orders-card .card-header {
        border-color: var(--customer-line);
        background: #fff;
        padding: 14px 17px;
    }
    .customer-profile .card-heading { color: var(--customer-ink); font-size: 17px !important; font-weight: 800 !important; }
    .customer-profile .card-subheading { color: var(--customer-muted); font-size: 13px !important; font-weight: 600; }
    .customer-profile .contact-row { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; }
    .customer-profile .contact-row + .contact-row { border-top: 1px solid var(--customer-line); }
    .customer-profile .contact-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: #edf3ff;
        color: #315691;
        font-size: 18px;
    }
    .customer-profile .info-label { color: var(--customer-muted); }
    .customer-profile .info-value { color: var(--customer-ink); font-size: 14px !important; font-weight: 750; word-break: break-word; }
    .customer-profile .customer-note { border-radius: 10px; background: var(--customer-soft); color: var(--customer-muted); font-size: 13px !important; font-weight: 600; }
    .customer-profile .customer-summary .card-body { padding: 0; }
    .customer-profile .metric-block { min-height: 118px; padding: 16px 18px; }
    .customer-profile .metric-block + .metric-block { border-left: 1px solid var(--customer-line); }
    .customer-profile .metric-label { color: var(--customer-muted); }
    .customer-profile .metric-value { color: var(--customer-ink); font-size: 25px !important; font-weight: 800 !important; letter-spacing: -.025em; line-height: 1.15; }
    .customer-profile .metric-help { color: var(--customer-muted); font-size: 12px !important; font-weight: 600; }
    .customer-profile .metric-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 9px;
        font-size: 18px;
    }
    .customer-profile .orders-heading-copy { color: var(--customer-muted); }
    .customer-profile .orders-card .table th { padding-top: 13px; padding-bottom: 13px; white-space: nowrap; }
    .customer-profile .orders-card .table td { padding-top: 15px; padding-bottom: 15px; vertical-align: middle; }
    .customer-profile .order-number { color: var(--customer-ink); font-size: 14px !important; font-weight: 800; }
    .customer-profile .order-date { color: var(--customer-muted); font-size: 12.5px !important; font-weight: 600; }
    .customer-profile .order-total { color: var(--customer-ink); font-size: 14px !important; font-weight: 800; }
    .customer-profile .status-pill { display: inline-flex; align-items: center; font-size: 12px !important; font-weight: 750; }
    @media (max-width: 991.98px) {
        .customer-profile .metric-block:nth-child(odd) { border-left: 0; }
        .customer-profile .metric-block:nth-child(n + 3) { border-top: 1px solid var(--customer-line); }
    }
    @media (max-width: 767.98px) {
        .customer-profile .profile-name { font-size: 21px !important; }
        .customer-profile .profile-meta-card { min-width: 100%; }
        .customer-profile .orders-card .table { min-width: 760px; }
    }
    @media (max-width: 575.98px) {
        .customer-profile .metric-block + .metric-block { border-left: 0; border-top: 1px solid var(--customer-line); }
    }
</style>
@endpush

@section('content')
@php
    $initials = collect(preg_split('/\s+/', trim($customerName)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');
    $completionRate = (int) $metrics->total_orders > 0
        ? round(((int) $metrics->completed_orders / (int) $metrics->total_orders) * 100)
        : 0;
    $averageCompletedOrder = (int) $metrics->completed_orders > 0
        ? (float) $metrics->total_spent / (int) $metrics->completed_orders
        : 0;
    $firstOrderAt = $metrics->first_order_at ? \Carbon\Carbon::parse($metrics->first_order_at) : null;
    $lastOrderAt = $metrics->last_order_at ? \Carbon\Carbon::parse($metrics->last_order_at) : null;
@endphp

<div class="customer-profile">
    <a href="{{ route('admin.customers.index') }}" class="customer-back text-primary text-decoration-none mb-3">
        <iconify-icon icon="solar:arrow-left-line-duotone"></iconify-icon>{{ __('admin.customers.back_to_list') }}
    </a>

    <section class="profile-hero card border-0 mb-4">
        <div class="card-body p-4 p-lg-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="profile-avatar" aria-hidden="true">{{ $initials ?: '?' }}</div>
                    <div>
                        <div class="profile-kicker mb-1">Hồ sơ khách hàng</div>
                        <h1 class="profile-name mb-2">{{ $customerName }}</h1>
                        <div class="profile-contact d-flex flex-wrap gap-x-3 gap-y-1">
                            <span><iconify-icon icon="solar:letter-line-duotone" class="me-1"></iconify-icon>{{ $customerEmail }}</span>
                            @if($customerPhone)<span><iconify-icon icon="solar:phone-line-duotone" class="me-1"></iconify-icon>{{ $customerPhone }}</span>@endif
                        </div>
                    </div>
                </div>
                <div class="profile-meta-card p-3">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div><div class="label mb-1">Loại khách hàng</div><div class="value">{{ $registeredCustomer ? __('admin.customers.registered') : __('admin.customers.guest') }}</div></div>
                        <span class="badge {{ $registeredCustomer ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $registeredCustomer ? 'Tài khoản' : 'Mua không cần tài khoản' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <section class="profile-card card">
                <div class="card-header"><h2 class="card-heading mb-1">Thông tin liên hệ</h2><p class="card-subheading mb-0">Dữ liệu lấy từ đơn hàng gần nhất</p></div>
                <div class="card-body p-3">
                    <div class="contact-row pt-0"><span class="contact-icon"><iconify-icon icon="solar:letter-line-duotone"></iconify-icon></span><div><div class="info-label mb-1">Email</div><div class="info-value">{{ $customerEmail }}</div></div></div>
                    <div class="contact-row"><span class="contact-icon"><iconify-icon icon="solar:phone-line-duotone"></iconify-icon></span><div><div class="info-label mb-1">Điện thoại</div><div class="info-value">{{ $customerPhone ?: 'Chưa có thông tin' }}</div></div></div>
                    <div class="contact-row"><span class="contact-icon"><iconify-icon icon="solar:calendar-line-duotone"></iconify-icon></span><div><div class="info-label mb-1">Lần mua đầu</div><div class="info-value">{{ $firstOrderAt?->format('d/m/Y H:i') ?? 'Chưa xác định' }}</div></div></div>
                    <div class="contact-row pb-0"><span class="contact-icon"><iconify-icon icon="solar:clock-circle-line-duotone"></iconify-icon></span><div><div class="info-label mb-1">Đơn gần nhất</div><div class="info-value">{{ $lastOrderAt?->format('d/m/Y H:i') ?? 'Chưa có đơn' }}</div></div></div>
                    @if($registeredCustomer)
                        <div class="customer-note mt-4 p-3"><iconify-icon icon="solar:verified-check-line-duotone" class="me-1"></iconify-icon> Khách đã đăng ký tài khoản từ {{ $registeredCustomer->created_at?->format('d/m/Y') }}.</div>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-xl-8">
            <section class="customer-summary profile-card card">
                <div class="card-header"><h2 class="card-heading mb-1">Tổng quan chi tiêu</h2><p class="card-subheading mb-0">Chỉ số được tính từ lịch sử đơn hàng của khách</p></div>
                <div class="card-body"><div class="row g-0">
                    <div class="col-sm-6 col-lg-3 metric-block"><div class="d-flex justify-content-between gap-2"><div class="metric-label">Tổng đơn</div><span class="metric-icon bg-primary-subtle text-primary"><iconify-icon icon="solar:bag-4-line-duotone"></iconify-icon></span></div><div class="metric-value mt-2">{{ number_format($metrics->total_orders) }}</div><div class="metric-help mt-1">Tất cả đơn đã đặt</div></div>
                    <div class="col-sm-6 col-lg-3 metric-block"><div class="d-flex justify-content-between gap-2"><div class="metric-label">Tỷ lệ hoàn tất</div><span class="metric-icon bg-success-subtle text-success"><iconify-icon icon="solar:check-circle-line-duotone"></iconify-icon></span></div><div class="metric-value mt-2">{{ number_format($completionRate) }}%</div><div class="metric-help mt-1">{{ number_format($metrics->completed_orders) }}/{{ number_format($metrics->total_orders) }} đơn thành công</div></div>
                    <div class="col-sm-6 col-lg-3 metric-block"><div class="d-flex justify-content-between gap-2"><div class="metric-label">Đã chi tiêu</div><span class="metric-icon bg-warning-subtle text-warning"><iconify-icon icon="solar:wallet-money-line-duotone"></iconify-icon></span></div><div class="metric-value mt-2">{{ number_format($metrics->total_spent, 0, ',', '.') }}₫</div><div class="metric-help mt-1">Chỉ tính đơn hoàn tất</div></div>
                    <div class="col-sm-6 col-lg-3 metric-block"><div class="d-flex justify-content-between gap-2"><div class="metric-label">Giá trị TB</div><span class="metric-icon bg-info-subtle text-info"><iconify-icon icon="solar:chart-2-line-duotone"></iconify-icon></span></div><div class="metric-value mt-2">{{ number_format($averageCompletedOrder, 0, ',', '.') }}₫</div><div class="metric-help mt-1">Mỗi đơn hoàn tất</div></div>
                </div></div>
            </section>
        </div>
    </div>

    <section class="orders-card card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><h2 class="card-heading mb-1">{{ __('admin.customers.order_history') }}</h2><p class="card-subheading mb-0">{{ number_format($metrics->total_orders) }} đơn hàng của khách</p></div>
            <span class="orders-heading-copy">Mới nhất trước</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-4">{{ __('admin.customers.order_number') }}</th><th>{{ __('admin.customers.status') }}</th><th>{{ __('admin.customers.payment_status') }}</th><th class="text-end">Tổng thanh toán</th><th>{{ __('admin.customers.last_order') }}</th><th class="pe-4 text-end">Thao tác</th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $statusClasses = ['pending' => 'bg-warning-subtle text-warning', 'processing' => 'bg-info-subtle text-info', 'completed' => 'bg-success-subtle text-success', 'cancelled' => 'bg-danger-subtle text-danger'];
                            $paymentClasses = ['pending' => 'bg-warning-subtle text-warning', 'paid' => 'bg-success-subtle text-success', 'failed' => 'bg-danger-subtle text-danger', 'partially_refunded' => 'bg-info-subtle text-info', 'refunded' => 'bg-secondary-subtle text-secondary'];
                        @endphp
                        <tr>
                            <td class="ps-4"><div class="order-number">{{ $order->order_number }}</div><div class="order-date">{{ $order->customer_phone }}</div></td>
                            <td><span class="badge status-pill {{ $statusClasses[$order->status] ?? 'bg-secondary-subtle text-secondary' }}">{{ __('admin.orders.statuses.'.$order->status) }}</span></td>
                            <td><span class="badge status-pill {{ $paymentClasses[$order->payment_status] ?? 'bg-secondary-subtle text-secondary' }}">{{ __('admin.orders.payment_statuses.'.$order->payment_status) }}</span></td>
                            <td class="text-end"><div class="order-total">{{ number_format($order->grand_total, 0, ',', '.') }} ₫</div></td>
                            <td><div class="order-date">{{ $order->created_at->format('d/m/Y') }}</div><div class="order-date">{{ $order->created_at->format('H:i') }}</div></td>
                            <td class="text-end pe-4"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary"><iconify-icon icon="solar:eye-line-duotone" class="me-1"></iconify-icon>{{ __('admin.customers.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">{{ __('admin.customers.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="card-body border-top">{{ $orders->links() }}</div>@endif
    </section>
</div>
@endsection
