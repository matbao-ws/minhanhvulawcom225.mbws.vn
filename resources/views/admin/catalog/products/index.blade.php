@extends('admin.layouts.app')

@section('title', __('catalog.products.title'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/dragula/dist/dragula.min.css') }}">
@endpush

@php $isFiltered = $isFiltered ?? false; @endphp

@section('content')
        <x-admin.page-header :title="__('catalog.products.title')">
            <x-slot:breadcrumbs>
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('catalog.products.title') }}</li>
            </x-slot:breadcrumbs>
            <x-slot:actions>
                <x-admin.action-button data-tour="products.import" variant="info" icon="upload" data-bs-toggle="modal" data-bs-target="#importProductsModal">{{ __('catalog.products.import') }}</x-admin.action-button>
                <x-admin.action-button data-tour="products.export" :href="route('admin.products.export', request()->query())" variant="success" icon="download">{{ __('catalog.products.export') }}</x-admin.action-button>
                <x-admin.action-button data-tour="products.create" :href="route('admin.products.create')" icon="plus">{{ __('catalog.products.create') }}</x-admin.action-button>
            </x-slot:actions>
        </x-admin.page-header>

    @if(session('import_errors'))
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <h5 class="alert-heading fw-semibold"><i class="ti ti-alert-triangle me-1"></i>{{ __('catalog.products.import_errors_title') }}</h5>
            <ul class="mb-0 ps-3 fs-3" style="max-height: 200px; overflow-y: auto;">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('catalog.actions.close') }}"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form data-tour="products.filters" method="GET" data-responsive-filters class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('catalog.products.search_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.fields.category') }}</label>
                    <select name="category_id" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
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
                        <option value="">{{ __('admin.all') }}</option>
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
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected((string) request('status') === '1')>{{ __('catalog.status.active') }}
                        </option>
                        <option value="0" @selected((string) request('status') === '0')>{{ __('catalog.status.inactive') }}
                        </option>
                    </select>
                </div>
                <div class="col-md-1">
                    <x-admin.action-button type="submit" icon="search" class="w-100" :title="__('catalog.actions.search')" aria-label="{{ __('catalog.actions.search') }}" />
                </div>
            </form>
        </div>
        {{-- Filters, pages, show/hide and delete update these two parts only (admin-ajax.js). --}}
        @php
            $canUpdateProducts = (bool) auth()->user()?->can('products.update');
        @endphp
        <div class="card-body p-0" data-ajax-list="products">
            @include('admin.shared.bulk-actions', [
                'bulkFormId' => 'bulk-products-form',
                'bulkActionUrl' => route('admin.products.bulk'),
                'bulkItemLabel' => __('catalog.products.bulk_item_label'),
            ])
            <div data-tour="products.table" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th data-tour="products.select-all" class="ps-4" style="width: 44px;"><input type="checkbox" class="form-check-input" data-bulk-select-all="bulk-products-form" aria-label="{{ __('catalog.products.select_all') }}"></th>
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
                    <tbody id="product-table-sortable" data-sortable="{{ $isFiltered ? '0' : '1' }}" data-start-order="{{ max(0, ($products->firstItem() ?? 1) - 1) }}">
                        @forelse($products as $product)
                            <tr class="text-nowrap" data-product-id="{{ $product->id }}">
                                <td class="ps-4"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $product->id }}" form="bulk-products-form" data-bulk-select="bulk-products-form" aria-label="{{ __('catalog.actions.select_item', ['name' => $product->name]) }}"></td>
                                <td class="ps-4 text-wrap" style="min-width: 300px;">
                                    <div class="d-flex align-items-center gap-3">
                                        @unless($isFiltered)
                                            <span class="catalog-drag-handle cursor-grab" title="{{ __('catalog.actions.drag_sort') }}">
                                                <i class="ti ti-grip-vertical fs-5"></i>
                                            </span>
                                        @endunless
                                        <img src="{{ $product->image_url ?: asset('admin-assets/images/icons/empty-sanpham.png') }}"
                                             onerror="this.onerror=null;this.src='{{ asset('admin-assets/images/icons/empty-sanpham.png') }}'"
                                             alt="{{ $product->name }}"
                                             class="rounded-2 object-fit-cover"
                                             width="50" height="50"
                                             style="min-width: 50px;">
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
                                @if($canUpdateProducts && $product->variants_count === 0)
                                    {{-- Edited in place: Enter or leaving the field saves (admin-ajax.js). --}}
                                    <td>
                                        <form method="POST" action="{{ route('admin.products.quick-update', $product) }}" id="quick-product-{{ $product->id }}" class="product-quick-edit mb-0" data-product-quick-edit>
                                            @csrf
                                            @method('PATCH')
                                            <div class="input-group input-group-sm" style="width: 150px;">
                                                <input type="number" name="price" class="form-control" min="0" step="any" value="{{ (float) $product->price }}" required
                                                       aria-label="{{ __('catalog.fields.price') }}" title="{{ __('catalog.products.quick_edit_hint') }}" data-quick-original="{{ (float) $product->price }}">
                                                <span class="input-group-text">đ</span>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <input type="number" name="stock_quantity" form="quick-product-{{ $product->id }}" class="form-control form-control-sm" style="width: 90px;" min="0" step="1" value="{{ (int) $product->stock_quantity }}" required
                                               aria-label="{{ __('catalog.fields.stock_quantity') }}" title="{{ __('catalog.products.quick_edit_hint') }}" data-quick-original="{{ (int) $product->stock_quantity }}">
                                    </td>
                                @else
                                    <td>
                                        <span class="fw-semibold text-dark fs-3">{{ number_format((float) $product->price) }} đ</span>
                                    </td>
                                    <td>
                                        <span class="fs-3 text-dark fw-medium">{{ $product->stock_quantity }}</span>
                                    </td>
                                @endif
                                <td>
                                    @if($product->variants_count > 0)
                                        <span class="badge bg-info-subtle text-info fw-semibold fs-2">{{ $product->variants_count }} {{ __('catalog.fields.variants') }}</span>
                                    @else
                                        <span class="text-muted fs-2">-</span>
                                    @endif
                                </td>
                                <td>
                                    @include('admin.shared.status-switch', [
                                        'action' => route('admin.products.bulk'),
                                        'id' => $product->id,
                                        'active' => (bool) $product->is_active,
                                        'can' => $canUpdateProducts,
                                        'activeLabel' => __('catalog.status.active'),
                                        'inactiveLabel' => __('catalog.status.inactive'),
                                    ])
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
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->isEmpty())
                @include('admin.shared.empty-state', [
                    'emptyImage' => asset('admin-assets/images/icons/empty-sanpham.png'),
                ])
            @endif
        </div>
        <div class="card-body" data-ajax-list="products-pages">
            @include('admin.shared.pagination', ['paginator' => $products])
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('catalog.actions.close') }}"></button>
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
                        <div class="form-text mt-1">{{ __('catalog.products.import_file_help') }}</div>
                    </div>
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="fw-semibold mb-2 fs-3"><i class="ti ti-download me-1 text-primary"></i>{{ __('catalog.products.download_template_heading') }}
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

