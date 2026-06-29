@extends('admin.layouts.app')

@section('title', __('admin.reviews.title'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.reviews.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.reviews.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small fw-bold text-dark mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('admin.reviews.search_placeholder') }}">
                </div>
                <div class="col-md-3 col-lg-3">
                    <label class="form-label small fw-bold text-dark mb-1">{{ __('admin.reviews.fields.product') }}</label>
                    <select name="product_id" class="form-select">
                        <option value="">{{ __('admin.reviews.all_products') }}</option>
                        @foreach($products as $p)
                            @php
                                $pName = $p->getTranslation('name', app()->getLocale(), false) ?: $p->getTranslation('name', config('app.fallback_locale', 'vi'), false);
                            @endphp
                            <option value="{{ $p->id }}" @selected((string) request('product_id') === (string) $p->id)>{{ $pName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-dark mb-1">{{ __('admin.reviews.fields.rating') }}</label>
                    <select name="rating" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        @for($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" @selected((string) request('rating') === (string) $i)>{{ $i }} {{ __('admin.reviews.fields.star') }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-dark mb-1">{{ __('admin.reviews.fields.status') }}</label>
                    <select name="is_visible" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected((string) request('is_visible') === '1')>{{ __('admin.reviews.fields.visible') }}</option>
                        <option value="0" @selected((string) request('is_visible') === '0')>{{ __('admin.reviews.fields.hidden') }}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit">
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
                            <th class="ps-4">{{ __('admin.reviews.fields.product') }}</th>
                            <th>{{ __('admin.reviews.fields.reviewer') }}</th>
                            <th>{{ __('admin.reviews.fields.rating') }}</th>
                            <th style="min-width: 300px;">{{ __('admin.reviews.fields.comment') }}</th>
                            <th>{{ __('admin.reviews.fields.created_at') }}</th>
                            <th>{{ __('admin.reviews.fields.status') }}</th>
                            <th class="text-end pe-4">{{ __('catalog.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reviews as $review)
                            @php
                                $productName = $review->product->getTranslation('name', app()->getLocale(), false) 
                                    ?: $review->product->getTranslation('name', config('app.fallback_locale', 'vi'), false);
                            @endphp
                            <tr>
                                <td class="ps-4" style="max-width: 250px;">
                                    <div class="d-flex align-items-center gap-2">
                                        @if($review->product->image_url)
                                            <img src="{{ $review->product->image_url }}" alt="{{ $productName }}" width="40" height="40" class="rounded object-fit-cover">
                                        @else
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="ti ti-package text-muted fs-5"></i>
                                            </div>
                                        @endif
                                        <div class="text-truncate">
                                            <a href="{{ route('admin.products.edit', $review->product) }}" class="fw-semibold text-dark fs-3 text-decoration-none hover-primary">
                                                {{ $productName }}
                                            </a>
                                            <div class="text-muted small">SKU: {{ $review->product->sku }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark fs-3">{{ $review->customer_name }}</div>
                                    <div class="text-muted small">{{ $review->customer_email }}</div>
                                    @if($review->user)
                                        <span class="badge bg-primary-subtle text-primary fw-semibold fs-1">{{ __('admin.reviews.fields.member') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-dark fw-semibold fs-1">{{ __('admin.reviews.fields.guest') }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $review->rating)
                                            <i class="ti ti-star-filled text-warning fs-5"></i>
                                        @else
                                            <i class="ti ti-star text-muted fs-5"></i>
                                        @endif
                                    @endfor
                                </td>
                                <td>
                                    <div class="text-dark comment-text text-wrap" style="max-width: 450px;">
                                        {{ $review->comment }}
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <span class="text-dark small">{{ $review->created_at->format('d/m/Y H:i') }}</span>
                                </td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input js-visibility-toggle" type="checkbox" role="switch"
                                            data-url="{{ route('admin.reviews.toggle-visibility', $review) }}"
                                            @checked($review->is_visible)>
                                    </div>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary js-edit-review-btn" 
                                            data-url="{{ route('admin.reviews.update', $review) }}"
                                            data-comment="{{ $review->comment }}"
                                            data-visible="{{ $review->is_visible ? 1 : 0 }}"
                                            title="{{ __('catalog.actions.edit') }}">
                                            <i class="ti ti-edit fs-4"></i>
                                        </button>
                                        <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" class="js-delete-form" style="display:inline-block;">
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
                                <td colspan="7" class="text-center py-4 text-dark">
                                    {{ __('admin.reviews.not_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($reviews->hasPages())
                <div class="px-4 py-3 border-top">
                    {{ $reviews->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Edit Review Modal -->
    <div class="modal fade" id="editReviewModal" tabindex="-1" aria-labelledby="editReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold text-dark" id="editReviewModalLabel">{{ __('admin.reviews.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editReviewForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="modal_comment" class="form-label fw-semibold text-dark">{{ __('admin.reviews.fields.comment') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control text-dark" id="modal_comment" name="comment" rows="5" required></textarea>
                        </div>
                        <div class="mb-0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="modal_is_visible">
                                <label class="form-check-label fw-semibold text-dark ms-2" for="modal_is_visible">{{ __('admin.reviews.show_on_website') }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('catalog.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('catalog.actions.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Edit modal handling
            const editModalEl = document.getElementById('editReviewModal');
            const editModal = new bootstrap.Modal(editModalEl);
            const editForm = document.getElementById('editReviewForm');
            const modalComment = document.getElementById('modal_comment');
            const modalIsVisible = document.getElementById('modal_is_visible');

            document.querySelectorAll('.js-edit-review-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const actionUrl = this.getAttribute('data-url');
                    const comment = this.getAttribute('data-comment');
                    const isVisible = this.getAttribute('data-visible') === '1';

                    editForm.action = actionUrl;
                    modalComment.value = comment;
                    modalIsVisible.checked = isVisible;

                    editModal.show();
                });
            });

            // Edit Form submit via AJAX
            if (editForm) {
                editForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: "{{ __('admin.reviews.saving') }}",
                        text: "{{ __('admin.reviews.please_wait') }}",
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Prepare form data including checkbox logic
                    const formData = new FormData(editForm);
                    if (!formData.has('is_visible')) {
                        formData.append('is_visible', '0');
                    }

                    fetch(editForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => { throw err; });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: "{{ __('admin.settings.success') }}",
                                text: data.message || "{{ __('admin.reviews.updated') }}",
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                editModal.hide();
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: "{{ __('admin.settings.error') }}",
                                text: data.message || "{{ __('admin.reviews.update_failed') }}"
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        let errMsg = 'Không thể kết nối đến máy chủ.';
                        if (error.errors) {
                            errMsg = Object.values(error.errors).flat().join('\n');
                        } else if (error.message) {
                            errMsg = error.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: "{{ __('admin.settings.error') }}",
                            text: errMsg
                        });
                    });
                });
            }

            // Quick Visibility Toggle via AJAX
            document.querySelectorAll('.js-visibility-toggle').forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const url = this.getAttribute('data-url');
                    const isChecked = this.checked;

                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => { throw err; });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Optionally toast notification
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000,
                                timerProgressBar: true
                            });
                            Toast.fire({
                                icon: 'success',
                                title: "{{ __('admin.reviews.status_updated') }}"
                            });
                        } else {
                            this.checked = !isChecked; // revert
                            Swal.fire({
                                icon: 'error',
                                title: "{{ __('admin.settings.error') }}",
                                text: data.message || 'Có lỗi xảy ra.'
                            });
                        }
                    })
                    .catch(error => {
                        this.checked = !isChecked; // revert
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: "{{ __('admin.settings.error') }}",
                            text: "{{ __('admin.reviews.status_update_failed') }}"
                        });
                    });
                });
            });
        });
    </script>
@endpush
