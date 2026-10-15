{{-- AI on a record that is not saved yet. Its AI buttons carry .js-ai-draft-first and
     data-ai-tool (the tool's class without "js-ai-"): after a confirm, the form is submitted
     through its normal save as a draft, with _ai_next / _ai_locale; the controller redirects to
     the edit page with ?ai=…&ai_locale=… (Admin\Concerns\OpensAiToolAfterCreate), where this
     script clicks that tool's button. --}}
@once
    @push('scripts')
        <script>
            (function () {
                const confirmText = @json(__('ai.draft.confirm'));

                document.addEventListener('click', function (event) {
                    const button = event.target.closest('.js-ai-draft-first');
                    const form = button ? button.closest('form') : null;
                    if (!form) return;
                    event.preventDefault();
                    // Fields the browser would refuse are shown first; nothing is added to the form.
                    if (!form.reportValidity() || !window.confirm(confirmText)) return;
                    const hidden = function (name, value) {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = name; input.value = value;
                        form.appendChild(input);
                    };
                    // Saved unpublished: the last is_active in the form is the one the server reads.
                    hidden('is_active', '0');
                    hidden('_ai_next', button.dataset.aiTool || '');
                    if (button.dataset.locale) hidden('_ai_locale', button.dataset.locale);
                    form.requestSubmit();
                });

                // After every tool has wired its button (they do so on DOMContentLoaded).
                window.addEventListener('load', function () {
                    const params = new URLSearchParams(window.location.search);
                    const tool = params.get('ai');
                    if (!tool || !/^[a-z-]+$/.test(tool)) return;
                    const locale = params.get('ai_locale');
                    params.delete('ai'); params.delete('ai_locale');
                    const query = params.toString();
                    window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);

                    const selector = '.js-ai-' + tool;
                    const button = (locale && document.querySelector(selector + '[data-locale="' + CSS.escape(locale) + '"]')) || document.querySelector(selector);
                    if (!button) return;
                    // Show the button's language tab, so the review sits next to the text it is about.
                    const pane = button.closest('.tab-pane');
                    const tab = pane && pane.id ? document.querySelector('[data-bs-target="#' + CSS.escape(pane.id) + '"]') : null;
                    if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();
                    button.click();
                });
            })();
        </script>
    @endpush
@endonce
