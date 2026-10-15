/*
 * Admin lists without full page reloads.
 *
 * A page marks the part that changes with data-ajax-list="<name>" (the table, its bulk toolbar and
 * its pagination). Then, on that page:
 * - a GET form aimed at the same page (filters, search, per page) and a link inside the list to the
 *   same page (pagination, sorting) fetch the page and swap only the list; the address bar follows,
 *   so reload, a shared link and Back still give the same list;
 * - a form that changes something (delete, status switch, bulk action) and sits inside the list, or
 *   carries data-ajax-form (a quick-edit modal), is posted with fetch; the server answers exactly as
 *   for a normal submit (same route, permission, validation, redirect back with a flash), and the
 *   list is swapped from that answer and the flash shown as a toast.
 *
 * Nothing here decides anything: without JavaScript, or when the answer is not the same page, the
 * browser does what it always did. data-no-ajax on a form or link opts out.
 *
 * Page scripts that bind to rows register with AdminAjax.ready(fn): fn(document) once loaded, then
 * fn(list) for each list swapped in.
 */
(function () {
    'use strict';

    const LIST = '[data-ajax-list]';
    const FLASH_KEY = 'admin-ajax-flash';
    const readyCallbacks = [];
    let pending = null;
    let historyTouched = false;

    const lists = function (root) {
        return Array.from((root || document).querySelectorAll(LIST));
    };

    const sameUrl = function (url) {
        try {
            const target = new URL(url, window.location.href);
            return target.origin === window.location.origin && target.pathname === window.location.pathname;
        } catch (error) {
            return false;
        }
    };

    // Read from the attributes: a field named "action" or "target" (the bulk forms have one)
    // hides the form's own properties of that name.
    const formAction = function (form) {
        return new URL(form.getAttribute('action') || window.location.href, window.location.href).toString();
    };

    const formTarget = function (form) {
        return form.getAttribute('target') || '';
    };

    const text = function (key, fallback) {
        const config = window.AdminAjaxText || {};
        return config[key] || fallback;
    };

    // ---------------------------------------------------------------- flash messages

    const readFlash = function (doc) {
        const node = doc.querySelector('script[data-admin-flash]');
        if (!node) return null;
        try {
            return JSON.parse(node.textContent || 'null');
        } catch (error) {
            return null;
        }
    };

    const toast = function (icon, message) {
        if (!message) return;
        if (!window.Swal) {
            if (icon === 'error') window.alert(message);
            return;
        }
        Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            didOpen: function (element) {
                element.addEventListener('mouseenter', Swal.stopTimer);
                element.addEventListener('mouseleave', Swal.resumeTimer);
            },
        }).fire({ icon: icon, title: message });
    };

    const showFlash = function (flash) {
        if (!flash) return;
        if (flash.limit && window.Swal) {
            // A package refusal stays until acknowledged, as on a full page load.
            Swal.fire({
                icon: 'warning',
                title: flash.limit.title,
                html: flash.limit.html,
                confirmButtonText: flash.limit.upgrade,
                confirmButtonColor: '#e32326',
                showCancelButton: true,
                cancelButtonText: flash.limit.ok,
                allowOutsideClick: false,
            }).then(function (result) {
                if (result.isConfirmed) window.open(flash.limit.url, '_blank', 'noopener');
            });
            return;
        }
        if (flash.toast) toast(flash.toast.icon, flash.toast.message);
    };

    const failed = function (status) {
        if (status === 403) return toast('error', text('forbidden', 'Bạn không có quyền thực hiện thao tác này.'));
        if (status === 404) return toast('error', text('missing', 'Không tìm thấy dữ liệu, có thể đã bị xoá.'));
        if (status === 429) return toast('error', text('throttled', 'Thao tác quá nhanh, vui lòng thử lại sau ít phút.'));
        toast('error', text('failed', 'Không thực hiện được. Vui lòng tải lại trang và thử lại.'));
    };

    // ---------------------------------------------------------------- swapping

    const setBusy = function (busy) {
        lists().forEach(function (list) {
            list.classList.toggle('is-ajax-loading', busy);
            list.setAttribute('aria-busy', busy ? 'true' : 'false');
        });
    };

    const runReady = function (root) {
        readyCallbacks.forEach(function (callback) {
            try {
                callback(root);
            } catch (error) {
                window.console && console.error(error);
            }
        });
    };

    // Swaps every list of this page for its new version; false when the answer lacks one.
    const swap = function (doc) {
        const current = lists();
        const next = current.map(function (list) {
            return doc.querySelector('[data-ajax-list="' + CSS.escape(list.dataset.ajaxList) + '"]');
        });
        if (!current.length || next.some(function (node) { return !node; })) return false;

        const focused = document.activeElement && document.activeElement.id;
        const swapped = current.map(function (list, index) {
            const fresh = document.importNode(next[index], true);
            list.replaceWith(fresh);
            return fresh;
        });
        if (focused && document.getElementById(focused) && swapped.some(function (list) { return list.contains(document.getElementById(focused)); })) {
            document.getElementById(focused).focus({ preventScroll: true });
        }
        swapped.forEach(function (list) {
            runReady(list);
            list.dispatchEvent(new CustomEvent('admin:list-updated', { bubbles: true }));
        });
        return true;
    };

    const parse = function (html) {
        return new DOMParser().parseFromString(html, 'text/html');
    };

    // Filter forms outside the list show what the address says (after Back/Forward).
    const syncForms = function () {
        const params = new URL(window.location.href).searchParams;
        document.querySelectorAll('form').forEach(function (form) {
            if (!isListForm(form) || form.closest(LIST)) return;
            Array.from(form.elements).forEach(function (field) {
                if (!field.name || field.type === 'hidden' || field.type === 'submit' || field.type === 'button' || field.type === 'file') return;
                const values = params.getAll(field.name);
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = values.indexOf(field.value) !== -1;
                } else if (field.multiple) {
                    Array.from(field.options).forEach(function (option) { option.selected = values.indexOf(option.value) !== -1; });
                } else {
                    field.value = values.length ? values[0] : '';
                }
            });
        });
    };

    const scrollToList = function () {
        const first = lists()[0];
        if (!first) return;
        const top = first.getBoundingClientRect().top;
        if (top < 0) window.scrollTo({ top: window.scrollY + top - 90, behavior: 'smooth' });
    };

    // ---------------------------------------------------------------- loading a list (GET)

    const load = function (url, options) {
        options = options || {};
        if (pending) pending.abort();
        const controller = new AbortController();
        pending = controller;
        setBusy(true);

        return fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Admin-Ajax': '1' },
            signal: controller.signal,
        }).then(function (response) {
            if (!response.ok || !sameUrl(response.url)) throw new Error('navigate');
            return response.text().then(function (html) {
                const doc = parse(html);
                if (!swap(doc)) throw new Error('navigate');
                if (options.push !== false) {
                    window.history.pushState({ adminAjax: true }, '', response.url);
                    historyTouched = true;
                }
                if (doc.title) document.title = doc.title;
                syncForms();
                showFlash(readFlash(doc));
                if (options.scroll) scrollToList();
            });
        }).catch(function (error) {
            if (error && error.name === 'AbortError') return;
            window.location.assign(url);
        }).finally(function () {
            if (pending === controller) {
                pending = null;
                setBusy(false);
            }
        });
    };

    // ---------------------------------------------------------------- changing something (POST)

    const formData = function (form, submitter) {
        let data;
        try {
            data = new FormData(form, submitter || undefined);
        } catch (error) {
            data = new FormData(form);
            if (submitter && submitter.name) data.append(submitter.name, submitter.value);
        }
        return data;
    };

    const isListForm = function (form) {
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        return method === 'get'
            && !form.hasAttribute('data-no-ajax')
            && (!formTarget(form) || formTarget(form) === '_self')
            && lists().length > 0
            && sameUrl(formAction(form));
    };

    const isChangeForm = function (form) {
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        return method !== 'get'
            && !form.hasAttribute('data-no-ajax')
            && (!formTarget(form) || formTarget(form) === '_self')
            && lists().length > 0
            && (form.hasAttribute('data-ajax-form') || form.closest(LIST) !== null);
    };

    const post = function (form, submitter) {
        // One answer at a time per form: Enter and leaving a field must not save twice.
        if (form.dataset.ajaxBusy) return Promise.resolve();
        form.dataset.ajaxBusy = '1';
        const buttons = Array.from(form.querySelectorAll('button, input[type="submit"]'));
        if (form.id) buttons.push.apply(buttons, Array.from(document.querySelectorAll('[form="' + CSS.escape(form.id) + '"][type="submit"]')));
        buttons.forEach(function (button) { button.disabled = true; });
        setBusy(true);

        return fetch(formAction(form), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Admin-Ajax': '1' },
            body: formData(form, submitter),
        }).then(function (response) {
            if (response.status === 419) {
                // The session or its token expired: a fresh page asks to sign in again.
                window.location.reload();
                return;
            }
            return response.text().then(function (html) {
                const doc = parse(html);
                const flash = readFlash(doc);
                const ok = !(flash && flash.toast && flash.toast.icon === 'error') && !(flash && flash.limit);

                if (response.ok && sameUrl(response.url) && swap(doc)) {
                    showFlash(flash);
                    // A quick-edit dialog closes once saved; refused, it stays open to fix.
                    const modal = form.closest('.modal');
                    if (ok && modal && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                    form.dispatchEvent(new CustomEvent('admin:ajax-done', { bubbles: true, detail: { ok: ok } }));
                    return;
                }
                if (response.ok && !sameUrl(response.url)) {
                    // Somewhere else (the created record, another list): go there with the message.
                    try { sessionStorage.setItem(FLASH_KEY, JSON.stringify(flash)); } catch (error) { /* shown or not, the work is done */ }
                    window.location.assign(response.url);
                    return;
                }
                failed(response.status);
            });
        }).catch(function () {
            toast('error', text('offline', 'Mất kết nối. Kiểm tra mạng rồi thử lại.'));
        }).finally(function () {
            buttons.forEach(function (button) { button.disabled = false; });
            delete form.dataset.ajaxBusy;
            setBusy(false);
        });
    };

    // For scripts that confirm first (delete dialogs, bulk toolbar): posts like a click on Submit would.
    const submit = function (form, submitter) {
        if (isChangeForm(form)) return post(form, submitter);
        if (isListForm(form)) return load(listUrl(form, submitter), { scroll: true });
        HTMLFormElement.prototype.submit.call(form);
        return Promise.resolve();
    };

    const listUrl = function (form, submitter) {
        const url = new URL(formAction(form));
        const params = new URLSearchParams();
        formData(form, submitter).forEach(function (value, key) {
            if (typeof value === 'string') params.append(key, value);
        });
        url.search = params.toString();
        url.hash = '';
        return url.toString();
    };

    // ---------------------------------------------------------------- wiring

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
        // A delete form asks first (layout dialog), then calls AdminAjax.submit itself.
        if (form.classList.contains('js-delete-form')) return;

        if (isChangeForm(form)) {
            event.preventDefault();
            post(form, event.submitter);
        } else if (isListForm(form)) {
            event.preventDefault();
            load(listUrl(form, event.submitter), { scroll: true });
        }
    });

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || !link.closest(LIST) || link.hasAttribute('data-no-ajax') || link.hasAttribute('download')) return;
        if (link.target && link.target !== '_self') return;
        const href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        const target = new URL(link.href);
        // Another page, or a jump within this one: the browser's own business.
        if (!sameUrl(link.href) || (target.hash && target.search === window.location.search)) return;

        event.preventDefault();
        load(link.href, { scroll: true });
    });

    window.addEventListener('popstate', function () {
        if (historyTouched || (window.history.state && window.history.state.adminAjax)) {
            load(window.location.href, { push: false });
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (lists().length) window.history.replaceState({ adminAjax: true }, '', window.location.href);
        try {
            const stashed = sessionStorage.getItem(FLASH_KEY);
            if (stashed) {
                sessionStorage.removeItem(FLASH_KEY);
                showFlash(JSON.parse(stashed));
            }
        } catch (error) { /* no storage: nothing to show */ }
        runReady(document);
    });

    window.AdminAjax = {
        ready: function (callback) {
            readyCallbacks.push(callback);
            if (document.readyState !== 'loading') callback(document);
        },
        submit: submit,
        load: load,
        toast: toast,
    };
})();
