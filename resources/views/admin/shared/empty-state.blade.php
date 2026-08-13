@php
    $emptyImage = $emptyImage ?? asset('admin-assets/images/icons/order-empty.png');
    $emptyMessage = $emptyMessage ?? __('catalog.common.no_data');
    $emptyId = $emptyId ?? null;
@endphp

<div @if($emptyId) id="{{ $emptyId }}" @endif class="text-center px-3 py-5">
    <img src="{{ $emptyImage }}"
         alt="{{ $emptyMessage }}"
         width="240"
         class="img-fluid d-block mx-auto mb-3"
         onerror="this.onerror=null;this.src='{{ asset('admin-assets/images/icons/emptydata.png') }}';">
    <p class="text-muted mb-0 fs-4 fw-bold">{{ $emptyMessage }}</p>
</div>
