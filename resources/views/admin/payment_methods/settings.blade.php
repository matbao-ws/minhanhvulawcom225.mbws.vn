@extends('admin.layouts.app')

@section('title', __('admin.payment_methods.config_shipping_integration'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.payment_methods.setup_connection') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.payment-methods.index') }}">{{ __('admin.payment_methods.title') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.payment_methods.setup_connection') }}: {{ $method->name }}</li>
                        </ol>
                    </nav>
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
                        $apiKey = data_get($method->settings, 'api_key', '');
                        $webhookToken = data_get($method->settings, 'webhook_token', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_key">API Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="api_key" name="api_key" 
                                value="{{ old('api_key', $apiKey) }}" placeholder="Nhập Sepay API Key" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="sepay_webhook_token">Webhook Authorization Token</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-dark" id="sepay_webhook_token" name="webhook_token" 
                                    value="{{ old('webhook_token', $webhookToken) }}" placeholder="Nhập webhook token...">
                                <button class="btn btn-outline-secondary d-flex align-items-center gap-1" type="button" onclick="generateWebhookToken()">
                                    <i class="ti ti-refresh fs-4"></i> {{ __('admin.payment_methods.generate_random') }}
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark">{{ __('admin.payment_methods.webhook_url_label') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light text-dark font-monospace small" id="sepay_webhook_url" 
                                    value="{{ url('/api/webhooks/sepay') }}?token={{ $webhookToken }}" readonly>
                                <button class="btn btn-outline-primary d-flex align-items-center gap-1" type="button" onclick="copyWebhookUrl()">
                                    <i class="ti ti-copy fs-4"></i> {{ __('admin.payment_methods.copy') }}
                                </button>
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
@endsection

@push('scripts')
<script>
    function generateWebhookToken() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let token = '';
        for (let i = 0; i < 32; i++) {
            token += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('sepay_webhook_token').value = token;
        updateWebhookUrl(token);
    }

    function updateWebhookUrl(token) {
        const baseUrl = "{{ url('/api/webhooks/sepay') }}";
        document.getElementById('sepay_webhook_url').value = baseUrl + '?token=' + token;
    }

    function copyWebhookUrl() {
        const copyText = document.getElementById("sepay_webhook_url");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            icon: 'success',
            title: '{{ __('admin.payment_methods.copied_notification') }}'
        });
    }
</script>
@endpush
