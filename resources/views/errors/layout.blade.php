<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') - {{ $siteBranding['name'] ?? config('app.name', 'MatBao WS') }}</title>

    @php
        $segments = request()->segments();
        if (!empty($segments) && app(\App\Services\LanguageRegistry::class)->supports($segments[0])) {
            app()->setLocale($segments[0]);
        }
        $currentLocale = app()->getLocale();
        $targetLocale = $currentLocale === 'vi' ? 'en' : 'vi';
        $targetLocaleLabel = $currentLocale === 'vi' ? 'English (EN)' : 'Tiếng Việt (VI)';
        $targetLocaleFlag = $currentLocale === 'vi' ? asset('admin-assets/images/flag/icon-flag-en.svg') : asset('admin-assets/images/flag/Flag_of_Vietnam.svg.png');
        $currentLocaleFlag = $currentLocale === 'vi' ? asset('admin-assets/images/flag/Flag_of_Vietnam.svg.png') : asset('admin-assets/images/flag/icon-flag-en.svg');

        // The same page in the other language: the default language's storefront pages have no
        // prefix, admin pages always keep it (App\Support\LocaleUrl).
        $switchUrl = \App\Support\LocaleUrl::switchTo($targetLocale);
        if ($query = request()->getQueryString()) {
            parse_str($query, $parsedQuery);
            unset($parsedQuery['locale']);
            if (!empty($parsedQuery)) {
                $switchUrl .= '?' . http_build_query($parsedQuery);
            }
        }

        // Phân biệt rạch ròi giữa khu vực Admin và Storefront (Client):
        $isAdminContext = $isAdminContext ?? (request()->is('*/admin*') || request()->is('admin*') || (request()->is('error-preview*') && request()->query('context') !== 'client'));
        $adminDashboardUrl = $adminDashboardUrl ?? route('admin.dashboard', ['locale' => $currentLocale]);
        $clientHomeUrl = $clientHomeUrl ?? (\Illuminate\Support\Facades\Route::has('client.home')
            ? route('client.home', ['locale' => $currentLocale])
            : (is_file(base_path('routes/theme.php')) ? \App\Support\LocaleUrl::home($currentLocale) : null));

        $favicon = $isAdminContext
            ? ($siteBranding['admin_favicon_url'] ?? asset('admin-assets/images/logos/favicon.png'))
            : ($siteBranding['favicon_url'] ?? asset('admin-assets/images/logos/favicon.png'));
        $headerLogoUrl = $isAdminContext
            ? ($siteBranding['admin_logo_url'] ?? asset('matbao-ws-logo.png'))
            : ($siteBranding['logo_url'] ?? $siteBranding['admin_logo_url'] ?? asset('matbao-ws-logo.png'));
        $headerLogoLink = $isAdminContext
            ? $adminDashboardUrl
            : ($clientHomeUrl ?: 'javascript:safeGoBack()');
    @endphp

    <link rel="icon" href="{{ $favicon }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #FF5238;
            --primary-gradient: linear-gradient(135deg, #FF6B4A 0%, #FF2E63 100%);
            --primary-hover: linear-gradient(135deg, #FF5530 0%, #E61E53 100%);
            --primary-shadow: rgba(255, 46, 99, 0.35);
            --dark: #0f172a;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-glass: rgba(255, 255, 255, 0.85);
            --card-bg: rgba(255, 255, 255, 0.88);
            --card-shadow: 0 30px 80px -20px rgba(220, 38, 38, 0.12), 0 16px 36px -12px rgba(15, 23, 42, 0.05);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Quicksand', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
            font-weight: 500;
            line-height: 1.6;
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-color: #faf4f5;
            background-image: url('{{ asset('admin-assets/images/backgrounds/error-bg.png') }}');
            background-repeat: no-repeat;
            background-position: center bottom;
            background-size: cover;
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            position: relative;
        }

        /* Subtle animated noise / sheen overlay for ultra-rich aesthetic */
        .ambient-mesh {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background: radial-gradient(circle at 15% 20%, rgba(255, 107, 74, 0.08) 0%, transparent 40%),
                        radial-gradient(circle at 85% 25%, rgba(255, 46, 99, 0.07) 0%, transparent 45%);
        }

        .error-page-container {
            position: relative;
            z-index: 1;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.5rem 1.25rem;
        }

        /* Navigation Header */
        .error-header {
            max-width: 1160px;
            width: 100%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1.25rem;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        .brand-logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--dark);
        }

        .brand-logo-img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .lang-switch-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            text-decoration: none;
            padding: 0.38rem 0.85rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.2s ease;
        }

        .lang-switch-btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .lang-flag {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Main Error Content */
        .error-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 0;
            width: 100%;
        }

        .error-card {
            max-width: 1160px;
            width: 100%;
            margin: 0 auto;
            background: var(--card-bg);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--border-glass);
            border-radius: 32px;
            box-shadow: var(--card-shadow);
            padding: 3rem 3.5rem;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 3.5rem;
            align-items: center;
            position: relative;
            overflow: hidden;
            animation: cardAppear 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes cardAppear {
            0% {
                opacity: 0;
                transform: translateY(24px) scale(0.98);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
/* 
        .error-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 32px 32px 0 0;
        } */

        /* Mascot Stage */
        .mascot-stage {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            user-select: none;
        }

        .mascot-ambient-glow {
            position: absolute;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(255, 94, 54, 0.16) 0%, rgba(255, 46, 99, 0.06) 45%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }

        .mascot-img {
            position: relative;
            z-index: 1;
            max-width: 100%;
            width: 440px;
            height: auto;
            object-fit: contain;
            animation: mascotFloat 4.2s ease-in-out infinite;
            filter: drop-shadow(0 20px 30px rgba(220, 38, 38, 0.16));
            -webkit-user-drag: none;
        }

        .mascot-shadow {
            width: 250px;
            height: 24px;
            background: radial-gradient(ellipse at center, rgba(225, 29, 72, 0.28) 0%, rgba(225, 29, 72, 0.08) 50%, transparent 75%);
            border-radius: 50%;
            margin-top: -12px;
            z-index: 0;
            animation: shadowPulse 4.2s ease-in-out infinite;
        }

        @keyframes mascotFloat {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-16px) rotate(0.8deg);
            }
        }

        @keyframes shadowPulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.85;
            }
            50% {
                transform: scale(0.8);
                opacity: 0.45;
            }
        }

        /* Text & Action Stage */
        .error-info {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            z-index: 1;
        }

        .error-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            align-self: flex-start;
            padding: 0.45rem 1rem;
            background: rgba(255, 78, 80, 0.1);
            border: 1px solid rgba(255, 78, 80, 0.22);
            border-radius: 9999px;
            font-size: 13.5px;
            font-weight: 700;
            color: #E11D48;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        .error-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #E11D48;
            box-shadow: 0 0 8px rgba(225, 29, 72, 0.6);
        }

        .error-title {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1.25;
            color: var(--dark);
            letter-spacing: -0.02em;
        }

        .error-desc {
            font-size: 15.5px;
            line-height: 1.65;
            color: var(--text-muted);
            font-weight: 500;
        }

        .error-tip-box {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.85rem 1.15rem;
            background: rgba(241, 245, 249, 0.75);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 14px;
            font-size: 13.5px;
            color: #475569;
        }

        .error-tip-icon {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            color: #F59E0B;
            margin-top: 2px;
        }

        /* Action Buttons */
        .error-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.85rem;
            margin-top: 0.5rem;
        }

        .btn-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            font-size: 14.5px;
            font-weight: 700;
            padding: 0.8rem 1.6rem;
            border-radius: 14px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            outline: none;
        }

        .btn-primary-cta {
            background: var(--primary-gradient);
            color: #ffffff;
            box-shadow: 0 10px 24px -6px var(--primary-shadow);
        }

        .btn-primary-cta:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -6px rgba(255, 46, 99, 0.5);
            color: #ffffff;
        }

        .btn-primary-cta:active {
            transform: translateY(0);
        }

        .btn-secondary-cta {
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid rgba(203, 213, 225, 0.8);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .btn-secondary-cta:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            transform: translateY(-2px);
            color: var(--dark);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.05);
        }

        .btn-secondary-cta:active {
            transform: translateY(0);
        }

        .btn-icon {
            width: 19px;
            height: 19px;
            transition: transform 0.2s ease;
        }

        .btn-cta:hover .btn-icon {
            transform: scale(1.1);
        }

        /* Quick Navigation Links */
        .quick-nav {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(226, 232, 240, 0.75);
            font-size: 13.5px;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .quick-nav-title {
            font-weight: 600;
            white-space: nowrap;
        }

        .quick-nav-links {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
        }

        .quick-link {
            white-space: nowrap;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .quick-link:hover {
            color: #D91B4C;
            text-decoration: underline;
        }

        /* Technical Details Drawer */
        .tech-details-box {
            margin-top: 0.75rem;
            border: 1px dashed rgba(203, 213, 225, 0.9);
            border-radius: 12px;
            overflow: hidden;
            font-size: 13px;
        }

        .tech-details-toggle {
            width: 100%;
            text-align: left;
            background: rgba(248, 250, 252, 0.8);
            padding: 0.6rem 0.9rem;
            border: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
        }

        .tech-details-toggle:hover {
            color: var(--dark);
            background: #f1f5f9;
        }

        .tech-details-content {
            padding: 0.75rem 0.9rem;
            background: #ffffff;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            color: #e11d48;
            max-height: 180px;
            overflow-y: auto;
            word-break: break-all;
            display: none;
        }

        .tech-details-content.show {
            display: block;
        }

        /* Footer */
        .error-footer {
            max-width: 1160px;
            width: 100%;
            margin: 1.5rem auto 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 13px;
            color: #64748b;
            padding: 0.5rem 1.25rem;
        }

        .footer-brand-name {
            font-weight: 600;
            color: var(--dark);
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .error-card {
                grid-template-columns: 1fr;
                padding: 2.25rem 1.75rem;
                gap: 2rem;
                text-align: center;
            }

            .error-badge {
                align-self: center;
            }

            .error-title {
                font-size: 1.85rem;
            }

            .mascot-img {
                width: 320px;
            }

            .mascot-shadow {
                width: 190px;
            }

            .error-actions {
                justify-content: center;
            }

            .quick-nav {
                justify-content: center;
                flex-direction: column;
                gap: 0.5rem;
            }

            .error-footer {
                justify-content: center;
                text-align: center;
            }
        }

        @media (max-width: 575px) {
            .error-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 0.65rem 1rem;
            }

            .brand-logo-img {
                height: 32px;
            }

            .lang-switch-btn {
                padding: 0.32rem 0.7rem;
                font-size: 12px;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .mascot-img {
                width: 250px;
            }

            .mascot-shadow {
                width: 150px;
            }

            .error-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-cta {
                width: 100%;
            }

            .error-card {
                padding: 2rem 1.25rem;
                border-radius: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="ambient-mesh"></div>

    <div class="error-page-container">
        <!-- Top Navigation Bar -->
        <header class="error-header">
            <a href="{{ $headerLogoLink }}" class="brand-logo-wrap" title="{{ $siteBranding['name'] ?? config('app.name') }}">
                <img src="{{ $headerLogoUrl }}" alt="{{ $siteBranding['name'] ?? config('app.name') }}" class="brand-logo-img">
            </a>

            <div class="header-actions">
                <a href="{{ $switchUrl }}" class="lang-switch-btn" title="{{ $targetLocaleLabel }}">
                    <img src="{{ $targetLocaleFlag }}" alt="{{ $targetLocaleLabel }}" class="lang-flag">
                    <span>{{ strtoupper($targetLocale) }}</span>
                </a>
            </div>
        </header>

        <!-- Main Stage -->
        <main class="error-main">
            <div class="error-card">
                <!-- Left: 3D Mascot Stage -->
                <div class="mascot-stage">
                    <div class="mascot-ambient-glow"></div>
                    <img src="@yield('image')" alt="@yield('code', 'Error')" class="mascot-img" />
                    <div class="mascot-shadow"></div>
                </div>

                <!-- Right: Content & Action Stage -->
                <div class="error-info">
                    <div class="error-badge">
                        <span class="error-badge-dot"></span>
                        <span>@yield('badge')</span>
                    </div>

                    <h1 class="error-title">@yield('heading')</h1>

                    <p class="error-desc">@yield('message')</p>

                    @hasSection('tip')
                        <div class="error-tip-box">
                            <svg class="error-tip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <div>@yield('tip')</div>
                        </div>
                    @endif

                    <!-- Action Controls -->
                    <div class="error-actions">
                        @yield('actions')
                    </div>

                    <!-- Helpful Links -->
                    <div class="quick-nav">
                        <span class="quick-nav-title">{{ __('errors.common.helpful_links') }}</span>
                        <div class="quick-nav-links">
                            @if($isAdminContext)
                                <a href="{{ $adminDashboardUrl }}" class="quick-link">{{ __('errors.common.go_to_dashboard') }}</a>
                                @if($clientHomeUrl)
                                    <a href="{{ $clientHomeUrl }}" class="quick-link" target="_blank">{{ __('errors.common.back_to_home') }}</a>
                                @endif
                            @else
                                <a href="javascript:safeGoBack()" class="quick-link">{{ __('errors.common.go_back') }}</a>
                                @if($clientHomeUrl)
                                    <a href="{{ $clientHomeUrl }}" class="quick-link">{{ __('errors.common.back_to_home') }}</a>
                                @endif
                            @endif
                            <a href="mailto:{{ $siteBranding['contact']['email'] ?? 'support@matbao.com' }}" class="quick-link">{{ __('errors.common.contact_support') }}</a>
                        </div>
                    </div>

                    {{-- An error code to quote to support, for shoppers and admins alike. Nothing technical
                         is shown here (the detail is in the log under that code), so nothing to fold away. --}}
                    @hasSection('details')
                        <div class="tech-details-box">
                            <div class="tech-details-content show" id="tech-details-body">
                                @yield('details')
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="error-footer">
            <div>
                &copy; {{ date('Y') }} <span class="footer-brand-name">{{ $siteBranding['name'] ?? config('app.name', 'MatBao WS') }}</span>. {{ __('errors.common.all_rights_reserved') }}
            </div>
            <div>
                <span style="opacity: 0.8;">HTTP @yield('code')</span>
            </div>
        </footer>
    </div>

    <script>
        function safeGoBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = "{{ $headerLogoLink }}";
            }
        }
    </script>
</body>
</html>