@push('scripts')
    <script src="{{ asset('admin-assets/libs/dragula/dist/dragula.min.js') }}"></script>
    <script>
        // Price and stock in the row: saved when changed, on Enter or when leaving the field.
        (function () {
            const save = function (input) {
                const form = input.form;
                if (!form || !form.hasAttribute('data-product-quick-edit')) return;
                const fields = Array.from(form.elements).filter(function (field) { return field.dataset.quickOriginal !== undefined; });
                const changed = fields.some(function (field) { return Number(field.value) !== Number(field.dataset.quickOriginal); });
                if (!changed) return;
                if (!form.reportValidity()) return;
                form.requestSubmit ? form.requestSubmit() : form.submit();
            };
            document.addEventListener('keydown', function (event) {
                const input = event.target.closest('[data-quick-original]');
                if (!input || event.key !== 'Enter') return;
                event.preventDefault();
                save(input);
            });
            document.addEventListener('change', function (event) {
                const input = event.target.closest('[data-quick-original]');
                if (input) save(input);
            });
        })();

        // Again after each in-place list update (admin-ajax.js): the rows are new.
        AdminAjax.ready(function (root) {
            const sortable = root.querySelector('#product-table-sortable');
            const sortingEnabled = sortable && sortable.dataset.sortable === '1';
            const csrfToken = @json(csrf_token());
            const sortUrl = @json(route('admin.products.sort'));

            const toast = function (icon, message) {
                if (!window.Swal) {
                    return;
                }

                Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true
                }).fire({ icon, title: message });
            };

            if (!sortingEnabled || !sortable || !window.dragula || sortable.querySelectorAll('tr[data-product-id]').length < 2) {
                return;
            }

            dragula([sortable], {
                moves: function (el, container, handle) {
                    return el.dataset.productId !== undefined && handle.closest('.catalog-drag-handle') !== null;
                }
            }).on('drop', function () {
                const ids = Array.from(sortable.querySelectorAll('tr[data-product-id]')).map(function (tr) {
                    return tr.dataset.productId;
                });

                fetch(sortUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        ids: ids,
                        start_order: Number(sortable.dataset.startOrder || 0)
                    })
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Sort failed');
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        toast('success', payload.message || @json(__('catalog.products.sorted')));
                    })
                    .catch(function () {
                        toast('error', @json(__('catalog.products.sort_failed')));
                    });
            });
        });
    </script>
@endpush
