{{-- Password reset, for a customer or an admin (App\Notifications\QueuedResetPassword). --}}
@extends('emails.layout')

@php($font = $look['font_stack'])

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="px" align="center" style="padding:34px 24px 6px">
                <div style="width:52px;height:52px;border-radius:50%;background:#f3f4f6;margin:0 auto;font:24px/52px {!! $font !!};text-align:center">&#128274;</div>
                <h1 class="h1" style="margin:16px 0 8px;font:600 25px/1.25 {!! $font !!};color:#111827">{{ $heading }}</h1>
                <p style="margin:0 auto;max-width:440px;font:15px/1.6 {!! $font !!};color:#4b5563">{!! nl2br(e($intro)) !!}</p>
                <p style="margin:10px 0 0;font:600 15px/1.6 {!! $font !!};color:#111827">{{ $account }}</p>
            </td>
        </tr>
        <tr><td class="px" align="center" style="padding:22px 24px 6px">@include('emails.partials.button', ['label' => __('emails.ui.reset_button'), 'href' => $url])</td></tr>
        <tr><td class="px" align="center" style="padding:10px 24px 0"><p style="margin:0;font:13px/1.6 {!! $font !!};color:#6b7280">{{ __('emails.ui.reset_expires', ['minutes' => $minutes]) }}</p></td></tr>
        <tr>
            <td class="px" style="padding:24px 24px 30px">
                <div style="border-top:1px solid #e5e7eb;padding-top:18px;font:13px/1.6 {!! $font !!};color:#6b7280">
                    {{ __('emails.ui.reset_fallback') }}<br>
                    <a href="{{ $url }}" style="word-break:break-all;color:{{ $look['accent'] }}">{{ $url }}</a>
                    <br><br><b style="font-weight:600;color:#374151">{{ __('emails.ui.reset_ignore') }}</b>
                </div>
            </td>
        </tr>
    </table>
@endsection
