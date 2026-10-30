{{-- An order email to the customer, all five states (App\Mail\OrderEmailData builds every value). --}}
@extends('emails.layout')

@php($font = $look['font_stack'])

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="px" style="padding:30px 32px 6px">
                <div style="font:600 12px/1.4 {!! $font !!};letter-spacing:.08em;text-transform:uppercase;color:{{ $mail['tone'] }}">{{ $mail['eyebrow'] }}</div>
                <h1 class="h1" style="margin:8px 0 10px;font:600 25px/1.25 {!! $font !!};color:#111827">{{ $mail['heading'] }}</h1>
                <p style="margin:0;font:15px/1.6 {!! $font !!};color:#4b5563">{!! nl2br(e($mail['intro'])) !!}</p>
            </td>
        </tr>
        <tr>
            <td class="px" style="padding:18px 32px 0">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px">
                    <tr>
                        <td style="padding:12px 16px;font:13px/1.4 {!! $font !!};color:#6b7280">{{ __('emails.ui.order_number') }}<br><b style="font-weight:600;font:600 15px/1.5 Consolas,monospace;color:#111827">#{{ $mail['number'] }}</b></td>
                        <td align="right" style="padding:12px 16px;font:13px/1.4 {!! $font !!};color:#6b7280">{{ __('emails.ui.placed_at') }}<br><b style="font-weight:600;font:600 15px/1.5 {!! $font !!};color:#111827">{{ $mail['placed_at'] }}</b></td>
                    </tr>
                </table>
            </td>
        </tr>

        @if($mail['step'] !== null)
            <tr>
                <td class="px" style="padding:24px 32px 4px">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            @foreach(__('emails.ui.steps') as $i => $stepLabel)
                                @if($i > 0)
                                    <td valign="middle" style="padding:0 4px"><div style="height:2px;background:{{ $i <= $mail['step'] ? $look['accent'] : '#e5e7eb' }};font-size:0;line-height:0">&nbsp;</div></td>
                                @endif
                                <td width="30" align="center"><div style="width:28px;height:28px;border-radius:50%;background:{{ $i <= $mail['step'] ? $look['accent'] : '#e5e7eb' }};color:{{ $i <= $mail['step'] ? $look['on_accent'] : '#6b7280' }};font:600 13px/28px {!! $font !!};text-align:center">{!! $i < $mail['step'] ? '&#10003;' : $i + 1 !!}</div></td>
                            @endforeach
                        </tr>
                    </table>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            @foreach(__('emails.ui.steps') as $i => $stepLabel)
                                <td width="25%" align="{{ $i === 0 ? 'left' : ($i === 3 ? 'right' : 'center') }}" style="padding-top:8px;font:{{ $i === $mail['step'] ? '600' : '400' }} 12px/1.3 {!! $font !!};color:{{ $i <= $mail['step'] ? '#111827' : '#9ca3af' }}">{{ $stepLabel }}</td>
                            @endforeach
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        @if($mail['notice'])
            <tr>
                <td class="px" style="padding:20px 32px 0">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $mail['notice']['background'] }};border-radius:10px">
                        <tr><td style="padding:14px 16px;font:14px/1.55 {!! $font !!};color:{{ $mail['notice']['color'] }}">@if($mail['notice']['title'])<b style="font-weight:600">{{ $mail['notice']['title'] }}</b> @endif{!! nl2br(e($mail['notice']['text'])) !!}</td></tr>
                    </table>
                </td>
            </tr>
        @endif

        @if($mail['cta'])
            <tr><td class="px" style="padding:24px 32px 4px">@include('emails.partials.button', $mail['cta'])</td></tr>
        @endif

        <tr><td class="px" style="padding:26px 32px 0"><div style="font:600 16px/1.4 {!! $font !!};color:#111827">{{ __('emails.ui.items') }}</div></td></tr>
        <tr>
            <td class="px" style="padding:4px 32px 0">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    @foreach($mail['lines'] as $line)
                        <tr>
                            @if($mail['images'])
                                <td width="56" style="padding:12px 0;border-bottom:1px solid #f0f1f3">
                                    @if($line['image'])<img src="{{ $line['image'] }}" width="48" height="48" alt="" style="display:block;width:48px;height:48px;border-radius:10px;object-fit:cover;border:0">@else<div style="width:48px;height:48px;border-radius:10px;background:#f3f4f6"></div>@endif
                                </td>
                            @endif
                            <td style="padding:12px {{ $mail['images'] ? '12px' : '12px 12px 0' }};border-bottom:1px solid #f0f1f3;font:14px/1.45 {!! $font !!};color:#111827">
                                <b style="font-weight:600">{{ $line['name'] }}</b><br>
                                <span style="color:#6b7280;font-size:13px">@if($line['variant']){{ $line['variant'] }} · @endif{{ __('emails.ui.qty') }} {{ $line['qty'] }}</span>
                            </td>
                            <td align="right" style="padding:12px 0;border-bottom:1px solid #f0f1f3;font:600 14px/1.45 {!! $font !!};color:#111827;white-space:nowrap">{{ $line['total'] }}</td>
                        </tr>
                    @endforeach
                </table>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px">
                    @foreach($mail['totals'] as $row)
                        <tr>
                            <td style="padding:5px 0;font:14px/1.4 {!! $font !!};color:#4b5563">{{ $row['label'] }}</td>
                            <td align="right" style="padding:5px 0;font:600 14px/1.4 {!! $font !!};color:{{ $row['minus'] ? '#047857' : '#111827' }};white-space:nowrap">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="2" style="padding-top:8px;border-bottom:1px solid #e5e7eb;font-size:0;line-height:0">&nbsp;</td></tr>
                    <tr>
                        <td style="padding:12px 0 0;font:600 16px/1.4 {!! $font !!};color:#111827">{{ __('emails.ui.total') }}</td>
                        <td align="right" style="padding:12px 0 0;font:600 18px/1.4 {!! $font !!};color:{{ $look['accent'] }};white-space:nowrap">{{ $mail['grand_total'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td class="px" style="padding:24px 32px 28px">
                <div style="border-top:1px solid #e5e7eb;padding-top:22px">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="stack">
                        <tr>
                            <td class="col" width="50%" valign="top" style="padding-right:12px">
                                <div style="font:600 12px/1.4 {!! $font !!};letter-spacing:.06em;text-transform:uppercase;color:#6b7280;margin-bottom:6px">{{ __('emails.ui.ship_to') }}</div>
                                <div style="font:14px/1.55 {!! $font !!};color:#111827">
                                    <b style="font-weight:600">{{ $mail['customer'] }}</b>@if($mail['phone']) · {{ $mail['phone'] }}@endif<br>{{ $mail['address'] }}
                                    @if($mail['notes'])<br><span style="color:#6b7280">{{ __('emails.ui.notes') }}: {{ $mail['notes'] }}</span>@endif
                                </div>
                            </td>
                            <td class="col" width="50%" valign="top" style="padding-left:12px">
                                <div style="font:600 12px/1.4 {!! $font !!};letter-spacing:.06em;text-transform:uppercase;color:#6b7280;margin-bottom:6px">{{ __('emails.ui.payment_shipping') }}</div>
                                <div style="font:14px/1.55 {!! $font !!};color:#111827">
                                    {{ $mail['payment'] }}
                                    @if($mail['carrier'])<br>{{ $mail['carrier'] }}@endif
                                    @if($mail['tracking'])
                                        <br>{{ __('emails.ui.tracking') }} <b style="font-weight:600;font-family:Consolas,monospace">{{ $mail['tracking'] }}</b>
                                    @elseif($mail['step'] !== null && $mail['step'] < 2)
                                        <br><span style="color:#6b7280">{{ __('emails.ui.tracking_later') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            <td class="px" style="padding:18px 32px;background:#f9fafb;border-top:1px solid #eef0f2;font:14px/1.6 {!! $font !!};color:#4b5563">
                @if($look['phone'])
                    {!! __('emails.ui.help', ['phone' => '<a href="tel:'.e(preg_replace('/[^0-9+]/', '', $look['phone'])).'" style="font-weight:600;text-decoration:none;color:'.e($look['accent']).'">'.e($look['phone']).'</a>']) !!}
                @else
                    {{ __('emails.ui.help_no_phone') }}
                @endif
            </td>
        </tr>
    </table>
@endsection
