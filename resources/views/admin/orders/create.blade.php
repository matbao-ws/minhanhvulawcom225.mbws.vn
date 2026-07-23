@extends('admin.layouts.app')

@section('title', 'Tạo đơn hàng')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-semibold mb-0">Tạo đơn hàng thủ công</h4>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Quay lại</a>
    </div>

    <form method="POST" action="{{ route('admin.orders.store') }}">
        @csrf
        <div class="card mb-4"><div class="card-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Tên khách hàng</label><input name="customer_name" value="{{ old('customer_name') }}" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="customer_email" value="{{ old('customer_email') }}" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Số điện thoại</label><input name="customer_phone" value="{{ old('customer_phone') }}" class="form-control" required></div>
            <div class="col-md-8"><label class="form-label">Địa chỉ giao hàng</label><input name="shipping_address" value="{{ old('shipping_address') }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Phương thức thanh toán</label><select name="payment_method" class="form-select" required><option value="">Chọn phương thức</option>@foreach($paymentMethods as $method)<option value="{{ $method->method_code }}" @selected(old('payment_method') === $method->method_code)>{{ $method->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Giảm giá thủ công</label><input type="number" min="0" step="0.01" name="discount" value="{{ old('discount', 0) }}" class="form-control"><div class="form-text">Áp dụng thêm sau campaign/Flash Sale đang chạy.</div></div>
            <div class="col-md-4"><label class="form-label">Phí giao hàng</label><input type="number" min="0" step="0.01" name="shipping_fee" value="{{ old('shipping_fee', 0) }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Ghi chú</label><input name="notes" value="{{ old('notes') }}" class="form-control"></div>
        </div></div></div>

        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Sản phẩm</h5><button type="button" id="add-item" class="btn btn-sm btn-outline-primary">Thêm sản phẩm</button></div><div class="card-body" id="order-items"></div></div>
        <button class="btn btn-primary mt-4">Tạo đơn hàng</button>
    </form>

    <template id="order-item-template"><div class="row g-2 align-items-end border-bottom pb-3 mb-3 order-item">
        <div class="col-md-2"><label class="form-label">Danh mục</label><select class="form-select category-select" required><option value="">Chọn danh mục</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach<option value="uncategorized">Chưa phân loại</option></select></div>
        <div class="col-md-2"><label class="form-label">Thương hiệu</label><select class="form-select brand-select"><option value="">Tất cả thương hiệu</option>@foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach<option value="unbranded">Không thương hiệu</option></select></div>
        <div class="col-md-3"><label class="form-label">Sản phẩm</label><select name="items[__INDEX__][product_id]" class="form-select product-select" required disabled><option value="">Chọn danh mục trước</option>@foreach($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category_id ?? 'uncategorized' }}" data-brand="{{ $product->brand_id ?? 'unbranded' }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Biến thể (tuỳ chọn)</label><select name="items[__INDEX__][variant_id]" class="form-select variant-select" disabled><option value="">Chọn sản phẩm trước</option>
            @foreach($products as $product)
                @foreach($product->variants as $variant)
                    <option value="{{ $variant->id }}" data-product="{{ $product->id }}">{{ $variant->name }}{{ $variant->sku ? ' — '.$variant->sku : '' }}</option>
                @endforeach
            @endforeach
        </select></div>
        <div class="col-md-1"><label class="form-label">Số lượng</label><input type="number" min="1" name="items[__INDEX__][quantity]" value="1" class="form-control" required></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-item d-inline-flex align-items-center justify-content-center p-0" style="width: 38px; height: 38px;" title="Xóa sản phẩm" aria-label="Xóa sản phẩm">×</button></div>
    </div></template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
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
            productPlaceholder.textContent = selectedCategory ? 'Chọn sản phẩm' : 'Chọn danh mục trước';
            [...product.options].forEach(option => {
                const isVisible = !option.value || (option.dataset.category === selectedCategory && (!selectedBrand || option.dataset.brand === selectedBrand));
                option.hidden = !isVisible;
                option.disabled = !isVisible;
            });
        };
        const filterVariants = () => {
            variants.disabled = !product.value;
            variantPlaceholder.textContent = product.value ? 'Không chọn biến thể' : 'Chọn sản phẩm trước';
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
