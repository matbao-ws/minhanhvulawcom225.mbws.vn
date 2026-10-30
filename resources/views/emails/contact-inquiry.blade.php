{{-- A contact form message to the shop owner (App\Mail\ContactInquiryMail). --}}
@extends('emails.layout')

@php($font = $look['font_stack'])

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="px" style="padding:30px 32px 6px">
                <div style="font:600 12px/1.4 {!! $font !!};letter-spacing:.08em;text-transform:uppercase;color:{{ $look['accent'] }}">{{ $eyebrow }}</div>
                <h1 class="h1" style="margin:8px 0 6px;font:600 25px/1.25 {!! $font !!};color:#111827">{{ $heading }}</h1>
                <p style="margin:0;font:14px/1.6 {!! $font !!};color:#6b7280">{{ __('emails.ui.sent_at', ['time' => $sentAt]) }}</p>
            </td>
        </tr>
        @if(filled($inquiry['message'] ?? null))
            <tr>
                <td class="px" style="padding:20px 32px 0">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-left:3px solid {{ $look['accent'] }};background:#f9fafb;border-radius:0 10px 10px 0">
                        <tr><td style="padding:16px 18px;font:15px/1.65 {!! $font !!};color:#1f2937">{!! nl2br(e($inquiry['message'])) !!}</td></tr>
                    </table>
                </td>
            </tr>
        @endif
        <tr>
            <td class="px" style="padding:22px 32px 0">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font:14px/1.5 {!! $font !!}">
                    <tr>
                        <td width="34%" style="padding:9px 0;color:#6b7280;border-bottom:1px solid #f0f1f3">{{ __('emails.ui.name') }}</td>
                        <td style="padding:9px 0;color:#111827;font-weight:600;border-bottom:1px solid #f0f1f3">{{ $inquiry['name'] ?? '' }}</td>
                    </tr>
                    @if($tel)
                        <tr>
                            <td style="padding:9px 0;color:#6b7280;border-bottom:1px solid #f0f1f3">{{ __('emails.ui.phone') }}</td>
                            <td style="padding:9px 0;border-bottom:1px solid #f0f1f3"><a href="{{ $tel }}" style="font-weight:600;text-decoration:none;color:{{ $look['accent'] }}">{{ $inquiry['phone'] }}</a></td>
                        </tr>
                    @endif
                    @if(filled($inquiry['email'] ?? null))
                        <tr>
                            <td style="padding:9px 0;color:#6b7280;border-bottom:1px solid #f0f1f3">{{ __('emails.ui.email') }}</td>
                            <td style="padding:9px 0;border-bottom:1px solid #f0f1f3"><a href="mailto:{{ $inquiry['email'] }}" style="font-weight:600;text-decoration:none;color:{{ $look['accent'] }}">{{ $inquiry['email'] }}</a></td>
                        </tr>
                    @endif
                    @foreach($extra as $label => $value)
                        <tr>
                            <td style="padding:9px 0;color:#6b7280;border-bottom:1px solid #f0f1f3">{{ $label }}</td>
                            <td style="padding:9px 0;color:#111827;border-bottom:1px solid #f0f1f3">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
        <tr>
            <td class="px" style="padding:24px 32px 30px">
                @if($tel)
                    @include('emails.partials.button', ['label' => __('emails.ui.call_back'), 'href' => $tel])
                @endif
                @if(filled($inquiry['email'] ?? null))
                    &nbsp;@include('emails.partials.button', ['label' => __('emails.ui.reply'), 'href' => 'mailto:'.$inquiry['email'], 'secondary' => (bool) $tel])
                @endif
                @if($intro !== '')
                    <p style="margin:16px 0 0;font:13px/1.6 {!! $font !!};color:#6b7280">{!! nl2br(e($intro)) !!}</p>
                @endif
            </td>
        </tr>
    </table>
@endsection
