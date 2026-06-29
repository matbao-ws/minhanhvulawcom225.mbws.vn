@extends('admin.layouts.app')

@section('title', __('catalog.products.title'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('catalog.products.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('catalog.products.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center justify-content-end mb-4">
        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importProductsModal">
            <i class="ti ti-upload me-1 fs-4"></i>{{ __('catalog.products.import') }}
        </button>
        <a href="{{ route('admin.products.export', request()->query()) }}" class="btn btn-success">
            <i class="ti ti-download me-1 fs-4"></i>{{ __('catalog.products.export') }}
        </a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1 fs-4"></i>{{ __('catalog.products.create') }}
        </a>
    </div>

    @if(session('import_errors'))
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <h5 class="alert-heading fw-semibold"><i class="ti ti-alert-triangle me-1"></i>Chi tiết lỗi nhập dữ liệu:</h5>
            <ul class="mb-0 ps-3 fs-3" style="max-height: 200px; overflow-y: auto;">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('catalog.products.search_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.fields.category') }}</label>
                    <select name="category_id" class="form-select">
                        <option value="">Tất cả</option>
                        @foreach($categoryOptions as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                                {!! str_repeat('&nbsp;&nbsp;', $category->depth ?? 0) !!}{{ $category->depth ? '↳ ' : '' }}{{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.fields.brand') }}</label>
                    <select name="brand_id" class="form-select">
                        <option value="">Tất cả</option>
                        @foreach($brandOptions as $brandOption)
                            @php
                                $brandName = $brandOption->getTranslation('name', app()->getLocale(), false) ?: $brandOption->name;
                            @endphp
                            <option value="{{ $brandOption->id }}" @selected((string) request('brand_id') === (string) $brandOption->id)>
                                {{ $brandName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="1" @selected((string) request('status') === '1')>{{ __('catalog.status.active') }}
                        </option>
                        <option value="0" @selected((string) request('status') === '0')>{{ __('catalog.status.inactive') }}
                        </option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit" title="{{ __('catalog.actions.search') }}">
                        <i class="ti ti-search fs-5"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('catalog.fields.name') }}</th>
                            <th>{{ __('catalog.fields.category') }}</th>
                            <th>{{ __('catalog.fields.brand') }}</th>
                            <th>{{ __('catalog.fields.price') }}</th>
                            <th>{{ __('catalog.fields.stock_quantity') }}</th>
                            <th>{{ __('catalog.fields.variants') }}</th>
                            <th>{{ __('catalog.fields.status') }}</th>
                            <th class="text-end pe-4">{{ __('catalog.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr class="text-nowrap">
                                <td class="ps-4 text-wrap" style="min-width: 300px;">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="{{ $product->image_url ?: asset('images/icons/default-product.png') }}"
                                             onerror="this.src='{{ asset('images/icons/default-product.png') }}'"
                                             alt="{{ $product->name }}"
                                             class="rounded border border-2 border-primary-subtle"
                                             width="50" height="50"
                                             style="object-fit: cover; min-width: 50px;">
                                        <div>
                                            <h6 class="fw-semibold mb-1 fs-3">
                                                <a href="{{ route('admin.products.show', $product) }}"
                                                    class="text-decoration-none text-dark hover-primary">{{ $product->name }}</a>
                                            </h6>
                                            <div class="text-muted fs-2 d-flex flex-wrap gap-2 align-items-center">
                                                <span>SKU: <strong class="text-dark">{{ $product->sku ?: '-' }}</strong></span>
                                                @if($product->slug)
                                                    <span class="text-muted">|</span>
                                                    <span class="text-muted">{{ $product->slug }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($product->category)
                                        <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">{{ $product->category->name }}</span>
                                    @else
                                        <span class="text-muted fs-2">{{ __('catalog.common.none') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($product->brand)
                                        <span class="badge bg-warning-subtle text-warning fw-semibold fs-2">{{ $product->brand->name }}</span>
                                    @else
                                        <span class="text-muted fs-2">{{ __('catalog.common.none') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-3">{{ number_format((float) $product->price) }} đ</span>
                                </td>
                                <td>
                                    <span class="fs-3 text-dark fw-medium">{{ $product->stock_quantity }}</span>
                                </td>
                                <td>
                                    @if($product->variants_count > 0)
                                        <span class="badge bg-info-subtle text-info fw-semibold fs-2">{{ $product->variants_count }} {{ __('catalog.fields.variants') }}</span>
                                    @else
                                        <span class="text-muted fs-2">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} fw-semibold fs-2">
                                        {{ $product->is_active ? __('catalog.status.active') : __('catalog.status.inactive') }}
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <!-- View Link -->
                                        <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('catalog.actions.view') }}">
                                            <i class="ti ti-eye fs-4"></i>
                                        </a>
                                        <!-- Edit Link -->
                                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary" title="{{ __('catalog.actions.edit') }}">
                                            <i class="ti ti-edit fs-4"></i>
                                        </a>
                                        <!-- Delete Link -->
                                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="d-inline js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('catalog.actions.delete') }}">
                                                <i class="ti ti-trash fs-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <img src="{{ asset('admin-assets/images/icons/emptydata.png') }}" alt="No data"
                                        class="img-fluid mb-2" style="max-height: 60px;">
                                    <p class="text-muted fw-bold mb-0">{{ __('catalog.common.no_data') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-body">
            {{ $products->links() }}
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importProductsModal" tabindex="-1" aria-labelledby="importProductsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.products.import') }}" class="modal-content"
                enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importProductsModalLabel">{{ __('catalog.products.import_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="import_type">{{ __('catalog.products.import_type') }}</label>
                        <select name="import_type" id="import_type" class="form-select" required>
                            <option value="standard" selected>{{ __('catalog.products.import_type_standard') }}</option>
                            <option value="wordpress">{{ __('catalog.products.import_type_wordpress') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="import_file">{{ __('catalog.products.import_file') }} <span
                                class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="import_file" id="import_file" accept=".csv,text/csv"
                            required>
                        <div class="form-text fs-2">Hỗ trợ tệp CSV tối đa 5MB.</div>
                    </div>
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="fw-semibold mb-2 fs-3"><i class="ti ti-download me-1 text-primary"></i>Tải xuống tệp mẫu:
                        </h6>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.products.template', ['type' => 'standard']) }}"
                                class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="ti ti-file-text me-1"></i>{{ __('catalog.products.import_type_standard') }}
                            </a>
                            <a href="{{ route('admin.products.template', ['type' => 'wordpress']) }}"
                                class="btn btn-outline-info btn-sm flex-fill">
                                <i class="ti ti-brand-wordpress me-1"></i>{{ __('catalog.products.import_type_wordpress') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-secondary-subtle text-secondary"
                        data-bs-dismiss="modal">{{ __('catalog.actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('catalog.products.import') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection