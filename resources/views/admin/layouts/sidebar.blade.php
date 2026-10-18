  @php
      $sidebarUser = auth()->user();
      $sidebarFeatures = app(\App\Support\FeatureGate::class);
      $sidebarCanUpdateCore = app(\App\Support\CoreUpdate\CoreUpdateAccess::class)->allows($sidebarUser);
  @endphp

  <style>
      /* Override double sidebar styles to create a clean single sidebar layout */
      .side-mini-panel {
          width: 264px !important;
      }
      .side-mini-panel .nav-logo {
          left: 0 !important;
          width: 264px !important;
          border-right: 1px solid rgba(0, 0, 0, 0.05);
          padding: 0 20px !important;
          justify-content: center;
      }
      .side-mini-panel .sidebarmenu .sidebar-nav {
          left: 0 !important;
          display: block !important;
          width: 264px !important;
          border-right: 1px solid rgba(0, 0, 0, 0.05);
      }
      
      /* Page wrapper margins */
      @media (min-width: 1300px) {
          html[data-layout="vertical"] .topbar {
              width: calc(100% - 264px) !important;
          }
          html[data-layout="vertical"] .page-wrapper {
              margin-left: 264px !important;
          }
      }
      
      /* Mobile drawer sidebar position */
      @media (max-width: 1299px) {
          .side-mini-panel {
              left: -264px !important;
              width: 264px !important;
          }
          .show-sidebar .side-mini-panel {
              left: 0 !important;
          }
      }
      /* Sidebar link font weight, size & alignment */
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link {
          font-weight: 600 !important;
          font-size: 15px !important;
          display: flex !important;
          align-items: center !important;
          gap: 12px !important;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link .hide-menu {
          font-weight: 600 !important;
          font-size: 15px !important;
      }
      /* Long labels (translations, extension entries) wrap instead of running past the panel. */
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link {
          white-space: normal !important;
          line-height: 1.35;
          overflow-wrap: anywhere;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link .hide-menu {
          flex: 1 1 auto;
          min-width: 0;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link > iconify-icon,
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link > i,
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link > .icon-small {
          flex-shrink: 0;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item > .sidebar-link {
          gap: 10px !important;
          padding-right: 12px !important;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link.has-arrow {
          padding-right: 32px !important;
      }
      .side-mini-panel .sidebar-nav ul .nav-small-cap .hide-menu {
          font-weight: 600 !important;
          font-size: 13px !important;
          opacity: 0.8;
      }
      /* Sidebar icon color, opacity & size */
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link iconify-icon {
          opacity: 0.95 !important;
          color: #2a3547 !important;
          font-size: 19px !important;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link.active iconify-icon,
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link:hover iconify-icon,
      .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link[aria-expanded="true"] iconify-icon {
          color: inherit !important;
          opacity: 1 !important;
      }
      /* Keep the active navigation state aligned with the soft red admin palette. */
      .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link,
      .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link.active,
      .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link.active {
          background-color: #fff0f1 !important;
          color: #d11e28 !important;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link.has-arrow::after,
      .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link.active.has-arrow::after {
          border-color: #d11e28 !important;
      }
      /* Sidebar dot styling & anti-distortion */
      .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item .icon-small {
          background-color: #2a3547 !important;
          opacity: 0.95 !important;
          width: 7px !important;
          height: 7px !important;
          border-radius: 50% !important;
          flex-shrink: 0 !important;
          display: inline-block !important;
          aspect-ratio: 1 / 1 !important;
          margin: 0 !important;
      }
      .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item .sidebar-link:hover .icon-small,
      .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item .sidebar-link.active .icon-small {
          background-color: var(--bs-primary) !important;
          opacity: 1 !important;
      }

      /* Desktop compact mode: leave the primary navigation icons visible. */
      .sidebar-mini-logo {
          display: none;
      }
      .sidebar-collapse-toggle {
          display: none !important;
          padding: 0 8px;
          border: 0 !important;
          background: transparent !important;
          color: #2a3547;
      }
      .sidebar-collapse-toggle:hover,
      .sidebar-collapse-toggle:focus-visible {
          color: #d11e28;
      }
      .sidebar-collapse-toggle .sidebar-collapse-icon {
          width: 20px;
          height: 20px;
          stroke: currentColor;
      }

      @media (min-width: 1300px) {
          .sidebar-collapse-toggle {
              display: inline-flex !important;
          }
          body.sidebar-icons-only .side-mini-panel,
          body.sidebar-icons-only .side-mini-panel .nav-logo,
          body.sidebar-icons-only .side-mini-panel .sidebarmenu .sidebar-nav {
              width: 76px !important;
          }
          body.sidebar-icons-only .side-mini-panel .nav-logo {
              padding: 0 12px !important;
              justify-content: center;
          }
          html[data-layout="vertical"] body.sidebar-icons-only .topbar {
              width: calc(100% - 76px) !important;
          }
          html[data-layout="vertical"] body.sidebar-icons-only .page-wrapper {
              margin-left: 76px !important;
          }
          body.sidebar-icons-only .side-mini-panel .logo-img {
              display: none !important;
          }
          body.sidebar-icons-only .side-mini-panel .sidebar-mini-logo {
              display: block;
              width: 34px;
              height: 34px;
              object-fit: contain;
          }
          body.sidebar-icons-only .side-mini-panel .sidebar-menu > .sidebar-item > .sidebar-link {
              justify-content: center;
              gap: 0 !important;
              padding-left: 0 !important;
              padding-right: 0 !important;
          }
          body.sidebar-icons-only .side-mini-panel .sidebar-menu > .sidebar-item > .sidebar-link .hide-menu,
          body.sidebar-icons-only .side-mini-panel .sidebar-menu > .nav-small-cap,
          body.sidebar-icons-only .side-mini-panel .sidebar-menu > .sidebar-item > .sidebar-link.has-arrow::after,
          body.sidebar-icons-only .side-mini-panel .sidebar-menu > .sidebar-item > .first-level {
              display: none !important;
          }
      }

      /* Compact mode: the label of a hovered icon floats beside the sidebar (filled by the script below). */
      .sidebar-tooltip {
          position: fixed;
          z-index: 1080;
          padding: 6px 12px;
          border-radius: 8px;
          background: #2a3547;
          color: #fff;
          font-size: 13.5px;
          font-weight: 600;
          white-space: nowrap;
          pointer-events: none;
          box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
          opacity: 0;
          transform: translate(-4px, -50%);
          transition: opacity 0.12s ease, transform 0.12s ease;
      }
      .sidebar-tooltip::before {
          content: "";
          position: absolute;
          top: 50%;
          left: -5px;
          margin-top: -5px;
          border-width: 5px 5px 5px 0;
          border-style: solid;
          border-color: transparent #2a3547 transparent transparent;
      }
      .sidebar-tooltip.is-visible {
          opacity: 1;
          transform: translate(0, -50%);
      }
      body.sidebar-dark .sidebar-tooltip {
          background: #fff;
          color: #2a3547;
      }
      body.sidebar-dark .sidebar-tooltip::before {
          border-right-color: #fff;
      }

      /* Header switch for the dark sidebar. */
      .sidebar-dark-toggle {
          display: inline-flex;
          align-items: center;
          padding: 0 8px;
          border: 0;
          background: transparent;
          color: #2a3547;
          font-size: 20px;
      }
      .sidebar-dark-toggle:hover,
      .sidebar-dark-toggle:focus-visible {
          color: #d11e28;
      }
      [data-sidebar-dark-toggle] .sidebar-dark-icon-on,
      body.sidebar-dark [data-sidebar-dark-toggle] .sidebar-dark-icon-off {
          display: none;
      }
      body.sidebar-dark [data-sidebar-dark-toggle] .sidebar-dark-icon-on {
          display: inline-block;
      }

      /* Dark sidebar. */
      body.sidebar-dark .side-mini-panel,
      body.sidebar-dark .side-mini-panel .nav-logo,
      body.sidebar-dark .side-mini-panel .sidebarmenu .sidebar-nav {
          background: #1f2633 !important;
          border-right-color: rgba(255, 255, 255, 0.06) !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link .hide-menu,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .nav-small-cap {
          color: #c9d1de !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link iconify-icon,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link i {
          color: #c9d1de !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item .icon-small {
          background-color: #c9d1de !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link.has-arrow::after {
          border-color: #c9d1de !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link:hover,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link:hover .hide-menu,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link:hover iconify-icon,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .sidebar-link:hover i {
          color: #fff !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link:hover {
          background-color: rgba(255, 255, 255, 0.06);
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item .sidebar-link:hover .icon-small {
          background-color: #fff !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link.active,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link.active {
          background-color: rgba(209, 30, 40, 0.22) !important;
          color: #ff8a90 !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link .hide-menu,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link iconify-icon,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link i,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link.active .hide-menu,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link.active iconify-icon,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link.active i {
          color: #ff8a90 !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item.selected > .sidebar-link.has-arrow::after,
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item > .sidebar-link.active.has-arrow::after {
          border-color: #ff8a90 !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item .sidebar-link.active {
          background-color: transparent !important;
          color: #ff8a90 !important;
      }
      body.sidebar-dark .side-mini-panel .sidebar-nav ul .sidebar-item .first-level .sidebar-item .sidebar-link.active .icon-small {
          background-color: #ff8a90 !important;
      }
      body.sidebar-dark .side-mini-panel .simplebar-scrollbar::before {
          background: rgba(255, 255, 255, 0.4);
      }
  </style>

  <script>
      try {
          if (window.localStorage.getItem('admin-sidebar-icons-only') === 'true') {
              document.body.classList.add('sidebar-icons-only');
          }
          if (window.localStorage.getItem('admin-sidebar-dark') === 'true') {
              document.body.classList.add('sidebar-dark');
          }
      } catch (error) {
          // Storage can be unavailable in private browsing; the toggle still works for this page.
      }
  </script>

  <aside data-tour="layout.sidebar" class="side-mini-panel with-vertical">
      <!-- ---------------------------------- -->
      <!-- Start Vertical Layout Sidebar -->
      <!-- ---------------------------------- -->
      <div>
          <div>
              <div class="sidebarmenu">
                  <div class="brand-logo d-flex align-items-center nav-logo">
                      <a href="{{ route('admin.dashboard') }}" class="text-nowrap logo-img d-flex align-items-center">
                          <img src="{{ $siteBranding['admin_logo_url'] }}" width="150" alt="MatBaoWS" class="img-fluid">
                      </a>
                          <a href="{{ route('admin.dashboard') }}" class="sidebar-mini-logo" aria-label="{{ $siteBranding['name'] }}">
                              <img src="{{ $siteBranding['favicon_url'] }}" alt="" class="sidebar-mini-logo">
                          </a>
                  </div>

                  <!-- ---------------------------------- -->
                  <!-- Icon 1: E-Commerce Panel -->
                  <!-- ---------------------------------- -->
                  <nav class="sidebar-nav" id="menu-right-mini-1" data-simplebar="">
                      <ul class="sidebar-menu" id="sidebarnav">
                          @php
                              // One place decides what each account sees; a section heading shows only above visible items.
                              $sidebarCatalog = $sidebarFeatures->availableTo($sidebarUser, 'catalog');
                              $sidebarCms = $sidebarFeatures->availableTo($sidebarUser, 'cms_page');
                              // AI items are always listed; a locked one says why when clicked (App\Support\AiAccess).
                              $sidebarAi = app(\App\Support\AiAccess::class);
                              $sidebarAiAccess = [
                                  'seo_tasks' => $sidebarAi->check($sidebarUser, 'ai_seo'),
                                  'seo_research' => $sidebarAi->check($sidebarUser, 'ai_research'),
                                  'seo_bulk' => $sidebarAi->check($sidebarUser, 'ai_seo'),
                              ];
                              $sidebarCan = fn (string $ability): bool => (bool) $sidebarUser?->can($ability);
                              $sidebarShow = [
                                  'orders' => $sidebarCatalog && $sidebarCan('orders.view'),
                                  'customers' => $sidebarCan('customers.view'),
                                  'contacts' => $sidebarCan('contacts.view'),
                                  'products' => $sidebarCatalog && $sidebarCan('products.view'),
                                  'vouchers' => $sidebarFeatures->availableTo($sidebarUser, 'voucher') && $sidebarCan('vouchers.view'),
                                  'promotions' => $sidebarCatalog && $sidebarFeatures->availableTo($sidebarUser, 'voucher') && $sidebarCan('promotions.view'),
                                  'reviews' => $sidebarFeatures->availableTo($sidebarUser, 'review') && $sidebarCan('reviews.view'),
                                  'posts' => $sidebarCms && $sidebarCan('posts.view'),
                                  'pages' => $sidebarCms && $sidebarCan('pages.view'),
                                  'banners' => $sidebarFeatures->availableTo($sidebarUser, 'banner') && $sidebarCan('banners.view'),
                                  'menus' => $sidebarFeatures->availableTo($sidebarUser, 'menu') && $sidebarCan('menus.view'),
                                  'media' => $sidebarCan('media.view'),
                                  'seo_tasks' => true,
                                  'seo_research' => true,
                                  'seo_bulk' => true,
                                  // "Viết bằng AI" is not listed: it starts from the post list (and keyword research).
                                  'redirects' => $sidebarCan('redirects.view'),
                                  'users' => $sidebarFeatures->availableTo($sidebarUser, 'multi_admin') && $sidebarCan('users.view'),
                                  'roles' => $sidebarFeatures->availableTo($sidebarUser, 'multi_admin') && $sidebarCan('roles.view'),
                                  'settings' => $sidebarCan('settings.view') || $sidebarCan('shipping.view') || $sidebarCan('payments.view') || $sidebarCan('languages.view') || (bool) $sidebarUser?->isSuperAdmin(),
                                  'activity_logs' => $sidebarCan('activity_logs.view'),
                              ];
                              $sidebarExtensionItems = app(\App\Extension\Registries\AdminMenu::class)->visibleTo($sidebarUser);
                              $sidebarAny = fn (string ...$keys): bool => collect($keys)->contains(fn ($key) => $sidebarShow[$key]);
                          @endphp

                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.dashboard') }}" id="get-url" aria-expanded="false">
                                  <iconify-icon icon="solar:chart-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.dashboard') }}</span>
                              </a>
                          </li>

                          {{-- Bán hàng --}}
                          @if($sidebarShow['orders'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.orders.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:bill-list-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.orders') }}</span>
                              </a>
                          </li>
                          @endif
                          @if($sidebarShow['customers'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.customers.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:users-group-two-rounded-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.customers') }}</span>
                              </a>
                          </li>
                          @endif
                          @if($sidebarShow['contacts'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.contact-submissions.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:letter-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.contacts') }}</span>
                              </a>
                          </li>
                          @endif
                          @if($sidebarShow['products'])
                          <li class="sidebar-item">
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="solar:cart-3-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.products') }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.products.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.product_list') }}</a></li>
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.categories.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.product_categories') }}</a></li>
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.brands.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.brands') }}</a></li>
                              </ul>
                          </li>
                          @endif
                          @if($sidebarAny('vouchers', 'promotions'))
                          <li class="sidebar-item">
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="solar:ticket-sale-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.promotions') }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  @if($sidebarShow['vouchers'])
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.vouchers.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.vouchers') }}</a></li>
                                  @endif
                                  @if($sidebarShow['promotions'])
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.promotions.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.promotions_flash_sale') }}</a></li>
                                  @endif
                              </ul>
                          </li>
                          @endif
                          @if($sidebarShow['reviews'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.reviews.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:chat-round-line-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.reviews') }}</span>
                              </a>
                          </li>
                          @endif

                          {{-- Nội dung & SEO --}}
                          @if($sidebarShow['posts'])
                          <li class="sidebar-item">
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="solar:widget-4-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.blog') }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.posts.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.post_list') }}</a></li>
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.post-categories.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.post_categories') }}</a></li>
                              </ul>
                          </li>
                          @endif
                          @if($sidebarShow['pages'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.pages.index') }}" aria-expanded="false">
                                  <i class="ti ti-file-text fs-6" aria-hidden="true"></i>
                                  <span class="hide-menu">{{ __('admin.sidebar.pages') }}</span>
                              </a>
                          </li>
                          @endif
                          @if($sidebarShow['banners'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.banners.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:gallery-bold-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.banners') }}</span>
                              </a>
                          </li>
                          @endif
                          @if($sidebarShow['menus'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.menus.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:list-arrow-down-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.menu.menus') }}</span>
                              </a>
                          </li>
                          @endif
                          @if($sidebarShow['media'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.media.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:gallery-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.media_library') }}</span>
                              </a>
                          </li>
                          @endif
                          @if($sidebarAny('seo_tasks', 'seo_research', 'seo_bulk', 'redirects'))
                          <li class="sidebar-item" data-sidebar-seo-menu>
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="solar:graph-up-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.seo') }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  @foreach([
                                      'seo_tasks' => ['admin.ai.seo-tasks.index', __('ai.tasks.title')],
                                      'seo_research' => ['admin.ai.research.index', __('ai.research.title')],
                                      'seo_bulk' => ['admin.ai.bulk.index', __('ai.bulk.title')],                                  ] as $sidebarKey => [$sidebarRoute, $sidebarLabel])
                                      @if($sidebarShow[$sidebarKey])
                                          @php
                                              $sidebarAccess = $sidebarAiAccess[$sidebarKey];
                                          @endphp
                                          <li class="sidebar-item">
                                              @if($sidebarAccess['allowed'])
                                                  <a class="sidebar-link" href="{{ route($sidebarRoute) }}"><span class="icon-small"></span>{{ $sidebarLabel }}</a>
                                              @else
                                                  <a class="sidebar-link is-ai-locked" href="{{ route($sidebarRoute) }}"{!! \App\Support\AiAccess::lockAttributes($sidebarAccess) !!} title="{{ $sidebarAccess['message'] }}" data-sidebar-ai-locked="{{ $sidebarKey }}"><span class="icon-small"></span>{{ $sidebarLabel }}<i class="ti ti-lock ai-lock-icon" aria-hidden="true"></i></a>
                                              @endif
                                          </li>
                                      @endif
                                  @endforeach
                                  @if($sidebarShow['redirects'])
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.redirects.index') }}"><span class="icon-small"></span>{{ __('redirects.title') }}</a></li>
                                  @endif
                              </ul>
                          </li>
                          @endif

                          {{-- Hệ thống --}}
                          @if($sidebarAny('users', 'roles'))
                          <li class="sidebar-item">
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="solar:shield-user-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.user_management') }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  @if($sidebarShow['users'])
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.users.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.user_list') }}</a></li>
                                  @endif
                                  @if($sidebarShow['roles'])
                                  <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.roles.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.roles_permissions') }}</a></li>
                                  @endif
                              </ul>
                          </li>
                          @endif

                          <!-- Cấu hình -->
                          @if($sidebarShow['settings'])
                          <li class="sidebar-item" data-sidebar-settings-menu>
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="solar:settings-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.settings') }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  @can('settings.view')
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.settings.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.general_settings') }}
                                      </a>
                                  </li>
                                  @endcan
                                  @if($sidebarFeatures->availableTo($sidebarUser, 'shipping') && $sidebarUser?->can('shipping.view'))
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.shipping-partners.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.shipping_settings') }}
                                      </a>
                                  </li>
                                  @endif
                                  @if(($sidebarFeatures->availableTo($sidebarUser, 'cod_order') || $sidebarFeatures->availableTo($sidebarUser, 'online_payment')) && $sidebarUser?->can('payments.view'))
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.payment-methods.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.payment_settings') }}
                                      </a>
                                  </li>
                                  @endif
                                  @can('settings.view')
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.notification-settings.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.notification_settings') }}
                                      </a>
                                  </li>
                                  @endcan
                                  @if($sidebarFeatures->availableTo($sidebarUser, 'multilingual') && $sidebarUser?->can('languages.view'))
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.languages.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.content_languages') }}
                                      </a>
                                  </li>
                                  @endif
                                  @if($sidebarCanUpdateCore)
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.core-update.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.core_update') }}
                                      </a>
                                  </li>
                                  @endif
                                  @if($sidebarUser?->isSuperAdmin())
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.features.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.feature_settings') }}
                                      </a>
                                  </li>
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.extensions.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.extensions') }}
                                      </a>
                                  </li>
                                  @endif
                              </ul>
                          </li>
                          @endif

                          {{-- Entries of the website's extensions (App\Extension\Registries\AdminMenu), each only for an account allowed to open it. --}}
                          @foreach($sidebarExtensionItems as $extensionItem)
                          <li class="sidebar-item" data-extension-menu="{{ $extensionItem['key'] }}">
                              @if($extensionItem['children'] !== [])
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="{{ $extensionItem['icon'] ?? 'solar:widget-add-line-duotone' }}"></iconify-icon>
                                  <span class="hide-menu">{{ $extensionItem['label'] }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  @if($extensionItem['url'])
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ $extensionItem['url'] }}"><span class="icon-small"></span>{{ $extensionItem['label'] }}</a>
                                  </li>
                                  @endif
                                  @foreach($extensionItem['children'] as $extensionChild)
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ $extensionChild['url'] }}"><span class="icon-small"></span>{{ $extensionChild['label'] }}</a>
                                  </li>
                                  @endforeach
                              </ul>
                              @else
                              <a class="sidebar-link" href="{{ $extensionItem['url'] }}" aria-expanded="false">
                                  <iconify-icon icon="{{ $extensionItem['icon'] ?? 'solar:widget-add-line-duotone' }}"></iconify-icon>
                                  <span class="hide-menu">{{ $extensionItem['label'] }}</span>
                              </a>
                              @endif
                          </li>
                          @endforeach

                          @if($sidebarShow['activity_logs'])
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.activity-logs.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:history-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.activity_logs') }}</span>
                              </a>
                          </li>
                          @endif
                      </ul>
                  </nav>
              </div>
          </div>
      </div>
  </aside>

  <script>
      document.addEventListener('DOMContentLoaded', () => {
          const compactClass = 'sidebar-icons-only';
          const storageKey = 'admin-sidebar-icons-only';
          const toggles = [...document.querySelectorAll('[data-sidebar-collapse-toggle]')];
          const darkClass = 'sidebar-dark';
          const darkStorageKey = 'admin-sidebar-dark';
          const darkToggles = [...document.querySelectorAll('[data-sidebar-dark-toggle]')];

          const syncDarkToggle = () => {
              const dark = document.body.classList.contains(darkClass);
              const label = dark ? @json(__('admin.sidebar.dark_off')) : @json(__('admin.sidebar.dark_on'));
              darkToggles.forEach((toggle) => {
                  toggle.setAttribute('aria-pressed', String(dark));
                  toggle.setAttribute('aria-label', label);
                  toggle.setAttribute('title', label);
              });
          };

          darkToggles.forEach((toggle) => {
              toggle.addEventListener('click', () => {
                  const dark = document.body.classList.toggle(darkClass);
                  try {
                      window.localStorage.setItem(darkStorageKey, String(dark));
                  } catch (error) {
                      // Keep the chosen state for the current page when storage is unavailable.
                  }
                  syncDarkToggle();
              });
          });
          syncDarkToggle();

          // Compact mode hides every label, so the hovered icon shows its label beside the sidebar.
          // The bubble lives on <body>: the scrolling nav would clip anything placed inside it.
          const tooltip = document.createElement('div');
          tooltip.className = 'sidebar-tooltip';
          tooltip.setAttribute('role', 'tooltip');
          tooltip.hidden = true;
          document.body.appendChild(tooltip);

          const hideTooltip = () => {
              tooltip.classList.remove('is-visible');
              tooltip.hidden = true;
          };
          const showTooltip = (link) => {
              const label = link.dataset.sidebarTooltip;
              if (!label || !document.body.classList.contains(compactClass) || !window.matchMedia('(min-width: 1300px)').matches) {
                  return;
              }
              const rect = link.getBoundingClientRect();
              tooltip.textContent = label;
              tooltip.style.left = `${rect.right + 10}px`;
              tooltip.style.top = `${rect.top + rect.height / 2}px`;
              tooltip.hidden = false;
              requestAnimationFrame(() => tooltip.classList.add('is-visible'));
          };

          document.querySelectorAll('#sidebarnav > .sidebar-item > .sidebar-link').forEach((link) => {
              const label = link.querySelector('.hide-menu')?.textContent?.trim();
              if (!label) {
                  return;
              }
              link.dataset.sidebarTooltip = label;
              link.setAttribute('aria-label', label);
              link.addEventListener('mouseenter', () => showTooltip(link));
              link.addEventListener('focus', () => showTooltip(link));
              link.addEventListener('mouseleave', hideTooltip);
              link.addEventListener('blur', hideTooltip);
              link.addEventListener('click', hideTooltip);
          });
          document.querySelector('#sidebarnav')?.closest('.sidebar-nav')?.addEventListener('scroll', hideTooltip, true);
          window.addEventListener('resize', hideTooltip);

          if (!toggles.length) {
              return;
          }

          const syncToggle = () => {
              const compact = document.body.classList.contains(compactClass);
              const label = compact ? @json(__('admin.sidebar.expand')) : @json(__('admin.sidebar.collapse'));
              toggles.forEach((toggle) => {
                  toggle.setAttribute('aria-pressed', String(compact));
                  toggle.setAttribute('aria-label', label);
                  toggle.setAttribute('title', label);
              });
          };

          toggles.forEach((toggle) => {
              toggle.addEventListener('click', () => {
                  hideTooltip();
                  const compact = document.body.classList.toggle(compactClass);
                  try {
                      window.localStorage.setItem(storageKey, String(compact));
                  } catch (error) {
                      // Keep the chosen state for the current page when storage is unavailable.
                  }
                  syncToggle();
              });
          });

          document.querySelectorAll('#sidebarnav .sidebar-link.has-arrow').forEach((link) => {
              link.addEventListener('click', () => {
                  if (!document.body.classList.contains(compactClass)) {
                      return;
                  }

                  document.body.classList.remove(compactClass);
                  try {
                      window.localStorage.setItem(storageKey, 'false');
                  } catch (error) {
                      // See storage fallback above.
                  }
                  syncToggle();
              });
          });

          syncToggle();
      });
  </script>
