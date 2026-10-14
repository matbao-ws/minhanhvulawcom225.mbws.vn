@once
    @push('scripts')
        <div class="modal fade" id="ai-meta-modal" tabindex="-1" aria-labelledby="ai-meta-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ai-meta-modal-title"><i class="ti ti-sparkles me-1"></i>{{ __('ai.meta.title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">{{ __('ai.meta.review_note') }}</p>
                        <div class="js-ai-meta-loading text-center py-4">
                            <span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.meta.loading') }}
                        </div>
                        <div class="js-ai-meta-error alert alert-danger d-none" role="alert"></div>
                        <div class="js-ai-meta-body d-none">
                            <div class="border rounded p-3 mb-3 bg-light-subtle">
                                <div class="fw-semibold mb-1">{{ __('ai.meta.current') }}</div>
                                <div class="small"><span class="text-muted">{{ __('ai.meta.meta_title') }}:</span> <span class="js-ai-current-title"></span></div>
                                <div class="small"><span class="text-muted">{{ __('ai.meta.meta_description') }}:</span> <span class="js-ai-current-description"></span></div>
                            </div>
                            {{-- The focus keyword the AI read from the page: one click puts it in the SEO box. --}}
                            <div class="border rounded p-3 mb-3 bg-light-subtle text-dark d-flex flex-wrap align-items-center gap-2 d-none js-ai-meta-keyword">
                                <span><i class="ti ti-target-arrow me-1" aria-hidden="true"></i>{{ __('ai.meta.keyword_suggested') }}: <strong class="js-ai-meta-keyword-value"></strong></span>
                                <button type="button" class="btn btn-sm btn-primary ms-auto js-ai-meta-keyword-use">{{ __('ai.meta.keyword_use') }}</button>
                            </div>
                            <div class="js-ai-meta-options"></div>
                            <p class="small text-muted mb-0 js-ai-meta-usage"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary js-ai-meta-reject d-none">{{ __('ai.meta.reject') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <template id="ai-meta-option-template">
            <div class="card border mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold js-option-label"></span>
                        <span class="badge bg-light text-dark js-option-angle"></span>
                    </div>
                    <label class="form-label small mb-1">{{ __('ai.meta.meta_title') }} <span class="text-muted js-title-count"></span></label>
                    <input type="text" class="form-control mb-2 js-option-title" maxlength="{{ \App\Services\AiGateway\MetaSuggestionService::TITLE_MAX }}">
                    <label class="form-label small mb-1">{{ __('ai.meta.meta_description') }} <span class="text-muted js-description-count"></span></label>
                    <textarea class="form-control mb-2 js-option-description" rows="2" maxlength="{{ \App\Services\AiGateway\MetaSuggestionService::DESCRIPTION_MAX }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm js-option-apply">{{ __('ai.meta.apply') }}</button>
                </div>
            </div>
        </template>

        <script>
            (function () {
                const text = {
                    keywordAdded: @json(__('ai.meta.keyword_added')),
                    empty: @json(__('ai.meta.empty')),
                    option: @json(__('ai.meta.option', ['number' => '#'])),
                    credits: @json(__('ai.meta.credits', ['used' => ':used', 'limit' => ':limit'])),
                    failed: @json(__('ai.errors.default')),
                };
                const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
                const notify = (icon, message) => window.Swal
                    ? Swal.fire({ toast: true, position: 'top-end', timer: 3500, showConfirmButton: false, icon, title: message })
                    : window.alert(message);

                async function post(url, body) {
                    const response = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                        body: JSON.stringify(body || {}),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || payload.success === false) {
                        throw new Error(payload.message || text.failed);
                    }
                    return payload;
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const modalElement = document.getElementById('ai-meta-modal');
                    if (!modalElement || !window.bootstrap) return;

                    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                    const part = (selector) => modalElement.querySelector(selector);
                    let state = null;

                    const counter = (input, target, limit) => {
                        const update = () => { target.textContent = '(' + input.value.length + '/' + limit + ')'; };
                        input.addEventListener('input', update);
                        update();
                    };

                    function reset() {
                        part('.js-ai-meta-loading').classList.remove('d-none');
                        part('.js-ai-meta-error').classList.add('d-none');
                        part('.js-ai-meta-body').classList.add('d-none');
                        part('.js-ai-meta-reject').classList.add('d-none');
                        part('.js-ai-meta-options').replaceChildren();
                    }

                    function fail(message) {
                        part('.js-ai-meta-loading').classList.add('d-none');
                        part('.js-ai-meta-error').textContent = message;
                        part('.js-ai-meta-error').classList.remove('d-none');
                    }

                    function render(data) {
                        part('.js-ai-current-title').textContent = data.current.title || text.empty;
                        part('.js-ai-current-description').textContent = data.current.description || text.empty;

                        // Offered when it is not already the box's focus keyword (only boxes with keywords).
                        const keywordBox = part('.js-ai-meta-keyword');
                        const keywordsInput = document.querySelector('[data-seo-keys]');
                        const currentKeyword = keywordsInput ? (keywordsInput.value.split(',')[0] || '').trim().toLowerCase() : null;
                        const suggestedKeyword = (data.focus_keyword || '').trim();
                        keywordBox.classList.toggle('d-none', !keywordsInput || !suggestedKeyword || suggestedKeyword.toLowerCase() === currentKeyword);
                        part('.js-ai-meta-keyword-value').textContent = suggestedKeyword;
                        part('.js-ai-meta-keyword-use').disabled = false;
                        part('.js-ai-meta-keyword-use').onclick = function () {
                            document.dispatchEvent(new CustomEvent('seo-box:use-keyword', { detail: { keyword: suggestedKeyword } }));
                            this.disabled = true;
                            notify('success', text.keywordAdded);
                        };

                        const template = document.getElementById('ai-meta-option-template');
                        data.options.forEach(function (option, index) {
                            const card = template.content.firstElementChild.cloneNode(true);
                            const title = card.querySelector('.js-option-title');
                            const description = card.querySelector('.js-option-description');
                            card.querySelector('.js-option-label').textContent = text.option.replace('#', index + 1);
                            card.querySelector('.js-option-angle').textContent = option.angle || '';
                            title.value = option.title;
                            description.value = option.description;
                            counter(title, card.querySelector('.js-title-count'), 60);
                            counter(description, card.querySelector('.js-description-count'), 160);
                            card.querySelector('.js-option-apply').addEventListener('click', () => apply(index, title.value, description.value, card));
                            part('.js-ai-meta-options').appendChild(card);
                        });

                        if (data.usage && data.usage.credits_limit) {
                            part('.js-ai-meta-usage').textContent = text.credits
                                .replace(':used', data.usage.credits_used).replace(':limit', data.usage.credits_limit);
                        }

                        part('.js-ai-meta-loading').classList.add('d-none');
                        part('.js-ai-meta-body').classList.remove('d-none');
                        part('.js-ai-meta-reject').classList.remove('d-none');
                    }

                    async function apply(index, title, description, card) {
                        card.querySelectorAll('button, input, textarea').forEach((element) => { element.disabled = true; });
                        try {
                            const payload = await post(state.data.apply_url, { option: index, title, description });
                            // Keep the open form in step, so saving it later does not write the old values back.
                            const titleInput = document.querySelector(`[name="${state.button.dataset.titleField}"]`);
                            const descriptionInput = document.querySelector(`[name="${state.button.dataset.descriptionField}"]`);
                            [[titleInput, payload.data.title], [descriptionInput, payload.data.description]].forEach(function ([input, value]) {
                                if (!input) return;
                                input.value = value;
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                            });
                            state.done = true;
                            modal.hide();
                            notify('success', payload.message);
                        } catch (error) {
                            card.querySelectorAll('button, input, textarea').forEach((element) => { element.disabled = false; });
                            notify('error', error.message);
                        }
                    }

                    part('.js-ai-meta-reject').addEventListener('click', async function () {
                        if (state && state.data) {
                            state.done = true;
                            post(state.data.reject_url, { reason: 'none_fit' }).catch(() => {});
                        }
                        modal.hide();
                    });

                    // A suggestion asked for earlier and never reviewed (admin.shared.ai-pending).
                    (window.AdminAiResume = window.AdminAiResume || {})['meta.suggest'] = function (button, data) {
                        state = { button, data, done: false };
                        reset();
                        render(data);
                        modal.show();
                    };

                    document.querySelectorAll('.js-ai-meta-suggest').forEach(function (button) {
                        button.addEventListener('click', async function () {
                            state = { button, data: null, done: false };
                            reset();
                            modal.show();
                            button.disabled = true;
                            try {
                                const payload = await post(@json(route('admin.ai.meta.suggest')), {
                                    entity_type: button.dataset.entityType,
                                    entity_id: Number(button.dataset.entityId),
                                    content_locale: button.dataset.locale,
                                });
                                state.data = payload.data;
                                render(payload.data);
                            } catch (error) {
                                fail(error.message);
                            } finally {
                                button.disabled = false;
                            }
                        });
                    });
                });
            })();
        </script>
    @endpush
@endonce
