<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="ltr" data-bs-theme="light" data-color-theme="Orange_Theme" data-layout="vertical">
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
        body, h1, h2, h3, h4, h5, h6, input, button, select, textarea, label, .form-control, * {
            font-family: 'Quicksand', sans-serif !important;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
            font-weight: 600 !important;
        }
        body, p, .text-muted, .fs-12, .form-label, .form-text, .description {
            font-weight: 500 !important;
        }
    </style>
    <title>{{ __('auth.reset.title') }} - {{ $siteBranding['name'] }}</title>
</head>
<body>
<div class="preloader">
    <img src="{{ $siteBranding['favicon_url'] }}" alt="{{ $siteBranding['name'] }}" class="lds-ripple img-fluid">
</div>

<div id="main-wrapper">
    <div class="position-relative overflow-hidden auth-bg min-vh-100 w-100 d-flex align-items-center justify-content-center">
        <div class="d-flex align-items-center justify-content-center w-100">
            <div class="row justify-content-center w-100 my-5 my-xl-0">
                <div class="col-md-5">
                    <div class="card mb-0 bg-body auth-login m-auto w-100">
                        <div class="card-body">
                            <a href="{{ $loginUrl ?? route('admin.login') }}" class="text-nowrap logo-img d-block mb-4 w-100">
                                <img src="{{ $siteBranding['admin_logo_url'] }}" width="140" class="dark-logo" alt="MatBaoWS">
                            </a>
                            <h2 class="lh-base mb-4">{{ __('auth.reset.heading') }}</h2>

                            <form method="POST" action="{{ $formAction ?? route('admin.password.update') }}">
                                @csrf
                                <input type="hidden" name="token" value="{{ $token }}">

                                <div class="mb-3">
                                    <label for="email" class="form-label">{{ __('auth.login.email') }}</label>
                                    <input type="email" name="email" class="form-control" id="email" value="{{ old('email', $email) }}" autocomplete="email" autofocus>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">{{ __('auth.reset.password') }}</label>
                                    <input type="password" name="password" class="form-control" id="password" autocomplete="new-password">
                                </div>

                                <div class="mb-4">
                                    <label for="password_confirmation" class="form-label">{{ __('auth.reset.password_confirmation') }}</label>
                                    <input type="password" name="password_confirmation" class="form-control" id="password_confirmation" autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-8 mb-4 rounded-1">
                                    {{ __('auth.reset.submit') }}
                                </button>
                                <a href="{{ $loginUrl ?? route('admin.login') }}" class="btn bg-primary-subtle text-primary w-100 py-8 rounded-1">
                                    {{ __('auth.forgot.back_to_login') }}
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('admin-assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/theme/app.init.js') }}"></script>
<script src="{{ asset('admin-assets/js/theme/theme.js') }}"></script>
<script src="{{ asset('admin-assets/js/theme/app.min.js') }}"></script>
<script src="{{ asset('admin-assets/libs/sweetalert2/dist/sweetalert2.min.js') }}"></script>
@include('admin.layouts.toast')
</body>
</html>
