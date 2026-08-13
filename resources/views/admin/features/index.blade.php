@extends('admin.layouts.app')

@section('title', __('admin.features.title'))

@section('content')
    <!-- Header Card -->
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
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
        $groupMeta = [
            'ecommerce' => [
                'title' => __('admin.features.website_ecommerce'),
                'description' => __('admin.features.website_ecommerce_description'),
                'icon' => 'ti ti-shopping-cart',
            ],
            'non_ecommerce' => [
                'title' => __('admin.features.website_non_ecommerce'),
                'description' => __('admin.features.website_non_ecommerce_description'),
                'icon' => 'ti ti-layout-dashboard',
            ],
        ];
    @endphp

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <ul class="nav nav-tabs feature-tabs px-3 pt-3" id="featureTypeTabs" role="tablist">
                @foreach($groupMeta as $groupKey => $meta)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link d-flex align-items-center gap-2 @if($loop->first) active @endif"
                                id="feature-tab-{{ $groupKey }}"
                                data-bs-toggle="tab"
                                data-bs-target="#feature-panel-{{ $groupKey }}"
                                type="button"
                                role="tab"
                                aria-controls="feature-panel-{{ $groupKey }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            <i class="{{ $meta['icon'] }}" aria-hidden="true"></i>
                            {{ $meta['title'] }}
                            <span class="badge bg-light text-dark">{{ $featureGroups->get($groupKey, collect())->count() }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content p-3 p-lg-4" id="featureTypeTabsContent">
                @foreach($groupMeta as $groupKey => $meta)
                    @php
                        $groupFeatures = $featureGroups->get($groupKey, collect());
                        $enabledCount = $groupFeatures->where('is_enabled', true)->count();
                        $allEnabled = $groupFeatures->isNotEmpty() && $enabledCount === $groupFeatures->count();
                        $partiallyEnabled = $enabledCount > 0 && !$allEnabled;
                    @endphp
                    <div class="tab-pane fade @if($loop->first) show active @endif"
                         id="feature-panel-{{ $groupKey }}"
                         role="tabpanel"
                         aria-labelledby="feature-tab-{{ $groupKey }}"
                         tabindex="0">
                        <div class="feature-group-toolbar d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $meta['title'] }}</h5>
                                <p class="text-muted mb-0">{{ $meta['description'] }}</p>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge rounded-pill {{ $allEnabled ? 'bg-success-subtle text-success' : ($partiallyEnabled ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary') }}"
                                      id="group-status-{{ $groupKey }}"
                                      data-group-status>
                                    {{ $allEnabled ? __('admin.features.group_all_enabled') : ($partiallyEnabled ? __('admin.features.group_partially_enabled') : __('admin.features.group_all_disabled')) }}
                                </span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           id="group-switch-{{ $groupKey }}"
                                           data-feature-group-switch="{{ $groupKey }}"
                                           onchange="toggleFeatureGroup('{{ $groupKey }}', this.checked)"
                                           @checked($allEnabled)>
                                    <label class="form-check-label fw-semibold" for="group-switch-{{ $groupKey }}" id="group-action-{{ $groupKey }}">
                                        {{ $allEnabled ? __('admin.features.disable_group') : __('admin.features.enable_group') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            @forelse($groupFeatures as $feature)
                                @include('admin.features._feature_card', ['feature' => $feature, 'groupKey' => $groupKey])
                            @empty
                                <div class="col-12">
                                    <div class="alert alert-light border mb-0">Chưa có tính năng trong nhóm này.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
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
    .feature-tabs .nav-link {
        color: #5a6a85;
        font-weight: 700;
        padding: 0.9rem 1.1rem;
    }
    .feature-tabs .nav-link.active {
        color: #5d87ff;
    }
    .feature-group-toolbar {
        background: #f8fafc;
        border: 1px solid #e5eaf2;
        border-radius: 14px;
        padding: 1rem 1.25rem;
    }
</style>
@endpush

@push('scripts')
<script>
    const featureToggleUrl = @json(route('admin.features.toggle'));
    const featureGroupToggleUrl = @json(route('admin.features.group-toggle'));
    const featureCsrfToken = @json(csrf_token());
    const featureMessages = {
        enabled: @json(__('admin.features.group_all_enabled')),
        partial: @json(__('admin.features.group_partially_enabled')),
        disabled: @json(__('admin.features.group_all_disabled')),
        enableGroup: @json(__('admin.features.enable_group')),
        disableGroup: @json(__('admin.features.disable_group')),
        groupError: @json(__('admin.features.group_update_error')),
    };

    function setFeatureUi(code, checked) {
        const badge = document.getElementById('badge-' + code);
        const badgeIcon = document.getElementById('badge-icon-' + code);
        const badgeText = document.getElementById('badge-text-' + code);
        const statusText = document.getElementById('status-text-' + code);
        if (!badge || !badgeIcon || !badgeText || !statusText) return;

        if (checked) {
            badge.className = 'position-absolute top-0 end-0 bg-success text-white px-3 py-1 small fw-semibold d-flex align-items-center gap-1';
            badgeIcon.className = 'ti ti-circle-check';
            badgeText.innerText = 'Đã kích hoạt';
            statusText.innerText = 'Hoạt động';
            statusText.className = 'fw-bold text-success mb-0 fs-3';
        } else {
            badge.className = 'position-absolute top-0 end-0 bg-secondary-subtle text-muted px-3 py-1 small fw-semibold d-flex align-items-center gap-1';
            badgeIcon.className = 'ti ti-circle-x';
            badgeText.innerText = 'Đang tắt';
            statusText.innerText = 'Tạm dừng';
            statusText.className = 'fw-bold text-danger mb-0 fs-3';
        }
        badge.style.borderBottomLeftRadius = '12px';
    }

    function refreshGroupState(group) {
        const switches = Array.from(document.querySelectorAll('[data-feature-switch][data-feature-group="' + group + '"]'));
        const groupSwitch = document.querySelector('[data-feature-group-switch="' + group + '"]');
        const status = document.getElementById('group-status-' + group);
        const action = document.getElementById('group-action-' + group);
        if (!groupSwitch || !status || !action) return;

        const enabledCount = switches.filter(input => input.checked).length;
        const allEnabled = switches.length > 0 && enabledCount === switches.length;
        const partiallyEnabled = enabledCount > 0 && !allEnabled;

        groupSwitch.checked = allEnabled;
        groupSwitch.indeterminate = partiallyEnabled;
        action.innerText = allEnabled ? featureMessages.disableGroup : featureMessages.enableGroup;
        status.innerText = allEnabled ? featureMessages.enabled : (partiallyEnabled ? featureMessages.partial : featureMessages.disabled);
        status.className = 'badge rounded-pill ' + (allEnabled
            ? 'bg-success-subtle text-success'
            : (partiallyEnabled ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary'));
    }

    async function postFeatureState(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': featureCsrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Lỗi cập nhật tính năng.');
        }
        return data;
    }

    function toggleFeatureState(code, checked) {
        const input = document.getElementById('switch-' + code);
        const group = input?.dataset.featureGroup;
        const originalChecked = !checked;
        if (!input) return;

        input.disabled = true;
        setFeatureUi(code, checked);
        if (group) refreshGroupState(group);

        postFeatureState(featureToggleUrl, {
            feature_code: code,
            is_enabled: checked
        })
        .catch(err => {
            console.error(err);
            window.alert(err.message);
            input.checked = originalChecked;
            setFeatureUi(code, originalChecked);
            if (group) refreshGroupState(group);
        })
        .finally(() => {
            input.disabled = false;
        });
    }

    function toggleFeatureGroup(group, checked) {
        const groupSwitch = document.querySelector('[data-feature-group-switch="' + group + '"]');
        const switches = Array.from(document.querySelectorAll('[data-feature-switch][data-feature-group="' + group + '"]'));
        const originalStates = switches.map(input => ({ input, checked: input.checked }));

        groupSwitch.disabled = true;
        groupSwitch.indeterminate = false;
        switches.forEach(input => {
            input.disabled = true;
            input.checked = checked;
            setFeatureUi(input.id.replace('switch-', ''), checked);
        });
        refreshGroupState(group);

        postFeatureState(featureGroupToggleUrl, {
            group: group,
            is_enabled: checked
        })
        .catch(err => {
            console.error(err);
            window.alert(err.message || featureMessages.groupError);
            originalStates.forEach(item => {
                item.input.checked = item.checked;
                setFeatureUi(item.input.id.replace('switch-', ''), item.checked);
            });
            refreshGroupState(group);
        })
        .finally(() => {
            groupSwitch.disabled = false;
            switches.forEach(input => {
                input.disabled = false;
            });
        });
    }

    document.querySelectorAll('[data-feature-group-switch]').forEach(input => {
        refreshGroupState(input.dataset.featureGroupSwitch);
    });
</script>
@endpush
