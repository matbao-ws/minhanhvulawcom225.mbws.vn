  {{-- <button class="btn btn-danger p-3 rounded-circle d-flex align-items-center justify-content-center customizer-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample" aria-controls="offcanvasExample">
        <i class="icon ti ti-settings fs-7"></i>
      </button> --}}

      <div class="offcanvas customizer offcanvas-end" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
          <h4 class="offcanvas-title fw-semibold" id="offcanvasExampleLabel">
            Settings
          </h4>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body" data-simplebar="" style="height: calc(100vh - 80px)">
          <h6 class="fw-semibold fs-4 mb-2">Theme</h6>

          <div class="d-flex flex-row gap-3 customizer-box" role="group">
            <input type="radio" class="btn-check light-layout" name="theme-layout" id="light-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="light-layout">
              <i class="icon ti ti-brightness-up fs-7 me-2"></i>Light
            </label>

            <input type="radio" class="btn-check dark-layout" name="theme-layout" id="dark-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="dark-layout">
              <i class="icon ti ti-moon fs-7 me-2"></i>Dark
            </label>
          </div>

          <h6 class="mt-5 fw-semibold fs-4 mb-2">Theme Direction</h6>
          <div class="d-flex flex-row gap-3 customizer-box" role="group">
            <input type="radio" class="btn-check" name="direction-l" id="ltr-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="ltr-layout">
              <i class="icon ti ti-text-direction-ltr fs-7 me-2"></i>LTR
            </label>

            <input type="radio" class="btn-check" name="direction-l" id="rtl-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="rtl-layout">
              <i class="icon ti ti-text-direction-rtl fs-7 me-2"></i>RTL
            </label>
          </div>

          <h6 class="mt-5 fw-semibold fs-4 mb-2">Theme Colors</h6>

          <div class="d-flex flex-row flex-wrap gap-3 customizer-box color-pallete" role="group">
            <input type="radio" class="btn-check" name="color-theme-layout" id="Blue_Theme" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2 d-flex align-items-center justify-content-center" onclick="handleColorTheme('Blue_Theme')" for="Blue_Theme" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="BLUE_THEME">
              <div class="color-box rounded-circle d-flex align-items-center justify-content-center skin-1">
                <i class="ti ti-check text-white d-flex icon fs-5"></i>
              </div>
            </label>

            <input type="radio" class="btn-check" name="color-theme-layout" id="Aqua_Theme" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2 d-flex align-items-center justify-content-center" onclick="handleColorTheme('Aqua_Theme')" for="Aqua_Theme" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="AQUA_THEME">
              <div class="color-box rounded-circle d-flex align-items-center justify-content-center skin-2">
                <i class="ti ti-check text-white d-flex icon fs-5"></i>
              </div>
            </label>

            <input type="radio" class="btn-check" name="color-theme-layout" id="Purple_Theme" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2 d-flex align-items-center justify-content-center" onclick="handleColorTheme('Purple_Theme')" for="Purple_Theme" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="PURPLE_THEME">
              <div class="color-box rounded-circle d-flex align-items-center justify-content-center skin-3">
                <i class="ti ti-check text-white d-flex icon fs-5"></i>
              </div>
            </label>

            <input type="radio" class="btn-check" name="color-theme-layout" id="green-theme-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2 d-flex align-items-center justify-content-center" onclick="handleColorTheme('Green_Theme')" for="green-theme-layout" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="GREEN_THEME">
              <div class="color-box rounded-circle d-flex align-items-center justify-content-center skin-4">
                <i class="ti ti-check text-white d-flex icon fs-5"></i>
              </div>
            </label>

            <input type="radio" class="btn-check" name="color-theme-layout" id="cyan-theme-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2 d-flex align-items-center justify-content-center" onclick="handleColorTheme('Cyan_Theme')" for="cyan-theme-layout" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="CYAN_THEME">
              <div class="color-box rounded-circle d-flex align-items-center justify-content-center skin-5">
                <i class="ti ti-check text-white d-flex icon fs-5"></i>
              </div>
            </label>

            <input type="radio" class="btn-check" name="color-theme-layout" id="Orange_Theme" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2 d-flex align-items-center justify-content-center" onclick="handleColorTheme('Orange_Theme')" for="Orange_Theme" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ORANGE_THEME">
              <div class="color-box rounded-circle d-flex align-items-center justify-content-center skin-6">
                <i class="ti ti-check text-white d-flex icon fs-5"></i>
              </div>
            </label>
          </div>

          <h6 class="mt-5 fw-semibold fs-4 mb-2">Layout Type</h6>
          <div class="d-flex flex-row gap-3 customizer-box" role="group">
            <div>
              <input type="radio" class="btn-check" name="page-layout" id="vertical-layout" autocomplete="off">
              <label class="btn p-9 btn-outline-primary rounded-2" for="vertical-layout">
                <i class="icon ti ti-layout-sidebar-right fs-7 me-2"></i>Vertical
              </label>
            </div>
            <div>
              <input type="radio" class="btn-check" name="page-layout" id="horizontal-layout" autocomplete="off">
              <label class="btn p-9 btn-outline-primary rounded-2" for="horizontal-layout">
                <i class="icon ti ti-layout-navbar fs-7 me-2"></i>Horizontal
              </label>
            </div>
          </div>

          <h6 class="mt-5 fw-semibold fs-4 mb-2">Container Option</h6>

          <div class="d-flex flex-row gap-3 customizer-box" role="group">
            <input type="radio" class="btn-check" name="layout" id="boxed-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="boxed-layout">
              <i class="icon ti ti-layout-distribute-vertical fs-7 me-2"></i>Boxed
            </label>

            <input type="radio" class="btn-check" name="layout" id="full-layout" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="full-layout">
              <i class="icon ti ti-layout-distribute-horizontal fs-7 me-2"></i>Full
            </label>
          </div>

          <h6 class="fw-semibold fs-4 mb-2 mt-5">Sidebar Type</h6>
          <div class="d-flex flex-row gap-3 customizer-box" role="group">
            <a href="javascript:void(0)" class="fullsidebar">
              <input type="radio" class="btn-check" name="sidebar-type" id="full-sidebar" autocomplete="off">
              <label class="btn p-9 btn-outline-primary rounded-2" for="full-sidebar">
                <i class="icon ti ti-layout-sidebar-right fs-7 me-2"></i>Full
              </label>
            </a>
            <div>
              <input type="radio" class="btn-check" name="sidebar-type" id="mini-sidebar" autocomplete="off">
              <label class="btn p-9 btn-outline-primary rounded-2" for="mini-sidebar">
                <i class="icon ti ti-layout-sidebar fs-7 me-2"></i>Collapse
              </label>
            </div>
          </div>

          <h6 class="mt-5 fw-semibold fs-4 mb-2">Card With</h6>

          <div class="d-flex flex-row gap-3 customizer-box" role="group">
            <input type="radio" class="btn-check" name="card-layout" id="card-with-border" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="card-with-border">
              <i class="icon ti ti-border-outer fs-7 me-2"></i>Border
            </label>

            <input type="radio" class="btn-check" name="card-layout" id="card-without-border" autocomplete="off">
            <label class="btn p-9 btn-outline-primary rounded-2" for="card-without-border">
              <i class="icon ti ti-border-none fs-7 me-2"></i>Shadow
            </label>
          </div>
        </div>
      </div>

      <script>
  function handleColorTheme(e) {
    document.documentElement.setAttribute("data-color-theme", e);
  }
