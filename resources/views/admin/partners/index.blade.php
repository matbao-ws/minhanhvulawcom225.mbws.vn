@extends('admin.layouts.app')

@section('title', __('partners.title'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/dragula/dist/dragula.min.css') }}">
@endpush

@section('content')
    <x-admin.page-header :title="__('partners.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('partners.title') }}</li>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <x-admin.action-button data-tour="partners.groups" :href="route('admin.partner-groups.index')" variant="light" icon="folders">
                {{ __('partners.groups.title') }}
            </x-admin.action-button>
            @can('partners.create')
                <x-admin.action-button data-tour="partners.create" :href="route('admin.partners.create', $activeGroup ? ['group' => $activeGroup->id] : [])" icon="plus">
                    {{ __('partners.create') }}
                </x-admin.action-button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <p class="text-muted mb-3">{{ __('partners.intro') }}</p>

    <div class="card">
        {{-- One tab per group: what the theme shows in one logo strip. --}}
        <div class="card-body border-bottom pb-0 pt-3 px-4">
            <ul data-tour="partners.tabs" class="nav nav-tabs border-bottom-0 flex-nowrap overflow-auto">
                <li class="nav-item">
                    <a class="nav-link {{ $activeGroup ? '' : 'active' }}" href="{{ route('admin.partners.index', array_filter(['q' => request('q'), 'status' => request('status')], 'filled')) }}">
                        {{ __('partners.all_groups') }}
                    </a>
                </li>
                @foreach($groups as $group)
                    <li class="nav-item">
                        <a class="nav-link text-nowrap {{ $activeGroup?->id === $group->id ? 'active' : '' }}" href="{{ route('admin.partners.index', array_filter(['group' => $group->id, 'q' => request('q'), 'status' => request('status')], 'filled')) }}">
                            {{ $group->name }} <span class="badge bg-light text-muted ms-1">{{ $group->partners_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body border-bottom p-4">
            <form data-tour="partners.filters" method="GET" data-responsive-filters class="row g-2 align-items-end">
                @if($activeGroup)
                    <input type="hidden" name="group" value="{{ $activeGroup->id }}">
                @endif
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1" for="partner-search">{{ __('partners.search') }}</label>
                    <input type="search" id="partner-search" name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('partners.search_placeholder') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1" for="partner-status">{{ __('partners.fields.status') }}</label>
                    <select id="partner-status" name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected(request('status') === '1')>{{ __('partners.status.active') }}</option>
                        <option value="0" @selected(request('status') === '0')>{{ __('partners.status.inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <x-admin.action-button type="submit" icon="search" class="w-100">
                        {{ __('catalog.actions.search') }}
                    </x-admin.action-button>
                </div>
            </form>
            @if(! $sortable && $partners->total() > 1)
                <p class="small text-muted mt-3 mb-0"><i class="ti ti-info-circle me-1"></i>{{ __('partners.sort_hint') }}</p>
            @endif
        </div>

        {{-- Filters, pages, show/hide and delete update this part only (admin-ajax.js). --}}
        @php
            $canUpdatePartners = (bool) auth()->user()?->can('partners.update');
            $canDeletePartners = (bool) auth()->user()?->can('partners.delete');
        @endphp
        <div class="card-body p-0" data-ajax-list="partners">
            @include('admin.shared.bulk-actions', [
                'bulkFormId' => 'bulk-partners-form',
                'bulkActionUrl' => route('admin.partners.bulk'),
                'bulkItemLabel' => __('partners.bulk_label'),
            ])
            <div data-tour="partners.table" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap text-muted">
                            <th class="ps-4" style="width: 44px;"></th>
                            <th data-tour="partners.select-all" style="width: 44px;"><input type="checkbox" class="form-check-input" data-bulk-select-all="bulk-partners-form" aria-label="{{ __('partners.select_all') }}"></th>
                            <th class="fw-semibold small" style="width: 160px;">{{ __('partners.fields.logo') }}</th>
                            <th class="fw-semibold small">{{ __('partners.fields.name') }}</th>
                            <th class="fw-semibold small">{{ __('partners.fields.group') }}</th>
                            <th class="fw-semibold small" style="width: 150px;">{{ __('partners.fields.status') }}</th>
                            <th class="pe-4 text-end" style="width: 120px;"></th>
                        </tr>
                    </thead>
                    <tbody id="partner-table-sortable" data-sortable="{{ $sortable ? '1' : '0' }}" data-start-order="{{ max(0, ($partners->firstItem() ?? 1) - 1) }}">
                        @forelse($partners as $partner)
                            <tr class="text-nowrap" data-partner-id="{{ $partner->id }}">
                                <td class="ps-4">
                                    @if($sortable && $canUpdatePartners)
                                        <span class="catalog-drag-handle cursor-grab" title="{{ __('catalog.actions.drag_sort') }}">
                                            <i class="ti ti-grip-vertical fs-5"></i>
                                        </span>
                                    @endif
                                </td>
                                <td><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $partner->id }}" form="bulk-partners-form" data-bulk-select="bulk-partners-form" aria-label="{{ __('admin.bulk.select_item', ['name' => $partner->name]) }}"></td>
                                <td>
                                    <div class="rounded border p-1 bg-light d-inline-flex align-items-center justify-content-center" style="width: 132px; height: 60px;">
                                        @if($partner->logo_path)
                                            <img src="{{ $partner->logo_path }}" alt="{{ $partner->name }}" style="max-width: 124px; max-height: 52px; object-fit: contain;"
                                                 onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/404.png') }}';">
                                        @else
                                            <span class="small text-muted">{{ __('partners.no_logo') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <h6 class="fw-semibold mb-1 fs-3">
                                        @if($canUpdatePartners)
                                            <a href="{{ route('admin.partners.edit', $partner) }}" class="text-decoration-none text-dark hover-primary">{{ $partner->name }}</a>
                                        @else
                                            {{ $partner->name }}
                                        @endif
                                    </h6>
                                    @if($partner->url)
                                        <a href="{{ $partner->url }}" target="_blank" rel="noopener noreferrer" class="text-muted small text-decoration-none d-block text-truncate" style="max-width: 300px;">
                                            <i class="ti ti-link me-1"></i>{{ $partner->url }}
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">{{ $partner->group?->name }}</span>
                                </td>
                                <td>
                                    @include('admin.shared.status-switch', [
                                        'action' => route('admin.partners.bulk'),
                                        'id' => $partner->id,
                                        'active' => (bool) $partner->is_active,
                                        'can' => $canUpdatePartners,
                                        'activeLabel' => __('partners.status.active'),
                                        'inactiveLabel' => __('partners.status.inactive'),
                                    ])
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        @if($canUpdatePartners)
                                            <x-admin.action-button :href="route('admin.partners.edit', $partner)" variant="outline-primary" size="sm" icon="edit" :title="__('catalog.actions.edit')" aria-label="{{ __('catalog.actions.edit') }}" />
                                        @endif
                                        @if($canDeletePartners)
                                            <form method="POST" action="{{ route('admin.partners.destroy', $partner) }}" class="d-inline js-delete-form" data-confirm-title="{{ __('partners.confirm_delete', ['name' => $partner->name]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-admin.action-button type="submit" variant="outline-danger" size="sm" icon="trash" :title="__('catalog.actions.delete')" aria-label="{{ __('catalog.actions.delete') }}" />
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">@include('admin.shared.empty-state', [
                                'emptyImage' => asset('admin-assets/images/icons/emptydata.png'),
                                'emptyMessage' => $groups->isEmpty() ? __('partners.empty_no_group') : ($isFiltered ? __('partners.empty_filtered') : __('partners.empty')),
                            ])</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($partners->total() > 0)
                <div class="p-4 border-top">
                    @include('admin.shared.pagination', ['paginator' => $partners])
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('admin-assets/libs/dragula/dist/dragula.min.js') }}"></script>
    <script>
        AdminAjax.ready(function (root) {
            const sortable = root.querySelector('#partner-table-sortable');
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

            if (!sortingEnabled || !window.dragula || sortable.querySelectorAll('tr[data-partner-id]').length < 2) {
                return;
            }

            dragula([sortable], {
                moves: function (el, container, handle) {
                    return el.dataset.partnerId !== undefined && handle.closest('.catalog-drag-handle') !== null;
                }
            }).on('drop', function () {
                const ids = Array.from(sortable.querySelectorAll('tr[data-partner-id]')).map(function (tr) {
                    return tr.dataset.partnerId;
                });

                fetch(@json(route('admin.partners.sort')), {
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
                        toast('error', @json(__('partners.messages.sort_failed')));
                    });
            });
        });
    </script>
@endpush
