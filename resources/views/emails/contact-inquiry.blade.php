@extends('emails.layout')

@section('title', 'Yêu cầu báo giá mới')
@section('header-subtitle', 'Thông báo yêu cầu báo giá mới')

@section('content')
    <div class="section-title">Thông tin khách hàng liên hệ</div>
    <table class="info-table">
        <tr>
            <td class="label">Họ và tên:</td>
            <td class="value">{{ $inquiry['name'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Số điện thoại:</td>
            <td class="value">{{ $inquiry['phone'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Email:</td>
            <td class="value">{{ $inquiry['email'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Thời gian gửi:</td>
            <td class="value">{{ now()->format('d/m/Y H:i:s') }}</td>
        </tr>
    </table>

    <div class="section-title">Nội dung yêu cầu chi tiết</div>
    <div class="message-box">{{ $inquiry['message'] ?? 'Không có nội dung tin nhắn.' }}</div>
@endsection
