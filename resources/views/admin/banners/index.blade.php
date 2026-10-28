@extends('admin.layouts.app')

@section('title', __('admin.banners.title'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/dragula/dist/dragula.min.css') }}">
@endpush

@section('content')
    <x-admin.page-header :title="__('admin.banners.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.banners.title') }}</li>
        </x-slot:breadcrumbs>

        @can('banners.create')
            <x-slot:actions>
                <x-admin.action-button data-tour="banners.create" :href="route('admin.banners.create')" icon="plus">
                    {{ __('admin.banners.create') }}
                </x-admin.action-button>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <!-- Success Notification -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-check fs-5"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Banners List Card -->
    <div class="card">
        <div class="card-body border-bottom p-4">
            <form data-tour="banners.filters" method="GET" data-responsive-filters class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.banners.fields.position') }}</label>
                    <select name="position" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="home_main" @selected(request('position') === 'home_main')>{{ __('admin.banners.positions.home_main') }}</option>
                        <option value="home_sidebar" @selected(request('position') === 'home_sidebar')>{{ __('admin.banners.positions.home_sidebar') }}</option>
                        <option value="promotional" @selected(request('position') === 'promotional')>{{ __('admin.banners.positions.promotional') }}</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.banners.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected(request('status') === '1')>{{ __('admin.banners.fields.active') }}</option>
                        <option value="0" @selected(request('status') === '0')>{{ __('admin.banners.fields.inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <x-admin.action-button type="submit" icon="search" class="w-100">
                        {{ __('catalog.actions.search') }}
                    </x-admin.action-button>
                </div>
            </form>
        </div>

        {{-- Filters, pages, show/hide and delete update this part only (admin-ajax.js). --}}
        @php
            $canUpdateBanners = (bool) auth()->user()?->can('banners.update');
        @endphp
        <div class="card-body p-0" data-ajax-list="banners">
            @include('admin.shared.bulk-actions', [
                'bulkFormId' => 'bulk-banners-form',
                'bulkActionUrl' => route('admin.banners.bulk'),
                'bulkItemLabel' => __('admin.banners.bulk_label'),
            ])
            <div data-tour="banners.table" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap text-muted">
                            <th class="ps-4" style="width: 44px;"></th>
                            <th data-tour="banners.select-all" class="ps-4" style="width: 44px;"><input type="checkbox" class="form-check-input" data-bulk-select-all="bulk-banners-form" aria-label="{{ __('admin.banners.select_all') }}"></th>
                            <th class="ps-4 fw-semibold small" style="width: 180px;">{{ __('admin.banners.fields.image') }}</th>
                            <th class="fw-semibold small">{{ __('admin.banners.fields.title') }}</th>
                            <th class="fw-semibold small">{{ __('admin.banners.fields.position') }}</th>
                            <th class="fw-semibold small" style="width: 150px;">{{ __('admin.banners.fields.status') }}</th>
                            <th class="pe-4 text-end" style="width: 120px;"></th>
                        </tr>
                    </thead>
                    <tbody id="banner-table-sortable" data-sortable="{{ $isFiltered ? '0' : '1' }}" data-start-order="{{ max(0, ($banners->firstItem() ?? 1) - 1) }}">
                        @forelse($banners as $banner)
                            <tr class="text-nowrap" data-banner-id="{{ $banner->id }}">
                                <td class="ps-4">
                                    @unless($isFiltered)
                                        <span class="catalog-drag-handle cursor-grab" title="{{ __('catalog.actions.drag_sort') }}">
                                            <i class="ti ti-grip-vertical fs-5"></i>
                                        </span>
                                    @endunless
                                </td>
                                <td><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $banner->id }}" form="bulk-banners-form" data-bulk-select="bulk-banners-form" aria-label="{{ __('admin.bulk.select_item', ['name' => $banner->title ?: __('admin.banners.item_fallback')]) }}"></td>
                                <td class="ps-4">
                                    <div class="rounded border p-1 bg-light d-inline-block">
                                        <img src="{{ $banner->image_url }}" 
                                             alt="{{ $banner->title }}" 
                                             class="rounded" 
                                             width="140" height="70" 
                                             style="object-fit: cover; max-width: 140px;">
                                    </div>
                                </td>
                                <td>
                                    <h6 class="fw-semibold mb-1 fs-3">
                                        <a href="{{ route('admin.banners.edit', $banner) }}" class="text-decoration-none text-dark hover-primary">
                                            {{ $banner->title ?: __('admin.not_configured') }}
                                        </a>
                                    </h6>
                                    @if($banner->link_url)
                                        <a href="{{ $banner->link_url }}" target="_blank" class="text-muted small text-decoration-none d-block text-truncate" style="max-width: 300px;">
                                            <i class="ti ti-link me-1"></i>{{ $banner->link_url }}
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">
                                        {{ __('admin.banners.positions.' . $banner->position) }}
                                    </span>
                                </td>
                                <td>
                                    @include('admin.shared.status-switch', [
                                        'action' => route('admin.banners.bulk'),
                                        'id' => $banner->id,
                                        'active' => (bool) $banner->is_active,
                                        'can' => $canUpdateBanners,
                                        'activeLabel' => __('admin.banners.fields.active'),
                                        'inactiveLabel' => __('admin.banners.fields.inactive'),
                                    ])
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <x-admin.action-button :href="route('admin.banners.edit', $banner)" variant="outline-primary" size="sm" icon="edit" :title="__('catalog.actions.edit')" aria-label="{{ __('catalog.actions.edit') }}" />
                                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="d-inline js-delete-form" data-confirm-title="{{ __('admin.banners.confirm_delete') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-admin.action-button type="submit" variant="outline-danger" size="sm" icon="trash" :title="__('catalog.actions.delete')" aria-label="{{ __('catalog.actions.delete') }}" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">@include('admin.shared.empty-state', [
                                'emptyImage' => asset('admin-assets/images/icons/emptydata.png'),
                                'emptyMessage' => __('admin.banners.empty'),
                            ])</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($banners->total() > 0)
                <div class="p-4 border-top">
                    @include('admin.shared.pagination', ['paginator' => $banners])
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('admin-assets/libs/dragula/dist/dragula.min.js') }}"></script>
    <script>
        AdminAjax.ready(function (root) {
            const sortable = root.querySelector('#banner-table-sortable');
            const sortingEnabled = sortable && sortable.dataset.sortable === '1';
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
                }).fire({ icon: icon, title: message });
            };

            if (!sortingEnabled || !window.dragula || sortable.querySelectorAll('tr[data-banner-id]').length < 2) {
                return;
            }

            dragula([sortable], {
                moves: function (el, container, handle) {
                    return el.dataset.bannerId !== undefined && handle.closest('.catalog-drag-handle') !== null;
                }
            }).on('drop', function () {
                const ids = Array.from(sortable.querySelectorAll('tr[data-banner-id]')).map(function (tr) {
                    return tr.dataset.bannerId;
                });

                fetch(@json(route('admin.banners.sort')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token())
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
                        toast('success', payload.message);
                    })
                    .catch(function () {
                        toast('error', @json(__('admin.banners.messages.sort_failed')));
                    });
            });
        });
    </script>
@endpush
