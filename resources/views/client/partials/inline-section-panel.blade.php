@php
    $sectionPanelAllowed = \App\Support\ClientEditorChrome::editorActive();
    $sectionSchema = $sectionPanelAllowed ? app(\App\Support\SectionRegistry::class)->schema() : [];
@endphp

@if($sectionPanelAllowed && $sectionSchema !== [])
    {{--
        Appearance settings for one section.

        What this panel can do is bounded by `App\Support\SectionRegistry`: an editor
        picks among shapes the section already knows how to render. There is no field
        here for a class, a pixel value or a position, and adding one would turn this
        into the page builder the core deliberately does not have.

        Alpine drives it. The rest of the editor is vanilla and stays that way — this
        panel is a form with a dozen bound controls, which is the one place binding
        earns its keep. It never touches `[data-block-key]`: `admin-bar` owns edit
        mode, this asks it for permission to queue work and nothing more.

        A direct child of `<body>`, like the other editor partials, because a
        `transform` on any ancestor would re-anchor `position: fixed` to that element
        instead of the viewport.
    --}}
    <style>
        #client-section-panel {
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
            transition: transform .2s ease !important;
            width: 320px !important;
            z-index: 2147483000 !important;
        }
        /*
         * Visibility rides on a class, not on Alpine's `x-show`.
         *
         * `x-show` hides by writing `display: none` inline, and an inline
         * declaration loses to an author `!important` rule — which the line above
         * has to be, or theme CSS wins and the panel is unstyled. The outline panel
         * already opens this way, so the two behave alike.
         */
        #client-section-panel {
            transform: translateX(100%) !important;
            visibility: hidden !important;
        }
        #client-section-panel.is-open {
            transform: none !important;
            visibility: visible !important;
        }
        #client-section-panel [hidden] { display: none !important; }
        #client-section-panel * { box-sizing: border-box !important; }
        #client-section-panel .client-sp__head {
            align-items: center !important;
            border-bottom: 1px solid #e3e8ef !important;
            display: flex !important;
            gap: 8px !important;
            padding: 12px 14px !important;
        }
        #client-section-panel .client-sp__title {
            flex: 1 1 auto !important;
            font: 700 15px/1.3 inherit !important;
            margin: 0 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        #client-section-panel .client-sp__where {
            color: #6b7a90 !important;
            display: block !important;
            font: 600 11px/1.4 inherit !important;
            letter-spacing: .04em !important;
            text-transform: uppercase !important;
            word-break: break-all !important;
        }
        #client-section-panel .client-sp__close {
            background: #eef1f6 !important;
            border: 0 !important;
            border-radius: 7px !important;
            color: #45536b !important;
            cursor: pointer !important;
            display: inline-flex !important;
            flex: 0 0 auto !important;
            padding: 6px 8px !important;
        }
        #client-section-panel .client-sp__body {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            padding: 12px 14px !important;
        }
        #client-section-panel .client-sp__field {
            border: 0 !important;
            display: block !important;
            margin: 0 0 14px !important;
            padding: 0 !important;
        }
        #client-section-panel .client-sp__label {
            color: #45536b !important;
            display: block !important;
            font: 700 11px/1.4 inherit !important;
            letter-spacing: .06em !important;
            margin: 0 0 5px !important;
            text-transform: uppercase !important;
        }
        #client-section-panel select {
            appearance: auto !important;
            background: #fff !important;
            border: 1px solid #d4dbe6 !important;
            border-radius: 8px !important;
            color: #172033 !important;
            display: block !important;
            font: inherit !important;
            font-size: 14px !important;
            padding: 8px 10px !important;
            width: 100% !important;
        }
        #client-section-panel .client-sp__colour {
            align-items: center !important;
            display: flex !important;
            gap: 8px !important;
        }
        #client-section-panel input[type="color"] {
            background: #fff !important;
            border: 1px solid #d4dbe6 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            flex: 0 0 auto !important;
            height: 38px !important;
            padding: 2px !important;
            width: 52px !important;
        }
        #client-section-panel .client-sp__ghost {
            background: transparent !important;
            border: 1px solid #d4dbe6 !important;
            border-radius: 8px !important;
            color: #45536b !important;
            cursor: pointer !important;
            font: inherit !important;
            font-size: 13px !important;
            padding: 8px 10px !important;
        }
        #client-section-panel .client-sp__toggle {
            align-items: center !important;
            cursor: pointer !important;
            display: flex !important;
            font: inherit !important;
            gap: 8px !important;
            margin: 0 0 14px !important;
        }
        #client-section-panel .client-sp__foot {
            border-top: 1px solid #e3e8ef !important;
            padding: 12px 14px !important;
        }
        #client-section-panel .client-sp__restore {
            background: #fff5f5 !important;
            border: 1px solid #f3c7c7 !important;
            border-radius: 8px !important;
            color: #a02323 !important;
            cursor: pointer !important;
            font: inherit !important;
            font-size: 13px !important;
            padding: 9px 12px !important;
            width: 100% !important;
        }
        #client-section-panel .client-sp__note {
            color: #6b7a90 !important;
            font-size: 12px !important;
            margin: 8px 0 0 !important;
        }
        @media (prefers-reduced-motion: reduce) {
            #client-section-panel { transition: none !important; }
        }
    </style>

    <aside
        id="client-section-panel"
        x-data="clientSectionPanel"
        :class="{ 'is-open': isOpen }"
        aria-label="Cài đặt hiển thị section"
    >
        <div class="client-sp__head">
            <h2 class="client-sp__title">
                <span x-text="label"></span>
                <span class="client-sp__where" x-text="listKey"></span>
            </h2>
            <button type="button" class="client-sp__close" @click="close()" title="Đóng" aria-label="Đóng">
                {{-- Inline SVG, never an icon font: an arbitrary project's storefront
                     may load none, and a missing glyph makes the button look empty. --}}
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="client-sp__body">
            <template x-if="variants.length > 0">
                <label class="client-sp__field">
                    <span class="client-sp__label">Biến thể</span>
                    <select x-model="variant" @change="apply()">
                        {{-- Empty is not "none", it is "whatever the template does".
                             Same meaning as an absent setting, and the reason the
                             registry declares no variant for it. --}}
                        <option value="">Theo template</option>
                        <template x-for="option in variants" :key="option.value">
                            <option :value="option.value" x-text="option.label"></option>
                        </template>
                    </select>
                </label>
            </template>

            <template x-for="setting in settings" :key="setting.name">
                <div class="client-sp__field">
                    <span class="client-sp__label" x-text="setting.label"></span>

                    <template x-if="setting.type === 'token'">
                        <select x-model="values[setting.name]" @change="apply()">
                            <option value="">Theo template</option>
                            <template x-for="option in setting.values" :key="option.value">
                                <option :value="option.value" x-text="option.label"></option>
                            </template>
                        </select>
                    </template>

                    <template x-if="setting.type === 'color'">
                        <div class="client-sp__colour">
                            {{-- A colour input cannot be empty — it falls back to
                                 #000000 — so clearing needs its own control, or
                                 "no colour chosen" would be unreachable. --}}
                            <input type="color" :value="values[setting.name] || '#2563eb'"
                                   @input="values[setting.name] = $event.target.value; apply()"
                                   :aria-label="setting.label">
                            <button type="button" class="client-sp__ghost"
                                    @click="values[setting.name] = ''; apply()">Theo template</button>
                        </div>
                    </template>
                </div>
            </template>

            <label class="client-sp__toggle">
                <input type="checkbox" x-model="hidden" @change="apply()">
                <span>Ẩn section này khỏi trang</span>
            </label>
        </div>

        <div class="client-sp__foot">
            <button type="button" class="client-sp__restore" @click="restore()">Trả section về mặc định</button>
            <p class="client-sp__note">Thay đổi ở trên chờ nút <strong>Lưu</strong> trên thanh dưới. Nút này gọi server ngay.</p>
        </div>
    </aside>

    <script>
        document.addEventListener('alpine:init', function () {
            const schema = @json($sectionSchema);

            if (!window.Alpine) return;

            /*
             * The bar is not here yet.
             *
             * `window.clientBlocks` is published from `admin-bar`'s DOMContentLoaded
             * handler, while Alpine's CDN build starts itself in a microtask as soon as
             * its own script runs — which `defer` places *before* DOMContentLoaded. So
             * `alpine:init` and every component's `init()` run first.
             *
             * Registering the component cannot wait (Alpine.data has to be called
             * before start), so only the parts that talk to the bar do.
             */
            let api = null;

            function whenBarReady(callback) {
                if (window.clientBlocks) {
                    callback(window.clientBlocks);

                    return;
                }

                document.addEventListener('DOMContentLoaded', function () {
                    // Absent means the page has no editable region at all, so the bar
                    // stood down on purpose. Nothing to attach to, and no error.
                    if (window.clientBlocks) callback(window.clientBlocks);
                });
            }

            /*
             * Queued changes and how to undo them, kept outside the Alpine component
             * on purpose: closing the panel must not discard work, and the component's
             * state is rebuilt every time it opens on a different section.
             */
            const queued = new Map();
            const snapshots = new Map();

            function wrapperOf(listKey, section) {
                const escape = window.CSS && CSS.escape ? CSS.escape : function (value) { return value; };

                return document.querySelector(
                    '[data-section-list="' + escape(listKey) + '"][data-section-name="' + escape(section) + '"]'
                );
            }

            /** Every class and custom property this section's schema can produce. */
            function vocabulary(section) {
                const entry = schema[section];
                const classes = ['client-section--hidden'];
                const properties = [];

                if (!entry) return { classes: classes, properties: properties };

                entry.variants.forEach(function (option) {
                    if (option.class) classes.push(option.class);
                });
                entry.settings.forEach(function (setting) {
                    if (setting.class) classes.push(setting.class);
                    if (setting.property) properties.push(setting.property);
                    setting.values.forEach(function (option) {
                        if (option.class) classes.push(option.class);
                    });
                });

                return { classes: classes, properties: properties };
            }

            /**
             * Show the queued state on the page.
             *
             * Everything this section's schema could have put there is removed first,
             * so switching from `dark` to `muted` does not leave both classes on and
             * let specificity decide which one the admin sees.
             */
            function paint(wrapper, section, state) {
                const known = vocabulary(section);
                const entry = schema[section];

                known.classes.forEach(function (name) { wrapper.classList.remove(name); });
                known.properties.forEach(function (name) { wrapper.style.removeProperty(name); });

                if (!entry) return;

                const variant = entry.variants.find(function (option) { return option.value === state.variant; });
                if (variant && variant.class) wrapper.classList.add(variant.class);

                entry.settings.forEach(function (setting) {
                    const value = state.values[setting.name];
                    if (!value) return;

                    if (setting.type === 'color') {
                        if (setting.class) wrapper.classList.add(setting.class);
                        if (setting.property) wrapper.style.setProperty(setting.property, value);

                        return;
                    }

                    const option = setting.values.find(function (candidate) { return candidate.value === value; });
                    if (option && option.class) wrapper.classList.add(option.class);
                });

                if (state.hidden) wrapper.classList.add('client-section--hidden');
            }

            /** What the server currently believes, read off the wrapper. */
            function storedState(wrapper) {
                let values = {};

                try {
                    values = JSON.parse(wrapper.getAttribute('data-section-settings') || '{}') || {};
                } catch (error) {
                    values = {};
                }

                return {
                    variant: wrapper.getAttribute('data-section-variant') || '',
                    values: values,
                    hidden: wrapper.getAttribute('data-section-hidden') === 'true',
                };
            }

            /** Write the server's answer back onto the wrapper and repaint from it. */
            function adopt(wrapper, section, data) {
                const known = vocabulary(section);

                known.classes.forEach(function (name) { wrapper.classList.remove(name); });
                known.properties.forEach(function (name) { wrapper.style.removeProperty(name); });

                (data.classes || []).forEach(function (name) { wrapper.classList.add(name); });
                if (data.variant_class) wrapper.classList.add(data.variant_class);
                Object.keys(data.styles || {}).forEach(function (property) {
                    wrapper.style.setProperty(property, data.styles[property]);
                });
                if (data.hidden) wrapper.classList.add('client-section--hidden');

                wrapper.setAttribute('data-section-variant', data.variant || '');
                wrapper.setAttribute('data-section-hidden', data.hidden ? 'true' : 'false');
                wrapper.setAttribute('data-section-settings', JSON.stringify(data.settings || {}));
            }

            async function send(method, body) {
                const response = await fetch(api.urls.sections, {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': api.csrf,
                    },
                    body: JSON.stringify(body),
                });
                const payload = await response.json().catch(function () { return {}; });

                if (!response.ok || !payload.success) {
                    const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                    throw new Error(errors || payload.message || 'Không lưu được cài đặt section.');
                }

                return payload.data;
            }

            /*
             * Queued work goes through the bar's one Save button. Writing it here
             * would give edit mode a second owner, and the two would disagree about
             * what is still pending the moment either one failed.
             */
            const saver = {
                pending: function () {
                    return queued.size > 0;
                },
                save: async function () {
                    for (const [wrapper, state] of Array.from(queued.entries())) {
                        const data = await send('PATCH', {
                            list_key: state.listKey,
                            section: state.section,
                            variant: state.variant || null,
                            settings: state.values,
                            is_visible: !state.hidden,
                        });

                        // The server's answer, not the guess: a value it refused or
                        // normalised has to be what the admin ends up looking at.
                        adopt(wrapper, state.section, data);
                        queued.delete(wrapper);
                        snapshots.delete(wrapper);
                    }
                },
                revert: function () {
                    snapshots.forEach(function (snapshot, wrapper) {
                        wrapper.className = snapshot.className;
                        if (snapshot.style === null) {
                            wrapper.removeAttribute('style');
                        } else {
                            wrapper.setAttribute('style', snapshot.style);
                        }
                    });
                    queued.clear();
                    snapshots.clear();
                },
            };

            // Declared above the registration on purpose: `whenBarReady` runs its
            // callback synchronously when the bar is already up, and referencing
            // `saver` before its `const` would be a TDZ error rather than undefined.
            whenBarReady(function (bar) {
                api = bar;
                bar.registerSaver(saver);
            });

            window.Alpine.data('clientSectionPanel', function () {
                return {
                    isOpen: false,
                    listKey: '',
                    section: '',
                    label: '',
                    variants: [],
                    settings: [],
                    variant: '',
                    values: {},
                    hidden: false,

                    init: function () {
                        const component = this;

                        whenBarReady(function (bar) {
                            // Leaving edit mode closes the panel: its controls would
                            // still work while the rest of the editor had stood down.
                            bar.on('mode', function (on) {
                                if (!on) component.isOpen = false;
                            });
                        });

                        window.clientSectionPanel = {
                            open: function (wrapper) { component.openFor(wrapper); },
                            close: function () { component.isOpen = false; },
                            /** True when this section has queued, unsaved changes. */
                            isQueued: function (wrapper) { return queued.has(wrapper); },
                        };
                    },

                    openFor: function (wrapper) {
                        if (!api) return;

                        const section = wrapper && wrapper.getAttribute('data-section-name');
                        const entry = section ? schema[section] : null;

                        // A section with nothing to offer never opens an empty form.
                        if (!entry) return;

                        const state = queued.get(wrapper) || storedState(wrapper);

                        this.wrapper = wrapper;
                        this.listKey = wrapper.getAttribute('data-section-list') || '';
                        this.section = section;
                        this.label = entry.label;
                        this.variants = entry.variants;
                        this.settings = entry.settings;
                        this.variant = state.variant || '';
                        this.values = Object.assign({}, state.values);
                        this.hidden = Boolean(state.hidden);
                        this.isOpen = true;
                    },

                    close: function () {
                        this.isOpen = false;
                    },

                    apply: function () {
                        const wrapper = this.wrapper;
                        if (!wrapper || !api) return;

                        // Snapshot once, on the first change, so Huỷ restores the state
                        // the page loaded with rather than the previous keystroke.
                        if (!snapshots.has(wrapper)) {
                            snapshots.set(wrapper, {
                                className: wrapper.className,
                                style: wrapper.getAttribute('style'),
                            });
                        }

                        const state = {
                            listKey: this.listKey,
                            section: this.section,
                            variant: this.variant,
                            values: Object.assign({}, this.values),
                            hidden: this.hidden,
                        };

                        queued.set(wrapper, state);
                        paint(wrapper, this.section, state);
                        api.refreshPending();
                    },

                    /**
                     * Drop every override, server-side, now.
                     *
                     * Immediate rather than queued, matching the region control an
                     * admin already knows: only the server can say what the template
                     * renders once nothing is stored. It is also the only way back for
                     * a section that has since been retired from the registry — this
                     * endpoint asks the registry nothing.
                     */
                    restore: async function () {
                        const wrapper = this.wrapper;
                        if (!wrapper || !api) return;

                        try {
                            const data = await send('DELETE', {
                                list_key: this.listKey,
                                section: this.section,
                            });

                            queued.delete(wrapper);
                            snapshots.delete(wrapper);
                            adopt(wrapper, this.section, data);

                            this.variant = '';
                            this.values = {};
                            this.hidden = false;

                            api.refreshPending();
                            api.status('Đã trả section về mặc định.');
                        } catch (error) {
                            api.status(error.message, true);
                        }
                    },
                };
            });
        });
    </script>
@endif
