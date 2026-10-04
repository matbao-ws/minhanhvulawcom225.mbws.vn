@extends('client.layouts.app')

@section('title', 'Checkout test')

@section('content')
<main class="client-shell checkout-test">
    <div class="checkout-test__intro"><p class="checkout-test__eyebrow">Development utility</p><h1>Checkout test</h1><p>Tạo đơn qua API public. Giá, khuyến mãi, tồn kho và phí giao hàng đều do server quyết định.</p></div>
    <form id="checkout-test-form" class="checkout-test__card" novalidate>
        <div id="checkout-test-status" class="checkout-test__status" hidden role="status"></div>
        <fieldset><legend>Khách hàng</legend><div class="checkout-test__grid"><label>Họ tên<input name="customer_name" required maxlength="255"></label><label>Email<input name="customer_email" type="email" required maxlength="255"></label><label>Số điện thoại<input name="customer_phone" required maxlength="20"></label><label>Phương thức<select name="payment_method"><option value="cod">COD</option></select></label></div><label>Địa chỉ giao hàng<textarea name="shipping_address" required maxlength="500"></textarea></label><div class="checkout-test__grid" id="ghn-address" data-provinces-url="{{ route('admin.checkout.test.ghn.provinces') }}" data-districts-url="{{ url('/'.app()->getLocale().'/admin/checkout-test/ghn/provinces/__ID__/districts') }}" data-wards-url="{{ url('/'.app()->getLocale().'/admin/checkout-test/ghn/districts/__ID__/wards') }}"><label>Tỉnh/Thành GHN<select id="ghn-province"><option>Đang tải…</option></select></label><label>Quận/Huyện GHN<select id="ghn-district" disabled><option>Chọn tỉnh trước</option></select></label><label>Phường/Xã GHN<select id="ghn-ward" disabled><option>Chọn quận/huyện trước</option></select></label><label>Khối lượng (gram)<input id="ghn-weight" type="number" min="1" value="500"></label></div><label>Ghi chú<textarea name="notes" maxlength="1000"></textarea></label></fieldset>
        <fieldset><legend>Sản phẩm</legend><div id="checkout-test-items"></div><button type="button" class="checkout-test__secondary" data-action="add-item">+ Thêm sản phẩm</button><p class="checkout-test__hint">Chọn sản phẩm từ database. Với sản phẩm biến thể, nhập các Option value ID, ngăn cách bằng dấu phẩy.</p></fieldset>
        <button class="checkout-test__submit" type="submit">Tạo đơn thử</button>
    </form>
</main>
<template id="checkout-test-item-template"><div class="checkout-test__item"><label>Sản phẩm<select name="product_id" required><option value="">Chọn sản phẩm</option>@foreach($products as $product)<option value="{{ $product->id }}">#{{ $product->id }} — {{ $product->getTranslation('name', app()->getLocale()) }}{{ $product->sku ? ' ('.$product->sku.')' : '' }} — {{ number_format($product->price, 0, ',', '.') }} ₫</option>@endforeach</select></label><label>SL<input name="quantity" type="number" min="1" value="1" required></label><div class="checkout-test__options" data-role="options"></div><button type="button" data-action="remove-item" aria-label="Xóa sản phẩm">×</button></div></template>
@endsection

@push('scripts')<script src="{{ asset('client-assets/js/checkout-test.js') }}" defer></script>@endpush
<script type="application/json" id="checkout-test-product-options">@json($productOptions)</script>
