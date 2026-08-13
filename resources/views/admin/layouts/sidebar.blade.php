  @php
      $sidebarUser = auth()->user();
      $sidebarFeatures = app(\App\Support\FeatureGate::class);
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
  </style>

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
                              @can('manage_orders')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.orders.index') }}" aria-expanded="false">
                                      <iconify-icon icon="solar:bill-list-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.orders') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          @can('view_customers')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.customers.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:users-group-two-rounded-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.customers') }}</span>
                              </a>
                          </li>
                          @endcan

                          <!-- Sản phẩm (Products, Categories, Brands) -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'catalog'))
                              @can('manage_products')
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
                              @can('manage_reviews')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.reviews.index') }}" aria-expanded="false">
                                      <iconify-icon icon="solar:chat-round-line-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.reviews') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          <!-- Liên hệ -->
                          @can('manage_contacts')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.contact-submissions.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:letter-line-duotone"></iconify-icon>
                                  <span class="hide-menu">Liên hệ</span>
                              </a>
                          </li>
                          @endcan

                          <!-- Mã giảm giá -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'voucher') || $sidebarFeatures->availableTo($sidebarUser, 'catalog'))
                              @can('manage_vouchers')
                              <li class="sidebar-item">
                                  <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                      <iconify-icon icon="solar:ticket-sale-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.promotions') }}</span>
                                  </a>
                                  <ul aria-expanded="false" class="collapse first-level">
                                      @if($sidebarFeatures->availableTo($sidebarUser, 'voucher'))
                                      <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.vouchers.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.vouchers') }}</a></li>
                                      @endif
                                      @if($sidebarFeatures->availableTo($sidebarUser, 'catalog'))
                                      <li class="sidebar-item"><a class="sidebar-link" href="{{ route('admin.promotions.index') }}"><span class="icon-small"></span>{{ __('admin.sidebar.promotions_flash_sale') }}</a></li>
                                      @endif
                                  </ul>
                              </li>
                              @endcan
                          @endif

                          <!-- Quản lý Banner -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'banner'))
                              @can('manage_banners')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.banners.index') }}" aria-expanded="false">
                                      <iconify-icon icon="solar:gallery-bold-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.banners') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          <!-- Bài viết (Posts, Post Categories) -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'cms_page'))
                              @can('manage_posts')
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
                              @can('manage_pages')
                              <li class="sidebar-item">
                                  <a class="sidebar-link" href="{{ route('admin.pages.index') }}" aria-expanded="false">
                                      <i class="ti ti-file-text fs-6" aria-hidden="true"></i>
                                      <span class="hide-menu">{{ __('admin.sidebar.pages') }}</span>
                                  </a>
                              </li>
                              @endcan
                          @endif

                          <!-- Quản lý người dùng -->
                          @if($sidebarFeatures->availableTo($sidebarUser, 'multi_admin'))
                               @if(auth()->user()->can('manage_users') || auth()->user()->isSuperAdmin())
                               <li class="sidebar-item">
                                  <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                      <iconify-icon icon="solar:shield-user-line-duotone"></iconify-icon>
                                      <span class="hide-menu">{{ __('admin.sidebar.user_management') }}</span>
                                  </a>
                                  <ul aria-expanded="false" class="collapse first-level">
                                      @can('manage_users')
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.users.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.user_list') }}
                                          </a>
                                      </li>
                                      @endcan
                                      @if(auth()->user()->isSuperAdmin())
                                      <li class="sidebar-item">
                                          <a class="sidebar-link" href="{{ route('admin.roles.index') }}">
                                              <span class="icon-small"></span>{{ __('admin.sidebar.roles_permissions') }}
                                          </a>
                                      </li>
                                      @endif
                                  </ul>
                              </li>
                              @endif
                          @endif

                          <!-- Cấu hình -->
                          @if($sidebarUser?->can('manage_settings') || $sidebarUser?->isSuperAdmin())
                          <li class="sidebar-item" data-sidebar-settings-menu>
                              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                                  <iconify-icon icon="solar:settings-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.settings') }}</span>
                              </a>
                              <ul aria-expanded="false" class="collapse first-level">
                                  @can('manage_settings')
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.settings.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.general_settings') }}
                                      </a>
                                  </li>
                                  @if($sidebarFeatures->availableTo($sidebarUser, 'shipping'))
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.shipping-partners.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.shipping_settings') }}
                                      </a>
                                  </li>
                                  @endif
                                  @if($sidebarFeatures->availableTo($sidebarUser, 'cod_order') || $sidebarFeatures->availableTo($sidebarUser, 'online_payment'))
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.payment-methods.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.payment_settings') }}
                                      </a>
                                  </li>
                                  @endif
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.notification-settings.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.notification_settings') }}
                                      </a>
                                  </li>
                                  @endcan
                                  @if($sidebarUser?->isSuperAdmin())
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.languages.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.content_languages') }}
                                      </a>
                                  </li>
                                  <li class="sidebar-item">
                                      <a class="sidebar-link" href="{{ route('admin.features.index') }}">
                                          <span class="icon-small"></span>{{ __('admin.sidebar.feature_settings') }}
                                      </a>
                                  </li>
                                  @endif
                              </ul>
                          </li>
                          @endif

                          @can('view_audit_log')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.activity-logs.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:history-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.activity_logs') }}</span>
                              </a>
                          </li>
                          @endcan

                          {{-- <!-- Hóa đơn của tôi -->
                          @can('manage_settings')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.invoices.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:receipt-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.my_invoices') }}</span>
                              </a>
                          </li>
                          @endcan --}}

                          <!-- Media Library -->
                          @can('manage_media')
                          <li class="sidebar-item">
                              <a class="sidebar-link" href="{{ route('admin.media.index') }}" aria-expanded="false">
                                  <iconify-icon icon="solar:gallery-line-duotone"></iconify-icon>
                                  <span class="hide-menu">{{ __('admin.sidebar.media_library') }}</span>
                              </a>
                          </li>
                          @endcan
                      </ul>
                  </nav>
              </div>
          </div>
      </div>
  </aside>
