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
    @include('client.partials.google-analytics')
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
         which the formatting toolbar attaches to on DOMContentLoaded.

         Each of these asks `ClientEditorChrome::editorActive()` itself, so they
         disappear together for a visitor and for the responsive preview frame — which
         loads this same URL with the editor suppressed, and would otherwise hold a
         second admin bar and a second owner of edit mode. Gating them here as well
         would be a second mechanism for one rule, and the partial's own check is the
         one a replacement theme layout cannot forget. --}}
    @include('client.partials.admin-bar')
    @include('client.partials.inline-blocks')
    @include('client.partials.inline-outline')
    @include('client.partials.inline-section-panel')
    @include('client.partials.inline-viewport')
    @include('client.partials.inline-history-panel')
</body>
</html>
