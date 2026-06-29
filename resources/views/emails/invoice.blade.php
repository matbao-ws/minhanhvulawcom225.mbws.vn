<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn thanh toán #{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #f6f9fc;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
        }
        .header {
            background: linear-gradient(135deg, #5d87ff, #39b3d7);
            color: #ffffff;
            padding: 32px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 32px;
        }
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
        .invoice-row:last-child {
            margin-bottom: 0;
        }
        .label {
            color: #64748b;
            font-weight: 500;
        }
        .value {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-paid {
            background-color: #def7ec;
            color: #03543f;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-unpaid {
            background-color: #fde8e8;
            color: #9b1c1c;
        }
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
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #eef2f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>HÓA ĐƠN THANH TOÁN</h1>
            <p>Mã hóa đơn: #{{ $invoice->invoice_number }}</p>
        </div>
        <div class="content">
            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-top: 0;">
                Xin chào Quý khách,
            </p>
            <p style="color: #475569; font-size: 15px; line-height: 1.6;">
                Chúng tôi xin gửi thông tin chi tiết hóa đơn cho gói dịch vụ quý khách đã sử dụng trên hệ thống E-commerce Core.
            </p>
            
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
                            <span class="badge badge-paid">Đã thanh toán</span>
                        @elseif($invoice->status === 'pending')
                            <span class="badge badge-pending">Chờ xử lý</span>
                        @else
                            <span class="badge badge-unpaid">Chưa thanh toán</span>
                        @endif
                    </span>
                </div>
                <div class="invoice-row total-row">
                    <span class="label" style="font-size: 16px; font-weight: 700; align-self: center;">Tổng thanh toán:</span>
                    <span class="value total-value">{{ number_format($invoice->amount, 0) }} đ</span>
                </div>
            </div>

            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin-bottom: 0;">
                Nếu có bất kỳ thắc mắc nào, vui lòng liên hệ với bộ phận hỗ trợ khách hàng của chúng tôi để được giải đáp kịp thời.
            </p>
        </div>
        <div class="footer">
            <p>Hệ thống E-commerce Core - Bản quyền © {{ date('Y') }}</p>
            <p>Email này được gửi tự động, vui lòng không phản hồi.</p>
        </div>
    </div>
</body>
</html>
