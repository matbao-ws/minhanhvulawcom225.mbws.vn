@php
  $items = app(\App\Services\MenuService::class)->tree($menuKey ?? 'primary');
@endphp

@if($items->isNotEmpty())
  <nav class="sticky top-0 z-50 bg-navy-900 text-white shadow-xl hidden lg:block border-t border-navy-800 client-nav" aria-label="{{ __('admin.menus.title') }}" contenteditable="false">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      @include('client.partials.navigation-branch', ['items' => $items, 'level' => 0, 'activeNav' => $activeNav ?? ''])
    </div>
  </nav>
@endif
