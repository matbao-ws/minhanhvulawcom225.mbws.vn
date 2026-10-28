@extends('admin.layouts.app')

@section('title', __('admin.orders.create_order'))

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-semibold mb-0">{{ __('admin.orders.form.manual_title') }}</h4>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.orders.store') }}">
        @csrf
        <div class="card mb-4"><div class="card-body"><div class="row g-3 position-relative">
            <div data-tour="order-form.customer" class="col-md-6"><label class="form-label" for="customer_name">{{ __('admin.orders.form.customer_name') }} <span class="text-danger">*</span></label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" class="form-control js-customer-lookup" placeholder="{{ __('admin.orders.form.customer_name_placeholder') }}" autocomplete="off" required></div>
            <div class="col-md-3"><label class="form-label" for="customer_email">Email <span class="text-danger">*</span></label><input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" class="form-control js-customer-lookup" placeholder="{{ __('admin.orders.form.email_placeholder') }}" autocomplete="off" required></div>
            <div class="col-md-3"><label class="form-label" for="customer_phone">{{ __('admin.orders.fields.phone') }} <span class="text-danger">*</span></label><input id="customer_phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" class="form-control js-customer-lookup" placeholder="{{ __('admin.orders.form.phone_placeholder') }}" autocomplete="off" required></div>
            <div class="col-12 position-relative mt-0">
                <div id="customer-suggestions" class="list-group position-absolute start-0 end-0 mx-3 shadow d-none" style="z-index: 1050; max-height: 280px; overflow-y: auto;"></div>
                <div id="customer-lookup-help" class="form-text">{{ __('admin.orders.form.lookup.help') }}</div>
            </div>
            <div data-tour="order-form.address" class="col-md-8"><label class="form-label" for="shipping_address">{{ __('admin.orders.fields.shipping_address') }} <span class="text-danger">*</span></label><input id="shipping_address" name="shipping_address" value="{{ old('shipping_address') }}" class="form-control" placeholder="{{ __('admin.orders.form.address_placeholder') }}" required></div>
            <div data-tour="order-form.payment" class="col-md-4"><label class="form-label">{{ __('admin.orders.payment_method') }}</label><select name="payment_method" class="form-select" required><option value="">{{ __('admin.orders.form.choose_payment_method') }}</option>@foreach($paymentMethods as $method)<option value="{{ $method->method_code }}" @selected(old('payment_method') === $method->method_code)>{{ $method->name }}</option>@endforeach</select></div>
            <div data-tour="order-form.discount" class="col-md-4"><label class="form-label">{{ __('admin.orders.form.manual_discount') }}</label><input type="number" min="0" step="0.01" name="discount" value="{{ old('discount', 0) }}" class="form-control"><div class="form-text">{{ __('admin.orders.form.manual_discount_help') }}</div></div>
            <div data-tour="order-form.shipping-fee" class="col-md-4"><label class="form-label">{{ __('admin.orders.shipping_fee') }}</label><input type="number" min="0" step="0.01" name="shipping_fee" value="{{ old('shipping_fee', 0) }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">{{ __('admin.orders.form.notes') }}</label><input name="notes" value="{{ old('notes') }}" class="form-control"></div>
        </div></div></div>

        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div data-tour="order-form.items" class="card"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">{{ __('admin.orders.form.product') }}</h5><button data-tour="order-form.add-item" type="button" id="add-item" class="btn btn-sm btn-outline-primary">{{ __('admin.orders.form.add_product') }}</button></div><div class="card-body" id="order-items"></div></div>
        <button data-tour="order-form.submit" class="btn btn-primary mt-4">{{ __('admin.orders.create_order') }}</button>
    </form>

    <template id="order-item-template"><div class="row g-2 align-items-end border-bottom pb-3 mb-3 order-item">
        <div class="col-md-2"><label class="form-label">{{ __('admin.orders.form.category') }}</label><select class="form-select category-select" required><option value="">{{ __('admin.orders.form.choose_category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach<option value="uncategorized">{{ __('admin.orders.form.uncategorized') }}</option></select></div>
        <div class="col-md-2"><label class="form-label">{{ __('admin.orders.form.brand') }}</label><select class="form-select brand-select"><option value="">{{ __('admin.orders.form.all_brands') }}</option>@foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach<option value="unbranded">{{ __('admin.orders.form.unbranded') }}</option></select></div>
        <div class="col-md-3"><label class="form-label">{{ __('admin.orders.form.product') }}</label><select name="items[__INDEX__][product_id]" class="form-select product-select" required disabled><option value="">{{ __('admin.orders.form.lookup.choose_category_first') }}</option>@foreach($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category_id ?? 'uncategorized' }}" data-brand="{{ $product->brand_id ?? 'unbranded' }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">{{ __('admin.orders.form.variant_optional') }}</label><select name="items[__INDEX__][variant_id]" class="form-select variant-select" disabled><option value="">{{ __('admin.orders.form.lookup.choose_product_first') }}</option>
            @foreach($products as $product)
                @foreach($product->variants as $variant)
                    <option value="{{ $variant->id }}" data-product="{{ $product->id }}">{{ $variant->name }}{{ $variant->sku ? ' — '.$variant->sku : '' }}</option>
                @endforeach
            @endforeach
        </select></div>
        <div class="col-md-1"><label class="form-label">{{ __('admin.orders.quantity') }}</label><input type="number" min="1" name="items[__INDEX__][quantity]" value="1" class="form-control" required></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-item d-inline-flex align-items-center justify-content-center p-0" style="width: 38px; height: 38px;" title="{{ __('admin.orders.form.remove_product') }}" aria-label="{{ __('admin.orders.form.remove_product') }}">×</button></div>
    </div></template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const lookupText = @json(__('admin.orders.form.lookup'));
    const customerFields = {
        name: document.getElementById('customer_name'),
        email: document.getElementById('customer_email'),
        phone: document.getElementById('customer_phone'),
        address: document.getElementById('shipping_address'),
    };
    const suggestions = document.getElementById('customer-suggestions');
    const lookupHelp = document.getElementById('customer-lookup-help');
    const lookupUrl = @json(route('admin.orders.customer-suggestions'));
    let lookupTimer;
    let lookupRequest;

    const closeSuggestions = () => {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
    };

    const selectCustomer = customer => {
        customerFields.name.value = customer.name || '';
        customerFields.email.value = customer.email || '';
        customerFields.phone.value = customer.phone || '';
        customerFields.address.value = customer.address || '';
        lookupHelp.textContent = lookupText.filled;
        lookupHelp.classList.add('text-success');
        closeSuggestions();
    };

    const renderSuggestions = customers => {
        suggestions.replaceChildren();
        if (!customers.length) {
            lookupHelp.textContent = lookupText.not_found;
            lookupHelp.classList.remove('text-success');
            closeSuggestions();
            return;
        }

        customers.forEach(customer => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action py-2';

            const title = document.createElement('div');
            title.className = 'fw-semibold text-dark';
            title.textContent = customer.name || customer.email;

            const detail = document.createElement('small');
            detail.className = 'text-muted d-block';
            detail.textContent = [customer.phone, customer.email, customer.address].filter(Boolean).join(' • ');

            button.append(title, detail);
            button.addEventListener('mousedown', event => {
                event.preventDefault();
                selectCustomer(customer);
            });
            suggestions.appendChild(button);
        });
        suggestions.classList.remove('d-none');
    };

    const searchCustomers = async query => {
        lookupRequest?.abort();
        lookupRequest = new AbortController();
        lookupHelp.textContent = lookupText.searching;
        lookupHelp.classList.remove('text-success');

        try {
            const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'Accept': 'application/json' },
                signal: lookupRequest.signal,
            });
            if (!response.ok) throw new Error('Customer lookup failed');
            const payload = await response.json();
            renderSuggestions(Array.isArray(payload.data) ? payload.data : []);
        } catch (error) {
            if (error.name === 'AbortError') return;
            closeSuggestions();
            lookupHelp.textContent = lookupText.failed;
            lookupHelp.classList.remove('text-success');
        }
    };

    document.querySelectorAll('.js-customer-lookup').forEach(input => {
        input.addEventListener('input', () => {
            window.clearTimeout(lookupTimer);
            const query = input.value.trim();
            if (query.length < 2) {
                lookupRequest?.abort();
                closeSuggestions();
                lookupHelp.textContent = lookupText.help;
                lookupHelp.classList.remove('text-success');
                return;
            }
            lookupTimer = window.setTimeout(() => searchCustomers(query), 300);
        });
        input.addEventListener('focus', () => {
            if (suggestions.childElementCount) suggestions.classList.remove('d-none');
        });
    });
    document.addEventListener('click', event => {
        if (!suggestions.contains(event.target) && !event.target.classList.contains('js-customer-lookup')) {
            closeSuggestions();
        }
    });

    const container = document.getElementById('order-items');
    const template = document.getElementById('order-item-template').innerHTML;
    let index = 0;
    const addItem = () => {
        container.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', index++));
        const row = container.lastElementChild;
        const category = row.querySelector('.category-select');
        const brand = row.querySelector('.brand-select');
        const product = row.querySelector('.product-select');
        const variants = row.querySelector('.variant-select');
        const productPlaceholder = product.querySelector('option[value=""]');
        const variantPlaceholder = variants.querySelector('option[value=""]');
        const filterProducts = () => {
            const selectedCategory = category.value;
            const selectedBrand = brand.value;
            product.disabled = !selectedCategory;
            productPlaceholder.textContent = selectedCategory ? lookupText.choose_product : lookupText.choose_category_first;
            [...product.options].forEach(option => {
                const isVisible = !option.value || (option.dataset.category === selectedCategory && (!selectedBrand || option.dataset.brand === selectedBrand));
                option.hidden = !isVisible;
                option.disabled = !isVisible;
            });
        };
        const filterVariants = () => {
            variants.disabled = !product.value;
            variantPlaceholder.textContent = product.value ? lookupText.no_variant : lookupText.choose_product_first;
            [...variants.options].forEach(option => {
                const isVisible = !option.value || option.dataset.product === product.value;
                option.hidden = !isVisible;
                option.disabled = !isVisible;
            });
        };
        category.addEventListener('change', () => {
            product.value = '';
            variants.value = '';
            filterProducts();
            filterVariants();
        });
        brand.addEventListener('change', () => {
            product.value = '';
            variants.value = '';
            filterProducts();
            filterVariants();
        });
        product.addEventListener('change', () => { variants.value = ''; filterVariants(); });
        filterProducts();
        filterVariants();
        row.querySelector('.remove-item').addEventListener('click', () => row.remove());
    };
    document.getElementById('add-item').addEventListener('click', addItem);
    addItem();
});
</script>
@endpush
