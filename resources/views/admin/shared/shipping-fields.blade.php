@php
    /**
     * The shipping fields of a product or a variant (today the weight; a length, width and height join here
     * when a carrier needs them, so the product and variant forms never repeat them).
     *
     * Params: $entity (Product|ProductVariant, may be unsaved), $help (optional text under the field),
     * $placeholder (optional, e.g. the weight a variant inherits), $columns (grid classes, default full width).
     */
    $help = $help ?? null;
    $placeholder = $placeholder ?? '';
    $columns = $columns ?? 'col-12';
@endphp
<div class="{{ $columns }} mb-3" data-shipping-fields>
    <label class="form-label" for="weight_grams">{{ __('catalog.fields.weight_grams') }}</label>
    <input type="number" min="0" max="{{ \App\Support\Shipping\ShippingProfileResolver::MAX_PARCEL_GRAMS }}" step="1" inputmode="numeric" class="form-control @error('weight_grams') is-invalid @enderror" id="weight_grams" name="weight_grams" value="{{ old('weight_grams', $entity->weight_grams) }}" placeholder="{{ $placeholder }}">
    @error('weight_grams')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($help)<p class="fs-2 mt-1 mb-0">{{ $help }}</p>@endif
</div>
