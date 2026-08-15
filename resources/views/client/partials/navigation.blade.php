@php
    /**
     * Storefront navigation. Items come from App\Services\MenuService so the
     * Blade layout and /api/public/menus/{key} always agree; this partial only
     * renders what the service resolved.
     */
    $items = app(\App\Services\MenuService::class)->tree($menuKey ?? 'primary');
@endphp

@if($items->isNotEmpty())
    <nav class="client-nav" aria-label="{{ __('admin.menus.title') }}">
        @include('client.partials.navigation-branch', ['items' => $items, 'level' => 0])
    </nav>
@endif
