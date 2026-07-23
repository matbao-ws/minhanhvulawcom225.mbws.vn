@php
    $contentLanguages = app(\App\Services\LanguageRegistry::class)->active();
    $defaultContentLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
    $cancelUrl = route('admin.vouchers.index');
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="form-label" for="code">{{ __('admin.vouchers.fields.code') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase" id="code" name="code" value="{{ old('code', $voucher->code) }}" placeholder="{{ __('admin.vouchers.placeholders.code') }}" required>
            </div>
            
            <div class="col-12 mb-4">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    @foreach($contentLanguages as $language)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link d-flex align-items-center gap-2 @if($language->code === $defaultContentLocale) active @endif"
                               data-bs-toggle="tab"
                               href="#voucher-language-{{ $language->code }}"
                               role="tab"
                               aria-selected="{{ $language->code === $defaultContentLocale ? 'true' : 'false' }}">
                                <span><i class="ti ti-language fs-4"></i></span>
                                <span>{{ $language->native_name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content">@foreach($contentLanguages as $language)@php($code = $language->code)<div id="voucher-language-{{ $code }}" class="tab-pane fade @if($code === $defaultContentLocale) show active @endif">@if($code !== $defaultContentLocale)<div class="text-end mb-2"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}">Dịch {{ strtoupper($defaultContentLocale) }} → {{ strtoupper($code) }}</button></div>@endif<div class="mb-3"><label class="form-label">{{ __('admin.vouchers.fields.name') }} @if($code === $defaultContentLocale)<span class="text-danger">*</span>@endif</label><input class="form-control" id="voucher_name_{{ $code }}" name="name[{{ $code }}]" value="{{ old("name.$code", $voucher->getTranslation('name', $code, false)) }}" data-i18n-locale="{{ $code }}" data-i18n-field="name" @required($code === $defaultContentLocale)></div><div><label class="form-label">{{ __('admin.vouchers.fields.description') }}</label><textarea class="form-control" id="voucher_description_{{ $code }}" name="description[{{ $code }}]" rows="3" data-i18n-locale="{{ $code }}" data-i18n-field="description">{{ old("description.$code", $voucher->getTranslation('description', $code, false)) }}</textarea></div></div>@endforeach</div>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="type">{{ __('admin.vouchers.fields.type') }} <span class="text-danger">*</span></label>
                <select class="form-select" id="type" name="type" required>
                    <option value="percentage" @selected(old('type', $voucher->type) === 'percentage')>{{ __('admin.vouchers.fields.percentage') }}</option>
                    <option value="fixed" @selected(old('type', $voucher->type) === 'fixed')>{{ __('admin.vouchers.fields.fixed') }}</option>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="value">{{ __('admin.vouchers.fields.value') }} <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="value" name="value" value="{{ old('value', $voucher->value) }}" placeholder="{{ __('admin.vouchers.placeholders.value') }}" required min="0">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="min_order_amount">{{ __('admin.vouchers.fields.min_order_amount') }}</label>
                <input type="number" step="0.01" class="form-control" id="min_order_amount" name="min_order_amount" value="{{ old('min_order_amount', $voucher->min_order_amount ?? 0) }}" placeholder="0" min="0">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="max_discount_amount">{{ __('admin.vouchers.fields.max_discount_amount') }}</label>
                <input type="number" step="0.01" class="form-control" id="max_discount_amount" name="max_discount_amount" value="{{ old('max_discount_amount', $voucher->max_discount_amount) }}" placeholder="{{ __('admin.vouchers.placeholders.max_discount') }}" min="0">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="quantity">{{ __('admin.vouchers.fields.quantity') }}</label>
                <input type="number" class="form-control" id="quantity" name="quantity" value="{{ old('quantity', $voucher->quantity) }}" placeholder="{{ __('admin.vouchers.placeholders.quantity') }}" min="0">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="per_user_limit">{{ __('admin.vouchers.fields.per_user_limit') }}</label>
                <input type="number" class="form-control" id="per_user_limit" name="per_user_limit" value="{{ old('per_user_limit', $voucher->per_user_limit) }}" placeholder="{{ __('admin.vouchers.placeholders.per_user_limit') }}" min="1">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="is_active">{{ __('admin.vouchers.fields.is_active') }}</label>
                <div class="form-check form-switch mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked((bool) old('is_active', $voucher->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('admin.vouchers.statuses.active') }}</label>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="start_date">{{ __('admin.vouchers.fields.start_date') }}</label>
                <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="{{ old('start_date', $voucher->start_date ? $voucher->start_date->format('Y-m-d\TH:i') : '') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label" for="end_date">{{ __('admin.vouchers.fields.end_date') }}</label>
                <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="{{ old('end_date', $voucher->end_date ? $voucher->end_date->format('Y-m-d\TH:i') : '') }}">
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])
@include('admin.shared.translation-assets')
