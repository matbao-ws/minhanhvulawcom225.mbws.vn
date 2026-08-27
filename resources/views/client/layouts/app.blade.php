<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif
    <link rel="stylesheet" href="{{ asset('client-assets/css/storefront.css') }}">
    @stack('styles')
</head>
<body>
    {{-- Header and footer are rendered from the Menu admin and site settings.
         Database-driven blocks get no inline edit hooks — they are edited on
         the screen that owns them — so both carry contenteditable="false". --}}
    <header class="client-header" contenteditable="false">
        <div class="client-shell client-header__inner">
            <a class="client-brand" href="{{ url('/'.app()->getLocale()) }}">{{ $siteBranding['name'] ?? config('app.name') }}</a>
            @include('client.partials.navigation', ['menuKey' => 'primary'])
        </div>
    </header>

    @yield('content')

    <footer class="client-footer" contenteditable="false">
        <div class="client-shell client-footer__inner">
            <p class="client-footer__note">
                © {{ now()->year }} {{ $siteBranding['name'] ?? config('app.name') }}
            </p>
            @include('client.partials.navigation', ['menuKey' => 'footer'])
        </div>
    </footer>

    {{-- Order matters: the bar owns edit mode and publishes window.clientBlocks,
         which the formatting toolbar attaches to on DOMContentLoaded. --}}
    @include('client.partials.admin-bar')
    @include('client.partials.inline-blocks')
    @include('client.partials.inline-outline')
</body>
</html>
