<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.order.subject', ['number' => $order->order_number]) }}</title>
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
            <h1>{{ __('emails.order.heading') }}</h1>
            <p>{{ __('emails.order.order_number') }}: #{{ $order->order_number }}</p>
        </div>
        <div class="content">
            <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-top: 0;">
                {{ __('emails.order.hello', ['name' => $order->customer_name]) }}
            </p>
            <p style="color: #475569; font-size: 15px; line-height: 1.6;">
                {{ __('emails.order.intro') }}
            </p>

            <div class="order-status-card" style="text-align: center;">
                @if($order->status === 'pending')
                    <span class="status-badge status-pending">{{ __('emails.order.status.pending.label') }}</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">{{ __('emails.order.status.pending.message') }}</p>
                @elseif($order->status === 'processing')
                    <span class="status-badge status-processing">{{ __('emails.order.status.processing.label') }}</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">{{ __('emails.order.status.processing.message') }}</p>
                @elseif($order->status === 'completed')
                    <span class="status-badge status-completed">{{ __('emails.order.status.completed.label') }}</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">{{ __('emails.order.status.completed.message') }}</p>
                @elseif($order->status === 'cancelled')
                    <span class="status-badge status-cancelled">{{ __('emails.order.status.cancelled.label') }}</span>
                    <p style="margin: 0; color: #475569; font-size: 14px;">{{ __('emails.order.status.cancelled.message') }}</p>
                @endif
            </div>

            <div class="section-title">{{ __('emails.order.shipping_info') }}</div>
            <table class="info-table">
                <tr>
                    <td class="label">{{ __('emails.order.recipient') }}:</td>
                    <td class="value">{{ $order->customer_name }}</td>
                </tr>
                <tr>
                    <td class="label">{{ __('emails.order.phone') }}:</td>
                    <td class="value">{{ $order->customer_phone }}</td>
                </tr>
                <tr>
                    <td class="label">{{ __('emails.order.address') }}:</td>
                    <td class="value">{{ $order->shipping_address }}</td>
                </tr>
                @if($order->notes)
                <tr>
                    <td class="label">{{ __('emails.order.notes') }}:</td>
                    <td class="value">{{ $order->notes }}</td>
                </tr>
                @endif
            </table>

            <div class="section-title">{{ __('emails.order.items') }}</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>{{ __('emails.order.product') }}</th>
                        <th class="qty" style="text-align: center;">{{ __('emails.order.quantity') }}</th>
                        <th class="price" style="text-align: right;">{{ __('emails.order.price') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->product_name }}</strong>
                                @if($item->variant_name)
                                    <div style="font-size: 12px; color: #64748b;">{{ __('emails.order.variant') }}: {{ $item->variant_name }}</div>
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
                    <span style="color: #64748b;">{{ __('emails.order.subtotal') }}:</span>
                    <span style="font-weight: 600; color: #1e293b;">{{ number_format($order->subtotal, 0) }} đ</span>
                </div>
                @if($order->discount > 0)
                <div class="total-row">
                    <span style="color: #64748b;">{{ __('emails.order.discount') }}:</span>
                    <span style="font-weight: 600; color: #dc2626;">-{{ number_format($order->discount, 0) }} đ</span>
                </div>
                @endif
                <div class="total-row grand-total">
                    <span>{{ __('emails.order.total') }}:</span>
                    <span>{{ number_format($order->grand_total, 0) }} đ</span>
                </div>
            </div>
            
            <div style="clear: both;"></div>
        </div>
        <div class="footer">
            <p>{{ __('emails.order.copyright', ['year' => date('Y')]) }}</p>
            <p>{{ __('emails.order.automatic') }}</p>
        </div>
    </div>
</body>
</html>
