@extends('admin.layouts.app')

@section('title', __('admin.settings.title'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.settings.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.settings.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link d-flex active align-items-center gap-2 py-3" data-bs-toggle="tab" href="#general-pane" role="tab">
                <span><i class="ti ti-info-circle fs-4"></i></span>
                <span class="d-none d-md-block ms-1">{{ __('admin.settings.tabs.general') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2 py-3" data-bs-toggle="tab" href="#contact-pane" role="tab">
                <span><i class="ti ti-phone fs-4"></i></span>
                <span class="d-none d-md-block ms-1">{{ __('admin.settings.tabs.contact') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2 py-3" data-bs-toggle="tab" href="#social-pane" role="tab">
                <span><i class="ti ti-share fs-4"></i></span>
                <span class="d-none d-md-block ms-1">{{ __('admin.settings.tabs.social') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2 py-3" data-bs-toggle="tab" href="#seo-pane" role="tab">
                <span><i class="ti ti-search fs-4"></i></span>
                <span class="d-none d-md-block ms-1">{{ __('admin.settings.tabs.seo') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2 py-3" data-bs-toggle="tab" href="#theme-pane" role="tab">
                <span><i class="ti ti-palette fs-4"></i></span>
                <span class="d-none d-md-block ms-1">{{ __('admin.settings.tabs.theme') }}</span>
            </a>
        </li>
    </ul>

    <!-- Form -->
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" id="settingsForm">
        @csrf
        <div class="tab-content" id="settingTabsContent">
            <!-- General Pane -->
            <div class="tab-pane fade show active" id="general-pane" role="tabpanel" aria-labelledby="general-tab">
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
                    </div>
                </div>
            </div>

            <!-- Contact Pane -->
            <div class="tab-pane fade" id="contact-pane" role="tabpanel" aria-labelledby="contact-tab">
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
                                <textarea class="form-control text-dark" id="contact_address" name="contact[address]" rows="4">{{ old('contact.address', $contact['address'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Pane -->
            <div class="tab-pane fade" id="social-pane" role="tabpanel" aria-labelledby="social-tab">
                <div class="card">
                    <div class="card-body">
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

            <!-- SEO Pane -->
            <div class="tab-pane fade" id="seo-pane" role="tabpanel" aria-labelledby="seo-tab">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.seo.title') }}</h5>
                        @php
                            $seo = $settings->get('seo') ?? [];
                        @endphp
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="seo_title">{{ __('admin.settings.seo.meta_title') }}</label>
                                <input type="text" class="form-control text-dark" id="seo_title" name="seo[title]" 
                                    value="{{ old('seo.title', $seo['title'] ?? '') }}" placeholder="{{ __('admin.settings.seo.meta_title_placeholder') }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="seo_description">{{ __('admin.settings.seo.meta_desc') }}</label>
                                <textarea class="form-control text-dark" id="seo_description" name="seo[description]" rows="4" 
                                    placeholder="{{ __('admin.settings.seo.meta_desc_placeholder') }}">{{ old('seo.description', $seo['description'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Theme Pane -->
            <div class="tab-pane fade" id="theme-pane" role="tabpanel" aria-labelledby="theme-tab">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4 text-dark">{{ __('admin.settings.theme.title') }}</h5>
                        @php
                            $theme = $settings->get('theme') ?? [];
                        @endphp
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="theme_color">{{ __('admin.settings.theme.primary_color') }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color border" id="theme_color" name="theme[primary_color]" 
                                        value="{{ old('theme.primary_color', $theme['primary_color'] ?? '#0d6efd') }}" title="{{ __('admin.settings.theme.color_title') }}">
                                    <span class="text-dark small font-monospace fw-semibold">{{ $theme['primary_color'] ?? '#0d6efd' }}</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="theme_layout">{{ __('admin.settings.theme.layout') }}</label>
                                <select class="form-select text-dark" id="theme_layout" name="theme[layout]">
                                    <option value="default" @selected(old('theme.layout', $theme['layout'] ?? 'default') === 'default')>{{ __('admin.settings.theme.layout_default') }}</option>
                                    <option value="compact" @selected(old('theme.layout', $theme['layout'] ?? 'default') === 'compact')>{{ __('admin.settings.theme.layout_compact') }}</option>
                                    <option value="boxed" @selected(old('theme.layout', $theme['layout'] ?? 'default') === 'boxed')>{{ __('admin.settings.theme.layout_boxed') }}</option>
                                </select>
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

        function generateWebhookToken() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let token = '';
            for (let i = 0; i < 32; i++) {
                token += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('ghtk_webhook_token').value = token;
        }

        function copyWebhookUrl() {
            const copyText = document.getElementById('ghtk_webhook_url');
            if (copyText) {
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(copyText.value).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: '{{ __('admin.settings.shipping.ghtk.copied') }}',
                        text: '{{ __('admin.settings.shipping.ghtk.copied_msg') }}',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            }
        }

    </script>
@endpush
