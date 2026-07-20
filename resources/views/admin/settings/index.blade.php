@extends('admin.layouts.app')

@section('title', __('admin.settings.title'))

@push('styles')
    <style>
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
    </style>
@endpush

@section('content')
    <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(135deg, #141e30 0%, #243b55 100%) !important;">
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
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#embed-pane" role="tab">
                <span><i class="ti ti-code fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.embed') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#smtp-pane" role="tab">
                <span><i class="ti ti-mail fs-4"></i></span>
                <span>{{ __('admin.settings.tabs.smtp') }}</span>
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
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                <div class="form-text text-dark">{{ __('admin.settings.general.logo_help') }}</div>
                                @if($settings->get('logo_url'))
                                    <div class="mt-3">
                                        <p class="small text-dark mb-1">{{ __('admin.settings.general.logo_current') }}</p>
                                        <img src="{{ $settings->get('logo_url') }}" alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="favicon">{{ __('admin.settings.general.favicon') }}</label>
                                <input type="file" class="form-control" id="favicon" name="favicon" accept="image/*">
                                <div class="form-text text-dark">{{ __('admin.settings.general.favicon_help') }}</div>
                                @if($settings->get('favicon_url'))
                                    <div class="mt-3">
                                        <p class="small text-dark mb-1">{{ __('admin.settings.general.favicon_current') }}</p>
                                        <img src="{{ $settings->get('favicon_url') }}" alt="Favicon" class="img-thumbnail" style="max-height: 40px;">
                                    </div>
                                @endif
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.theme.title') }}</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="theme_color">{{ __('admin.settings.theme.primary_color') }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color border" id="theme_color" name="theme[primary_color]" 
                                        value="{{ old('theme.primary_color', data_get($settings->get('theme'), 'primary_color', '#0d6efd')) }}" title="{{ __('admin.settings.theme.color_title') }}">
                                    <span class="text-dark small font-monospace fw-semibold">{{ data_get($settings->get('theme'), 'primary_color', '#0d6efd') }}</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="theme_layout">{{ __('admin.settings.theme.layout') }}</label>
                                <select class="form-select text-dark" id="theme_layout" name="theme[layout]">
                                    <option value="default" @selected(old('theme.layout', data_get($settings->get('theme'), 'layout', 'default')) === 'default')>{{ __('admin.settings.theme.layout_default') }}</option>
                                    <option value="compact" @selected(old('theme.layout', data_get($settings->get('theme'), 'layout', 'default')) === 'compact')>{{ __('admin.settings.theme.layout_compact') }}</option>
                                    <option value="boxed" @selected(old('theme.layout', data_get($settings->get('theme'), 'layout', 'default')) === 'boxed')>{{ __('admin.settings.theme.layout_boxed') }}</option>
                                </select>
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
                                <textarea class="form-control text-dark" id="contact_address" name="contact[address]" rows="3">{{ old('contact.address', $contact['address'] ?? '') }}</textarea>
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

            <!-- SMTP Pane -->
            <div class="tab-pane fade" id="smtp-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h5 class="card-title fw-semibold text-dark mb-0">{{ __('admin.settings.smtp.title') }}</h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="smtp[enabled]" value="1" id="smtp_enabled" 
                                    @checked(old('smtp.enabled', data_get($settings, 'notification_settings.smtp.enabled', false)))>
                                <label class="form-check-label fw-semibold text-dark" for="smtp_enabled">{{ __('admin.settings.smtp.enabled') }}</label>
                            </div>
                        </div>
                        
                        <div class="row smtp-fields">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_host">{{ __('admin.settings.smtp.host') }}</label>
                                <input type="text" class="form-control text-dark" id="smtp_host" name="smtp[host]" 
                                    value="{{ old('smtp.host', data_get($settings, 'notification_settings.smtp.host')) }}" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_port">{{ __('admin.settings.smtp.port') }}</label>
                                <input type="number" class="form-control text-dark" id="smtp_port" name="smtp[port]" 
                                    value="{{ old('smtp.port', data_get($settings, 'notification_settings.smtp.port')) }}" placeholder="465">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_encryption">{{ __('admin.settings.smtp.encryption') }}</label>
                                <select class="form-select text-dark" id="smtp_encryption" name="smtp[encryption]">
                                    <option value="ssl" @selected(old('smtp.encryption', data_get($settings, 'notification_settings.smtp.encryption', 'ssl')) === 'ssl')>{{ __('admin.settings.smtp.ssl') }}</option>
                                    <option value="tls" @selected(old('smtp.encryption', data_get($settings, 'notification_settings.smtp.encryption', 'ssl')) === 'tls')>{{ __('admin.settings.smtp.tls') }}</option>
                                    <option value="none" @selected(old('smtp.encryption', data_get($settings, 'notification_settings.smtp.encryption', 'ssl')) === 'none')>{{ __('admin.settings.smtp.none') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_username">{{ __('admin.settings.smtp.username') }}</label>
                                <input type="text" class="form-control text-dark" id="smtp_username" name="smtp[username]" 
                                    value="{{ old('smtp.username', data_get($settings, 'notification_settings.smtp.username')) }}" placeholder="username@gmail.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_password">{{ __('admin.settings.smtp.password') }}</label>
                                <input type="password" class="form-control text-dark" id="smtp_password" name="smtp[password]" 
                                    value="{{ old('smtp.password', data_get($settings, 'notification_settings.smtp.password')) }}" placeholder="xxxx xxxx xxxx xxxx">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_from_email">{{ __('admin.settings.smtp.from_email') }}</label>
                                <input type="email" class="form-control text-dark" id="smtp_from_email" name="smtp[from_email]" 
                                    value="{{ old('smtp.from_email', data_get($settings, 'notification_settings.smtp.from_email')) }}" placeholder="noreply@domain.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_from_name">{{ __('admin.settings.smtp.from_name') }}</label>
                                <input type="text" class="form-control text-dark" id="smtp_from_name" name="smtp[from_name]" 
                                    value="{{ old('smtp.from_name', data_get($settings, 'notification_settings.smtp.from_name')) }}" placeholder="Cửa hàng ABC">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_owner_email">{{ __('admin.settings.smtp.owner_email') }}</label>
                                <input type="email" class="form-control text-dark" id="smtp_owner_email" name="smtp[owner_email]" 
                                    value="{{ old('smtp.owner_email', data_get($settings, 'notification_settings.smtp.owner_email')) }}" placeholder="admin@domain.com">
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
                                window.location.reload();
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
        });
    </script>
@endpush
