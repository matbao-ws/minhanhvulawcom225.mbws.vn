@extends('admin.layouts.app')

@section('title', __('catalog.media.title'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('catalog.media.title') }}</h4>
                    <div class="text-muted">{{ __('catalog.media.subtitle') }}</div>
                </div>
                <nav style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('catalog.media.title') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Configuration Warning -->
    @if(!$isConfigured)
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti ti-alert-triangle fs-7 me-3 text-warning"></i>
                <div>
                    <h6 class="alert-heading fw-semibold mb-1">{{ __('catalog.media.local_storage_mode') }}</h6>
                    <p class="mb-0 fs-2 opacity-75">{{ __('catalog.media.local_warning') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Sidebar Folders -->
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="card-title mb-0 fw-semibold">{{ __('catalog.media.cloud_folders') }}</h5>
                </div>
                <div class="list-group list-group-flush rounded">
                    <!-- Option to see all files -->
                    <a href="{{ route('admin.media.index', ['folder' => 'all']) }}" 
                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between {{ $activeFolder === 'all' ? 'active bg-primary-subtle text-primary border-primary fw-semibold' : '' }}">
                        <div class="d-flex align-items-center gap-2">
                            <iconify-icon icon="solar:folder-with-files-bold" class="fs-5"></iconify-icon>
                            <span>{{ __('catalog.media.all_folders') }}</span>
                        </div>
                    </a>

                    @foreach($folders as $folder)
                        <a href="{{ route('admin.media.index', ['folder' => $folder]) }}" 
                           class="list-group-item list-group-item-action d-flex align-items-center justify-content-between {{ $activeFolder === $folder ? 'active bg-primary-subtle text-primary border-primary fw-semibold' : '' }}">
                            <div class="d-flex align-items-center gap-2">
                                <iconify-icon icon="solar:folder-open-bold-duotone" class="fs-5"></iconify-icon>
                                <span class="text-capitalize">{{ $folder }}</span>
                            </div>
                            <span class="badge bg-light text-dark rounded-pill fs-2">📂</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Media Content & Uploader -->
        <div class="col-lg-9 col-md-8">
            <!-- Upload Form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-3">
                        <i class="ti ti-upload me-1 text-primary"></i>
                        {{ __('catalog.media.upload_to_folder', ['folder' => ucfirst($activeFolder === 'all' ? 'general' : $activeFolder)]) }}
                    </h5>
                    
                    <form method="POST" action="{{ route('admin.media.upload') }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                        @csrf
                        <input type="hidden" name="folder" value="{{ $activeFolder === 'all' ? 'general' : $activeFolder }}">
                        
                        <div class="row align-items-center g-2">
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <input type="file" class="form-control" name="file" id="media_file_upload" required>
                                    <label class="input-group-text" for="media_file_upload">{{ __('catalog.media.select_file') }}</label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti ti-cloud-upload me-1"></i>{{ __('catalog.media.upload') }}
                                </button>
                            </div>
                        </div>
                        @error('file')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </form>
                </div>
            </div>

            <!-- Media Library Grid -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0 fw-semibold">{{ __('catalog.media.files') }} ({{ count($resources) }})</h5>
                </div>
                <div class="card-body">
                    @if(count($resources) === 0)
                        <div class="text-center py-5">
                            <iconify-icon icon="solar:gallery-wide-line-duotone" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                            <p class="text-muted mb-0 fs-3">{{ __('catalog.media.no_files') }}</p>
                        </div>
                    @else
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xxl-4 g-3">
                            @foreach($resources as $resource)
                                <div class="col">
                                    <div class="card h-100 border rounded shadow-none overflow-hidden position-relative media-card">
                                        <!-- File Thumbnail Preview -->
                                        <div class="ratio ratio-4x3 bg-light border-bottom d-flex align-items-center justify-content-center overflow-hidden position-relative">
                                            @php
                                                $isImage = in_array(strtolower($resource['format']), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']);
                                            @endphp

                                            @if($isImage)
                                                <img src="{{ $resource['secure_url'] }}" 
                                                     alt="{{ $resource['public_id'] }}" 
                                                     class="img-fluid position-absolute w-100 h-100 start-0 top-0" 
                                                     style="object-fit: contain;">
                                            @else
                                                <div class="d-flex flex-column align-items-center justify-content-center">
                                                    <iconify-icon icon="solar:document-bold-duotone" class="fs-10 text-primary mb-2"></iconify-icon>
                                                    <span class="badge bg-primary text-uppercase">{{ $resource['format'] }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Card Info -->
                                        <div class="card-body p-3">
                                            @php
                                                $pathParts = explode('/', $resource['public_id']);
                                                $displayName = end($pathParts);
                                            @endphp
                                            <h6 class="card-subtitle text-dark fw-semibold text-truncate mb-1" title="{{ $displayName }}">
                                                {{ $displayName }}
                                            </h6>
                                            <div class="d-flex justify-content-between align-items-center text-muted fs-2 mb-3">
                                                <span>{{ number_format($resource['bytes'] / 1024, 1) }} KB</span>
                                                <span>{{ date('d-m-Y', strtotime($resource['created_at'])) }}</span>
                                            </div>

                                            <div class="d-flex gap-2">
                                                <!-- Copy Link Button -->
                                                <button class="btn btn-sm btn-outline-secondary flex-fill d-flex align-items-center justify-content-center gap-1 copy-url-btn" 
                                                        data-url="{{ $resource['secure_url'] }}">
                                                    <i class="ti ti-copy fs-4"></i>
                                                    <span>{{ __('catalog.media.copy_url') }}</span>
                                                </button>

                                                <!-- Delete Button -->
                                                <form method="POST" action="{{ route('admin.media.delete') }}" class="d-inline flex-fill">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="public_id" value="{{ $resource['public_id'] }}">
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-1"
                                                            onclick="return confirm('{{ __('catalog.media.delete_confirm') }}')">
                                                        <i class="ti ti-trash fs-4"></i>
                                                        <span>{{ __('catalog.actions.delete') }}</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Copy URL feature
            const copyButtons = document.querySelectorAll('.copy-url-btn');
            copyButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const url = this.getAttribute('data-url');
                    
                    navigator.clipboard.writeText(url).then(() => {
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="ti ti-check fs-4"></i><span>{{ __('catalog.media.copied') }}</span>';
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-success');
                        
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.classList.remove('btn-success');
                            this.classList.add('btn-outline-secondary');
                        }, 2000);
                    }).catch(err => {
                        alert('{{ __('catalog.media.copy_failed') }}');
                    });
                });
            });
        });
    </script>
@endpush
