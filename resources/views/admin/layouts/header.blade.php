      <!--  Header Start -->
      <header class="topbar">
          <div class="with-vertical">
              <!-- ---------------------------------- -->
              <!-- Start Vertical Layout Header -->
              <!-- ---------------------------------- -->
              <nav class="navbar navbar-expand-lg p-0">
                  <ul class="navbar-nav">
                      <li class="nav-item d-flex d-xl-none">
                          <a class="nav-link nav-icon-hover-bg rounded-circle  sidebartoggler " id="headerCollapse"
                              href="javascript:void(0)">
                              <iconify-icon icon="solar:hamburger-menu-line-duotone" class="fs-6"></iconify-icon>
                          </a>
                      </li>
                      <li class="nav-item d-none d-xl-flex align-items-center">
                          <a class="nav-link d-flex align-items-center gap-2" href="{{ url('/') }}" target="_blank" style="padding: 0 8px;">
                              <span class="nav-icon-hover-bg rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                  <iconify-icon icon="solar:home-2-linear" class="fs-6"></iconify-icon>
                              </span>
                              <span class="site-name">{{ $siteBranding['name'] }}</span>
                          </a>
                      </li>
                      <li class="nav-item d-none d-xl-flex nav-icon-hover-bg rounded-circle">
                          <a class="nav-link" href="javascript:void(0)" data-bs-toggle="modal"
                              data-bs-target="#exampleModal">
                              <iconify-icon icon="solar:magnifer-linear" class="fs-6"></iconify-icon>
                          </a>
                      </li>
                      <li class="nav-item d-none d-lg-flex dropdown nav-icon-hover-bg rounded-circle">
                          <div class="hover-dd">
                              <a class="nav-link" id="drop2" href="javascript:void(0)" aria-haspopup="true"
                                  aria-expanded="false">
                                  <iconify-icon icon="solar:widget-3-linear" class="fs-6"></iconify-icon>
                              </a>
                              <div class="dropdown-menu dropdown-menu-nav dropdown-menu-animate-up py-0 overflow-hidden"
                                  aria-labelledby="drop2" style="min-width: 650px !important;">
                                  <div class="p-4">
                                      <div class="row g-4 mb-4">
                                          <!-- Hướng dẫn sử dụng -->
                                          <div class="col-md-6">
                                              <a href="https://support.matbao.ws" target="_blank" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #f3ebff;">
                                                      <iconify-icon icon="lucide:life-buoy" class="fs-7" style="color: #8a2be2;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">Hướng dẫn sử dụng</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Tài liệu hướng dẫn sử dụng</span>
                                                  </div>
                                              </a>
                                          </div>
                                          
                                          <!-- support@matbao.ws -->
                                          <div class="col-md-6">
                                              <a href="mailto:support@matbao.ws" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #fff5e6;">
                                                      <iconify-icon icon="solar:letter-bold-duotone" class="fs-7" style="color: #ff9900;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">support@matbao.ws</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Email hỗ trợ kỹ thuật</span>
                                                  </div>
                                              </a>
                                          </div>

                                          <!-- Hotline -->
                                          <div class="col-md-6">
                                              <a href="tel:02877777999" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e6f2ff;">
                                                      <iconify-icon icon="solar:phone-calling-rounded-bold-duotone" class="fs-7" style="color: #0066cc;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">(028) 7777 7999</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Hotline hỗ trợ 24/7/365</span>
                                                  </div>
                                              </a>
                                          </div>

                                          <!-- UltraViewer -->
                                          <div class="col-md-6">
                                              <a href="https://ultraviewer.net/vi/download.html" target="_blank" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #eafaf1;">
                                                      <iconify-icon icon="lucide:download" class="fs-7" style="color: #2ecc71;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">Tải xuống UltraViewer</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Phần mềm hỗ trợ từ xa</span>
                                                  </div>
                                              </a>
                                          </div>
                                      </div>
                                      
                                      <!-- Footer Row -->
                                      <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                                          <span class="text-dark fw-bold fs-3">Cần gặp nhân viên hỗ trợ kỹ thuật?</span>
                                          <a href="https://support.matbao.ws/submitticket.php" target="_blank" class="btn btn-teal text-white fw-bold px-4 py-2" style="background-color: #00b493 !important; border-color: #00b493 !important; border-radius: 6px; font-size: 13.5px;">
                                              GỬI YÊU CẦU NGAY
                                          </a>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </li>
                  </ul>

                  <div class="d-block d-lg-none py-9 py-xl-0">
                      <img src="{{ $siteBranding['admin_logo_url'] }}" alt="MatBaoWS" width="120px">
                  </div>
                  <a class="navbar-toggler p-0 border-0 nav-icon-hover-bg rounded-circle" href="javascript:void(0)"
                      data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav"
                      aria-expanded="false" aria-label="Toggle navigation">
                      <iconify-icon icon="solar:menu-dots-bold-duotone" class="fs-6"></iconify-icon>
                  </a>
                  <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                      <div class="d-flex align-items-center justify-content-between">
                          <ul class="navbar-nav flex-row mx-auto ms-lg-auto align-items-center justify-content-center">
                              <li class="nav-item dropdown">
                                  <a href="javascript:void(0)"
                                      class="nav-link nav-icon-hover-bg rounded-circle d-flex d-lg-none align-items-center justify-content-center"
                                      type="button" data-bs-toggle="offcanvas" data-bs-target="#mobilenavbar"
                                      aria-controls="offcanvasWithBothOptions">
                                      <iconify-icon icon="solar:sort-line-duotone" class="fs-6"></iconify-icon>
                                  </a>
                              </li>
                              <li class="nav-item d-block d-xl-none">
                                  <a class="nav-link nav-icon-hover-bg rounded-circle" href="javascript:void(0)"
                                      data-bs-toggle="modal" data-bs-target="#exampleModal">
                                      <iconify-icon icon="solar:magnifer-line-duotone" class="fs-6"></iconify-icon>
                                  </a>
                              </li>

                              <!-- ------------------------------- -->
                              <!-- start notification Dropdown -->
                              <!-- ------------------------------- -->
                              <li class="nav-item dropdown nav-icon-hover-bg rounded-circle">
                                  <a class="nav-link position-relative" href="javascript:void(0)" id="drop2"
                                      aria-expanded="false">
                                      <iconify-icon icon="solar:bell-bing-line-duotone" class="fs-6"></iconify-icon>
                                  </a>
                                  <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up"
                                      aria-labelledby="drop2">
                                      <div class="d-flex align-items-center justify-content-between py-3 px-7">
                                          <h5 class="mb-0 fs-5 fw-semibold">{{ __('admin.notifications') }}</h5>
                                          <span class="badge text-bg-primary rounded-4 px-3 py-1 lh-sm">{{ $headerNotifications->count() }} mới</span>
                                      </div>
                                      <div class="message-body" data-simplebar="">
                                          @forelse($headerNotifications as $notification)
                                              <a href="{{ $notification['link'] }}"
                                                  class="py-6 px-7 d-flex align-items-center dropdown-item gap-3">
                                                  <span
                                                      class="flex-shrink-0 {{ $notification['bg_color'] }} rounded-circle round d-flex align-items-center justify-content-center fs-6">
                                                      <iconify-icon icon="{{ $notification['icon'] }}"></iconify-icon>
                                                  </span>
                                                  <div class="w-75">
                                                      <div class="d-flex align-items-center justify-content-between">
                                                          <h6 class="mb-1 fw-semibold text-truncate" style="max-width: 150px;">{{ $notification['title'] }}</h6>
                                                          <span class="d-block fs-2 text-muted">{{ $notification['time'] }}</span>
                                                      </div>
                                                      <span class="d-block text-truncate fs-11 text-muted">{{ $notification['message'] }}</span>
                                                  </div>
                                              </a>
                                          @empty
                                              <div class="py-5 text-center text-muted">
                                                  <iconify-icon icon="solar:bell-bing-line-duotone" class="fs-7 mb-2 d-inline-block"></iconify-icon>
                                                  <p class="mb-0 fs-3">Không có thông báo mới</p>
                                              </div>
                                          @endforelse
                                      </div>
                                      <div class="py-6 px-7 mb-1">
                                          <a href="{{ route('admin.notifications.index') }}" class="btn btn-primary w-100">{{ __('admin.see_all_notifications') }}</a>
                                      </div>

                                  </div>
                              </li>
                              <!-- ------------------------------- -->
                              <!-- end notification Dropdown -->
                              <!-- ------------------------------- -->

                              <!-- ------------------------------- -->
                              <!-- start language Dropdown -->
                              <!-- ------------------------------- -->
                              @include('admin.layouts.language-switcher')
                              <!-- ------------------------------- -->
                              <!-- end language Dropdown -->
                              <!-- ------------------------------- -->

                              <!-- ------------------------------- -->
                              <!-- start profile Dropdown -->
                              <!-- ------------------------------- -->
                              <li class="nav-item dropdown">
                                  <a class="nav-link" href="javascript:void(0)" id="drop1"
                                      aria-expanded="false">
                                      <div class="d-flex align-items-center gap-2 lh-base">
                                          <img src="{{ asset('admin-assets/images/profile/user-1.jpg') }}" class="rounded-circle"
                                              width="35" height="35" alt="matdash-img">
                                          <iconify-icon icon="solar:alt-arrow-down-bold"
                                              class="fs-2"></iconify-icon>
                                      </div>
                                  </a>
                                  <div class="dropdown-menu profile-dropdown dropdown-menu-end dropdown-menu-animate-up"
                                      aria-labelledby="drop1">
                                      <div class="position-relative px-4 pt-3 pb-2">
                                          <div class="d-flex align-items-center mb-3 pb-3 border-bottom gap-6">
                                              <img src="{{ asset('admin-assets/images/profile/user-1.jpg') }}" class="rounded-circle"
                                                  width="56" height="56" alt="matdash-img">
                                              <div>
                                                  <h5 class="mb-0 fs-12">{{ auth()->user()->name ?? 'Admin' }} <span
                                                          class="text-success fs-11">Admin</span>
                                                  </h5>
                                                  <p class="mb-0 text-dark">
                                                      {{ auth()->user()->email ?? 'admin@example.com' }}
                                                  </p>
                                              </div>
                                          </div>
                                          <div class="message-body">
                                              <a href="{{ route('admin.users.edit', auth()->id()) }}" class="p-2 dropdown-item h6 rounded-1">
                                                  {{ __('admin.profile.my_profile') }}
                                              </a>
                                              <a href="{{ route('admin.settings.index') }}"
                                                  class="p-2 dropdown-item h6 rounded-1">
                                                  {{ __('admin.profile.account_settings') }}
                                              </a>
                                              <form method="POST" action="{{ route('admin.logout') }}">
                                                  @csrf
                                                  <button type="submit" class="p-2 dropdown-item h6 rounded-1 border-0 bg-transparent w-100 text-start">
                                                      {{ __('admin.profile.sign_out') }}
                                                  </button>
                                              </form>
                                          </div>
                                      </div>
                                  </div>
                              </li>
                              <!-- ------------------------------- -->
                              <!-- end profile Dropdown -->
                              <!-- ------------------------------- -->
                          </ul>
                      </div>
                  </div>
              </nav>
              <!-- ---------------------------------- -->
              <!-- End Vertical Layout Header -->
              <!-- ---------------------------------- -->

              <!-- ------------------------------- -->
              <!-- apps Dropdown in Small screen -->
              <!-- ------------------------------- -->
              <!--  Mobilenavbar -->
              <div class="offcanvas offcanvas-start pt-0" data-bs-scroll="true" tabindex="-1" id="mobilenavbar"
                  aria-labelledby="offcanvasWithBothOptionsLabel">
                  <nav class="sidebar-nav scroll-sidebar">
                      <div class="offcanvas-header justify-content-between">
                          <a href="{{ route('admin.dashboard') }}" class="text-nowrap logo-img">
                               <img src="{{ $siteBranding['favicon_url'] }}" alt="{{ $siteBranding['name'] }}" width="35" height="35">
                          </a>
                          <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                              aria-label="Close"></button>
                      </div>
                      <div class="offcanvas-body pt-0" data-simplebar="" style="height: calc(100vh - 80px)">
                          <ul id="sidebarnav">
                              <li class="sidebar-item">
                                  <a class="sidebar-link has-arrow ms-0" href="javascript:void(0)"
                                      aria-expanded="false">
                                      <span>
                                          <iconify-icon icon="solar:widget-3-line-duotone"
                                              class="fs-7"></iconify-icon>
                                      </span>
                                      <span class="hide-menu">Hỗ trợ kỹ thuật</span>
                                  </a>
                                  <ul aria-expanded="false" class="collapse first-level my-3 ps-3">
                                      <li class="sidebar-item py-2">
                                          <a href="https://support.matbao.ws" target="_blank" class="d-flex align-items-center text-decoration-none">
                                              <div class="rounded round-48 me-3 d-flex align-items-center justify-content-center" style="background-color: #f3ebff;">
                                                  <iconify-icon icon="lucide:life-buoy" class="fs-7" style="color: #8a2be2;"></iconify-icon>
                                              </div>
                                              <div>
                                                  <h6 class="mb-0 text-dark fw-bolder" style="font-size: 15px; font-weight: 800 !important;">Hướng dẫn sử dụng</h6>
                                                  <span class="fs-11 d-block text-muted fw-semibold">Tài liệu hướng dẫn sử dụng</span>
                                              </div>
                                          </a>
                                      </li>
                                      <li class="sidebar-item py-2">
                                          <a href="mailto:support@matbao.ws" class="d-flex align-items-center text-decoration-none">
                                              <div class="rounded round-48 me-3 d-flex align-items-center justify-content-center" style="background-color: #fff5e6;">
                                                  <iconify-icon icon="solar:letter-bold-duotone" class="fs-7" style="color: #ff9900;"></iconify-icon>
                                              </div>
                                              <div>
                                                  <h6 class="mb-0 text-dark fw-bolder" style="font-size: 15px; font-weight: 800 !important;">support@matbao.ws</h6>
                                                  <span class="fs-11 d-block text-muted fw-semibold">Email hỗ trợ kỹ thuật</span>
                                              </div>
                                          </a>
                                      </li>
                                      <li class="sidebar-item py-2">
                                          <a href="tel:02877777999" class="d-flex align-items-center text-decoration-none">
                                              <div class="rounded round-48 me-3 d-flex align-items-center justify-content-center" style="background-color: #e6f2ff;">
                                                  <iconify-icon icon="solar:phone-calling-rounded-bold-duotone" class="fs-7" style="color: #0066cc;"></iconify-icon>
                                              </div>
                                              <div>
                                                  <h6 class="mb-0 text-dark fw-bolder" style="font-size: 15px; font-weight: 800 !important;">(028) 7777 7999</h6>
                                                  <span class="fs-11 d-block text-muted fw-semibold">Hotline hỗ trợ 24/7/365</span>
                                              </div>
                                          </a>
                                      </li>
                                      <li class="sidebar-item py-2">
                                          <a href="https://ultraviewer.net/vi/download.html" target="_blank" class="d-flex align-items-center text-decoration-none">
                                              <div class="rounded round-48 me-3 d-flex align-items-center justify-content-center" style="background-color: #eafaf1;">
                                                  <iconify-icon icon="lucide:download" class="fs-7" style="color: #2ecc71;"></iconify-icon>
                                              </div>
                                              <div>
                                                  <h6 class="mb-0 text-dark fw-bolder" style="font-size: 15px; font-weight: 800 !important;">Tải xuống UltraViewer</h6>
                                                  <span class="fs-11 d-block text-muted fw-semibold">Phần mềm hỗ trợ từ xa</span>
                                              </div>
                                          </a>
                                      </li>
                                      <li class="sidebar-item py-2">
                                          <a href="https://support.matbao.ws/submitticket.php" target="_blank" class="d-flex align-items-center text-decoration-none">
                                              <div class="rounded round-48 me-3 d-flex align-items-center justify-content-center" style="background-color: #e0f8f4;">
                                                  <iconify-icon icon="solar:pen-new-square-bold-duotone" class="fs-7" style="color: #00b493;"></iconify-icon>
                                              </div>
                                              <div>
                                                  <h6 class="mb-0 text-dark fw-bolder" style="font-size: 15px; font-weight: 800 !important;">Gửi yêu cầu hỗ trợ</h6>
                                                  <span class="fs-11 d-block text-muted fw-semibold">Cần gặp nhân viên hỗ trợ ngay</span>
                                              </div>
                                          </a>
                                      </li>
                                  </ul>
                              </li>
                          </ul>
                      </div>
                  </nav>
              </div>

          </div>
          <div class="app-header with-horizontal">
              <nav class="navbar navbar-expand-xl container-fluid p-0">
                  <ul class="navbar-nav align-items-center">
                      <li class="nav-item d-flex d-xl-none">
                          <a class="nav-link sidebartoggler nav-icon-hover-bg rounded-circle" id="sidebarCollapse"
                              href="javascript:void(0)">
                              <iconify-icon icon="solar:hamburger-menu-line-duotone" class="fs-7"></iconify-icon>
                          </a>
                      </li>
                      <li class="nav-item d-none d-xl-flex align-items-center">
                          <a href="{{ route('admin.dashboard') }}" class="text-nowrap nav-link">
                              <img src="{{ asset('admin-assets/images/logos/logo.svg') }}" alt="matdash-img">
                          </a>
                      </li>
                      <li class="nav-item d-none d-xl-flex align-items-center">
                          <a class="nav-link d-flex align-items-center gap-2" href="{{ url('/') }}" target="_blank" style="padding: 0 8px;">
                              <span class="nav-icon-hover-bg rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                  <iconify-icon icon="solar:home-2-linear" class="fs-6"></iconify-icon>
                              </span>
                              <span class="site-name">{{ $siteBranding['name'] }}</span>
                          </a>
                      </li>
                      <li class="nav-item d-none d-xl-flex align-items-center nav-icon-hover-bg rounded-circle">
                          <a class="nav-link" href="javascript:void(0)" data-bs-toggle="modal"
                              data-bs-target="#exampleModal">
                              <iconify-icon icon="solar:magnifer-linear" class="fs-6"></iconify-icon>
                          </a>
                      </li>
                      <li
                          class="nav-item d-none d-lg-flex align-items-center dropdown nav-icon-hover-bg rounded-circle">
                          <div class="hover-dd">
                              <a class="nav-link" id="drop2" href="javascript:void(0)" aria-haspopup="true"
                                  aria-expanded="false">
                                  <iconify-icon icon="solar:widget-3-linear" class="fs-6"></iconify-icon>
                              </a>
                              <div class="dropdown-menu dropdown-menu-nav dropdown-menu-animate-up py-0 overflow-hidden"
                                  aria-labelledby="drop2" style="min-width: 650px !important;">
                                  <div class="p-4">
                                      <div class="row g-4 mb-4">
                                          <!-- Hướng dẫn sử dụng -->
                                          <div class="col-md-6">
                                              <a href="https://support.matbao.ws" target="_blank" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #f3ebff;">
                                                      <iconify-icon icon="lucide:life-buoy" class="fs-7" style="color: #8a2be2;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">Hướng dẫn sử dụng</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Tài liệu hướng dẫn sử dụng</span>
                                                  </div>
                                              </a>
                                          </div>
                                          
                                          <!-- support@matbao.ws -->
                                          <div class="col-md-6">
                                              <a href="mailto:support@matbao.ws" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #fff5e6;">
                                                      <iconify-icon icon="solar:letter-bold-duotone" class="fs-7" style="color: #ff9900;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">support@matbao.ws</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Email hỗ trợ kỹ thuật</span>
                                                  </div>
                                              </a>
                                          </div>

                                          <!-- Hotline -->
                                          <div class="col-md-6">
                                              <a href="tel:02877777999" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e6f2ff;">
                                                      <iconify-icon icon="solar:phone-calling-rounded-bold-duotone" class="fs-7" style="color: #0066cc;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">(028) 7777 7999</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Hotline hỗ trợ 24/7/365</span>
                                                  </div>
                                              </a>
                                          </div>

                                          <!-- UltraViewer -->
                                          <div class="col-md-6">
                                              <a href="https://ultraviewer.net/vi/download.html" target="_blank" class="d-flex align-items-center text-decoration-none">
                                                  <div class="rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #eafaf1;">
                                                      <iconify-icon icon="lucide:download" class="fs-7" style="color: #2ecc71;"></iconify-icon>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-1 text-dark fw-bolder" style="font-size: 16px; font-weight: 800 !important;">Tải xuống UltraViewer</h6>
                                                      <span class="fs-2 text-muted fw-semibold">Phần mềm hỗ trợ từ xa</span>
                                                  </div>
                                              </a>
                                          </div>
                                      </div>
                                      
                                      <!-- Footer Row -->
                                      <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                                          <span class="text-dark fw-bold fs-3">Cần gặp nhân viên hỗ trợ kỹ thuật?</span>
                                          <a href="https://support.matbao.ws/submitticket.php" target="_blank" class="btn btn-teal text-white fw-bold px-4 py-2" style="background-color: #00b493 !important; border-color: #00b493 !important; border-radius: 6px; font-size: 13.5px;">
                                              GỬI YÊU CẦU NGAY
                                          </a>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </li>
                  </ul>
                  <div class="d-block d-xl-none">
                      <a href="{{ route('admin.dashboard') }}" class="text-nowrap nav-link">
                          <img src="{{ asset('admin-assets/images/logos/logo.svg') }}" alt="matdash-img">
                      </a>
                  </div>
                  <a class="navbar-toggler nav-icon-hover p-0 border-0 nav-icon-hover-bg rounded-circle"
                      href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                      <span class="p-2">
                          <i class="ti ti-dots fs-7"></i>
                      </span>
                  </a>
                  <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                      <div class="d-flex align-items-center justify-content-between px-0 px-xl-8">
                          <ul class="navbar-nav flex-row mx-auto ms-lg-auto align-items-center justify-content-center">
                              <li class="nav-item dropdown">
                                  <a href="javascript:void(0)"
                                      class="nav-link nav-icon-hover-bg rounded-circle d-flex d-lg-none align-items-center justify-content-center"
                                      type="button" data-bs-toggle="offcanvas" data-bs-target="#mobilenavbar"
                                      aria-controls="offcanvasWithBothOptions">
                                      <iconify-icon icon="solar:sort-line-duotone" class="fs-6"></iconify-icon>
                                  </a>
                              </li>
                              <li class="nav-item d-block d-xl-none">
                                  <a class="nav-link nav-icon-hover-bg rounded-circle" href="javascript:void(0)"
                                      data-bs-toggle="modal" data-bs-target="#exampleModal">
                                      <iconify-icon icon="solar:magnifer-line-duotone" class="fs-6"></iconify-icon>
                                  </a>
                              </li>

                              <!-- ------------------------------- -->
                              <!-- start notification Dropdown -->
                              <!-- ------------------------------- -->
                              <li class="nav-item dropdown nav-icon-hover-bg rounded-circle">
                                  <a class="nav-link position-relative" href="javascript:void(0)" id="drop2"
                                      aria-expanded="false">
                                      <iconify-icon icon="solar:bell-bing-line-duotone" class="fs-6"></iconify-icon>
                                  </a>
                                  <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up"
                                      aria-labelledby="drop2">
                                      <div class="d-flex align-items-center justify-content-between py-3 px-7">
                                          <h5 class="mb-0 fs-5 fw-semibold">{{ __('admin.notifications') }}</h5>
                                          <span class="badge text-bg-primary rounded-4 px-3 py-1 lh-sm">{{ $headerNotifications->count() }} mới</span>
                                      </div>
                                      <div class="message-body" data-simplebar="">
                                          @forelse($headerNotifications as $notification)
                                              <a href="{{ $notification['link'] }}"
                                                  class="py-6 px-7 d-flex align-items-center dropdown-item gap-3">
                                                  <span
                                                      class="flex-shrink-0 {{ $notification['bg_color'] }} rounded-circle round d-flex align-items-center justify-content-center fs-6">
                                                      <iconify-icon icon="{{ $notification['icon'] }}"></iconify-icon>
                                                  </span>
                                                  <div class="w-75">
                                                      <div class="d-flex align-items-center justify-content-between">
                                                          <h6 class="mb-1 fw-semibold text-truncate" style="max-width: 150px;">{{ $notification['title'] }}</h6>
                                                          <span class="d-block fs-2 text-muted">{{ $notification['time'] }}</span>
                                                      </div>
                                                      <span class="d-block text-truncate fs-11 text-muted">{{ $notification['message'] }}</span>
                                                  </div>
                                              </a>
                                          @empty
                                              <div class="py-5 text-center text-muted">
                                                  <iconify-icon icon="solar:bell-bing-line-duotone" class="fs-7 mb-2 d-inline-block"></iconify-icon>
                                                  <p class="mb-0 fs-3">Không có thông báo mới</p>
                                              </div>
                                          @endforelse
                                      </div>
                                      <div class="py-6 px-7 mb-1">
                                          <a href="{{ route('admin.notifications.index') }}" class="btn btn-primary w-100">{{ __('admin.see_all_notifications') }}</a>
                                      </div>

                                  </div>
                              </li>
                              <!-- ------------------------------- -->
                              <!-- end notification Dropdown -->
                              <!-- ------------------------------- -->

                              <!-- ------------------------------- -->
                              <!-- start language Dropdown -->
                              <!-- ------------------------------- -->
                              @include('admin.layouts.language-switcher')
                              <!-- ------------------------------- -->
                              <!-- end language Dropdown -->
                              <!-- ------------------------------- -->

                              <!-- ------------------------------- -->
                              <!-- start profile Dropdown -->
                              <!-- ------------------------------- -->
                              <li class="nav-item dropdown">
                                  <a class="nav-link" href="javascript:void(0)" id="drop1"
                                      aria-expanded="false">
                                      <div class="d-flex align-items-center gap-2 lh-base">
                                          <img src="{{ asset('admin-assets/images/profile/user-1.jpg') }}" class="rounded-circle"
                                              width="35" height="35" alt="matdash-img">
                                          <iconify-icon icon="solar:alt-arrow-down-bold"
                                              class="fs-2"></iconify-icon>
                                      </div>
                                  </a>
                                  <div class="dropdown-menu profile-dropdown dropdown-menu-end dropdown-menu-animate-up"
                                      aria-labelledby="drop1">
                                      <div class="position-relative px-4 pt-3 pb-2">
                                          <div class="d-flex align-items-center mb-3 pb-3 border-bottom gap-6">
                                              <img src="{{ asset('admin-assets/images/profile/user-1.jpg') }}" class="rounded-circle"
                                                  width="56" height="56" alt="matdash-img">
                                              <div>
                                                  <h5 class="mb-0 fs-12">{{ auth()->user()->name ?? 'Admin' }} <span
                                                          class="text-success fs-11">Admin</span>
                                                  </h5>
                                                  <p class="mb-0 text-dark">
                                                      {{ auth()->user()->email ?? 'admin@example.com' }}
                                                  </p>
                                              </div>
                                          </div>
                                          <div class="message-body">
                                              <a href="{{ route('admin.users.edit', auth()->id()) }}" class="p-2 dropdown-item h6 rounded-1">
                                                  {{ __('admin.profile.my_profile') }}
                                              </a>
                                              <a href="{{ route('admin.settings.index') }}"
                                                  class="p-2 dropdown-item h6 rounded-1">
                                                  {{ __('admin.profile.account_settings') }}
                                              </a>
                                              <form method="POST" action="{{ route('admin.logout') }}">
                                                  @csrf
                                                  <button type="submit" class="p-2 dropdown-item h6 rounded-1 border-0 bg-transparent w-100 text-start">
                                                      {{ __('admin.profile.sign_out') }}
                                                  </button>
                                              </form>
                                          </div>
                                      </div>
                                  </div>
                              </li>
                              <!-- ------------------------------- -->
                              <!-- end profile Dropdown -->
                              <!-- ------------------------------- -->
                          </ul>
                      </div>
                  </div>
              </nav>

          </div>
      </header>
      <!--  Header End -->
