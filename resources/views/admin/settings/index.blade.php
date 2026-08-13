@extends('admin.layouts.app')

@section('title', __('admin.settings.title'))

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .custom-social-row .form-control,
        .custom-social-row .select2-container--default .select2-selection--single {
            height: 40px !important;
            min-height: 40px !important;
            border-radius: 7px !important;
            font-size: 14px !important;
        }
        .custom-social-row .select2-container--default .select2-selection--single {
            display: flex !important;
            align-items: center !important;
            border: 1px solid #dfe5ef !important;
            background-color: #fff !important;
        }
        .custom-social-row .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal !important;
            display: flex !important;
            align-items: center !important;
            padding-left: 12px !important;
            color: #2a3547 !important;
        }
        .custom-social-row .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
            top: 1px !important;
        }
        .custom-social-row .btn-outline-danger {
            height: 40px !important;
            width: 40px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            float: right;
        }
        .border-dashed {
            border-style: dashed !important;
            border-width: 2px !important;
            border-color: #dfe5ef !important;
        }
        .select2-results__option {
            display: flex !important;
            align-items: center !important;
            padding: 8px 12px !important;
        }
        .nav-tabs {
            border-bottom: 2px solid var(--bs-primary) !important;
            gap: 8px;
        }
        .nav-tabs .nav-link {
            border: 1px solid var(--bs-primary) !important;
            border-bottom: none !important;
            color: var(--bs-primary) !important;
            background-color: #fff !important;
            font-weight: 700 !important;
            border-radius: 6px 6px 0 0 !important;
            padding: 10px 24px !important;
            transition: all 0.2s ease-in-out;
            font-size: 15px;
        }
        .nav-tabs .nav-link.active {
            color: #fff !important;
            background-color: var(--bs-primary) !important;
            border: 1px solid var(--bs-primary) !important;
            border-bottom: none !important;
        }
        .nav-tabs .nav-link:hover:not(.active) {
            background-color: rgba(var(--bs-primary-rgb), 0.1) !important;
        }

        /* Site Icon Browser Preview Styles */
        .site-icon-preview {
            --site-icon-preview-browser-top: #2c3338;
            --site-icon-preview-browser-bottom: #1e1e1e;
            --site-icon-address-bar-background: #3c434a;
            --site-icon-address-bar-close: #f0f0f1;
            --site-icon-address-bar-text: #f0f0f1;
            --site-icon-preview-browser-border: #43494e;
            --site-icon-shadow-1: rgba(0, 0, 0, 0.15);
            --site-icon-shadow-2: rgba(0, 0, 0, 0.3);
            --site-icon-shadow-3: rgba(0, 0, 0, 0.2);
            --site-icon-input-border: #8c8f94;
            position: relative;
            pointer-events: none !important;
        }
        .site-icon-preview.settings {
            height: 88px;
            padding: 10px 0 0 12px;
            width: 100%;
            max-width: 380px;
            margin: 12px 0 16px 0;
            background: #ffffff !important;
            border: 1px solid #dcdcde !important;
            border-radius: 6px !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            position: relative;
        }
        .site-icon-preview:after {
            --after-size: 150%;
            aspect-ratio: 1/1;
            content: "";
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            width: var(--after-size);
            transform: translate(calc(var(--after-size) * -.125), calc(var(--after-size) * -.125));
            filter: blur(8px);
            opacity: .4;
            background-image: var(--site-icon-url);
            background-size: cover;
            background-position: center;
            pointer-events: none !important;
            z-index: 0;
        }
        .site-icon-preview.hidden {
            display: none;
        }
        .site-icon-preview .direction-wrap {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            direction: ltr;
            height: 100%;
            width: 100%;
        }
        .site-icon-preview.settings .direction-wrap {
            gap: 14px;
        }
        .site-icon-preview .app-icon-preview {
            aspect-ratio: 1/1;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
            width: 56px;
            height: 56px;
            z-index: 1;
            object-fit: cover;
            background: #ffffff;
            border: 1px solid #f0f0f1;
        }
        .site-icon-preview-browser {
            display: flex;
            padding: 6px 8px 0 12px;
            align-items: center;
            gap: 12px;
            flex: 1;
            height: 100%;
            z-index: 1;
            border-top-left-radius: 10px;
            border-top: 1px solid var(--site-icon-preview-browser-border);
            border-left: 1px solid var(--site-icon-preview-browser-border);
            background: linear-gradient(180deg, var(--site-icon-preview-browser-top) 0%, var(--site-icon-preview-browser-bottom) 100%);
            box-shadow: 0 10px 22px 0 var(--site-icon-shadow-2);
        }
        .site-icon-preview .browser-buttons {
            width: 48px;
            height: 20px;
            fill: #8c8f94;
            flex-shrink: 0;
            display: block;
        }
        .site-icon-preview-tab {
            padding: 6px 10px;
            align-items: center;
            gap: 8px;
            flex: 1;
            border-radius: 6px 6px 0 0;
            background-color: var(--site-icon-address-bar-background);
            box-shadow: 0 1px 3px 0 var(--site-icon-shadow-1);
            display: flex;
            height: 40px;
            overflow: hidden;
        }
        .site-icon-preview-browser .browser-icon-preview {
            width: 22px;
            height: 22px;
            box-shadow: 0 0 4px 0 var(--site-icon-shadow-1);
            object-fit: cover;
            border-radius: 2px;
            flex-shrink: 0;
        }
        .site-icon-preview-tab > svg.close-button {
            width: 14px;
            height: 14px;
            fill: var(--site-icon-address-bar-close);
            flex-shrink: 0;
        }
        .site-icon-preview-site-title {
            color: var(--site-icon-address-bar-text);
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            font-weight: 600;
            font-size: 14px;
            flex: 1;
        }
    </style>
