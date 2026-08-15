<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif
    @stack('styles')
</head>
<body>
    {{-- Server-rendered from the database and marked uneditable: the inline
         page editor must never treat navigation as page content. --}}
    <header class="client-header" contenteditable="false">
        @include('client.partials.navigation', ['menuKey' => 'primary'])
    </header>

    @yield('content')

    @include('client.partials.admin-bar')
</body>
</html>
