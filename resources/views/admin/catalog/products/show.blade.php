@extends('admin.layouts.app')

@section('title', $product->name)

@section('content')
    @php
        $productImage = $product->image_url ?: asset('images/icons/default-product.png');
        $fallbackImage = asset('images/icons/default-product.png');
        $totalStock = $product->usesVariantInventory()
            ? $product->variants->sum('stock_quantity')
            : $product->stock_quantity;
    @endphp

    <div class="card product-show-hero shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-3 px-md-4 py-3">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="min-w-0">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">
                                    {{ __('admin.home') }}
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a class="text-white-50 text-decoration-none" href="{{ route('admin.products.index') }}">
                                    {{ __('catalog.products.title') }}
                                </a>
                            </li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ $product->name }}</li>
                        </ol>
                    </nav>
                    <h4 class="fw-semibold text-white mb-1 text-break">{{ $product->name }}</h4>
                    <div class="d-flex flex-wrap align-items-center gap-2 text-white-50">
                        <span>SKU: {{ $product->sku ?: '-' }}</span>
                        @if($product->slug)
                            <span aria-hidden="true">•</span>
                            <span class="text-break">{{ $product->slug }}</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-light">
                        <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>{{ __('catalog.actions.back') }}
                    </a>
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-light text-primary">
                        <i class="ti ti-edit me-1" aria-hidden="true"></i>{{ __('catalog.actions.edit') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="product-show-image">
                        <img src="{{ $productImage }}"
                             onerror="this.onerror=null;this.src='{{ $fallbackImage }}'"
                             alt="{{ $product->name }}">
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="badge {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} px-3 py-2">
                            <i class="ti {{ $product->is_active ? 'ti-circle-check' : 'ti-eye-off' }} me-1" aria-hidden="true"></i>
                            {{ $product->is_active ? __('catalog.status.active') : __('catalog.status.inactive') }}
                        </span>
                        @if($product->is_featured)
                            <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                <i class="ti ti-star-filled me-1" aria-hidden="true"></i>{{ __('catalog.fields.is_featured') }}
                            </span>
                        @endif
                    </div>

                    @if($product->short_description)
                        <div class="product-show-short-description mt-3 text-muted">
                            {!! $product->short_description !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-4">
                    <div class="card product-show-stat h-100 mb-0">
                        <div class="card-body">
                            <div class="product-show-stat-icon bg-primary-subtle text-primary">
                                <i class="ti ti-currency-dong" aria-hidden="true"></i>
                            </div>
                            <div class="text-muted small mt-3">{{ __('catalog.fields.price') }}</div>
                            <div class="fs-6 fw-bold text-dark">{{ number_format((float) $product->price) }} đ</div>
                            @if($product->compare_at_price)
                                <div class="small text-muted text-decoration-line-through">
                                    {{ number_format((float) $product->compare_at_price) }} đ
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card product-show-stat h-100 mb-0">
                        <div class="card-body">
                            <div class="product-show-stat-icon bg-info-subtle text-info">
                                <i class="ti ti-box" aria-hidden="true"></i>
                            </div>
                            <div class="text-muted small mt-3">{{ __('catalog.fields.stock_quantity') }}</div>
                            <div class="fs-6 fw-bold text-dark">{{ number_format((int) $totalStock) }}</div>
                            <div class="small text-muted">
                                {{ $product->usesVariantInventory() ? 'Tính theo SKU biến thể' : ($product->manage_stock ? 'Đang quản lý tồn kho' : 'Không theo dõi tồn kho') }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card product-show-stat h-100 mb-0">
                        <div class="card-body">
                            <div class="product-show-stat-icon bg-warning-subtle text-warning">
                                <i class="ti ti-stack-2" aria-hidden="true"></i>
                            </div>
                            <div class="text-muted small mt-3">{{ __('catalog.fields.variants') }}</div>
                            <div class="fs-6 fw-bold text-dark">{{ $product->variants->count() }}</div>
                            <div class="small text-muted">{{ $product->optionGroups->count() }} nhóm thuộc tính</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="mb-0">{{ __('catalog.products.details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <div class="product-show-field">
                                <span class="product-show-field-icon"><i class="ti ti-category" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <div class="text-muted small">{{ __('catalog.fields.category') }}</div>
                                    <div class="fw-semibold text-break">{{ $product->category?->name ?? __('catalog.common.none') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="product-show-field">
                                <span class="product-show-field-icon"><i class="ti ti-award" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <div class="text-muted small">{{ __('catalog.fields.brand') }}</div>
                                    <div class="fw-semibold text-break">{{ $product->brand?->name ?? __('catalog.common.none') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="product-show-field">
                                <span class="product-show-field-icon"><i class="ti ti-barcode" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <div class="text-muted small">{{ __('catalog.fields.sku') }}</div>
                                    <div class="fw-semibold text-break">{{ $product->sku ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="product-show-field">
                                <span class="product-show-field-icon"><i class="ti ti-calendar" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <div class="text-muted small">Ngày đăng</div>
                                    <div class="fw-semibold">
                                        {{ ($product->published_at ?? $product->created_at)?->format('d/m/Y H:i') ?? '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($product->description)
        <div class="card mt-4 mb-0">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="mb-0">{{ __('catalog.fields.description') }}</h5>
            </div>
            <div class="card-body">
                <div class="product-show-description">{!! $product->description !!}</div>
            </div>
        </div>
    @endif

    <div class="card mt-4 mb-0">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-1">{{ __('catalog.variants.title') }}</h5>
                    <div class="text-muted small">
                        {{ $product->variants->count() }} SKU · {{ $product->optionGroups->count() }} nhóm thuộc tính
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.products.options.edit', $product) }}" class="btn btn-outline-primary">
                        <i class="ti ti-adjustments me-1" aria-hidden="true"></i>Thuộc tính
                    </a>
                    @if($product->optionGroups->isNotEmpty())
                        <form method="POST" action="{{ route('admin.products.variants.generate', $product) }}">
                            @csrf
                            <button class="btn btn-outline-success" type="submit">
                                <i class="ti ti-wand me-1" aria-hidden="true"></i>Tạo tổ hợp
                            </button>
                        </form>
                        <a href="{{ route('admin.products.variants.create', $product) }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1" aria-hidden="true"></i>{{ __('catalog.variants.create') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 product-show-variants">
                <thead>
                    <tr>
                        <th class="ps-4">{{ __('catalog.fields.name') }}</th>
                        <th>{{ __('catalog.fields.sku') }}</th>
                        <th>{{ __('catalog.fields.options') }}</th>
                        <th>{{ __('catalog.fields.price') }}</th>
                        <th>{{ __('catalog.fields.stock_quantity') }}</th>
                        <th>{{ __('catalog.fields.status') }}</th>
                        <th class="text-end pe-4">{{ __('catalog.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($product->variants as $variant)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold">{{ $variant->name ?: '-' }}</div>
                                @if($variant->is_default)
                                    <span class="badge bg-info-subtle text-info mt-1">Mặc định</span>
                                @endif
                            </td>
                            <td><span class="font-monospace">{{ $variant->sku }}</span></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($variant->optionValues as $value)
                                        <span class="badge bg-primary-subtle text-primary">
                                            {{ $value->optionGroup?->name }}: {{ $value->label }}
                                        </span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="text-nowrap">
                                {{ number_format((float) ($variant->price ?? $product->price)) }} đ
                            </td>
                            <td>{{ number_format((int) $variant->stock_quantity) }}</td>
                            <td>
                                <span class="badge {{ $variant->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                    {{ $variant->is_active ? __('catalog.status.active') : __('catalog.status.inactive') }}
                                </span>
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <a href="{{ route('admin.products.variants.edit', [$product, $variant]) }}"
                                   class="btn btn-sm btn-outline-primary"
                                   title="{{ __('catalog.actions.edit') }}">
                                    <i class="ti ti-edit" aria-hidden="true"></i>
                                </a>
                                <form method="POST"
                                      action="{{ route('admin.products.variants.destroy', [$product, $variant]) }}"
                                      class="d-inline js-delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="{{ __('catalog.actions.delete') }}">
                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <img src="{{ asset('admin-assets/images/icons/emptydata.png') }}"
                                     alt=""
                                     class="img-fluid mb-2"
                                     style="max-height: 60px;">
                                <p class="text-muted fw-bold mb-0">{{ __('catalog.common.no_data') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .product-show-hero {
            background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;
        }

        .product-show-hero .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.55);
        }

        .product-show-image {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 320px;
            aspect-ratio: 1 / 1;
            padding: 20px;
            overflow: hidden;
            border: 1px solid #e5eaf2;
            border-radius: 16px;
            background: #f8fafc;
        }

        .product-show-image img {
            width: 100%;
            height: 100%;
            max-height: 420px;
            object-fit: contain;
        }

        .product-show-stat {
            border: 1px solid #e5eaf2;
            box-shadow: none;
        }

        .product-show-stat-icon,
        .product-show-field-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 12px;
            font-size: 22px;
        }

        .product-show-field {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .product-show-field-icon {
            color: #5d87ff;
            background: rgba(93, 135, 255, 0.12);
        }

        .product-show-short-description > :last-child,
        .product-show-description > :last-child {
            margin-bottom: 0;
        }

        .product-show-description {
            max-width: 100%;
            overflow-wrap: anywhere;
        }

        .product-show-description img,
        .product-show-short-description img {
            max-width: 100%;
            height: auto;
        }

        .product-show-variants {
            min-width: 920px;
        }

        @media (max-width: 575.98px) {
            .product-show-image {
                min-height: 240px;
            }

            .product-show-hero .btn {
                flex: 1 1 auto;
            }
        }
    </style>
@endpush
