{{-- The "Gửi email thử" email (App\Mail\MailSettingsTestMail): $rows = [label, value, monospace]. --}}
@extends('emails.layout')

@php($font = $look['font_stack'])

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="px" align="center" style="padding:34px 32px 22px">
                <div style="width:52px;height:52px;border-radius:50%;background:#ecfdf5;color:#047857;margin:0 auto;font:600 26px/52px {!! $font !!};text-align:center">&#10003;</div>
                <h1 class="h1" style="margin:16px 0 8px;font:600 25px/1.25 {!! $font !!};color:#111827">{{ $heading }}</h1>
                <p style="margin:0 auto;max-width:440px;font:15px/1.6 {!! $font !!};color:#4b5563">{!! nl2br(e($intro)) !!}</p>
                <p style="margin:8px 0 0;font:13px/1.6 {!! $font !!};color:#6b7280">{{ $sentAt }}</p>
            </td>
        </tr>
        <tr>
            <td class="px" style="padding:0 32px 30px">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font:14px/1.5 {!! $font !!};background:#f9fafb;border-radius:10px">
                    @foreach($rows as [$label, $value, $mono])
                        <tr>
                            <td width="38%" style="padding:10px 16px;color:#6b7280{{ $loop->last ? '' : ';border-bottom:1px solid #eef0f2' }}">{{ $label }}</td>
                            <td style="padding:10px 16px;color:#111827;font-weight:600{{ $mono ? ';font-family:Consolas,monospace' : '' }}{{ $loop->last ? '' : ';border-bottom:1px solid #eef0f2' }};word-break:break-word">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
                <p style="margin:14px 0 0;font:13px/1.6 {!! $font !!};color:#6b7280">{{ __('emails.ui.test_spam') }}</p>
            </td>
        </tr>
    </table>
@endsection
