@extends('admin.layouts.app')

@section('title', __('admin.payment_methods.config_shipping_integration'))

@section('content')
    <!-- Header Card -->
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.payment_methods.setup_connection') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.payment-methods.index') }}">{{ __('admin.payment_methods.title') }}</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.payment_methods.setup_connection') }}: {{ $method->name }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Card -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-4 pb-2 border-bottom">
                <div class="bg-light p-2 rounded">
                    <iconify-icon icon="solar:card-recive-line-duotone" class="fs-6 text-primary"></iconify-icon>
                </div>
                <div>
                    <h5 class="fw-semibold text-dark mb-0">{{ __('admin.payment_methods.setup_partner_config', ['name' => $method->name]) }}</h5>
                    <p class="text-muted small mb-0">{{ __('admin.payment_methods.connection_code') }}: {{ $method->method_code }} | {{ __('admin.payment_methods.type') }}: {{ $method->type === 'connected' ? __('admin.payment_methods.api_connected') : __('admin.payment_methods.self_delivery') }}</p>
                </div>
            </div>

            <form action="{{ route('admin.payment-methods.update-settings', $method) }}" method="POST">
                @csrf
                
                @if($method->method_code === 'stripe')
                    @php
                        $publishableKey = data_get($method->settings, 'publishable_key', '');
                        $secretKey = data_get($method->settings, 'secret_key', '');
                        $webhookSecret = data_get($method->settings, 'webhook_secret', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="publishable_key">Publishable Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="publishable_key" name="publishable_key" 
                                value="{{ old('publishable_key', $publishableKey) }}" placeholder="pk_live_... / pk_test_..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="secret_key">Secret Key <span class="text-danger">*</span></label>
                            <input type="password" class="form-control text-dark" id="secret_key" name="secret_key" 
                                value="{{ old('secret_key', $secretKey) }}" placeholder="sk_live_... / sk_test_..." required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="webhook_secret">Webhook Signing Secret</label>
                            <input type="text" class="form-control text-dark" id="webhook_secret" name="webhook_secret" 
                                value="{{ old('webhook_secret', $webhookSecret) }}" placeholder="whsec_...">
                        </div>
                    </div>
                @elseif($method->method_code === 'sepay')
                    @php
                        $bankCode = data_get($method->settings, 'bank_code', '');
                        $accountNumber = data_get($method->settings, 'account_number', '');
                        $accountHolder = data_get($method->settings, 'account_holder', '');
                        $apiKey = data_get($method->settings, 'api_key', '');
                        $webhookToken = data_get($method->settings, 'webhook_token', '');
                        $webhookSecret = data_get($method->settings, 'webhook_secret', '');
                        $paymentPrefix = data_get($method->settings, 'payment_prefix', '');
                        $qrTemplate = data_get($method->settings, 'qr_template', 'compact');
                        $instructions = data_get($method->settings, 'instructions', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="bank_code">{{ __('admin.payment_methods.sepay_bank_code') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="bank_code" name="bank_code"
                                value="{{ old('bank_code', $bankCode) }}" placeholder="Vietcombank, MBBank, ACB..." required>
                            <div class="form-text text-muted">{{ __('admin.payment_methods.sepay_bank_code_hint') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="account_number">{{ __('admin.payment_methods.sepay_account_number') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="account_number" name="account_number"
                                value="{{ old('account_number', $accountNumber) }}" placeholder="0123456789" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="account_holder">{{ __('admin.payment_methods.sepay_account_holder') }}</label>
                            <input type="text" class="form-control text-dark" id="account_holder" name="account_holder"
                                value="{{ old('account_holder', $accountHolder) }}" placeholder="NGUYEN VAN A">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="payment_prefix">{{ __('admin.payment_methods.sepay_payment_prefix') }}</label>
                            <input type="text" class="form-control text-dark" id="payment_prefix" name="payment_prefix"
                                value="{{ old('payment_prefix', $paymentPrefix) }}" placeholder="SEVQR">
                            <div class="form-text text-muted">{{ __('admin.payment_methods.sepay_payment_prefix_hint') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="qr_template">{{ __('admin.payment_methods.sepay_qr_template') }}</label>
                            <select class="form-select text-dark" id="qr_template" name="qr_template">
                                @foreach(\App\Services\SePayService::QR_TEMPLATES as $template)
                                    <option value="{{ $template }}" @selected(old('qr_template', $qrTemplate) === $template)>{{ $template }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_key">{{ __('admin.payment_methods.sepay_api_key') }}</label>
                            <input type="password" class="form-control text-dark" id="api_key" name="api_key"
                                value="{{ old('api_key', $apiKey) }}" placeholder="Nhập SePay API Token" autocomplete="off">
                            <div class="form-text text-muted">{{ __('admin.payment_methods.sepay_api_key_hint') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="webhook_token">{{ __('admin.payment_methods.sepay_webhook_token') }}</label>
                            <input type="text" class="form-control text-dark font-monospace small" id="webhook_token" name="webhook_token"
                                value="{{ old('webhook_token', $webhookToken) }}" autocomplete="off">
                            <div class="form-text text-muted">{{ __('admin.payment_methods.sepay_webhook_token_hint') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="webhook_secret">{{ __('admin.payment_methods.sepay_webhook_secret') }}</label>
                            <input type="password" class="form-control text-dark" id="webhook_secret" name="webhook_secret"
                                value="{{ old('webhook_secret', $webhookSecret) }}" autocomplete="off">
                            <div class="form-text text-muted">{{ __('admin.payment_methods.sepay_webhook_secret_hint') }}</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="instructions">{{ __('admin.payment_methods.sepay_instructions') }}</label>
                            <textarea class="form-control text-dark" id="instructions" name="instructions" rows="3"
                                placeholder="{{ __('admin.payment_methods.sepay_instructions_placeholder') }}">{{ old('instructions', $instructions) }}</textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark">{{ __('admin.payment_methods.webhook_url_label') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light text-dark font-monospace small" id="sepay_webhook_url"
                                    value="{{ route('api.webhooks.sepay') }}" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copySepayWebhookUrl()">{{ __('admin.payment_methods.copy') }}</button>
                            </div>
                            <div class="form-text text-muted">{{ __('admin.payment_methods.webhook_url_hint') }}</div>
                        </div>
                    </div>
                @elseif($method->method_code === 'bank_transfer')
                    @php
                        $bankName = data_get($method->settings, 'bank_name', '');
                        $accountNumber = data_get($method->settings, 'account_number', '');
                        $accountHolder = data_get($method->settings, 'account_holder', '');
                        $instructions = data_get($method->settings, 'instructions', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="bank_name">Tên ngân hàng <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="bank_name" name="bank_name" 
                                value="{{ old('bank_name', $bankName) }}" placeholder="Ví dụ: Vietcombank, MB Bank..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="account_number">Số tài khoản <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="account_number" name="account_number" 
                                value="{{ old('account_number', $accountNumber) }}" placeholder="Nhập số tài khoản" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="account_holder">Tên chủ tài khoản <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="account_holder" name="account_holder" 
                                value="{{ old('account_holder', $accountHolder) }}" placeholder="Ví dụ: NGUYEN VAN A" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="instructions">Hướng dẫn chuyển khoản</label>
                            <textarea class="form-control text-dark" id="instructions" name="instructions" rows="4" 
                                placeholder="Ghi chú các bước chuyển khoản hoặc thông tin lưu ý cho khách hàng...">{{ old('instructions', $instructions) }}</textarea>
                        </div>
                    </div>
                @elseif($method->method_code === 'cod' || $method->type === 'custom')
                    @php
                        $description = data_get($method->settings, 'description', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="description">{{ __('admin.payment_methods.fee') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control text-dark" id="description" name="description" rows="4" 
                                placeholder="Hướng dẫn hoặc mô tả cho khách hàng..." required>{{ old('description', $description) }}</textarea>
                        </div>
                    </div>
                @endif

                <div class="mt-4 pt-2 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        {{ __('admin.payment_methods.save_config') }}
                    </button>
                    <a href="{{ route('admin.payment-methods.index') }}" class="btn btn-outline-secondary px-4">
                        {{ __('admin.payment_methods.back') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if($method->method_code === 'sepay')
        <script>
            function copySepayWebhookUrl() {
                const field = document.getElementById('sepay_webhook_url');
                navigator.clipboard.writeText(field.value).then(function () {
                    alert(@json(__('admin.payment_methods.copied_notification')));
                });
            }
        </script>
    @endif
@endsection
