@extends('admin.layouts.app')

@section('title', __('admin.email_design.title'))

@php
    $t = fn (string $key, array $replace = []) => __('admin.email_design.'.$key, $replace);
    $canEdit = auth()->user()?->can('settings.update');
    $socialLabels = ['facebook' => 'Facebook', 'zalo' => 'Zalo', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];
    $layoutThumbs = [
        'classic' => '<rect x="0" y="0" width="120" height="84" rx="6" fill="#eef0f3"/><rect x="48" y="8" width="24" height="6" rx="2" fill="currentColor"/><rect x="14" y="20" width="92" height="56" rx="5" fill="#fff" stroke="#e1e4e8"/><rect x="22" y="28" width="50" height="5" rx="2" fill="#1f2937"/><rect x="22" y="38" width="76" height="3" rx="1.5" fill="#c9ced6"/><rect x="22" y="45" width="66" height="3" rx="1.5" fill="#c9ced6"/><rect x="22" y="58" width="30" height="9" rx="3" fill="currentColor"/>',
        'minimal' => '<rect x="0" y="0" width="120" height="84" rx="6" fill="#fff" stroke="#e1e4e8"/><rect x="12" y="9" width="24" height="6" rx="2" fill="currentColor"/><rect x="12" y="19" width="96" height="1.5" fill="currentColor"/><rect x="12" y="28" width="56" height="5" rx="2" fill="#1f2937"/><rect x="12" y="38" width="92" height="3" rx="1.5" fill="#c9ced6"/><rect x="12" y="45" width="80" height="3" rx="1.5" fill="#c9ced6"/><rect x="12" y="58" width="30" height="9" rx="3" fill="currentColor"/>',
        'banner' => '<rect x="0" y="0" width="120" height="84" rx="6" fill="#eef0f3"/><rect x="14" y="6" width="92" height="72" rx="5" fill="#fff" stroke="#e1e4e8"/><path d="M14 11a5 5 0 0 1 5-5h82a5 5 0 0 1 5 5v13H14z" fill="currentColor"/><rect x="48" y="29" width="24" height="6" rx="2" fill="#1f2937"/><rect x="22" y="42" width="76" height="3" rx="1.5" fill="#c9ced6"/><rect x="22" y="49" width="66" height="3" rx="1.5" fill="#c9ced6"/><rect x="22" y="61" width="30" height="9" rx="3" fill="currentColor"/>',
    ];
    $accentSwatches = ['#1f2937', '#c8102e', '#b45309', '#047857', '#1d4ed8', '#7c3aed', '#db2777', '#0f766e'];
@endphp

