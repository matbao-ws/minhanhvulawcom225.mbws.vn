<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="{{ asset('admin-assets/images/logos/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/core-controls.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/sweetalert2/dist/sweetalert2.min.css') }}">
    <title>@yield('title', 'Admin') - {{ config('app.name', 'Laravel Ecommerce Core') }}</title>
    @stack('styles')
</head>

<body>
    <div class="preloader">
        <img src="{{ asset('admin-assets/images/logos/favicon.png') }}" alt="loader" class="lds-ripple img-fluid">
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

    <script src="{{ asset('admin-assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
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
        });
    </script>
    @stack('scripts')
</body>

</html>
