@extends('admin.layouts.app')

@section('title', __('catalog.promotions.title'))

@section('content')
    <x-admin.page-header :title="__('catalog.promotions.heading')" :subtitle="__('catalog.promotions.subtitle')">
        <x-slot:actions>
            <x-admin.action-button data-tour="promotions.create" :href="route('admin.promotions.create')" icon="plus">{{ __('catalog.promotions.create_button') }}</x-admin.action-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-body border-bottom">
            <form data-tour="promotions.filters" class="row g-2"><div class="col-md-6"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('catalog.promotions.search_placeholder') }}"></div><div class="col-md-4"><select name="kind" class="form-select"><option value="">{{ __('catalog.promotions.all_kinds') }}</option><option value="automatic" @selected(request('kind') === 'automatic')>{{ __('catalog.promotions.kind_automatic') }}</option><option value="flash_sale" @selected(request('kind') === 'flash_sale')>Flash Sale</option></select></div><div class="col-md-2"><x-admin.action-button type="submit" icon="filter" class="w-100">{{ __('catalog.actions.filter') }}</x-admin.action-button></div></form>
        </div>
        {{-- Filters, pages and delete update this part only (admin-ajax.js). --}}
        <div data-ajax-list="promotions">
        <div data-tour="promotions.table" class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th class="ps-4">{{ __('catalog.promotions.columns.promotion') }}</th><th>{{ __('catalog.promotions.columns.discount') }}</th><th>{{ __('catalog.promotions.columns.scope') }}</th><th>{{ __('catalog.promotions.columns.schedule') }}</th><th>{{ __('catalog.promotions.columns.usage') }}</th><th>{{ __('catalog.fields.status') }}</th><th class="text-end pe-4">{{ __('catalog.fields.actions') }}</th></tr></thead><tbody>
        @forelse($promotions as $promotion)
            @php $active = $promotion->is_active && (! $promotion->start_at || ! $promotion->start_at->isFuture()) && (! $promotion->end_at || ! $promotion->end_at->isPast()); @endphp
            <tr><td class="ps-4"><div class="fw-semibold">{{ $promotion->name }}</div><small class="text-muted">{{ __('catalog.promotions.row_meta', ['priority' => $promotion->priority, 'min' => $promotion->min_quantity]) }}</small></td><td><span class="badge {{ $promotion->kind === 'flash_sale' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}">{{ $promotion->kind === 'flash_sale' ? 'Flash Sale' : __('catalog.promotions.kind_automatic_short') }}</span><div class="mt-1 fw-semibold">@if($promotion->discount_type === 'percentage') -{{ number_format($promotion->value, 0) }}%@elseif($promotion->discount_type === 'fixed_price') {{ __('catalog.promotions.fixed_price_value', ['amount' => number_format($promotion->value)]) }}đ @else -{{ number_format($promotion->value) }}đ @endif</div></td><td>{{ $promotion->applies_to === 'all_products' ? __('catalog.promotions.all_products') : __('catalog.promotions.targets_count', ['count' => $promotion->targets_count]) }}</td><td><small>{{ $promotion->start_at?->format('d/m H:i') ?? __('catalog.promotions.starts_now') }}<br>→ {{ $promotion->end_at?->format('d/m H:i') ?? __('catalog.promotions.no_end') }}</small></td><td>{{ $promotion->used_count }} / {{ $promotion->quantity_limit ?? '∞' }}</td><td><span class="badge {{ $active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $active ? __('admin.vouchers.statuses.running') : __('catalog.promotions.status_stopped') }}</span></td><td data-tour="promotions.row-actions" class="text-end pe-4"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.promotions.edit', $promotion) }}">{{ __('catalog.actions.edit') }}</a><form class="d-inline" method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('catalog.promotions.confirm_delete')) }})">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('catalog.actions.delete') }}</button></form></td></tr>
        @empty <tr><td colspan="7">@include('admin.shared.empty-state', [
            'emptyImage' => asset('admin-assets/images/icons/emptydata.png'),
            'emptyMessage' => __('catalog.promotions.empty'),
        ])</td></tr>@endforelse
        </tbody></table></div>
        @if($promotions->total() > 0)<div class="p-3">@include('admin.shared.pagination', ['paginator' => $promotions])</div>@endif
        </div>
    </div>
@endsection
