  @php
      $sidebarUser = auth()->user();
      $sidebarFeatures = app(\App\Support\FeatureGate::class);
      $sidebarCoreVersion = app(\App\Support\CoreManifest::class)->version();
  @endphp

  <style>
      /* Override double sidebar styles to create a clean single sidebar layout */
      .side-mini-panel {
          width: 240px !important;
      }
      .side-mini-panel .nav-logo {
          left: 0 !important;
          width: 240px !important;
          border-right: 1px solid rgba(0, 0, 0, 0.05);
          padding: 0 20px !important;
      }
      .side-mini-panel .sidebarmenu .sidebar-nav {
          left: 0 !important;
          display: block !important;
          width: 240px !important;
          border-right: 1px solid rgba(0, 0, 0, 0.05);
      }
      
      /* Page wrapper margins */
      @media (min-width: 1300px) {
          html[data-layout="vertical"] .topbar {
              width: calc(100% - 240px) !important;
          }
          html[data-layout="vertical"] .page-wrapper {
              margin-left: 240px !important;
          }
      }
      
      /* Mobile drawer sidebar position */
      @media (max-width: 1299px) {
          .side-mini-panel {
              left: -240px !important;
              width: 240px !important;
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
  </style>

  <script>
      try {
          if (window.localStorage.getItem('admin-sidebar-icons-only') === 'true') {
              document.body.classList.add('sidebar-icons-only');
          }
      } catch (error) {
          // Storage can be unavailable in private browsing; the toggle still works for this page.
      }
  </script>

  <aside class="side-mini-panel with-vertical">
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
                          <li class="nav-small-cap">
                              <!-- <span class="hide-menu">E-Commerce</span> -->
                          </li>
                          
                          <!-- Trang quản trị -->
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.dashboard') }}" id="get-url" aria-expanded="false">
                                  <iconify-icon icon="solar:chart-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.dashboard') }}</span>
                              </a>
                          </li>

                          <!-- Đơn hàng -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'catalog'))
                              @can('orders.view')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.orders.index') }}" aria-expanded="false">
                                      <iconify-icon icon="solar:bill-list-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.orders') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          @can('customers.view')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.customers.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:users-group-two-rounded-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.customers') }}</span>
                              </a>
                          </li>
                          @endcan

                          <!-- Sản phẩm (Products, Categories, Brands) -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'catalog'))
                              @can('products.view')
                              <li class="sidebar-item">
                                  <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                      <iconify-icon icon="solar:cart-3-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.products') }}</span>
                                  </a>
                                  <ul aria-expanded="false" class="collapse first-level">
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.products.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.product_list') }}
                                          </a>
                                      </li>
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.categories.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.product_categories') }}
                                          </a>
                                      </li>
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.brands.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.brands') }}
                                          </a>
                                      </li>
                                  </ul>
                              </li>
                              @endcan
                          @endif

                          <!-- Đánh giá bình luận -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'review'))
                              @can('reviews.view')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.reviews.index') }}" aria-expanded="false">
                                      <iconify-icon icon="solar:chat-round-line-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.reviews') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          <!-- Liên hệ -->
                          @can('contacts.view')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.contact-submissions.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:letter-line-duotone"></iconify-icon>
                                  <span class="hide-menu">Liên hệ</span>
                              </a>
                          </li>
                          @endcan

                          <!-- Mã giảm giá -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'voucher') || $sidebarFeatures->availableTo($sidebarUser, 'catalog'))
                              @if($sidebarUser?->can('vouchers.view') || $sidebarUser?->can('promotions.view'))
                              <li class="sidebar-item">
                                  <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                      <iconify-icon icon="solar:ticket-sale-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.promotions') }}</span>
                                  </a>
                                  <ul aria-expanded="false" class="collapse first-level">
                                      @if($sidebarFeatures->availableTo($sidebarUser, 'voucher') && $sidebarUser?->can('vouchers.view'))
                                      <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.vouchers.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.vouchers') }}</a></li>
                                      @endif
                                      @if($sidebarFeatures->availableTo($sidebarUser, 'catalog') && $sidebarUser?->can('promotions.view'))
                                      <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.promotions.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.promotions_flash_sale') }}</a></li>
                                      @endif
                                  </ul>
                              </li>
                              @endif
                          @endif

                          <!-- Quản lý Banner -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'banner'))
                              @can('banners.view')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.banners.index') }}" aria-expanded="false">
                                      <iconify-icon icon="solar:gallery-bold-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.banners') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          <!-- Menu điều hướng storefront -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'menu'))
                              @can('menus.view')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.menus.index') }}" aria-expanded="false">
                                      <iconify-icon icon="solar:list-arrow-down-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.menu.menus') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          <!-- Bài viết (Posts, Post Categories) -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'cms_page'))
                              @can('posts.view')
                              <li class="sidebar-item">
                                  <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                      <iconify-icon icon="solar:widget-4-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.blog') }}</span>
                                  </a>
                                  <ul aria-expanded="false" class="collapse first-level">
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.posts.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.post_list') }}
                                          </a>
                                      </li>
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.post-categories.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.post_categories') }}
                                          </a>
                                      </li>
                                  </ul>
                              </li>
                              @endcan
                          @endif

                          @if($sidebarFeatures->availableTo($sidebarUser, 'cms_page'))
                              @can('pages.view')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.pages.index') }}" aria-expanded="false">
                                      <i class="ti ti-file-text fs-6" aria-hidden="true"></i>
                                      <span class="hide-menu">{{ __('admin.sidebar.pages') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          @if($sidebarFeatures->availableTo($sidebarUser, 'ai_seo'))
                              @can('ai.use')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.ai.seo-tasks.index') }}" aria-expanded="false">
                                      <i class="ti ti-chart-arrows-vertical fs-6" aria-hidden="true"></i>
                                      <span class="hide-menu">{{ __('ai.tasks.title') }}</span>
                                  </a>
                              </li>
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.ai.bulk.index') }}" aria-expanded="false">
                                      <i class="ti ti-stack-2 fs-6" aria-hidden="true"></i>
                                      <span class="hide-menu">{{ __('ai.bulk.title') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          <!-- Quản lý người dùng -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'multi_admin'))
                               @if($sidebarUser?->can('users.view') || $sidebarUser?->can('roles.view'))
                               <li class="sidebar-item">
                                  <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                      <iconify-icon icon="solar:shield-user-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.user_management') }}</span>
                                  </a>
                                  <ul aria-expanded="false" class="collapse first-level">
                                      @can('users.view')
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.users.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.user_list') }}
                                          </a>
                                      </li>
                                      @endcan
                                      @can('roles.view')
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.roles.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.roles_permissions') }}
                                          </a>
                                      </li>
                                      @endcan
                                  </ul>
                              </li>
                              @endif
                          @endif

                          <!-- Cấu hình -->
                          @if($sidebarUser?->can('settings.view') || $sidebarUser?->can('shipping.view') || $sidebarUser?->can('payments.view') || $sidebarUser?->can('languages.view') || $sidebarUser?->isSuperAdmin())
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
                                  @if($sidebarUser?->isSuperAdmin())
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.features.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.feature_settings') }}
                                      </a>
                                  </li>
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.core-update.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.core_update') }}
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
                          @foreach(app(\App\Extension\Registries\AdminMenu::class)->visibleTo($sidebarUser) as $extensionItem)
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

                          @can('activity_logs.view')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.activity-logs.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:history-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.activity_logs') }}</span>
                              </a>
                          </li>
                          @endcan

                          <!-- Media Library -->
                          @can('media.view')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.media.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:gallery-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.media_library') }}</span>
                              </a>
                          </li>
                          @endcan
                      </ul>
                  </nav>
                  {{-- Which core this website runs: the first thing to look at after an update. --}}
                  <div class="hide-menu px-4 pt-3 pb-4 fs-2 text-muted" data-core-version="{{ $sidebarCoreVersion }}">
                      @if($sidebarUser?->isSuperAdmin())
                      <a href="{{ route('admin.core-update.index') }}" class="text-muted">{{ __('admin.sidebar.core_version', ['version' => $sidebarCoreVersion]) }}</a>
                      @else
                      {{ __('admin.sidebar.core_version', ['version' => $sidebarCoreVersion]) }}
                      @endif
                  </div>
              </div>
          </div>
      </div>
  </aside>

  <script>
      document.addEventListener('DOMContentLoaded', () => {
          const compactClass = 'sidebar-icons-only';
          const storageKey = 'admin-sidebar-icons-only';
          const toggles = [...document.querySelectorAll('[data-sidebar-collapse-toggle]')];

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

          document.querySelectorAll('#sidebarnav .sidebar-menu > .sidebar-item > .sidebar-link').forEach((link) => {
              const label = link.querySelector('.hide-menu')?.textContent?.trim();
              if (label) {
                  link.setAttribute('title', label);
              }
          });

          toggles.forEach((toggle) => {
              toggle.addEventListener('click', () => {
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
