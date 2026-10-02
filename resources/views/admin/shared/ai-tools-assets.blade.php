@once
    @push('scripts')
        {{-- FAQ --}}
        <div class="modal fade" id="ai-faq-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="ti ti-help-circle me-1"></i>{{ __('ai.faq.title') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="text-muted small">{{ __('ai.faq.note') }}</p>
                    <div class="js-ai-loading text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.faq.loading') }}</div>
                    <div class="js-ai-error alert alert-danger d-none"></div>
                    <div class="js-ai-faq-items"></div>
                    <p class="small text-muted mb-0 js-ai-usage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary js-ai-reject d-none">{{ __('ai.meta.reject') }}</button>
                    <button type="button" class="btn btn-primary js-ai-apply d-none">{{ __('ai.faq.apply') }}</button>
                </div>
            </div></div>
        </div>

        {{-- Image alts --}}
        <div class="modal fade" id="ai-alt-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="ti ti-photo-check me-1"></i>{{ __('ai.alt.title') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="js-ai-loading text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.alt.loading') }}</div>
                    <div class="js-ai-error alert alert-danger d-none"></div>
                    <p class="small fw-semibold js-ai-alt-count d-none"></p>
                    <div class="js-ai-alt-items"></div>
                    <p class="small text-muted mb-0 js-ai-usage"></p>
                </div>
            </div></div>
        </div>

        {{-- Product description --}}
        <div class="modal fade" id="ai-product-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="ti ti-sparkles me-1"></i>{{ __('ai.product.title') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="text-muted small">{{ __('ai.product.note') }}</p>
                    <div class="js-ai-product-start">
                        <label class="form-label" for="ai-product-points">{{ __('ai.product.selling_points') }}</label>
                        <textarea class="form-control mb-1" id="ai-product-points" rows="3" maxlength="2000"></textarea>
                        <div class="form-text mb-3">{{ __('ai.product.selling_points_hint') }}</div>
                        <label class="form-label" for="ai-product-words">{{ __('ai.product.length') }}</label>
                        <input type="number" class="form-control mb-3" id="ai-product-words" min="{{ \App\Services\AiGateway\ProductDescriptionService::MIN_WORDS }}" max="{{ \App\Services\AiGateway\ProductDescriptionService::MAX_WORDS }}" step="50" value="300" style="max-width: 160px">
                        <button type="button" class="btn btn-primary js-ai-product-run"><i class="ti ti-sparkles me-1"></i>{{ __('ai.product.run') }}</button>
                    </div>
                    <div class="js-ai-loading text-center py-4 d-none"><span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.product.loading') }}</div>
                    <div class="js-ai-error alert alert-danger d-none mt-3"></div>
                    <div class="js-ai-product-result d-none">
                        <div class="alert alert-warning small js-ai-product-missing d-none"><div class="fw-semibold">{{ __('ai.product.missing') }}</div><ul class="mb-0"></ul></div>
                        {{-- HTML the server already ran through the product sanitizer. --}}
                        <div class="border rounded p-3 bg-white js-ai-product-preview" style="max-height: 420px; overflow: auto;"></div>
                        <p class="small text-muted mt-2 mb-0 js-ai-usage"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary js-ai-reject d-none">{{ __('ai.edit.reject') }}</button>
                    <button type="button" class="btn btn-primary js-ai-apply d-none">{{ __('ai.product.apply') }}</button>
                </div>
            </div></div>
        </div>

        {{-- Fix by SEO score --}}
        <div class="modal fade" id="ai-seo-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="ti ti-sparkles me-1"></i>{{ __('ai.seo.title') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="js-ai-loading text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.seo.loading') }}</div>
                    <div class="js-ai-error alert alert-danger d-none"></div>
                    <div class="js-ai-seo-result d-none">
                        <p class="fw-semibold">{{ __('ai.seo.current_score') }}: <span class="js-ai-seo-score"></span>/100</p>
                        <div class="js-ai-seo-fields"></div>
                        <p class="small text-muted js-ai-seo-nothing d-none">{{ __('ai.seo.nothing') }}</p>
                        <h6>{{ __('ai.seo.notes') }}</h6>
                        <ul class="small js-ai-seo-notes"></ul>
                        <p class="small text-muted mb-0 js-ai-usage"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary js-ai-reject d-none">{{ __('ai.meta.reject') }}</button>
                    <button type="button" class="btn btn-primary js-ai-apply d-none">{{ __('ai.seo.apply') }}</button>
                </div>
            </div></div>
        </div>

        <script>
            (function () {
                const t = {
                    failed: @json(__('ai.errors.default')),
                    credits: @json(__('ai.meta.credits', ['used' => ':used', 'limit' => ':limit'])),
                    question: @json(__('ai.faq.question')), answer: @json(__('ai.faq.answer')), remove: @json(__('ai.faq.remove')),
                    altNone: @json(__('ai.alt.none')), altCount: @json(__('ai.alt.count', ['count' => ':count'])),
                    altGenerate: @json(__('ai.alt.generate')), altApply: @json(__('ai.alt.apply')),
                    seoFields: { seo_title: @json(__('ai.seo.seo_title')), seo_description: @json(__('ai.seo.seo_description')), intro_html: @json(__('ai.seo.intro_html')) },
                    seoUse: @json(__('ai.seo.use')),
                };
                const urls = {
                    faq: @json(route('admin.ai.faq.suggest')), altMissing: @json(route('admin.ai.alt.missing')),
                    alt: @json(route('admin.ai.alt.suggest')), product: @json(route('admin.ai.product.suggest')), seo: @json(route('admin.ai.seo.suggest')),
                };
                const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
                const notify = (icon, message) => window.Swal
                    ? Swal.fire({ toast: true, position: 'top-end', timer: 3500, showConfirmButton: false, icon, title: message })
                    : window.alert(message);
                async function post(url, body) {
                    const response = await fetch(url, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                        body: JSON.stringify(body || {}),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || payload.success === false) throw new Error(payload.message || t.failed);
                    return payload;
                }
                // Keep the open form in step, so saving it later does not write the old body back.
                function syncEditor(target, html) {
                    const textarea = document.getElementById(target);
                    const editor = document.querySelector(`.catalog-quill[data-target="${target}"]`);
                    if (editor && editor.__quill) editor.__quill.root.innerHTML = html;
                    if (textarea) { textarea.value = html; textarea.dispatchEvent(new Event('input', { bubbles: true })); }
                }
                function el(tag, attrs, text) {
                    const node = document.createElement(tag);
                    Object.entries(attrs || {}).forEach(([key, value]) => key === 'class' ? node.className = value : node.setAttribute(key, value));
                    if (text !== undefined) node.textContent = text;
                    return node;
                }
                function usage(modal, data) {
                    const target = modal.querySelector('.js-ai-usage');
                    if (target && data.usage && data.usage.credits_limit) target.textContent = t.credits.replace(':used', data.usage.credits_used).replace(':limit', data.usage.credits_limit);
                }
                function setup(id) {
                    const element = document.getElementById(id);
                    const modal = bootstrap.Modal.getOrCreateInstance(element);
                    const part = (selector) => element.querySelector(selector);
                    const show = (selector, visible) => part(selector)?.classList.toggle('d-none', !visible);
                    const fail = (message) => { show('.js-ai-loading', false); part('.js-ai-error').textContent = message; show('.js-ai-error', true); };
                    return { element, modal, part, show, fail };
                }
                function applied(state, payload, ui) {
                    if (payload.data.reload) { window.location.reload(); return; }
                    if (state.button && payload.data.html !== undefined) syncEditor(state.button.dataset.target, payload.data.html);
                    ui.modal.hide();
                    notify('success', payload.message);
                }
                function wireReject(ui, state) {
                    ui.part('.js-ai-reject')?.addEventListener('click', function () {
                        if (state.data) post(state.data.reject_url, { reason: 'none_fit' }).catch(() => {});
                        ui.modal.hide();
                    });
                }
                const record = (button) => ({ entity_type: button.dataset.entityType, entity_id: Number(button.dataset.entityId), content_locale: button.dataset.locale });

                document.addEventListener('DOMContentLoaded', function () {
                    if (!window.bootstrap) return;

                    // FAQ
                    const faq = setup('ai-faq-modal');
                    const faqState = {};
                    wireReject(faq, faqState);
                    document.querySelectorAll('.js-ai-faq').forEach((button) => button.addEventListener('click', async function () {
                        Object.assign(faqState, { button, data: null });
                        faq.part('.js-ai-faq-items').replaceChildren();
                        ['.js-ai-error', '.js-ai-apply', '.js-ai-reject'].forEach((s) => faq.show(s, false));
                        faq.show('.js-ai-loading', true);
                        faq.modal.show();
                        try {
                            const payload = await post(urls.faq, record(button));
                            faqState.data = payload.data;
                            payload.data.items.forEach(function (item) {
                                const card = el('div', { class: 'border rounded p-2 mb-2 js-ai-faq-item' });
                                const q = el('input', { class: 'form-control form-control-sm mb-1 js-q', maxlength: '200', 'aria-label': t.question }); q.value = item.question;
                                const a = el('textarea', { class: 'form-control form-control-sm mb-1 js-a', rows: '2', maxlength: '600', 'aria-label': t.answer }); a.value = item.answer;
                                const remove = el('button', { type: 'button', class: 'btn btn-link btn-sm text-danger p-0' }, t.remove);
                                remove.addEventListener('click', () => card.remove());
                                card.append(q, a, remove);
                                faq.part('.js-ai-faq-items').appendChild(card);
                            });
                            usage(faq.element, payload.data);
                            faq.show('.js-ai-loading', false); faq.show('.js-ai-apply', true); faq.show('.js-ai-reject', true);
                        } catch (error) { faq.fail(error.message); }
                    }));
                    faq.part('.js-ai-apply').addEventListener('click', async function () {
                        const items = Array.from(faq.element.querySelectorAll('.js-ai-faq-item')).map((card) => ({
                            question: card.querySelector('.js-q').value.trim(), answer: card.querySelector('.js-a').value.trim(),
                        })).filter((item) => item.question && item.answer);
                        this.disabled = true;
                        try { applied(faqState, await post(faqState.data.apply_url, { items }), faq); }
                        catch (error) { notify('error', error.message); }
                        finally { this.disabled = false; }
                    });

                    // Image alts
                    const alt = setup('ai-alt-modal');
                    let altState = {};
                    document.querySelectorAll('.js-ai-alt').forEach((button) => button.addEventListener('click', async function () {
                        altState = { button };
                        alt.part('.js-ai-alt-items').replaceChildren();
                        alt.show('.js-ai-error', false); alt.show('.js-ai-alt-count', false); alt.show('.js-ai-loading', true);
                        alt.modal.show();
                        try {
                            const images = (await post(urls.altMissing, record(button))).data.images;
                            alt.show('.js-ai-loading', false);
                            alt.part('.js-ai-alt-count').textContent = images.length ? t.altCount.replace(':count', images.length) : t.altNone;
                            alt.show('.js-ai-alt-count', true);
                            images.forEach(function (src) {
                                const row = el('div', { class: 'd-flex gap-3 align-items-start border rounded p-2 mb-2' });
                                const thumb = el('img', { src, alt: '', style: 'width: 96px; height: 72px; object-fit: cover', class: 'rounded border' });
                                const body = el('div', { class: 'flex-grow-1' });
                                const input = el('input', { class: 'form-control form-control-sm mb-1', maxlength: '255' });
                                const generate = el('button', { type: 'button', class: 'btn btn-sm btn-outline-primary me-1' }, t.altGenerate);
                                const save = el('button', { type: 'button', class: 'btn btn-sm btn-primary d-none' }, t.altApply);
                                let applyUrl = null;
                                generate.addEventListener('click', async function () {
                                    generate.disabled = true;
                                    try {
                                        const payload = await post(urls.alt, { ...record(button), src });
                                        input.value = payload.data.alt; applyUrl = payload.data.apply_url;
                                        save.classList.remove('d-none'); usage(alt.element, payload.data);
                                    } catch (error) { notify('error', error.message); }
                                    finally { generate.disabled = false; }
                                });
                                save.addEventListener('click', async function () {
                                    save.disabled = true;
                                    try {
                                        const payload = await post(applyUrl, { alt: input.value.trim() });
                                        if (payload.data.reload) { window.location.reload(); return; }
                                        syncEditor(button.dataset.target, payload.data.html);
                                        row.classList.add('opacity-50'); generate.remove(); save.remove(); input.disabled = true;
                                        notify('success', payload.message);
                                    } catch (error) { save.disabled = false; notify('error', error.message); }
                                });
                                body.append(input, generate, save);
                                row.append(thumb, body);
                                alt.part('.js-ai-alt-items').appendChild(row);
                            });
                        } catch (error) { alt.fail(error.message); }
                    }));

                    // Product description
                    const product = setup('ai-product-modal');
                    const productState = {};
                    wireReject(product, productState);
                    document.querySelectorAll('.js-ai-product').forEach((button) => button.addEventListener('click', function () {
                        Object.assign(productState, { button, data: null });
                        ['.js-ai-loading', '.js-ai-error', '.js-ai-product-result', '.js-ai-apply', '.js-ai-reject'].forEach((s) => product.show(s, false));
                        product.show('.js-ai-product-start', true);
                        product.modal.show();
                    }));
                    product.part('.js-ai-product-run').addEventListener('click', async function () {
                        product.show('.js-ai-product-start', false); product.show('.js-ai-error', false); product.show('.js-ai-loading', true);
                        try {
                            const payload = await post(urls.product, {
                                entity_id: Number(productState.button.dataset.entityId), content_locale: productState.button.dataset.locale,
                                selling_points: document.getElementById('ai-product-points').value, target_words: Number(document.getElementById('ai-product-words').value),
                            });
                            productState.data = payload.data;
                            product.part('.js-ai-product-preview').innerHTML = payload.data.html;
                            const list = product.part('.js-ai-product-missing ul'); list.replaceChildren();
                            (payload.data.missing_info || []).forEach((item) => list.appendChild(el('li', {}, item)));
                            product.show('.js-ai-product-missing', (payload.data.missing_info || []).length > 0);
                            usage(product.element, payload.data);
                            product.show('.js-ai-loading', false); product.show('.js-ai-product-result', true);
                            product.show('.js-ai-apply', true); product.show('.js-ai-reject', true);
                        } catch (error) { product.fail(error.message); product.show('.js-ai-product-start', true); }
                    });
                    product.part('.js-ai-apply').addEventListener('click', async function () {
                        this.disabled = true;
                        try { applied(productState, await post(productState.data.apply_url), product); }
                        catch (error) { notify('error', error.message); }
                        finally { this.disabled = false; }
                    });

                    // Fix by SEO score
                    const seo = setup('ai-seo-modal');
                    const seoState = {};
                    wireReject(seo, seoState);
                    document.querySelectorAll('.js-ai-seo-fix').forEach((button) => button.addEventListener('click', async function () {
                        Object.assign(seoState, { button: null, data: null });
                        seo.part('.js-ai-seo-fields').replaceChildren(); seo.part('.js-ai-seo-notes').replaceChildren();
                        ['.js-ai-error', '.js-ai-seo-result', '.js-ai-apply', '.js-ai-reject'].forEach((s) => seo.show(s, false));
                        seo.show('.js-ai-loading', true);
                        seo.modal.show();
                        try {
                            const payload = await post(urls.seo, { entity_id: Number(button.dataset.entityId) });
                            seoState.data = payload.data;
                            seo.part('.js-ai-seo-score').textContent = payload.data.score;
                            let offered = 0;
                            ['seo_title', 'seo_description', 'intro_html'].forEach(function (field) {
                                const value = payload.data.proposal[field] || '';
                                if (!value) return;
                                offered++;
                                const box = el('div', { class: 'border rounded p-2 mb-2' });
                                const id = 'ai-seo-use-' + field;
                                const check = el('input', { type: 'checkbox', class: 'form-check-input me-1 js-use', id, 'data-field': field }); check.checked = true;
                                const label = el('label', { class: 'form-check-label fw-semibold', for: id }, t.seoFields[field] + ' — ' + t.seoUse);
                                box.append(check, label);
                                if (field === 'intro_html') {
                                    // Server-sanitized HTML, shown as the reader will see it.
                                    const preview = el('div', { class: 'small border-top mt-2 pt-2 js-value', 'data-html': '1' });
                                    preview.innerHTML = value; preview.dataset.value = value;
                                    box.appendChild(preview);
                                } else {
                                    const input = el(field === 'seo_title' ? 'input' : 'textarea', { class: 'form-control form-control-sm mt-2 js-value', maxlength: field === 'seo_title' ? '255' : '500' });
                                    input.value = value;
                                    const old = el('div', { class: 'small text-muted mt-1' }, (payload.data.current[field] || '—'));
                                    box.append(input, old);
                                }
                                seo.part('.js-ai-seo-fields').appendChild(box);
                            });
                            (payload.data.proposal.notes || []).forEach((note) => seo.part('.js-ai-seo-notes').appendChild(el('li', {}, note.note)));
                            seo.show('.js-ai-seo-nothing', offered === 0);
                            usage(seo.element, payload.data);
                            seo.show('.js-ai-loading', false); seo.show('.js-ai-seo-result', true);
                            seo.show('.js-ai-apply', offered > 0); seo.show('.js-ai-reject', true);
                        } catch (error) { seo.fail(error.message); }
                    }));
                    seo.part('.js-ai-apply').addEventListener('click', async function () {
                        const values = {};
                        seo.element.querySelectorAll('.js-use:checked').forEach(function (check) {
                            const value = check.closest('.border').querySelector('.js-value');
                            values[check.dataset.field] = value.dataset.html ? value.dataset.value : value.value;
                        });
                        this.disabled = true;
                        try {
                            const payload = await post(seoState.data.apply_url, values);
                            notify('success', payload.message);
                            window.location.reload();
                        } catch (error) { notify('error', error.message); this.disabled = false; }
                    });
                });
            })();
        </script>
    @endpush
@endonce