@push('styles')
    {{-- The font choices, so each one shows in its own face. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?{{ collect(\App\Services\EmailAppearance::FONTS)->pluck('google')->filter()->map(fn ($family) => 'family='.$family.':wght@400;600')->implode('&') }}&display=swap">
    <style>
        .email-layout-option { cursor: pointer; }
        .email-layout-option .card { transition: border-color .15s ease, box-shadow .15s ease; }
        .email-layout-option input:checked + .card { border-color: var(--bs-primary) !important; box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), .15); }
        .email-layout-option input:focus-visible + .card { outline: 2px solid var(--bs-primary); outline-offset: 2px; }
        .email-swatch { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px #d0d5dd; }
        .email-swatch.active { box-shadow: 0 0 0 2px #111827; }
        .email-preview-sticky { position: sticky; top: 88px; }
        .email-preview-stage { background: #e9ebee; border-radius: 0 0 .5rem .5rem; display: flex; justify-content: center; overflow-x: auto; padding: 18px 10px; }
        .email-preview-frame { width: 100%; max-width: 640px; min-height: 640px; border: 0; background: #f3f4f6; border-radius: 6px; transition: width .2s ease; }
        .email-preview-frame.phone { width: 380px; max-width: 380px; border-radius: 22px; box-shadow: 0 0 0 9px #1d232b; }
        .email-var { cursor: pointer; }
        @media (prefers-reduced-motion: reduce) { .email-preview-frame { transition: none; } }
    </style>
@endpush

@section('content')
    <x-admin.page-header :title="$t('title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.settings.index') }}">{{ __('admin.sidebar.settings') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ $t('title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <p class="text-muted mb-4">{{ $t('subtitle') }}</p>

    <form action="{{ route('admin.email-design.update') }}" method="POST" id="emailDesignForm" class="admin-form-with-sticky-actions" data-preview-url="{{ route('admin.email-design.preview') }}">
        @csrf
        <div class="row g-4">
            <div class="col-xl-7">
                {{-- Layout --}}
                <div class="card shadow-sm mb-4" data-tour="email-design.layout">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-3">{{ $t('layout.title') }}</h5>
                        <div class="row g-3">
                            @foreach(\App\Services\EmailAppearance::LAYOUTS as $layout)
                                <div class="col-md-4">
                                    <label class="email-layout-option d-block h-100">
                                        <input type="radio" class="visually-hidden" name="layout" value="{{ $layout }}" id="layout_{{ $layout }}" @checked(old('layout', $settings['layout']) === $layout)>
                                        <span class="card h-100 border mb-0">
                                            <span class="card-body p-3 d-block">
                                                <svg viewBox="0 0 120 84" class="w-100 mb-2" style="color: {{ $settings['accent'] }}" data-layout-thumb aria-hidden="true">{!! $layoutThumbs[$layout] !!}</svg>
                                                <span class="fw-semibold text-dark d-block">{{ $t("layout.{$layout}.name") }}</span>
                                                <span class="text-muted small d-block">{{ $t("layout.{$layout}.desc") }}</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Font, colour and logo --}}
                <div class="card shadow-sm mb-4" data-tour="email-design.brand">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-3">{{ $t('brand.title') }}</h5>

                        <span class="form-label fw-semibold text-dark d-block">{{ $t('brand.font') }}</span>
                        <div class="row g-2 mb-1" role="radiogroup" aria-label="{{ $t('brand.font') }}">
                            @foreach(\App\Services\EmailAppearance::FONTS as $fontKey => $fontMeta)
                                @php($family = $fontMeta['label'] ? "'".$fontMeta['label']."', " : '')
                                <div class="col-sm-6 col-lg-4">
                                    <label class="email-layout-option d-block h-100">
                                        <input type="radio" class="visually-hidden" name="font" value="{{ $fontKey }}" id="font_{{ $fontKey }}" @checked(old('font', $settings['font']) === $fontKey)>
                                        <span class="card h-100 border mb-0">
                                            <span class="card-body p-3 d-block">
                                                <span class="d-block text-dark" style="font-family: {{ $family }}-apple-system, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 17px; font-weight: 600; line-height: 1.3">{{ $t('brand.font_sample_title') }}</span>
                                                <span class="d-block text-muted" style="font-family: {{ $family }}-apple-system, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 13px; line-height: 1.4">{{ $t('brand.font_sample_body') }}</span>
                                                <span class="d-block small mt-1 fw-semibold">{{ $fontMeta['label'] ?? $t('brand.font_system') }}</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text mb-4">{{ $t('brand.font_help') }}</div>

                        <label class="form-label fw-semibold text-dark" for="accent">{{ $t('brand.accent') }}</label>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <input type="color" class="form-control form-control-color" id="accent_picker" value="{{ old('accent', $settings['accent']) }}" aria-label="{{ $t('brand.accent') }}">
                            <input type="text" class="form-control font-monospace @error('accent') is-invalid @enderror" id="accent" name="accent" value="{{ old('accent', $settings['accent']) }}" maxlength="7" style="max-width: 120px">
                            @foreach($accentSwatches as $swatch)
                                <button type="button" class="email-swatch" style="background: {{ $swatch }}" data-swatch="{{ $swatch }}" aria-label="{{ $swatch }}"></button>
                            @endforeach
                        </div>
                        <div class="form-text mb-4">{{ $t('brand.accent_help') }}</div>

                        <label class="form-label fw-semibold text-dark" for="logo_file">{{ $t('brand.logo') }}</label>
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                            <div class="border rounded d-flex align-items-center justify-content-center bg-light" style="width: 160px; height: 64px;">
                                <img src="{{ $settings['logo'] ? \App\Support\MediaUrl::resolve($settings['logo']) : ($look['logo_url'] ?? '') }}" alt="" style="max-width: 140px; max-height: 48px; object-fit: contain;" data-logo-preview @class(['d-none' => ! ($settings['logo'] || $look['logo_url'])])>
                                <span @class(['text-muted small px-2 text-center', 'd-none' => $settings['logo'] || $look['logo_url']]) data-logo-empty >{{ $look['shop'] }}</span>
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <span class="small text-muted" data-logo-state
                                      data-custom="{{ $t('brand.logo_custom') }}" data-shop="{{ $look['logo_url'] ? $t('brand.logo_shop') : $t('brand.logo_none') }}" data-shop-url="{{ $look['logo_url'] }}">
                                    {{ $settings['logo'] ? $t('brand.logo_custom') : ($look['logo_url'] ? $t('brand.logo_shop') : $t('brand.logo_none')) }}
                                </span>
                                <div class="d-flex flex-wrap gap-2">
                                    <label class="btn btn-sm btn-outline-primary mb-0" for="logo_file">{{ $t('brand.logo_choose') }}</label>
                                    <input type="file" class="d-none" id="logo_file" accept="image/*" data-media-folder="settings" data-media-selected-field="logo">
                                    <button type="button" @class(['btn btn-sm bg-secondary-subtle text-secondary', 'd-none' => ! $settings['logo']]) data-logo-clear >{{ $t('brand.logo_clear') }}</button>
                                </div>
                            </div>
                            <input type="hidden" name="logo" id="logo" value="{{ old('logo', $settings['logo'] ? \App\Support\MediaUrl::resolve($settings['logo']) : '') }}">
                        </div>

                        <div class="d-flex align-items-center gap-3 mb-1">
                            <span class="fw-semibold text-dark">{{ $t('brand.logo_align') }}</span>
                            @foreach(\App\Services\EmailAppearance::ALIGNS as $align)
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="logo_align" id="logo_align_{{ $align }}" value="{{ $align }}" @checked(old('logo_align', $settings['logo_align']) === $align)>
                                    <label class="form-check-label" for="logo_align_{{ $align }}">{{ $t('brand.align_'.$align) }}</label>
                                </div>
                            @endforeach
                        </div>

                        <div @class(['mt-4', 'd-none' => old('layout', $settings['layout']) !== 'banner']) data-banner-group>
                            <label class="form-label fw-semibold text-dark" for="banner_file">{{ $t('brand.banner') }}</label>
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <div class="border rounded bg-light overflow-hidden d-flex align-items-center justify-content-center" style="width: 220px; height: 72px;">
                                    <img src="{{ $settings['banner'] ? \App\Support\MediaUrl::resolve($settings['banner']) : '' }}" alt="" @class(['w-100 h-100', 'd-none' => ! $settings['banner']]) style="object-fit: cover" data-banner-preview >
                                    <span @class(['w-100 h-100', 'd-none' => $settings['banner']]) style="background: {{ $settings['accent'] }}" data-banner-band ></span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <label class="btn btn-sm btn-outline-primary mb-0" for="banner_file">{{ $t('brand.banner_choose') }}</label>
                                    <input type="file" class="d-none" id="banner_file" accept="image/*" data-media-folder="banners" data-media-selected-field="banner">
                                    <button type="button" @class(['btn btn-sm bg-secondary-subtle text-secondary', 'd-none' => ! $settings['banner']]) data-banner-clear >{{ $t('brand.banner_clear') }}</button>
                                </div>
                                <input type="hidden" name="banner" id="banner" value="{{ old('banner', $settings['banner'] ? \App\Support\MediaUrl::resolve($settings['banner']) : '') }}">
                            </div>
                            <div class="form-text">{{ $t('brand.banner_help') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="card shadow-sm mb-4" data-tour="email-design.footer">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-1">{{ $t('footer.title') }}</h5>
                        <p class="text-muted small mb-3">{{ $t('footer.help') }}</p>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark" for="signature">{{ $t('footer.signature') }}</label>
                            <textarea class="form-control @error('signature') is-invalid @enderror" id="signature" name="signature" rows="2" maxlength="500" placeholder="{{ $t('footer.signature_placeholder') }}">{{ old('signature', $settings['signature']) }}</textarea>
                        </div>

                        <label class="form-label fw-semibold text-dark">{{ $t('footer.socials') }}</label>
                        <div class="row g-2 mb-1">
                            @foreach(\App\Services\EmailAppearance::SOCIALS as $network)
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="min-width: 92px">{{ $socialLabels[$network] }}</span>
                                        <input type="url" class="form-control @error('socials.'.$network) is-invalid @enderror" id="social_{{ $network }}" name="socials[{{ $network }}]" value="{{ old('socials.'.$network, $settings['socials'][$network] ?? '') }}" placeholder="https://" maxlength="300">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text mb-3">{{ $t('footer.socials_help') }}</div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark" for="return_policy">{{ $t('footer.return_policy') }}</label>
                            <textarea class="form-control" id="return_policy" name="return_policy" rows="2" maxlength="500" placeholder="{{ $t('footer.return_policy_placeholder') }}">{{ old('return_policy', $settings['return_policy']) }}</textarea>
                            <div class="form-text">{{ $t('footer.return_policy_help') }}</div>
                        </div>
                        <div>
                            <label class="form-label fw-semibold text-dark" for="refund_note">{{ $t('footer.refund_note') }}</label>
                            <textarea class="form-control" id="refund_note" name="refund_note" rows="2" maxlength="500" placeholder="{{ $t('footer.refund_note_placeholder') }}">{{ old('refund_note', $settings['refund_note']) }}</textarea>
                            <div class="form-text">{{ $t('footer.refund_note_help') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Each email --}}
                <div class="card shadow-sm mb-4" data-tour="email-design.emails">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-1">{{ $t('emails.title') }}</h5>
                        <p class="text-muted small mb-3">{{ $t('emails.help') }}</p>

                        <div class="accordion" id="emailAccordion">
                            @foreach($emails as $key => $meta)
                                @php($own = $settings['emails'][$key])
                                <div class="accordion-item" data-email-item="{{ $key }}">
                                    <h2 class="accordion-header d-flex align-items-center">
                                        <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#email_{{ $key }}" aria-expanded="false" aria-controls="email_{{ $key }}" data-email-open="{{ $key }}">
                                            <span class="d-flex flex-wrap align-items-center gap-2">
                                                <span class="fw-semibold">{{ $t("emails.names.{$key}") }}</span>
                                                <span class="badge bg-light text-muted fw-normal">{{ $t('emails.audience.'.$meta['audience']) }}</span>
                                                <span class="badge bg-secondary-subtle text-secondary fw-normal {{ old("emails.{$key}.enabled", $own['enabled']) ? 'd-none' : '' }}" data-off-badge>{{ $t('emails.off') }}</span>
                                            </span>
                                        </button>
                                    </h2>
                                    <div id="email_{{ $key }}" class="accordion-collapse collapse" data-bs-parent="#emailAccordion">
                                        <div class="accordion-body">
                                            <div class="form-check form-switch mb-3">
                                                @if($meta['toggle'])
                                                    <input type="hidden" name="emails[{{ $key }}][enabled]" value="0">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="email_{{ $key }}_enabled" name="emails[{{ $key }}][enabled]" value="1" @checked(old("emails.{$key}.enabled", $own['enabled']))>
                                                    <label class="form-check-label fw-semibold" for="email_{{ $key }}_enabled">{{ $t('emails.enabled') }}</label>
                                                @else
                                                    <input class="form-check-input" type="checkbox" role="switch" id="email_{{ $key }}_enabled" checked disabled>
                                                    <label class="form-check-label fw-semibold" for="email_{{ $key }}_enabled">{{ $t('emails.always_on') }}</label>
                                                @endif
                                            </div>

                                            @foreach(\App\Services\EmailAppearance::TEXT_FIELDS as $field)
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-dark" for="email_{{ $key }}_{{ $field }}">{{ $t('emails.'.$field) }}</label>
                                                    @if($field === 'intro')
                                                        <textarea class="form-control @error("emails.{$key}.{$field}") is-invalid @enderror" id="email_{{ $key }}_{{ $field }}" name="emails[{{ $key }}][{{ $field }}]" rows="3" maxlength="1000" placeholder="{{ $defaults[$key][$field] }}" data-var-target>{{ old("emails.{$key}.{$field}", $own[$field]) }}</textarea>
                                                    @else
                                                        <input type="text" class="form-control @error("emails.{$key}.{$field}") is-invalid @enderror" id="email_{{ $key }}_{{ $field }}" name="emails[{{ $key }}][{{ $field }}]" maxlength="150" value="{{ old("emails.{$key}.{$field}", $own[$field]) }}" placeholder="{{ $defaults[$key][$field] }}" data-var-target>
                                                    @endif
                                                </div>
                                            @endforeach

                                            <div class="d-flex flex-wrap align-items-center gap-1 small">
                                                <span class="text-muted me-1">{{ $t('emails.variables') }}</span>
                                                @foreach($meta['vars'] as $var)
                                                    <button type="button" class="btn btn-sm btn-light border py-0 px-2 email-var" data-var="{{ $var }}" title="{{ $t('emails.vars.'.$var) }}"><code>{{ '{'.$var.'}' }}</code> <span class="text-muted">{{ $t('emails.vars.'.$var) }}</span></button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Live preview --}}
            <div class="col-xl-5">
                <div class="card shadow-sm email-preview-sticky" data-tour="email-design.preview">
                    <div class="card-body pb-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <h5 class="fw-semibold mb-0">{{ $t('preview.title') }}</h5>
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ $t('preview.title') }}">
                                <button type="button" class="btn btn-outline-secondary active" data-preview-width="desktop" aria-pressed="true">{{ $t('preview.desktop') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-preview-width="phone" aria-pressed="false">{{ $t('preview.phone') }}</button>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3">
                            <label class="form-label mb-0 small text-muted" for="preview_email">{{ $t('preview.email') }}</label>
                            <select class="form-select form-select-sm" id="preview_email" data-preview-email>
                                @foreach($emails as $key => $meta)
                                    <option value="{{ $key }}">{{ $t("emails.names.{$key}") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="small mt-2 text-muted" data-preview-status aria-live="polite">{{ $t('preview.note') }}</div>
                    </div>
                    <div class="email-preview-stage">
                        <iframe class="email-preview-frame" title="{{ $t('preview.title') }}" data-preview-frame></iframe>
                    </div>
                </div>
            </div>
        </div>

        @if($canEdit)
            @include('admin.shared.form-actions', ['cancelUrl' => route('admin.settings.index')])
        @endif
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('emailDesignForm');
    const frame = form.querySelector('[data-preview-frame]');
    const status = form.querySelector('[data-preview-status]');
    const picker = form.querySelector('[data-preview-email]');
    const texts = { loading: @json($t('preview.loading')), note: @json($t('preview.note')), failed: @json($t('preview.failed')) };
    let timer = null;
    let request = 0;
    let lastTarget = null;

    function fitFrame() {
        try { frame.style.height = Math.max(640, frame.contentDocument.documentElement.scrollHeight + 4) + 'px'; } catch (e) {}
    }
    frame.addEventListener('load', fitFrame);

    // The preview renders the form as it stands; nothing is saved until "Lưu".
    function refresh() {
        const mine = ++request;
        const data = new FormData(form);
        data.set('email', picker.value);
        status.textContent = texts.loading;
        fetch(form.dataset.previewUrl, { method: 'POST', body: data, headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { if (!response.ok) { throw new Error(String(response.status)); } return response.text(); })
            .then(function (html) { if (mine === request) { frame.srcdoc = html; status.textContent = texts.note; } })
            .catch(function () { if (mine === request) { status.textContent = texts.failed; } });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 400); }

    form.addEventListener('input', function (event) { if (!event.target.matches('[data-preview-email]')) { schedule(); } });
    form.addEventListener('change', function (event) { if (!event.target.matches('[data-preview-email]')) { schedule(); } });
    picker.addEventListener('change', refresh);

    // Opening an email's settings shows that email.
    form.querySelectorAll('[data-email-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (picker.value !== button.dataset.emailOpen) { picker.value = button.dataset.emailOpen; refresh(); }
        });
    });

    form.querySelectorAll('[data-preview-width]').forEach(function (button) {
        button.addEventListener('click', function () {
            const phone = button.dataset.previewWidth === 'phone';
            frame.classList.toggle('phone', phone);
            form.querySelectorAll('[data-preview-width]').forEach(function (other) {
                other.classList.toggle('active', other === button);
                other.setAttribute('aria-pressed', String(other === button));
            });
            setTimeout(fitFrame, 250);
        });
    });

    // Colour: picker, hex field and swatches stay in step.
    const accent = form.querySelector('#accent');
    const accentPicker = form.querySelector('#accent_picker');
    function setAccent(value, fromField) {
        if (!/^#[0-9a-fA-F]{6}$/.test(value)) { return; }
        if (!fromField) { accent.value = value; }
        accentPicker.value = value;
        form.querySelectorAll('[data-layout-thumb]').forEach(function (svg) { svg.style.color = value; });
        const band = form.querySelector('[data-banner-band]');
        if (band) { band.style.background = value; }
        form.querySelectorAll('[data-swatch]').forEach(function (s) { s.classList.toggle('active', s.dataset.swatch.toLowerCase() === value.toLowerCase()); });
    }
    accentPicker.addEventListener('input', function () { setAccent(accentPicker.value, false); schedule(); });
    accent.addEventListener('input', function () { setAccent(accent.value, true); });
    form.querySelectorAll('[data-swatch]').forEach(function (swatch) {
        swatch.addEventListener('click', function () { setAccent(swatch.dataset.swatch, false); schedule(); });
    });
    setAccent(accent.value, true);

    // Layout: the cover image only matters for the cover layout.
    form.querySelectorAll('input[name="layout"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            form.querySelector('[data-banner-group]').classList.toggle('d-none', radio.value !== 'banner' || !radio.checked);
        });
    });

    // Logo and cover come from the media library (admin.layouts.media-picker fills the hidden field).
    function wireImage(field, preview, clearButton, onEmpty) {
        const input = form.querySelector('#' + field);
        const image = form.querySelector(preview);
        const clear = form.querySelector(clearButton);
        input.addEventListener('change', function () {
            const has = input.value !== '';
            if (has) { image.src = input.value; }
            image.classList.toggle('d-none', !has && !onEmpty(true));
            clear.classList.toggle('d-none', !has);
            onEmpty(false, has);
        });
        clear.addEventListener('click', function () {
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
    const logoState = form.querySelector('[data-logo-state]');
    wireImage('logo', '[data-logo-preview]', '[data-logo-clear]', function (probe, has) {
        const shopUrl = logoState.dataset.shopUrl;
        if (probe) { return shopUrl !== ''; }
        const image = form.querySelector('[data-logo-preview]');
        if (!has && shopUrl) { image.src = shopUrl; image.classList.remove('d-none'); }
        form.querySelector('[data-logo-empty]').classList.toggle('d-none', has || shopUrl !== '');
        logoState.textContent = has ? logoState.dataset.custom : logoState.dataset.shop;
    });
    wireImage('banner', '[data-banner-preview]', '[data-banner-clear]', function (probe, has) {
        if (probe) { return false; }
        form.querySelector('[data-banner-band]').classList.toggle('d-none', has);
    });

    // Switching an email off shows it in its row.
    form.querySelectorAll('[data-email-item] input[type="checkbox"][name$="[enabled]"]').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            toggle.closest('[data-email-item]').querySelector('[data-off-badge]').classList.toggle('d-none', toggle.checked);
        });
    });

    // Variables: inserted where the cursor was, in the last text field of that email.
    form.addEventListener('focusin', function (event) { if (event.target.matches('[data-var-target]')) { lastTarget = event.target; } });
    form.querySelectorAll('[data-var]').forEach(function (button) {
        button.addEventListener('click', function () {
            const item = button.closest('[data-email-item]');
            const target = lastTarget && item.contains(lastTarget) ? lastTarget : item.querySelector('[data-var-target]');
            const token = '{' + button.dataset.var + '}';
            const start = target.selectionStart ?? target.value.length;
            const end = target.selectionEnd ?? target.value.length;
            target.value = target.value.slice(0, start) + token + target.value.slice(end);
            target.focus();
            target.setSelectionRange(start + token.length, start + token.length);
            target.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    refresh();
})();
</script>
@endpush
