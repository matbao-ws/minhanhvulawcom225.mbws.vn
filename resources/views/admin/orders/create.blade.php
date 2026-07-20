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
        <div class="col-md-5"><label class="form-label">Sản phẩm</label><select name="items[__INDEX__][product_id]" class="form-select product-select" required><option value="">Chọn sản phẩm</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Biến thể (tuỳ chọn)</label><select name="items[__INDEX__][variant_id]" class="form-select variant-select"><option value="">Không chọn biến thể</option>@foreach($products as $product)@foreach($product->variants as $variant)<option value="{{ $variant->id }}" data-product="{{ $product->id }}">{{ $variant->name }}{{ $variant->sku ? ' — '.$variant->sku : '' }}</option>@endforeach@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Số lượng</label><input type="number" min="1" name="items[__INDEX__][quantity]" value="1" class="form-control" required></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-item w-100">×</button></div>
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
        const product = row.querySelector('.product-select');
        const variants = row.querySelector('.variant-select');
        const filterVariants = () => [...variants.options].forEach(option => option.hidden = option.dataset.product && option.dataset.product !== product.value);
        product.addEventListener('change', () => { variants.value = ''; filterVariants(); });
        filterVariants();
        row.querySelector('.remove-item').addEventListener('click', () => row.remove());
    };
    document.getElementById('add-item').addEventListener('click', addItem);
    addItem();
});
</script>
@endpush
