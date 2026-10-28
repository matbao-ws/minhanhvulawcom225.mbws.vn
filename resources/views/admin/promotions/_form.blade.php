@php
    $selectedProductIds = collect(old('target_product_ids', $promotion->relationLoaded('targets') ? $promotion->targets->whereNull('product_variant_id')->pluck('product_id')->all() : []))->map(fn ($id) => (int) $id)->all();
    $selectedVariantIds = collect(old('target_variant_ids', $promotion->relationLoaded('targets') ? $promotion->targets->whereNotNull('product_variant_id')->pluck('product_variant_id')->all() : []))->map(fn ($id) => (int) $id)->all();
    $savedTargetLimits = $promotion->relationLoaded('targets')
        ? $promotion->targets->pluck('quantity_limit')->filter()->unique()->values()
        : collect();
    $targetQuantityLimit = old('target_quantity_limit', $savedTargetLimits->count() === 1 ? $savedTargetLimits->first() : null);
    $contentLanguages = app(\App\Services\LanguageRegistry::class)->active();
    $defaultContentLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
@endphp
<div class="card"><div class="card-body"><div class="row">
    <div class="col-12 mb-3">
        <ul data-tour="promotion-form.languages" class="nav nav-tabs mb-4" role="tablist">
            @foreach($contentLanguages as $language)
                <li class="nav-item" role="presentation">
                    <a class="nav-link d-flex align-items-center gap-2 @if($language->code === $defaultContentLocale) active @endif"
                       data-bs-toggle="tab"
                       href="#promotion-language-{{ $language->code }}"
                       role="tab"
                       aria-selected="{{ $language->code === $defaultContentLocale ? 'true' : 'false' }}">
                        <span><i class="ti ti-language fs-4"></i></span>
                        <span>{{ $language->native_name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="tab-content">@foreach($contentLanguages as $language)@php($code = $language->code)<div id="promotion-language-{{ $code }}" class="tab-pane fade @if($code === $defaultContentLocale) show active @endif">@if($code !== $defaultContentLocale)<div class="text-end mb-2"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}">{{ __('admin.translation.translate_button', ['from' => strtoupper($defaultContentLocale), 'to' => strtoupper($code)]) }}</button></div>@endif<div class="row"><div class="col-md-8 mb-3"><label class="form-label">{{ __('admin.vouchers.fields.name') }} @if($code === $defaultContentLocale)*@endif</label><input class="form-control" id="promotion_name_{{ $code }}" name="name[{{ $code }}]" value="{{ old("name.$code", $promotion->getTranslation('name', $code, false)) }}" data-i18n-locale="{{ $code }}" data-i18n-field="name" @required($code === $defaultContentLocale)></div><div class="col-12 mb-3"><label class="form-label">{{ __('catalog.fields.description') }}</label><textarea class="form-control" id="promotion_description_{{ $code }}" name="description[{{ $code }}]" rows="2" data-i18n-locale="{{ $code }}" data-i18n-field="description">{{ old("description.$code", $promotion->getTranslation('description', $code, false)) }}</textarea></div></div></div>@endforeach</div>
    </div>
    <div data-tour="promotion-form.kind" class="col-md-4 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.kind') }} *</label><select class="form-select" name="kind"><option value="automatic" @selected(old('kind', $promotion->kind) === 'automatic')>{{ __('catalog.promotions.kind_automatic') }}</option><option value="flash_sale" @selected(old('kind', $promotion->kind) === 'flash_sale')>Flash Sale</option></select></div>
    <div data-tour="promotion-form.discount" class="col-md-4 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.discount_type') }} *</label><select class="form-select" name="discount_type"><option value="percentage" @selected(old('discount_type', $promotion->discount_type) === 'percentage')>{{ __('catalog.promotions.discount_types.percentage') }}</option><option value="fixed_amount" @selected(old('discount_type', $promotion->discount_type) === 'fixed_amount')>{{ __('catalog.promotions.discount_types.fixed_amount') }}</option><option value="fixed_price" @selected(old('discount_type', $promotion->discount_type) === 'fixed_price')>{{ __('catalog.promotions.discount_types.fixed_price') }}</option></select></div>
    <div class="col-md-4 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.value') }} *</label><input type="number" step="0.01" min="0" class="form-control" name="value" value="{{ old('value', $promotion->value) }}" required></div>
    <div data-tour="promotion-form.priority" class="col-md-4 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.priority') }}</label><input type="number" min="0" class="form-control" name="priority" value="{{ old('priority', $promotion->priority) }}"></div>
    <div class="col-md-4 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.min_quantity') }}</label><input type="number" min="1" class="form-control" name="min_quantity" value="{{ old('min_quantity', $promotion->min_quantity ?? 1) }}"></div>
    <div data-tour="promotion-form.limits" class="col-md-4 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.quantity_limit') }}</label><input type="number" min="1" class="form-control" name="quantity_limit" value="{{ old('quantity_limit', $promotion->quantity_limit) }}"></div>
    <div class="col-md-4 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.target_quantity_limit') }}</label><input type="number" min="1" class="form-control" name="target_quantity_limit" value="{{ $targetQuantityLimit }}" placeholder="{{ __('catalog.promotions.target_quantity_placeholder') }}"><div class="form-text">{{ __('catalog.promotions.target_quantity_help') }}</div></div>
    <div data-tour="promotion-form.schedule" class="col-md-6 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.start_at') }}</label><input type="datetime-local" class="form-control" name="start_at" value="{{ old('start_at', $promotion->start_at?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-6 mb-3"><label class="form-label">{{ __('catalog.promotions.fields.end_at') }}</label><input type="datetime-local" class="form-control" name="end_at" value="{{ old('end_at', $promotion->end_at?->format('Y-m-d\TH:i')) }}"></div>
    <div data-tour="promotion-form.scope" class="col-12"><hr><h5 class="fw-bold mb-3">{{ __('catalog.promotions.scope_title') }}</h5>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label" for="applies_to_select">{{ __('catalog.promotions.fields.applies_to') }}</label>
                <select class="form-select" id="applies_to_select" name="applies_to">
                    <option value="selected" @selected(old('applies_to', $promotion->applies_to) === 'selected')>{{ __('catalog.promotions.applies_selected') }}</option>
                    <option value="all_products" @selected(old('applies_to', $promotion->applies_to) === 'all_products')>{{ __('catalog.promotions.all_products') }}</option>
                </select>
            </div>
            <div class="col-md-4 mb-3" id="target_products_col">
                <label class="form-label" for="target_product_ids">{{ __('catalog.products.title') }}</label>
                <select class="form-control select2-multiple" id="target_product_ids" name="target_product_ids[]" multiple="multiple" data-placeholder="{{ __('catalog.promotions.products_placeholder') }}">
                    @foreach($products as $productOption)
                        <option value="{{ $productOption->id }}" @selected(in_array($productOption->id, $selectedProductIds, true))>{{ $productOption->name }}{{ $productOption->sku ? ' — '.$productOption->sku : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3" id="target_variants_col">
                <label class="form-label" for="target_variant_ids">{{ __('catalog.promotions.fields.target_variants') }}</label>
                <select class="form-control select2-multiple" id="target_variant_ids" name="target_variant_ids[]" multiple="multiple" data-placeholder="{{ __('catalog.promotions.variants_placeholder') }}">
                    @foreach($variants as $variantOption)
                        <option value="{{ $variantOption->id }}" @selected(in_array($variantOption->id, $selectedVariantIds, true))>{{ $variantOption->product?->name ? $variantOption->product->name . ' — ' : '' }}{{ $variantOption->name }}{{ $variantOption->sku ? ' ('.$variantOption->sku.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="text-muted small">{{ __('catalog.promotions.scope_help') }}</p>
    </div>
    <div data-tour="promotion-form.active" class="col-12 d-flex gap-4"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $promotion->is_active))><label class="form-check-label" for="is_active">{{ __('catalog.fields.is_active') }}</label></div><span class="text-muted small align-self-center">{{ __('catalog.promotions.stacking_help') }}</span></div>
</div></div></div>
@include('admin.shared.form-actions', ['cancelUrl' => route('admin.promotions.index')])
@include('admin.shared.translation-assets')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/select2/dist/css/select2.min.css') }}">
    <style>
        .select2-container--default .select2-selection--multiple {
            min-height: 42px !important;
            border: 1px solid #dfe5ef !important;
            border-radius: 7px !important;
            padding: 4px 8px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #ecf2ff !important;
            border: 1px solid #dbe7ff !important;
            color: #2a3547 !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            border-radius: 5px !important;
            padding: 4px 10px !important;
            margin-top: 4px !important;
            margin-right: 6px !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('admin-assets/libs/select2/dist/js/select2.full.min.js') }}"></script>
    <script>
        (function() {
            function initPromotionsForm() {
                if (window.jQuery && jQuery().select2) {
                    $('.select2-multiple').select2({
                        placeholder: function() {
                            return $(this).data('placeholder') || @json(__('catalog.promotions.select_placeholder'));
                        },
                        allowClear: true,
                        width: '100%'
                    });
                }

                const appliesToSelect = document.getElementById('applies_to_select');
                const targetProductsCol = document.getElementById('target_products_col');
                const targetVariantsCol = document.getElementById('target_variants_col');

                function toggleScopeFields() {
                    if (!appliesToSelect) return;
                    const isSelected = appliesToSelect.value === 'selected';
                    if (targetProductsCol) targetProductsCol.style.display = isSelected ? 'block' : 'none';
                    if (targetVariantsCol) targetVariantsCol.style.display = isSelected ? 'block' : 'none';
                }

                if (appliesToSelect) {
                    appliesToSelect.removeEventListener('change', toggleScopeFields);
                    appliesToSelect.addEventListener('change', toggleScopeFields);
                    toggleScopeFields();
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPromotionsForm);
            } else {
                initPromotionsForm();
            }
        })();
    </script>
@endpush


