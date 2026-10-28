@extends('admin.layouts.app')

@section('title', __('admin.banners.edit'))

@section('content')
    <x-admin.page-header :title="__('admin.banners.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.banners.index') }}">{{ __('admin.banners.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.banners.edit') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

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
                    <h5 class="card-title fw-bold text-dark mb-0">{{ __('admin.banners.edit_card') }}</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.banners.update', $banner) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <!-- Banner Image Preview & Upload -->
                        <div data-tour="banner-form.image" class="mb-4">
                            <label class="form-label fw-bold text-dark d-block">{{ __('admin.banners.fields.image') }}</label>
                            
                            <!-- Current Image Preview -->
                            <div class="mb-3 text-center p-3 bg-light rounded-4 border">
                                <span class="d-block small text-muted mb-2">{{ __('admin.banners.current_image') }}</span>
                                <img src="{{ $banner->image_url }}" alt="Current Banner" class="rounded shadow-sm border border-2 border-primary-subtle" style="max-height: 180px; max-width: 100%; object-fit: cover;">
                            </div>

                            <div class="p-4 border border-2 border-dashed rounded-4 text-center bg-light-subtle position-relative hover-bg-light transition-all">
                                <i class="ti ti-photo-plus text-muted fs-9 mb-2 d-inline-block"></i>
                                <h6 class="text-dark fw-semibold mb-1">{{ __('admin.banners.change_file') }}</h6>
                                <p class="text-muted small mb-3">{{ __('admin.banners.change_file_help') }}</p>
                                <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*" data-media-folder="banners" style="cursor: pointer;">
                                <div id="imagePreviewContainer" class="mt-3" style="display: none;">
                                    <span class="d-block small text-primary fw-semibold mb-2">{{ __('admin.banners.new_image') }}</span>
                                    <img id="imagePreview" src="" alt="New Preview" class="rounded shadow-sm border border-2 border-success-subtle" style="max-height: 180px; max-width: 100%; object-fit: cover;">
                                </div>
                            </div>
                        </div>

                        <!-- Title -->
                        <div data-tour="banner-form.title" class="mb-3">
                            <label for="title" class="form-label fw-bold text-dark">{{ __('admin.banners.fields.title') }}</label>
                            <input type="text" class="form-control rounded-pill" id="title" name="title" value="{{ old('title', $banner->title) }}" placeholder="{{ __('admin.banners.title_placeholder') }}">
                        </div>

                        <!-- Destination URL Link -->
                        <div data-tour="banner-form.link" class="mb-3">
                            <label for="link_url" class="form-label fw-bold text-dark">{{ __('admin.banners.fields.link') }}</label>
                            <input type="url" class="form-control rounded-pill" id="link_url" name="link_url" value="{{ old('link_url', $banner->link_url) }}" placeholder="https://example.com/san-pham">
                        </div>

                        <div class="row">
                            <!-- Position -->
                            <div data-tour="banner-form.position" class="col-12 mb-3">
                                <label for="position" class="form-label fw-bold text-dark">{{ __('admin.banners.fields.position') }} <span class="text-danger">*</span></label>
                                <select class="form-select rounded-pill" id="position" name="position" required>
                                    <option value="home_main" @selected(old('position', $banner->position) === 'home_main')>{{ __('admin.banners.positions.home_main') }}</option>
                                    <option value="home_sidebar" @selected(old('position', $banner->position) === 'home_sidebar')>{{ __('admin.banners.positions.home_sidebar') }}</option>
                                    <option value="promotional" @selected(old('position', $banner->position) === 'promotional')>{{ __('admin.banners.positions.promotional') }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Active status -->
                        <div data-tour="banner-form.status" class="mb-4 mt-2">
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $banner->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="is_active">{{ __('admin.banners.fields.status') }}: {{ __('admin.banners.fields.active') }}</label>
                            </div>
                        </div>

                        <hr class="border-light">

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.banners.index') }}" class="btn btn-light rounded-pill px-4 fw-semibold">{{ __('catalog.actions.cancel') }}</a>
                            <button data-tour="banner-form.save" type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                <i class="ti ti-device-floppy me-1"></i>{{ __('admin.banners.save_changes') }}
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
