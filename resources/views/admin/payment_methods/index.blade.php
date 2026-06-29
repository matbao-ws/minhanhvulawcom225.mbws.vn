@extends('admin.layouts.app')

@section('title', __('admin.payment_methods.title'))

@push('styles')
<style>
    .partner-logo-showcase {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: center;
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
                    <h4 class="fw-semibold mb-1">{{ __('admin.payment_methods.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}">{{ __('admin.sidebar.settings') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.payment_methods.title') }}</li>
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
                    <h5 class="fw-semibold text-dark mb-1">{{ __('admin.payment_methods.title') }}</h5>
                    <p class="text-muted small mb-0">{{ __('admin.payment_methods.partner_subtitle') }}</p>
                </div>
                <a href="{{ route('admin.payment-methods.create') }}" class="btn btn-primary d-flex align-items-center gap-1">
                    <i class="ti ti-plus fs-5"></i> {{ __('admin.payment_methods.add_partner') }}
                </a>
            </div>

            <!-- Partner logo list -->
            <div class="partner-logo-showcase d-flex align-items-center gap-4 flex-wrap mt-3">
                <img src="{{ asset('admin-assets/images/logo-thanhtoan/sepay-logo.png') }}" alt="Sepay" style="height: 38px; max-width: 140px; object-fit: contain;">
                <img src="{{ asset('admin-assets/images/logo-thanhtoan/stripe-logo.png') }}" alt="Stripe" style="height: 36px; max-width: 140px; object-fit: contain;">
            </div>
        </div>
    </div>

    <!-- Partners List Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-semibold text-dark mb-0">{{ __('admin.payment_methods.list_title') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap text-muted">
                            <th class="ps-4 fw-semibold small">{{ __('admin.payment_methods.partner_code') }}</th>
                            <th class="fw-semibold small">{{ __('admin.payment_methods.partner_name') }}</th>
                            <th class="fw-semibold small">{{ __('admin.payment_methods.account_or_phone') }}</th>
                            <th class="fw-semibold small">{{ __('admin.payment_methods.type') }}</th>
                            <th class="fw-semibold small">{{ __('admin.payment_methods.status') }}</th>
                            <th class="pe-4 text-end">{{ __('admin.payment_methods.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($methods as $method)
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <span class="font-monospace text-dark fw-semibold">{{ $method->method_code }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-1 rounded d-flex align-items-center justify-content-center bg-white" style="width: 48px; height: 48px; overflow: hidden; border: 1px solid #e9ecef;">
                                            @if($method->logo_url && file_exists(public_path('admin-assets/images/logo-thanhtoan/' . $method->logo_url)))
                                                <img src="{{ asset('admin-assets/images/logo-thanhtoan/' . $method->logo_url) }}" alt="{{ $method->name }}" style="width: 100%; height: 100%; object-fit: contain;">
                                            @else
                                                <iconify-icon icon="solar:card-recive-line-duotone" class="fs-6 text-primary"></iconify-icon>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark">{{ $method->name }}</span>
                                            @if($method->type === 'custom' && $method->method_code !== 'cod' && $method->method_code !== 'bank_transfer')
                                                <div class="small text-muted">{{ $method->settings['description'] ?? '' }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark small">
                                        @if($method->method_code === 'cod')
                                            {{ $method->settings['description'] ?? '' }}
                                        @elseif($method->method_code === 'bank_transfer')
                                            {{ $method->account_name ?: __('admin.payment_methods.not_configured') }}
                                        @else
                                            {{ $method->account_name ?: __('admin.payment_methods.not_configured') }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    @if($method->type === 'connected')
                                        <span class="badge bg-success-subtle text-success fw-semibold">{{ __('admin.payment_methods.api_connected') }}</span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary fw-semibold">{{ __('admin.payment_methods.self_delivery') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input js-status-toggle" type="checkbox" role="switch"
                                            data-url="{{ route('admin.payment-methods.toggle-status', $method) }}"
                                            @checked($method->status === 'active')>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ __('admin.payment_methods.edit_title') }}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.payment-methods.settings', $method) }}">
                                                    <i class="ti ti-settings fs-4"></i> {{ __('admin.payment_methods.setup_connection') }}
                                                </a>
                                            </li>
                                            @if($method->type === 'custom' && $method->method_code !== 'cod' && $method->method_code !== 'bank_transfer')
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.payment-methods.edit', $method) }}">
                                                        <i class="ti ti-edit fs-4"></i> {{ __('admin.payment_methods.edit') }}
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('admin.payment-methods.destroy', $method) }}" method="POST" class="js-delete-form d-block">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2">
                                                            <i class="ti ti-trash fs-4"></i> {{ __('admin.payment_methods.delete') }}
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
                                    <iconify-icon icon="solar:card-recive-line-duotone" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                    <p class="mb-0">{{ __('admin.payment_methods.unconfigured_warning') }}</p>
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
                            title: data.message || '{{ __('admin.payment_methods.updated') }}'
                        });
                    } else {
                        this.checked = !isChecked; // revert
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __('admin.payment_methods.notification') }}',
                            text: data.message || '{{ __('admin.error') }}'
                        });
                    }
                })
                .catch(error => {
                    this.checked = !isChecked; // revert
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __('admin.payment_methods.error_title') }}',
                        text: '{{ __('admin.payment_methods.error_text') }}'
                    });
                });
            });
        });
    });
</script>
@endpush
