<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ | Quản trị rủi ro pháp lý cùng bạn')</title>
  @hasSection('meta_description')
    <meta name="description" content="@yield('meta_description')">
  @endif
  <link rel="icon" href="{{ $siteBranding['favicon_url'] ?? asset('assets/uploads/2024/10/cropped-favicon-32x32.png') }}" sizes="32x32" />
  <link rel="icon" href="{{ asset('assets/uploads/2024/10/cropped-favicon-192x192.png') }}" sizes="192x192" />
  <link rel="apple-touch-icon" href="{{ asset('assets/uploads/2024/10/cropped-favicon-180x180.png') }}" />

  <!-- Google Fonts: Inter & Dancing Script -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- FontAwesome Icons CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: {
              50: '#f0f4f8',
              100: '#d9e2ec',
              200: '#bcccdc',
              300: '#9fb3c8',
              400: '#627d98',
              500: '#486581',
              600: '#334e68',
              700: '#243b53',
              800: '#102a43',
              900: '#0b1a30',
              950: '#06101e',
            },
            brand: {
              red: '#dc2626',
              redDark: '#b91c1c',
              gold: '#c59b27',
              goldLight: '#e5be53'
            }
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
            accent: ['Dancing Script', 'cursive']
          }
        }
      }
    }
  </script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      font-size: 16px;
      line-height: 1.6;
    }
    .font-accent {
      font-family: 'Dancing Script', cursive;
    }
    .glass-navy {
      background-color: rgba(11, 26, 48, 0.98);
      backdrop-filter: blur(10px);
    }
    .nav-item-dropdown:hover .dropdown-menu {
      display: block;
    }
    @keyframes dropdownFadeIn {
      from {
        opacity: 0;
        transform: translateY(8px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .animate-fadeIn {
      animation: dropdownFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
  </style>
  @stack('styles')
  @include('core.head')
</head>
<body class="bg-white text-slate-700 antialiased flex flex-col min-h-screen">
  @include('client.partials.header')

  @if(session('contact_success'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full mt-4">
      <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-start gap-3" role="status">
        <i class="fa-solid fa-circle-check text-green-600 text-lg mt-0.5 flex-shrink-0"></i>
        <div>{{ session('contact_success') }}</div>
      </div>
    </div>
  @endif

  @yield('content')

  @include('client.partials.footer')

  @php
    $contactInfo = $siteBranding['contact'] ?? [];
    $rawPhone = !empty($contactInfo['phone']) ? $contactInfo['phone'] : '0397.999.123';
    $phoneHref = 'tel:'.preg_replace('/[^0-9+]/', '', $rawPhone);
  @endphp
  <!-- Floating Call & Back To Top -->
  <div class="fixed bottom-6 right-6 z-40 flex flex-col items-center gap-3" contenteditable="false">
    <a href="{{ $phoneHref }}" class="w-14 h-14 rounded-full bg-brand-red text-white flex items-center justify-center shadow-2xl hover:bg-brand-redDark hover:scale-110 transition duration-300 animate-bounce flex-shrink-0 aspect-square" style="width: 56px; height: 56px; border-radius: 9999px;" title="Gọi ngay cho Luật sư" aria-label="Gọi ngay cho Luật sư">
      <i class="fa-solid fa-phone text-xl"></i>
    </a>
    <button id="back-to-top" type="button" class="w-12 h-12 rounded-full bg-navy-900 border border-navy-800 text-white opacity-0 pointer-events-none hover:bg-brand-red hover:border-brand-red flex items-center justify-center shadow-lg transition duration-300 flex-shrink-0 aspect-square" style="width: 48px; height: 48px; border-radius: 9999px;" title="Về đầu trang" aria-label="Về đầu trang">
      <i class="fa-solid fa-arrow-up text-base"></i>
    </button>
  </div>

  <script src="{{ asset('assets/js/main.js') }}?v={{ @filemtime(public_path('assets/js/main.js')) ?: '20261109' }}"></script>
  @stack('scripts')
  @include('core.editor')
</body>
</html>