@endpush

@section('content')
    <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.settings.title') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                                    <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.settings.title') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active d-flex align-items-center gap-2" data-bs-toggle="tab" href="#general-pane" role="tab">
                <span><i class="ti ti-settings fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.general') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#contact-pane" role="tab">
                <span><i class="ti ti-phone fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.contact') }}</span>
            </a>
        </li>
        @if(auth()->user()?->isSuperAdmin())
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#multilingual-pane" role="tab">
                <span><i class="ti ti-world fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.multilingual') }}</span>
            </a>
        </li>
        @endif
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#embed-pane" role="tab">
                <span><i class="ti ti-code fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.embed') }}</span>
            </a>
        </li>
    </ul>

    <!-- Form -->
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" id="settingsForm">
        @csrf
        <div class="tab-content" id="settingTabsContent">
            
            <!-- Cài đặt website Pane -->
            <div class="tab-pane fade show active" id="general-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.general.title') }}</h5>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="shop_name">{{ __('admin.settings.general.shop_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-dark" id="shop_name" name="shop_name" 
                                    value="{{ old('shop_name', $settings->get('shop_name')) }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="logo">{{ __('admin.settings.general.logo') }}</label>
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif,image/x-icon" data-media-folder="settings" data-media-selected-field="logo_url">
                                <div class="form-text text-dark">{{ __('admin.settings.general.logo_help') }}</div>
                                <div class="mt-3 {{ $settings->get('logo_url') ? '' : 'd-none' }}" id="logo-preview-wrap">
                                    <p class="small text-dark mb-1">{{ __('admin.settings.general.logo_current') }}</p>
                                    <img id="logo-preview" src="{{ $settings->get('logo_url') ?: '' }}" alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="favicon">{{ __('admin.settings.general.favicon') }}</label>
                                <input type="file" class="form-control mb-2 position-relative" style="z-index: 10;" id="favicon" name="favicon" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif,image/x-icon" data-media-folder="settings" data-media-selected-field="favicon_url">
                                <div class="form-text text-dark mb-3">{{ __('admin.settings.general.favicon_help') }}</div>
                                
                                @php
                                    $faviconUrl = $settings->get('favicon_url') ?: asset('favicon.ico');
                                    $siteTitle = $settings->get('shop_name') ?: 'Website';
                                @endphp
                                <div id="site-icon-preview" class="site-icon-preview settings has-site-icon" style="--site-icon-url: url('{{ $faviconUrl }}');">
                                    <div class="direction-wrap">
                                        <img id="app-icon-preview" src="{{ $faviconUrl }}" class="app-icon-preview" alt="Xem trước biểu tượng ứng dụng">
                                        <div class="site-icon-preview-browser">
                                            <svg role="img" aria-hidden="true" viewBox="0 0 54 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="browser-buttons"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 20a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm18 0a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm24-6a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z"></path></svg>
                                            <div class="site-icon-preview-tab">
                                                <img id="browser-icon-preview" src="{{ $faviconUrl }}" class="browser-icon-preview" alt="Xem trước biểu tượng trình duyệt">
                                                <div class="site-icon-preview-site-title" id="site-icon-preview-site-title" aria-hidden="true">{{ $siteTitle }}</div>
                                                <svg role="img" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg" class="close-button">
                                                    <path d="M12 13.0607L15.7123 16.773L16.773 15.7123L13.0607 12L16.773 8.28772L15.7123 7.22706L12 10.9394L8.28771 7.22705L7.22705 8.28771L10.9394 12L7.22706 15.7123L8.28772 16.773L12 13.0607Z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.seo.title') }}</h5>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="seo_title">{{ __('admin.settings.seo.meta_title') }}</label>
                                <input type="text" class="form-control text-dark" id="seo_title" name="seo[title]" 
                                    value="{{ old('seo.title', data_get($settings->get('seo'), 'title')) }}" placeholder="{{ __('admin.settings.seo.meta_title_placeholder') }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="seo_description">{{ __('admin.settings.seo.meta_desc') }}</label>
                                <textarea class="form-control text-dark" id="seo_description" name="seo[description]" rows="4" 
                                    placeholder="{{ __('admin.settings.seo.meta_desc_placeholder') }}">{{ old('seo.description', data_get($settings->get('seo'), 'description')) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(auth()->user()?->isSuperAdmin())
                @php
                    $multilingual = old('multilingual', $multilingualSettings);
                    $multilingualEnabled = (bool) data_get($multilingual, 'enabled', true);
                    $multilingualMode = data_get($multilingual, 'mode', 'manual');
                    $gtranslateTargets = collect(data_get($multilingual, 'gtranslate.target_locales', []));
                    $sourceLanguage = $contentLanguages->firstWhere('is_default', true) ?? $contentLanguages->first();
                @endphp
                <div class="tab-pane fade" id="multilingual-pane" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                                <div>
                                    <h5 class="card-title fw-semibold text-dark mb-1">{{ __('admin.settings.multilingual.title') }}</h5>
                                    <p class="text-muted mb-0">{{ __('admin.settings.multilingual.description') }}</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="multilingual[enabled]" value="0">
                                    <input class="form-check-input" type="checkbox" name="multilingual[enabled]" value="1" id="multilingual_enabled" @checked($multilingualEnabled)>
                                    <label class="form-check-label fw-semibold" for="multilingual_enabled">{{ __('admin.settings.multilingual.enabled') }}</label>
                                </div>
                            </div>

                            <div class="row g-3 mb-4" id="multilingual-mode-options">
                                <div class="col-lg-6">
                                    <label class="border rounded-3 p-3 h-100 d-block cursor-pointer" for="multilingual_mode_manual">
                                        <span class="d-flex align-items-start gap-3">
                                            <input class="form-check-input mt-1 js-multilingual-mode" type="radio" name="multilingual[mode]" value="manual" id="multilingual_mode_manual" @checked($multilingualMode === 'manual')>
                                            <span>
                                                <strong class="d-block text-dark mb-1">{{ __('admin.settings.multilingual.manual_title') }}</strong>
                                                <span class="text-muted">{{ __('admin.settings.multilingual.manual_description') }}</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-lg-6">
                                    <label class="border rounded-3 p-3 h-100 d-block cursor-pointer" for="multilingual_mode_gtranslate">
                                        <span class="d-flex align-items-start gap-3">
                                            <input class="form-check-input mt-1 js-multilingual-mode" type="radio" name="multilingual[mode]" value="gtranslate" id="multilingual_mode_gtranslate" @checked($multilingualMode === 'gtranslate')>
                                            <span>
                                                <strong class="d-block text-dark mb-1">{{ __('admin.settings.multilingual.gtranslate_title') }}</strong>
                                                <span class="text-muted">{{ __('admin.settings.multilingual.gtranslate_description') }}</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div id="manual-settings" @class(['d-none' => $multilingualMode !== 'manual'])>
                                <div class="alert alert-info mb-0">
                                    {{ __('admin.settings.multilingual.manual_help') }}
                                    <a href="{{ route('admin.languages.index') }}" class="alert-link">{{ __('admin.settings.multilingual.manage_languages') }}</a>
                                </div>
                            </div>

                            <div id="gtranslate-settings" @class(['d-none' => $multilingualMode !== 'gtranslate'])>
                                <div class="border rounded-3 p-4">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1">{{ __('admin.settings.multilingual.gtranslate_options') }}</h6>
                                            <p class="text-muted mb-0">{{ __('admin.settings.multilingual.source_language') }}: <strong>{{ $sourceLanguage?->native_name ?? strtoupper(config('multilingual.default_locale', 'vi')) }}</strong></p>
                                        </div>
                                        <a href="https://gtranslate.io/website-translator-widget" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-external-link me-1"></i>{{ __('admin.settings.multilingual.widget_docs') }}
                                        </a>
                                    </div>

                                    <label class="form-label">{{ __('admin.settings.multilingual.target_languages') }}</label>
                                    <div class="row g-2 mb-3">
                                        @forelse($contentLanguages->where('code', '!=', $sourceLanguage?->code) as $language)
                                            <div class="col-md-4">
                                                <div class="form-check border rounded px-3 py-2">
                                                    <input class="form-check-input" type="checkbox" name="multilingual[gtranslate][target_locales][]" value="{{ $language->code }}" id="gtranslate_locale_{{ $language->code }}" @checked($gtranslateTargets->contains($language->code))>
                                                    <label class="form-check-label" for="gtranslate_locale_{{ $language->code }}">{{ $language->native_name }} ({{ strtoupper($language->code) }})</label>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-12">
                                                <div class="alert alert-danger mb-0">{{ __('admin.settings.multilingual.no_target_languages') }}</div>
                                            </div>
                                        @endforelse
                                    </div>
                                    @error('multilingual.gtranslate.target_locales')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="gtranslate_widget_look">{{ __('admin.settings.multilingual.widget_look') }}</label>
                                            <select class="form-select" id="gtranslate_widget_look" name="multilingual[gtranslate][widget_look]">
                                                <option value="float" @selected(data_get($multilingual, 'gtranslate.widget_look', 'float') === 'float')>{{ __('admin.settings.multilingual.look_float') }}</option>
                                                <option value="dropdown_with_flags" @selected(data_get($multilingual, 'gtranslate.widget_look') === 'dropdown_with_flags')>{{ __('admin.settings.multilingual.look_dropdown') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="gtranslate_position">{{ __('admin.settings.multilingual.position') }}</label>
                                            <select class="form-select" id="gtranslate_position" name="multilingual[gtranslate][position]">
                                                @foreach(['bottom_right', 'bottom_left', 'top_right', 'top_left', 'inline'] as $position)
                                                    <option value="{{ $position }}" @selected(data_get($multilingual, 'gtranslate.position', 'bottom_right') === $position)>{{ __('admin.settings.multilingual.position_'.$position) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column gap-2">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="multilingual[gtranslate][detect_browser_language]" value="0">
                                            <input class="form-check-input" type="checkbox" name="multilingual[gtranslate][detect_browser_language]" value="1" id="gtranslate_detect_browser" @checked(data_get($multilingual, 'gtranslate.detect_browser_language', false))>
                                            <label class="form-check-label" for="gtranslate_detect_browser">{{ __('admin.settings.multilingual.detect_browser') }}</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="multilingual[gtranslate][native_language_names]" value="0">
                                            <input class="form-check-input" type="checkbox" name="multilingual[gtranslate][native_language_names]" value="1" id="gtranslate_native_names" @checked(data_get($multilingual, 'gtranslate.native_language_names', true))>
                                            <label class="form-check-label" for="gtranslate_native_names">{{ __('admin.settings.multilingual.native_names') }}</label>
                                        </div>
                                    </div>

                                    <p class="form-text mt-3 mb-0">{{ __('admin.settings.multilingual.free_limit_note') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Nút liên hệ Pane -->
            <div class="tab-pane fade" id="contact-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.contact.title') }}</h5>
                        @php
                            $contact = $settings->get('contact') ?? [];
                        @endphp
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="contact_phone">{{ __('admin.settings.contact.phone') }}</label>
                                <input type="text" class="form-control text-dark" id="contact_phone" name="contact[phone]" 
                                    value="{{ old('contact.phone', $contact['phone'] ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="contact_email">{{ __('admin.settings.contact.email') }}</label>
                                <input type="email" class="form-control text-dark" id="contact_email" name="contact[email]" 
                                    value="{{ old('contact.email', $contact['email'] ?? '') }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="contact_address">{{ __('admin.settings.contact.address') }}</label>
                                <textarea class="form-control text-dark" id="contact_address" name="contact[address]" rows="2">{{ old('contact.address', $contact['address'] ?? '') }}</textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="contact_google_map_url">Google Maps Link / Mã nhúng vị trí bản đồ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-map-pin fs-5 text-danger"></i></span>
                                    <input type="text" class="form-control text-dark" id="contact_google_map_url" name="contact[google_map_url]" 
                                        placeholder="https://maps.google.com/?q=... hoặc <iframe src=...>" value="{{ old('contact.google_map_url', $contact['google_map_url'] ?? '') }}">
                                </div>
                                <div class="form-text text-muted">Nhập đường dẫn Google Maps (Share link) hoặc mã nhúng iFrame để hiển thị vị trí bản đồ trên trang liên hệ.</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.social.title') }}</h5>
                        @php
                            $social = $settings->get('social_links') ?? [];
                        @endphp
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="social_facebook">Facebook URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-brand-facebook fs-5 text-primary"></i></span>
                                    <input type="url" class="form-control text-dark" id="social_facebook" name="social_links[facebook]" 
                                        placeholder="https://facebook.com/yourpage" value="{{ old('social_links.facebook', $social['facebook'] ?? '') }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="social_youtube">YouTube Channel URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-brand-youtube fs-5 text-danger"></i></span>
                                    <input type="url" class="form-control text-dark" id="social_youtube" name="social_links[youtube]" 
                                        placeholder="https://youtube.com/c/yourchannel" value="{{ old('social_links.youtube', $social['youtube'] ?? '') }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="social_instagram">Instagram URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-brand-instagram fs-5 text-info"></i></span>
                                    <input type="url" class="form-control text-dark" id="social_instagram" name="social_links[instagram]" 
                                        placeholder="https://instagram.com/yourprofile" value="{{ old('social_links.instagram', $social['instagram'] ?? '') }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="social_tiktok">TikTok URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-brand-tiktok fs-5 text-dark"></i></span>
                                    <input type="url" class="form-control text-dark" id="social_tiktok" name="social_links[tiktok]" 
                                        placeholder="https://tiktok.com/@yourprofile" value="{{ old('social_links.tiktok', $social['tiktok'] ?? '') }}">
                                </div>
                            </div>
                        </div>

                        <!-- Custom Social / Web Links Section -->
                        <div class="mt-4 pt-2 border-top">
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                                <div>
                                    <h6 class="fw-semibold text-dark mb-1">Đường dẫn mạng xã hội / Website khác</h6>
                                    <p class="small text-muted mb-0 mt-2">Thêm không giới hạn các liên kết tùy chỉnh với icon tự chọn (Zalo, Shopee, Lazada, Telegram, WhatsApp, Website...)</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="add-custom-social-btn">
                                    <i class="ti ti-plus me-1"></i> Thêm đường dẫn mới
                                </button>
                            </div>
                            <div id="custom-social-links-container" class="d-flex flex-column gap-3 mt-2">
                                @php
                                    $customLinks = $social['custom'] ?? [];
                                    if (!is_array($customLinks)) $customLinks = [];
                                @endphp
                                @forelse($customLinks as $index => $customLink)
                                    @php
                                        $selectedIcon = $customLink['icon'] ?? 'ti ti-world';
                                    @endphp
                                    <div class="row align-items-center custom-social-row" data-index="{{ $index }}">
                                        <!-- Icon Select -->
                                        <div class="col-md-3 mb-2 mb-md-0">
                                            <select class="form-select text-dark custom-icon-select" name="social_links[custom][{{ $index }}][icon]">
                                                <option value="ti ti-world" data-icon="ti ti-world" @selected($selectedIcon === 'ti ti-world')>Website (WWW)</option>
                                                <option value="ti ti-message-dots" data-icon="ti ti-message-dots" @selected($selectedIcon === 'ti ti-message-dots' || $selectedIcon === 'simple-icons:zalo')>Zalo / Chat</option>
                                                <option value="ti ti-brand-shopee" data-icon="ti ti-brand-shopee" @selected($selectedIcon === 'ti ti-brand-shopee' || $selectedIcon === 'simple-icons:shopee')>Shopee</option>
                                                <option value="ti ti-shopping-cart" data-icon="ti ti-shopping-cart" @selected($selectedIcon === 'ti ti-shopping-cart' || $selectedIcon === 'simple-icons:lazada')>Lazada / TMĐT</option>
                                                <option value="ti ti-brand-facebook" data-icon="ti ti-brand-facebook" @selected($selectedIcon === 'ti ti-brand-facebook')>Facebook</option>
                                                <option value="ti ti-brand-youtube" data-icon="ti ti-brand-youtube" @selected($selectedIcon === 'ti ti-brand-youtube')>YouTube</option>
                                                <option value="ti ti-brand-instagram" data-icon="ti ti-brand-instagram" @selected($selectedIcon === 'ti ti-brand-instagram')>Instagram</option>
                                                <option value="ti ti-brand-tiktok" data-icon="ti ti-brand-tiktok" @selected($selectedIcon === 'ti ti-brand-tiktok')>TikTok</option>
                                                <option value="ti ti-brand-telegram" data-icon="ti ti-brand-telegram" @selected($selectedIcon === 'ti ti-brand-telegram')>Telegram</option>
                                                <option value="ti ti-brand-whatsapp" data-icon="ti ti-brand-whatsapp" @selected($selectedIcon === 'ti ti-brand-whatsapp')>WhatsApp</option>
                                                <option value="ti ti-brand-messenger" data-icon="ti ti-brand-messenger" @selected($selectedIcon === 'ti ti-brand-messenger')>Messenger</option>
                                                <option value="ti ti-brand-twitter" data-icon="ti ti-brand-twitter" @selected($selectedIcon === 'ti ti-brand-twitter')>X / Twitter</option>
                                                <option value="ti ti-brand-linkedin" data-icon="ti ti-brand-linkedin" @selected($selectedIcon === 'ti ti-brand-linkedin')>LinkedIn</option>
                                                <option value="ti ti-brand-pinterest" data-icon="ti ti-brand-pinterest" @selected($selectedIcon === 'ti ti-brand-pinterest')>Pinterest</option>
                                                <option value="ti ti-brand-discord" data-icon="ti ti-brand-discord" @selected($selectedIcon === 'ti ti-brand-discord')>Discord</option>
                                                <option value="ti ti-phone" data-icon="ti ti-phone" @selected($selectedIcon === 'ti ti-phone')>Hotline / SĐT</option>
                                                <option value="ti ti-mail" data-icon="ti ti-mail" @selected($selectedIcon === 'ti ti-mail')>Email</option>
                                                <option value="ti ti-map-pin" data-icon="ti ti-map-pin" @selected($selectedIcon === 'ti ti-map-pin')>Địa chỉ / Bản đồ</option>
                                                <option value="ti ti-link" data-icon="ti ti-link" @selected($selectedIcon === 'ti ti-link')>Liên kết khác</option>
                                            </select>
                                        </div>
                                        <!-- Title -->
                                        <div class="col-md-3 mb-2 mb-md-0">
                                            <input type="text" class="form-control text-dark" name="social_links[custom][{{ $index }}][title]" 
                                                placeholder="Tên hiển thị (Zalo, Shopee...)" value="{{ old("social_links.custom.$index.title", $customLink['title'] ?? '') }}">
                                        </div>
                                        <!-- URL -->
                                        <div class="col-md-5 mb-2 mb-md-0">
                                            <input type="url" class="form-control text-dark" name="social_links[custom][{{ $index }}][url]" 
                                                placeholder="https://..." value="{{ old("social_links.custom.$index.url", $customLink['url'] ?? '') }}">
                                        </div>
                                        <!-- Remove Button -->
                                        <div class="col-md-1 text-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-custom-social-btn" title="Xóa">
                                                <i class="ti ti-trash fs-5"></i>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="empty-custom-links-msg text-center text-muted p-4 rounded-3 border-dashed bg-light-subtle">
                                        <i class="ti ti-link-plus fs-7 d-block mb-1 text-primary"></i>
                                        <span class="small fw-semibold">Chưa có đường dẫn tùy chỉnh nào.</span>
                                        <div class="small text-muted mt-1">Nhấn nút <strong>"Thêm đường dẫn mới"</strong> ở trên để tạo thêm.</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mã nhúng Pane -->
            <div class="tab-pane fade" id="embed-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.embed.title') }}</h5>
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label class="form-label fw-semibold text-dark" for="embed_header">{{ __('admin.settings.embed.header') }}</label>
                                <textarea class="form-control text-dark font-monospace" id="embed_header" name="embed_header" rows="8" 
                                    placeholder="{{ __('admin.settings.embed.placeholder') }}">{{ old('embed_header', $settings->get('embed_header')) }}</textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="embed_footer">{{ __('admin.settings.embed.footer') }}</label>
                                <textarea class="form-control text-dark font-monospace" id="embed_footer" name="embed_footer" rows="8" 
                                    placeholder="{{ __('admin.settings.embed.placeholder') }}">{{ old('embed_footer', $settings->get('embed_footer')) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sticky Save Button -->
        <div class="mt-4 pt-2 mb-5">
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                <i class="ti ti-device-floppy me-1 fs-5"></i> {{ __('admin.settings.save_settings') }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('settingsForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: '{{ __('admin.settings.saving') }}',
                        text: '{{ __('admin.settings.please_wait') }}',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData(form);

                    fetch(form.action, {
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
                                title: '{{ __('admin.success') }}',
                                text: data.message || '{{ __('admin.settings.updated') }}',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = data.redirect_url || window.location.href;
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __('admin.error') }}',
                                text: data.message || '{{ __('admin.settings.save_failed') }}'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        let errMsg = '{{ __('admin.failed_to_connect') }}';
                        if (error.errors) {
                            errMsg = Object.values(error.errors).flat().join('\n');
                        } else if (error.message) {
                            errMsg = error.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __('admin.error') }}',
                            text: errMsg
                        });
                    });
                });
            }

            const updateMultilingualMode = function () {
                const mode = document.querySelector('input[name="multilingual[mode]"]:checked')?.value || 'manual';
                document.getElementById('manual-settings')?.classList.toggle('d-none', mode !== 'manual');
                document.getElementById('gtranslate-settings')?.classList.toggle('d-none', mode !== 'gtranslate');
            };
            document.querySelectorAll('.js-multilingual-mode').forEach(function (input) {
                input.addEventListener('change', updateMultilingualMode);
            });
            updateMultilingualMode();

            // Site Icon Live Preview Logic
            const faviconInput = document.getElementById('favicon');
            if (faviconInput) {
                faviconInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function (evt) {
                            const appPreview = document.getElementById('app-icon-preview');
                            const browserPreview = document.getElementById('browser-icon-preview');
                            const previewWrap = document.getElementById('site-icon-preview');
                            if (appPreview) appPreview.src = evt.target.result;
                            if (browserPreview) browserPreview.src = evt.target.result;
                            if (previewWrap) previewWrap.style.setProperty('--site-icon-url', `url('${evt.target.result}')`);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            document.addEventListener('media:selected', function (event) {
                const url = event.detail.url;
                if (event.target.id === 'logo') {
                    const preview = document.getElementById('logo-preview');
                    if (preview) preview.src = url;
                    document.getElementById('logo-preview-wrap')?.classList.remove('d-none');
                }
                if (event.target.id === 'favicon') {
                    const appPreview = document.getElementById('app-icon-preview');
                    const browserPreview = document.getElementById('browser-icon-preview');
                    const previewWrap = document.getElementById('site-icon-preview');
                    if (appPreview) appPreview.src = url;
                    if (browserPreview) browserPreview.src = url;
                    if (previewWrap) previewWrap.style.setProperty('--site-icon-url', `url('${url}')`);
                }
            });

            const shopNameInput = document.getElementById('shop_name');
            const siteTitlePreview = document.getElementById('site-icon-preview-site-title');
            if (shopNameInput && siteTitlePreview) {
                shopNameInput.addEventListener('input', function () {
                    siteTitlePreview.textContent = this.value.trim() || 'Website';
                });
            }

            // Custom Select2 Icon Formatting Function
            function formatSelectIcon(state) {
                if (!state || !state.id) return state ? state.text : '';
                const elem = state.element;
                const iconClass = elem ? (elem.dataset.icon || state.id) : state.id;
                if (typeof window.jQuery !== 'undefined') {
                    return window.jQuery(`<span class="d-inline-flex align-items-center gap-2 me-1"><i class="${iconClass} fs-5 text-primary"></i> <span>${state.text}</span></span>`);
                }
                return state.text;
            }

            function initCustomIconSelects(scope) {
                if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') return;
                const $ = window.jQuery;
                const container = scope || document;
                const selects = container.querySelectorAll ? container.querySelectorAll('.custom-icon-select') : [];
                selects.forEach(select => {
                    const $select = $(select);
                    if ($select.hasClass('select2-hidden-accessible')) return;
                    $select.select2({
                        templateResult: formatSelectIcon,
                        templateSelection: formatSelectIcon,
                        minimumResultsForSearch: Infinity,
                        width: '100%'
                    });
                });
            }

            initCustomIconSelects();

            // Auto-fill Title based on Icon selection
            const iconTitleMap = {
                'ti ti-world': 'Website',
                'ti ti-message-dots': 'Zalo',
                'ti ti-brand-shopee': 'Shopee',
                'ti ti-shopping-cart': 'Lazada',
                'ti ti-brand-facebook': 'Facebook',
                'ti ti-brand-youtube': 'YouTube',
                'ti ti-brand-instagram': 'Instagram',
                'ti ti-brand-tiktok': 'TikTok',
                'ti ti-brand-telegram': 'Telegram',
                'ti ti-brand-whatsapp': 'WhatsApp',
                'ti ti-brand-messenger': 'Messenger',
                'ti ti-brand-twitter': 'X (Twitter)',
                'ti ti-brand-linkedin': 'LinkedIn',
                'ti ti-brand-pinterest': 'Pinterest',
                'ti ti-brand-discord': 'Discord',
                'ti ti-phone': 'Hotline',
                'ti ti-mail': 'Email',
                'ti ti-map-pin': 'Địa chỉ',
                'ti ti-link': 'Liên kết'
            };

            function autoFillRowTitle(row, iconValue) {
                if (!row) return;
                const titleInput = row.querySelector('input[name*="[title]"]');
                if (!titleInput) return;
                const defaultTitle = iconTitleMap[iconValue] || '';
                const currentVal = titleInput.value.trim();
                const isDefaultVal = Object.values(iconTitleMap).includes(currentVal) || currentVal === '';
                if (isDefaultVal && defaultTitle) {
                    titleInput.value = defaultTitle;
                }
            }

            if (typeof window.jQuery !== 'undefined') {
                window.jQuery(document).on('change', '.custom-icon-select', function () {
                    const row = this.closest('.custom-social-row');
                    autoFillRowTitle(row, this.value);
                });
            }

            // Dynamic Custom Social Links Repeater Logic
            const customSocialContainer = document.getElementById('custom-social-links-container');
            const addCustomSocialBtn = document.getElementById('add-custom-social-btn');

            if (addCustomSocialBtn && customSocialContainer) {
                addCustomSocialBtn.addEventListener('click', function (e) {
                    if (e) e.preventDefault();
                    const emptyMsg = customSocialContainer.querySelector('.empty-custom-links-msg');
                    if (emptyMsg) emptyMsg.remove();

                    const index = customSocialContainer.querySelectorAll('.custom-social-row').length;
                    const newRow = document.createElement('div');
                    newRow.className = 'row align-items-center custom-social-row mb-2';
                    newRow.setAttribute('data-index', index);
                    newRow.innerHTML = `
                        <div class="col-md-3 mb-2 mb-md-0">
                            <select class="form-select text-dark custom-icon-select" name="social_links[custom][${index}][icon]">
                                <option value="ti ti-world" data-icon="ti ti-world" selected>Website (WWW)</option>
                                <option value="ti ti-message-dots" data-icon="ti ti-message-dots">Zalo / Chat</option>
                                <option value="ti ti-brand-shopee" data-icon="ti ti-brand-shopee">Shopee</option>
                                <option value="ti ti-shopping-cart" data-icon="ti ti-shopping-cart">Lazada / TMĐT</option>
                                <option value="ti ti-brand-facebook" data-icon="ti ti-brand-facebook">Facebook</option>
                                <option value="ti ti-brand-youtube" data-icon="ti ti-brand-youtube">YouTube</option>
                                <option value="ti ti-brand-instagram" data-icon="ti ti-brand-instagram">Instagram</option>
                                <option value="ti ti-brand-tiktok" data-icon="ti ti-brand-tiktok">TikTok</option>
                                <option value="ti ti-brand-telegram" data-icon="ti ti-brand-telegram">Telegram</option>
                                <option value="ti ti-brand-whatsapp" data-icon="ti ti-brand-whatsapp">WhatsApp</option>
                                <option value="ti ti-brand-messenger" data-icon="ti ti-brand-messenger">Messenger</option>
                                <option value="ti ti-brand-twitter" data-icon="ti ti-brand-twitter">X / Twitter</option>
                                <option value="ti ti-brand-linkedin" data-icon="ti ti-brand-linkedin">LinkedIn</option>
                                <option value="ti ti-brand-pinterest" data-icon="ti ti-brand-pinterest">Pinterest</option>
                                <option value="ti ti-brand-discord" data-icon="ti ti-brand-discord">Discord</option>
                                <option value="ti ti-phone" data-icon="ti ti-phone">Hotline / SĐT</option>
                                <option value="ti ti-mail" data-icon="ti ti-mail">Email</option>
                                <option value="ti ti-map-pin" data-icon="ti ti-map-pin">Địa chỉ / Bản đồ</option>
                                <option value="ti ti-link" data-icon="ti ti-link">Liên kết khác</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <input type="text" class="form-control text-dark" name="social_links[custom][${index}][title]" 
                                placeholder="Tên hiển thị (Zalo, Shopee...)" value="Website">
                        </div>
                        <div class="col-md-5 mb-2 mb-md-0">
                            <input type="url" class="form-control text-dark" name="social_links[custom][${index}][url]" 
                                placeholder="https://...">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-custom-social-btn" title="Xóa">
                                <i class="ti ti-trash fs-5"></i>
                            </button>
                        </div>
                    `;
                    customSocialContainer.appendChild(newRow);
                    initCustomIconSelects(newRow);
                });

                // Live preview icon update on change
                customSocialContainer.addEventListener('change', function (e) {
                    if (e.target.classList.contains('custom-icon-select')) {
                        const row = e.target.closest('.custom-social-row');
                        const preview = row ? row.querySelector('.custom-icon-preview') : null;
                        if (preview) {
                            preview.innerHTML = `<i class="${e.target.value} fs-5 text-primary"></i>`;
                        }
                    }
                });

                customSocialContainer.addEventListener('click', function (e) {
                    const removeBtn = e.target.closest('.remove-custom-social-btn');
                    if (removeBtn) {
                        const row = removeBtn.closest('.custom-social-row');
                        if (row) row.remove();
                        if (customSocialContainer.querySelectorAll('.custom-social-row').length === 0) {
                            customSocialContainer.innerHTML = `
                                <div class="empty-custom-links-msg text-center text-muted p-4 rounded-3 border-dashed bg-light-subtle">
                                    <i class="ti ti-link-plus fs-7 d-block mb-1 text-primary"></i>
                                    <span class="small fw-semibold">Chưa có đường dẫn tùy chỉnh nào.</span>
                                    <div class="small text-muted mt-1">Nhấn nút <strong>"Thêm đường dẫn mới"</strong> ở trên để tạo thêm.</div>
                                </div>
                            `;
                        }
                    }
                });
            }
        });
    </script>
@endpush
