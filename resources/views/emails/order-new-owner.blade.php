{{-- "Đơn mới" to the shop owner (App\Mail\OwnerNewOrderMail; values from App\Mail\OrderEmailData). --}}
@extends('emails.layout')

@php($font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif")
@php($tel = $mail['phone'] ? 'tel:'.preg_replace('/[^0-9+]/', '', $mail['phone']) : null)

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="px" style="padding:30px 32px 6px">
                <div style="font:700 12px/1.4 {!! $font !!};letter-spacing:.08em;text-transform:uppercase;color:{{ $look['accent'] }}">{{ $mail['eyebrow'] }}</div>
                <h1 class="h1" style="margin:8px 0 6px;font:800 25px/1.25 {!! $font !!};color:#111827">{{ $mail['heading'] }}</h1>
                <p style="margin:0;font:14px/1.6 {!! $font !!};color:#6b7280">#{{ $mail['number'] }} · {{ $mail['placed_at'] }} · {{ $mail['payment'] }}</p>
            </td>
        </tr>
        <tr>
            <td class="px" style="padding:20px 32px 0">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px">
                    <tr>
                        <td style="padding:14px 16px;font:14px/1.6 {!! $font !!};color:#111827">
                            <b>{{ $mail['customer'] }}</b>@if($tel) · <a href="{{ $tel }}" style="font-weight:700;text-decoration:none;color:{{ $look['accent'] }}">{{ $mail['phone'] }}</a>@endif<br>
                            <span style="color:#4b5563">{{ $mail['address'] }}</span>
                            @if($mail['notes'])<br><span style="color:#6b7280">{{ __('emails.ui.notes') }}: {{ $mail['notes'] }}</span>@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="px" style="padding:18px 32px 0">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    @foreach($mail['lines'] as $line)
                        <tr>
                            <td style="padding:9px 0;border-bottom:1px solid #f0f1f3;font:14px/1.45 {!! $font !!};color:#111827">{{ $line['name'] }}@if($line['variant']) <span style="color:#6b7280">· {{ $line['variant'] }}</span>@endif</td>
                            <td align="center" style="padding:9px 8px;border-bottom:1px solid #f0f1f3;font:600 14px/1.45 {!! $font !!};color:#111827;white-space:nowrap">× {{ $line['qty'] }}</td>
                            <td align="right" style="padding:9px 0;border-bottom:1px solid #f0f1f3;font:600 14px/1.45 {!! $font !!};color:#111827;white-space:nowrap">{{ $line['total'] }}</td>
                        </tr>
                    @endforeach
                </table>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;font:14px/1.5 {!! $font !!};color:#4b5563">
                    @foreach(array_slice($mail['totals'], 1) as $row)
                        <tr>
                            <td style="padding:3px 0">{{ $row['label'] }}</td>
                            <td align="right" style="padding:3px 0;font-weight:600;color:{{ $row['minus'] ? '#047857' : '#111827' }}">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="padding:8px 0 0;font-weight:700;color:#111827;font-size:16px">{{ __('emails.ui.customer_pays') }}</td>
                        <td align="right" style="padding:8px 0 0;font-weight:800;color:{{ $look['accent'] }};font-size:18px">{{ $mail['grand_total'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="px" style="padding:24px 32px 30px">
                @include('emails.partials.button', ['label' => __('emails.ui.open_order'), 'href' => $adminUrl])
                @if($tel)
                    &nbsp;@include('emails.partials.button', ['label' => __('emails.ui.call_customer'), 'href' => $tel, 'secondary' => true])
                @endif
                @if($mail['intro'] !== '')
                    <p style="margin:16px 0 0;font:13px/1.6 {!! $font !!};color:#6b7280">{!! nl2br(e($mail['intro'])) !!}</p>
                @endif
            </td>
        </tr>
    </table>
@endsection
