<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Cửa hàng mẫu</title>
    <link rel="stylesheet" href="{{ asset('client-assets/css/theme.css') }}">
    @stack('styles')
</head>
<body>
    @include('client.partials.header')
    <main>@yield('content')</main>
    @include('client.partials.footer')
    <script src="{{ asset('client-assets/js/theme.js') }}" defer></script>
</body>
</html>
