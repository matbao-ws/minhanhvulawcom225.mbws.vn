<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $siteBranding['name'])</title>
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
        .header-logo {
            display: block;
            width: auto;
            height: 42px;
            max-width: 180px;
            margin: 0 auto 14px;
            object-fit: contain;
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
            color: #475569;
            font-size: 15px;
            line-height: 1.6;
        }
        .content p {
            margin-top: 0;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 24px;
            margin-bottom: 12px;
            border-left: 4px solid #5d87ff;
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
            border-bottom: 1px solid #f1f5f9;
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
        .message-box {
            background-color: #f8fafc;
            border-left: 4px solid #5d87ff;
            padding: 15px;
            border-radius: 0 4px 4px 0;
            color: #334155;
            white-space: pre-line;
        }
        .badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-info { background-color: #dbeafe; color: #1e40af; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #eef2f6;
            clear: both;
        }
        @yield('styles')
    </style>
</head>
@php
    // The default fallback logo is a local public/ file, served from APP_URL.
    // In local/dev environments APP_URL is typically unreachable from outside
    // (e.g. http://localhost:8000), so a plain <img src> would show broken in
    // a real inbox. Embed it as an inline (CID) attachment in that case. A
    // real project logo (uploaded via Settings) is stored on Cloudinary and is
    // already a public https URL, so it's left untouched.
    $logoUrl = $siteBranding['logo_url'];
    $appBaseUrl = rtrim(url('/'), '/');
    if (isset($message) && str_starts_with($logoUrl, $appBaseUrl.'/')) {
        $logoLocalPath = public_path(ltrim(substr($logoUrl, strlen($appBaseUrl)), '/'));
        if (is_file($logoLocalPath)) {
            $logoUrl = $message->embed($logoLocalPath);
        }
    }
@endphp
<body>
    <div class="container">
        <div class="header">
            <img class="header-logo" src="{{ $logoUrl }}" alt="{{ $siteBranding['name'] }}">
            <h1>{{ $siteBranding['name'] }}</h1>
            @hasSection('header-subtitle')
                <p>@yield('header-subtitle')</p>
            @endif
        </div>
        <div class="content">
            @yield('content')
        </div>
        <div class="footer">
            <p>{{ $siteBranding['name'] }} &copy; {{ date('Y') }}</p>
            @if(filled(data_get($siteBranding, 'contact.email')))
                <p>Liên hệ hỗ trợ: {{ data_get($siteBranding, 'contact.email') }}</p>
            @endif
            <p>Email này được gửi tự động, vui lòng không phản hồi.</p>
        </div>
    </div>
</body>
</html>
