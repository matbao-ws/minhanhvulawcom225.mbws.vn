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

        /* Analytics setup is a guided workflow, not a dense list of fields. */
        .analytics-settings { max-width: 1040px; }
        .analytics-settings__hero { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 24px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 14px; background: linear-gradient(135deg, #fdf3f4 0%, #ffffff 56%); }
        .analytics-settings__hero-icon { display: inline-flex; width: 46px; height: 46px; flex: 0 0 46px; align-items: center; justify-content: center; border-radius: 12px; color: #c01e25; background: #ffe8ea; }
        .analytics-settings__title { margin: 0 0 4px; color: #2a3547; font-size: 18px; font-weight: 800; }
        .analytics-settings__help { margin: 0; color: #5a6a85; font-size: 13px; }
        .analytics-settings__section { padding: 18px; border: 1px solid #e5ebf3; border-radius: 14px; background: #fff; }
        .analytics-settings__section + .analytics-settings__section { margin-top: 16px; }
        .analytics-settings__section-title { margin-bottom: 14px; color: #344256; font-size: 14px; font-weight: 800; }
        .analytics-settings__steps { display: grid; gap: 10px; padding: 0; margin: 0; list-style: none; counter-reset: analytics-step; }
        .analytics-settings__steps > li { position: relative; min-height: 40px; padding: 10px 12px 10px 48px; border-radius: 10px; color: #526174; background: #f8fafc; font-size: 13px; }
        .analytics-settings__steps > li::before { position: absolute; top: 9px; left: 12px; display: inline-flex; width: 25px; height: 25px; align-items: center; justify-content: center; border-radius: 50%; color: #c01e25; background: #ffe8ea; content: counter(analytics-step); counter-increment: analytics-step; font-size: 12px; font-weight: 800; }
        .analytics-settings__service-account { margin-top: 10px; }
        .analytics-settings__field { height: 100%; padding: 14px; border: 1px solid #e8edf5; border-radius: 12px; background: #fbfcff; }
        .analytics-settings__field .form-label { margin-bottom: 7px; }
        .analytics-settings__field .form-text { margin-top: 7px; color: #627391; }
        .analytics-settings__actions { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: center; padding-top: 16px; margin-top: 18px; border-top: 1px dashed #dce4ec; }
        .analytics-settings__check { display: flex; align-items: center; gap: 10px; }
        .analytics-settings__check [data-ga-check-result] { min-width: 180px; color: #526174; }
        [data-bs-theme="dark"] .analytics-settings__hero, [data-bs-theme="dark"] .analytics-settings__section { border-color: #3b4c64; background: #203046; }
        [data-bs-theme="dark"] .analytics-settings__hero { background: linear-gradient(135deg, rgba(192, 30, 37, .22), #203046 56%); }
        [data-bs-theme="dark"] .analytics-settings__title, [data-bs-theme="dark"] .analytics-settings__section-title { color: #edf2f8; }
        [data-bs-theme="dark"] .analytics-settings__help, [data-bs-theme="dark"] .analytics-settings__steps > li, [data-bs-theme="dark"] .analytics-settings__field .form-text { color: #c4cfde; }
        [data-bs-theme="dark"] .analytics-settings__steps > li, [data-bs-theme="dark"] .analytics-settings__field { border-color: #40536d; background: #293a51; }
        [data-bs-theme="dark"] .analytics-settings__actions { border-color: #40536d; }
    </style>
@endpush

@section('content')
    <x-admin.page-header :title="__('admin.settings.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.settings.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <!-- Nav tabs -->
    <ul data-tour="settings.tabs" class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'general' ? 'active' : '' }} d-flex align-items-center gap-2" data-bs-toggle="tab" data-settings-tab="general" href="#general-pane" role="tab" aria-selected="{{ $activeTab === 'general' ? 'true' : 'false' }}">
                <span><i class="ti ti-settings fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.general') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a data-tour="settings.tab-contact" class="nav-link {{ $activeTab === 'contact' ? 'active' : '' }} d-flex align-items-center gap-2" data-bs-toggle="tab" data-settings-tab="contact" href="#contact-pane" role="tab" aria-selected="{{ $activeTab === 'contact' ? 'true' : 'false' }}">
                <span><i class="ti ti-phone fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.contact') }}</span>
            </a>
        </li>
        @if(auth()->user()?->isSuperAdmin())
        <li class="nav-item">
            <a data-tour="settings.tab-multilingual" class="nav-link {{ $activeTab === 'multilingual' ? 'active' : '' }} d-flex align-items-center gap-2" data-bs-toggle="tab" data-settings-tab="multilingual" href="#multilingual-pane" role="tab" aria-selected="{{ $activeTab === 'multilingual' ? 'true' : 'false' }}">
                <span><i class="ti ti-world fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.multilingual') }}</span>
            </a>
        </li>
        @endif
        <li class="nav-item">
            <a data-tour="settings.tab-embed" class="nav-link {{ $activeTab === 'embed' ? 'active' : '' }} d-flex align-items-center gap-2" data-bs-toggle="tab" data-settings-tab="embed" href="#embed-pane" role="tab" aria-selected="{{ $activeTab === 'embed' ? 'true' : 'false' }}">
                <span><i class="ti ti-code fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.embed') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a data-tour="settings.tab-analytics" class="nav-link {{ $activeTab === 'analytics' ? 'active' : '' }} d-flex align-items-center gap-2" data-bs-toggle="tab" data-settings-tab="analytics" href="#analytics-pane" role="tab" aria-selected="{{ $activeTab === 'analytics' ? 'true' : 'false' }}">
                <span><i class="ti ti-chart-line fs-4"></i></span>
                <span>{{ __('admin.analytics.tab') }}</span>
            </a>
        </li>
    </ul>

    <!-- Form -->
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" id="settingsForm">
        @csrf
        <input type="hidden" name="active_tab" value="{{ $activeTab }}" data-settings-active-tab>
        <div class="tab-content" id="settingTabsContent">
            
            <!-- Cài đặt website Pane -->
            <div data-tour="settings.general" class="tab-pane fade {{ $activeTab === 'general' ? 'show active' : '' }}" id="general-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div data-site-locale-scope data-i18n-scope>
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.general.title') }}</h5>
                        @include('admin.settings._locale-switch')
                        <div class="row">
                            <div class="col-md-12 mb-3" data-site-locale="{{ $siteDefaultLocale }}">
                                <label class="form-label fw-semibold text-dark" for="shop_name">{{ __('admin.settings.general.shop_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-dark" id="shop_name" name="shop_name"
                                    value="{{ old('shop_name', $settings->get('shop_name')) }}" required>
                            </div>
                            {{-- A brand name: typed by hand, never sent through the translate button. --}}
                            @foreach($siteOtherLanguages->keyBy('code') as $code => $language)
                                <div class="col-md-12 mb-3 d-none" data-site-locale="{{ $code }}">
                                    <label class="form-label fw-semibold text-dark" for="shop_name_{{ $code }}">{{ __('admin.settings.general.shop_name') }} ({{ $language->native_name }})</label>
                                    <input type="text" class="form-control text-dark" id="shop_name_{{ $code }}" name="shop_name_translations[{{ $code }}]"
                                        value="{{ old("shop_name_translations.$code", data_get($settings->get('shop_name_translations'), $code)) }}" placeholder="{{ $settings->get('shop_name') }}">
                                    <div class="form-text">{{ __('admin.settings.localized.shop_name_help') }}</div>
                                </div>
                            @endforeach
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
                                        <img id="app-icon-preview" src="{{ $faviconUrl }}" class="app-icon-preview" alt="{{ __('admin.settings.general.app_icon_preview') }}">
                                        <div class="site-icon-preview-browser">
                                            <svg role="img" aria-hidden="true" viewBox="0 0 54 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="browser-buttons"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 20a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm18 0a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm24-6a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z"></path></svg>
                                            <div class="site-icon-preview-tab">
                                                <img id="browser-icon-preview" src="{{ $faviconUrl }}" class="browser-icon-preview" alt="{{ __('admin.settings.general.browser_icon_preview') }}">
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
                            <div class="col-md-12 mb-3" data-site-locale="{{ $siteDefaultLocale }}">
                                <label class="form-label fw-semibold text-dark" for="seo_title">{{ __('admin.settings.seo.meta_title') }}</label>
                                <input type="text" class="form-control text-dark" id="seo_title" name="seo[title]" data-i18n-locale="{{ $siteDefaultLocale }}" data-i18n-field="seo_title"
                                    value="{{ old('seo.title', data_get($settings->get('seo'), 'title')) }}" placeholder="{{ __('admin.settings.seo.meta_title_placeholder') }}">
                            </div>
                            <div class="col-md-12 mb-3" data-site-locale="{{ $siteDefaultLocale }}">
                                <label class="form-label fw-semibold text-dark" for="seo_description">{{ __('admin.settings.seo.meta_desc') }}</label>
                                <textarea class="form-control text-dark" id="seo_description" name="seo[description]" data-i18n-locale="{{ $siteDefaultLocale }}" data-i18n-field="seo_description" rows="4"
                                    placeholder="{{ __('admin.settings.seo.meta_desc_placeholder') }}">{{ old('seo.description', data_get($settings->get('seo'), 'description')) }}</textarea>
                            </div>
                            @foreach($siteOtherLanguages->keyBy('code') as $code => $language)
                                <div class="col-md-12 mb-3 d-none" data-site-locale="{{ $code }}">
                                    <label class="form-label fw-semibold text-dark" for="seo_title_{{ $code }}">{{ __('admin.settings.seo.meta_title') }} ({{ $language->native_name }})</label>
                                    <input type="text" class="form-control text-dark" id="seo_title_{{ $code }}" name="seo[translations][{{ $code }}][title]" data-i18n-locale="{{ $code }}" data-i18n-field="seo_title"
                                        value="{{ old("seo.translations.$code.title", data_get($settings->get('seo'), "translations.$code.title")) }}" placeholder="{{ data_get($settings->get('seo'), 'title') ?: __('admin.settings.seo.meta_title_placeholder') }}">
                                </div>
                                <div class="col-md-12 mb-3 d-none" data-site-locale="{{ $code }}">
                                    <label class="form-label fw-semibold text-dark" for="seo_description_{{ $code }}">{{ __('admin.settings.seo.meta_desc') }} ({{ $language->native_name }})</label>
                                    <textarea class="form-control text-dark" id="seo_description_{{ $code }}" name="seo[translations][{{ $code }}][description]" data-i18n-locale="{{ $code }}" data-i18n-field="seo_description" rows="4"
                                        placeholder="{{ data_get($settings->get('seo'), 'description') ?: __('admin.settings.seo.meta_desc_placeholder') }}">{{ old("seo.translations.$code.description", data_get($settings->get('seo'), "translations.$code.description")) }}</textarea>
                                </div>
                            @endforeach
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
                    $gtranslateNames = (array) __('admin.settings.multilingual.language_names');
                    $allGTranslateLanguages = collect(\App\Services\MultilingualSettings::allGTranslateLanguages())
                        ->map(fn (string $name, string $code) => $gtranslateNames[$code] ?? $name)
                        ->all();
                @endphp
                <div class="tab-pane fade {{ $activeTab === 'multilingual' ? 'show active' : '' }}" id="multilingual-pane" role="tabpanel">
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

                            <div id="gtranslate-settings" 
                                @class(['d-none' => $multilingualMode !== 'gtranslate'])
                                data-source-locale="{{ $sourceLanguage?->code ?? 'vi' }}"
                                data-locales-map='@json($contentLanguages->pluck("regional", "code"))'>
                                <div class="row g-4">
                                    <div class="col-xl-7 col-lg-6">
                                        <div class="border rounded-3 p-4 h-100">
                                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                                 <div>
                                                     <h6 class="fw-bold text-dark mb-1">{{ __('admin.settings.multilingual.gtranslate_options') }}</h6>
                                                     <p class="text-muted mb-0 small">{{ __('admin.settings.multilingual.free_limit_note') }}</p>
                                                 </div>
                                                 <a href="https://gtranslate.io/website-translator-widget" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                     <i class="ti ti-external-link me-1"></i>{{ __('admin.settings.multilingual.widget_docs') }}
                                                 </a>
                                             </div>

                                             <div class="mb-3">
                                                 <label class="form-label fw-semibold" for="gtranslate_source_locale">
                                                     {{ __('admin.settings.multilingual.source_language') }}
                                                 </label>
                                                 @php
                                                     $selectedSourceLocale = data_get($multilingual, 'gtranslate.source_locale', $sourceLanguage?->code ?? 'vi');
                                                 @endphp
                                                 <select class="form-select text-dark js-gtranslate-input" id="gtranslate_source_locale" name="multilingual[gtranslate][source_locale]" style="width: 100%;">
                                                     @foreach($allGTranslateLanguages as $code => $name)
                                                         <option value="{{ $code }}" @selected($selectedSourceLocale === $code)>
                                                             {{ $name }} ({{ strtoupper($code) }})
                                                         </option>
                                                     @endforeach
                                                 </select>
                                             </div>

                                            <div class="mb-3">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                    <label class="form-label mb-0 fw-semibold" for="gtranslate_target_locales">
                                                        {{ __('admin.settings.multilingual.target_languages') }}
                                                    </label>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" id="js-gtranslate-select-all" style="font-size: 11px;">
                                                            <i class="ti ti-checks me-1"></i>{{ __('admin.settings.multilingual.select_all') }}
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 rounded-pill" id="js-gtranslate-select-popular" style="font-size: 11px;">
                                                            <i class="ti ti-star me-1"></i>{{ __('admin.settings.multilingual.popular_languages') }}
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" id="js-gtranslate-deselect-all" style="font-size: 11px;">
                                                            <i class="ti ti-x me-1"></i>{{ __('admin.settings.multilingual.deselect_all') }}
                                                        </button>
                                                    </div>
                                                </div>

                                                <select class="form-select text-dark" id="gtranslate_target_locales" name="multilingual[gtranslate][target_locales][]" multiple="multiple" style="width: 100%;">
                                                    <option value="all" @selected($gtranslateTargets->contains('all'))>
                                                        ★ {{ __('admin.settings.multilingual.all_languages_option') }}
                                                    </option>
                                                    @foreach($allGTranslateLanguages as $code => $name)
                                                        @if($code !== ($sourceLanguage?->code ?? 'vi'))
                                                            <option value="{{ $code }}" @selected($gtranslateTargets->contains($code) && !$gtranslateTargets->contains('all'))>
                                                                {{ $name }} ({{ strtoupper($code) }})
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <span class="text-muted small" id="js-gtranslate-count-text">
                                                        {{ __('admin.settings.multilingual.selected_count', ['count' => $gtranslateTargets->count(), 'total' => count($allGTranslateLanguages) - 1]) }}
                                                    </span>
                                                </div>
                                                @error('multilingual.gtranslate.target_locales')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label" for="gtranslate_widget_look">{{ __('admin.settings.multilingual.widget_look') }}</label>
                                                    <select class="form-select js-gtranslate-input" id="gtranslate_widget_look" name="multilingual[gtranslate][widget_look]">
                                                        @foreach(['float', 'dropdown_with_flags', 'flags_dropdown', 'dropdown', 'flags', 'flags_name', 'flags_code', 'lang_names', 'lang_codes', 'globe', 'popup', 'popup_search', 'uswds'] as $look)
                                                            <option value="{{ $look }}" @selected(data_get($multilingual, 'gtranslate.widget_look', 'float') === $look)>{{ __('admin.settings.multilingual.look_'.$look) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label" for="gtranslate_position">{{ __('admin.settings.multilingual.position') }}</label>
                                                    <select class="form-select js-gtranslate-input" id="gtranslate_position" name="multilingual[gtranslate][position]">
                                                        @foreach(['bottom_right', 'bottom_left', 'top_right', 'top_left', 'inline'] as $position)
                                                            <option value="{{ $position }}" @selected(data_get($multilingual, 'gtranslate.position', 'bottom_right') === $position)>{{ __('admin.settings.multilingual.position_'.$position) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-column gap-2">
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="multilingual[gtranslate][detect_browser_language]" value="0">
                                                    <input class="form-check-input js-gtranslate-input" type="checkbox" name="multilingual[gtranslate][detect_browser_language]" value="1" id="gtranslate_detect_browser" @checked(data_get($multilingual, 'gtranslate.detect_browser_language', false))>
                                                    <label class="form-check-label" for="gtranslate_detect_browser">{{ __('admin.settings.multilingual.detect_browser') }}</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="multilingual[gtranslate][native_language_names]" value="0">
                                                    <input class="form-check-input js-gtranslate-input" type="checkbox" name="multilingual[gtranslate][native_language_names]" value="1" id="gtranslate_native_names" @checked(data_get($multilingual, 'gtranslate.native_language_names', true))>
                                                    <label class="form-check-label" for="gtranslate_native_names">{{ __('admin.settings.multilingual.native_names') }}</label>
                                                </div>
                                            </div>

                                            <p class="form-text mt-3 mb-0">{{ __('admin.settings.multilingual.free_limit_note') }}</p>
                                        </div>
                                    </div>

                                    <div class="col-xl-5 col-lg-6">
                                        <div class="border rounded-3 p-4 bg-light-subtle h-100 d-flex flex-column">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                                    <i class="ti ti-device-desktop fs-5 text-primary"></i>
                                                    {{ __('admin.settings.multilingual.preview_title') }}
                                                </h6>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Live Demo</span>
                                            </div>
                                            <p class="text-muted small mb-3">{{ __('admin.settings.multilingual.preview_help') }}</p>

                                            <div class="preview-browser-window border rounded-3 overflow-hidden shadow-sm flex-grow-1 d-flex flex-column bg-white">
                                                <div class="browser-header bg-light border-bottom px-3 py-2 d-flex align-items-center gap-2">
                                                    <span class="rounded-circle bg-danger d-inline-block" style="width: 9px; height: 9px;"></span>
                                                    <span class="rounded-circle bg-warning d-inline-block" style="width: 9px; height: 9px;"></span>
                                                    <span class="rounded-circle bg-success d-inline-block" style="width: 9px; height: 9px;"></span>
                                                    <div class="bg-white border rounded-pill px-3 py-1 flex-grow-1 text-muted text-truncate font-monospace" style="font-size: 11px;">
                                                        https://your-store.com/
                                                    </div>
                                                </div>
                                                <iframe id="gtranslate-preview-iframe" class="w-100 flex-grow-1 border-0" style="min-height: 380px;"></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Nút liên hệ Pane -->
            <div class="tab-pane fade {{ $activeTab === 'contact' ? 'show active' : '' }}" id="contact-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        @php
                            $contact = $settings->get('contact') ?? [];
                        @endphp
                        <div data-site-locale-scope data-i18n-scope>
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.contact.title') }}</h5>
                        @include('admin.settings._locale-switch')
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
                            <div class="col-md-12 mb-3" data-site-locale="{{ $siteDefaultLocale }}">
                                <label class="form-label fw-semibold text-dark" for="contact_address">{{ __('admin.settings.contact.head_office_address') }}</label>
                                <textarea class="form-control text-dark" id="contact_address" name="contact[address]" data-i18n-locale="{{ $siteDefaultLocale }}" data-i18n-field="address" rows="2" placeholder="{{ __('admin.settings.contact.head_office_address_placeholder') }}">{{ old('contact.address', $contact['address'] ?? '') }}</textarea>
                            </div>
                            @foreach($siteOtherLanguages->keyBy('code') as $code => $language)
                                <div class="col-md-12 mb-3 d-none" data-site-locale="{{ $code }}">
                                    <label class="form-label fw-semibold text-dark" for="contact_address_{{ $code }}">{{ __('admin.settings.contact.head_office_address') }} ({{ $language->native_name }})</label>
                                    <textarea class="form-control text-dark" id="contact_address_{{ $code }}" name="contact[translations][{{ $code }}][address]" data-i18n-locale="{{ $code }}" data-i18n-field="address" rows="2" placeholder="{{ ($contact['address'] ?? '') ?: __('admin.settings.contact.head_office_address_placeholder') }}">{{ old("contact.translations.$code.address", data_get($contact, "translations.$code.address")) }}</textarea>
                                </div>
                            @endforeach
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="contact_google_map_url">{{ __('admin.settings.contact.head_office_map') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ti ti-map-pin fs-5 text-danger"></i></span>
                                    <input type="text" class="form-control text-dark" id="contact_google_map_url" name="contact[google_map_url]" 
                                        placeholder="{{ __('admin.settings.contact.map_placeholder') }}" value="{{ old('contact.google_map_url', $contact['google_map_url'] ?? '') }}">
                                </div>
                                <div class="form-text text-muted">{{ __('admin.settings.contact.head_office_map_help') }}</div>
                            </div>
                        </div>

                        <!-- Các Văn phòng / Chi nhánh / Cửa hàng phụ -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                                <div>
                                    <h6 class="fw-semibold text-dark mb-1">{{ __('admin.settings.contact.branches_title') }}</h6>
                                    <p class="small text-muted mb-0">{{ __('admin.settings.contact.branches_help') }}</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="add-branch-btn">
                                    <i class="ti ti-plus me-1"></i> {{ __('admin.settings.contact.add_branch') }}
                                </button>
                            </div>

                            <div id="branches-container" class="d-flex flex-column gap-3 mt-2">
                                @php
                                    $branches = $contact['branches'] ?? [];
                                    if (!is_array($branches)) $branches = [];
                                @endphp
                                @forelse($branches as $bIndex => $branch)
                                    <div class="card border mb-2 branch-row" data-index="{{ $bIndex }}">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                            <span class="fw-semibold text-dark small"><i class="ti ti-building-store me-1 text-primary"></i> {{ __('admin.settings.contact.branch_number', ['number' => $loop->iteration]) }}</span>
                                            <button type="button" class="btn btn-outline-danger btn-xs remove-branch-btn py-0 px-2" title="{{ __('admin.settings.contact.remove_branch') }}">
                                                <i class="ti ti-trash me-1"></i> {{ __('admin.settings.delete') }}
                                            </button>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="row g-2">
                                                <div class="col-md-4 mb-2">
                                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_name') }}</label>
                                                    <input type="text" class="form-control form-control-sm text-dark" name="contact[branches][{{ $bIndex }}][name]" data-site-locale="{{ $siteDefaultLocale }}" data-i18n-locale="{{ $siteDefaultLocale }}" data-i18n-field="branch_{{ $bIndex }}_name"
                                                        placeholder="{{ __('admin.settings.contact.branch_name_placeholder') }}" value="{{ old("contact.branches.$bIndex.name", $branch['name'] ?? '') }}">
                                                    @foreach($siteOtherLanguages as $language)
                                                        <input type="text" class="form-control form-control-sm text-dark d-none" name="contact[branches][{{ $bIndex }}][translations][{{ $language->code }}][name]" data-site-locale="{{ $language->code }}" data-i18n-locale="{{ $language->code }}" data-i18n-field="branch_{{ $bIndex }}_name" aria-label="{{ __('admin.settings.contact.branch_name') }} ({{ $language->native_name }})"
                                                            placeholder="{{ ($branch['name'] ?? '') ?: __('admin.settings.contact.branch_name_placeholder') }}" value="{{ old("contact.branches.$bIndex.translations.{$language->code}.name", data_get($branch, "translations.{$language->code}.name")) }}">
                                                    @endforeach
                                                </div>
                                                <div class="col-md-4 mb-2">
                                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_phone') }}</label>
                                                    <input type="text" class="form-control form-control-sm text-dark" name="contact[branches][{{ $bIndex }}][phone]"
                                                        placeholder="0901 234 567" value="{{ old("contact.branches.$bIndex.phone", $branch['phone'] ?? '') }}">
                                                </div>
                                                <div class="col-md-4 mb-2">
                                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_email') }}</label>
                                                    <input type="email" class="form-control form-control-sm text-dark" name="contact[branches][{{ $bIndex }}][email]"
                                                        placeholder="hanoi@domain.com" value="{{ old("contact.branches.$bIndex.email", $branch['email'] ?? '') }}">
                                                </div>
                                                <div class="col-md-12 mb-2">
                                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_address') }}</label>
                                                    <input type="text" class="form-control form-control-sm text-dark" name="contact[branches][{{ $bIndex }}][address]" data-site-locale="{{ $siteDefaultLocale }}" data-i18n-locale="{{ $siteDefaultLocale }}" data-i18n-field="branch_{{ $bIndex }}_address"
                                                        placeholder="{{ __('admin.settings.contact.branch_address_placeholder') }}" value="{{ old("contact.branches.$bIndex.address", $branch['address'] ?? '') }}">
                                                    @foreach($siteOtherLanguages as $language)
                                                        <input type="text" class="form-control form-control-sm text-dark d-none" name="contact[branches][{{ $bIndex }}][translations][{{ $language->code }}][address]" data-site-locale="{{ $language->code }}" data-i18n-locale="{{ $language->code }}" data-i18n-field="branch_{{ $bIndex }}_address" aria-label="{{ __('admin.settings.contact.branch_address') }} ({{ $language->native_name }})"
                                                            placeholder="{{ ($branch['address'] ?? '') ?: __('admin.settings.contact.branch_address_placeholder') }}" value="{{ old("contact.branches.$bIndex.translations.{$language->code}.address", data_get($branch, "translations.{$language->code}.address")) }}">
                                                    @endforeach
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_map') }}</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><i class="ti ti-map-pin text-danger"></i></span>
                                                        <input type="text" class="form-control text-dark" name="contact[branches][{{ $bIndex }}][google_map_url]"
                                                            placeholder="{{ __('admin.settings.contact.map_placeholder') }}" value="{{ old("contact.branches.$bIndex.google_map_url", $branch['google_map_url'] ?? '') }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="empty-branches-msg text-center text-muted p-4 rounded-3 border-dashed bg-light-subtle">
                                        <i class="ti ti-map-pins fs-7 d-block mb-1 text-primary"></i>
                                        <span class="small fw-semibold">{{ __('admin.settings.contact.no_branches') }}</span>
                                        <div class="small text-muted mt-1">{!! __('admin.settings.contact.no_branches_hint', ['button' => '<strong>"'.e(__('admin.settings.contact.add_branch')).'"</strong>']) !!}</div>
                                    </div>
                                @endforelse
                            </div>
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
                                    <h6 class="fw-semibold text-dark mb-1">{{ __('admin.settings.social.custom_title') }}</h6>
                                    <p class="small text-muted mb-0 mt-2">{{ __('admin.settings.social.custom_help') }}</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="add-custom-social-btn">
                                    <i class="ti ti-plus me-1"></i> {{ __('admin.settings.social.add_link') }}
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
                                                <option value="ti ti-shopping-cart" data-icon="ti ti-shopping-cart" @selected($selectedIcon === 'ti ti-shopping-cart' || $selectedIcon === 'simple-icons:lazada')>{{ __('admin.settings.social.icon_lazada') }}</option>
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
                                                <option value="ti ti-phone" data-icon="ti ti-phone" @selected($selectedIcon === 'ti ti-phone')>{{ __('admin.settings.social.icon_phone') }}</option>
                                                <option value="ti ti-mail" data-icon="ti ti-mail" @selected($selectedIcon === 'ti ti-mail')>Email</option>
                                                <option value="ti ti-map-pin" data-icon="ti ti-map-pin" @selected($selectedIcon === 'ti ti-map-pin')>{{ __('admin.settings.social.icon_map') }}</option>
                                                <option value="ti ti-link" data-icon="ti ti-link" @selected($selectedIcon === 'ti ti-link')>{{ __('admin.settings.social.icon_other') }}</option>
                                            </select>
                                        </div>
                                        <!-- Title -->
                                        <div class="col-md-3 mb-2 mb-md-0">
                                            <input type="text" class="form-control text-dark" name="social_links[custom][{{ $index }}][title]" 
                                                placeholder="{{ __('admin.settings.social.title_placeholder') }}" value="{{ old("social_links.custom.$index.title", $customLink['title'] ?? '') }}">
                                        </div>
                                        <!-- URL -->
                                        <div class="col-md-5 mb-2 mb-md-0">
                                            <input type="url" class="form-control text-dark" name="social_links[custom][{{ $index }}][url]" 
                                                placeholder="https://..." value="{{ old("social_links.custom.$index.url", $customLink['url'] ?? '') }}">
                                        </div>
                                        <!-- Remove Button -->
                                        <div class="col-md-1 text-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-custom-social-btn" title="{{ __('admin.settings.delete') }}">
                                                <i class="ti ti-trash fs-5"></i>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="empty-custom-links-msg text-center text-muted p-4 rounded-3 border-dashed bg-light-subtle">
                                        <i class="ti ti-link-plus fs-7 d-block mb-1 text-primary"></i>
                                        <span class="small fw-semibold">{{ __('admin.settings.social.no_links') }}</span>
                                        <div class="small text-muted mt-1">{!! __('admin.settings.social.no_links_hint', ['button' => '<strong>"'.e(__('admin.settings.social.add_link')).'"</strong>']) !!}</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mã nhúng Pane -->
            <div class="tab-pane fade {{ $activeTab === 'embed' ? 'show active' : '' }}" id="embed-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.embed.title') }}</h5>
                        @php($canEditEmbeds = auth()->user()->isSuperAdmin())
                        @unless ($canEditEmbeds)
                            <div class="alert alert-info">{{ __('admin.settings.embed.superadmin_only') }}</div>
                        @endunless
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label class="form-label fw-semibold text-dark" for="embed_header">{{ __('admin.settings.embed.header') }}</label>
                                <textarea class="form-control text-dark font-monospace" id="embed_header" name="embed_header" rows="8" @disabled(! $canEditEmbeds) 
                                    placeholder="{{ __('admin.settings.embed.placeholder') }}">{{ old('embed_header', $settings->get('embed_header')) }}</textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="embed_footer">{{ __('admin.settings.embed.footer') }}</label>
                                <textarea class="form-control text-dark font-monospace" id="embed_footer" name="embed_footer" rows="8" @disabled(! $canEditEmbeds) 
                                    placeholder="{{ __('admin.settings.embed.placeholder') }}">{{ old('embed_footer', $settings->get('embed_footer')) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'analytics' ? 'show active' : '' }}" id="analytics-pane" role="tabpanel">
                <div class="analytics-settings">
                    <div class="analytics-settings__section">
                        <div class="analytics-settings__section-title">{{ __('admin.analytics.settings_title') }}</div>
                        <ol class="analytics-settings__steps">
                            <li class="mb-2">{{ __('admin.analytics.step_1') }}</li>
                            <li>
                                {{ __('admin.analytics.step_2') }}
                                @if($analyticsServiceEmail)
                                    <div class="input-group input-group-sm analytics-settings__service-account" style="max-width: 560px;">
                                        <input type="text" class="form-control font-monospace" id="ga_service_email" value="{{ $analyticsServiceEmail }}" readonly>
                                        <button type="button" class="btn btn-outline-primary" data-copy-target="ga_service_email" data-copied-text="{{ __('admin.analytics.copied') }}">
                                            <i class="ti ti-copy me-1" aria-hidden="true"></i>{{ __('admin.analytics.copy') }}
                                        </button>
                                    </div>
                                @else
                                    <div class="alert alert-warning small py-2 mt-2 mb-0">{{ __('admin.analytics.service_account_missing') }}</div>
                                @endif
                            </li>
                            <li>{{ __('admin.analytics.step_3') }}</li>
                        </ol>
                    </div>

                    <div class="analytics-settings__section">
                        <div class="analytics-settings__section-title">{{ __('admin.analytics.tab') }}</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="analytics-settings__field">
                                    <label class="form-label fw-semibold text-dark" for="ga_measurement_id">{{ __('admin.analytics.measurement_id') }}</label>
                                    <input type="text" class="form-control text-dark font-monospace" id="ga_measurement_id" name="google_analytics[measurement_id]"
                                           value="{{ old('google_analytics.measurement_id', $analyticsSettings['measurement_id']) }}" placeholder="G-XXXXXXXXXX" maxlength="30">
                                    <div class="form-text">{{ __('admin.analytics.measurement_id_help') }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="analytics-settings__field">
                                    <label class="form-label fw-semibold text-dark" for="ga_property_id">{{ __('admin.analytics.property_id') }}</label>
                                    <input type="text" inputmode="numeric" class="form-control text-dark font-monospace" id="ga_property_id" name="google_analytics[property_id]"
                                           value="{{ old('google_analytics.property_id', $analyticsSettings['property_id']) }}" placeholder="987654321" maxlength="20">
                                    <div class="form-text">{{ __('admin.analytics.property_id_help') }}</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="analytics-settings__field">
                                    <label class="form-label fw-semibold text-dark" for="gsc_site_url">{{ __('admin.analytics.gsc_site_url') }}</label>
                                    <input type="text" class="form-control text-dark font-monospace" id="gsc_site_url" name="google_analytics[gsc_site_url]"
                                           value="{{ old('google_analytics.gsc_site_url', $analyticsSettings['gsc_site_url'] ?? '') }}" placeholder="sc-domain:example.com" maxlength="255">
                                    <div class="form-text">{{ __('admin.analytics.gsc_site_url_help') }}</div>
                                </div>
                            </div>
                        </div>

                        @if($analyticsServiceEmail)
                            <div class="analytics-settings__actions">
                                <div class="analytics-settings__check">
                                    <x-admin.action-button variant="outline-primary" icon="plug-connected" data-ga-check="{{ route('admin.settings.analytics.check') }}">{{ __('admin.analytics.check') }}</x-admin.action-button>
                                    <span class="small" data-ga-check-result role="status"></span>
                                </div>
                                <div class="analytics-settings__check">
                                    <x-admin.action-button variant="outline-primary" icon="brand-google" data-ga-check="{{ route('admin.settings.search-console.check') }}">{{ __('admin.analytics.gsc_check') }}</x-admin.action-button>
                                    <span class="small" data-ga-check-result role="status"></span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        <!-- Sticky Save Button -->
        <div class="mt-4 pt-2 mb-5">
            <button data-tour="settings.save" type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                <i class="ti ti-device-floppy me-1 fs-5"></i> {{ __('admin.settings.save_settings') }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const activeTabInput = document.querySelector('[data-settings-active-tab]');
            document.querySelectorAll('[data-settings-tab]').forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function (event) {
                    const activeTab = event.target.dataset.settingsTab;
                    if (!activeTab) return;

                    if (activeTabInput) activeTabInput.value = activeTab;
                    const url = new URL(window.location.href);
                    if (activeTab === 'general') {
                        url.searchParams.delete('tab');
                    } else {
                        url.searchParams.set('tab', activeTab);
                    }
                    window.history.replaceState({}, '', url);
                });
            });

            document.querySelectorAll('[data-copy-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const input = document.getElementById(button.dataset.copyTarget);
                    if (!input) return;
                    navigator.clipboard?.writeText(input.value);
                    input.select();
                    const original = button.innerHTML;
                    button.textContent = button.dataset.copiedText;
                    setTimeout(function () { button.innerHTML = original; }, 1500);
                });
            });

            // Tests the saved IDs: save the form first when they were just changed.
            document.querySelectorAll('[data-ga-check]').forEach(function (checkButton) {
            const checkResult = checkButton.parentElement.querySelector('[data-ga-check-result]');
            checkButton.addEventListener('click', async function () {
                checkButton.disabled = true;
                checkResult.className = 'small text-muted';
                checkResult.textContent = '…';
                try {
                    const response = await fetch(checkButton.dataset.gaCheck, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                    });
                    const data = await response.json().catch(function () { return {}; });
                    checkResult.className = 'small fw-semibold ' + (data.success ? 'text-success' : 'text-danger');
                    checkResult.textContent = data.message || response.statusText;
                } catch (error) {
                    checkResult.className = 'small fw-semibold text-danger';
                    checkResult.textContent = error.message;
                } finally {
                    checkButton.disabled = false;
                }
            });
            });
        });
    </script>
@endpush

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

            // GTranslate Live Preview Logic
            const gtranslateContainer = document.getElementById('gtranslate-settings');
            const previewIframe = document.getElementById('gtranslate-preview-iframe');

            function toGTranslateLocale(code, regional) {
                if (code !== 'zh') return code;
                return (regional || '').toUpperCase().endsWith('_TW') ? 'zh-TW' : 'zh-CN';
            }

            function updateGTranslatePreview() {
                if (!gtranslateContainer || !previewIframe) return;

                const sourceSelect = document.getElementById('gtranslate_source_locale');
                const sourceLocale = sourceSelect ? sourceSelect.value : (gtranslateContainer.dataset.sourceLocale || 'vi');
                let localesMap = {};
                try {
                    localesMap = JSON.parse(gtranslateContainer.dataset.localesMap || '{}');
                } catch (e) {
                    localesMap = {};
                }

                const targetSelect = document.getElementById('gtranslate_target_locales');
                let selectedTargetLocales = [];
                if (targetSelect) {
                    selectedTargetLocales = Array.from(targetSelect.selectedOptions).map(opt => opt.value);
                } else {
                    selectedTargetLocales = Array.from(document.querySelectorAll('input[name="multilingual[gtranslate][target_locales][]"]:checked')).map(cb => cb.value);
                }

                if (selectedTargetLocales.includes('all')) {
                    if (targetSelect) {
                        selectedTargetLocales = Array.from(targetSelect.options)
                            .map(opt => opt.value)
                            .filter(val => val !== 'all');
                    }
                }

                const defaultGLocale = toGTranslateLocale(sourceLocale, localesMap[sourceLocale]);
                const targetGLocales = selectedTargetLocales.map(code => toGTranslateLocale(code, localesMap[code]));
                const languages = Array.from(new Set([defaultGLocale, ...targetGLocales]));

                const widgetLook = document.getElementById('gtranslate_widget_look')?.value || 'float';
                const position = document.getElementById('gtranslate_position')?.value || 'bottom_right';
                const detectBrowser = document.getElementById('gtranslate_detect_browser')?.checked || false;
                const nativeNames = document.getElementById('gtranslate_native_names')?.checked || false;

                const widgetSettings = {
                    default_language: defaultGLocale,
                    languages: languages,
                    wrapper_selector: '.gtranslate_wrapper',
                    native_language_names: nativeNames,
                    detect_browser_language: detectBrowser
                };

                if (position === 'inline') {
                    widgetSettings.switcher_horizontal_position = 'inline';
                } else {
                    const parts = position.split('_');
                    widgetSettings.switcher_vertical_position = parts[0] || 'bottom';
                    widgetSettings.switcher_horizontal_position = parts[1] || 'right';
                }

                const scriptMap = {
                    'float': 'https://cdn.gtranslate.net/widgets/latest/float.js',
                    'dropdown_with_flags': 'https://cdn.gtranslate.net/widgets/latest/dwf.js',
                    'flags_dropdown': 'https://cdn.gtranslate.net/widgets/latest/fd.js',
                    'dropdown': 'https://cdn.gtranslate.net/widgets/latest/dropdown.js',
                    'flags': 'https://cdn.gtranslate.net/widgets/latest/flags.js',
                    'flags_name': 'https://cdn.gtranslate.net/widgets/latest/fn.js',
                    'flags_code': 'https://cdn.gtranslate.net/widgets/latest/fc.js',
                    'lang_names': 'https://cdn.gtranslate.net/widgets/latest/ln.js',
                    'lang_codes': 'https://cdn.gtranslate.net/widgets/latest/lc.js',
                    'globe': 'https://cdn.gtranslate.net/widgets/latest/globe.js',
                    'popup': 'https://cdn.gtranslate.net/widgets/latest/popup.js',
                    'popup_search': 'https://cdn.gtranslate.net/widgets/latest/ps.js',
                    'uswds': 'https://cdn.gtranslate.net/widgets/latest/uswds.js'
                };
                const scriptUrl = scriptMap[widgetLook] || scriptMap['float'];

                const inlineWrapperHtml = position === 'inline'
                    ? '<div class="gtranslate_wrapper notranslate"></div>'
                    : '';
                const floatWrapperHtml = position !== 'inline'
                    ? '<div class="gtranslate_wrapper notranslate"></div>'
                    : '';

                const docHtml = `<!DOCTYPE html>
<html lang="${sourceLocale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <style>
        body {
            margin: 0;
            padding: 12px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            font-size: 13px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            position: relative;
        }
        .store-header {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .store-hero {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            text-align: center;
        }
        .store-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
        }
        .gtranslate_wrapper {
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="store-header">
        <div class="fw-bold text-primary d-flex align-items-center gap-1">
            <i class="ti ti-shopping-bag fs-5"></i>
            <span>Demo Store</span>
        </div>
        <div id="inline-slot">
            ${inlineWrapperHtml}
        </div>
    </div>

    <div class="store-hero">
        <h6 class="fw-bold mb-1">{{ __('admin.settings.multilingual.preview_welcome') }}</h6>
        <p class="mb-0 small opacity-90">{{ __('admin.settings.multilingual.preview_intro') }}</p>
    </div>

    <div class="store-card flex-grow-1">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">{{ __('admin.settings.multilingual.preview_featured') }}</span>
            <span class="badge bg-primary-subtle text-primary">{{ __('admin.settings.multilingual.preview_discount') }}</span>
        </div>
        <p class="text-muted small mb-2">{{ __('admin.settings.multilingual.preview_try') }}</p>
        <div class="row g-2">
            <div class="col-6">
                <div class="p-2 border rounded bg-light text-center">
                    <div class="fw-bold">{{ __('admin.settings.multilingual.preview_product_phone') }}</div>
                    <div class="text-success small fw-semibold">12.500.000 đ</div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 border rounded bg-light text-center">
                    <div class="fw-bold">{{ __('admin.settings.multilingual.preview_product_headphones') }}</div>
                    <div class="text-success small fw-semibold">2.100.000 đ</div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-muted text-center pt-2" style="font-size: 11px;">
        &copy; 2026 Demo Store &bull; GTranslate Live Preview
    </div>

    ${floatWrapperHtml}

    <script>
        window.gtranslateSettings = ${JSON.stringify(widgetSettings)};
    <\/script>
    <script src="${scriptUrl}"><\/script>
</body>
</html>`;

                previewIframe.srcdoc = docHtml;
            }

            let gtranslatePreviewTimeout = null;
            function updateGTranslatePreviewDebounced() {
                clearTimeout(gtranslatePreviewTimeout);
                gtranslatePreviewTimeout = setTimeout(updateGTranslatePreview, 150);
            }

            if (gtranslateContainer) {
                document.querySelectorAll('.js-gtranslate-input').forEach(function (input) {
                    input.addEventListener('change', updateGTranslatePreviewDebounced);
                });
                document.querySelectorAll('.js-multilingual-mode').forEach(function (input) {
                    input.addEventListener('change', function () {
                        if (this.checked && this.value === 'gtranslate') {
                            updateGTranslatePreviewDebounced();
                        }
                    });
                });

                // Init Select2 for Source Language
                const $sourceSelect = $('#gtranslate_source_locale');
                if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined' && $sourceSelect.length) {
                    $sourceSelect.select2({
                        width: '100%'
                    });
                    $sourceSelect.on('change', updateGTranslatePreviewDebounced);
                }

                // Init Select2 for GTranslate Target Languages
                const $targetSelect = $('#gtranslate_target_locales');
                if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined' && $targetSelect.length) {
                    $targetSelect.select2({
                        placeholder: '{{ __('admin.settings.multilingual.search_language') }}',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false
                    });

                    function updateCountText() {
                        const vals = $targetSelect.val() || [];
                        const total = $targetSelect.find('option').length - 1;
                        let text = '';
                        if (vals.includes('all')) {
                            text = `{{ __('admin.settings.multilingual.selected_count', ['count' => ':count', 'total' => ':total']) }}`
                                .replace(':count', `${total} (${'{{ __('admin.settings.multilingual.select_all') }}'})`)
                                .replace(':total', total);
                        } else {
                            text = `{{ __('admin.settings.multilingual.selected_count', ['count' => ':count', 'total' => ':total']) }}`
                                .replace(':count', vals.length)
                                .replace(':total', total);
                        }
                        $('#js-gtranslate-count-text').text(text);
                    }

                    $targetSelect.on('change', function () {
                        const vals = $targetSelect.val() || [];
                        if (vals.length > 1 && vals.includes('all')) {
                            if (vals[vals.length - 1] === 'all') {
                                $targetSelect.val(['all']);
                            } else {
                                $targetSelect.val(vals.filter(v => v !== 'all'));
                            }
                        }
                        updateGTranslatePreviewDebounced();
                        updateCountText();
                    });

                    $('#js-gtranslate-select-all').on('click', function (e) {
                        e.preventDefault();
                        $targetSelect.val(['all']).trigger('change');
                    });

                    $('#js-gtranslate-select-popular').on('click', function (e) {
                        e.preventDefault();
                        const popular = ['en', 'zh-CN', 'zh-TW', 'ja', 'ko', 'fr', 'de', 'es', 'ru', 'th'];
                        $targetSelect.val(popular).trigger('change');
                    });

                    $('#js-gtranslate-deselect-all').on('click', function (e) {
                        e.preventDefault();
                        $targetSelect.val([]).trigger('change');
                    });

                    updateCountText();
                }

                updateGTranslatePreview();
            }

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
                'ti ti-map-pin': @json(__('admin.settings.social.default_title_address')),
                'ti ti-link': @json(__('admin.settings.social.default_title_link'))
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
                                <option value="ti ti-shopping-cart" data-icon="ti ti-shopping-cart">{{ __('admin.settings.social.icon_lazada') }}</option>
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
                                <option value="ti ti-phone" data-icon="ti ti-phone">{{ __('admin.settings.social.icon_phone') }}</option>
                                <option value="ti ti-mail" data-icon="ti ti-mail">Email</option>
                                <option value="ti ti-map-pin" data-icon="ti ti-map-pin">{{ __('admin.settings.social.icon_map') }}</option>
                                <option value="ti ti-link" data-icon="ti ti-link">{{ __('admin.settings.social.icon_other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <input type="text" class="form-control text-dark" name="social_links[custom][${index}][title]"
                                placeholder="{{ __('admin.settings.social.title_placeholder') }}" value="Website">
                        </div>
                        <div class="col-md-5 mb-2 mb-md-0">
                            <input type="url" class="form-control text-dark" name="social_links[custom][${index}][url]" 
                                placeholder="https://...">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-custom-social-btn" title="{{ __('admin.settings.delete') }}">
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
                                    <span class="small fw-semibold">{{ __('admin.settings.social.no_links') }}</span>
                                    <div class="small text-muted mt-1">{!! __('admin.settings.social.no_links_hint', ['button' => '<strong>"'.e(__('admin.settings.social.add_link')).'"</strong>']) !!}</div>
                                </div>
                            `;
                        }
                    }
                });
            }

            // Add Office / Branch Location Row
            const addBranchBtn = document.getElementById('add-branch-btn');
            const branchesContainer = document.getElementById('branches-container');

            if (addBranchBtn && branchesContainer) {
                addBranchBtn.addEventListener('click', function () {
                    const emptyMsg = branchesContainer.querySelector('.empty-branches-msg');
                    if (emptyMsg) {
                        emptyMsg.remove();
                    }

                    const index = Date.now();
                    const count = branchesContainer.querySelectorAll('.branch-row').length + 1;
                    const branchCard = document.createElement('div');
                    branchCard.className = 'card border mb-2 branch-row';
                    branchCard.dataset.index = index;
                    branchCard.innerHTML = `
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="fw-semibold text-dark small"><i class="ti ti-building-store me-1 text-primary"></i> {{ __('admin.settings.contact.branch_number', ['number' => '${count}']) }}</span>
                            <button type="button" class="btn btn-outline-danger btn-xs remove-branch-btn py-0 px-2" title="{{ __('admin.settings.contact.remove_branch') }}">
                                <i class="ti ti-trash me-1"></i> {{ __('admin.settings.delete') }}
                            </button>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_name') }}</label>
                                    <input type="text" class="form-control form-control-sm text-dark" name="contact[branches][${index}][name]" data-site-locale="{{ $siteDefaultLocale }}" data-i18n-locale="{{ $siteDefaultLocale }}" data-i18n-field="branch_${index}_name" placeholder="{{ __('admin.settings.contact.branch_name_placeholder') }}">
                                    @foreach($siteOtherLanguages as $language)
                                    <input type="text" class="form-control form-control-sm text-dark d-none" name="contact[branches][${index}][translations][{{ $language->code }}][name]" data-site-locale="{{ $language->code }}" data-i18n-locale="{{ $language->code }}" data-i18n-field="branch_${index}_name" aria-label="{{ __('admin.settings.contact.branch_name') }} ({{ $language->native_name }})" placeholder="{{ __('admin.settings.contact.branch_name_placeholder') }}">
                                    @endforeach
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_phone') }}</label>
                                    <input type="text" class="form-control form-control-sm text-dark" name="contact[branches][${index}][phone]" placeholder="0901 234 567">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_email') }}</label>
                                    <input type="email" class="form-control form-control-sm text-dark" name="contact[branches][${index}][email]" placeholder="hanoi@domain.com">
                                </div>
                                <div class="col-md-12 mb-2">
                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_address') }}</label>
                                    <input type="text" class="form-control form-control-sm text-dark" name="contact[branches][${index}][address]" data-site-locale="{{ $siteDefaultLocale }}" data-i18n-locale="{{ $siteDefaultLocale }}" data-i18n-field="branch_${index}_address" placeholder="{{ __('admin.settings.contact.branch_address_placeholder') }}">
                                    @foreach($siteOtherLanguages as $language)
                                    <input type="text" class="form-control form-control-sm text-dark d-none" name="contact[branches][${index}][translations][{{ $language->code }}][address]" data-site-locale="{{ $language->code }}" data-i18n-locale="{{ $language->code }}" data-i18n-field="branch_${index}_address" aria-label="{{ __('admin.settings.contact.branch_address') }} ({{ $language->native_name }})" placeholder="{{ __('admin.settings.contact.branch_address_placeholder') }}">
                                    @endforeach
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-semibold text-dark">{{ __('admin.settings.contact.branch_map') }}</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="ti ti-map-pin text-danger"></i></span>
                                        <input type="text" class="form-control text-dark" name="contact[branches][${index}][google_map_url]" placeholder="{{ __('admin.settings.contact.map_placeholder') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    branchesContainer.appendChild(branchCard);
                    window.AdminSiteLocale?.apply(branchCard.closest('[data-site-locale-scope]'));
                });

                branchesContainer.addEventListener('click', function (e) {
                    const removeBtn = e.target.closest('.remove-branch-btn');
                    if (removeBtn) {
                        const row = removeBtn.closest('.branch-row');
                        if (row) {
                            row.remove();
                            if (branchesContainer.querySelectorAll('.branch-row').length === 0) {
                                branchesContainer.innerHTML = `
                                    <div class="empty-branches-msg text-center text-muted p-4 rounded-3 border-dashed bg-light-subtle">
                                        <i class="ti ti-map-pins fs-7 d-block mb-1 text-primary"></i>
                                        <span class="small fw-semibold">{{ __('admin.settings.contact.no_branches') }}</span>
                                        <div class="small text-muted mt-1">{!! __('admin.settings.contact.no_branches_hint', ['button' => '<strong>"'.e(__('admin.settings.contact.add_branch')).'"</strong>']) !!}</div>
                                    </div>
                                `;
                            }
                        }
                    }
                });
            }
        });
    </script>
@endpush
