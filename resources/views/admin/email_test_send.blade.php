{{-- TEMPORARY test page (branch test/email-send-page); delete with EmailTestSendController. --}}
@extends('admin.layouts.app')

@section('title', 'Gửi thử email')

@section('content')
    <x-admin.page-header title="Gửi thử email (trang tạm)">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">Gửi thử email</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <div class="alert alert-warning">
        Trang tạm để thử mẫu email. Email đi bằng đường gửi thật của website này
        (<b>{{ ['admin' => 'SMTP riêng trong Cấu hình thông báo', 'remote' => 'mail relay MatBao', 'env' => 'cấu hình .env của máy chủ'][$route] ?? $route }}</b>),
        dữ liệu là đơn hàng mẫu, tiêu đề có <b>[THỬ]</b>. Giao diện lấy theo trang
        <a href="{{ route('admin.email-design.index') }}">Giao diện email</a> đã lưu.
    </div>

    @if(session('results'))
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">Kết quả</h5>
                <ul class="list-group">
                    @foreach(session('results') as [$email, $ok, $detail])
                        <li class="list-group-item d-flex gap-3 align-items-start">
                            <span class="badge {{ $ok ? 'bg-success' : 'bg-danger' }}">{{ $ok ? 'Đã gửi' : 'Lỗi' }}</span>
                            <span><b>{{ __("admin.email_design.emails.names.{$email}") }}</b><br><span class="text-muted small">{{ $detail }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.email-test-send.send') }}" class="card shadow-sm">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="to">Gửi tới (tối đa 5, cách nhau bằng dấu phẩy)</label>
                <input type="text" class="form-control @error('to') is-invalid @enderror" id="to" name="to" value="{{ old('to', 'pductoandev@gmail.com, phanbinh150504@gmail.com') }}">
                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <span class="form-label fw-semibold d-block">Mẫu email</span>
                @foreach($emails as $email)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="emails[]" value="{{ $email }}" id="test_{{ $email }}" @checked(in_array($email, old('emails', $emails), true))>
                        <label class="form-check-label" for="test_{{ $email }}">{{ __("admin.email_design.emails.names.{$email}") }}</label>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary">Gửi thử</button>
        </div>
    </form>
@endsection
