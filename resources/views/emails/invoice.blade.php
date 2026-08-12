@extends('emails.layout')

@section('title', $siteBranding['name'].' - Hóa đơn thanh toán #'.$invoice->invoice_number)
@section('header-subtitle', 'HÓA ĐƠN THANH TOÁN · #'.$invoice->invoice_number)

@section('styles')
    .invoice-box {
        background-color: #f8fafc;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
    }
    .invoice-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 14px;
    }
    .invoice-row:last-child { margin-bottom: 0; }
    .invoice-row .label { color: #64748b; font-weight: 500; }
    .invoice-row .value { color: #1e293b; font-weight: 600; text-align: right; }
    .total-row {
        border-top: 2px dashed #e2e8f0;
        margin-top: 16px;
        padding-top: 16px;
    }
    .total-value {
        font-size: 20px;
        color: #5d87ff;
        font-weight: 700;
    }
@endsection

@section('content')
    <p>Xin chào Quý khách,</p>
    <p>Chúng tôi xin gửi thông tin chi tiết hóa đơn cho gói dịch vụ quý khách đã sử dụng trên hệ thống {{ $siteBranding['name'] }}.</p>

    <div class="invoice-box">
        <div class="invoice-row">
            <span class="label">Tên gói dịch vụ:</span>
            <span class="value">{{ $invoice->package_name }}</span>
        </div>
        <div class="invoice-row">
            <span class="label">Ngày lập hóa đơn:</span>
            <span class="value">{{ \Carbon\Carbon::parse($invoice->billing_date)->format('d/m/Y') }}</span>
        </div>
        <div class="invoice-row">
            <span class="label">Hạn thanh toán:</span>
            <span class="value">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}</span>
        </div>
        <div class="invoice-row">
            <span class="label">Phương thức:</span>
            <span class="value">
                @if($invoice->payment_method === 'bank_transfer')
                    Chuyển khoản ngân hàng
                @elseif($invoice->payment_method)
                    {{ $invoice->payment_method }}
                @else
                    Chưa xác định
                @endif
            </span>
        </div>
        <div class="invoice-row">
            <span class="label">Trạng thái:</span>
            <span class="value">
                @if($invoice->status === 'paid')
                    <span class="badge badge-success">Đã thanh toán</span>
                @elseif($invoice->status === 'pending')
                    <span class="badge badge-warning">Chờ xử lý</span>
                @else
                    <span class="badge badge-danger">Chưa thanh toán</span>
                @endif
            </span>
        </div>
        <div class="invoice-row total-row">
            <span class="label" style="font-size: 16px; font-weight: 700; align-self: center;">Tổng thanh toán:</span>
            <span class="value total-value">{{ number_format($invoice->amount, 0) }} đ</span>
        </div>
    </div>

    <p style="margin-bottom: 0;">Nếu có bất kỳ thắc mắc nào, vui lòng liên hệ với bộ phận hỗ trợ khách hàng của chúng tôi để được giải đáp kịp thời.</p>
@endsection
