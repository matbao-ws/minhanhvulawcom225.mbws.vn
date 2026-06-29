@extends('admin.layouts.app')

@section('title', $product->name)

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fw-semibold mb-1">{{ $product->name }}</h4>
                <div class="text-muted">{{ $product->sku ?: $product->slug }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.products.edit', $product) }}" class="btn bg-primary-subtle text-primary">{{ __('catalog.actions.edit') }}</a>
                <a href="{{ route('admin.products.index') }}" class="btn btn-primary">{{ __('catalog.actions.back') }}</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('catalog.products.details') }}</h5>
                    <div class="mb-3">
                        <div class="text-muted">{{ __('catalog.fields.category') }}</div>
                        <div class="fw-semibold">{{ $product->category?->name ?? __('catalog.common.none') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted">{{ __('catalog.fields.brand') }}</div>
                        <div class="fw-semibold">{{ $product->brand?->name ?? __('catalog.common.none') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted">{{ __('catalog.fields.price') }}</div>
                        <div class="fw-semibold">{{ number_format((float) $product->price) }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted">{{ __('catalog.fields.stock_quantity') }}</div>
                        <div class="fw-semibold">{{ $product->stock_quantity }}</div>
                    </div>
                    <div>
                        <span class="badge {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            {{ $product->is_active ? __('catalog.status.active') : __('catalog.status.inactive') }}
                        </span>
                        @if($product->is_featured)
                            <span class="badge bg-warning-subtle text-warning">{{ __('catalog.fields.is_featured') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{ __('catalog.variants.title') }}</h5>
                    <a href="{{ route('admin.products.variants.create', $product) }}" class="btn btn-primary">{{ __('catalog.variants.create') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('catalog.fields.name') }}</th>
                            <th>{{ __('catalog.fields.sku') }}</th>
                            <th>{{ __('catalog.fields.options') }}</th>
                            <th>{{ __('catalog.fields.price') }}</th>
                            <th>{{ __('catalog.fields.stock_quantity') }}</th>
                            <th class="text-end">{{ __('catalog.fields.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($product->variants as $variant)
                            <tr>
                                <td>{{ $variant->name ?: '-' }}</td>
                                <td>{{ $variant->sku }}</td>
                                <td>
                                    @forelse($variant->option_values ?? [] as $name => $value)
                                        <span class="badge bg-primary-subtle text-primary">{{ $name }}: {{ $value }}</span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </td>
                                <td>{{ $variant->price !== null ? number_format((float) $variant->price) : '-' }}</td>
                                <td>{{ $variant->stock_quantity }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.products.variants.edit', [$product, $variant]) }}" class="btn btn-sm bg-primary-subtle text-primary">{{ __('catalog.actions.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.products.variants.destroy', [$product, $variant]) }}" class="d-inline js-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm bg-danger-subtle text-danger">{{ __('catalog.actions.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <img src="{{ asset('admin-assets/images/icons/emptydata.png') }}" alt="No data" class="img-fluid mb-2" style="max-height: 60px;">
                                    <p class="text-muted fw-bold mb-0">{{ __('catalog.common.no_data') }}</p>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
