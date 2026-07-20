@extends('admin.layouts.app')

@section('title', __('admin.features.title'))

@section('content')
    <!-- Header Card -->
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(135deg, #141e30 0%, #243b55 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.features.title') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.features.title') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $excludeKeys = ['max_products', 'max_admin_users'];
        $displayFeatures = $features->filter(fn($f) => !in_array($f->feature_code, $excludeKeys));
    @endphp

    <div class="row">
        @foreach($displayFeatures as $feature)
            @include('admin.features._feature_card', ['feature' => $feature])
        @endforeach
    </div>
@endsection

@push('styles')
<style>
    .feature-card {
        transition: all 0.25s ease-in-out;
    }
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
    }
</style>
@endpush

@push('scripts')
<script>
    function toggleFeatureState(code, checked) {
        const badge = document.getElementById('badge-' + code);
        const badgeIcon = document.getElementById('badge-icon-' + code);
        const badgeText = document.getElementById('badge-text-' + code);
        const statusText = document.getElementById('status-text-' + code);
        
        const originalChecked = !checked;

        // Perform dynamic UI updates immediately for optimistic response
        if (checked) {
            badge.className = 'position-absolute top-0 end-0 bg-success text-white px-3 py-1 small fw-semibold d-flex align-items-center gap-1';
            badge.style.borderBottomLeftRadius = '12px';
            badgeIcon.className = 'ti ti-circle-check';
            badgeText.innerText = 'Đã kích hoạt';
            statusText.innerText = 'Hoạt động';
            statusText.className = 'fw-bold text-success mb-0 fs-3';
        } else {
            badge.className = 'position-absolute top-0 end-0 bg-secondary-subtle text-muted px-3 py-1 small fw-semibold d-flex align-items-center gap-1';
            badge.style.borderBottomLeftRadius = '12px';
            badgeIcon.className = 'ti ti-circle-x';
            badgeText.innerText = 'Đang tắt';
            statusText.innerText = 'Tạm dừng';
            statusText.className = 'fw-bold text-danger mb-0 fs-3';
        }

        // Perform AJAX request to save in DB instantly
        fetch("{{ route('admin.features.toggle') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                feature_code: code,
                is_enabled: checked ? 1 : 0
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Lỗi cập nhật tính năng.');
                // Revert state
                document.getElementById('switch-' + code).checked = originalChecked;
                toggleFeatureState(code, originalChecked);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Có lỗi xảy ra khi kết nối máy chủ.');
            // Revert state
            document.getElementById('switch-' + code).checked = originalChecked;
            toggleFeatureState(code, originalChecked);
        });
    }
</script>
@endpush
