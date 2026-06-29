@extends('admin.layouts.app')

@section('title', __('catalog.brands.title'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/dragula/dist/dragula.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/quill/dist/quill.snow.css') }}">
@endpush

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fw-semibold mb-1">{{ __('catalog.brands.title') }}</h4>
                <div class="text-muted">{{ __('catalog.brands.subtitle') }}</div>
            </div>
            <a href="{{ route('admin.brands.create') }}" class="btn btn-primary">{{ __('catalog.brands.create') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('catalog.placeholders.brand_name') }}">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">{{ __('catalog.actions.search') }}</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table text-nowrap mb-0 align-middle">
                <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.name') }}</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.status') }}</h6>
                    </th>
                    <th class="text-center">
                        <h6 class="fs-4 fw-semibold mb-0">{{ __('catalog.fields.products_count') }}</h6>
                    </th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="brand-table-sortable" data-start-order="{{ max(0, ($brands->firstItem() ?? 1) - 1) }}">
                @forelse($brands as $brand)
                    @php
                        $brandName = $brand->getTranslation('name', app()->getLocale(), false) ?: $brand->name;
                        $brandDescription = $brand->getTranslation('description', app()->getLocale(), false) ?: '';
                    @endphp
                    <tr data-brand-id="{{ $brand->id }}">
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="catalog-drag-handle me-2" title="{{ __('catalog.actions.drag_sort') }}" style="cursor: grab;">
                                    <i class="ti ti-grip-vertical fs-5"></i>
                                </span>
                                @if($brand->image_url)
                                    <img src="{{ $brand->image_url }}" alt="{{ $brandName }}" class="rounded-2 object-fit-cover" width="42" height="42" onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/404.png') }}';">
                                @else
                                    <img src="{{ asset('admin-assets/js/icons/empty.png') }}" alt="empty" class="rounded-2 object-fit-cover" width="42" height="42">
                                @endif
                                <div class="ms-3">
                                    <h6 class="fw-semibold mb-1">{{ $brandName }}</h6>
                                    <span class="fw-normal text-muted">{{ $brand->slug }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $brand->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ $brand->is_active ? __('catalog.status.active') : __('catalog.status.inactive') }}
                            </span>
                        </td>
                        <td class="text-center">
                            <p class="mb-0 fw-bold text-primary">{{ $brand->products_count }}</p>
                        </td>
                        <td>
                            <div class="dropdown dropstart">
                                <a href="javascript:void(0)" class="text-muted" id="brandAction{{ $brand->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ti ti-dots fs-5"></i>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="brandAction{{ $brand->id }}">
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item d-flex align-items-center gap-3 js-brand-quick-edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#quickEditBrandModal"
                                            data-id="{{ $brand->id }}"
                                            data-name="{{ $brandName }}"
                                            data-slug="{{ $brand->slug }}"
                                            data-description="{{ $brandDescription }}"
                                            data-is-active="{{ $brand->is_active ? 1 : 0 }}"
                                            data-image-url="{{ $brand->image_url }}"
                                        >
                                            <i class="fs-4 ti ti-bolt"></i>{{ __('catalog.actions.quick_edit') }}
                                        </button>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-3" href="{{ route('admin.brands.edit', $brand) }}">
                                            <i class="fs-4 ti ti-edit"></i>{{ __('catalog.actions.edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" class="js-delete-form">
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
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <img src="{{ asset('admin-assets/images/icons/emptydata.png') }}" alt="No data" class="img-fluid mb-2" style="max-height: 60px;">
                            <p class="text-muted fw-bold mb-0">{{ __('catalog.common.no_data') }}</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $brands->links() }}
        </div>
    </div>

    <!-- Quick Edit Modal -->
    <div class="modal fade" id="quickEditBrandModal" tabindex="-1" aria-labelledby="quickEditBrandModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="" class="modal-content" enctype="multipart/form-data" id="quickEditBrandForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="quickEditBrandModalLabel">{{ __('catalog.brands.quick_edit') }}</h5>
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
                        <div class="col-md-12 mb-3">
                            <label class="form-label" for="quick_image_file">{{ __('catalog.fields.image') }}</label>
                            <input type="file" class="form-control" id="quick_image_file" name="image_file" accept="image/*">
                        </div>
                        <div class="col-12 mb-3 d-none" id="quickImagePreviewWrap">
                            <img src="" alt="" id="quickImagePreview" class="rounded border object-fit-cover" width="72" height="72" onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/404.png') }}';">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label" for="quick_description">{{ __('catalog.fields.description') }}</label>
                            <textarea class="form-control d-none" id="quick_description" name="description"></textarea>
                            <div id="quick_description_editor" class="catalog-quill" data-target="quick_description"></div>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="1">
                            <div class="form-check">
                                <input class="form-check-input primary" type="checkbox" name="is_active" value="0" id="quick_is_active">
                                <label class="form-check-label" for="quick_is_active">{{ __('catalog.fields.save_draft') }}</label>
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
            const sortable = document.getElementById('brand-table-sortable');
            const csrfToken = @json(csrf_token());
            const sortUrl = @json(route('admin.brands.sort'));
            const quickUpdateUrlTemplate = @json(route('admin.brands.quick-update', ['brand' => '__BRAND_ID__']));

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

            if (sortable && sortable.querySelectorAll('tr').length > 1 && window.dragula) {
                dragula([sortable], {
                    moves: function (el, container, handle) {
                        return handle.closest('.catalog-drag-handle') !== null;
                    }
                }).on('drop', function () {
                    const ids = Array.from(sortable.querySelectorAll('tr')).map(function (tr) {
                        return tr.dataset.brandId;
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
                            toast('success', payload.message || @json(__('catalog.brands.sorted')));
                        })
                        .catch(function () {
                            toast('error', @json(__('catalog.brands.sort_failed')));
                        });
                });
            }

            document.querySelectorAll('.js-brand-quick-edit').forEach(function (button) {
                button.addEventListener('click', function () {
                    const form = document.getElementById('quickEditBrandForm');
                    const imageUrl = button.dataset.imageUrl || '';
                    const previewWrap = document.getElementById('quickImagePreviewWrap');
                    const preview = document.getElementById('quickImagePreview');

                    form.action = quickUpdateUrlTemplate.replace('__BRAND_ID__', button.dataset.id);
                    document.getElementById('quick_name').value = button.dataset.name || '';
                    document.getElementById('quick_slug').value = button.dataset.slug || '';
                    
                    const descriptionVal = button.dataset.description || '';
                    document.getElementById('quick_description').value = descriptionVal;
                    const quickEditor = document.getElementById('quick_description_editor');
                    if (quickEditor && quickEditor.__quill) {
                        quickEditor.__quill.root.innerHTML = descriptionVal;
                    }

                    document.getElementById('quick_is_active').checked = button.dataset.isActive !== '1';
                    document.getElementById('quick_image_file').value = '';

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
