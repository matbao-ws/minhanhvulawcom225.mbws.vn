@extends('admin.layouts.app')

@section('title', __('catalog.categories.title'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/dragula/dist/dragula.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/quill/dist/quill.snow.css') }}">
    <style>
        /* Dynamic table borders for drag-and-drop */
        #category-table-sortable tbody.category-group-sortable tr td {
            border-bottom-width: 0px !important;
        }
        #category-table-sortable tbody.category-group-sortable tr:last-child td {
            border-bottom-width: 1px !important;
            border-bottom-style: solid !important;
            border-bottom-color: var(--bs-border-color, #e9edf0) !important;
        }
    </style>
@endpush

@section('content')
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3 d-flex align-items-center justify-content-between">
                    <div>
                <h4 class="fw-semibold mb-1 text-white">{{ __('catalog.categories.title') }}</h4>
                <div class="text-white-50">{{ __('catalog.categories.subtitle') }}</div>
            </div>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">{{ __('catalog.categories.create') }}</a>
                </div>
            </div>
        </div>
    </div>

    @php $isFiltered = $isFiltered ?? false; @endphp

    <div class="card">
        <div class="card-body">
            <form method="GET" data-responsive-filters class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1" for="filter_q">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" id="filter_q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('catalog.categories.search_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1" for="filter_parent_id">{{ __('catalog.fields.parent_category') }}</label>
                    <select name="parent_id" id="filter_parent_id" class="form-select">
                        <option value="">{{ __('catalog.filters.all') }}</option>
                        <option value="root" @selected(request('parent_id') === 'root')>{{ __('catalog.filters.root_only') }}</option>
                        @foreach($parentOptions as $parent)
                            <option value="{{ $parent->id }}" @selected((string) request('parent_id') === (string) $parent->id)>
                                {!! str_repeat('&nbsp;&nbsp;', $parent->depth ?? 0) !!}{{ $parent->depth ? '↳ ' : '' }}{{ $parent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1" for="filter_status">{{ __('catalog.fields.status') }}</label>
                    <select name="status" id="filter_status" class="form-select">
                        <option value="">{{ __('catalog.filters.all') }}</option>
                        <option value="1" @selected(request('status') === '1')>{{ __('catalog.status.active') }}</option>
                        <option value="0" @selected(request('status') === '0')>{{ __('catalog.status.inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1" for="filter_draft">{{ __('catalog.fields.save_draft') }}</label>
                    <select name="draft" id="filter_draft" class="form-select">
                        <option value="">{{ __('catalog.filters.all') }}</option>
                        <option value="1" @selected(request('draft') === '1')>{{ __('catalog.filters.draft_only') }}</option>
                        <option value="0" @selected(request('draft') === '0')>{{ __('catalog.filters.published_only') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">{{ __('catalog.actions.search') }}</button>
                    @if($isFiltered)
                        <a href="{{ route('admin.categories.index') }}" class="btn bg-secondary-subtle text-secondary" title="{{ __('catalog.actions.reset_filters') }}">
                            <i class="ti ti-x"></i>
                        </a>
                    @endif
                </div>
            </form>
            @if($isFiltered)
                <div class="text-muted fs-2 mt-2">
                    <i class="ti ti-info-circle me-1"></i>{{ __('catalog.categories.filtered_notice') }}
                </div>
            @endif
        </div>
        @include('admin.shared.bulk-actions', [
            'bulkFormId' => 'bulk-categories-form',
            'bulkActionUrl' => route('admin.categories.bulk'),
            'bulkItemLabel' => 'danh mục',
        ])
        <div class="table-responsive">
            <table class="table text-nowrap mb-0 align-middle" id="category-table-sortable" data-start-order="{{ max(0, ($categories->firstItem() ?? 1) - 1) }}">
                <thead class="text-dark fs-4">
                <tr>
                    <th class="ps-4" style="width: 44px;"><input type="checkbox" class="form-check-input" data-bulk-select-all="bulk-categories-form" aria-label="Chọn tất cả danh mục"></th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.category') }}</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.parent_category') }}</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.status') }}</h6>
                    </th>
                    <th class="text-center">
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.products_count') }}</h6>
                    </th>
                    <th class="text-center">
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.actions') }}</h6>
                    </th>
                </tr>
                </thead>
                @php $isFirstTbody = true; @endphp
                @forelse($categories as $category)
                    @php
                        $categoryName = $category->getTranslation('name', app()->getLocale(), false) ?: $category->name;
                        $categoryDescription = $category->getTranslation('description', app()->getLocale(), false) ?: '';
                    @endphp
                    @if(($category->depth ?? 0) === 0)
                        @if(!$isFirstTbody)
                            </tbody>
                        @endif
                        @php $isFirstTbody = false; @endphp
                        <tbody class="category-group-sortable" data-category-id="{{ $category->id }}">
                    @endif
                    <tr data-category-id="{{ $category->id }}" data-depth="{{ $category->depth ?? 0 }}">
                        <td class="ps-4"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $category->id }}" form="bulk-categories-form" data-bulk-select="bulk-categories-form" aria-label="Chọn {{ $categoryName }}"></td>
                        <td>
                            <div class="d-flex align-items-center" style="padding-left: {{ ($category->depth ?? 0) * 32 }}px;">
                                @if(($category->depth ?? 0) > 0)
                                    <span class="text-muted me-2" style="font-family: monospace; font-weight: bold;">↳</span>
                                @endif
                                @unless($isFiltered)
                                    <span class="catalog-drag-handle me-2 cursor-grab" title="{{ __('catalog.actions.drag_sort') }}">
                                        <i class="ti ti-grip-vertical fs-5"></i>
                                    </span>
                                @endunless
                                @if($category->image_url)
                                    <img src="{{ $category->image_url }}" alt="{{ $categoryName }}" class="rounded-2 object-fit-cover" width="42" height="42" onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/404.png') }}';">
                                @else
                                    <img src="{{ asset('admin-assets/js/icons/empty.png') }}" alt="empty" class="rounded-2 object-fit-cover" width="42" height="42">
                                @endif
                                <div class="ms-3">
                                    <h6 class="fw-semibold mb-1">{{ $categoryName }}</h6>
                                    <span class="fw-normal text-muted">{{ $category->slug }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if($category->parent)
                                    <span class="badge bg-primary-subtle text-primary">
                                        {{ $category->parent->name }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        {{ __('catalog.common.none') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $category->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ $category->is_active ? __('catalog.status.active') : __('catalog.status.inactive') }}
                            </span>
                            @if($category->is_draft)
                                <span class="badge bg-warning-subtle text-warning">{{ __('catalog.status.draft') }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <p class="mb-0 fw-bold text-primary">{{ $category->total_products_count ?? $category->products_count }}</p>
                        </td>
                        <td class="text-center">
                            <div class="dropdown dropstart">
                                <a href="javascript:void(0)" class="text-muted" id="categoryAction{{ $category->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ti ti-dots fs-5"></i>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="categoryAction{{ $category->id }}">
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item d-flex align-items-center gap-3 js-category-quick-edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#quickEditCategoryModal"
                                            data-id="{{ $category->id }}"
                                            data-name="{{ $categoryName }}"
                                            data-slug="{{ $category->slug }}"
                                            data-parent-id="{{ $category->parent_id }}"
                                            data-description="{{ $categoryDescription }}"
                                            data-is-active="{{ $category->is_active ? 1 : 0 }}"
                                            data-is-draft="{{ $category->is_draft ? 1 : 0 }}"
                                            data-image-url="{{ $category->image_url }}"
                                        >
                                            <i class="fs-4 ti ti-bolt"></i>{{ __('catalog.actions.quick_edit') }}
                                        </button>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-3" href="{{ route('admin.categories.edit', $category) }}">
                                            <i class="fs-4 ti ti-edit"></i>{{ __('catalog.actions.edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item d-flex align-items-center gap-3 text-danger">
                                                <i class="fs-4 ti ti-trash"></i>{{ __('catalog.actions.delete') }}
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                @endforelse
                @if(!$categories->isEmpty())
                    </tbody>
                @endif
            </table>
        </div>
        @if($categories->isEmpty())
            @include('admin.shared.empty-state')
        @endif
        <div class="card-body">
            @include('admin.shared.pagination', ['paginator' => $categories])
        </div>
    </div>

    <div class="modal fade" id="quickEditCategoryModal" tabindex="-1" aria-labelledby="quickEditCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="" class="modal-content" enctype="multipart/form-data" id="quickEditCategoryForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="quickEditCategoryModalLabel">{{ __('catalog.categories.quick_edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('catalog.actions.cancel') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label" for="quick_name">{{ __('catalog.fields.name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="quick_name" name="name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="quick_slug">{{ __('catalog.fields.slug') }}</label>
                            <input type="text" class="form-control" id="quick_slug" name="slug">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="quick_parent_id">{{ __('catalog.fields.parent_category') }}</label>
                            <select class="form-select" id="quick_parent_id" name="parent_id">
                                <option value="">{{ __('catalog.common.none') }}</option>
                                @foreach($parentOptions as $parent)
                                    <option value="{{ $parent->id }}">
                                        {!! str_repeat('&nbsp;&nbsp;', $parent->depth ?? 0) !!}{{ $parent->depth ? '↳ ' : '' }}{{ $parent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="quick_image_file">{{ __('catalog.fields.image') }}</label>
                            <input type="file" class="form-control" id="quick_image_file" name="image_file" accept="image/*" data-media-folder="categories">
                        </div>
                        <div class="col-12 mb-3 d-none" id="quickImagePreviewWrap">
                            <img src="" alt="" id="quickImagePreview" class="rounded border object-fit-cover" width="72" height="72" onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/404.png') }}';">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label" for="quick_description">{{ __('catalog.fields.description') }}</label>
                            <textarea class="form-control d-none" id="quick_description" name="description"></textarea>
                            <div id="quick_description_editor" class="catalog-quill" data-target="quick_description"></div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input primary" type="checkbox" name="is_active" value="1" id="quick_is_active">
                                <label class="form-check-label" for="quick_is_active">{{ __('catalog.fields.visible') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="is_draft" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input primary" type="checkbox" name="is_draft" value="1" id="quick_is_draft">
                                <label class="form-check-label" for="quick_is_draft">{{ __('catalog.fields.save_draft') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-secondary-subtle text-secondary" data-bs-dismiss="modal">{{ __('catalog.actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('catalog.actions.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('admin-assets/libs/dragula/dist/dragula.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/quill/dist/quill.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sortable = document.getElementById('category-table-sortable');
            const csrfToken = @json(csrf_token());
            const sortUrl = @json(route('admin.categories.sort'));
            const quickUpdateUrlTemplate = @json(route('admin.categories.quick-update', ['category' => '__CATEGORY_ID__']));

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

            // Initialize Quick Edit Quill editor
            const quickEditorElement = document.getElementById('quick_description_editor');
            if (quickEditorElement && window.Quill) {
                const target = document.getElementById(quickEditorElement.dataset.target);
                const quill = new Quill(quickEditorElement, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['link', 'clean']
                        ]
                    }
                });
                quickEditorElement.__quill = quill;

                const form = quickEditorElement.closest('form');
                if (form && target) {
                    form.addEventListener('submit', function () {
                        target.value = quill.root.innerHTML;
                    });
                }
            }

            const sortingEnabled = @json(! $isFiltered);

            if (sortingEnabled && sortable && sortable.querySelector('tbody.category-group-sortable') && window.dragula) {
                // Dragula initialization for reordering root categories (tbody elements)
                dragula([sortable], {
                    moves: function (el, container, handle) {
                        const tr = handle.closest('tr');
                        return el.classList.contains('category-group-sortable') && tr !== null && tr.dataset.depth === '0';
                    },
                    accepts: function (el, target, source, sibling) {
                        return sibling === null || sibling.tagName === 'TBODY';
                    }
                }).on('drop', function () {
                    const ids = Array.from(sortable.querySelectorAll('tbody.category-group-sortable')).map(function (tbody) {
                        return tbody.dataset.categoryId;
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
                            toast('success', payload.message || @json(__('catalog.categories.sorted')));
                        })
                        .catch(function () {
                            toast('error', @json(__('catalog.categories.sort_failed')));
                        });
                });

                // Dragula initialization for subcategories (tr elements inside each tbody container)
                const tbodyContainers = Array.from(sortable.querySelectorAll('tbody.category-group-sortable'));
                if (tbodyContainers.length > 0) {
                    dragula(tbodyContainers, {
                        moves: function (el, container, handle) {
                            return el.tagName === 'TR' && el.dataset.depth !== '0' && handle.closest('.catalog-drag-handle') !== null;
                        },
                        accepts: function (el, target, source, sibling) {
                            if (target !== source) return false;
                            if (sibling && sibling.dataset.depth === '0') return false;
                            return true;
                        }
                    }).on('drop', function (el, target, source, sibling) {
                        const childIds = Array.from(target.querySelectorAll('tr'))
                            .filter(function (row) {
                                return row.dataset.depth !== '0';
                            })
                            .map(function (row) {
                                return row.dataset.categoryId;
                            });

                        fetch(sortUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                ids: childIds,
                                start_order: 0
                            })
                        })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Sort failed');
                            }
                            return response.json();
                        })
                        .then(function (payload) {
                            toast('success', payload.message || @json(__('catalog.categories.sorted')));
                        })
                        .catch(function () {
                            toast('error', @json(__('catalog.categories.sort_failed')));
                        });
                    });
                }
            }

            document.querySelectorAll('.js-category-quick-edit').forEach(function (button) {
                button.addEventListener('click', function () {
                    const form = document.getElementById('quickEditCategoryForm');
                    const parentSelect = document.getElementById('quick_parent_id');
                    const imageUrl = button.dataset.imageUrl || '';
                    const previewWrap = document.getElementById('quickImagePreviewWrap');
                    const preview = document.getElementById('quickImagePreview');

                    form.action = quickUpdateUrlTemplate.replace('__CATEGORY_ID__', button.dataset.id);
                    document.getElementById('quick_name').value = button.dataset.name || '';
                    document.getElementById('quick_slug').value = button.dataset.slug || '';
                    
                    const descriptionVal = button.dataset.description || '';
                    document.getElementById('quick_description').value = descriptionVal;
                    const quickEditor = document.getElementById('quick_description_editor');
                    if (quickEditor && quickEditor.__quill) {
                        quickEditor.__quill.root.innerHTML = descriptionVal;
                    }

                    document.getElementById('quick_is_active').checked = button.dataset.isActive === '1';
                    document.getElementById('quick_is_draft').checked = button.dataset.isDraft === '1';
                    document.getElementById('quick_image_file').value = '';

                    Array.from(parentSelect.options).forEach(function (option) {
                        option.disabled = option.value === button.dataset.id;
                    });
                    parentSelect.value = button.dataset.parentId || '';

                    if (imageUrl) {
                        preview.src = imageUrl;
                        preview.alt = button.dataset.name || '';
                        previewWrap.classList.remove('d-none');
                    } else {
                        preview.src = '';
                        preview.alt = '';
                        previewWrap.classList.add('d-none');
                    }
                });
            });
        });
    </script>
@endpush
