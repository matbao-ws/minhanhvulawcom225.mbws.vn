@extends('admin.layouts.app')

@section('title', __('admin.notification_settings.title'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.notification_settings.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}">{{ __('admin.sidebar.settings') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.notification_settings.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Settings Form -->
    <form action="{{ route('admin.notification-settings.update') }}" method="POST" id="notificationSettingsForm" class="admin-form-with-sticky-actions">
        @csrf

        <div class="row">
            <!-- Left Column: Zalo Settings -->
            <div class="col-lg-6">
                <!-- Zalo OA Configuration -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary-subtle p-2 rounded text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <iconify-icon icon="solar:chat-round-line-line-duotone" class="fs-5"></iconify-icon>
                                </div>
                                <div>
                                    <h5 class="fw-semibold text-dark mb-0">{{ __('admin.notification_settings.zalo_oa.title') }}</h5>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="zalo_oa[enabled]" value="1" id="zalo_oa_enabled" 
                                    @checked(data_get($settings, 'zalo_oa.enabled', false))>
                            </div>
                        </div>

                        <p class="text-muted small mb-4">{{ __('admin.notification_settings.zalo_oa.help') }}</p>

                        <div class="row zalo-oa-fields">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="zalo_oa_app_id">{{ __('admin.notification_settings.zalo_oa.app_id') }}</label>
                                <input type="text" class="form-control text-dark" id="zalo_oa_app_id" name="zalo_oa[app_id]" 
                                    value="{{ data_get($settings, 'zalo_oa.app_id') }}" placeholder="{{ __('admin.notification_settings.placeholder_enter', ['field' => __('admin.notification_settings.zalo_oa.app_id')]) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="zalo_oa_template_id">{{ __('admin.notification_settings.zalo_oa.template_id') }}</label>
                                <input type="text" class="form-control text-dark" id="zalo_oa_template_id" name="zalo_oa[template_id]" 
                                    value="{{ data_get($settings, 'zalo_oa.template_id') }}" placeholder="{{ __('admin.notification_settings.placeholder_enter', ['field' => __('admin.notification_settings.zalo_oa.template_id')]) }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="zalo_oa_secret_key">{{ __('admin.notification_settings.zalo_oa.secret_key') }}</label>
                                <input type="password" class="form-control text-dark" id="zalo_oa_secret_key" name="zalo_oa[secret_key]" 
                                    value="{{ data_get($settings, 'zalo_oa.secret_key') }}" placeholder="{{ __('admin.notification_settings.placeholder_enter', ['field' => __('admin.notification_settings.zalo_oa.secret_key')]) }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="zalo_oa_access_token">{{ __('admin.notification_settings.zalo_oa.access_token') }}</label>
                                <textarea class="form-control text-dark" id="zalo_oa_access_token" name="zalo_oa[access_token]" rows="2" 
                                    placeholder="{{ __('admin.notification_settings.placeholder_enter', ['field' => __('admin.notification_settings.zalo_oa.access_token')]) }}">{{ data_get($settings, 'zalo_oa.access_token') }}</textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="zalo_oa_refresh_token">{{ __('admin.notification_settings.zalo_oa.refresh_token') }}</label>
                                <textarea class="form-control text-darkThông báo qua Zalo Cá Nhân" id="zalo_oa_refresh_token" name="zalo_oa[refresh_token]" rows="2" 
                                    placeholder="{{ __('admin.notification_settings.placeholder_enter', ['field' => __('admin.notification_settings.zalo_oa.refresh_token')]) }}">{{ data_get($settings, 'zalo_oa.refresh_token') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Zalo Personal Configuration -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-success-subtle p-2 rounded text-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <iconify-icon icon="solar:phone-calling-line-duotone" class="fs-5"></iconify-icon>
                                </div>
                                <div>
                                    <h5 class="fw-semibold text-dark mb-0">{{ __('admin.notification_settings.zalo_personal.title') }}</h5>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="zalo_personal[enabled]" value="1" id="zalo_personal_enabled" 
                                    @checked(data_get($settings, 'zalo_personal.enabled', false))>
                            </div>
                        </div>

                        <p class="text-muted small mb-4">{{ __('admin.notification_settings.zalo_personal.help') }}</p>

                        <div class="row zalo-personal-fields">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark" for="zalo_personal_bot_token">{{ __('admin.notification_settings.zalo_personal.bot_token') }}</label>
                                    <input type="text" class="form-control text-dark" id="zalo_personal_bot_token" name="zalo_personal[bot_token]" 
                                        value="{{ data_get($settings, 'zalo_personal.bot_token') }}" placeholder="{{ __('admin.notification_settings.zalo_personal.bot_token_placeholder') }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark" for="zalo_personal_chat_id">{{ __('admin.notification_settings.zalo_personal.chat_id') }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control text-dark" id="zalo_personal_chat_id" name="zalo_personal[chat_id]" 
                                            value="{{ data_get($settings, 'zalo_personal.chat_id') }}" placeholder="{{ __('admin.notification_settings.zalo_personal.chat_id_placeholder') }}">
                                        <button class="btn btn-outline-primary" type="button" id="btn_get_zalo_chat_id">
                                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="spinner_get_zalo_chat_id"></span>
                                            <iconify-icon icon="solar:refresh-line-duotone" class="align-middle me-1" id="icon_get_zalo_chat_id"></iconify-icon>
                                            <span id="text_get_zalo_chat_id">{{ __('admin.notification_settings.zalo_personal.get_chat_id') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center border-start ps-3 d-flex flex-column align-items-center justify-content-center">
                                <p class="small text-muted mb-2 fw-semibold text-center">{{ __('admin.notification_settings.zalo_personal.qr_code_help') }}</p>
                                <div class="" style="max-width: 180px;">
                                    <img src="https://bot.zapps.me/images/zbot-creator_qrcode.jpg" alt="Zalo Bot QR Code" class="img-fluid rounded">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: SMTP & Dashboard Settings -->
            <div class="col-lg-6">
                <!-- SMTP Email Configuration -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-info-subtle p-2 rounded text-info d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <iconify-icon icon="solar:letter-line-duotone" class="fs-5"></iconify-icon>
                                </div>
                                <div>
                                    <h5 class="fw-semibold text-dark mb-0">{{ __('admin.notification_settings.smtp.title') }}</h5>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="smtp[enabled]" value="1" id="smtp_enabled" 
                                    @checked(data_get($settings, 'smtp.enabled', false))>
                            </div>
                        </div>

                        <p class="text-muted small mb-4">{{ __('admin.notification_settings.smtp.help') }}</p>

                        <div class="row smtp-fields">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_username">{{ __('admin.notification_settings.smtp.username_gmail') }}</label>
                                <input type="text" class="form-control text-dark" id="smtp_username" name="smtp[username]" 
                                    value="{{ data_get($settings, 'smtp.username') }}" placeholder="username@gmail.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_password">{{ __('admin.notification_settings.smtp.password_app') }}</label>
                                <input type="password" class="form-control text-dark" id="smtp_password" name="smtp[password]" 
                                    value="{{ data_get($settings, 'smtp.password') }}" placeholder="xxxx xxxx xxxx xxxx">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-semibold text-dark" for="smtp_owner_email">{{ __('admin.notification_settings.smtp.owner_email_system') }}</label>
                                <input type="email" class="form-control text-dark" id="smtp_owner_email" name="smtp[owner_email]" 
                                    value="{{ data_get($settings, 'smtp.owner_email') }}" placeholder="{{ __('admin.notification_settings.smtp.owner_email_placeholder') }}">
                            </div>

                            <!-- Advanced Config Toggle -->
                            <div class="col-12 mb-2">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="small text-muted">{{ __('admin.notification_settings.smtp.gmail_hint') }}</span>
                                    <button class="btn btn-link btn-sm text-primary p-0 d-flex align-items-center gap-1 text-decoration-none fw-semibold" 
                                        type="button" data-bs-toggle="collapse" data-bs-target="#advancedSmtp" aria-expanded="false" aria-controls="advancedSmtp">
                                        <iconify-icon icon="solar:settings-bold-duotone" class="fs-4"></iconify-icon>
                                        {{ __('admin.notification_settings.smtp.advanced_config') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Collapsible Technical Fields -->
                            <div class="col-12">
                                <div class="collapse" id="advancedSmtp">
                                    <div class="p-3 bg-light rounded-3 border mb-3 mt-2 row g-3">
                                        <div class="col-md-8 mb-0">
                                            <label class="form-label fw-semibold text-dark" for="smtp_host">{{ __('admin.notification_settings.smtp.host') }}</label>
                                            <input type="text" class="form-control text-dark" id="smtp_host" name="smtp[host]" 
                                                value="{{ data_get($settings, 'smtp.host', 'smtp.gmail.com') }}" placeholder="smtp.gmail.com...">
                                        </div>
                                        <div class="col-md-4 mb-0">
                                            <label class="form-label fw-semibold text-dark" for="smtp_port">{{ __('admin.notification_settings.smtp.port') }}</label>
                                            <input type="number" class="form-control text-dark" id="smtp_port" name="smtp[port]" 
                                                value="{{ data_get($settings, 'smtp.port', '465') }}" placeholder="465 / 587">
                                        </div>
                                        <div class="col-md-12 mb-0">
                                            <label class="form-label fw-semibold text-dark" for="smtp_encryption">{{ __('admin.notification_settings.smtp.encryption') }}</label>
                                            <select name="smtp[encryption]" id="smtp_encryption" class="form-select text-dark">
                                                <option value="ssl" @selected(data_get($settings, 'smtp.encryption', 'ssl') === 'ssl')>SSL (Port 465)</option>
                                                <option value="tls" @selected(data_get($settings, 'smtp.encryption', 'ssl') === 'tls')>TLS (Port 587)</option>
                                                <option value="none" @selected(data_get($settings, 'smtp.encryption', 'ssl') === 'none')>None (Port 25)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-0">
                                            <label class="form-label fw-semibold text-dark" for="smtp_from_email">{{ __('admin.notification_settings.smtp.from_email') }}</label>
                                            <input type="email" class="form-control text-dark" id="smtp_from_email" name="smtp[from_email]" 
                                                value="{{ data_get($settings, 'smtp.from_email') }}" placeholder="noreply@domain.com">
                                        </div>
                                        <div class="col-md-6 mb-0">
                                            <label class="form-label fw-semibold text-dark" for="smtp_from_name">{{ __('admin.notification_settings.smtp.from_name') }}</label>
                                            <input type="text" class="form-control text-dark" id="smtp_from_name" name="smtp[from_name]" 
                                                value="{{ data_get($settings, 'smtp.from_name', 'Cửa hàng') }}" placeholder="{{ __('admin.notification_settings.placeholder_enter', ['field' => __('admin.notification_settings.smtp.from_name')]) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Configuration -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-warning-subtle p-2 rounded text-warning d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <iconify-icon icon="solar:bell-bing-line-duotone" class="fs-5"></iconify-icon>
                                </div>
                                <div>
                                    <h5 class="fw-semibold text-dark mb-0">{{ __('admin.notification_settings.dashboard.title') }}</h5>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="dashboard[enabled]" value="1" id="dashboard_enabled" 
                                    @checked(data_get($settings, 'dashboard.enabled', true))>
                            </div>
                        </div>

                        <p class="text-muted small mb-4">{{ __('admin.notification_settings.dashboard.help') }}</p>

                        <div class="row dashboard-fields">
                            <div class="col-12 mb-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <label class="form-label fw-semibold text-dark mb-0" for="dashboard_play_sound">{{ __('admin.notification_settings.dashboard.play_sound') }}</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <button class="btn btn-xs btn-outline-warning d-flex align-items-center gap-1 py-1 px-2" type="button" onclick="playTestSound()">
                                            <i class="ti ti-volume fs-3"></i>
                                        </button>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="dashboard[play_sound]" value="1" id="dashboard_play_sound" 
                                                @checked(data_get($settings, 'dashboard.play_sound', true))>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <label class="form-label fw-semibold text-dark mb-0" for="dashboard_auto_refresh">{{ __('admin.notification_settings.dashboard.auto_refresh') }}</label>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="dashboard[auto_refresh]" value="1" id="dashboard_auto_refresh" 
                                            @checked(data_get($settings, 'dashboard.auto_refresh', true))>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Hidden Audio element for Sound Testing -->
    <audio id="alertSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-84.wav" preload="auto"></audio>
@endsection

@push('scripts')
<script>
    // Auto-unlock audio element on first user interaction
    function unlockAudio() {
        const audio = document.getElementById('alertSound');
        if (audio) {
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
            }).catch(e => {
                console.log('Audio auto-unlock deferred:', e);
            });
        }
        // Remove listeners after first trigger
        ['click', 'touchstart', 'keydown'].forEach(event => {
            document.removeEventListener(event, unlockAudio);
        });
    }

    ['click', 'touchstart', 'keydown'].forEach(event => {
        document.addEventListener(event, unlockAudio, { passive: true });
    });

    function playTestSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                playFallbackAudio();
                return;
            }
            
            const ctx = new AudioContext();
            
            // Ding-Dong sound synthesis
            // Ding (High note)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            gain1.gain.setValueAtTime(0, ctx.currentTime);
            gain1.gain.linearRampToValueAtTime(0.15, ctx.currentTime + 0.05);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.65);
            
            // Dong (Lower note, delayed)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(440.00, ctx.currentTime + 0.15); // A4
            gain2.gain.setValueAtTime(0, ctx.currentTime + 0.15);
            gain2.gain.linearRampToValueAtTime(0.12, ctx.currentTime + 0.2);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.85);
            osc2.start(ctx.currentTime + 0.15);
            osc2.stop(ctx.currentTime + 0.9);
            
        } catch (error) {
            console.error('Web Audio play failed, trying fallback:', error);
            playFallbackAudio();
        }
    }

    function playFallbackAudio() {
        const audio = document.getElementById('alertSound');
        if (audio) {
            audio.currentTime = 0;
            audio.play().catch(error => {
                console.error('Fallback audio play failed:', error);
                Swal.fire({
                    icon: 'info',
                    title: "{{ __('admin.notification_settings.sound_test_fail_title') }}",
                    text: "{{ __('admin.notification_settings.sound_test_fail_msg') }}"
                });
            });
        }
    }

    // Client-side channel fields validation config
    const channelFields = {
        'zalo_oa_enabled': [
            { id: 'zalo_oa_app_id', name: "{{ __('admin.notification_settings.zalo_oa.app_id') }}" },
            { id: 'zalo_oa_template_id', name: "{{ __('admin.notification_settings.zalo_oa.template_id') }}" },
            { id: 'zalo_oa_secret_key', name: "{{ __('admin.notification_settings.zalo_oa.secret_key') }}" },
            { id: 'zalo_oa_access_token', name: "{{ __('admin.notification_settings.zalo_oa.access_token') }}" },
            { id: 'zalo_oa_refresh_token', name: "{{ __('admin.notification_settings.zalo_oa.refresh_token') }}" }
        ],
        'zalo_personal_enabled': [
            { id: 'zalo_personal_bot_token', name: "{{ __('admin.notification_settings.zalo_personal.bot_token') }}" },
            { id: 'zalo_personal_chat_id', name: "{{ __('admin.notification_settings.zalo_personal.chat_id') }}" }
        ],
        'smtp_enabled': [
            { id: 'smtp_username', name: "{{ __('admin.notification_settings.smtp.username_gmail') }}" },
            { id: 'smtp_password', name: "{{ __('admin.notification_settings.smtp.password_app') }}" },
            { id: 'smtp_owner_email', name: "{{ __('admin.notification_settings.smtp.owner_email_system') }}" }
        ]
    };

    // Enforce sufficient details when toggling switches ON
    Object.keys(channelFields).forEach(switchId => {
        const toggle = document.getElementById(switchId);
        if (!toggle) return;

        toggle.addEventListener('change', function () {
            if (this.checked) {
                // Subscription check for Zalo channels
                const hasActiveSubscription = @json($hasActiveSubscription ?? true);
                if ((switchId === 'zalo_oa_enabled' || switchId === 'zalo_personal_enabled') && !hasActiveSubscription) {
                    this.checked = false;
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi / Error',
                        text: "{{ __('admin.notification_settings.no_package') }}",
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                const emptyFields = [];
                let firstEmptyInput = null;

                channelFields[switchId].forEach(field => {
                    const input = document.getElementById(field.id);
                    if (input && !input.value.trim()) {
                        emptyFields.push(field.name);
                        if (!firstEmptyInput) {
                            firstEmptyInput = input;
                        }
                    }
                });

                if (emptyFields.length > 0) {
                    this.checked = false;
                    
                    Swal.fire({
                        icon: 'warning',
                        title: "{{ __('admin.notification_settings.validation_title') }}",
                        text: "{{ __('admin.notification_settings.validation_msg', ['fields' => '_FIELDS_']) }}".replace('_FIELDS_', emptyFields.join(', ')),
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (firstEmptyInput) {
                            firstEmptyInput.focus();
                            firstEmptyInput.classList.add('is-invalid');
                            
                            // Remove red outline once user starts typing
                            firstEmptyInput.addEventListener('input', function removeInvalid() {
                                this.classList.remove('is-invalid');
                                this.removeEventListener('input', removeInvalid);
                            });
                        }
                    });
                    return;
                }
            }
            // Auto-save on valid change or disable
            autoSaveSettings();
        });
    });

    // Helper to validate the entire form (checks active channels have required fields)
    function validateForm() {
        let isValid = true;
        for (const switchId of Object.keys(channelFields)) {
            const toggle = document.getElementById(switchId);
            if (toggle && toggle.checked) {
                const emptyFields = [];
                channelFields[switchId].forEach(field => {
                    const input = document.getElementById(field.id);
                    if (input && !input.value.trim()) {
                        emptyFields.push(field.name);
                    }
                });

                if (emptyFields.length > 0) {
                    isValid = false;
                }
            }
        }
        return isValid;
    }

    // AJAX auto-save settings helper
    function autoSaveSettings() {
        if (!validateForm()) {
            return;
        }

        const form = document.getElementById('notificationSettingsForm');
        const actionUrl = form.getAttribute('action');
        const formData = new FormData(form);

        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => {
            return response.json().then(data => {
                return { ok: response.ok, data };
            }).catch(() => {
                return { ok: response.ok, data: null };
            });
        })
        .then(({ ok, data }) => {
            if (ok && data && data.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500,
                    icon: 'success',
                    title: data.message || "{{ __('admin.notification_settings.save_success') }}"
                });
            } else {
                const errMsg = (data && data.message) || 'Không thể lưu cấu hình. Vui lòng thử lại.';
                console.error('Auto-save failed:', errMsg);
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi / Error',
                    text: errMsg,
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.reload();
                });
            }
        })
        .catch(error => {
            console.error('Auto-save connection error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Lỗi kết nối / Connection Error',
                text: 'Không thể kết nối đến máy chủ. Vui lòng thử lại.',
                confirmButtonText: 'OK'
            });
        });
    }

    // Submit listener on form (e.g. if user hits Enter in an input field)
    document.getElementById('notificationSettingsForm').addEventListener('submit', function (e) {
        e.preventDefault();
        autoSaveSettings();
    });

    // Auto-save when input changes or blurs
    document.querySelectorAll('#notificationSettingsForm input:not([type="checkbox"]), #notificationSettingsForm textarea, #notificationSettingsForm select').forEach(input => {
        input.addEventListener('change', function () {
            autoSaveSettings();
        });
    });

    // Auto get Zalo Chat ID
    const btnGetZaloChatId = document.getElementById('btn_get_zalo_chat_id');
    if (btnGetZaloChatId) {
        btnGetZaloChatId.addEventListener('click', function () {
            const botTokenInput = document.getElementById('zalo_personal_bot_token');
            const botToken = botTokenInput ? botTokenInput.value.trim() : '';

            if (!botToken) {
                Swal.fire({
                    icon: 'warning',
                    title: "{{ __('admin.notification_settings.validation_title') }}",
                    text: "{{ __('admin.notification_settings.zalo_personal.token_required') }}"
                });
                if (botTokenInput) {
                    botTokenInput.classList.add('is-invalid');
                    botTokenInput.focus();
                }
                return;
            }

            // Set loading state
            const spinner = document.getElementById('spinner_get_zalo_chat_id');
            const icon = document.getElementById('icon_get_zalo_chat_id');
            const text = document.getElementById('text_get_zalo_chat_id');

            if (spinner) spinner.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');
            if (text) text.textContent = "{{ __('admin.notification_settings.zalo_personal.get_chat_id_loading') }}";
            btnGetZaloChatId.disabled = true;

            fetch("{{ route('admin.notification-settings.get-chat-id') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    bot_token: botToken
                })
            })
            .then(response => {
                return response.json().then(data => {
                    return { ok: response.ok, data };
                });
            })
            .then(({ ok, data }) => {
                // Reset button state
                if (spinner) spinner.classList.add('d-none');
                if (icon) icon.classList.remove('d-none');
                if (text) text.textContent = "{{ __('admin.notification_settings.zalo_personal.get_chat_id') }}";
                btnGetZaloChatId.disabled = false;

                if (!ok || !data.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi / Error',
                        text: data.message || "{{ __('admin.notification_settings.zalo_personal.get_chat_id_error') }}"
                    });
                    return;
                }

                if (!data.chats || data.chats.length === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Thông báo / Info',
                        text: data.message || "{{ __('admin.notification_settings.zalo_personal.get_chat_id_empty') }}"
                    });
                    return;
                }

                // Multiple/Single chats found, display selection modal
                let selectHtml = `<p class="mb-3 text-start">{{ __('admin.notification_settings.zalo_personal.get_chat_id_desc') }}</p>`;
                selectHtml += `<select id="swal_chat_id_select" class="form-select text-dark mb-3">`;
                data.chats.forEach(chat => {
                    selectHtml += `<option value="${chat.chat_id}">${chat.display_name} (Chat ID: ${chat.chat_id})</option>`;
                });
                selectHtml += `</select>`;

                Swal.fire({
                    title: "{{ __('admin.notification_settings.zalo_personal.get_chat_id_title') }}",
                    html: selectHtml,
                    showCancelButton: true,
                    confirmButtonText: 'Chọn / Select',
                    cancelButtonText: 'Hủy / Cancel',
                    preConfirm: () => {
                        return document.getElementById('swal_chat_id_select').value;
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        const chatIdInput = document.getElementById('zalo_personal_chat_id');
                        if (chatIdInput) {
                            chatIdInput.value = result.value;
                            chatIdInput.classList.remove('is-invalid');
                            autoSaveSettings();
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error:', error);
                // Reset button state
                if (spinner) spinner.classList.add('d-none');
                if (icon) icon.classList.remove('d-none');
                if (text) text.textContent = "{{ __('admin.notification_settings.zalo_personal.get_chat_id') }}";
                btnGetZaloChatId.disabled = false;

                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi kết nối / Connection Error',
                    text: 'Không thể kết nối đến máy chủ. Vui lòng thử lại.'
                });
            });
        });

        // Clear is-invalid style when user types and auto-retrieve on change
        const botTokenInput = document.getElementById('zalo_personal_bot_token');
        if (botTokenInput) {
            botTokenInput.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            botTokenInput.addEventListener('change', function() {
                checkAndAutoRetrieveChatId();
            });
        }
    }

    function checkAndAutoRetrieveChatId() {
        const botTokenInput = document.getElementById('zalo_personal_bot_token');
        const chatIdInput = document.getElementById('zalo_personal_chat_id');
        if (!botTokenInput || !chatIdInput) return;

        const botToken = botTokenInput.value.trim();
        const chatId = chatIdInput.value.trim();

        if (botToken && !chatId) {
            fetch("{{ route('admin.notification-settings.get-chat-id') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    bot_token: botToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.chats && data.chats.length > 0) {
                    if (data.chats.length === 1) {
                        chatIdInput.value = data.chats[0].chat_id;
                        chatIdInput.classList.remove('is-invalid');
                        
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3500,
                            icon: 'success',
                            title: `Tự động nhận diện Chat ID: ${data.chats[0].display_name}`
                        });

                        autoSaveSettings();
                    } else {
                        let selectHtml = `<p class="mb-3 text-start">{{ __('admin.notification_settings.zalo_personal.get_chat_id_desc') }}</p>`;
                        selectHtml += `<select id="swal_chat_id_select" class="form-select text-dark mb-3">`;
                        data.chats.forEach(chat => {
                            selectHtml += `<option value="${chat.chat_id}">${chat.display_name} (Chat ID: ${chat.chat_id})</option>`;
                        });
                        selectHtml += `</select>`;

                        Swal.fire({
                            title: "{{ __('admin.notification_settings.zalo_personal.get_chat_id_title') }}",
                            html: selectHtml,
                            showCancelButton: true,
                            confirmButtonText: 'Chọn / Select',
                            cancelButtonText: 'Hủy / Cancel',
                            preConfirm: () => {
                                return document.getElementById('swal_chat_id_select').value;
                            }
                        }).then((result) => {
                            if (result.isConfirmed && result.value) {
                                chatIdInput.value = result.value;
                                chatIdInput.classList.remove('is-invalid');
                                autoSaveSettings();
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Background auto-retrieval error:', error);
            });
        }
    }

    // Trigger auto-retrieval on load
    setTimeout(checkAndAutoRetrieveChatId, 500);

    // Auto-sync smtp_username with smtp_from_email for convenience
    const usernameInput = document.getElementById('smtp_username');
    const fromEmailInput = document.getElementById('smtp_from_email');
    if (usernameInput && fromEmailInput) {
        if (!fromEmailInput.value) {
            fromEmailInput.value = usernameInput.value;
        }
        usernameInput.addEventListener('input', function () {
            if (!fromEmailInput.dataset.manual) {
                fromEmailInput.value = this.value;
            }
        });
        fromEmailInput.addEventListener('input', function () {
            this.dataset.manual = 'true';
        });
    }
</script>
@endpush
