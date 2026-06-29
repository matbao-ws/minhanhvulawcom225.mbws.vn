@extends('admin.layouts.app')

@section('title', __('admin.blog_categories.title'))

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
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fw-semibold mb-1">{{ __('admin.blog_categories.title') }}</h4>
                <div class="text-muted">{{ __('admin.blog_categories.drag_subtitle') }}</div>
            </div>
            <a href="{{ route('admin.post-categories.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="ti ti-plus fs-4"></i>{{ __('admin.blog_categories.create') }}
            </a>
        </div>
    </div>

    <!-- Success/Error Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-check fs-5"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-alert-circle fs-5"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Categories List Card -->
    <div class="card">
        <div class="card-body border-bottom p-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-11">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('admin.blog_categories.search_placeholder') }}">
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
                <table class="table text-nowrap mb-0 align-middle" id="category-table-sortable" data-start-order="{{ max(0, ($categories->firstItem() ?? 1) - 1) }}">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.blog_categories.title') }}</th>
                            <th>{{ __('catalog.fields.parent_category') }}</th>
                            <th>{{ __('admin.blog_categories.fields.status') }}</th>
                            <th class="text-center">{{ __('catalog.fields.actions') }}</th>
                        </tr>
                    </thead>
                    @php $isFirstTbody = true; @endphp
                    @forelse($categories as $category)
                        @php
                            $fallbackLocale = config('app.fallback_locale', config('app.locale', 'vi'));
                            $categoryName = $category->getTranslation('name', app()->getLocale(), false) ?: $category->getTranslation('name', $fallbackLocale, false);
                            $categoryDescription = $category->getTranslation('description', app()->getLocale(), false) ?: $category->getTranslation('description', $fallbackLocale, false) ?: '';
                        @endphp
                        @if(($category->depth ?? 0) === 0)
                            @if(!$isFirstTbody)
                                </tbody>
                            @endif
                            @php $isFirstTbody = false; @endphp
                            <tbody class="category-group-sortable" data-category-id="{{ $category->id }}">
                        @endif
                        <tr data-category-id="{{ $category->id }}" data-depth="{{ $category->depth ?? 0 }}">
                            <td>
                                <div class="d-flex align-items-center" style="padding-left: {{ ($category->depth ?? 0) * 32 }}px;">
                                    @if(($category->depth ?? 0) > 0)
                                        <span class="text-muted me-2" style="font-family: monospace; font-weight: bold;">↳</span>
                                        <span class="catalog-drag-handle me-2 cursor-grab" title="{{ __('catalog.actions.drag_sort') }}">
                                            <i class="ti ti-grip-vertical fs-5"></i>
                                        </span>
                                    @else
                                        <span class="catalog-drag-handle me-2 cursor-grab" title="{{ __('catalog.actions.drag_sort') }}">
                                            <i class="ti ti-grip-vertical fs-5"></i>
                                        </span>
                                    @endif
                                    <div class="ms-2">
                                        <h6 class="fw-semibold mb-1 fs-3">{{ $categoryName }}</h6>
                                        <span class="fs-2 text-muted">{{ $category->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($category->parent)
                                    <span class="badge bg-primary-subtle text-primary">
                                        {{ $category->parent->getTranslation('name', app()->getLocale(), false) ?: $category->parent->getTranslation('name', $fallbackLocale, false) }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">{{ __('catalog.common.none') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $category->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                    {{ $category->is_active ? __('admin.blog_categories.fields.active') : __('admin.blog_categories.fields.inactive') }}
                                </span>
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
                                            >
                                                <i class="fs-4 ti ti-bolt"></i>{{ __('catalog.actions.quick_edit') }}
                                            </button>
                                        </li>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center gap-3" href="{{ route('admin.post-categories.edit', $category) }}">
                                                <i class="fs-4 ti ti-edit"></i>{{ __('admin.blog_categories.edit_details') }}
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.post-categories.destroy', $category) }}" class="js-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item d-flex align-items-center gap-3 text-danger" onclick="return confirm('{{ __('admin.blog_categories.confirm_delete') }}')">
                                                    <i class="fs-4 ti ti-trash"></i>{{ __('admin.blog_categories.delete_category') }}
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tbody id="category-empty-body">
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <iconify-icon icon="solar:widget-broken" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                                    <p class="text-muted mb-0 fs-3">{{ __('admin.blog_categories.not_found') }}</p>
                                </td>
                            </tr>
                        </tbody>
                    @endforelse
                    @if(!$categories->isEmpty())
                        </tbody>
                    @endif
                </table>
            </div>
            <div class="card-body">
                {{ $categories->links() }}
            </div>
        </div>
    </div>

    <!-- Quick Edit Modal -->
    <div class="modal fade" id="quickEditCategoryModal" tabindex="-1" aria-labelledby="quickEditCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="" class="modal-content" id="quickEditCategoryForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="quickEditCategoryModalLabel">{{ __('catalog.actions.quick_edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label" for="quick_name">{{ __('admin.blog_categories.fields.name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="quick_name" name="name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="quick_slug">{{ __('admin.blog_categories.fields.slug') }}</label>
                            <input type="text" class="form-control" id="quick_slug" name="slug">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="quick_parent_id">{{ __('catalog.fields.parent_category') }}</label>
                            <select class="form-select" id="quick_parent_id" name="parent_id">
                                <option value="">{{ __('catalog.common.none') }}</option>
                                @foreach($parentOptions as $parent)
                                    @php
                                        $parentName = $parent->getTranslation('name', app()->getLocale(), false) ?: $parent->getTranslation('name', $fallbackLocale, false);
                                    @endphp
                                    <option value="{{ $parent->id }}">
                                        {!! str_repeat('&nbsp;&nbsp;', $parent->depth ?? 0) !!}{{ $parent->depth ? '↳ ' : '' }}{{ $parentName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label" for="quick_description">{{ __('admin.blog_categories.fields.description') }}</label>
                            <textarea class="form-control d-none" id="quick_description" name="description"></textarea>
                            <div id="quick_description_editor" class="catalog-quill" data-target="quick_description" style="height: 150px;"></div>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="1">
                            <div class="form-check">
                                <input class="form-check-input primary" type="checkbox" name="is_active" value="0" id="quick_is_active">
                                <label class="form-check-label" for="quick_is_active">{{ __('admin.blog_categories.save_draft_help') }}</label>
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
            const sortUrl = @json(route('admin.post-categories.sort'));
            const quickUpdateUrlTemplate = @json(route('admin.post-categories.quick-update', ['post_category' => '__CATEGORY_ID__']));

            const toast = function (icon, message) {
                if (!window.Swal) {
                    alert(message);
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

            // Dragula initialization for reordering root categories (tbody elements)
            if (sortable && sortable.querySelector('tbody.category-group-sortable') && window.dragula) {
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
                        toast('success', payload.message || "{{ __('admin.blog_categories.sorted_success') }}");
                    })
                    .catch(function () {
                        toast('error', "{{ __('admin.blog_categories.sorted_failed') }}");
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
                            toast('success', payload.message || "{{ __('admin.blog_categories.sorted_sub_success') }}");
                        })
                        .catch(function () {
                            toast('error', "{{ __('admin.blog_categories.sorted_sub_failed') }}");
                        });
                    });
                }
            }

            // Bind values to quick edit modal
            document.querySelectorAll('.js-category-quick-edit').forEach(function (button) {
                button.addEventListener('click', function () {
                    const form = document.getElementById('quickEditCategoryForm');
                    const parentSelect = document.getElementById('quick_parent_id');

                    form.action = quickUpdateUrlTemplate.replace('__CATEGORY_ID__', button.dataset.id);
                    document.getElementById('quick_name').value = button.dataset.name || '';
                    document.getElementById('quick_slug').value = button.dataset.slug || '';
                    
                    const descriptionVal = button.dataset.description || '';
                    document.getElementById('quick_description').value = descriptionVal;
                    const quickEditor = document.getElementById('quick_description_editor');
                    if (quickEditor && quickEditor.__quill) {
                        quickEditor.__quill.root.innerHTML = descriptionVal;
                    }

                    document.getElementById('quick_is_active').checked = button.dataset.isActive !== '1';

                    Array.from(parentSelect.options).forEach(function (option) {
                        option.disabled = option.value === button.dataset.id;
                    });
                    parentSelect.value = button.dataset.parentId || '';
                });
            });
        });
    </script>
@endpush
