{{-- Language tabs of a [data-site-locale-scope], styled like the tabs of every content form: show the
     fields of one content language at a time (data-site-locale). Fields every language shares (phone,
     email, map) carry no attribute. A non-default tab offers the shared translate button
     (admin.shared.translation-assets), limited to its own block by data-i18n-scope.
     Stored through App\Support\LocalizedSiteSettings. Hidden when only one language is active. --}}
@if($siteOtherLanguages->isNotEmpty())
    <ul class="nav nav-tabs mb-3" role="tablist" aria-label="{{ __('admin.settings.localized.switch_label') }}" data-site-locale-switch>
        @foreach($siteLanguages as $language)
            <li class="nav-item" role="presentation">
                <button type="button" role="tab" @class(['nav-link d-flex align-items-center gap-2', 'active' => $language->code === $siteDefaultLocale]) data-site-locale-target="{{ $language->code }}" aria-selected="{{ $language->code === $siteDefaultLocale ? 'true' : 'false' }}">
                    <span><i class="ti ti-language fs-4"></i></span>
                    <span>{{ $language->native_name }}</span>
                </button>
            </li>
        @endforeach
    </ul>
    @foreach($siteOtherLanguages as $language)
        <div class="text-end mb-3 d-none" data-site-locale="{{ $language->code }}">
            <button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $siteDefaultLocale }}" data-target-locale="{{ $language->code }}">
                <i class="ti ti-language me-1"></i>{{ __('admin.translation.translate_button', ['from' => strtoupper($siteDefaultLocale), 'to' => strtoupper($language->code)]) }}
            </button>
        </div>
    @endforeach

    @once
        @include('admin.shared.translation-assets')
        @push('scripts')
            <script>
                window.AdminSiteLocale = {
                    apply(scope) {
                        if (!scope) return;
                        const active = scope.dataset.siteLocaleActive || @json($siteDefaultLocale);
                        scope.querySelectorAll('[data-site-locale]').forEach((field) => {
                            field.classList.toggle('d-none', field.dataset.siteLocale !== active);
                        });
                        scope.querySelectorAll('[data-site-locale-target]').forEach((button) => {
                            const on = button.dataset.siteLocaleTarget === active;
                            button.classList.toggle('active', on);
                            button.setAttribute('aria-selected', on ? 'true' : 'false');
                        });
                    },
                };

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-site-locale-target]');
                    const scope = button?.closest('[data-site-locale-scope]');
                    if (!scope) return;
                    scope.dataset.siteLocaleActive = button.dataset.siteLocaleTarget;
                    window.AdminSiteLocale.apply(scope);
                });
            </script>
        @endpush
    @endonce
@endif
