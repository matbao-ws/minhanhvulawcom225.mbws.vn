@php
    $currentLocale = app()->getLocale();
    $locales = app(\App\Services\LanguageRegistry::class)->admin();
    $segments = request()->segments();
    $flags = [
        'vi' => 'admin-assets/images/flag/Flag_of_Vietnam.svg.png',
        'en' => 'admin-assets/images/flag/icon-flag-en.svg',
    ];

    if ($segments && $locales->contains('code', $segments[0])) {
        array_shift($segments);
    }
@endphp

@if ($locales->count() > 1)
    <div class="auth-language-switcher dropup">
        <button type="button" class="btn btn-light rounded-circle shadow" data-bs-toggle="dropdown"
            aria-expanded="false" aria-label="{{ __('admin.language') }}" title="{{ __('admin.language') }}">
            <img src="{{ asset($flags[$currentLocale] ?? $flags['vi']) }}"
                alt="{{ $locales->firstWhere('code', $currentLocale)?->native_name ?? $currentLocale }}"
                width="24" height="24" class="rounded-circle object-fit-cover">
        </button>

        <div class="dropdown-menu dropdown-menu-end shadow border-0 mb-2">
            @foreach ($locales as $language)
                @php($localeCode = $language->code)
                <a rel="alternate" hreflang="{{ $localeCode }}"
                    href="{{ url(trim($localeCode.'/'.implode('/', $segments), '/')) }}"
                    class="dropdown-item d-flex align-items-center gap-2 {{ $currentLocale === $localeCode ? 'active' : '' }}">
                    <img src="{{ asset($language->flag_path ?: ($flags[$localeCode] ?? $flags['vi'])) }}"
                        alt="{{ $language->native_name }}" width="20" height="20"
                        class="rounded-circle object-fit-cover">
                    <span>{{ $language->native_name }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .auth-language-switcher {
            position: fixed;
            right: 1.5rem;
            bottom: 1.5rem;
            z-index: 1050;
        }

        .auth-language-switcher > .btn {
            width: 48px;
            height: 48px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
@endif