</script>
    </div>

    <!--  Search Bar -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-bottom">
            <input type="search" class="form-control" placeholder="{{ __('admin.dashboard_page.global_search_placeholder') }}" id="search">
            <a href="javascript:void(0)" data-bs-dismiss="modal" class="lh-1">
              <i class="ti ti-x fs-5 ms-3"></i>
            </a>
          </div>
          <div class="modal-body message-body" data-simplebar="" id="searchModalBody">
            <div id="quickLinksContainer">
              <h5 class="mb-0 fs-5 p-1">{{ __('admin.dashboard_page.quick_links') }}</h5>
              <ul class="list mb-0 py-2">
                <li class="p-1 mb-1 bg-hover-light-black rounded px-2">
                  <a href="{{ route('admin.dashboard') }}" class="text-decoration-none d-block">
                    <span class="text-dark fw-semibold d-block">{{ __('admin.dashboard') }}</span>
                    <span class="fs-2 d-block text-muted">/admin/dashboard</span>
                  </a>
                </li>
                {{-- Each shortcut follows its own permission: listing a link the
                     account cannot open only advertises screens it will be
                     bounced from. --}}
                @can('products.view')
                <li class="p-1 mb-1 bg-hover-light-black rounded px-2">
                  <a href="{{ route('admin.products.index') }}" class="text-decoration-none d-block">
                    <span class="text-dark fw-semibold d-block">{{ __('admin.menu.products') }}</span>
                    <span class="fs-2 d-block text-muted">/admin/catalog/products</span>
                  </a>
                </li>
                @endcan
                @can('orders.view')
                <li class="p-1 mb-1 bg-hover-light-black rounded px-2">
                  <a href="{{ route('admin.orders.index') }}" class="text-decoration-none d-block">
                    <span class="text-dark fw-semibold d-block">{{ __('admin.menu.orders') }}</span>
                    <span class="fs-2 d-block text-muted">/admin/orders</span>
                  </a>
                </li>
                @endcan
                @can('products.view')
                <li class="p-1 mb-1 bg-hover-light-black rounded px-2">
                  <a href="{{ route('admin.categories.index') }}" class="text-decoration-none d-block">
                    <span class="text-dark fw-semibold d-block">{{ __('admin.menu.categories') }}</span>
                    <span class="fs-2 d-block text-muted">/admin/catalog/categories</span>
                  </a>
                </li>
                <li class="p-1 mb-1 bg-hover-light-black rounded px-2">
                  <a href="{{ route('admin.brands.index') }}" class="text-decoration-none d-block">
                    <span class="text-dark fw-semibold d-block">{{ __('admin.menu.brands') }}</span>
                    <span class="fs-2 d-block text-muted">/admin/catalog/brands</span>
                  </a>
                </li>
                @endcan
                @can('users.view')
                <li class="p-1 mb-1 bg-hover-light-black rounded px-2">
                  <a href="{{ route('admin.users.index') }}" class="text-decoration-none d-block">
                    <span class="text-dark fw-semibold d-block">{{ __('admin.menu.users') }}</span>
                    <span class="fs-2 d-block text-muted">/admin/users</span>
                  </a>
                </li>
                @endcan
                @can('settings.view')
                <li class="p-1 mb-1 bg-hover-light-black rounded px-2">
                  <a href="{{ route('admin.settings.index') }}" class="text-decoration-none d-block">
                    <span class="text-dark fw-semibold d-block">{{ __('admin.menu.settings') }}</span>
                    <span class="fs-2 d-block text-muted">/admin/settings</span>
                  </a>
                </li>
                @endcan
              </ul>
            </div>
            <div id="searchResultsContainer" style="display: none;"></div>
          </div>
        </div>
      </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('search');
        const quickLinksContainer = document.getElementById('quickLinksContainer');
        const searchResultsContainer = document.getElementById('searchResultsContainer');
        let debounceTimer;
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        }[char]));
        const safeAdminLink = (value, locale) => {
            try {
                const parsed = new URL(String(value ?? ''), window.location.origin);
                return parsed.origin === window.location.origin && parsed.pathname.startsWith(`/${locale}/admin/`)
                    ? parsed.href
                    : '#';
            } catch (_) {
                return '#';
            }
        };

        searchInput.addEventListener('input', function () {
            const query = searchInput.value.trim();

            clearTimeout(debounceTimer);
            
            if (query.length < 2) {
                quickLinksContainer.style.display = 'block';
                searchResultsContainer.style.display = 'none';
                searchResultsContainer.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                searchResultsContainer.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <iconify-icon icon="solar:refresh-bold-duotone" class="fs-7 rotate-spinner d-inline-block mb-2"></iconify-icon>
                        <p class="mb-0 fs-3">{{ __('admin.dashboard_page.searching') }}</p>
                    </div>
                `;
                quickLinksContainer.style.display = 'none';
                searchResultsContainer.style.display = 'block';

                const locale = document.documentElement.lang || 'vi';
                fetch(`/${locale}/admin/search?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.results || data.results.length === 0) {
                            searchResultsContainer.innerHTML = `
                                <div class="text-center py-5 text-muted">
                                    <iconify-icon icon="solar:info-circle-broken" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                    <p class="mb-0 fs-4 fw-bold text-dark">${"{{ __('admin.dashboard_page.no_results') }}".replace(':query', query)}</p>
                                </div>
                            `;
                            return;
                        }

                        let html = '';
                        data.results.forEach(group => {
                            html += `
                                <div class="mb-4">
                                    <h5 class="mb-2 fs-4 p-1 d-flex align-items-center gap-2 text-primary border-bottom pb-2">
                                        <iconify-icon icon="${escapeHtml(group.icon || 'solar:link-bold-duotone')}" class="fs-5"></iconify-icon>
                                        <span>${escapeHtml(group.category)}</span>
                                    </h5>
                                    <ul class="list mb-0 py-1" style="list-style: none; padding-left: 0;">
                            `;
                            group.items.forEach(item => {
                                html += `
                                        <li class="p-2 mb-1 bg-hover-light-black rounded">
                                            <a href="${safeAdminLink(item.link, locale)}" class="d-flex align-items-center justify-content-between text-decoration-none">
                                                <div>
                                                    <span class="text-dark fw-semibold d-block fs-3">${escapeHtml(item.title)}</span>
                                                    <span class="fs-2 d-block text-muted">${escapeHtml(item.subtitle)}</span>
                                                </div>
                                                <iconify-icon icon="solar:alt-arrow-right-linear" class="fs-5 text-muted"></iconify-icon>
                                            </a>
                                        </li>
                                `;
                            });
                            html += `
                                    </ul>
                                </div>
                            `;
                        });

                        searchResultsContainer.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error during global search:', error);
                        searchResultsContainer.innerHTML = `
                            <div class="text-center py-5 text-danger">
                                <iconify-icon icon="solar:danger-broken" class="fs-9 mb-2 d-inline-block"></iconify-icon>
                                <p class="mb-0 fs-4">{{ __('admin.connection_error') }}</p>
                            </div>
                        `;
                    });
            }, 300);
        });

        // Reset search when modal is closed
        const modalElement = document.getElementById('exampleModal');
        if (modalElement) {
            modalElement.addEventListener('hidden.bs.modal', function () {
                searchInput.value = '';
                quickLinksContainer.style.display = 'block';
                searchResultsContainer.style.display = 'none';
                searchResultsContainer.innerHTML = '';
            });
            
            // Auto-focus search input when modal is shown
            modalElement.addEventListener('shown.bs.modal', function () {
                searchInput.focus();
            });
        }
    });
    </script>
    <style>
    .rotate-spinner {
        animation: spinner-rotation 1.3s linear infinite;
    }
    @keyframes spinner-rotation {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .bg-hover-light-black:hover {
        background-color: rgba(0, 0, 0, 0.03) !important;
    }
    #search {
        font-weight: 700 !important;
    }
    #search::placeholder {
        font-weight: 700 !important;
        color: #5a6a85 !important;
        opacity: 0.8;
    }
    </style>


  </div>
  <div class="dark-transparent sidebartoggler"></div>
