@extends('admin.layouts.app')

@section('title', __('admin.banners.edit'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.banners.edit') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.banners.index') }}">{{ __('admin.banners.title') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.banners.edit') }}</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
                        <i class="ti ti-arrow-left fs-4"></i>{{ __('admin.back') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Errors list -->
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border border-light-subtle rounded-4">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title fw-bold text-dark mb-0">Chỉnh sửa Banner</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.banners.update', $banner) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <!-- Banner Image Preview & Upload -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark d-block">{{ __('admin.banners.fields.image') }}</label>
                            
                            <!-- Current Image Preview -->
                            <div class="mb-3 text-center p-3 bg-light rounded-4 border">
                                <span class="d-block small text-muted mb-2">Hình ảnh hiện tại:</span>
                                <img src="{{ $banner->image_url }}" alt="Current Banner" class="rounded shadow-sm border border-2 border-primary-subtle" style="max-height: 180px; max-width: 100%; object-fit: cover;">
                            </div>

                            <div class="p-4 border border-2 border-dashed rounded-4 text-center bg-light-subtle position-relative hover-bg-light transition-all">
                                <i class="ti ti-photo-plus text-muted fs-9 mb-2 d-inline-block"></i>
                                <h6 class="text-dark fw-semibold mb-1">Thay đổi tập tin ảnh banner (nếu muốn)</h6>
                                <p class="text-muted small mb-3">Chấp nhận JPG, PNG, WEBP tối đa 2MB. Bỏ trống nếu muốn giữ ảnh cũ.</p>
                                <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*" style="cursor: pointer;">
                                <div id="imagePreviewContainer" class="mt-3" style="display: none;">
                                    <span class="d-block small text-primary fw-semibold mb-2">Hình ảnh mới thay thế:</span>
                                    <img id="imagePreview" src="" alt="New Preview" class="rounded shadow-sm border border-2 border-success-subtle" style="max-height: 180px; max-width: 100%; object-fit: cover;">
                                </div>
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold text-dark">{{ __('admin.banners.fields.title') }}</label>
                            <input type="text" class="form-control rounded-pill" id="title" name="title" value="{{ old('title', $banner->title) }}" placeholder="Nhập tiêu đề hoặc mô tả ngắn">
                        </div>

                        <!-- Destination URL Link -->
                        <div class="mb-3">
                            <label for="link_url" class="form-label fw-bold text-dark">{{ __('admin.banners.fields.link') }}</label>
                            <input type="url" class="form-control rounded-pill" id="link_url" name="link_url" value="{{ old('link_url', $banner->link_url) }}" placeholder="https://example.com/san-pham">
                        </div>

                        <div class="row">
                            <!-- Position -->
                            <div class="col-md-6 mb-3">
                                <label for="position" class="form-label fw-bold text-dark">{{ __('admin.banners.fields.position') }} <span class="text-danger">*</span></label>
                                <select class="form-select rounded-pill" id="position" name="position" required>
                                    <option value="home_main" @selected(old('position', $banner->position) === 'home_main')>{{ __('admin.banners.positions.home_main') }}</option>
                                    <option value="home_sidebar" @selected(old('position', $banner->position) === 'home_sidebar')>{{ __('admin.banners.positions.home_sidebar') }}</option>
                                    <option value="promotional" @selected(old('position', $banner->position) === 'promotional')>{{ __('admin.banners.positions.promotional') }}</option>
                                </select>
                            </div>

                            <!-- Sort Order -->
                            <div class="col-md-6 mb-3">
                                <label for="sort_order" class="form-label fw-bold text-dark">{{ __('admin.banners.fields.sort_order') }} <span class="text-danger">*</span></label>
                                <input type="number" class="form-control rounded-pill" id="sort_order" name="sort_order" value="{{ old('sort_order', $banner->sort_order) }}" min="0" required>
                            </div>
                        </div>

                        <!-- Active status -->
                        <div class="mb-4 mt-2">
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $banner->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="is_active">{{ __('admin.banners.fields.status') }}: {{ __('admin.banners.fields.active') }}</label>
                            </div>
                        </div>

                        <hr class="border-light">

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.banners.index') }}" class="btn btn-light rounded-pill px-4 fw-semibold">{{ __('catalog.actions.cancel') }}</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                <i class="ti ti-device-floppy me-1"></i>Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('image_file');
        const previewContainer = document.getElementById('imagePreviewContainer');
        const previewImg = document.getElementById('imagePreview');

        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    previewImg.setAttribute('src', this.result);
                    previewContainer.style.display = 'block';
                });
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
                previewImg.setAttribute('src', '');
            }
        });
    });
</script>
@endpush
