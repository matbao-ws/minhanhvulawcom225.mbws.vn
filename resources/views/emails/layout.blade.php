{{--
    The frame of every email the shop sends (App\Services\EmailAppearance::look()):
    $look (layout, colours, logo, contact), $preheader (the inbox preview line), $footer (why the
    reader got it). Tables and inline styles only, so Gmail, Outlook and phone apps agree; the
    <style> block holds just the phone-width tweaks, which clients that drop it can live without.
--}}
@php
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $accent = $look['accent'];
    $minimal = $look['layout'] === 'minimal';
    $banner = $look['layout'] === 'banner';
    $align = $look['logo_align'] === 'left' ? 'left' : 'center';

    // A logo on this server's own disk, from a local or private address no inbox can reach, is
    // attached instead (cid:). Public https logos (Cloudinary, the live site) stay links.
    $logoUrl = $look['logo_url'];
    if ($logoUrl !== null && isset($message) && str_starts_with($logoUrl, rtrim(url('/'), '/').'/') && ! str_starts_with($logoUrl, 'https://')) {
        $logoPath = public_path(ltrim(substr($logoUrl, strlen(rtrim(url('/'), '/'))), '/'));
        if (is_file($logoPath)) {
            $logoUrl = $message->embed($logoPath);
        }
    }
    $initials = mb_strtoupper(collect(preg_split('/\s+/u', trim($look['shop'])))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: 'S';
    $socialLabels = ['facebook' => 'Facebook', 'zalo' => 'Zalo', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $look['shop'] }}</title>
    <style>
        body { margin: 0; padding: 0; }
        a { color: {{ $accent }}; }
        @media (max-width: 620px) {
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .stack td.col { display: block !important; width: 100% !important; padding: 0 0 16px !important; }
            .h1 { font-size: 22px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:{{ $minimal ? '#ffffff' : '#f3f4f6' }}">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all">{{ $preheader }}&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $minimal ? '#ffffff' : '#f3f4f6' }}">
    <tr>
        <td align="center" style="padding:{{ $minimal ? '20px 12px' : '28px 12px' }}">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">
                {{-- Logo above the card (classic, minimal) --}}
                @unless($banner)
                    <tr>
                        <td align="{{ $align }}" class="{{ $minimal ? 'px' : '' }}" style="padding:{{ $minimal ? '4px 32px 18px' : '4px 0 20px' }}{{ $minimal ? ';border-bottom:2px solid '.$accent : '' }}">
                            @include('emails.partials.logo')
                        </td>
                    </tr>
                @endunless

                <tr>
                    <td style="background:#ffffff;{{ $minimal ? '' : 'border-radius:14px;border:1px solid #e5e7eb;overflow:hidden' }}">
                        {{-- Banner: the shop's cover image, or an accent band, with the logo under it --}}
                        @if($banner)
                            @if($look['banner_url'])
                                <img src="{{ $look['banner_url'] }}" width="600" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;border-radius:14px 14px 0 0">
                            @else
                                <div style="height:10px;background:{{ $accent }};border-radius:14px 14px 0 0;font-size:0;line-height:0">&nbsp;</div>
                            @endif
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr><td align="{{ $align }}" class="px" style="padding:22px 32px 0">@include('emails.partials.logo')</td></tr>
                            </table>
                        @endif

                        @yield('content')
                    </td>
                </tr>

                {{-- Footer: signature, social links, the shop's contact details --}}
                <tr>
                    <td class="px" align="{{ $minimal ? 'left' : 'center' }}" style="padding:22px {{ $minimal ? '32px' : '28px' }} 8px;font:13px/1.6 {!! $font !!};color:#6b7280{{ $minimal ? ';border-top:1px solid #e5e7eb' : '' }}">
                        @if($look['signature'] !== '')
                            <div style="margin-bottom:12px;color:#374151;font-size:14px">{!! nl2br(e($look['signature'])) !!}</div>
                        @endif
                        <b style="color:#374151">{{ $look['shop'] }}</b>
                        @if($look['address'])<br>{{ $look['address'] }}@endif
                        @if($look['phone'] || $look['email'])
                            <br>
                            @if($look['phone']){{ __('emails.ui.hotline') }} <a href="tel:{{ preg_replace('/[^0-9+]/', '', $look['phone']) }}" style="color:#374151;text-decoration:none;font-weight:600">{{ $look['phone'] }}</a>@endif
                            @if($look['phone'] && $look['email']) · @endif
                            @if($look['email'])<a href="mailto:{{ $look['email'] }}" style="color:#374151;text-decoration:none">{{ $look['email'] }}</a>@endif
                        @endif
                        @if($look['socials'] !== [])
                            <div style="margin-top:10px">
                                @foreach($look['socials'] as $network => $url)
                                    <a href="{{ $url }}" style="display:inline-block;margin:0 4px;padding:4px 10px;border-radius:999px;background:#ffffff;border:1px solid #e5e7eb;color:#374151;text-decoration:none;font-weight:600;font-size:12px">{{ $socialLabels[$network] ?? $network }}</a>
                                @endforeach
                            </div>
                        @endif
                        <div style="margin-top:12px;color:#9ca3af;font-size:12px">{{ $footer }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
