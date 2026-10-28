/*
 * "This week's SEO work" (admin/ai/seo-tasks): mark done / skip, AI advice, and the AI fix dialog.
 *
 * The fix runs the task's steps one at a time (each is one request, so the owner sees progress and
 * no request waits on several model calls), shows each proposal for review and edit, then posts only
 * what the owner kept. Everything the model wrote is set with textContent or value, never as HTML.
 */
(function () {
    'use strict';

    const configElement = document.getElementById('seoTasksConfig');
    if (!configElement) return;

    const config = JSON.parse(configElement.textContent);
    const T = config.text;
    const F = T.fix;
    let uid = 0;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function icon(name) {
        const node = el('i', 'ti ' + name);
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function fmt(template, values) {
        return String(template).replace(/:(\w+)/g, (match, key) => (key in values ? values[key] : match));
    }

    function firstError(json) {
        const errors = json && json.errors ? Object.values(json.errors) : [];
        return errors.length && errors[0].length ? errors[0][0] : '';
    }

    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf},
            body: JSON.stringify(body || {}),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || !json.success) {
            const error = new Error(json.message || firstError(json) || T.failed);
            error.data = json;
            throw error;
        }
        return json;
    }

    if (window.bootstrap) {
        document.querySelectorAll('.seo-work [data-bs-toggle="tooltip"]').forEach((node) => new window.bootstrap.Tooltip(node));
    }

    // Tabs: the one in the address bar opens, so a reload after a fix lands where it was.
    const remembered = document.querySelector('.seo-tabs [data-bs-target="' + window.location.hash.replace(/[^#\w-]/g, '') + '"]');
    if (remembered && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(remembered).show();
    document.querySelectorAll('.seo-tabs [data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', () => history.replaceState(null, '', tab.dataset.bsTarget));
    });

    // "View details" / the banner: open the to-do tab and bring the list into view.
    document.querySelectorAll('[data-seo-goto-todo]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const tab = document.getElementById('seo-tab-todo');
            if (tab && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(tab).show();
            (document.querySelector('.seo-tabs') || tab)?.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
    });

    // ---- Filter chips: show one group of recommendations.
    const filters = document.querySelectorAll('[data-seo-filter]');
    const noMatch = document.querySelector('[data-seo-no-match]');
    filters.forEach((chip) => {
        chip.addEventListener('click', () => {
            const group = chip.dataset.seoFilter;
            filters.forEach((other) => {
                other.classList.toggle('is-active', other === chip);
                other.setAttribute('aria-pressed', other === chip ? 'true' : 'false');
            });
            let shown = 0;
            document.querySelectorAll('.seo-recs [data-seo-task]').forEach((row) => {
                const visible = group === 'all' || row.dataset.category === group;
                row.classList.toggle('d-none', !visible);
                if (visible) shown++;
            });
            if (noMatch) noMatch.classList.toggle('d-none', shown > 0);
        });
    });

    // "Why?" on the row answers inside the row's details: open them first.
    document.querySelectorAll('[data-seo-advice-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const details = document.querySelector(button.dataset.seoAdviceOpen);
            if (details && window.bootstrap) window.bootstrap.Collapse.getOrCreateInstance(details, {toggle: false}).show();
        });
    });

    // ---- Done (fixed by hand) / skip: the card stays, dimmed, with an undo until the page is reloaded.
    document.querySelectorAll('[data-seo-mark]').forEach((button) => {
        button.addEventListener('click', async () => {
            const card = button.closest('[data-seo-task]');
            const group = button.closest('[data-seo-mark-group]');
            const buttons = group.querySelectorAll('button');
            const enable = (on) => buttons.forEach((item) => { item.disabled = !on; });
            enable(false);
            try {
                const json = await post(button.dataset.url, {status: button.dataset.seoMark});
                card.classList.add('is-marked');
                const note = el('span', 'small seo-muted ms-auto', json.message + ' ');
                const undo = el('button', 'btn btn-link btn-sm p-0 align-baseline', T.undo);
                undo.type = 'button';
                undo.addEventListener('click', async () => {
                    undo.disabled = true;
                    try {
                        await post(button.dataset.url, {status: 'open'});
                        card.classList.remove('is-marked');
                        note.remove();
                        group.classList.remove('d-none');
                        enable(true);
                    } catch (error) {
                        undo.disabled = false;
                        window.alert(error.message);
                    }
                });
                note.appendChild(undo);
                group.classList.add('d-none');
                group.after(note);
            } catch (error) {
                enable(true);
                window.alert(error.message);
            }
        });
    });

    // ---- "Why?": the AI's reading of the task, as plain text.
    // One answer per task: asked once, then every "Why?" of the row just brings it into view.
    document.querySelectorAll('[data-seo-advice]').forEach((button) => {
        const box = button.closest('[data-seo-task]').querySelector('[data-seo-advice-result]');
        button.addEventListener('click', async () => {
            if (box.dataset.state === 'ready' || box.dataset.state === 'loading') {
                box.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                return;
            }
            box.dataset.state = 'loading';
            box.classList.remove('d-none', 'is-error');
            const loading = el('div', 'seo-advice__loading');
            loading.append(icon('ti-loader-2'), el('span', null, T.adviceLoading));
            box.replaceChildren(loading);
            box.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            try {
                const json = await post(button.dataset.seoAdvice);
                const head = el('div', 'seo-advice__head');
                head.append(icon('ti-sparkles'), el('span', null, T.adviceTitle));
                const list = el('ol', 'seo-advice__steps');
                json.data.advice.steps.forEach((step) => list.appendChild(el('li', null, step)));
                box.replaceChildren(head, el('p', 'seo-advice__summary', json.data.advice.summary), list, el('p', 'seo-advice__note', T.adviceNote));
                box.dataset.state = 'ready';
            } catch (error) {
                box.classList.add('is-error');
                box.replaceChildren(el('span', null, error.message));
                box.dataset.state = '';
            }
        });
    });

    // ---- The AI fix dialog.
    const modalElement = document.getElementById('seoFixModal');
    if (!modalElement || !window.bootstrap) return;

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    const parts = {
        page: modalElement.querySelector('[data-seo-fix-page]'),
        progress: modalElement.querySelector('[data-seo-fix-progress]'),
        hint: modalElement.querySelector('[data-seo-fix-hint]'),
        blocks: modalElement.querySelector('[data-seo-fix-blocks]'),
        done: modalElement.querySelector('[data-seo-fix-done]'),
        actions: modalElement.querySelector('[data-seo-fix-actions]'),
        apply: modalElement.querySelector('[data-seo-fix-apply]'),
    };
    const blockTitles = {meta: F.meta_title, section: F.section_title, faq: F.faq_title, alt: F.alt_title};
    let session = null;

    modalElement.addEventListener('hidden.bs.modal', () => {
        if (!session) return;
        session.cancelled = true;
        // The card is now done: reload so it moves to "Waiting for results".
        if (session.applied) window.location.reload();
        session = null;
    });

    document.querySelectorAll('[data-seo-fix]').forEach((button) => {
        button.addEventListener('click', () => open(button.closest('[data-seo-task]')));
    });

    parts.apply.addEventListener('click', applyChanges);

    async function open(card) {
        const task = JSON.parse(card.dataset.fix);
        session = {task, blocks: [], applied: false, cancelled: false};
        const current = session;

        parts.page.textContent = task.title;
        parts.progress.textContent = '';
        parts.progress.classList.remove('d-none');
        parts.blocks.textContent = '';
        parts.blocks.classList.remove('d-none');
        parts.hint.classList.add('d-none');
        parts.done.classList.add('d-none');
        parts.done.textContent = '';
        parts.actions.classList.remove('d-none');
        updateApply();
        modal.show();

        const rows = {};
        task.steps.forEach((step) => {
            const row = el('li', 'seo-muted');
            row.append(icon('ti-circle-dashed'), el('span', null, F.running[step]));
            parts.progress.appendChild(row);
            rows[step] = row;
        });

        for (const step of task.steps) {
            if (current.cancelled) return;
            const row = rows[step];
            row.className = 'is-running';
            row.replaceChildren(icon('ti-loader-2'), el('span', null, F.running[step]));
            try {
                const json = await post(task.step_url.replace('__STEP__', step));
                if (current.cancelled) return;
                const block = renderers[step](json.data, task);
                current.blocks.push(block);
                parts.blocks.appendChild(block.node);
                row.className = 'is-done';
                row.replaceChildren(icon('ti-circle-check'), el('span', null, blockTitles[step] + ' · ' + F.ready));
            } catch (error) {
                if (current.cancelled) return;
                row.className = 'is-failed';
                row.replaceChildren(icon('ti-alert-triangle'), el('span', null, blockTitles[step] + ' · ' + fmt(F.step_failed, {message: error.message})));
            }
            updateApply();
        }
        parts.hint.classList.toggle('d-none', current.blocks.length === 0);
    }

    function updateApply() {
        const count = session ? session.blocks.filter((block) => block.collect() !== null).length : 0;
        parts.apply.disabled = count === 0;
        parts.apply.querySelector('span').textContent = count ? fmt(F.apply, {count}) : F.apply_none;
    }

    async function applyChanges() {
        if (!session) return;
        const current = session;
        const changes = current.blocks.map((block) => block.collect()).filter(Boolean);
        if (!changes.length) return;

        parts.apply.disabled = true;
        parts.apply.querySelector('span').textContent = F.applying;
        try {
            const json = await post(current.task.apply_url, {changes});
            current.applied = true;
            showDone(json, current.task);
        } catch (error) {
            const failed = error.data && error.data.data ? error.data.data.failed || [] : [];
            window.alert(failed.length ? failed.map((item) => blockTitles[item.step] + ': ' + item.message).join('\n') : error.message);
            updateApply();
        }
    }

    function showDone(json, task) {
        parts.progress.classList.add('d-none');
        parts.hint.classList.add('d-none');
        parts.blocks.classList.add('d-none');
        parts.actions.classList.add('d-none');

        const done = parts.done;
        done.textContent = '';
        done.append(icon('ti-circle-check'), el('h3', null, F.applied_title), el('p', 'mb-3', json.message));

        const failed = (json.data && json.data.failed) || [];
        if (failed.length) {
            const box = el('div', 'alert alert-warning text-start small');
            box.appendChild(el('div', 'fw-semibold mb-1', F.partial));
            const list = el('ul', 'mb-0 ps-3');
            failed.forEach((item) => list.appendChild(el('li', null, blockTitles[item.step] + ': ' + item.message)));
            box.appendChild(list);
            done.appendChild(box);
        }

        const links = el('div', 'd-flex flex-wrap justify-content-center gap-2');
        if (task.page) {
            const view = el('a', 'btn seo-btn-ghost');
            view.href = task.page;
            view.target = '_blank';
            view.rel = 'noopener noreferrer';
            view.append(icon('ti-external-link'), document.createTextNode(' ' + F.view_page));
            links.appendChild(view);
        }
        if (task.edit_url) {
            const edit = el('a', 'btn seo-btn-ghost');
            edit.href = task.edit_url;
            edit.append(icon('ti-edit'), document.createTextNode(' ' + F.open_editor));
            links.appendChild(edit);
        }
        const close = el('button', 'btn btn-primary', F.close);
        close.type = 'button';
        close.dataset.bsDismiss = 'modal';
        links.appendChild(close);
        done.appendChild(links);
        done.classList.remove('d-none');
    }

    // ---- Review blocks: one per step, each with an "apply this part" switch and collect().
    function frame(title, help) {
        const node = el('section', 'seo-fix__block');
        const head = el('div', 'seo-fix__block-head');
        const text = el('div');
        text.append(el('h3', 'seo-fix__block-title', title));
        if (help) text.append(el('p', 'seo-fix__block-help', help));
        const label = el('label', 'form-check form-switch seo-fix__include');
        const include = el('input', 'form-check-input');
        include.type = 'checkbox';
        include.checked = true;
        include.setAttribute('role', 'switch');
        label.append(include, el('span', 'form-check-label', F.include));
        include.addEventListener('change', () => {
            node.classList.toggle('is-off', !include.checked);
            updateApply();
        });
        head.append(text, label);
        node.append(head);
        return {node, include};
    }

    function field(label, value, options) {
        const settings = options || {};
        const wrap = el('div', 'mb-2');
        const id = 'seo-fix-field-' + (++uid);
        const caption = el('label', 'form-label small fw-semibold mb-1', label);
        caption.htmlFor = id;
        const input = settings.multiline ? el('textarea', 'form-control') : el('input', 'form-control');
        if (settings.multiline) input.rows = settings.rows || 3;
        else input.type = 'text';
        input.id = id;
        input.value = value || '';
        wrap.append(caption, input);
        let refresh = () => {};
        if (settings.max) {
            const counter = el('div', 'seo-fix__counter');
            wrap.appendChild(counter);
            refresh = () => {
                const length = input.value.trim().length;
                counter.textContent = fmt(F.chars, {count: length, max: settings.max}) + (length > settings.max ? ' · ' + F.too_long : '');
                counter.classList.toggle('is-over', length > settings.max);
            };
            refresh();
        }
        input.addEventListener('input', () => {
            refresh();
            updateApply();
        });
        return {wrap, input, refresh};
    }

    /** The fields of a proposal, folded away until the owner wants to change the wording. */
    function editor(nodes) {
        const details = el('details', 'seo-fix__edit');
        const summary = el('summary');
        summary.append(icon('ti-pencil'), document.createTextNode(F.edit));
        details.append(summary, ...nodes);
        return details;
    }

    function siteLabel(page) {
        try {
            const url = new URL(page || window.location.origin);
            const path = decodeURI(url.pathname).split('/').filter(Boolean).join(' › ');
            return url.host + (path ? ' › ' + path : '');
        } catch (error) {
            return window.location.host;
        }
    }

    function serp(site, after) {
        const node = el('div', 'seo-serp' + (after ? ' seo-serp--after' : ''));
        const title = el('div', 'seo-serp__title');
        const description = el('div', 'seo-serp__desc');
        node.append(el('div', 'seo-serp__site', site), title, description);
        return {
            node,
            set(titleText, descriptionText) {
                title.textContent = titleText || F.empty_title;
                title.classList.toggle('is-empty', !titleText);
                description.textContent = descriptionText || F.empty_description;
                description.classList.toggle('is-empty', !descriptionText);
            },
        };
    }

    const renderers = {
        meta(data, task) {
            const block = frame(F.meta_title, F.meta_help);
            const site = siteLabel(task.page);

            const compare = el('div', 'seo-fix__compare');
            const now = serp(site, false);
            now.set(data.current.title, data.current.description);
            const after = serp(site, true);
            const nowColumn = el('div');
            nowColumn.append(el('div', 'seo-fix__caption', F.now), now.node);
            const afterColumn = el('div');
            afterColumn.append(el('div', 'seo-fix__caption', F.after), after.node);
            compare.append(nowColumn, afterColumn);

            const options = el('div', 'seo-fix__options');
            options.appendChild(el('div', 'seo-fix__caption', F.pick));
            const name = 'seo-fix-option-' + (++uid);
            const title = field(F.title_label, '', {max: data.limits.title});
            const description = field(F.description_label, '', {multiline: true, rows: 2, max: data.limits.description});
            let chosen = 0;

            const sync = () => {
                after.set(title.input.value.trim(), description.input.value.trim());
                title.refresh();
                description.refresh();
                updateApply();
            };
            data.options.forEach((option, index) => {
                const label = el('label', 'seo-fix__option');
                const radio = el('input', 'form-check-input mt-1');
                radio.type = 'radio';
                radio.name = name;
                radio.checked = index === 0;
                radio.addEventListener('change', () => {
                    chosen = index;
                    title.input.value = option.title;
                    description.input.value = option.description;
                    sync();
                });
                const text = el('div');
                text.append(el('strong', null, fmt(F.option, {n: index + 1}) + (option.angle ? ' · ' + option.angle : '')), el('small', null, option.title));
                label.append(radio, text);
                options.appendChild(label);
            });
            title.input.addEventListener('input', sync);
            description.input.addEventListener('input', sync);
            if (data.options[0]) {
                title.input.value = data.options[0].title;
                description.input.value = data.options[0].description;
            }
            sync();

            block.node.append(compare, options, title.wrap, description.wrap);

            return {
                node: block.node,
                collect() {
                    const titleText = title.input.value.trim();
                    const descriptionText = description.input.value.trim();
                    if (!block.include.checked || !titleText || !descriptionText) return null;
                    return {step: 'meta', suggestion_id: data.suggestion_id, option: chosen, title: titleText, description: descriptionText};
                },
            };
        },

        section(data) {
            const block = frame(F.section_title, F.section_help);
            const section = data.section || {};

            if ((section.covers || []).length) {
                const covers = el('div', 'seo-fix__covers');
                covers.appendChild(el('strong', null, F.answers));
                section.covers.forEach((query) => covers.appendChild(el('span', null, query)));
                block.node.appendChild(covers);
            }
            const heading = field(F.heading_label, section.heading);
            const paragraphs = (section.paragraphs || []).map((text, index) => field(fmt(F.paragraph_label, {n: index + 1}), text, {multiline: true, rows: 3}));
            const bullets = (section.bullets || []).map((text, index) => field(fmt(F.bullet_label, {n: index + 1}), text));

            // What the page will show, kept in step with the fields below it.
            const preview = el('div', 'seo-fix__preview');
            const render = () => {
                preview.textContent = '';
                preview.appendChild(el('h4', null, heading.input.value.trim()));
                paragraphs.forEach((item) => { if (item.input.value.trim()) preview.appendChild(el('p', null, item.input.value.trim())); });
                const list = el('ul');
                bullets.forEach((item) => { if (item.input.value.trim()) list.appendChild(el('li', null, item.input.value.trim())); });
                if (list.children.length) preview.appendChild(list);
            };
            [heading, ...paragraphs, ...bullets].forEach((item) => item.input.addEventListener('input', render));
            render();
            block.node.append(el('div', 'seo-fix__caption', F.preview), preview,
                editor([heading.wrap, ...paragraphs.map((item) => item.wrap), ...bullets.map((item) => item.wrap)]));

            return {
                node: block.node,
                collect() {
                    const values = (list) => list.map((item) => item.input.value.trim()).filter(Boolean);
                    const headingText = heading.input.value.trim();
                    const texts = values(paragraphs);
                    if (!block.include.checked || !headingText || !texts.length) return null;
                    return {step: 'section', suggestion_id: data.suggestion_id, section: {heading: headingText, paragraphs: texts, bullets: values(bullets)}};
                },
            };
        },

        faq(data) {
            const block = frame(F.faq_title, F.faq_help);
            const items = (data.items || []).map((item) => {
                const row = el('div', 'seo-fix__item');
                const keep = el('input', 'form-check-input mt-2');
                keep.type = 'checkbox';
                keep.checked = true;
                keep.setAttribute('aria-label', F.include);
                const fields = el('div');
                const question = field(F.question, item.question);
                const answer = field(F.answer, item.answer, {multiline: true, rows: 2});
                const shownQuestion = el('div', 'is-question', item.question);
                const shownAnswer = el('p', 'mb-0', item.answer);
                question.input.addEventListener('input', () => { shownQuestion.textContent = question.input.value.trim(); });
                answer.input.addEventListener('input', () => { shownAnswer.textContent = answer.input.value.trim(); });
                keep.addEventListener('change', () => {
                    row.classList.toggle('opacity-50', !keep.checked);
                    updateApply();
                });
                const shown = el('div', 'seo-fix__preview');
                shown.append(shownQuestion, shownAnswer);
                fields.append(shown, editor([question.wrap, answer.wrap]));
                row.append(keep, fields);
                block.node.appendChild(row);
                return {keep, question, answer};
            });

            return {
                node: block.node,
                collect() {
                    if (!block.include.checked) return null;
                    const kept = items.filter((item) => item.keep.checked)
                        .map((item) => ({question: item.question.input.value.trim(), answer: item.answer.input.value.trim()}))
                        .filter((item) => item.question && item.answer);
                    return kept.length ? {step: 'faq', suggestion_id: data.suggestion_id, items: kept} : null;
                },
            };
        },

        alt(data) {
            const block = frame(F.alt_title, F.alt_help);
            const items = (data.items || []).map((item) => {
                const row = el('div', 'seo-fix__item');
                const keep = el('input', 'form-check-input mt-2');
                keep.type = 'checkbox';
                keep.checked = true;
                keep.setAttribute('aria-label', F.include);
                keep.addEventListener('change', updateApply);
                const image = el('img');
                image.src = item.src;
                image.alt = '';
                image.loading = 'lazy';
                const fields = el('div');
                const alt = field(F.alt_title, item.alt);
                fields.appendChild(alt.wrap);
                row.append(keep, image, fields);
                block.node.appendChild(row);
                return {keep, alt, id: item.suggestion_id};
            });
            if (data.remaining > 0) block.node.appendChild(el('p', 'small seo-muted mb-0', fmt(F.alt_remaining, {count: data.remaining})));

            return {
                node: block.node,
                collect() {
                    if (!block.include.checked) return null;
                    const kept = items.filter((item) => item.keep.checked && item.alt.input.value.trim())
                        .map((item) => ({suggestion_id: item.id, alt: item.alt.input.value.trim()}));
                    return kept.length ? {step: 'alt', items: kept} : null;
                },
            };
        },
    };
})();
