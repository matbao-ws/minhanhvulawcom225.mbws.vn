@extends('admin.layouts.app')

@section('title', __('admin.shipping_partners.config_shipping_integration'))

@section('content')
    <!-- Header Card -->
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.shipping_partners.setup_connection') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.shipping-partners.index') }}">{{ __('admin.shipping_partners.title') }}</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.shipping_partners.setup_connection') }}: {{ $partner->name }}</li>
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
                    <iconify-icon icon="solar:delivery-line-duotone" class="fs-6 text-primary"></iconify-icon>
                </div>
                <div>
                    <h5 class="fw-semibold text-dark mb-0">{{ __('admin.shipping_partners.setup_partner_config', ['name' => $partner->name]) }}</h5>
                    <p class="text-muted small mb-0">{{ __('admin.shipping_partners.connection_code') }}: {{ $partner->partner_code }} | {{ __('admin.shipping_partners.type') }}: {{ $partner->type === 'connected' ? __('admin.shipping_partners.api_connected') : __('admin.shipping_partners.self_delivery') }}</p>
                </div>
            </div>

            <form action="{{ route('admin.shipping-partners.update-settings', $partner) }}" method="POST">
                @csrf
                
                @if($partner->partner_code === 'DTGH000012') {{-- GHTK --}}
                    @php
                        $apiToken = data_get($partner->settings, 'api_token', '');
                        $apiUrl = data_get($partner->settings, 'api_url', 'https://services.giaohangtietkiem.vn');
                        $webhookToken = data_get($partner->settings, 'webhook_token', '');
                        $realtimeTrackingEnabled = (bool) old('realtime_tracking_enabled', data_get($partner->settings, 'realtime_tracking_enabled', filled($webhookToken)));
                    @endphp
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_token">API Token <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="api_token" name="api_token" 
                                value="{{ old('api_token', $apiToken) }}" placeholder="{{ __('admin.shipping_partners.api_token_ghtk_placeholder') }}" required>
                            <div class="form-text">{{ __('admin.shipping_partners.api_token_ghtk_hint') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_url">{{ __('admin.shipping_partners.api_url_label') }} <span class="text-danger">*</span></label>
                            <select name="api_url" id="api_url" class="form-select text-dark" required>
                                <option value="https://services.giaohangtietkiem.vn" @selected($apiUrl === 'https://services.giaohangtietkiem.vn')>Production (services.giaohangtietkiem.vn)</option>
                                <option value="https://services.ghtk.vn" @selected($apiUrl === 'https://services.ghtk.vn')>Sandbox / Mock Environment (services.ghtk.vn)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark d-block">Cập nhật trạng thái realtime</label>
                            <input type="hidden" name="realtime_tracking_enabled" value="0">
                            <div class="form-check form-switch pt-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="ghtk_realtime_tracking" name="realtime_tracking_enabled" value="1" @checked($realtimeTrackingEnabled)>
                                <label class="form-check-label" for="ghtk_realtime_tracking">Nhận webhook trạng thái đơn từ GHTK</label>
                            </div>
                            <div class="form-text">Không cần bật để kết nối tài khoản và đẩy đơn sang GHTK.</div>
                        </div>
                        <div class="col-md-6 mb-3" id="ghtk-webhook-settings" @style(! $realtimeTrackingEnabled ? 'display: none' : '')>
                            <label class="form-label fw-semibold text-dark" for="ghtk_webhook_token">{{ __('admin.shipping_partners.webhook_token_label') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-dark" id="ghtk_webhook_token" name="webhook_token" 
                                    value="{{ old('webhook_token', $webhookToken) }}" placeholder="Nhập token tự định nghĩa...">
                                <button class="btn btn-outline-secondary d-flex align-items-center gap-1" type="button" onclick="generateWebhookToken()">
                                    <i class="ti ti-refresh fs-4"></i> {{ __('admin.shipping_partners.generate_random') }}
                                </button>
                            </div>
                        </div>
                        @php
                            $pickup = data_get($partner->settings, 'pickup', []);
                        @endphp
                        <div class="col-12"><hr><h6 class="fw-semibold">Thông tin lấy hàng</h6></div>
                        <div class="col-md-6 mb-3"><label class="form-label" for="pickup_name">Tên người gửi <span class="text-danger">*</span></label><input class="form-control" id="pickup_name" name="pickup_name" value="{{ old('pickup_name', data_get($pickup, 'name')) }}" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label" for="pickup_tel">Số điện thoại lấy hàng <span class="text-danger">*</span></label><input class="form-control" id="pickup_tel" name="pickup_tel" value="{{ old('pickup_tel', data_get($pickup, 'tel')) }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label" for="pickup_province">Tỉnh/Thành <span class="text-danger">*</span></label><input class="form-control" id="pickup_province" name="pickup_province" value="{{ old('pickup_province', data_get($pickup, 'province')) }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label" for="pickup_district">Quận/Huyện <span class="text-danger">*</span></label><input class="form-control" id="pickup_district" name="pickup_district" value="{{ old('pickup_district', data_get($pickup, 'district')) }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label" for="pickup_ward">Phường/Xã <span class="text-danger">*</span></label><input class="form-control" id="pickup_ward" name="pickup_ward" value="{{ old('pickup_ward', data_get($pickup, 'ward')) }}" required></div>
                        <div class="col-12 mb-3"><label class="form-label" for="pickup_address">Địa chỉ lấy hàng <span class="text-danger">*</span></label><input class="form-control" id="pickup_address" name="pickup_address" value="{{ old('pickup_address', data_get($pickup, 'address')) }}" required></div>
                        
                        <div class="col-md-12 mb-3" id="ghtk-webhook-url" @style(! $realtimeTrackingEnabled ? 'display: none' : '')>
                            <label class="form-label fw-semibold text-dark">{{ __('admin.shipping_partners.webhook_url_label') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light text-dark font-monospace small" id="ghtk_webhook_url" 
                                    value="{{ url('/api/webhooks/ghtk') }}" readonly>
                                <button class="btn btn-outline-primary d-flex align-items-center gap-1" type="button" onclick="copyWebhookUrl()">
                                    <i class="ti ti-copy fs-4"></i> {{ __('admin.shipping_partners.copy') }}
                                </button>
                            </div>
                            <div class="form-text text-muted">{{ __('admin.shipping_partners.webhook_url_hint') }}</div>
                        </div>
                    </div>
                @elseif($partner->partner_code === 'DTGH000013') {{-- GHN --}}
                    @php
                        $apiToken = data_get($partner->settings, 'api_token', '');
                        $apiUrl = data_get($partner->settings, 'api_url', 'https://dev-online-gateway.ghn.vn');
                        $clientId = data_get($partner->settings, 'client_id', '');
                        $shopId = data_get($partner->settings, 'shop_id', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_token">API Token <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="api_token" name="api_token" 
                                value="{{ old('api_token', $apiToken) }}" placeholder="{{ __('admin.shipping_partners.api_token_ghn_placeholder') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_url">{{ __('admin.shipping_partners.api_url_label') }} <span class="text-danger">*</span></label>
                            <select name="api_url" id="api_url" class="form-select text-dark" required>
                                <option value="https://online-gateway.ghn.vn" @selected($apiUrl === 'https://online-gateway.ghn.vn')>Production (online-gateway.ghn.vn)</option>
                                <option value="https://dev-online-gateway.ghn.vn" @selected($apiUrl === 'https://dev-online-gateway.ghn.vn')>Sandbox / Dev Testing (dev-online-gateway.ghn.vn)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="shop_id">{{ __('admin.shipping_partners.shop_id_label') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="shop_id" name="shop_id" 
                                value="{{ old('shop_id', $shopId) }}" placeholder="Nhập Shop ID...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="client_id">{{ __('admin.shipping_partners.client_id_label') }}</label>
                            <input type="text" class="form-control text-dark" id="client_id" name="client_id" 
                                value="{{ old('client_id', $clientId) }}" placeholder="Nhập Client ID (nếu có)...">
                        </div>
                    </div>
                @elseif($partner->partner_code === 'DTGH000014') {{-- J&T --}}
                    @php
                        $customerid = data_get($partner->settings, 'customerid', '');
                        $key = data_get($partner->settings, 'key', '');
                        $eccompanyid = data_get($partner->settings, 'eccompanyid', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="customerid">{{ __('admin.shipping_partners.customer_id_jt_label') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="customerid" name="customerid" 
                                value="{{ old('customerid', $customerid) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="key">{{ __('admin.shipping_partners.secret_key_jt_label') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="key" name="key" 
                                value="{{ old('key', $key) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="eccompanyid">{{ __('admin.shipping_partners.ec_company_id_label') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="eccompanyid" name="eccompanyid" 
                                value="{{ old('eccompanyid', $eccompanyid) }}" required>
                        </div>
                    </div>
                @elseif($partner->partner_code === 'DTGH000015') {{-- SPX Express --}}
                    @php
                        $apiToken = data_get($partner->settings, 'api_token', '');
                        $apiUrl = data_get($partner->settings, 'api_url', 'https://api.spx.vn');
                        $partnerId = data_get($partner->settings, 'partner_id', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_token">API Token / Partner Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="api_token" name="api_token" 
                                value="{{ old('api_token', $apiToken) }}" placeholder="{{ __('admin.shipping_partners.api_token_spx_placeholder') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_url">{{ __('admin.shipping_partners.api_url_label') }} <span class="text-danger">*</span></label>
                            <input type="url" class="form-control text-dark" id="api_url" name="api_url" 
                                value="{{ old('api_url', $apiUrl) }}" placeholder="Nhập API URL..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="partner_id">{{ __('admin.shipping_partners.partner_id_label') }}</label>
                            <input type="text" class="form-control text-dark" id="partner_id" name="partner_id" 
                                value="{{ old('partner_id', $partnerId) }}" placeholder="Nhập Partner ID...">
                        </div>
                    </div>
                @elseif($partner->partner_code === 'DTGH000016') {{-- Viettel Post --}}
                    @php
                        $apiToken = data_get($partner->settings, 'api_token', '');
                        $apiUrl = data_get($partner->settings, 'api_url', 'https://partner.viettelpost.vn');
                        $username = data_get($partner->settings, 'username', '');
                    @endphp
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_token">API Token / App Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-dark" id="api_token" name="api_token" 
                                value="{{ old('api_token', $apiToken) }}" placeholder="{{ __('admin.shipping_partners.api_token_viettel_placeholder') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="api_url">{{ __('admin.shipping_partners.api_url_label') }} <span class="text-danger">*</span></label>
                            <input type="url" class="form-control text-dark" id="api_url" name="api_url" 
                                value="{{ old('api_url', $apiUrl) }}" placeholder="Nhập API URL..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="username">{{ __('admin.shipping_partners.username_label') }}</label>
                            <input type="text" class="form-control text-dark" id="username" name="username" 
                                value="{{ old('username', $username) }}" placeholder="Nhập số điện thoại hoặc email đăng ký...">
                        </div>
                    </div>
                @elseif($partner->partner_code === 'DTGHTUGIAO') {{-- Flat Rate --}}
                    @php
                        $fee = data_get($partner->settings, 'fee', 30000);
                    @endphp
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-dark" for="fee">{{ __('admin.shipping_partners.fee') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control text-dark" id="fee" name="fee" 
                                value="{{ old('fee', $fee) }}" min="0" required>
                        </div>
                    </div>
                @endif

                <div class="mt-4 pt-2 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        {{ __('admin.shipping_partners.save_config') }}
                    </button>
                    <a href="{{ route('admin.shipping-partners.index') }}" class="btn btn-outline-secondary px-4">
                        {{ __('admin.shipping_partners.back') }}
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
        document.getElementById('ghtk_webhook_token').value = token;
    }

    function toggleGhtkWebhookSettings() {
        const enabled = document.getElementById('ghtk_realtime_tracking').checked;
        document.getElementById('ghtk-webhook-settings').style.display = enabled ? '' : 'none';
        document.getElementById('ghtk-webhook-url').style.display = enabled ? '' : 'none';
    }

    function copyWebhookUrl() {
        const copyText = document.getElementById("ghtk_webhook_url");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            icon: 'success',
            title: '{{ __('admin.shipping_partners.copied_notification') }}'
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const realtimeToggle = document.getElementById('ghtk_realtime_tracking');
        const webhookToken = document.getElementById('ghtk_webhook_token');
        if (!realtimeToggle || !webhookToken) return;

        realtimeToggle.addEventListener('change', toggleGhtkWebhookSettings);
        toggleGhtkWebhookSettings();
    });
</script>
@endpush
