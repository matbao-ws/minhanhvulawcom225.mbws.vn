@extends('admin.layouts.app')

@section('title', __('catalog.media.title'))

@push('styles')
    <style>
        .media-library-shell { width: 100%; max-width: 100%; margin: 0; }
        .media-library-panel { background: #fff; border: 1px solid #edf0f5; border-radius: 14px; box-shadow: 0 4px 18px rgba(25, 42, 70, .045); }
        .media-sidebar { padding: 12px; }
        .media-sidebar-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 4px 5px 10px; user-select: none; flex-shrink: 0; background: #fff; z-index: 2; }
        .media-sidebar-title { display: flex; align-items: center; gap: 8px; margin: 0; color: #27364d; font-size: .9375rem; font-weight: 700; }
        .media-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        /* Custom Modern Scrollbar */
        .media-folder-sidebar,
        .media-sidebar-collapse-body {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.45) transparent;
        }
        .media-folder-sidebar::-webkit-scrollbar,
        .media-sidebar-collapse-body::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        .media-folder-sidebar::-webkit-scrollbar-track,
        .media-sidebar-collapse-body::-webkit-scrollbar-track {
            background: transparent;
            margin: 6px 0;
        }
        .media-folder-sidebar::-webkit-scrollbar-thumb,
        .media-sidebar-collapse-body::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 10px;
            transition: background 0.2s ease;
        }
        .media-folder-sidebar:hover::-webkit-scrollbar-thumb,
        .media-sidebar-collapse-body:hover::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.65);
        }
        .media-folder-sidebar::-webkit-scrollbar-thumb:hover,
        .media-sidebar-collapse-body::-webkit-scrollbar-thumb:hover {
            background: #d11e28;
        }
        .media-folder-link { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 10px; border-radius: 8px; color: #64748b; font-size: .8125rem; font-weight: 600; text-decoration: none; transition: background .15s, color .15s; }
        .media-folder-link:hover { background: #fff3f3; color: #d11e28; }
        .media-folder-link.is-active { background: #fff0f1; color: #d11e28; }
        .media-folder-link .folder-name { display: flex; align-items: center; gap: 9px; min-width: 0; }
        .media-folder-link .ti { font-size: 17px; }
        .media-folder-count { min-width: 20px; padding: 1px 6px; border-radius: 99px; background: #f3f5f8; color: #9aa6b5; font-size: .6875rem; text-align: center; }
        .media-storage-card { display: flex; gap: 10px; margin-top: 18px; padding: 12px; border: 1px solid #edf0f5; border-radius: 9px; background: #fbfcfe; }
        .media-storage-icon { color: #8b98aa; font-size: 30px; line-height: 1; }
        .media-storage-content { min-width: 0; flex: 1; }
        .media-storage-label { margin: 0 0 2px; color: #536278; font-size: .6875rem; font-weight: 600; }
        .media-storage-amount { margin: 0; color: #1f2d42; font-size: .8125rem; font-weight: 700; }
        .media-storage-amount span { color: #8794a5; font-weight: 500; }
        .media-storage-progress { height: 8px; margin-top: 9px; overflow: hidden; border-radius: 99px; background: #e9edf3; }
        .media-storage-progress > span { display: block; height: 100%; border-radius: inherit; background: #d11e28; }
        .media-cloud-card { margin-top: 10px; padding: 12px; border: 1px solid #edf0f5; border-radius: 9px; background: #fff; }
        .media-cloud-status { display: flex; align-items: flex-start; gap: 9px; }
        .media-cloud-status > .ti { color: #16a36a; font-size: 23px; }
        .media-cloud-status.is-local > .ti { color: #8794a5; }
        .media-cloud-title { margin: 0; color: #334155; font-size: .75rem; font-weight: 700; }
        .media-cloud-detail { margin: 2px 0 0; color: #8b98aa; font-size: .6875rem; overflow-wrap: anywhere; }
        .media-upload-panel, .media-files-panel { padding: 16px; }
        .media-panel-heading { display: flex; align-items: center; gap: 8px; color: #34425a; font-size: .875rem; font-weight: 700; }
        .media-panel-heading .ti { color: #e5232c; }
        .media-dropzone { display: flex; min-height: 184px; margin-top: 14px; padding: 24px; align-items: center; justify-content: center; border: 1px dashed #cfd7e2; border-radius: 10px; background: #fcfdff; cursor: pointer; text-align: center; transition: border-color .15s, background .15s; }
        .media-dropzone.is-dragging { border-color: #e5232c; background: #fff6f6; }
        .media-dropzone-content { display: flex; flex-direction: column; align-items: center; }
        .media-dropzone-icon { color: #8290a3; font-size: 33px; line-height: 1; }
        .media-dropzone-title { margin: 7px 0 2px; color: #34425a; font-size: .875rem; font-weight: 700; }
        .media-dropzone-hint { margin: 0 0 10px; color: #9aa6b5; font-size: .75rem; }
        .media-choose-file { display: inline-flex; align-items: center; gap: 6px; padding: 8px 19px; border-radius: 7px; background: #d11e28; color: #fff !important; cursor: pointer; font-size: .75rem; font-weight: 700; text-decoration: none; transition: background .15s; }
        .media-choose-file:hover, .media-choose-file:focus { background: #b81720; color: #fff !important; }
        .media-upload-help, .media-selected-file { margin: 9px 0 0; color: #91a0b2; font-size: .6875rem; }
        .media-selected-file { color: #52657c; font-weight: 600; }
        .media-upload-submit { margin-top: 12px; }
        .media-upload-previews-wrap {
            margin-top: 14px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .media-upload-previews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .media-preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e2e8f0;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .media-preview-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }
        .media-preview-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .media-pdf-preview {
            width: 100%;
            height: 100%;
            border: 0;
            overflow: hidden;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .media-pdf-preview canvas {
            display: block;
            max-width: 100%;
            max-height: 100%;
            width: auto !important;
            height: auto !important;
        }
        .media-pdf-preview.is-unavailable::after {
            content: '{{ __('admin.media.pdf_preview_unavailable') }}';
            color: #64748b;
            font-size: .75rem;
            font-weight: 600;
        }
        .media-file-name-link {
            color: inherit;
            text-decoration: none;
        }
        .media-file-name-link:hover,
        .media-file-name-link:focus-visible {
            color: #d11e28;
            text-decoration: underline;
        }
        .media-preview-doc {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6px;
            text-align: center;
            width: 100%;
            height: 100%;
            color: #64748b;
        }
        .media-preview-doc i {
            font-size: 28px;
            margin-bottom: 4px;
        }
        .media-preview-doc .doc-name {
            font-size: 10.5px;
            font-weight: 600;
            line-height: 1.2;
            word-break: break-all;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .media-preview-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.75);
            color: #ffffff !important;
            border: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            cursor: pointer;
            backdrop-filter: blur(4px);
            transition: background 0.15s ease, transform 0.15s ease;
            z-index: 5;
            padding: 0;
            line-height: 1;
        }
        .media-preview-remove:hover {
            background: #dc2626;
            color: #ffffff !important;
            transform: scale(1.15);
        }
        .media-preview-size {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(15, 23, 42, 0.75));
            color: #ffffff;
            font-size: 10px;
            font-weight: 600;
            padding: 8px 4px 2px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            pointer-events: none;
        }
        .media-toolbar { padding-bottom: 14px; border-bottom: 1px solid #edf0f5; }
        .media-toolbar-title { margin: 0; color: #34425a; font-size: .875rem; font-weight: 700; white-space: nowrap; }
        .media-toolbar-controls { display: flex; align-items: center; gap: 12px; }
        .media-toolbar-actions { display: flex; align-items: center; gap: 8px; }
        .media-sort-label { padding: 5px 9px; border: 1px solid #edf0f5; border-radius: 6px; color: #7f8da0; font-size: .6875rem; background: #fff; }
        .media-view-toggle { display: inline-flex; gap: 3px; padding: 3px; border: 1px solid #edf0f5; border-radius: 7px; background: #fff; }
        .media-view-toggle button { width: 28px; height: 26px; padding: 0; border: 0; border-radius: 5px; background: transparent; color: #8794a6; }
        .media-view-toggle button.is-active { background: #d11e28; color: #fff; }
        .media-empty { display: flex; min-height: 300px; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .media-empty img { width: 138px; max-width: 55%; }
        .media-empty h6 { margin: 9px 0 2px; color: #43526a; font-size: .875rem; font-weight: 700; }
        .media-empty p { margin: 0; color: #9aa6b5; font-size: .75rem; }
        .media-file-grid.is-list { display: block; }
        .media-file-grid.is-list > .col { width: 100%; margin-bottom: 10px; }
        .media-file-grid.is-list .media-file-card { display: flex; min-height: 112px; flex-direction: row; }
        .media-file-grid.is-list .media-preview { width: 140px; flex: 0 0 140px; border-bottom: 0 !important; border-right: 1px solid #edf0f5; }
        .media-file-grid.is-list .media-file-card .card-body { display: flex; flex: 1; align-items: center; justify-content: space-between; gap: 20px; }
        /* .media-preview is a Bootstrap .ratio box, whose ".ratio > *" rule forces
           every direct child to width:100%/height:100% to fill the frame; these two
           overlays are direct children too but must stay pill-sized, not stretch. */
        .media-select-overlay { position: absolute; top: 8px; left: 8px; z-index: 2; width: auto; height: auto; padding: 3px 5px; border-radius: 6px; background: rgba(255, 255, 255, .85); }
        .media-select-overlay .form-check-input { margin: 0; cursor: pointer; }
        .media-source-badge { position: absolute; top: 8px; right: 8px; z-index: 2; width: auto; height: auto; }
        .media-bulk-bar { display: flex; align-items: center; gap: 10px; }
        .media-bulk-select-all { display: flex; align-items: center; gap: 6px; color: #52657c; font-size: .75rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .media-bulk-select-all input { cursor: pointer; }
        #mediaBulkDeleteBtn { white-space: nowrap; }
        @media (max-width: 991.98px) {
            .media-toolbar {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding-bottom: 12px;
            }
            .media-toolbar-controls {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-top: 8px;
                border-top: 1px dashed #edf0f5;
            }
            .media-bulk-bar {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
            }
        }
        @media (min-width: 992px) {
            .media-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
        }
        @media (max-width: 767.98px) {
            .media-folder-sidebar { margin-bottom: 14px; }
            .media-sidebar-header { cursor: pointer; padding: 6px 8px; border-radius: 8px; transition: background .15s ease; }
            .media-sidebar-header:hover { background: #f8fafc; }
            .media-sidebar-header[aria-expanded="true"] { padding-bottom: 10px; border-bottom: 1px solid #edf0f5; margin-bottom: 8px; border-radius: 8px 8px 0 0; }
            .media-sidebar-toggle-btn { width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: 0; border-radius: 6px; color: #64748b; }
            .media-sidebar-toggle-icon { transition: transform .25s ease; }
            .media-sidebar-header[aria-expanded="true"] .media-sidebar-toggle-icon { transform: rotate(180deg); }
            .media-sidebar-collapse-body { max-height: 65vh; overflow-y: auto; padding-right: 2px; }
        }
        @media (min-width: 768px) {
            .media-sidebar-header { pointer-events: none; }
            .page-wrapper:has(.media-library-shell) { overflow-x: clip; }
            .media-folder-sidebar {
                position: sticky;
                top: 88px;
                max-height: calc(100vh - 104px);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            #mediaSidebarCollapse {
                display: flex !important;
                flex-direction: column;
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }
            .media-sidebar-collapse-body {
                flex: 1;
                min-height: 0;
                overflow-y: auto;
                padding-right: 2px;
            }
        }
        @media (max-width: 575.98px) {
            .media-dropzone { min-height: 164px; padding: 18px; }
            .media-file-grid.is-list .media-preview { width: 100px; flex-basis: 100px; }
            .media-file-grid.is-list .media-file-card .card-body { align-items: flex-start; flex-direction: column; gap: 8px; }
            .media-select-overlay { top: 5px; left: 5px; padding: 2px 4px; }
            .media-source-badge { top: 5px; right: 5px; font-size: 0.65rem; }
            .media-file-card .card-subtitle { font-size: 0.75rem; }
            .media-file-card .copy-url-btn,
            .media-file-card button[type="submit"] { padding: 4px 6px; }
        }
    </style>
@endpush

@section('content')
    <x-admin.page-header :title="__('catalog.media.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('catalog.media.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @php
        $uploadFolder = $activeFolder === 'all' ? 'general' : $activeFolder;
        $storageUsed = $storageUsage['used'];
        $storageLimit = $storageUsage['limit'];
        $creditsUsed = $storageUsage['credits_used'] ?? null;
        $creditsLimit = $storageUsage['credits_limit'] ?? null;
        $storagePercent = $storageUsed !== null && $storageLimit ? min(100, ($storageUsed / $storageLimit) * 100) : null;
        $formatStorage = fn (int $bytes): string => $bytes >= 1024 ** 3
            ? number_format($bytes / 1024 ** 3, 1).' GB'
            : number_format($bytes / 1024 ** 2, 0).' MB';
    @endphp

    <div class="media-library-shell">
        @if (! $isConfigured)
            <div class="alert alert-warning alert-dismissible fade show mb-3 border-0 shadow-sm" role="alert">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap pe-4">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-alert-triangle fs-6 me-2 text-warning"></i>
                        <div>
                            <h6 class="alert-heading fw-semibold mb-1">{{ __('catalog.media.local_storage_mode') }}</h6>
                            <p class="mb-0 fs-2 opacity-75">{{ __('catalog.media.local_warning') }}</p>
                        </div>
                    </div>
                    @can('settings.update')
                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cloudinaryConnectionModal">
                            <i class="ti ti-cloud me-1"></i>{{ __('catalog.media.cloudinary_manage') }}
                        </button>
                    @endcan
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-3">
            <aside class="col-lg-3 col-md-4">
                <div class="media-folder-sidebar media-library-panel media-sidebar">
                    <div class="media-sidebar-header"
                         role="button"
                         data-bs-toggle="collapse"
                         data-bs-target="#mediaSidebarCollapse"
                         aria-expanded="false"
                         aria-controls="mediaSidebarCollapse">
                        <div class="media-sidebar-title">
                            <span class="d-inline-flex align-items-center">
                                <i class="ti ti-folder me-1 text-danger"></i>{{ __('catalog.media.cloud_folders') }}
                            </span>
                            <span class="badge bg-danger-subtle text-danger d-md-none ms-2 px-2 py-1 rounded-pill text-capitalize fw-semibold">
                                {{ $activeFolder === 'all' ? __('catalog.media.all_folders') : $activeFolder }}
                            </span>
                        </div>
                        <button type="button" class="media-sidebar-toggle-btn d-md-none" aria-label="{{ __('catalog.media.cloud_folders') }}">
                            <i class="ti ti-chevron-down fs-4 media-sidebar-toggle-icon"></i>
                        </button>
                    </div>

                    <div class="collapse d-md-block" id="mediaSidebarCollapse">
                        <div class="media-sidebar-collapse-body pt-2 pt-md-0">
                            <nav data-tour="media.folders" aria-label="{{ __('catalog.media.cloud_folders') }}">
                                <a href="{{ route('admin.media.index', ['folder' => 'all']) }}" class="media-folder-link {{ $activeFolder === 'all' ? 'is-active' : '' }}">
                                    <span class="folder-name"><i class="ti ti-folders"></i>{{ __('catalog.media.all_folders') }}</span>
                                    @if ($activeFolder === 'all')<span class="media-folder-count">{{ $resources->total() }}</span>@endif
                                </a>
                                @foreach ($folders as $folder)
                                    <a href="{{ route('admin.media.index', ['folder' => $folder]) }}" class="media-folder-link {{ $activeFolder === $folder ? 'is-active' : '' }}">
                                        <span class="folder-name"><i class="ti ti-folder"></i><span class="text-capitalize">{{ $folder }}</span></span>
                                        @if ($activeFolder === $folder)<span class="media-folder-count">{{ $resources->total() }}</span>@else<i class="ti ti-chevron-right"></i>@endif
                                    </a>
                                @endforeach
                            </nav>
                            <div data-tour="media.storage" class="media-storage-card">
                                <i class="ti ti-database media-storage-icon"></i>
                                <div class="media-storage-content">
                                    <p class="media-storage-label">{{ __('catalog.media.storage_used') }}</p>
                                    @if ($storageUsed !== null && $storageLimit !== null)
                                        <p class="media-storage-amount">{{ $formatStorage($storageUsed) }} <span>/ {{ $formatStorage($storageLimit) }}</span></p>
                                        <div class="media-storage-progress" aria-label="{{ round($storagePercent) }}%"><span style="width: {{ $storagePercent }}%"></span></div>
                                    @else
                                        <p class="media-storage-amount"><span>{{ __('catalog.media.storage_unavailable') }}</span></p>
                                    @endif
                                    @if ($creditsUsed !== null && $creditsLimit !== null)
                                        <p class="media-storage-label mt-2">{{ __('catalog.media.credits_used') }}</p>
                                        <p class="media-storage-amount">{{ number_format($creditsUsed, 1) }} <span>/ {{ number_format($creditsLimit, 0) }}</span></p>
                                    @endif
                                </div>
                            </div>
                            <div data-tour="media.cloud" class="media-cloud-card">
                                <div class="media-cloud-status {{ $cloudinaryConnection['configured'] ? '' : 'is-local' }}">
                                    <i class="ti {{ $cloudinaryConnection['configured'] ? 'ti-cloud-check' : 'ti-cloud-off' }}"></i>
                                    <div class="flex-grow-1 min-w-0">
                                        <p class="media-cloud-title">{{ $cloudinaryConnection['configured'] ? __('catalog.media.cloudinary_connected_status') : __('catalog.media.local_storage_mode') }}</p>
                                        @if ($cloudinaryConnection['configured'])
                                            <p class="media-cloud-detail">{{ __('catalog.media.cloudinary_cloud_name', ['name' => $cloudinaryConnection['cloud_name']]) }}</p>
                                        @endif
                                        @can('settings.update')
                                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" data-bs-toggle="modal" data-bs-target="#cloudinaryConnectionModal">
                                                {{ __('catalog.media.cloudinary_manage') }}
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="col-lg-9 col-md-8">
                <section data-tour="media.upload" class="media-library-panel media-upload-panel mb-3">
                    <div class="media-panel-heading"><i class="ti ti-upload"></i>{{ __('catalog.media.upload_to_folder', ['folder' => ucfirst($uploadFolder)]) }}</div>
                    <form method="POST" action="{{ route('admin.media.upload') }}" enctype="multipart/form-data" id="mediaUploadForm">
                        @csrf
                        <input type="hidden" name="folder" value="{{ $uploadFolder }}">
                        <input type="file" class="visually-hidden" name="file[]" id="media_file_upload" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.mp4" multiple required>
                        <div class="media-dropzone" id="mediaUploadDropzone" tabindex="0" role="button" aria-controls="media_file_upload">
                            <div class="media-dropzone-content">
                                <i class="ti ti-cloud-upload media-dropzone-icon"></i>
                                <p class="media-dropzone-title">{{ __('catalog.media.dropzone_title') }}</p>
                                <p class="media-dropzone-hint">{{ __('catalog.media.dropzone_hint') }}</p>
                                <label class="media-choose-file" for="media_file_upload"><i class="ti ti-folder-open"></i>{{ __('catalog.media.select_file') }}</label>
                                <p class="media-selected-file d-none" id="mediaSelectedFile"></p>
                                <p class="media-upload-help">{{ __('catalog.media.upload_help') }}</p>
                            </div>
                        </div>

                        <div class="media-upload-previews-wrap d-none" id="mediaUploadPreviewsWrap">
                            <div class="d-flex align-items-center justify-content-between mb-1 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 fw-semibold" id="mediaUploadCountBadge">0 {{ __('catalog.media.files_selected') }}</span>
                                    <small class="text-muted fw-semibold" id="mediaUploadSizeBadge"></small>
                                </div>
                                <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0 fw-semibold" id="mediaClearAllBtn">
                                    <i class="ti ti-trash me-1"></i>{{ __('admin.media.clear_all') }}
                                </button>
                            </div>
                            <div class="media-upload-previews-grid" id="mediaUploadPreviewsGrid">
                                <!-- Dynamic thumbnail previews -->
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-3">
                            <button type="submit" class="btn btn-primary media-upload-submit d-none mt-0" id="mediaUploadSubmit">
                                <i class="ti ti-upload me-1"></i>{{ __('catalog.media.upload') }} (<span id="mediaUploadSubmitCount">0</span>)
                            </button>
                            <label class="btn btn-outline-secondary btn-sm d-none mb-0" id="mediaAddMoreBtn" for="media_file_upload">
                                <i class="ti ti-plus me-1"></i>{{ __('admin.media.add_more') }}
                            </label>
                        </div>
                        @error('file')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </form>
                </section>

                <section data-tour="media.files" class="media-library-panel media-files-panel">
                    <div class="media-toolbar">
                        <div class="d-flex align-items-center justify-content-between w-100 w-lg-auto gap-2">
                            <h5 class="media-toolbar-title"><i class="ti ti-photo me-1 text-danger"></i>{{ __('catalog.media.files') }} ({{ $resources->total() }})</h5>
                            @if ($resources->isNotEmpty())
                                <div data-tour="media.view" class="media-toolbar-actions d-flex d-lg-none align-items-center gap-2">
                                    <form method="GET" class="m-0">
                                        <input type="hidden" name="folder" value="{{ $activeFolder }}">
                                        <select class="media-sort-label" name="sort" onchange="this.form.submit()" aria-label="{{ __('catalog.media.sort') }}">
                                            <option value="newest" @selected($sort === 'newest')>{{ __('catalog.media.newest_first') }}</option>
                                            <option value="oldest" @selected($sort === 'oldest')>{{ __('catalog.media.oldest_first') }}</option>
                                            <option value="name_asc" @selected($sort === 'name_asc')>{{ __('catalog.media.name_asc') }}</option>
                                            <option value="name_desc" @selected($sort === 'name_desc')>{{ __('catalog.media.name_desc') }}</option>
                                        </select>
                                    </form>
                                    <div class="media-view-toggle" aria-label="{{ __('catalog.media.files') }}">
                                        <button type="button" class="is-active" data-media-view="grid" aria-label="{{ __('admin.media.grid_view') }}"><i class="ti ti-layout-grid"></i></button>
                                        <button type="button" data-media-view="list" aria-label="{{ __('admin.media.list_view') }}"><i class="ti ti-list"></i></button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($resources->isNotEmpty())
                            <div class="media-toolbar-controls">
                                <div class="media-bulk-bar">
                                    <label data-tour="media.select-all" class="media-bulk-select-all">
                                        <input type="checkbox" class="form-check-input" id="mediaSelectAll">
                                        <span>{{ __('catalog.media.select_all') }}</span>
                                    </label>
                                    @can('media.delete')
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="mediaBulkDeleteBtn" disabled>
                                            <i class="ti ti-trash me-1"></i><span>{{ __('catalog.media.delete_selected') }} (<span id="mediaSelectedCount">0</span>)</span>
                                        </button>
                                    @endcan
                                </div>
                                <div data-tour="media.view" class="media-toolbar-actions d-none d-lg-flex align-items-center gap-2">
                                    <form method="GET" class="m-0">
                                        <input type="hidden" name="folder" value="{{ $activeFolder }}">
                                        <select class="media-sort-label" name="sort" onchange="this.form.submit()" aria-label="{{ __('catalog.media.sort') }}">
                                            <option value="newest" @selected($sort === 'newest')>{{ __('catalog.media.newest_first') }}</option>
                                            <option value="oldest" @selected($sort === 'oldest')>{{ __('catalog.media.oldest_first') }}</option>
                                            <option value="name_asc" @selected($sort === 'name_asc')>{{ __('catalog.media.name_asc') }}</option>
                                            <option value="name_desc" @selected($sort === 'name_desc')>{{ __('catalog.media.name_desc') }}</option>
                                        </select>
                                    </form>
                                    <div class="media-view-toggle" aria-label="{{ __('catalog.media.files') }}">
                                        <button type="button" class="is-active" data-media-view="grid" aria-label="{{ __('admin.media.grid_view') }}"><i class="ti ti-layout-grid"></i></button>
                                        <button type="button" data-media-view="list" aria-label="{{ __('admin.media.list_view') }}"><i class="ti ti-list"></i></button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @can('media.delete')
                        <form method="POST" action="{{ route('admin.media.bulk-delete') }}" id="mediaBulkDeleteForm" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endcan

                    @if ($resources->isEmpty())
                        <div class="media-empty">
                            <img src="{{ asset('admin-assets/images/icons/images-empty.png') }}" alt="" aria-hidden="true">
                            <h6>{{ __('catalog.media.empty_title') }}</h6>
                            <p>{{ __('catalog.media.empty_hint') }}</p>
                        </div>
                    @else
                        <div class="row row-cols-2 row-cols-xl-3 g-2 g-sm-3 mt-1 media-file-grid" id="mediaFileGrid">
                            @foreach ($resources as $resource)
                                @php
                                    $isImage = in_array(strtolower($resource['format']), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']);
                                    $isVideo = ($resource['resource_type'] ?? 'image') === 'video';
                                    $isPdf = strtolower($resource['format']) === 'pdf';
                                    $displayName = basename($resource['public_id']);
                                    $resourceSource = $resource['source'] ?? ($isConfigured ? 'cloudinary' : 'local');
                                @endphp
                                <div class="col">
                                    <article class="card h-100 border shadow-none overflow-hidden media-file-card">
                                        <div class="ratio ratio-4x3 bg-light border-bottom d-flex align-items-center justify-content-center overflow-hidden position-relative media-preview">
                                             @can('media.delete')
                                                 <label class="media-select-overlay">
                                                     <input type="checkbox" class="form-check-input media-select-checkbox" data-public-id="{{ $resource['public_id'] }}" data-resource-type="{{ $resource['resource_type'] ?? 'image' }}" data-source="{{ $resourceSource }}" aria-label="{{ __('catalog.media.select_all') }}">
                                                 </label>
                                             @endcan
                                             @if ($isConfigured && $resourceSource === 'local')
                                                 <span class="badge bg-secondary media-source-badge">{{ __('catalog.media.source_local_badge') }}</span>
                                             @endif
                                             @if ($isVideo)
                                                 <video class="position-absolute w-100 h-100 start-0 top-0" controls preload="metadata" style="object-fit: contain;"><source src="{{ $resource['secure_url'] }}" type="video/mp4"></video>
                                             @elseif ($isImage)
                                                 <img src="{{ $resource['secure_url'] }}" alt="{{ $resource['public_id'] }}" class="img-fluid position-absolute w-100 h-100 start-0 top-0" style="object-fit: contain;">
                                             @elseif ($isPdf)
                                                 <div class="media-pdf-preview position-absolute w-100 h-100 start-0 top-0" data-pdf-preview data-pdf-url="{{ $resource['secure_url'] }}" aria-label="{{ $displayName }}">
                                                     <canvas aria-hidden="true"></canvas>
                                                 </div>
                                             @else
                                                 <div class="d-flex flex-column align-items-center justify-content-center"><i class="ti ti-file-text fs-8 text-primary mb-2"></i><span class="badge bg-primary text-uppercase">{{ $resource['format'] }}</span></div>
                                             @endif
                                         </div>
                                         <div class="card-body p-2 p-sm-3">
                                             <div>
                                                 <h6 class="card-subtitle text-dark fw-semibold text-truncate mb-1" title="{{ $displayName }}">
                                                     @if ($isPdf)
                                                         <a class="media-file-name-link" href="{{ $resource['secure_url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $displayName }}">{{ $displayName }} <i class="ti ti-external-link" aria-hidden="true"></i></a>
                                                     @else
                                                         {{ $displayName }}
                                                     @endif
                                                 </h6>
                                                 <div class="d-flex justify-content-between align-items-center text-muted fs-2 mb-2 mb-sm-3"><span>{{ number_format($resource['bytes'] / 1024, 1) }} KB</span><span>{{ date('d-m-Y', strtotime($resource['created_at'])) }}</span></div>
                                             </div>
                                             <div class="d-flex gap-1 gap-sm-2">
                                                 <button type="button" class="btn btn-sm btn-outline-secondary flex-fill copy-url-btn" data-url="{{ $resource['secure_url'] }}" title="{{ __('catalog.media.copy_url') }}"><i class="ti ti-copy fs-4"></i><span class="visually-hidden">{{ __('catalog.media.copy_url') }}</span></button>
                                                 <form method="POST" action="{{ route('admin.media.delete') }}" class="d-inline flex-fill">
                                                     @csrf
                                                     @method('DELETE')
                                                     <input type="hidden" name="public_id" value="{{ $resource['public_id'] }}">
                                                     <input type="hidden" name="resource_type" value="{{ $resource['resource_type'] ?? 'image' }}">
                                                     <input type="hidden" name="source" value="{{ $resourceSource }}">
                                                     <button type="submit" class="btn btn-sm btn-outline-danger w-100" title="{{ __('catalog.actions.delete') }}" onclick="return confirm('{{ __('catalog.media.delete_confirm') }}')"><i class="ti ti-trash fs-4"></i><span class="visually-hidden">{{ __('catalog.actions.delete') }}</span></button>
                                                 </form>
                                             </div>
                                         </div>
                                     </article>
                                 </div>
                             @endforeach
                         </div>
                    @endif

                    @if($resources->total() > 0)<div class="pt-3 mt-3 border-top">@include('admin.shared.pagination', ['paginator' => $resources])</div>@endif
                </section>
            </main>
        </div>
    </div>

    @can('settings.update')
        <div class="modal fade" id="cloudinaryConnectionModal" tabindex="-1" aria-labelledby="cloudinaryConnectionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="cloudinaryConnectionModalLabel">{{ __('catalog.media.cloudinary_connect_title') }}</h5>
                            @if ($cloudinaryConnection['configured'])
                                <p class="mb-0 mt-1 small text-success"><i class="ti ti-shield-check me-1"></i>{{ __('catalog.media.cloudinary_cloud_name', ['name' => $cloudinaryConnection['cloud_name']]) }}</p>
                            @endif
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if ($cloudinaryConnection['configured'])
                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-success-subtle border border-success-subtle mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="ti ti-circle-check fs-6 text-success"></i>
                                    <div>
                                        <p class="mb-0 fw-semibold text-success">{{ __('catalog.media.cloudinary_connected_status') }}</p>
                                        <p class="mb-0 small text-muted">{{ __('catalog.media.cloudinary_cloud_name', ['name' => $cloudinaryConnection['cloud_name']]) }}</p>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#cloudinaryEditCollapse" aria-expanded="{{ (session('cloudinary_action') !== 'disconnect' && $errors->cloudinary->any()) ? 'true' : 'false' }}">
                                    <i class="ti ti-edit me-1"></i>{{ __('admin.media.edit_keys') }}
                                </button>
                            </div>
                        @endif

                        <div class="{{ $cloudinaryConnection['configured'] ? 'collapse' : '' }} {{ ($cloudinaryConnection['configured'] && session('cloudinary_action') !== 'disconnect' && $errors->cloudinary->any()) ? 'show' : '' }} mb-3" id="cloudinaryEditCollapse">
                            <div class="{{ $cloudinaryConnection['configured'] ? 'card card-body bg-light border p-3' : '' }}">
                                @if ($cloudinaryConnection['configured'])
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-key me-1 text-primary"></i>{{ __('admin.media.update_keys') }}</h6>
                                        <button type="button" class="btn-close btn-sm" data-bs-toggle="collapse" data-bs-target="#cloudinaryEditCollapse" aria-label="Close"></button>
                                    </div>
                                @endif
                                <p class="text-muted small">{{ __('catalog.media.cloudinary_connect_help') }}</p>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <a class="btn btn-sm btn-outline-primary" href="https://cloudinary.com/users/register/free" target="_blank" rel="noopener noreferrer">
                                        <i class="ti ti-brand-google me-1"></i>{{ __('catalog.media.cloudinary_signup') }}
                                    </a>
                                    <a class="btn btn-sm btn-outline-secondary" href="https://console.cloudinary.com/settings/api-keys" target="_blank" rel="noopener noreferrer">
                                        <i class="ti ti-key me-1"></i>{{ __('catalog.media.cloudinary_api_keys') }}
                                    </a>
                                </div>

                                <form method="POST" action="{{ route('admin.media.cloudinary.connect') }}" autocomplete="off">
                                    @csrf
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <label class="form-label fw-semibold mb-0" for="cloudinary_cloud_name">{{ __('catalog.media.cloudinary_cloud_name_field') }}</label>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-secondary" data-cloudinary-guide-open data-guide-field="cloud_name" aria-label="{{ __('catalog.media.cloudinary_field_help', ['field' => __('catalog.media.cloudinary_cloud_name_field')]) }}" title="{{ __('catalog.media.cloudinary_field_help', ['field' => __('catalog.media.cloudinary_cloud_name_field')]) }}">
                                                <i class="ti ti-help-circle fs-5"></i>
                                            </button>
                                        </div>
                                        <input type="text" class="form-control @if(session('cloudinary_action') !== 'disconnect' && $errors->cloudinary->has('cloud_name')) is-invalid @endif" id="cloudinary_cloud_name" name="cloud_name" value="{{ old('cloud_name', $cloudinaryConnection['cloud_name'] ?? '') }}" placeholder="{{ __('catalog.media.cloudinary_cloud_name_placeholder') }}" spellcheck="false" autocapitalize="none" autocomplete="off" required>
                                        @if (session('cloudinary_action') !== 'disconnect')
                                            @foreach ($errors->cloudinary->get('cloud_name') as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <label class="form-label fw-semibold mb-0" for="cloudinary_api_key">{{ __('catalog.media.cloudinary_api_key_field') }}</label>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-secondary" data-cloudinary-guide-open data-guide-field="api_key" aria-label="{{ __('catalog.media.cloudinary_field_help', ['field' => __('catalog.media.cloudinary_api_key_field')]) }}" title="{{ __('catalog.media.cloudinary_field_help', ['field' => __('catalog.media.cloudinary_api_key_field')]) }}">
                                                <i class="ti ti-help-circle fs-5"></i>
                                            </button>
                                        </div>
                                        <input type="text" class="form-control @if(session('cloudinary_action') !== 'disconnect' && $errors->cloudinary->has('api_key')) is-invalid @endif" id="cloudinary_api_key" name="api_key" value="{{ old('api_key', '') }}" placeholder="{{ __('catalog.media.cloudinary_api_key_placeholder') }}" spellcheck="false" autocapitalize="none" autocomplete="off" required>
                                        @if ($cloudinaryConnection['configured'])
                                            <div class="form-text">{{ __('catalog.media.cloudinary_current_value', ['masked' => $cloudinaryConnection['api_key']]) }}</div>
                                        @endif
                                        @if (session('cloudinary_action') !== 'disconnect')
                                            @foreach ($errors->cloudinary->get('api_key') as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <label class="form-label fw-semibold mb-0" for="cloudinary_api_secret">{{ __('catalog.media.cloudinary_api_secret_field') }}</label>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-secondary" data-cloudinary-guide-open data-guide-field="api_secret" aria-label="{{ __('catalog.media.cloudinary_field_help', ['field' => __('catalog.media.cloudinary_api_secret_field')]) }}" title="{{ __('catalog.media.cloudinary_field_help', ['field' => __('catalog.media.cloudinary_api_secret_field')]) }}">
                                                <i class="ti ti-help-circle fs-5"></i>
                                            </button>
                                        </div>
                                        <div class="input-group">
                                            <input type="password" class="form-control @if(session('cloudinary_action') !== 'disconnect' && $errors->cloudinary->has('api_secret')) is-invalid @endif" id="cloudinary_api_secret" name="api_secret" value="{{ old('api_secret', '') }}" placeholder="{{ __('catalog.media.cloudinary_api_secret_placeholder') }}" autocomplete="new-password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="toggleApiSecretBtn" title="{{ __('admin.media.toggle_secret') }}">
                                                <i class="ti ti-eye" id="toggleApiSecretIcon"></i>
                                            </button>
                                        </div>
                                        @if ($cloudinaryConnection['configured'])
                                            <div class="form-text">{{ __('catalog.media.cloudinary_current_value', ['masked' => $cloudinaryConnection['api_secret']]) }}</div>
                                        @endif
                                        @if (session('cloudinary_action') !== 'disconnect')
                                            @foreach ($errors->cloudinary->get('api_secret') as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
                                        @endif
                                        <div class="form-text"><i class="ti ti-lock me-1"></i>{{ __('catalog.media.cloudinary_credentials_security') }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold" for="cloudinary_current_password">{{ __('catalog.media.current_password') }}</label>
                                        <input type="password" class="form-control @if(session('cloudinary_action') !== 'disconnect' && $errors->cloudinary->has('current_password')) is-invalid @endif" id="cloudinary_current_password" name="current_password" autocomplete="current-password" placeholder="{{ __('catalog.media.current_password') }}..." required>
                                        @if (session('cloudinary_action') !== 'disconnect')
                                            @foreach ($errors->cloudinary->get('current_password') as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
                                        @endif
                                        <div class="form-text">{{ __('catalog.media.current_password_help') }}</div>
                                    </div>
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="ti ti-shield-check me-1"></i>{{ __($cloudinaryConnection['configured'] ? 'catalog.media.cloudinary_replace_submit' : 'catalog.media.cloudinary_connect_submit') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($cloudinaryConnection['configured'])
                            <div class="border-top pt-3">
                                <form method="POST" action="{{ route('admin.media.cloudinary.disconnect') }}" autocomplete="off" onsubmit="return confirm('{{ __('catalog.media.cloudinary_disconnect_confirm') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold" for="cloudinary_disconnect_password">{{ __('catalog.media.current_password') }}</label>
                                        <input type="password" class="form-control @if(session('cloudinary_action') === 'disconnect' && $errors->cloudinary->has('current_password')) is-invalid @endif" id="cloudinary_disconnect_password" name="current_password" autocomplete="current-password" placeholder="{{ __('catalog.media.current_password') }}..." required>
                                        @if (session('cloudinary_action') === 'disconnect')
                                            @foreach ($errors->cloudinary->get('current_password') as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
                                        @endif
                                        <div class="form-text">{{ __('catalog.media.current_password_help') }}</div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger w-100"><i class="ti ti-cloud-off me-1"></i>{{ __('catalog.media.cloudinary_disconnect') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="cloudinaryCredentialGuideModal" tabindex="-1" aria-labelledby="cloudinaryCredentialGuideModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cloudinaryCredentialGuideModalLabel">{{ __('catalog.media.cloudinary_guide_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">{{ __('catalog.media.cloudinary_guide_intro') }}</p>
                        <img class="img-fluid rounded border d-block mx-auto" src="{{ asset('admin-assets/images/guides/cloudinary-api-keys-guide.png') }}" alt="{{ __('catalog.media.cloudinary_guide_image_alt') }}" width="1852" height="847">
                        <div class="alert alert-warning d-flex gap-2 align-items-start mt-3 mb-0" role="note">
                            <i class="ti ti-shield-lock fs-4"></i>
                            <span>{{ __('catalog.media.cloudinary_guide_secret') }}</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-outline-secondary" href="https://console.cloudinary.com/settings/api-keys" target="_blank" rel="noopener noreferrer">
                            <i class="ti ti-external-link me-1"></i>{{ __('catalog.media.cloudinary_api_keys') }}
                        </a>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('catalog.media.cloudinary_guide_back') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const renderPdfPreviews = async function () {
                const previews = Array.from(document.querySelectorAll('[data-pdf-preview]'));
                if (! previews.length) return;

                try {
                    const pdfjs = await import(@json(asset('admin-assets/vendor/pdfjs/pdf.min.mjs')));
                    pdfjs.GlobalWorkerOptions.workerSrc = @json(asset('admin-assets/vendor/pdfjs/pdf.worker.min.mjs'));

                    await Promise.all(previews.map(async function (preview) {
                        try {
                            const document = await pdfjs.getDocument(preview.dataset.pdfUrl).promise;
                            const page = await document.getPage(1);
                            const initialViewport = page.getViewport({ scale: 1 });
                            const scale = Math.min(
                                preview.clientWidth / initialViewport.width,
                                preview.clientHeight / initialViewport.height,
                            );
                            const viewport = page.getViewport({ scale: scale * window.devicePixelRatio });
                            const canvas = preview.querySelector('canvas');
                            canvas.width = Math.ceil(viewport.width);
                            canvas.height = Math.ceil(viewport.height);
                            canvas.style.width = Math.ceil(viewport.width / window.devicePixelRatio) + 'px';
                            canvas.style.height = Math.ceil(viewport.height / window.devicePixelRatio) + 'px';
                            await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                        } catch (_) {
                            preview.classList.add('is-unavailable');
                        }
                    }));
                } catch (_) {
                    previews.forEach(function (preview) { preview.classList.add('is-unavailable'); });
                }
            };

            renderPdfPreviews();

            @can('settings.update')
                @if ($errors->cloudinary->any())
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('cloudinaryConnectionModal')).show();
                @endif
                const cloudinaryConnectionModalElement = document.getElementById('cloudinaryConnectionModal');
                const cloudinaryGuideModalElement = document.getElementById('cloudinaryCredentialGuideModal');
                if (cloudinaryConnectionModalElement && cloudinaryGuideModalElement) {
                    const cloudinaryConnectionModal = bootstrap.Modal.getOrCreateInstance(cloudinaryConnectionModalElement);
                    const cloudinaryGuideModal = bootstrap.Modal.getOrCreateInstance(cloudinaryGuideModalElement);
                    let cloudinaryGuideTrigger = null;

                    document.querySelectorAll('[data-cloudinary-guide-open]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            cloudinaryGuideTrigger = button;
                            cloudinaryConnectionModalElement.addEventListener('hidden.bs.modal', function () {
                                cloudinaryGuideModal.show();
                            }, { once: true });
                            cloudinaryConnectionModal.hide();
                        });
                    });

                    cloudinaryGuideModalElement.addEventListener('hidden.bs.modal', function () {
                        if (! cloudinaryGuideTrigger) return;
                        cloudinaryConnectionModalElement.addEventListener('shown.bs.modal', function () {
                            cloudinaryGuideTrigger.focus();
                            cloudinaryGuideTrigger = null;
                        }, { once: true });
                        cloudinaryConnectionModal.show();
                    });
                }
            @endcan
            const input = document.getElementById('media_file_upload');
            const dropzone = document.getElementById('mediaUploadDropzone');
            const selectedFile = document.getElementById('mediaSelectedFile');
            const submit = document.getElementById('mediaUploadSubmit');
            const previewsWrap = document.getElementById('mediaUploadPreviewsWrap');
            const previewsGrid = document.getElementById('mediaUploadPreviewsGrid');
            const countBadge = document.getElementById('mediaUploadCountBadge');
            const sizeBadge = document.getElementById('mediaUploadSizeBadge');
            const submitCount = document.getElementById('mediaUploadSubmitCount');
            const clearAllBtn = document.getElementById('mediaClearAllBtn');
            const addMoreBtn = document.getElementById('mediaAddMoreBtn');
            const uploadForm = document.getElementById('mediaUploadForm');

            let currentFiles = [];
            let objectUrls = [];

            const formatBytes = function (bytes) {
                if (! bytes || bytes === 0) return '0 B';
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            };

            const cleanupUrls = function () {
                objectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
                objectUrls = [];
            };

            const renderPreviews = function () {
                cleanupUrls();
                if (! previewsGrid) return;
                previewsGrid.innerHTML = '';

                const count = currentFiles.length;
                const hasFiles = count > 0;

                if (previewsWrap) previewsWrap.classList.toggle('d-none', ! hasFiles);
                if (submit) submit.classList.toggle('d-none', ! hasFiles);
                if (addMoreBtn) addMoreBtn.classList.toggle('d-none', ! hasFiles);

                if (! hasFiles) {
                    if (selectedFile) {
                        selectedFile.textContent = '';
                        selectedFile.classList.add('d-none');
                    }
                    return;
                }

                let totalBytes = 0;
                currentFiles.forEach(function (file) { totalBytes += file.size; });

                if (countBadge) {
                    countBadge.textContent = count + ' {{ __('catalog.media.files_selected') }}';
                }
                if (sizeBadge) {
                    sizeBadge.textContent = '(' + formatBytes(totalBytes) + ')';
                }
                if (submitCount) {
                    submitCount.textContent = count;
                }

                currentFiles.forEach(function (file, index) {
                    const item = document.createElement('div');
                    item.className = 'media-preview-item';
                    item.dataset.index = index;

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'media-preview-remove';
                    removeBtn.title = @json(__('admin.media.remove_file'));
                    removeBtn.setAttribute('aria-label', @json(__('admin.media.remove_file')));
                    removeBtn.innerHTML = '<i class="ti ti-x"></i>';
                    removeBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeFile(index);
                    });
                    item.appendChild(removeBtn);

                    if (file.type && file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.className = 'media-preview-thumb';
                        const url = URL.createObjectURL(file);
                        objectUrls.push(url);
                        img.src = url;
                        img.alt = file.name;
                        item.appendChild(img);
                    } else {
                        const doc = document.createElement('div');
                        doc.className = 'media-preview-doc';
                        let iconClass = 'ti ti-file text-secondary';
                        if (file.type && file.type.startsWith('video/')) {
                            iconClass = 'ti ti-video text-primary';
                        } else if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                            iconClass = 'ti ti-file-type-pdf text-danger';
                        }
                        doc.innerHTML = '<i class="' + iconClass + '"></i><span class="doc-name" title="' + file.name + '">' + file.name + '</span>';
                        item.appendChild(doc);
                    }

                    const sizeTag = document.createElement('div');
                    sizeTag.className = 'media-preview-size';
                    sizeTag.textContent = formatBytes(file.size);
                    item.appendChild(sizeTag);

                    previewsGrid.appendChild(item);
                });
            };

            const syncInput = function () {
                if (typeof DataTransfer !== 'undefined') {
                    const dt = new DataTransfer();
                    currentFiles.forEach(function (file) { dt.items.add(file); });
                    input.files = dt.files;
                }
                renderPreviews();
            };

            const removeFile = function (index) {
                if (index >= 0 && index < currentFiles.length) {
                    currentFiles.splice(index, 1);
                    syncInput();
                }
            };

            const addFiles = function (filesList) {
                const incoming = Array.from(filesList);
                incoming.forEach(function (nf) {
                    const exists = currentFiles.some(function (cf) {
                        return cf.name === nf.name && cf.size === nf.size && cf.lastModified === nf.lastModified;
                    });
                    if (! exists) {
                        currentFiles.push(nf);
                    }
                });
                syncInput();
            };

            input?.addEventListener('change', function () {
                if (input.files && input.files.length) {
                    addFiles(input.files);
                }
            });

            clearAllBtn?.addEventListener('click', function (e) {
                e.preventDefault();
                currentFiles = [];
                syncInput();
            });

            dropzone?.addEventListener('click', function (event) {
                if (! event.target.closest('label') && ! event.target.closest('button')) {
                    input?.click();
                }
            });
            dropzone?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    input?.click();
                }
            });
            ['dragenter', 'dragover'].forEach(function (eventName) {
                dropzone?.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.add('is-dragging');
                });
            });
            ['dragleave', 'drop'].forEach(function (eventName) {
                dropzone?.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove('is-dragging');
                });
            });
            dropzone?.addEventListener('drop', function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
                if (event.dataTransfer && event.dataTransfer.files.length) {
                    addFiles(event.dataTransfer.files);
                }
            });

            uploadForm?.addEventListener('submit', function () {
                if (submit) {
                    submit.disabled = true;
                    submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> {{ __('catalog.media.upload') }}...';
                }
            });
            document.querySelectorAll('[data-media-view]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const isList = button.dataset.mediaView === 'list';
                    document.getElementById('mediaFileGrid')?.classList.toggle('is-list', isList);
                    document.querySelectorAll('[data-media-view]').forEach(function (item) {
                        item.classList.toggle('is-active', item.dataset.mediaView === button.dataset.mediaView);
                    });
                });
            });
            document.querySelectorAll('.copy-url-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    navigator.clipboard.writeText(button.dataset.url).then(function () {
                        button.classList.replace('btn-outline-secondary', 'btn-success');
                        setTimeout(function () { button.classList.replace('btn-success', 'btn-outline-secondary'); }, 1600);
                    }).catch(function () { alert('{{ __('catalog.media.copy_failed') }}'); });
                });
            });

            const selectAllCheckbox = document.getElementById('mediaSelectAll');
            const bulkDeleteBtn = document.getElementById('mediaBulkDeleteBtn');
            const selectedCountEl = document.getElementById('mediaSelectedCount');
            const bulkDeleteForm = document.getElementById('mediaBulkDeleteForm');
            const getMediaCheckboxes = function () { return Array.from(document.querySelectorAll('.media-select-checkbox')); };

            const refreshBulkState = function () {
                const all = getMediaCheckboxes();
                const checked = all.filter(function (checkbox) { return checkbox.checked; });
                if (selectedCountEl) selectedCountEl.textContent = checked.length;
                if (bulkDeleteBtn) bulkDeleteBtn.disabled = checked.length === 0;
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = all.length > 0 && checked.length === all.length;
                    selectAllCheckbox.indeterminate = checked.length > 0 && checked.length < all.length;
                }
            };

            getMediaCheckboxes().forEach(function (checkbox) {
                checkbox.addEventListener('change', refreshBulkState);
            });

            selectAllCheckbox?.addEventListener('change', function () {
                getMediaCheckboxes().forEach(function (checkbox) { checkbox.checked = selectAllCheckbox.checked; });
                refreshBulkState();
            });

            bulkDeleteBtn?.addEventListener('click', function () {
                const checked = getMediaCheckboxes().filter(function (checkbox) { return checkbox.checked; });
                if (! checked.length || ! bulkDeleteForm) return;

                const confirmMessage = @json(__('catalog.media.bulk_delete_confirm')).replace(':count', checked.length);
                if (! confirm(confirmMessage)) return;

                bulkDeleteForm.querySelectorAll('input[name^="items"]').forEach(function (el) { el.remove(); });
                checked.forEach(function (checkbox, index) {
                    ['publicId', 'resourceType', 'source'].forEach(function (key, fieldIndex) {
                        const field = ['public_id', 'resource_type', 'source'][fieldIndex];
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'items[' + index + '][' + field + ']';
                        hidden.value = checkbox.dataset[key];
                        bulkDeleteForm.appendChild(hidden);
                    });
                });
                bulkDeleteForm.submit();
            });

            const sidebarCollapseEl = document.getElementById('mediaSidebarCollapse');
            const sidebarHeader = document.querySelector('.media-sidebar-header');
            if (sidebarCollapseEl && sidebarHeader) {
                sidebarCollapseEl.addEventListener('show.bs.collapse', function () {
                    sidebarHeader.setAttribute('aria-expanded', 'true');
                });
                sidebarCollapseEl.addEventListener('hide.bs.collapse', function () {
                    sidebarHeader.setAttribute('aria-expanded', 'false');
                });
            }

            const toggleApiSecretBtn = document.getElementById('toggleApiSecretBtn');
            const apiSecretInput = document.getElementById('cloudinary_api_secret');
            const toggleApiSecretIcon = document.getElementById('toggleApiSecretIcon');
            if (toggleApiSecretBtn && apiSecretInput) {
                toggleApiSecretBtn.addEventListener('click', function () {
                    const isPassword = apiSecretInput.type === 'password';
                    apiSecretInput.type = isPassword ? 'text' : 'password';
                    if (toggleApiSecretIcon) {
                        toggleApiSecretIcon.classList.toggle('ti-eye', !isPassword);
                        toggleApiSecretIcon.classList.toggle('ti-eye-off', isPassword);
                    }
                });
            }

            refreshBulkState();
        });
    </script>
@endpush
