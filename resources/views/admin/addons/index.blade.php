@extends('admin.layouts.app')

@section('title', 'Tích hợp & hỗ trợ')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <h4 class="fw-semibold mb-1 text-white">Tích hợp & hỗ trợ</h4>
                    <p class="mb-0 text-white-50">Các tích hợp không bị khóa theo gói. Cần hỗ trợ kích hoạt hoặc cấu hình, hãy liên hệ bộ phận hỗ trợ kỹ thuật.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-3" role="alert">
        <i class="ti ti-message-circle fs-6"></i>
        <div>
            <strong>Không thanh toán addon trong hệ thống.</strong>
            Bạn có thể vào phần Cổng thanh toán hoặc Đối tác vận chuyển để cấu hình ngay. Nếu cần mở kết nối, xác thực thông tin hoặc hỗ trợ triển khai, vui lòng nhắn bộ phận hỗ trợ.
        </div>
    </div>

    <div class="row">
        @forelse ($addons as $addon)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm border border-light-subtle rounded-4">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="mb-3">
                            <div class="bg-primary-subtle text-primary p-3 rounded-3 d-inline-block">
                                <i class="ti ti-plug-connected fs-7"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">{{ $addon->name }}</h5>
                        <p class="text-muted small flex-grow-1">{{ $addon->description }}</p>
                        <span class="badge bg-success-subtle text-success align-self-start">Có thể cấu hình</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5 text-muted">Chưa có tích hợp nào được khai báo.</div>
        @endforelse
    </div>
@endsection
