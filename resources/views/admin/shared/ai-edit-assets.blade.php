@once
    @push('scripts')
        <div class="modal fade" id="ai-edit-modal" tabindex="-1" aria-labelledby="ai-edit-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ai-edit-modal-title"><i class="ti ti-wand me-1"></i>{{ __('ai.edit.title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">{{ __('ai.meta.review_note') }}</p>
                        <div class="js-ai-edit-start">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ai-edit-level" id="ai-edit-light" value="light" checked>
                                <label class="form-check-label" for="ai-edit-light">{{ __('ai.edit.light') }}</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="ai-edit-level" id="ai-edit-medium" value="medium">
                                <label class="form-check-label" for="ai-edit-medium">{{ __('ai.edit.medium') }}</label>
                            </div>
                            <button type="button" class="btn btn-primary js-ai-edit-run"><i class="ti ti-wand me-1"></i>{{ __('ai.edit.button') }}</button>
                        </div>
                        <div class="js-ai-edit-loading text-center py-4 d-none">
                            <span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.edit.loading') }}
                        </div>
                        <div class="js-ai-edit-error alert alert-danger d-none mt-3" role="alert"></div>
                        <div class="js-ai-edit-result d-none">
                            <h6 class="mt-1">{{ __('ai.edit.changes') }}</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>{{ __('ai.edit.before') }}</th><th>{{ __('ai.edit.after') }}</th><th></th></tr></thead>
                                    <tbody class="js-ai-edit-changes"></tbody>
                                </table>
                            </div>
                            <p class="text-muted small js-ai-edit-none d-none">{{ __('ai.edit.no_changes') }}</p>
                            <h6>{{ __('ai.edit.preview') }}</h6>
                            {{-- Filled with HTML the server already ran through the record's sanitizer. --}}
                            <div class="border rounded p-3 bg-white js-ai-edit-preview" style="max-height: 360px; overflow: auto;"></div>
                            <p class="small text-muted mt-2 mb-0 js-ai-edit-usage"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary js-ai-edit-reject d-none">{{ __('ai.edit.reject') }}</button>
                        <button type="button" class="btn btn-primary js-ai-edit-apply d-none">{{ __('ai.edit.apply') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const text = {
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
                    if (!response.ok || payload.success === false) throw new Error(payload.message || text.failed);
                    return payload;
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const modalElement = document.getElementById('ai-edit-modal');
                    if (!modalElement || !window.bootstrap) return;

                    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                    const part = (selector) => modalElement.querySelector(selector);
                    const show = (selector, visible) => part(selector).classList.toggle('d-none', !visible);
                    let state = null;

                    function reset() {
                        show('.js-ai-edit-start', true);
                        show('.js-ai-edit-loading', false);
                        show('.js-ai-edit-error', false);
                        show('.js-ai-edit-result', false);
                        show('.js-ai-edit-apply', false);
                        show('.js-ai-edit-reject', false);
                        part('.js-ai-edit-changes').replaceChildren();
                        part('.js-ai-edit-preview').replaceChildren();
                    }

                    function render(data) {
                        const rows = part('.js-ai-edit-changes');
                        data.changes.forEach(function (change) {
                            const row = document.createElement('tr');
                            [['text-danger', change.before], ['text-success', change.after], ['text-muted small', change.reason]].forEach(function ([cls, value]) {
                                const cell = document.createElement('td');
                                cell.className = cls;
                                cell.textContent = value || '';
                                row.appendChild(cell);
                            });
                            rows.appendChild(row);
                        });
                        show('.js-ai-edit-none', data.changes.length === 0);
                        part('.js-ai-edit-preview').innerHTML = data.html;
                        if (data.usage && data.usage.credits_limit) {
                            part('.js-ai-edit-usage').textContent = text.credits
                                .replace(':used', data.usage.credits_used).replace(':limit', data.usage.credits_limit);
                        }
                        show('.js-ai-edit-loading', false);
                        show('.js-ai-edit-result', true);
                        show('.js-ai-edit-apply', data.changes.length > 0);
                        show('.js-ai-edit-reject', true);
                    }

                    part('.js-ai-edit-run').addEventListener('click', async function () {
                        const level = modalElement.querySelector('input[name="ai-edit-level"]:checked')?.value || 'light';
                        show('.js-ai-edit-start', false);
                        show('.js-ai-edit-error', false);
                        show('.js-ai-edit-loading', true);
                        try {
                            const payload = await post(@json(route('admin.ai.content.suggest')), {
                                entity_type: state.button.dataset.entityType,
                                entity_id: Number(state.button.dataset.entityId),
                                content_locale: state.button.dataset.locale,
                                level,
                            });
                            state.data = payload.data;
                            render(payload.data);
                        } catch (error) {
                            show('.js-ai-edit-loading', false);
                            show('.js-ai-edit-start', true);
                            part('.js-ai-edit-error').textContent = error.message;
                            show('.js-ai-edit-error', true);
                        }
                    });

                    part('.js-ai-edit-apply').addEventListener('click', async function () {
                        const button = this;
                        button.disabled = true;
                        try {
                            const payload = await post(state.data.apply_url);
                            if (payload.data.reload) {
                                // Policy pages keep their stored HTML out of the admin editor; show the saved result instead.
                                window.location.reload();
                                return;
                            }
                            // Keep the open form in step, so saving it later does not write the old text back.
                            const textarea = document.getElementById(state.button.dataset.target);
                            window.AdminRichEditor?.forTarget(state.button.dataset.target)?.setHTML(payload.data.html);
                            if (textarea) {
                                textarea.value = payload.data.html;
                                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                            modal.hide();
                            notify('success', payload.message);
                        } catch (error) {
                            notify('error', error.message);
                        } finally {
                            button.disabled = false;
                        }
                    });

                    part('.js-ai-edit-reject').addEventListener('click', function () {
                        if (state && state.data) post(state.data.reject_url, { reason: 'kept_original' }).catch(() => {});
                        modal.hide();
                    });

                    // A suggestion asked for earlier and never reviewed (admin.shared.ai-pending).
                    (window.AdminAiResume = window.AdminAiResume || {})['content.edit'] = function (button, data) {
                        state = { button, data };
                        reset();
                        show('.js-ai-edit-start', false);
                        render(data);
                        modal.show();
                    };

                    document.querySelectorAll('.js-ai-edit').forEach(function (button) {
                        button.addEventListener('click', function () {
                            state = { button, data: null };
                            reset();
                            modal.show();
                        });
                    });
                });
            })();
        </script>
    @endpush
@endonce
