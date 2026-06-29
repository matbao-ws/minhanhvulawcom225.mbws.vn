@extends('admin.layouts.app')

@section('title', __('admin.shipping_partners.title'))

@push('styles')
<style>
    .partner-logo-showcase {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }
    .partner-logo-card {
        padding: 10px 15px;
        border-radius: 8px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        font-weight: 600;
        font-size: 0.85rem;
        color: #495057;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .partner-logo-card i {
        font-size: 1.1rem;
    }
    /* Fix table bottom rounded corners when there is no pagination */
    .card-body.p-0,
    .table-responsive,
    .table-responsive table {
        border-bottom-left-radius: inherit;
        border-bottom-right-radius: inherit;
    }
    .table-responsive table tr:last-child td:first-child {
        border-bottom-left-radius: inherit;
    }
    .table-responsive table tr:last-child td:last-child {
        border-bottom-right-radius: inherit;
    }
</style>
@endpush

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.shipping_partners.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}">{{ __('admin.sidebar.settings') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.shipping_partners.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Partner Showcase Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
                <div>
                    <h5 class="fw-semibold text-dark mb-1">{{ __('admin.shipping_partners.title') }}</h5>
                    <p class="text-muted small mb-0">{{ __('admin.shipping_partners.partner_subtitle') }}</p>
                </div>
                <a href="{{ route('admin.shipping-partners.create') }}" class="btn btn-primary d-flex align-items-center gap-1">
                    <i class="ti ti-plus fs-5"></i> {{ __('admin.shipping_partners.add_partner') }}
                </a>
            </div>

            <!-- Partner logo list -->
            <div class="partner-logo-showcase d-flex align-items-center gap-4 flex-wrap mt-3">
                <img src="{{ asset('admin-assets/images/logo-vanchuyen/Logo-GHTK.webp') }}" alt="GHTK" style="height: 60px; max-width: 140px; object-fit: contain;">
                <img src="{{ asset('admin-assets/images/logo-vanchuyen/J&TExpress.png') }}" alt="J&T Express" style="height: 42px; max-width: 140px; object-fit: contain;">
                <img src="{{ asset('admin-assets/images/logo-vanchuyen/logo-giao-hang-nhanh.jpg') }}" alt="Giao Hàng Nhanh" style="height: 42px; max-width: 160px; object-fit: contain;">
                <img src="{{ asset('admin-assets/images/logo-vanchuyen/SPXEXPRESS.png') }}" alt="SPX Express" style="height: 48px; max-width: 150px; object-fit: contain;">
                <img src="{{ asset('admin-assets/images/logo-vanchuyen/Viettel_Post_logo.svg') }}" alt="Viettel Post" style="height: 50px; max-width: 140px; object-fit: contain;">
            </div>
        </div>
    </div>

    <!-- Partners List Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-semibold text-dark mb-0">{{ __('admin.shipping_partners.list_title') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap text-muted">
                            <th class="ps-4 fw-semibold small">{{ __('admin.shipping_partners.partner_code') }}</th>
                            <th class="fw-semibold small">{{ __('admin.shipping_partners.partner_name') }}</th>
                            <th class="fw-semibold small">{{ __('admin.shipping_partners.account_or_phone') }}</th>
                            <th class="fw-semibold small">{{ __('admin.shipping_partners.type') }}</th>
                            <th class="fw-semibold small">{{ __('admin.shipping_partners.status') }}</th>
                            <th class="pe-4 text-end">{{ __('admin.shipping_partners.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($partners as $partner)
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <span class="font-monospace text-dark fw-semibold">{{ $partner->partner_code }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-1 rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; overflow: hidden; border: 1px solid #e9ecef;">
                                            @if($partner->logo_url && file_exists(public_path('admin-assets/images/logo-vanchuyen/' . $partner->logo_url)))
                                                <img src="{{ asset('admin-assets/images/logo-vanchuyen/' . $partner->logo_url) }}" alt="{{ $partner->name }}" style="width: 100%; height: 100%; object-fit: contain;">
                                            @else
                                                <iconify-icon icon="solar:delivery-line-duotone" class="fs-6 text-primary"></iconify-icon>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark">{{ $partner->name }}</span>
                                            @if($partner->type === 'custom')
                                                <div class="small text-muted">{{ __('admin.shipping_partners.fee') }}: {{ number_format($partner->settings['fee'] ?? 0, 0, ',', '.') }} ₫</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark small">{{ $partner->account_name ?: ($partner->phone ?: __('admin.shipping_partners.not_configured')) }}</span>
                                </td>
                                <td>
                                    @if($partner->type === 'connected')
                                        <span class="badge bg-success-subtle text-success fw-semibold">{{ __('admin.shipping_partners.api_connected') }}</span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary fw-semibold">{{ __('admin.shipping_partners.self_delivery') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input js-status-toggle" type="checkbox" role="switch"
                                            data-url="{{ route('admin.shipping-partners.toggle-status', $partner) }}"
                                            @checked($partner->status === 'active')>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ __('admin.shipping_partners.edit_title') }}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            @if($partner->type === 'connected' || $partner->partner_code === 'DTGHTUGIAO')
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.shipping-partners.settings', $partner) }}">
                                                        <i class="ti ti-settings fs-4"></i> {{ __('admin.shipping_partners.setup_connection') }}
                                                    </a>
                                                </li>
                                            @endif
                                            @if($partner->type === 'custom')
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.shipping-partners.edit', $partner) }}">
                                                        <i class="ti ti-edit fs-4"></i> {{ __('admin.shipping_partners.edit') }}
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('admin.shipping-partners.destroy', $partner) }}" method="POST" class="js-delete-form d-block">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2">
                                                            <i class="ti ti-trash fs-4"></i> {{ __('admin.shipping_partners.delete') }}
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <iconify-icon icon="solar:delivery-broken" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                    <p class="mb-0">{{ __('admin.shipping_partners.unconfigured_warning') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Handle Status Toggle Switch via AJAX
        document.querySelectorAll('.js-status-toggle').forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                const url = this.getAttribute('data-url');
                const isChecked = this.checked;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true
                        });
                        Toast.fire({
                            icon: 'success',
                            title: data.message || '{{ __('admin.shipping_partners.updated') }}'
                        });
                    } else {
                        this.checked = !isChecked; // revert
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __('admin.shipping_partners.notification') }}',
                            text: data.message || '{{ __('admin.error') }}'
                        });
                    }
                })
                .catch(error => {
                    this.checked = !isChecked; // revert
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __('admin.shipping_partners.error_title') }}',
                        text: '{{ __('admin.shipping_partners.error_text') }}'
                    });
                });
            });
        });
    });
</script>
@endpush
