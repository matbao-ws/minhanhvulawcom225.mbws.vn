@php
    $historyPanelAllowed = \App\Support\ClientEditorChrome::editorActive();
@endphp

@if($historyPanelAllowed)
    {{--
        Saved history, and the way back to one entry of it.

        The revisions were already being written — coalesced into ten-minute
        checkpoints — and until now nothing read them. This is that reader.

        One panel serves every grain because they differ only in which endpoint
        answers: a text or image region (`site_blocks`), a section's appearance
        (`site_sections`), and the body of a CMS page (`page_revisions`).
        Splitting them would duplicate the list, the empty state and the restore
        flow to no end.

        The first two are opened from an outline row, which owns one region. The
        page body has no row — it is the document those rows sit inside — so the
        admin bar opens it instead, handing over the editable root.

        Rolling back calls the server immediately rather than queueing behind Lưu.
        Only the server knows what a snapshot held, and it forces a checkpoint of the
        value being replaced first — so the rollback is itself undoable, which is
        what makes an immediate write acceptable here.
    --}}
    <style>
        #client-history {
            background: #fff !important;
            border-left: 1px solid #e3e8ef !important;
            bottom: 0 !important;
            box-shadow: -6px 0 28px rgba(15, 23, 42, .14) !important;
            color: #172033 !important;
            display: flex !important;
            flex-direction: column !important;
            font: 500 14px/1.5 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif !important;
            margin: 0 !important;
            max-width: 88vw !important;
            padding: 0 !important;
            position: fixed !important;
            right: 0 !important;
            top: 0 !important;
            transform: translateX(100%) !important;
            transition: transform .2s ease !important;
            visibility: hidden !important;
            width: 340px !important;
            z-index: 2147483000 !important;
        }
        /* Class, not Alpine's `x-show`: an inline `display: none` loses to the
           author `!important` above, which theme CSS forces us to use. */
        #client-history.is-open {
            transform: none !important;
            visibility: visible !important;
        }
        #client-history [hidden] { display: none !important; }
        #client-history * { box-sizing: border-box !important; }
        #client-history .client-hi__head {
            align-items: center !important;
            border-bottom: 1px solid #e3e8ef !important;
            display: flex !important;
            gap: 8px !important;
            padding: 12px 14px !important;
        }
        #client-history .client-hi__title {
            flex: 1 1 auto !important;
            font: 700 15px/1.3 inherit !important;
            margin: 0 !important;
            min-width: 0 !important;
        }
        #client-history .client-hi__where {
            color: #6b7a90 !important;
            display: block !important;
            font: 600 11px/1.4 inherit !important;
            letter-spacing: .04em !important;
            text-transform: uppercase !important;
            word-break: break-all !important;
        }
        #client-history .client-hi__close {
            background: #eef1f6 !important;
            border: 0 !important;
            border-radius: 7px !important;
            color: #45536b !important;
            cursor: pointer !important;
            display: inline-flex !important;
            flex: 0 0 auto !important;
            padding: 6px 8px !important;
        }
        #client-history .client-hi__body {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            padding: 10px 12px !important;
        }
        #client-history .client-hi__row {
            border: 1px solid #e3e8ef !important;
            border-radius: 9px !important;
            margin-bottom: 8px !important;
            padding: 10px !important;
        }
        #client-history .client-hi__when {
            color: #45536b !important;
            display: block !important;
            font-size: 12px !important;
        }
        #client-history .client-hi__preview {
            display: block !important;
            font-size: 13px !important;
            margin: 4px 0 8px !important;
            overflow-wrap: anywhere !important;
        }
        #client-history .client-hi__restore {
            background: #eef2ff !important;
            border: 1px solid #c7d2fe !important;
            border-radius: 7px !important;
            color: #3730a3 !important;
            cursor: pointer !important;
            font: inherit !important;
            font-size: 13px !important;
            padding: 6px 10px !important;
        }
        #client-history .client-hi__restore:disabled {
            cursor: not-allowed !important;
            opacity: .5 !important;
        }
        #client-history .client-hi__empty {
            color: #6b7a90 !important;
            padding: 10px 4px !important;
        }
        @media (prefers-reduced-motion: reduce) {
            #client-history { transition: none !important; }
        }
    </style>

    <aside
        id="client-history"
        x-data="clientHistoryPanel"
        :class="{ 'is-open': isOpen }"
        aria-label="Lịch sử phiên bản"
    >
        <div class="client-hi__head">
            <h2 class="client-hi__title">
                Lịch sử
                <span class="client-hi__where" x-text="label"></span>
            </h2>
            <button type="button" class="client-hi__close" @click="close()" title="Đóng" aria-label="Đóng">
                {{-- Inline SVG, never an icon font: a storefront may load none. --}}
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="client-hi__body">
            <template x-if="loading">
                <p class="client-hi__empty">Đang tải…</p>
            </template>

            <template x-if="!loading && entries.length === 0">
                {{-- Not an error. A region saved once has nothing before it to keep,
                     and that is the correct answer rather than a missing revision. --}}
                <p class="client-hi__empty">Chưa có phiên bản nào được lưu cho vùng này.</p>
            </template>

            <template x-for="entry in entries" :key="entry.id">
                <div class="client-hi__row">
                    <span class="client-hi__when">
                        <span x-text="when(entry.created_at)"></span>
                        <template x-if="entry.author">
                            <span> · <span x-text="entry.author"></span></span>
                        </template>
                    </span>
                    <span class="client-hi__preview" x-text="entry.preview || '(trống)'"></span>
                    <button type="button" class="client-hi__restore"
                            :disabled="busy || entry.has_locale === false"
                            @click="restore(entry)">
                        <span x-text="entry.has_locale === false ? 'Không có bản dịch này' : 'Khôi phục bản này'"></span>
                    </button>
                </div>
            </template>
        </div>
    </aside>

    <script>
        document.addEventListener('alpine:init', function () {
            if (!window.Alpine) return;

            /*
             * Same bootstrap order problem the settings panel documents: Alpine's CDN
             * build starts itself in a microtask as soon as its script runs, which
             * `defer` places before DOMContentLoaded — where the bar publishes
             * `window.clientBlocks`. Register now, attach when the bar appears.
             */
            let api = null;

            function whenBarReady(callback) {
                if (window.clientBlocks) {
                    callback(window.clientBlocks);

                    return;
                }

                document.addEventListener('DOMContentLoaded', function () {
                    if (window.clientBlocks) callback(window.clientBlocks);
                });
            }

            whenBarReady(function (bar) { api = bar; });

            async function call(method, url, body) {
                const response = await fetch(url, {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': api.csrf,
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const payload = await response.json().catch(function () { return {}; });

                if (!response.ok || !payload.success) {
                    const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                    throw new Error(errors || payload.message || 'Không đọc được lịch sử.');
                }

                return payload.data;
            }

            window.Alpine.data('clientHistoryPanel', function () {
                return {
                    isOpen: false,
                    loading: false,
                    busy: false,
                    label: '',
                    entries: [],
                    target: null,

                    init: function () {
                        const component = this;

                        whenBarReady(function (bar) {
                            // Leaving edit mode closes it: a restore button that still
                            // worked after the editor stood down would be a trap.
                            bar.on('mode', function (on) {
                                if (!on) component.isOpen = false;
                            });
                        });

                        window.clientHistoryPanel = {
                            /**
                             * `element` is an outline node: a region carrying
                             * `data-block-key`, or a section wrapper carrying
                             * `data-section-list` and `data-section-name`.
                             */
                            open: function (element) { component.openFor(element); },
                            close: function () { component.isOpen = false; },
                        };
                    },

                    openFor: async function (element) {
                        if (!api || !element) return;

                        const blockKey = element.getAttribute('data-block-key');
                        const listKey = element.getAttribute('data-section-list');
                        const section = element.getAttribute('data-section-name');

                        if (blockKey) {
                            this.target = { kind: 'block', element: element, key: blockKey };
                            this.label = blockKey;
                        } else if (listKey && section) {
                            this.target = { kind: 'section', element: element, listKey: listKey, section: section };
                            this.label = listKey + ' › ' + section;
                        } else if (element.hasAttribute('data-client-editable-root') && api.urls.pageRevisions) {
                            // The CMS page body. It has no key of its own because it
                            // is not a region — it is the document the regions would
                            // sit inside, saved whole by the bar. The URL already
                            // names the page, so nothing has to be passed here.
                            this.target = { kind: 'page', element: element };
                            this.label = 'Nội dung trang';
                        } else {
                            return;
                        }

                        this.entries = [];
                        this.loading = true;
                        this.isOpen = true;

                        try {
                            const data = await call('GET', this.indexUrl());
                            this.entries = data.revisions || [];
                        } catch (error) {
                            api.status(error.message, true);
                            this.isOpen = false;
                        } finally {
                            this.loading = false;
                        }
                    },

                    close: function () {
                        this.isOpen = false;
                    },

                    indexUrl: function () {
                        const target = this.target;

                        if (target.kind === 'page') {
                            return api.urls.pageRevisions;
                        }

                        if (target.kind === 'block') {
                            return api.urls.blockRevisions + '?key=' + encodeURIComponent(target.key);
                        }

                        return api.urls.sectionRevisions
                            + '?list_key=' + encodeURIComponent(target.listKey)
                            + '&section=' + encodeURIComponent(target.section);
                    },

                    restoreUrl: function (entry) {
                        const template = {
                            page: api.urls.pageRevisionRestore,
                            block: api.urls.blockRevisionRestore,
                            section: api.urls.sectionRevisionRestore,
                        }[this.target.kind];

                        // The template carries a placeholder id, so the route name stays
                        // the single source of the path shape.
                        return template.replace('__ID__', String(entry.id));
                    },

                    /**
                     * Roll one entry back.
                     *
                     * Immediate, and safe to be immediate: the server forces a
                     * checkpoint of the value it is replacing, so this is undoable from
                     * the very list it refreshes.
                     */
                    restore: async function (entry) {
                        if (!api || this.busy) return;

                        this.busy = true;

                        try {
                            await call('POST', this.restoreUrl(entry));
                            api.status('Đã khôi phục phiên bản. Tải lại trang để xem nội dung mới.');

                            // Reloading the list rather than the page: the new checkpoint
                            // has to appear, and the admin may want to step back again.
                            const data = await call('GET', this.indexUrl());
                            this.entries = data.revisions || [];
                        } catch (error) {
                            api.status(error.message, true);
                        } finally {
                            this.busy = false;
                        }
                    },

                    /** Locale-formatted, falling back to the raw stamp. */
                    when: function (value) {
                        if (!value) return '';

                        const date = new Date(value);

                        return isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
                    },
                };
            });
        });
    </script>
@endif
