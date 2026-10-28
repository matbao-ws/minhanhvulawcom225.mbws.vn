@php
    $translationKey = "admin.features.{$feature->feature_code}";
    $displayName = Lang::has($translationKey) ? __($translationKey) : Str::headline($feature->feature_code);
    // Features this one needs on to have any effect (config features.requires).
    $requires = (array) config("features.requires.{$feature->feature_code}", []);
    $requiresLabels = collect($requires)->map(fn ($code) => Lang::has("admin.features.{$code}") ? __("admin.features.{$code}") : Str::headline($code))->implode(', ');
    
    $iconMap = [
        'catalog' => 'ti ti-package',
        'cart' => 'ti ti-shopping-cart',
        'cod_order' => 'ti ti-cash',
        'online_payment' => 'ti ti-credit-card',
        'voucher' => 'ti ti-ticket',
        'review' => 'ti ti-message-report',
        'shipping' => 'ti ti-truck-delivery',
        'banner' => 'ti ti-photo',
        'menu' => 'ti ti-menu-2',
        'zalo_oa' => 'ti ti-bell-ringing',
        'multi_admin' => 'ti ti-users-group',
        'cms_page' => 'ti ti-article',
        'product_variant' => 'ti ti-versions',
        'payment_vnpay' => 'ti ti-qrcode',
        'multilingual' => 'ti ti-language',
        'analytics' => 'ti ti-chart-line',
        'ai_seo' => 'ti ti-sparkles',
        'ai_research' => 'ti ti-target-arrow',
    ];
    $icon = $iconMap[$feature->feature_code] ?? 'ti ti-apps';
    
    $colorMap = [
        'catalog' => 'bg-primary-subtle text-primary',
        'cart' => 'bg-info-subtle text-info',
        'cod_order' => 'bg-warning-subtle text-warning',
        'online_payment' => 'bg-success-subtle text-success',
        'voucher' => 'bg-danger-subtle text-danger',
        'review' => 'bg-secondary-subtle text-secondary',
        'shipping' => 'bg-info-subtle text-info',
        'banner' => 'bg-primary-subtle text-primary',
        'menu' => 'bg-info-subtle text-info',
        'zalo_oa' => 'bg-warning-subtle text-warning',
        'multi_admin' => 'bg-success-subtle text-success',
        'cms_page' => 'bg-secondary-subtle text-secondary',
        'product_variant' => 'bg-primary-subtle text-primary',
        'payment_vnpay' => 'bg-success-subtle text-success',
        'multilingual' => 'bg-info-subtle text-info',
        'analytics' => 'bg-success-subtle text-success',
        'ai_seo' => 'bg-primary-subtle text-primary',
        'ai_research' => 'bg-danger-subtle text-danger',
    ];
    $colorClass = $colorMap[$feature->feature_code] ?? 'bg-light text-dark';
@endphp

<div data-tour="features.card" class="col-md-6 col-lg-4 mb-4" data-feature-card data-feature-group="{{ $groupKey }}" data-feature-requires="{{ implode(',', $requires) }}">
    <div class="card h-100 shadow-sm border border-light-subtle rounded-4 overflow-hidden position-relative feature-card">
        <!-- Top Status Badge -->
        <span class="position-absolute top-0 end-0 {{ $feature->is_enabled ? 'bg-success text-white' : 'bg-secondary-subtle text-muted' }} px-3 py-1 small fw-semibold d-flex align-items-center gap-1" 
              id="badge-{{ $feature->feature_code }}"
              style="border-bottom-left-radius: 12px;">
            <i class="{{ $feature->is_enabled ? 'ti ti-circle-check' : 'ti ti-circle-x' }}" id="badge-icon-{{ $feature->feature_code }}"></i>
            <span id="badge-text-{{ $feature->feature_code }}">{{ $feature->is_enabled ? __('admin.features.badge_on') : __('admin.features.badge_off') }}</span>
        </span>

        <div class="card-body p-4 d-flex flex-column justify-content-between">
            <div>
                <!-- Icon representation -->
                <div class="mb-3">
                    <div class="{{ $colorClass }} p-3 rounded-3 d-inline-block">
                        <i class="{{ $icon }} fs-7"></i>
                    </div>
                </div>

                <h5 class="fw-bold text-dark mb-2">{{ $displayName }}</h5>
                @if($feature->package_enabled !== null)
                    <div data-tour="features.source" class="d-flex align-items-center gap-2 mb-2" data-feature-source="{{ $feature->feature_code }}">
                        <span class="badge bg-warning-subtle text-warning {{ $feature->is_overridden ? '' : 'd-none' }}" data-source-override>{{ __('admin.package.badge_override') }}</span>
                        <button type="submit" form="feature-reset-form" name="feature_code" value="{{ $feature->feature_code }}" class="btn btn-link btn-sm p-0 {{ $feature->is_overridden ? '' : 'd-none' }}" data-source-override>{{ __('admin.package.reset_one') }}</button>
                        <span class="badge bg-primary-subtle text-primary {{ $feature->is_overridden ? 'd-none' : '' }}" data-source-package>{{ __('admin.package.badge_package') }}</span>
                    </div>
                @endif
                <p class="text-muted small mb-{{ $requires ? '2' : '4' }}" style="min-height: 48px;">
                    {{ Lang::has("admin.features.subtitle_for_{$feature->feature_code}") ? __("admin.features.subtitle_for_{$feature->feature_code}") : '' }}
                </p>
                @if($requires)
                    <p class="small mb-4 feature-requires">
                        <i class="ti ti-link me-1" aria-hidden="true"></i>{{ __('admin.features.requires', ['features' => $requiresLabels]) }}
                        <span class="d-block text-warning fw-semibold d-none" data-requires-off>
                            <i class="ti ti-alert-triangle me-1" aria-hidden="true"></i>{{ __('admin.features.requires_off') }}
                        </span>
                    </p>
                @endif
            </div>

            <div>
                <div class="d-flex align-items-center justify-content-between border-top pt-3">
                    <div>
                        <span class="text-muted small block">{{ __('admin.features.status') }}</span>
                        <h6 class="fw-bold {{ $feature->is_enabled ? 'text-success' : 'text-danger' }} mb-0 fs-3" id="status-text-{{ $feature->feature_code }}">
                            {{ $feature->is_enabled ? __('admin.features.status_on') : __('admin.features.status_off') }}
                        </h6>
                    </div>
                    
                    <div class="form-check form-switch fs-5 mb-0">
                        <input type="hidden" name="features[{{ $feature->feature_code }}]" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="features[{{ $feature->feature_code }}]" value="1"
                               id="switch-{{ $feature->feature_code }}"
                               data-feature-switch
                               data-feature-group="{{ $groupKey }}"
                               onchange="toggleFeatureState('{{ $feature->feature_code }}', this.checked)"
                               @checked($feature->is_enabled)>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
