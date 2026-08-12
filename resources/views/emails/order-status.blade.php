@extends('emails.layout')

@section('title', __('emails.order.subject', ['number' => $order->order_number]))
@section('header-subtitle', __('emails.order.heading').' · #'.$order->order_number)

@section('styles')
    .order-status-card {
        background-color: #f8fafc;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
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
    .items-table td.qty { text-align: center; }
    .items-table td.price { text-align: right; }
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
        color: #5d87ff;
        font-weight: 700;
        border-top: 1px solid #e2e8f0;
        padding-top: 10px;
        margin-top: 6px;
    }
@endsection

@section('content')
    <p>{{ __('emails.order.hello', ['name' => $order->customer_name]) }}</p>
    <p>{{ __('emails.order.intro') }}</p>

    <div class="order-status-card" style="text-align: center;">
        @if($order->status === 'pending')
            <span class="badge badge-warning">{{ __('emails.order.status.pending.label') }}</span>
            <p style="margin: 10px 0 0;">{{ __('emails.order.status.pending.message') }}</p>
        @elseif($order->status === 'processing')
            <span class="badge badge-info">{{ __('emails.order.status.processing.label') }}</span>
            <p style="margin: 10px 0 0;">{{ __('emails.order.status.processing.message') }}</p>
        @elseif($order->status === 'completed')
            <span class="badge badge-success">{{ __('emails.order.status.completed.label') }}</span>
            <p style="margin: 10px 0 0;">{{ __('emails.order.status.completed.message') }}</p>
        @elseif($order->status === 'cancelled')
            <span class="badge badge-danger">{{ __('emails.order.status.cancelled.label') }}</span>
            <p style="margin: 10px 0 0;">{{ __('emails.order.status.cancelled.message') }}</p>
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
@endsection
