@php
    $currentLocale = app()->getLocale();
    $locales = config('laravellocalization.supportedLocales', []);
    $segments = request()->segments();
    $flags = [
        'vi' => 'admin-assets/images/flag/Flag_of_Vietnam.svg.png',
        'en' => 'admin-assets/images/flag/icon-flag-en.svg',
    ];

    if ($segments && array_key_exists($segments[0], $locales)) {
        array_shift($segments);
    }
@endphp

<li class="nav-item dropdown nav-icon-hover-bg rounded-circle">
    <a class="nav-link" href="javascript:void(0)" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('admin.language') }}">
        <img src="{{ asset($flags[$currentLocale] ?? $flags['vi']) }}"
             alt="{{ $locales[$currentLocale]['native'] ?? $currentLocale }}"
             width="20"
             height="20"
             class="rounded-circle object-fit-cover round-20">
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up">
        <div class="message-body">
            @foreach($locales as $localeCode => $properties)
                <a rel="alternate"
                   hreflang="{{ $localeCode }}"
                   href="{{ url(trim($localeCode.'/'.implode('/', $segments), '/')) }}"
                   class="d-flex align-items-center gap-2 py-3 px-4 dropdown-item {{ $currentLocale === $localeCode ? 'active' : '' }}">
                    <div class="position-relative">
                        <img src="{{ asset($flags[$localeCode] ?? $flags['vi']) }}"
                             alt="{{ $properties['native'] }}"
                             width="20"
                             height="20"
                             class="rounded-circle object-fit-cover round-20">
                    </div>
                    <span class="mb-0 fs-3">{{ $properties['native'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</li>
