<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="ltr" data-bs-theme="light" data-color-theme="Orange_Theme" data-layout="vertical">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="{{ asset('admin-assets/images/logos/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/core-controls.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/sweetalert2/dist/sweetalert2.min.css') }}">
    <title>{{ __('auth.login.title') }} - {{ config('app.name', 'Laravel Ecommerce Core') }}</title>
</head>
<body>
<div class="preloader">
    <img src="{{ asset('admin-assets/images/logos/favicon.png') }}" alt="loader" class="lds-ripple img-fluid">
</div>

<div id="main-wrapper">
    <div class="position-relative overflow-hidden auth-bg min-vh-100 w-100 d-flex align-items-center justify-content-center">
        <div class="d-flex align-items-center justify-content-center w-100">
            <div class="row justify-content-center w-100 my-5 my-xl-0">
                <div class="col-md-9 d-flex flex-column justify-content-center">
                    <div class="card mb-0 bg-body auth-login m-auto w-100">
                        <div class="row gx-0">
                            <div class="col-xl-6 border-end">
                                <div class="row justify-content-center py-4">
                                    <div class="col-lg-11">
                                        <div class="card-body">
                                            <a href="{{ route('admin.login') }}" class="text-nowrap logo-img d-block mb-4 w-100">
                                                <img src="{{ asset('matbao-ws-logo.png') }}" width="140" class="dark-logo" alt="Logo">
                                            </a>
                                            <h2 class="lh-base mb-4">{{ __('auth.login.heading') }}</h2>

                                            <form method="POST" action="{{ route('admin.login.store') }}" id="login-form">
                                                @csrf

                                                <div class="mb-3">
                                                    <label for="email" class="form-label">{{ __('auth.login.email') }}</label>
                                                    <input type="email"
                                                           name="email"
                                                           class="form-control"
                                                           id="email"
                                                           value="{{ old('email') }}"
                                                           placeholder="{{ __('auth.login.email_placeholder') }}"
                                                           autocomplete="email"
                                                           autofocus>
                                                </div>

                                                <div class="mb-4">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <label for="password" class="form-label">{{ __('auth.login.password') }}</label>
                                                        <a class="text-primary link-dark fs-3" href="{{ route('admin.password.request') }}">{{ __('auth.login.forgot_password') }}</a>
                                                    </div>
                                                    <input type="password"
                                                           name="password"
                                                           class="form-control"
                                                           id="password"
                                                           placeholder="{{ __('auth.login.password_placeholder') }}"
                                                           autocomplete="current-password">
                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mb-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input primary" type="checkbox" value="1" name="remember" id="remember" @checked(old('remember'))>
                                                        <label class="form-check-label text-dark" for="remember">
                                                            {{ __('auth.login.remember') }}
                                                        </label>
                                                    </div>
                                                </div>

                                                <button type="submit" class="btn btn-primary w-100 py-8 mb-4 rounded-1" id="login-submit">
                                                    <span class="login-submit-text">{{ __('auth.login.submit') }}</span>
                                                    <span class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 d-none d-xl-block">
                                <div class="row justify-content-center align-items-start h-100">
                                    <div class="col-lg-9">
                                        <div id="auth-login" class="carousel slide auth-carousel mt-5 pt-4" data-bs-ride="carousel">
                                            <div class="carousel-indicators">
                                                <button type="button" data-bs-target="#auth-login" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                                <button type="button" data-bs-target="#auth-login" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                                <button type="button" data-bs-target="#auth-login" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                            </div>
                                            <div class="carousel-inner">
                                                @foreach(__('auth.login.slides') as $index => $slide)
                                                    <div class="carousel-item @if($index === 0) active @endif">
                                                        <div class="d-flex align-items-center justify-content-center w-100 h-100 flex-column gap-9 text-center">
                                                            <img src="{{ asset('admin-assets/images/backgrounds/login-side.png') }}" alt="login-side-img" width="300" class="img-fluid">
                                                            <h4 class="mb-0">{{ $slide['title'] }}</h4>
                                                            <p class="fs-12 mb-0">{{ $slide['description'] }}</p>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="dark-transparent sidebartoggler"></div>

<script src="{{ asset('admin-assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('admin-assets/libs/simplebar/dist/simplebar.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/theme/app.init.js') }}"></script>
<script src="{{ asset('admin-assets/js/theme/theme.js') }}"></script>
<script src="{{ asset('admin-assets/js/theme/app.min.js') }}"></script>
<script src="{{ asset('admin-assets/libs/sweetalert2/dist/sweetalert2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
@include('admin.layouts.toast')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('login-form');
        const submitButton = document.getElementById('login-submit');
        const submitText = submitButton.querySelector('.login-submit-text');
        const submitSpinner = submitButton.querySelector('.spinner-border');
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: function (toastElement) {
                toastElement.addEventListener('mouseenter', Swal.stopTimer);
                toastElement.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        const setLoading = function (isLoading) {
            submitButton.disabled = isLoading;
            submitSpinner.classList.toggle('d-none', !isLoading);
            submitText.textContent = isLoading ? @json(__('auth.login.processing')) : @json(__('auth.login.submit'));
        };

        const firstErrorMessage = function (payload) {
            if (payload && payload.errors) {
                const firstKey = Object.keys(payload.errors)[0];

                if (firstKey && payload.errors[firstKey] && payload.errors[firstKey][0]) {
                    return payload.errors[firstKey][0];
                }
            }

            return payload && payload.message ? payload.message : @json(__('auth.failed'));
        };

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            setLoading(true);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });
                const payload = await response.json();

                if (!response.ok || !payload.success) {
                    toast.fire({
                        icon: 'error',
                        title: firstErrorMessage(payload),
                    });
                    return;
                }

                toast.fire({
                    icon: 'success',
                    title: payload.message || @json(__('auth.login.success')),
                });

                window.setTimeout(function () {
                    window.location.href = payload.data.redirect;
                }, 500);
            } catch (error) {
                toast.fire({
                    icon: 'error',
                    title: @json(__('auth.login.request_failed')),
                });
            } finally {
                setLoading(false);
            }
        });
    });
</script>
</body>
</html>
