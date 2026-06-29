<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật đơn hàng #{{ $order->order_number }}</title>
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
            background: linear-gradient(135deg, #13deb9, #0d8cf0);
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
        .order-status-card {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-processing { background-color: #dbeafe; color: #1e40af; }
        .status-completed { background-color: #d1fae5; color: #065f46; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 24px;
            margin-bottom: 12px;
            border-left: 4px solid #0d8cf0;
            padding-left: 8px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 8px 0;
            font-size: 14px;
            vertical-align: top;
        }
        .info-table td.label {
            color: #64748b;
            width: 35%;
            font-weight: 500;
        }
        .info-table td.value {
            color: #1e293b;
            font-weight: 600;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #475569;
            text-align: left;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .items-table td.qty {
            text-align: center;
        }
        .items-table td.price {
            text-align: right;
        }
        
        .totals-box {
            float: right;
            width: 250px;
            margin-top: 10px;
            margin-bottom: 30px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }
        .grand-total {
            font-size: 18px;
            color: #0d8cf0;
            font-weight: 700;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 6px;
        }

        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #eef2f6;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CẬP NHẬT ĐƠN HÀNG</h1>
            <p>Mã đơn hàng: #{{ $order->order_number }}</p>
        </div>
        <div class="content">
            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-top: 0;">
                Xin chào {{ $order->customer_name }},
            </p>
            <p style="color: #475569; font-size: 15px; line-height: 1.6;">
                Trạng thái đơn hàng của bạn vừa được cập nhật như sau:
            </p>

            <div class="order-status-card" style="text-align: center;">
                @if($order->status === 'pending')
                    <span class="status-badge status-pending">Chờ xử lý</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">Đơn hàng của bạn đã được tiếp nhận và đang chờ xác nhận.</p>
                @elseif($order->status === 'processing')
                    <span class="status-badge status-processing">Đang xử lý</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">Đơn hàng đang được chuẩn bị và đóng gói để gửi đi.</p>
                @elseif($order->status === 'completed')
                    <span class="status-badge status-completed">Đã hoàn thành</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">Đơn hàng đã được giao thành công. Cảm ơn bạn đã mua sắm tại cửa hàng chúng tôi!</p>
                @elseif($order->status === 'cancelled')
                    <span class="status-badge status-cancelled">Đã hủy</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">Đơn hàng đã bị hủy bỏ khỏi hệ thống.</p>
                @endif
            </div>

            <div class="section-title">Thông tin giao hàng</div>
            <table class="info-table">
                <tr>
                    <td class="label">Người nhận:</td>
                    <td class="value">{{ $order->customer_name }}</td>
                </tr>
                <tr>
                    <td class="label">Số điện thoại:</td>
                    <td class="value">{{ $order->customer_phone }}</td>
                </tr>
                <tr>
                    <td class="label">Địa chỉ nhận:</td>
                    <td class="value">{{ $order->shipping_address }}</td>
                </tr>
                @if($order->notes)
                <tr>
                    <td class="label">Ghi chú:</td>
                    <td class="value">{{ $order->notes }}</td>
                </tr>
                @endif
            </table>

            <div class="section-title">Danh sách sản phẩm</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th class="qty" style="text-align: center;">SL</th>
                        <th class="price" style="text-align: right;">Giá</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->product_name }}</strong>
                                @if($item->variant_name)
                                    <div style="font-size: 12px; color: #64748b;">Mẫu: {{ $item->variant_name }}</div>
                                @endif
                            </td>
                            <td class="qty" style="text-align: center;">{{ $item->quantity }}</td>
                            <td class="price" style="text-align: right;">{{ number_format($item->price, 0) }} đ</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals-box">
                <div class="total-row">
                    <span style="color: #64748b;">Tạm tính:</span>
                    <span style="font-weight: 600; color: #1e293b;">{{ number_format($order->subtotal, 0) }} đ</span>
                </div>
                @if($order->discount > 0)
                <div class="total-row">
                    <span style="color: #64748b;">Giảm giá:</span>
                    <span style="font-weight: 600; color: #dc2626;">-{{ number_format($order->discount, 0) }} đ</span>
                </div>
                @endif
                <div class="total-row grand-total">
                    <span>Tổng cộng:</span>
                    <span>{{ number_format($order->grand_total, 0) }} đ</span>
                </div>
            </div>
            
            <div style="clear: both;"></div>
        </div>
        <div class="footer">
            <p>Hệ thống E-commerce Core - Bản quyền © {{ date('Y') }}</p>
            <p>Email này được gửi tự động, vui lòng không phản hồi.</p>
        </div>
    </div>
</body>
</html>
