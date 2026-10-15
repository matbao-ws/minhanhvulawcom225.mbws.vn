@extends('admin.layouts.app')

@section('title', __('redirects.title'))

@php
    $canCreate = auth()->user()?->can('redirects.create');
    $canUpdate = auth()->user()?->can('redirects.update');
    $canDelete = auth()->user()?->can('redirects.delete');
    $entityLabels = [
        \App\Models\Post::class => [__('redirects.entities.post'), 'title'],
        \App\Models\Page::class => [__('redirects.entities.page'), 'title'],
        \App\Models\Product::class => [__('redirects.entities.product'), 'name'],
        \App\Models\Category::class => [__('redirects.entities.category'), 'name'],
        \App\Models\PostCategory::class => [__('redirects.entities.post_category'), 'name'],
    ];
    $originBadges = ['manual' => 'bg-secondary-subtle text-secondary', 'wordpress' => 'bg-info-subtle text-info', 'not_found' => 'bg-warning-subtle text-warning'];
@endphp

@section('content')
    <x-admin.page-header :title="__('redirects.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('redirects.title') }}</li>
        </x-slot:breadcrumbs>
        <x-slot:help>
            <h6>{{ __('redirects.guide.what_title') }}</h6>
            <p class="mb-2">{{ __('redirects.guide.what') }}</p>
            <h6>{{ __('redirects.guide.codes_title') }}</h6>
            <ul>
                <li><code>301</code> {{ __('redirects.guide.code_301') }}</li>
                <li><code>302</code> / <code>307</code> {{ __('redirects.guide.code_302') }}</li>
                <li><code>410</code> {{ __('redirects.guide.code_410') }}</li>
            </ul>
            <h6>{{ __('redirects.guide.when_title') }}</h6>
            <ul>
                <li>{{ __('redirects.guide.when_wordpress') }}</li>
                <li>{{ __('redirects.guide.when_slug') }}</li>
                <li>{{ __('redirects.guide.when_404') }}</li>
            </ul>
        </x-slot:help>
        <x-slot:actions>
            @if($canCreate)
                <x-admin.action-button data-tour="redirects.add" type="button" icon="plus" data-redirect-open="create">{{ __('redirects.actions.add') }}</x-admin.action-button>
                <span data-tour="redirects.import" class="d-inline-flex gap-2">
                    <x-admin.action-button type="button" variant="light" icon="upload" data-bs-toggle="modal" data-bs-target="#redirectImportModal">{{ __('redirects.actions.import') }}</x-admin.action-button>
                    <x-admin.action-button :href="route('admin.redirects.export')" variant="light" icon="download">{{ __('redirects.actions.export') }}</x-admin.action-button>
                </span>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <ul data-tour="redirects.tabs" class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a @class(['nav-link', 'active' => $tab === 'redirects']) href="{{ route('admin.redirects.index') }}">
                {{ __('redirects.tabs.redirects') }} <span @class(['badge ms-1', 'bg-white text-primary' => $tab === 'redirects', 'bg-secondary-subtle text-secondary' => $tab !== 'redirects'])>{{ number_format($redirectCount) }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a @class(['nav-link', 'active' => $tab === 'not-found']) href="{{ route('admin.redirects.index', ['tab' => 'not-found']) }}">
                {{ __('redirects.tabs.not_found') }} @if($notFoundCount > 0)<span @class(['badge ms-1', 'bg-white text-primary' => $tab === 'not-found', 'bg-danger-subtle text-danger' => $tab !== 'not-found'])>{{ number_format($notFoundCount) }}</span>@endif
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-body">
            <form data-tour="redirects.search" method="GET" data-responsive-filters class="row g-2 align-items-end">
                @if($tab === 'not-found')<input type="hidden" name="tab" value="not-found">@endif
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark mb-1" for="redirect-search">{{ __('redirects.actions.search') }}</label>
                    <input type="search" id="redirect-search" name="q" class="form-control" value="{{ request('q') }}"
                           placeholder="{{ $tab === 'not-found' ? __('redirects.placeholders.search_404') : __('redirects.placeholders.search') }}">
                </div>
                @if($tab === 'redirects')
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark mb-1" for="redirect-origin">{{ __('redirects.fields.origin') }}</label>
                        <select id="redirect-origin" name="origin" class="form-select">
                            <option value="">{{ __('redirects.origins.all') }}</option>
                            @foreach(['manual', 'wordpress', 'not_found'] as $origin)
                                <option value="{{ $origin }}" @selected(request('origin') === $origin)>{{ __("redirects.origins.$origin") }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <x-admin.action-button type="submit" icon="search" class="w-100">{{ __('redirects.actions.search') }}</x-admin.action-button>
                </div>
            </form>
        </div>

        {{-- Search, pages, on/off, saving and delete update this part only (admin-ajax.js). --}}
        <div class="card-body p-0" data-ajax-list="redirects">
            @if($tab === 'redirects')
                @if($canUpdate)
                    @include('admin.shared.bulk-actions', [
                        'bulkFormId' => 'bulk-redirects-form',
                        'bulkActionUrl' => route('admin.redirects.bulk'),
                        'bulkItemLabel' => __('redirects.item_label'),
                    ])
                @endif
                <div data-tour="redirects.table" class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-nowrap">
                                @if($canUpdate)
                                    <th class="ps-4" style="width: 44px;"><input type="checkbox" class="form-check-input" data-bulk-select-all="bulk-redirects-form" aria-label="{{ __('redirects.item_label') }}"></th>
                                @endif
                                <th class="ps-4">{{ __('redirects.fields.source') }}</th>
                                <th>{{ __('redirects.fields.target') }}</th>
                                <th>{{ __('redirects.fields.status_code') }}</th>
                                <th>{{ __('redirects.fields.origin') }}</th>
                                <th class="text-end">{{ __('redirects.fields.hits') }}</th>
                                <th>{{ __('redirects.fields.is_active') }}</th>
                                <th class="text-end pe-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($redirectList as $redirect)
                                @php
                                    $target = $targets[$redirect->id] ?? null;
                                    $entity = $redirect->redirectable;
                                    [$entityType, $entityField] = $entity ? ($entityLabels[$entity::class] ?? [null, null]) : [null, null];
                                    $entityName = $entity && $entityField ? (method_exists($entity, 'getTranslation') ? $entity->getTranslation($entityField, app()->getLocale(), true) : $entity->{$entityField}) : null;
                                @endphp
                                <tr>
                                    @if($canUpdate)
                                        <td class="ps-4"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $redirect->id }}" form="bulk-redirects-form" data-bulk-select="bulk-redirects-form" aria-label="{{ $redirect->source_path }}"></td>
                                    @endif
                                    <td class="ps-4">
                                        <code class="text-dark text-break">{{ $redirect->source_path }}</code>
                                    </td>
                                    <td style="max-width: 360px;">
                                        @if($redirect->status_code === 410)
                                            <span class="text-muted">—</span>
                                        @elseif($target)
                                            <a href="{{ $target }}" target="_blank" rel="noopener" class="text-break">{{ $target }}</a>
                                        @else
                                            <span class="text-warning small">{{ __('redirects.no_target') }}</span>
                                        @endif
                                        @if($entityType)
                                            <div class="small text-muted" title="{{ __('redirects.help.entity') }}">
                                                <i class="ti ti-link me-1" aria-hidden="true"></i>{{ $entityType }}: {{ \Illuminate\Support\Str::limit((string) $entityName, 60) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap"><span class="badge bg-primary-subtle text-primary fw-semibold">{{ $redirect->status_code }}</span></td>
                                    <td class="text-nowrap"><span class="badge {{ $originBadges[$redirect->origin] ?? $originBadges['manual'] }} fw-semibold">{{ __('redirects.origins.'.$redirect->origin) }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <div class="fw-semibold">{{ number_format($redirect->hits) }}</div>
                                        <div class="small text-muted">{{ $redirect->last_hit_at?->diffForHumans() ?? __('redirects.state.never') }}</div>
                                    </td>
                                    <td>
                                        @include('admin.shared.status-switch', [
                                            'action' => route('admin.redirects.bulk'),
                                            'id' => $redirect->id,
                                            'active' => (bool) $redirect->is_active,
                                            'can' => $canUpdate,
                                            'activeLabel' => __('redirects.state.active'),
                                            'inactiveLabel' => __('redirects.state.inactive'),
                                        ])
                                    </td>
                                    <td class="text-end pe-4 text-nowrap">
                                        @if($canUpdate)
                                            <button type="button" class="btn btn-sm btn-outline-primary" title="{{ __('redirects.actions.edit') }}" aria-label="{{ __('redirects.actions.edit') }}"
                                                    data-redirect-open="edit"
                                                    data-action="{{ route('admin.redirects.update', $redirect) }}"
                                                    data-source="{{ $redirect->source_path }}"
                                                    data-target="{{ $target }}"
                                                    data-status="{{ $redirect->status_code }}"
                                                    data-active="{{ $redirect->is_active ? 1 : 0 }}">
                                                <i class="ti ti-edit fs-4"></i>
                                            </button>
                                        @endif
                                        @if($canDelete)
                                            <form action="{{ route('admin.redirects.destroy', $redirect) }}" method="POST" class="js-delete-form d-inline-block" data-confirm-text="{{ __('redirects.confirm_delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('redirects.actions.delete') }}" aria-label="{{ __('redirects.actions.delete') }}"><i class="ti ti-trash fs-4"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($redirectList->isEmpty())
                    @include('admin.shared.empty-state', ['emptyMessage' => __('redirects.empty')])
                @endif
                @if($redirectList->total() > 0)
                    <div class="card-body border-top">@include('admin.shared.pagination', ['paginator' => $redirectList])</div>
                @endif
            @else
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-4 py-3 border-bottom">
                    <p class="small text-muted mb-0">{{ __('redirects.help.not_found') }}</p>
                    @if($canDelete && $notFoundCount > 0)
                        <form action="{{ route('admin.redirects.not-found.clear') }}" method="POST" class="js-delete-form" data-confirm-text="{{ __('redirects.confirm_clear_404') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>{{ __('redirects.actions.clear_404') }}</button>
                        </form>
                    @endif
                </div>
                <div data-tour="redirects.table" class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-nowrap">
                                <th class="ps-4">{{ __('redirects.fields.path') }}</th>
                                <th class="text-end">{{ __('redirects.fields.hits') }}</th>
                                <th>{{ __('redirects.fields.last_hit') }}</th>
                                <th>{{ __('redirects.fields.referer') }}</th>
                                <th class="text-end pe-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notFoundList as $log)
                                <tr>
                                    <td class="ps-4"><code class="text-dark text-break">{{ $log->path }}</code></td>
                                    <td class="text-end fw-semibold">{{ number_format($log->hits) }}</td>
                                    <td class="text-nowrap small">{{ $log->last_seen_at?->diffForHumans() }}</td>
                                    <td class="small text-muted text-break" style="max-width: 260px;">{{ $log->referer ?: '—' }}</td>
                                    <td class="text-end pe-4 text-nowrap">
                                        @if($canCreate)
                                            <button type="button" class="btn btn-sm btn-primary" data-redirect-open="create" data-source="{{ $log->path }}" data-not-found-id="{{ $log->id }}">
                                                <i class="ti ti-arrow-forward-up me-1"></i>{{ __('redirects.actions.create_from_404') }}
                                            </button>
                                        @endif
                                        @if($canDelete)
                                            <form action="{{ route('admin.redirects.not-found.destroy', $log) }}" method="POST" class="d-inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('redirects.actions.delete') }}" aria-label="{{ __('redirects.actions.delete') }}"><i class="ti ti-trash fs-4"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($notFoundList->isEmpty())
                    @include('admin.shared.empty-state', ['emptyMessage' => __('redirects.empty_404')])
                @endif
                @if($notFoundList->total() > 0)
                    <div class="card-body border-top">@include('admin.shared.pagination', ['paginator' => $notFoundList])</div>
                @endif
            @endif
        </div>
    </div>

    @if($canCreate || $canUpdate)
        {{-- One form for create and edit; the open button fills it. --}}
        <div class="modal fade" id="redirectModal" tabindex="-1" aria-labelledby="redirectModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('admin.redirects.store') }}" class="modal-content" data-redirect-form data-ajax-form data-store-action="{{ route('admin.redirects.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" data-redirect-method>
                    <input type="hidden" name="not_found_id" value="{{ old('not_found_id') }}" data-redirect-not-found>
                    {{-- The edit URL, so a form sent back with errors reopens as the same edit. --}}
                    <input type="hidden" name="_redirect_edit" value="{{ old('_redirect_edit') }}" data-redirect-edit>
                    <div class="modal-header">
                        <h5 class="modal-title" id="redirectModalTitle" data-redirect-title data-create="{{ __('redirects.modal.create') }}" data-edit="{{ __('redirects.modal.edit') }}">{{ __('redirects.modal.create') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('redirects.actions.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="redirect-source">{{ __('redirects.fields.source') }}</label>
                            <input type="text" id="redirect-source" name="source" class="form-control @error('source') is-invalid @enderror" value="{{ old('source') }}" maxlength="500" required placeholder="{{ __('redirects.placeholders.source') }}">
                            @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('redirects.help.source') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="redirect-status">{{ __('redirects.fields.status_code') }}</label>
                            <select id="redirect-status" name="status_code" class="form-select" data-redirect-status>
                                @foreach(\App\Models\Redirect::STATUS_CODES as $code)
                                    <option value="{{ $code }}" @selected((int) old('status_code', 301) === $code)>{{ __("redirects.statuses.$code") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3" data-redirect-target-group>
                            <label class="form-label" for="redirect-target">{{ __('redirects.fields.target') }}</label>
                            <input type="text" id="redirect-target" name="target" class="form-control @error('target') is-invalid @enderror" value="{{ old('target') }}" maxlength="2048" placeholder="{{ __('redirects.placeholders.target') }}">
                            @error('target')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('redirects.help.target') }}</div>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="redirect-active" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
                            <label class="form-check-label fw-semibold" for="redirect-active">{{ __('redirects.fields.is_active') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('redirects.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('redirects.actions.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canCreate)
        <div class="modal fade" id="redirectImportModal" tabindex="-1" aria-labelledby="redirectImportTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="redirectImportTitle">{{ __('redirects.modal.import') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('redirects.actions.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="redirect-file">{{ __('redirects.fields.file') }}</label>
                        <input type="file" id="redirect-file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".csv,text/csv,text/plain" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('redirects.help.import') }}</div>
                        <pre class="bg-light rounded p-2 small mt-2 mb-0">source,target,status
/ten-bai-cu/,/bai-viet/ten-bai-moi,301
/trang-da-xoa/,,410</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('redirects.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('redirects.actions.import') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('redirectModal');
            if (!modalElement || !window.bootstrap) return;
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const form = modalElement.querySelector('[data-redirect-form]');
            const title = modalElement.querySelector('[data-redirect-title]');
            const status = form.querySelector('[data-redirect-status]');
            const targetGroup = form.querySelector('[data-redirect-target-group]');
            let focusField = '[name="source"]';
            // After the opening animation: Bootstrap focuses the dialog itself when it is shown.
            modalElement.addEventListener('shown.bs.modal', function () {
                form.querySelector(focusField)?.focus();
            });

            // 410 (gone) has no new link.
            const syncTarget = function () {
                targetGroup.classList.toggle('d-none', status.value === '410');
            };
            status.addEventListener('change', syncTarget);

            // On the document: rows swapped in by admin-ajax.js open it too.
            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-redirect-open]');
                if (!button) return;
                {
                    const editing = button.dataset.redirectOpen === 'edit';
                    form.action = editing ? button.dataset.action : form.dataset.storeAction;
                    form.querySelector('[data-redirect-method]').value = editing ? 'PUT' : 'POST';
                    form.querySelector('[data-redirect-not-found]').value = button.dataset.notFoundId || '';
                    form.querySelector('[data-redirect-edit]').value = editing ? button.dataset.action : '';
                    title.textContent = editing ? title.dataset.edit : title.dataset.create;
                    form.querySelector('[name="source"]').value = button.dataset.source || '';
                    form.querySelector('[name="target"]').value = button.dataset.target || '';
                    status.value = button.dataset.status || '301';
                    form.querySelector('#redirect-active').checked = (button.dataset.active || '1') === '1';
                    form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
                    syncTarget();
                    focusField = button.dataset.source ? '[name="target"]' : '[name="source"]';
                    modal.show();
                }
            });

            syncTarget();
            @if($errors->hasAny(['source', 'target', 'status_code']))
                // The form came back with errors: show it again as it was sent.
                const editUrl = form.querySelector('[data-redirect-edit]').value;
                if (editUrl) {
                    form.action = editUrl;
                    form.querySelector('[data-redirect-method]').value = 'PUT';
                    title.textContent = title.dataset.edit;
                }
                modal.show();
            @endif
            @if($errors->has('file'))
                bootstrap.Modal.getOrCreateInstance(document.getElementById('redirectImportModal')).show();
            @endif
        });
    </script>
@endpush
