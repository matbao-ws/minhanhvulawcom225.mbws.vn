@extends('admin.layouts.app')

@section('title', __('catalog.options.title'))

@section('content')
    @php
        $contentLanguages = app(\App\Services\LanguageRegistry::class)->active();
        $defaultContentLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
    @endphp
    <x-admin.page-header :title="__('catalog.options.page_title', ['name' => $product->name])" :subtitle="__('catalog.options.subtitle')">
        <x-slot:actions>
            <x-admin.action-button data-tour="product-options.back" :href="route('admin.products.show', $product)" variant="light">{{ __('catalog.options.back') }}</x-admin.action-button>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.products.options.update', $product) }}" class="admin-form-with-sticky-actions">
        @csrf
        @method('PUT')
        <div data-tour="product-options.languages" class="card"><div class="card-body d-flex align-items-center justify-content-between gap-3"><div class="nav nav-pills content-language-tabs">@foreach($contentLanguages as $language)<button type="button" class="nav-link option-locale-switch @if($language->code === $defaultContentLocale) active @endif" data-option-locale="{{ $language->code }}">{{ $language->native_name }}</button>@endforeach</div><div>@foreach($contentLanguages as $language)@if($language->code !== $defaultContentLocale)<button type="button" class="btn btn-sm btn-outline-primary js-translate-locale option-translate-button d-none" data-option-translation-target="{{ $language->code }}" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $language->code }}">{{ __('admin.translation.translate_button', ['from' => strtoupper($defaultContentLocale), 'to' => strtoupper($language->code)]) }}</button>@endif @endforeach</div></div></div>
        <div data-tour="product-options.groups" id="option-groups" class="d-flex flex-column gap-3">
            @forelse($product->optionGroups as $groupIndex => $group)
                <div class="card option-group" data-group-index="{{ $groupIndex }}">
                    <div class="card-body">
                        <input type="hidden" name="groups[{{ $groupIndex }}][id]" value="{{ $group->id }}">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('catalog.variant_builder.group_name') }}</label>
                                @foreach($contentLanguages as $language)@php($code = $language->code)<div class="option-locale-field option-locale-{{ $code }} @if($code !== $defaultContentLocale) d-none @endif"><input class="form-control" name="groups[{{ $groupIndex }}][name][{{ $code }}]" value="{{ old("groups.$groupIndex.name.$code", $group->getTranslation('name', $code, false)) }}" data-i18n-locale="{{ $code }}" data-i18n-field="group_{{ $groupIndex }}_name" @required($code === $defaultContentLocale)></div>@endforeach
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('catalog.variant_builder.display_type') }}</label>
                                <select class="form-select" name="groups[{{ $groupIndex }}][display_type]">
                                    @foreach(['select' => __('catalog.variant_builder.display_select'), 'color' => __('catalog.fields.option_color'), 'image' => __('catalog.variant_builder.display_image')] as $type => $label)
                                        <option value="{{ $type }}" @selected(old("groups.$groupIndex.display_type", $group->display_type) === $type)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-group">{{ __('catalog.variant_builder.remove_group') }}</button></div>
                        </div>
                        <div class="values d-flex flex-column gap-2">
                            @foreach($group->values as $valueIndex => $value)
                                @include('admin.catalog.products.partials.option-value-row', ['groupIndex' => $groupIndex, 'valueIndex' => $valueIndex, 'value' => $value])
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3 add-value">{{ __('catalog.variant_builder.add_value') }}</button>
                    </div>
                </div>
            @empty
                <div class="card option-group" data-group-index="0">
                    <div class="card-body">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-6"><label class="form-label">{{ __('catalog.variant_builder.group_name') }}</label>@foreach($contentLanguages as $language)@php($code = $language->code)<div class="option-locale-field option-locale-{{ $code }} @if($code !== $defaultContentLocale) d-none @endif"><input class="form-control" name="groups[0][name][{{ $code }}]" value="{{ $code === $defaultContentLocale ? __('catalog.fields.option_color') : '' }}" data-i18n-locale="{{ $code }}" data-i18n-field="group_0_name" @required($code === $defaultContentLocale)></div>@endforeach</div>
                            <div class="col-md-4"><label class="form-label">{{ __('catalog.variant_builder.display_type') }}</label><select class="form-select" name="groups[0][display_type]"><option value="select">{{ __('catalog.variant_builder.display_select') }}</option><option value="color">{{ __('catalog.fields.option_color') }}</option><option value="image">{{ __('catalog.variant_builder.display_image') }}</option></select></div>
                            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-group">{{ __('catalog.variant_builder.remove_group') }}</button></div>
                        </div>
                        <div class="values d-flex flex-column gap-2">
                            @include('admin.catalog.products.partials.option-value-row', ['groupIndex' => 0, 'valueIndex' => 0, 'value' => null])
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3 add-value">{{ __('catalog.variant_builder.add_value') }}</button>
                    </div>
                </div>
            @endforelse
        </div>
        <button data-tour="product-options.add-group" type="button" id="add-group" class="btn btn-outline-primary mt-3">{{ __('catalog.variant_builder.add_group') }}</button>
        @include('admin.shared.form-actions', ['cancelUrl' => route('admin.products.show', $product)])
    </form>

    <template id="value-template">
        <div class="row g-2 value-row align-items-end">
            <div class="col-md-4"><label class="form-label">{{ __('catalog.fields.option_value') }}</label>@foreach($contentLanguages as $language)@php($code = $language->code)<div class="option-locale-field option-locale-{{ $code }} @if($code !== $defaultContentLocale) d-none @endif"><input class="form-control" data-name="groups[__GROUP__][values][__VALUE__][label][{{ $code }}]" data-locale="{{ $code }}" data-field-template="group___GROUP___value___VALUE___label" @required($code === $defaultContentLocale)></div>@endforeach</div>
            <div class="col-md-2"><label class="form-label">{{ __('catalog.variant_builder.color_hex') }}</label><input class="form-control" data-name="groups[__GROUP__][values][__VALUE__][color_hex]" placeholder="#FFFFFF"></div>
            <div class="col-md-4"><label class="form-label">{{ __('catalog.variant_builder.image_url') }}</label><input type="url" class="form-control" data-name="groups[__GROUP__][values][__VALUE__][image_url]"></div>
            <div class="col-md-1"><div class="form-check mb-2"><input type="hidden" data-name="groups[__GROUP__][values][__VALUE__][is_active]" value="0"><input class="form-check-input" type="checkbox" data-name="groups[__GROUP__][values][__VALUE__][is_active]" value="1" checked><label class="form-check-label">{{ __('catalog.variant_builder.enabled') }}</label></div></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-value">×</button></div>
        </div>
    </template>
    <template id="group-template">
        <div class="card option-group">
            <div class="card-body">
                <div class="row align-items-end mb-3">
                    <div class="col-md-6"><label class="form-label">{{ __('catalog.variant_builder.group_name') }}</label>@foreach($contentLanguages as $language)@php($code = $language->code)<div class="option-locale-field option-locale-{{ $code }} @if($code !== $defaultContentLocale) d-none @endif"><input class="form-control" data-name="groups[__GROUP__][name][{{ $code }}]" data-locale="{{ $code }}" data-field-template="group___GROUP___name" @required($code === $defaultContentLocale)></div>@endforeach</div>
                    <div class="col-md-4"><label class="form-label">{{ __('catalog.variant_builder.display_type') }}</label><select class="form-select" data-name="groups[__GROUP__][display_type]"><option value="select">{{ __('catalog.variant_builder.display_select') }}</option><option value="color">{{ __('catalog.fields.option_color') }}</option><option value="image">{{ __('catalog.variant_builder.display_image') }}</option></select></div>
                    <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-group">{{ __('catalog.variant_builder.remove_group') }}</button></div>
                </div>
                <div class="values d-flex flex-column gap-2"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-3 add-value">{{ __('catalog.variant_builder.add_value') }}</button>
            </div>
        </div>
    </template>
    @include('admin.shared.translation-assets')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const groups = document.getElementById('option-groups');
    const valueTemplate = document.getElementById('value-template');
    const groupTemplate = document.getElementById('group-template');
    const nextGroupIndex = () => Math.max(-1, ...[...groups.querySelectorAll('.option-group')].map((el) => Number(el.dataset.groupIndex || -1))) + 1;
    const addValue = (group) => {
        const groupIndex = group.dataset.groupIndex;
        const valueIndex = group.querySelectorAll('.value-row').length;
        const node = valueTemplate.content.cloneNode(true);
        node.querySelectorAll('[data-name]').forEach((input) => {
            input.name = input.dataset.name.replaceAll('__GROUP__', groupIndex).replaceAll('__VALUE__', valueIndex);
            if (input.dataset.locale) input.dataset.i18nLocale = input.dataset.locale;
            if (input.dataset.fieldTemplate) input.dataset.i18nField = input.dataset.fieldTemplate.replaceAll('__GROUP__', groupIndex).replaceAll('__VALUE__', valueIndex);
        });
        group.querySelector('.values').appendChild(node);
        document.querySelector('.option-locale-switch.active')?.click();
    };
    document.getElementById('add-group').addEventListener('click', () => {
        const groupIndex = nextGroupIndex();
        const node = groupTemplate.content.cloneNode(true);
        const group = node.querySelector('.option-group');
        group.dataset.groupIndex = groupIndex;
        group.querySelectorAll('[data-name]').forEach((input) => {
            input.name = input.dataset.name.replaceAll('__GROUP__', groupIndex);
            if (input.dataset.locale) input.dataset.i18nLocale = input.dataset.locale;
            if (input.dataset.fieldTemplate) input.dataset.i18nField = input.dataset.fieldTemplate.replaceAll('__GROUP__', groupIndex);
        });
        groups.appendChild(node);
        addValue(groups.lastElementChild);
        document.querySelector('.option-locale-switch.active')?.click();
    });
    groups.addEventListener('click', (event) => {
        if (event.target.closest('.add-value')) addValue(event.target.closest('.option-group'));
        if (event.target.closest('.remove-value')) event.target.closest('.value-row').remove();
        if (event.target.closest('.remove-group')) event.target.closest('.option-group').remove();
    });
    document.querySelectorAll('.option-locale-switch').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('.option-locale-switch').forEach((item) => item.classList.toggle('active', item === button));
        document.querySelectorAll('.option-locale-field').forEach((field) => field.classList.toggle('d-none', !field.classList.contains(`option-locale-${button.dataset.optionLocale}`)));
        document.querySelectorAll('.option-translate-button').forEach((item) => item.classList.toggle('d-none', item.dataset.optionTranslationTarget !== button.dataset.optionLocale));
    }));
});
</script>
@endpush
