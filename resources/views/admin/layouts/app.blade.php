<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ $siteBranding['favicon_url'] }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/core-controls.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/sweetalert2/dist/sweetalert2.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body, h1, h2, h3, h4, h5, h6, .sidebar-nav, .topbar, * {
            font-family: 'Quicksand', sans-serif !important;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
            font-weight: 600 !important;
        }
        
        /* Global typography & readability improvements for Admin */
        body, p, .text-muted, .description, .card-subtitle, .form-text, .fs-12, .fs-13, .fs-14 {
            font-weight: 500 !important;
        }
        .text-muted:not([class*="fs-"]) {
            color: #5a6a85 !important;
            font-size: 13.5px !important;
        }
        
        /* Unified font-size and typography to match dashboard */
        .card-body h5, 
        .card h5,
        h5.fw-semibold,
        .card-title {
            font-size: 18px !important;
            color: #2a3547 !important;
            font-weight: 600 !important;
        }
        .card-body p:not([class*="fs-"]),
        p:not([class*="fs-"]) {
            font-size: 15px !important;
        }
        
        /* Make form elements and labels slightly larger and highly readable */
        label, 
        .form-label {
            font-size: 14.5px !important;
            font-weight: 600 !important;
            color: #2a3547 !important;
        }
        input, 
        textarea, 
        .form-control {
            font-size: 14.5px !important;
            color: #2a3547 !important;
        }
        select, 
        .form-select,
        .dropdown-toggle {
            font-size: 14.5px !important;
            font-weight: 600 !important;
            color: #2a3547 !important;
        }
        .btn {
            font-size: 14.5px !important;
            font-weight: 600 !important;
        }
        
        /* Dropdown menu items font size and icon size alignment */
        .dropdown-menu {
            font-size: 14.5px !important;
        }
        .dropdown-item {
            font-size: 14.5px !important;
            font-weight: 600 !important;
        }
        .dropdown-item i,
        .dropdown-item iconify-icon {
            font-size: 17px !important;
        }
        
        /* Select2 override for premium, dark, and clear text size */
        /* Fix Bootstrap form-select CSS bug on multi-select elements */
        select[multiple],
        select.form-select[multiple],
        select.form-control[multiple] {
            height: auto !important;
            min-height: 150px !important;
            background-image: none !important;
            padding: 8px 12px !important;
            border-radius: 7px !important;
            border: 1px solid #dfe5ef !important;
        }
        select[multiple] option,
        select.form-select[multiple] option {
            padding: 6px 10px !important;
            border-radius: 4px !important;
            margin-bottom: 2px !important;
        }

        /* Select2 override for premium, dark, and clear text size */
        .select2-container .select2-selection--single {
            height: 38px !important;
            border: 1px solid #dfe5ef !important;
            border-radius: 7px !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            font-size: 14.5px !important;
            font-weight: 600 !important;
            color: #2a3547 !important; /* Dark bold text color */
            padding-left: 12px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
            right: 8px !important;
        }
        .select2-container--default .select2-selection--multiple {
            min-height: 42px !important;
            border: 1px solid #dfe5ef !important;
            border-radius: 7px !important;
            padding: 4px 8px !important;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #5d87ff !important;
            box-shadow: 0 0 0 0.25rem rgba(93, 135, 255, 0.15) !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #ecf2ff !important;
            border: 1px solid #dbe7ff !important;
            color: #2a3547 !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            border-radius: 5px !important;
            padding: 3px 8px !important;
            margin-top: 4px !important;
            margin-right: 6px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #ef4444 !important;
            margin-right: 6px !important;
            font-weight: bold !important;
            border: none !important;
            background: transparent !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #dc2626 !important;
        }
        .select2-dropdown {
            border: 1px solid #dfe5ef !important;
            border-radius: 7px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
        }
        .select2-results__option {
            font-size: 14.5px !important;
            font-weight: 600 !important;
            color: #2a3547 !important;
            padding: 8px 12px !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #ecf2ff !important;
            color: #c01e25 !important; /* Highlight with primary red color! */
        }
        .select2-container--default .select2-results__option[aria-selected="true"] {
            background-color: #f8f9fa !important;
            color: #2a3547 !important;
        }

        /* Global Nav Tabs styling matching Settings page */
        .nav-tabs {
            border-bottom: 2px solid var(--bs-primary) !important;
            gap: 8px;
        }
        .nav-tabs .nav-link {
            border: 1px solid var(--bs-primary) !important;
            border-bottom: none !important;
            color: var(--bs-primary) !important;
            background-color: #fff !important;
            font-weight: 700 !important;
            border-radius: 6px 6px 0 0 !important;
            padding: 10px 24px !important;
            transition: all 0.2s ease-in-out;
            font-size: 15px;
        }
        .nav-tabs .nav-link.active {
            color: #fff !important;
            background-color: var(--bs-primary) !important;
            border: 1px solid var(--bs-primary) !important;
            border-bottom: none !important;
        }
        .nav-tabs .nav-link:hover:not(.active) {
            background-color: rgba(var(--bs-primary-rgb), 0.1) !important;
        }

        /* Quill picker/dropdown overrides */
        .ql-toolbar .ql-picker {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #2a3547 !important;
        }
        .ql-toolbar .ql-picker-label {
            color: #2a3547 !important;
        }
        .ql-toolbar .ql-picker-options {
            border-radius: 6px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
        }
        .ql-toolbar .ql-picker-item {
            font-size: 13.5px !important;
            font-weight: 600 !important;
            color: #2a3547 !important;
        }
        
        /* Help notes/subtexts under inputs inside forms */
        .card-body p.fs-2,
        .form-text {
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #5a6a85 !important; /* Dark slate grey / black */
            margin-top: 6px !important;
        }
        
        /* Table styling overrides */
        .table th {
            font-size: 15px !important;
            font-weight: 700 !important;
            color: #2a3547 !important;
            text-transform: none !important;
            background-color: #ecf2ff !important;
            border-bottom: 2px solid #dbe7ff !important;
        }
        .table td {
            font-size: 14.5px !important;
            color: #2a3547 !important;
        }
        .table td .text-dark {
            color: #2a3547 !important;
            font-weight: 600 !important;
        }
        .table td .text-muted:not([class*="fs-"]) {
            font-size: 13.5px !important;
            color: #5a6a85 !important;
        }
        
        /* Even rows get a very light grey background for zebra striping effect */
        .table tbody tr:nth-child(even) td {
            background-color: #f8f9fa !important;
        }
        
        /* Pagination overrides to use the brand primary cherry red (#c01e25) */
        .page-link {
            color: #c01e25 !important;
            font-weight: 600 !important;
            border-color: #dee2e6 !important;
        }
        .page-link:hover {
            color: #9c151a !important;
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
        }
        .page-item.active .page-link {
            background-color: #c01e25 !important;
            border-color: #c01e25 !important;
            color: #ffffff !important;
        }
        .page-item.disabled .page-link {
            color: #6c757d !important;
            background-color: #ffffff !important;
            border-color: #dee2e6 !important;
        }
        .page-link:focus {
            box-shadow: 0 0 0 0.25rem rgba(192, 30, 37, 0.25) !important;
        }

        /* Increase font weight of the table footer pagination text */
        .card-footer,
        .card-footer p,
        .card-footer span,
        .card-footer div {
            font-weight: 600 !important;
        }
        
        /* Badges size and style overrides for a soft, premium, non-glaring color palette */
        .badge {
            font-size: 12px !important;
            padding: 5px 10px !important;
            font-weight: 600 !important;
        }
        /* .bg-primary-subtle {
            background-color: #ecf2ff !important;
        } */
        /* .text-primary {
            color: #3e66fb !important;
        } */
        .bg-success-subtle {
            background-color: #e6f9ed !important;
        }
        .text-success {
            color: #137333 !important;
        }
        .bg-danger-subtle {
            background-color: #fce8e6 !important;
        }
        .text-danger {
            color: #c5221f !important;
        }
        .bg-warning-subtle {
            background-color: #fef7e0 !important;
        }
        .text-warning {
            color: #b06000 !important;
        }
        .bg-info-subtle {
            background-color: #e4f7fb !important;
        }
        .text-info {
            color: #007b83 !important;
        }
        
        /* Soft overrides for solid badges to render as premium subtle badges */
        .badge.bg-primary {
            background-color: #ecf2ff !important;
            color: #3e66fb !important;
        }
        .badge.bg-success {
            background-color: #e6f9ed !important;
            color: #137333 !important;
        }
        .badge.bg-danger {
            background-color: #fce8e6 !important;
            color: #c5221f !important;
        }
        .badge.bg-warning {
            background-color: #fef7e0 !important;
            color: #b06000 !important;
        }
        .badge.bg-info {
            background-color: #e4f7fb !important;
            color: #007b83 !important;
        }
        
        /* Round the bottom corners of tables and cards, preventing sharp footer edges */
        .card {
            overflow: hidden !important;
        }
        .table-responsive {
            border-bottom-left-radius: 7px !important;
            border-bottom-right-radius: 7px !important;
            overflow-x: auto !important;
        }
        .table {
            margin-bottom: 0 !important;
        }
        .table tr:last-child td:first-child {
            border-bottom-left-radius: 7px !important;
        }
        .table tr:last-child td:last-child {
            border-bottom-right-radius: 7px !important;
        }
        
        /* Softer, premium colors for all status buttons to avoid glaring colors */
        .btn-primary {
            background-color: #c01e25 !important;
            border-color: #c01e25 !important;
            color: #ffffff !important;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: #9c151a !important;
            border-color: #9c151a !important;
            color: #ffffff !important;
        }
        
        .btn-outline-primary {
            background-color: transparent !important;
            border-color: #c01e25 !important;
            color: #c01e25 !important;
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary:active {
            background-color: #c01e25 !important;
            border-color: #c01e25 !important;
            color: #ffffff !important;
        }
        
        .btn-success {
            background-color: #2b8a3e !important;
            border-color: #2b8a3e !important;
            color: #ffffff !important;
        }
        .btn-success:hover, .btn-success:focus, .btn-success:active {
            background-color: #206a2e !important;
            border-color: #206a2e !important;
            color: #ffffff !important;
        }
        
        .btn-danger {
            background-color: #c5221f !important;
            border-color: #c5221f !important;
            color: #ffffff !important;
        }
        .btn-danger:hover, .btn-danger:focus, .btn-danger:active {
            background-color: #9f1c1a !important;
            border-color: #9f1c1a !important;
            color: #ffffff !important;
        }
        
        .btn-warning {
            background-color: #d97706 !important;
            border-color: #d97706 !important;
            color: #ffffff !important;
        }
        .btn-warning:hover, .btn-warning:focus, .btn-warning:active {
            background-color: #b45309 !important;
            border-color: #b45309 !important;
            color: #ffffff !important;
        }
        
        .btn-info {
            background-color: #0d8a9e !important;
            border-color: #0d8a9e !important;
            color: #ffffff !important;
        }
        .btn-info:hover, .btn-info:focus, .btn-info:active {
            background-color: #0a6c7c !important;
            border-color: #0a6c7c !important;
            color: #ffffff !important;
        }
        
        .btn-outline-secondary,
        .btn-outline-info {
            background-color: transparent !important;
            border-color: #007b83 !important;
            color: #007b83 !important;
        }
        .btn-outline-secondary:hover, .btn-outline-secondary:focus, .btn-outline-secondary:active,
        .btn-outline-info:hover, .btn-outline-info:focus, .btn-outline-info:active {
            background-color: #e4f7fb !important;
            border-color: #007b83 !important;
            color: #007b83 !important;
        }

        /* Make topbar header navigation icons darker and more prominent */
        .topbar .navbar .navbar-nav .nav-item .nav-link iconify-icon {
            color: #000000 !important;
        }
        .topbar .navbar .navbar-nav .nav-item .nav-link:hover iconify-icon {
            color: #c01e25 !important;
        }
        .topbar .navbar .navbar-nav .nav-item .nav-link .site-name {
            color: #000000 !important;
            font-weight: 600;
            font-size: 15px;
            transition: color 0.15s ease-in-out;
        }
        .topbar .navbar .navbar-nav .nav-item .nav-link:hover .site-name {
            color: #c01e25 !important;
        }
    </style>
    <title>@yield('title', 'Admin') - {{ $siteBranding['name'] }}</title>
    @stack('styles')
</head>

<body>
    @if(session()->has('impersonated_by'))
        <div class="alert alert-warning border-0 rounded-0 mb-0 py-2 d-flex align-items-center justify-content-between px-4" style="background-color: #fff3cd !important; border-bottom: 1px solid #ffeeba !important; position: sticky; top: 0; z-index: 99999;">
            <div class="d-flex align-items-center gap-2" style="color: #856404 !important; font-weight: 600; font-size: 14.5px;">
                <iconify-icon icon="solar:info-circle-line-duotone" class="fs-6"></iconify-icon>
                <span>Bạn đang đăng nhập với vai trò <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</span>
            </div>
            <form method="POST" action="{{ route('admin.users.impersonate.leave', ['locale' => app()->getLocale()]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning fw-bold" style="font-size: 13px; border-radius: 6px; padding: 4px 12px;">
                    Quay lại Admin
                </button>
            </form>
        </div>
    @endif

    <div class="preloader">
        <img src="{{ $siteBranding['favicon_url'] }}" alt="{{ $siteBranding['name'] }}" class="lds-ripple img-fluid">
    </div>

    <div id="main-wrapper">
        @include('admin.layouts.sidebar')

        <div class="page-wrapper">
            @include('admin.layouts.header')

            <div class="body-wrapper">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>

            @include('admin.layouts.footer')
        </div>
    </div>

    <div class="dark-transparent sidebartoggler"></div>

    <script src="{{ asset('admin-assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
    @can('media.view')
        @include('admin.layouts.media-picker')
    @endcan
    <script src="{{ asset('admin-assets/libs/simplebar/dist/simplebar.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/theme/app.init.js') }}"></script>
    <script src="{{ asset('admin-assets/js/theme/theme.js') }}"></script>
    <script src="{{ asset('admin-assets/js/theme/app.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/theme/sidebarmenu.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/sweetalert2/dist/sweetalert2.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    @include('admin.layouts.toast')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Collapse list filters on mobile while keeping them visible on desktop.
            document.querySelectorAll('form[data-responsive-filters]').forEach(function (form, index) {
                if (form.closest('.admin-responsive-filter-collapse')) return;

                const collapseId = 'admin-responsive-filters-' + index;
                const hasActiveFilters = Array.from(new FormData(form).entries()).some(function (entry) {
                    return entry[0] !== 'page' && String(entry[1]).trim() !== '';
                });
                const collapse = document.createElement('div');
                collapse.id = collapseId;
                collapse.className = 'collapse d-md-block admin-responsive-filter-collapse' + (hasActiveFilters ? ' show' : '');

                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'btn btn-outline-primary d-md-none w-100 d-flex align-items-center justify-content-between mb-2 admin-responsive-filter-toggle';
                toggle.setAttribute('data-bs-toggle', 'collapse');
                toggle.setAttribute('data-bs-target', '#' + collapseId);
                toggle.setAttribute('aria-controls', collapseId);
                toggle.setAttribute('aria-expanded', hasActiveFilters ? 'true' : 'false');
                toggle.innerHTML = '<span><i class="ti ti-adjustments-horizontal me-2"></i>'
                    + @json(app()->getLocale() === 'vi' ? 'Bộ lọc' : 'Filters')
                    + '</span><i class="ti ti-chevron-down"></i>';

                form.parentNode.insertBefore(toggle, form);
                form.parentNode.insertBefore(collapse, form);
                collapse.appendChild(form);
            });

            // Global SweetAlert2 delete confirmation
            document.body.addEventListener('submit', function (e) {
                const form = e.target;
                if (form.classList.contains('js-delete-form')) {
                    e.preventDefault();
                    Swal.fire({
                        title: form.dataset.confirmTitle || "{{ __('catalog.actions.confirm_delete') }}",
                        text: form.dataset.confirmText || "",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e32326',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: form.dataset.confirmBtn || "{{ __('catalog.actions.delete') }}",
                        cancelButtonText: "{{ __('catalog.actions.cancel') }}",
                        focusCancel: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                }
            });

            // Advanced Quill Image Resizer & Alignment Toolbar (Vanilla JS)
            let activeImage = null;
            let overlay = null;
            let toolbar = null;

            document.addEventListener('click', function (e) {
                if (e.target.tagName === 'IMG' && e.target.closest('.ql-editor')) {
                    const img = e.target;
                    e.preventDefault();
                    e.stopPropagation();
                    setupImageOverlay(img);
                } else if (overlay && !overlay.contains(e.target) && (!toolbar || !toolbar.contains(e.target))) {
                    removeOverlay();
                }
            }, true);

            function setupImageOverlay(img) {
                if (activeImage === img) return;
                removeOverlay();
                
                activeImage = img;
                
                // Create overlay wrapper that mirrors the image position
                overlay = document.createElement('div');
                overlay.style.position = 'absolute';
                overlay.style.border = '2px dashed var(--bs-primary)';
                overlay.style.pointerEvents = 'none';
                overlay.style.zIndex = '9999';
                document.body.appendChild(overlay);
                
                // Create alignment toolbar
                toolbar = document.createElement('div');
                toolbar.style.position = 'absolute';
                toolbar.style.backgroundColor = '#2a3547';
                toolbar.style.borderRadius = '6px';
                toolbar.style.padding = '6px 8px';
                toolbar.style.display = 'flex';
                toolbar.style.gap = '8px';
                toolbar.style.zIndex = '10000';
                toolbar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                
                const buttons = [
                    { icon: 'solar:align-left-linear', text: 'Trái', action: () => alignImage('left') },
                    { icon: 'solar:align-horizontaly-linear', text: 'Giữa', action: () => alignImage('center') },
                    { icon: 'solar:align-right-linear', text: 'Phải', action: () => alignImage('right') },
                    { icon: 'solar:trash-bin-trash-bold-duotone', text: 'Xóa', color: '#ef4444', action: () => deleteImage() }
                ];
                
                buttons.forEach(btn => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.style.background = 'none';
                    button.style.border = 'none';
                    button.style.color = btn.color || '#fff';
                    button.style.cursor = 'pointer';
                    button.style.display = 'flex';
                    button.style.alignItems = 'center';
                    button.style.gap = '4px';
                    button.style.fontSize = '12px';
                    button.style.fontWeight = 'bold';
                    button.style.padding = '4px 8px';
                    button.style.borderRadius = '4px';
                    button.innerHTML = `<iconify-icon icon="${btn.icon}" style="font-size: 16px;"></iconify-icon> ${btn.text}`;
                    
                    button.addEventListener('mouseenter', () => {
                        button.style.backgroundColor = btn.color ? 'rgba(239, 68, 68, 0.2)' : 'rgba(255,255,255,0.1)';
                    });
                    button.addEventListener('mouseleave', () => {
                        button.style.backgroundColor = 'transparent';
                    });
                    
                    button.addEventListener('click', (clickEvent) => {
                        clickEvent.preventDefault();
                        clickEvent.stopPropagation();
                        btn.action();
                    });
                    
                    toolbar.appendChild(button);
                });
                
                document.body.appendChild(toolbar);
                
                // Add 4 corner handles
                const handles = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
                handles.forEach(pos => {
                    const handle = document.createElement('div');
                    handle.style.position = 'absolute';
                    handle.style.width = '10px';
                    handle.style.height = '10px';
                    handle.style.backgroundColor = 'var(--bs-primary)';
                    handle.style.border = '1px solid white';
                    handle.style.pointerEvents = 'auto';
                    handle.style.zIndex = '10001';
                    
                    if (pos === 'top-left') {
                        handle.style.cursor = 'nwse-resize';
                    } else if (pos === 'top-right') {
                        handle.style.cursor = 'nesw-resize';
                    } else if (pos === 'bottom-left') {
                        handle.style.cursor = 'nesw-resize';
                    } else if (pos === 'bottom-right') {
                        handle.style.cursor = 'nwse-resize';
                    }
                    
                    handle.addEventListener('mousedown', function (dragEvent) {
                        dragEvent.preventDefault();
                        dragEvent.stopPropagation();
                        
                        const startX = dragEvent.clientX;
                        const startWidth = activeImage.clientWidth;
                        
                        function onMouseMove(moveEvent) {
                            let deltaX = moveEvent.clientX - startX;
                            if (pos === 'top-left' || pos === 'bottom-left') {
                                deltaX = -deltaX;
                            }
                            
                            const newWidth = Math.max(30, startWidth + deltaX);
                            activeImage.style.width = newWidth + 'px';
                            activeImage.style.height = 'auto';
                            
                            updateOverlayPosition();
                            triggerEditorUpdate();
                        }
                        
                        function onMouseUp() {
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                        }
                        
                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    });
                    
                    overlay.appendChild(handle);
                });
                
                function updateOverlayPosition() {
                    if (!activeImage) return;
                    const rect = activeImage.getBoundingClientRect();
                    const scrollY = window.scrollY;
                    const scrollX = window.scrollX;
                    
                    overlay.style.top = (scrollY + rect.top) + 'px';
                    overlay.style.left = (scrollX + rect.left) + 'px';
                    overlay.style.width = rect.width + 'px';
                    overlay.style.height = rect.height + 'px';
                    
                    toolbar.style.top = (scrollY + rect.top - 45) + 'px';
                    toolbar.style.left = (scrollX + rect.left + (rect.width - toolbar.offsetWidth) / 2) + 'px';
                    
                    const children = overlay.children;
                    if (children.length === 4) {
                        children[0].style.top = '-5px';
                        children[0].style.left = '-5px';
                        children[1].style.top = '-5px';
                        children[1].style.right = '-5px';
                        children[2].style.bottom = '-5px';
                        children[2].style.left = '-5px';
                        children[3].style.bottom = '-5px';
                        children[3].style.right = '-5px';
                    }
                }
                
                updateOverlayPosition();
                
                window.addEventListener('scroll', updateOverlayPosition, { passive: true });
                window.addEventListener('resize', updateOverlayPosition, { passive: true });
                
                setTimeout(updateOverlayPosition, 10);
            }

            function alignImage(alignment) {
                if (!activeImage) return;
                
                if (alignment === 'left') {
                    activeImage.style.float = 'left';
                    activeImage.style.display = 'inline';
                    activeImage.style.margin = '5px 15px 15px 0';
                } else if (alignment === 'right') {
                    activeImage.style.float = 'right';
                    activeImage.style.display = 'inline';
                    activeImage.style.margin = '5px 0 15px 15px';
                } else if (alignment === 'center') {
                    activeImage.style.float = 'none';
                    activeImage.style.display = 'block';
                    activeImage.style.margin = '15px auto';
                }
                
                setTimeout(() => {
                    if (!activeImage) return;
                    const rect = activeImage.getBoundingClientRect();
                    overlay.style.top = (window.scrollY + rect.top) + 'px';
                    overlay.style.left = (window.scrollX + rect.left) + 'px';
                    overlay.style.width = rect.width + 'px';
                    overlay.style.height = rect.height + 'px';
                    toolbar.style.top = (window.scrollY + rect.top - 45) + 'px';
                    toolbar.style.left = (window.scrollX + rect.left + (rect.width - toolbar.offsetWidth) / 2) + 'px';
                }, 50);
                
                triggerEditorUpdate();
            }

            function deleteImage() {
                if (!activeImage) return;
                const editor = activeImage.closest('.ql-editor');
                activeImage.remove();
                removeOverlay();
                if (editor) {
                    const catalogQuill = editor.closest('.catalog-quill');
                    if (catalogQuill) {
                        const targetInput = document.getElementById(catalogQuill.dataset.target);
                        if (targetInput) {
                            targetInput.value = editor.innerHTML;
                        }
                    }
                    const postEditor = document.getElementById('content_editor');
                    if (postEditor && postEditor.contains(editor)) {
                        const contentInput = document.getElementById('content_input');
                        if (contentInput) {
                            contentInput.value = editor.innerHTML;
                        }
                    }
                }
            }

            function triggerEditorUpdate() {
                if (!activeImage) return;
                const editor = activeImage.closest('.ql-editor');
                if (editor) {
                    const catalogQuill = editor.closest('.catalog-quill');
                    if (catalogQuill) {
                        const targetInput = document.getElementById(catalogQuill.dataset.target);
                        if (targetInput) {
                            targetInput.value = editor.innerHTML;
                        }
                    }
                    const postEditor = document.getElementById('content_editor');
                    if (postEditor && postEditor.contains(editor)) {
                        const contentInput = document.getElementById('content_input');
                        if (contentInput) {
                            contentInput.value = editor.innerHTML;
                        }
                    }
                }
            }

            function removeOverlay() {
                activeImage = null;
                if (overlay) {
                    overlay.remove();
                    overlay = null;
                }
                if (toolbar) {
                    toolbar.remove();
                    toolbar = null;
                }
            }
        });
    </script>
    <script src="{{ asset('admin-assets/js/form-guidance.js') }}"></script>
    @stack('scripts')
</body>

</html>
