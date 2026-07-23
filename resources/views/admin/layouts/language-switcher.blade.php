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

@if($locales->count() > 1)
<li class="nav-item dropdown nav-icon-hover-bg rounded-circle">
    <a class="nav-link" href="javascript:void(0)" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('admin.language') }}">
        <img src="{{ asset($flags[$currentLocale] ?? $flags['vi']) }}"
             alt="{{ $locales->firstWhere('code', $currentLocale)?->native_name ?? $currentLocale }}"
             width="20"
             height="20"
             class="rounded-circle object-fit-cover round-20">
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up">
        <div class="message-body">
            @foreach($locales as $language)
                @php($localeCode = $language->code)
                <a rel="alternate"
                   hreflang="{{ $localeCode }}"
                   href="{{ url(trim($localeCode.'/'.implode('/', $segments), '/')) }}"
                   class="d-flex align-items-center gap-2 py-3 px-4 dropdown-item {{ $currentLocale === $localeCode ? 'active' : '' }}">
                    <div class="position-relative">
                        <img src="{{ asset($language->flag_path ?: ($flags[$localeCode] ?? $flags['vi'])) }}"
                             alt="{{ $language->native_name }}"
                             width="20"
                             height="20"
                             class="rounded-circle object-fit-cover round-20">
                    </div>
                    <span class="mb-0 fs-3">{{ $language->native_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
</li>
@endif
