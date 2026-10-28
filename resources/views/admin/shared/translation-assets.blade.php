@once
    @push('styles')
        <style>
            .content-language-tabs .nav-link { font-weight: 600; }
            .content-language-tabs .language-required { font-size: .72rem; }
            @cannot('translations.use')
                .js-translate-locale { display: none !important; }
            @endcannot
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.js-translate-locale').forEach(function (button) {
                    button.addEventListener('click', async function () {
                        const sourceLocale = button.dataset.sourceLocale;
                        const targetLocale = button.dataset.targetLocale;
                        const fields = {};
                        const formats = {};
                        // A page with several translatable blocks (Settings) limits each button to its own block.
                        const root = button.closest('[data-i18n-scope]') || document;

                        root.querySelectorAll(`[data-i18n-locale="${sourceLocale}"][data-i18n-field]`).forEach(function (element) {
                            const editor = window.AdminRichEditor?.forTarget(element.id);
                            if (editor) element.value = editor.getHTML();
                            fields[element.dataset.i18nField] = element.value || '';
                            formats[element.dataset.i18nField] = element.dataset.translationFormat || 'text';
                        });

                        const targets = Array.from(root.querySelectorAll(`[data-i18n-locale="${targetLocale}"][data-i18n-field]`));
                        if (targets.some((element) => (element.value || '').trim() !== '') && !window.confirm(@json(__('admin.translation.confirm_overwrite')))) return;

                        const original = button.innerHTML;
                        button.disabled = true;
                        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + @json(__('admin.translation.translating'));

                        try {
                            const response = await fetch(@json(route('admin.translations.preview')), {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                },
                                body: JSON.stringify({ source_locale: sourceLocale, target_locale: targetLocale, fields, formats }),
                            });
                            const payload = await response.json();
                            if (!response.ok) throw new Error(payload.message || @json(__('admin.translation.failed')));

                            targets.forEach(function (element) {
                                const value = payload.data?.fields?.[element.dataset.i18nField];
                                if (typeof value !== 'string') return;
                                element.value = value;
                                // A policy page body stays disabled until edited (data-sync="edited"); a translation is an edit.
                                element.disabled = false;
                                window.AdminRichEditor?.forTarget(element.id)?.setHTML(value);
                                element.dispatchEvent(new Event('input', { bubbles: true }));
                            });
                        } catch (error) {
                            window.alert(error.message || @json(__('admin.translation.failed')));
                        } finally {
                            button.disabled = false;
                            button.innerHTML = original;
                        }
                    });
                });
            });
        </script>
    @endpush
@endonce
