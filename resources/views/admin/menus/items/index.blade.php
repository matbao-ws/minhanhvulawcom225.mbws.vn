@extends('admin.layouts.app')

@section('title', __('admin.menus.items.title', ['menu' => $menu->name]))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/dragula/dist/dragula.min.css') }}">
    <style>
        .menu-tree, .menu-tree ul { list-style: none; margin: 0; padding: 0; }
        .menu-tree ul { padding-left: 32px; }
        .menu-tree li { margin-bottom: 8px; }
        .menu-node {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            background: var(--bs-body-bg);
        }
        .menu-node .menu-drag-handle { cursor: grab; color: #8a94a6; }
        .menu-node.gu-transit { opacity: .4; }
        .menu-node-inactive { opacity: .65; }
    </style>
@endpush

@section('content')
<div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
    <div class="card-body px-4 py-3">
        <h4 class="fw-semibold mb-1 text-white">{{ __('admin.menus.items.title', ['menu' => $menu->name]) }}</h4>
        <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.menus.index') }}">{{ __('admin.menus.title') }}</a></li>
                <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ $menu->name }}</li>
            </ol>
        </nav>
    </div>
</div>
<div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">{{ __('admin.back') }}</a>
        @can('menus.update')
            <a href="{{ route('admin.menus.items.create', $menu) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>{{ __('admin.menus.items.create') }}
            </a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($items->isEmpty())
            <div class="text-center text-muted py-5">{{ __('admin.menus.items.empty') }}</div>
        @else
            @can('menus.update')
                <p class="text-muted fs-2 mb-3"><i class="ti ti-arrows-sort me-1"></i>{{ __('admin.menus.items.sort_hint') }}</p>
            @endcan

            @php
                // Rebuild the nesting from the flat, depth-annotated list the
                // service produced so the markup mirrors the real tree.
                $byParent = $items->groupBy('parent_id');
            @endphp

            <div id="menu-tree-root"
                 data-sort-url="{{ route('admin.menus.items.sort', $menu) }}"
                 data-sortable="{{ auth()->user()->can('menus.update') ? '1' : '0' }}">
                @include('admin.menus.items._branch', [
                    'branch' => $byParent->get(null) ?? collect(),
                    'byParent' => $byParent,
                    'menu' => $menu,
                    'parentId' => '',
                ])
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('admin-assets/libs/dragula/dist/dragula.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const root = document.getElementById('menu-tree-root');
            if (!root || root.dataset.sortable !== '1' || !window.dragula) return;

            const sortUrl = root.dataset.sortUrl;
            const containers = Array.from(root.querySelectorAll('ul.menu-sortable'));
            if (containers.length === 0) return;

            dragula(containers, {
                moves: function (el, source, handle) {
                    return handle.closest('.menu-drag-handle') !== null;
                },
                // Re-parenting is done in the item form, not by dragging, so a
                // drop is only accepted back into the list it came from.
                accepts: function (el, target, source) {
                    return target === source;
                },
            }).on('drop', function (el, target) {
                const ids = Array.from(target.children)
                    .map(function (li) { return Number(li.dataset.itemId); })
                    .filter(Boolean);

                fetch(sortUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: ids, start_order: 0 }),
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('sort failed');
                        return response.json();
                    })
                    .then(function (payload) {
                        if (window.toast) toast('success', payload.message);
                    })
                    .catch(function () {
                        if (window.toast) toast('error', @json(__('admin.menus.items.sort_failed')));
                    });
            });
        });
    </script>
@endpush
