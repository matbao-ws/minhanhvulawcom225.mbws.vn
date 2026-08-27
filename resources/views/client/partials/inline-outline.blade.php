@php
    $inlineOutlineAllowed = \App\Support\ClientEditorChrome::editorActive();
@endphp

@if($inlineOutlineAllowed)
    {{--
        Outline of every editable region on the page.

        Two jobs: find a region without hunting for it, and move a box within the
        region that owns it. Only the second is a write, and only boxes an editor
        added can move — a region authored in Blade sits where the template puts
        it, and recording that position in the database would be recording the
        layout, which is the page builder this core does not have.

        Built from the DOM rather than from the server, because the DOM is what the
        admin is looking at: a region hidden behind a feature flag or a conditional
        is simply not in the list.
    --}}
    <style>
        /*
         * Dark, because this panel is a tool and not part of the page. On a light
         * storefront a white sidebar reads as content that failed to lay out; a dark
         * one reads as chrome, which is what every editor navigator relies on.
         *
         * Every rule is `!important` and namespaced under the id: theme CSS is
         * imported wholesale and will otherwise win.
         */
        #client-outline {
            background: #23262b !important;
            border-right: 1px solid #34383f !important;
            bottom: 0 !important;
            box-shadow: 6px 0 34px rgba(2, 6, 14, .45) !important;
            color: #e7eaf0 !important;
            display: flex !important;
            flex-direction: column !important;
            font: 500 13px/1.45 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif !important;
            left: 0 !important;
            margin: 0 !important;
            max-width: 88vw !important;
            padding: 0 !important;
            position: fixed !important;
            top: 0 !important;
            transform: translateX(-100%) !important;
            transition: transform .18s ease !important;
            width: 300px !important;
            z-index: 2147483644 !important;
        }
        #client-outline.is-open {
            transform: translateX(0) !important;
        }
        #client-outline[hidden] {
            display: none !important;
        }
        #client-outline * {
            box-sizing: border-box !important;
        }
        #client-outline .client-outline__head {
            align-items: center !important;
            border-bottom: 1px solid #34383f !important;
            display: flex !important;
            gap: 10px !important;
            padding: 12px 12px 11px !important;
        }
        #client-outline .client-outline__title {
            color: #f4f6fa !important;
            flex: 1 1 auto !important;
            font: 600 14px/1.3 inherit !important;
            letter-spacing: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            text-transform: none !important;
            white-space: nowrap !important;
        }
        /* The panel stays open while the admin works. This is its explicit
           minimise control, kept beside the title where it is always reachable. */
        #client-outline .client-outline__minimize {
            background: transparent !important;
            border: 0 !important;
            border-radius: 6px !important;
            color: #98a1b0 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            flex: 0 0 auto !important;
            order: -1 !important;
            padding: 5px !important;
        }
        #client-outline .client-outline__minimize:hover {
            background: #33373d !important;
            color: #f4f6fa !important;
        }
        #client-outline .client-outline__body {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            padding: 8px !important;
            scrollbar-color: #626c78 #1d2025 !important;
            scrollbar-gutter: stable !important;
            scrollbar-width: thin !important;
        }
        #client-outline .client-outline__body::-webkit-scrollbar {
            width: 8px !important;
        }
        #client-outline .client-outline__body::-webkit-scrollbar-track {
            background: #1d2025 !important;
            border-left: 1px solid #292d33 !important;
        }
        #client-outline .client-outline__body::-webkit-scrollbar-thumb {
            background: #626c78 !important;
            border: 2px solid #1d2025 !important;
            border-radius: 999px !important;
            min-height: 36px !important;
        }
        #client-outline .client-outline__body::-webkit-scrollbar-thumb:hover {
            background: #87919e !important;
        }

        /* One entry is a row plus whatever nests under it; both move together. */
        #client-outline .client-outline__entry {
            display: block !important;
        }
        #client-outline .client-outline__row {
            align-items: center !important;
            background: #2c3036 !important;
            border: 1px solid transparent !important;
            border-radius: 6px !important;
            display: flex !important;
            gap: 2px !important;
            margin: 0 0 3px !important;
            min-height: 34px !important;
            padding: 0 4px 0 2px !important;
        }
        #client-outline .client-outline__row:hover {
            background: #34383f !important;
        }
        /* A section row is the structural level and says so by being a shade
           stronger than the regions inside it. */
        #client-outline .client-outline__row.client-outline__section {
            background: #313640 !important;
        }
        #client-outline .client-outline__row.client-outline__section:hover {
            background: #3a4049 !important;
        }

        /* Disclosure triangle. Only on a row that actually has children, so the
           affordance never lies — the same rule the grip follows. */
        #client-outline .client-outline__caret {
            background: transparent !important;
            border: 0 !important;
            border-radius: 4px !important;
            color: #98a1b0 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            flex: 0 0 auto !important;
            padding: 4px !important;
            transform: rotate(0deg) !important;
            transition: transform .12s ease !important;
        }
        #client-outline .client-outline__caret[aria-expanded="true"] {
            transform: rotate(90deg) !important;
        }
        #client-outline .client-outline__caret:hover {
            color: #f4f6fa !important;
        }
        /* Keeps the labels of childless rows aligned with the ones that have a
           triangle; a ragged left edge is most of what makes a tree hard to scan. */
        #client-outline .client-outline__caret-spacer {
            display: inline-block !important;
            flex: 0 0 auto !important;
            height: 1px !important;
            width: 21px !important;
        }

        /* Grip only where dragging is possible, so the affordance never lies. */
        #client-outline .client-outline__grip {
            color: #6f7787 !important;
            cursor: grab !important;
            flex: 0 0 auto !important;
            font: 700 12px/1 inherit !important;
            letter-spacing: -1px !important;
            padding: 0 3px !important;
            user-select: none !important;
        }
        #client-outline .client-outline__row:hover .client-outline__grip {
            color: #aab2c0 !important;
        }

        #client-outline .client-outline__jump {
            background: transparent !important;
            border: 0 !important;
            border-radius: 5px !important;
            color: #e7eaf0 !important;
            cursor: pointer !important;
            display: flex !important;
            flex: 1 1 auto !important;
            font: inherit !important;
            gap: 6px !important;
            min-width: 0 !important;
            padding: 6px 2px !important;
            text-align: left !important;
        }
        /* Kind and name, not one run of text: the type is the thing being scanned
           for and the name is the thing being read once found. */
        #client-outline .client-outline__kind {
            color: #f4f6fa !important;
            flex: 0 0 auto !important;
            font-weight: 700 !important;
        }
        #client-outline .client-outline__name {
            color: #98a1b0 !important;
            flex: 1 1 auto !important;
            min-width: 0 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        #client-outline .client-outline__row.client-outline__section .client-outline__jump {
            font-size: 13px !important;
        }

        /*
         * Tools cluster, right aligned.
         *
         * Quiet until the row is hovered or something inside it takes focus, rather
         * than hidden: a hover-only control is unreachable on a touch screen, and
         * `:focus-within` is what keeps the keyboard path intact.
         */
        #client-outline .client-outline__tools {
            display: flex !important;
            flex: 0 0 auto !important;
            gap: 1px !important;
            opacity: .45 !important;
            transition: opacity .12s ease !important;
        }
        #client-outline .client-outline__row:hover .client-outline__tools,
        #client-outline .client-outline__tools:focus-within {
            opacity: 1 !important;
        }
        #client-outline .client-outline__gear {
            background: transparent !important;
            border: 0 !important;
            border-radius: 5px !important;
            color: #aab2c0 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            flex: 0 0 auto !important;
            font: 600 12px/1 inherit !important;
            justify-content: center !important;
            min-width: 22px !important;
            padding: 5px 4px !important;
        }
        #client-outline .client-outline__gear:hover {
            background: #454b55 !important;
            color: #ffffff !important;
        }
        /* A section with unsaved settings has to say so here: the panel that holds
           them may be closed, and the row is then the only place it shows. */
        #client-outline .client-outline__gear.is-queued {
            color: #fbbf24 !important;
            opacity: 1 !important;
        }

        /* Sortable's own state classes, applied to the entry. */
        #client-outline .client-outline__entry.is-chosen > .client-outline__row {
            background: #3b4a68 !important;
            border-color: #5d87ff !important;
        }
        #client-outline .client-outline__entry.is-ghost > .client-outline__row {
            opacity: .4 !important;
        }
        /* A section travels with its children, so the block being dragged would
           otherwise be a whole subtree tall and cover the rows it is aiming at. */
        #client-outline .client-outline__entry.is-chosen .client-outline__nest,
        #client-outline .client-outline__entry.is-ghost .client-outline__nest {
            display: none !important;
        }

        /* Depth by indentation plus a hairline rail: indentation alone stops being
           legible about three levels down. */
        #client-outline .client-outline__nest {
            border-left: 1px solid #3a3f47 !important;
            margin: 0 0 3px 11px !important;
            padding-left: 7px !important;
        }
        #client-outline .client-outline__nest[hidden] {
            display: none !important;
        }
        #client-outline .client-outline__empty {
            color: #98a1b0 !important;
            padding: 10px 6px !important;
        }

        /* Flash the region that was jumped to; a silent scroll leaves the admin
           guessing which of several similar boxes they landed on. */
        .client-outline-flash {
            animation: client-outline-flash 1.2s ease-out !important;
        }
        @keyframes client-outline-flash {
            0%, 100% { box-shadow: 0 0 0 0 rgba(93, 135, 255, 0); }
            15%      { box-shadow: 0 0 0 4px rgba(93, 135, 255, .55); }
        }
        @media (prefers-reduced-motion: reduce) {
            #client-outline,
            #client-outline .client-outline__caret,
            #client-outline .client-outline__tools { transition: none !important; }
            .client-outline-flash { animation: none !important; }
        }
    </style>

    <aside id="client-outline" hidden aria-label="Mục lục vùng nội dung">
        <div class="client-outline__head">
            <h2 class="client-outline__title">Vùng nội dung</h2>
            <button type="button" class="client-outline__minimize" id="client-outline-minimize"
                    title="Thu nhỏ" aria-label="Thu nhỏ sidebar"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
        </div>
        <div class="client-outline__body" id="client-outline-body"></div>
    </aside>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const api = window.clientBlocks;
            const panel = document.getElementById('client-outline');
            const body = document.getElementById('client-outline-body');
            const toggle = document.getElementById('client-outline-button');

            if (!api || !panel || !body || !toggle) return;

            const reorderUrl = api.urls.reorder;

            function label(element) {
                const key = element.getAttribute('data-block-key') || '';
                const text = (element.textContent || '').trim().replace(/\s+/g, ' ');
                if (text) return text.length > 46 ? text.slice(0, 46) + '…' : text;
                if (element.getAttribute('data-block-type') === 'image') return '(ảnh)';
                // An emptied region has nothing to show, so fall back to its key.
                return '(' + key.split('.').pop() + ')';
            }

            function jump(element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                element.classList.remove('client-outline-flash');
                // Reflow so the animation restarts when the same row is clicked twice.
                void element.offsetWidth;
                element.classList.add('client-outline-flash');
                window.setTimeout(function () {
                    element.classList.remove('client-outline-flash');
                }, 1400);
            }

            /** The live nodes of one list, keyed by their id. */
            function nodesOf(listKey) {
                const map = new Map();
                document.querySelectorAll('[data-section-list][data-section-name], [data-list-key][data-list-item]')
                    .forEach(function (node) {
                        const key = node.getAttribute('data-section-list') || node.getAttribute('data-list-key');
                        if (key !== listKey) return;
                        map.set(node.getAttribute('data-section-name') || node.getAttribute('data-list-item'), node);
                    });

                return map;
            }

            /**
             * Rearrange the page to match the saved order.
             *
             * Inserted before the element that followed the last one rather than
             * appended: appending would drop the whole run at the end of its parent,
             * pushing it past anything the template placed after it.
             */
            function applyOrderToDom(listKey, order) {
                const nodes = nodesOf(listKey);
                const present = order.filter(function (id) { return nodes.has(id); });
                if (present.length < 2) return;

                const last = nodes.get(present[present.length - 1]);
                const parent = last.parentElement;
                if (!parent) return;

                const anchor = last.nextSibling;
                present.forEach(function (id, index) {
                    const node = nodes.get(id);
                    node.style.order = String(index);
                    parent.insertBefore(node, anchor);
                });
            }

            async function commitOrder(listKey, order) {
                try {
                    const listRoot = Array.from(document.querySelectorAll('[data-list-root]')).find(function (node) {
                        return node.getAttribute('data-list-root') === listKey;
                    });
                    if (!listRoot) throw new Error('Không tìm thấy Row sở hữu danh sách Card này.');

                    let defaults;
                    try {
                        defaults = JSON.parse(window.atob(listRoot.getAttribute('data-list-defaults') || ''));
                    } catch (error) {
                        throw new Error('Row Card khai báo thứ tự mặc định không hợp lệ.');
                    }
                    if (!Array.isArray(defaults) || defaults.length === 0) {
                        throw new Error('Row Card chưa khai báo danh sách Card mặc định.');
                    }
                    const response = await fetch(reorderUrl, {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': api.csrf,
                        },
                        body: JSON.stringify({ key: listKey, order: order, defaults: defaults }),
                    });
                    const data = await response.json().catch(function () { return {}; });
                    if (!response.ok || !data.success) {
                        const errors = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                        throw new Error(errors || data.message || 'Không đổi được thứ tự.');
                    }
                    // Move the real nodes as well. Re-rendering the panel from an
                    // unchanged page just snapped the row back to where it started,
                    // which read as "nothing happened".
                    applyOrderToDom(listKey, data.data.order || order);
                    api.status('Đã đổi thứ tự.');
                    render();
                } catch (error) {
                    api.status(error.message, true);
                    // Sortable already moved the row; the page did not. Rebuild, or the
                    // panel keeps showing an order the server refused.
                    render();
                }
            }

            const NODE_SELECTOR = '[data-outline-section], [data-outline-group], [data-section-list][data-section-name], [data-block-key]';

            function sectionLabel(element) {
                return element.getAttribute('data-section-name')
                    || element.getAttribute('data-outline-section')
                    || element.getAttribute('data-outline-group')
                    || '';
            }

            /**
             * What kind of thing a row points at.
             *
             * Shown as a bold prefix ahead of the name. The type is what an admin
             * scans a tree for; the name is what they read once they have found it,
             * and running the two together as one string makes both harder.
             */
            function kindOf(element, isSection) {
                if (element.hasAttribute('data-outline-group')) {
                    const kind = element.getAttribute('data-outline-kind') || 'group';
                    return { row: 'Hàng', card: 'Card', content: 'Nội dung', group: 'Nhóm' }[kind] || 'Nhóm';
                }
                if (isSection) return 'Section';
                if (element.getAttribute('data-block-type') === 'image') return 'Ảnh';

                // A box an editor appended, rather than a region the template authored.
                return element.hasAttribute('data-list-item') ? 'Ô' : 'Text';
            }

            /** The readable name: the section's own label, or the region's text. */
            function nameOf(element, isSection) {
                if (!isSection) return label(element);

                return element.getAttribute('data-outline-label')
                    || element.getAttribute('data-outline-group-label')
                    || element.getAttribute('data-section-label')
                    || sectionLabel(element);
            }

            /**
             * The right-aligned control cluster of a row, created on first use.
             *
             * Lazy so each caller can keep appending to `row` without needing to know
             * whether it is the first one to add a control.
             */
            function toolsOf(row) {
                let tools = row.querySelector(':scope > .client-outline__tools');

                if (!tools) {
                    tools = document.createElement('span');
                    tools.className = 'client-outline__tools';
                    row.appendChild(tools);
                }

                return tools;
            }

            /**
             * A stable id for one row, so the expanded/collapsed state survives the
             * re-render that follows every reorder.
             */
            function rowId(element) {
                if (element.hasAttribute('data-block-key')) {
                    return element.getAttribute('data-block-key');
                }
                if (element.hasAttribute('data-outline-section')) {
                    return 'outline|' + element.getAttribute('data-outline-section');
                }
                if (element.hasAttribute('data-outline-group')) {
                    return 'group|' + element.getAttribute('data-outline-group');
                }

                return element.getAttribute('data-section-list') + '|' + sectionLabel(element);
            }

            /*
             * Expanded rows, by id. Top-level sections start open so their semantic
             * Row/Card structure is immediately visible; deeper Card/Content groups
             * remain folded so a large page is still scannable.
             */
            const expanded = new Set();
            const collapsed = new Set();

            /** The control that opens the saved history of one row. */
            function buildHistory(element) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'client-outline__gear';
                button.title = 'Lịch sử phiên bản';
                button.setAttribute('aria-label', 'Lịch sử phiên bản');
                button.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    + '<path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 9"/><path d="M12 8v4l3 2"/>'
                    + '</svg>';

                button.addEventListener('click', function (event) {
                    event.stopPropagation();

                    if (!window.clientHistoryPanel) {
                        api.status('Bảng lịch sử không có trên trang này.', true);

                        return;
                    }

                    window.clientHistoryPanel.open(element);
                });

                return button;
            }

            /** The control that opens the appearance panel for one section. */
            function buildGear(element) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'client-outline__gear';
                button.title = 'Cài đặt hiển thị';
                button.setAttribute('aria-label', 'Cài đặt hiển thị');
                // Inline SVG, never an icon font: a storefront may load none.
                button.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">'
                    + '<circle cx="12" cy="12" r="3"/>'
                    + '<path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>'
                    + '</svg>';

                if (window.clientSectionPanel && window.clientSectionPanel.isQueued(element)) {
                    button.classList.add('is-queued');
                }

                button.addEventListener('click', function (event) {
                    event.stopPropagation();

                    // Absent when the panel partial is not on the page — a theme
                    // layout may include the bar and not this. Say so rather than
                    // failing silently on a click.
                    if (!window.clientSectionPanel) {
                        api.status('Bảng cài đặt section không có trên trang này.', true);

                        return;
                    }

                    window.clientSectionPanel.open(element);
                });

                return button;
            }

            function buildRow(element, isSection, listKey, itemId) {
                const movable = Boolean(listKey && itemId);

                const row = document.createElement('div');
                row.className = 'client-outline__row' + (isSection ? ' client-outline__section' : '');

                /*
                 * A placeholder in the triangle's slot, swapped for a real caret by
                 * `renderInto` once it knows whether anything nests under this row.
                 * Keeping the slot either way is what stops the labels of childless
                 * rows from sitting a triangle's width to the left of the rest.
                 */
                const caretSlot = document.createElement('span');
                caretSlot.className = 'client-outline__caret-spacer';
                caretSlot.setAttribute('aria-hidden', 'true');
                row.appendChild(caretSlot);

                /*
                 * The grip is the only thing the row still carries for dragging. The
                 * list key lives on the entry wrapper instead, because that wrapper is
                 * what Sortable moves and what enforces the scoping rule.
                 */
                if (movable) {
                    const grip = document.createElement('span');
                    grip.className = 'client-outline__grip';
                    grip.textContent = '⠿';
                    grip.setAttribute('aria-hidden', 'true');
                    row.appendChild(grip);
                }

                const jumpButton = document.createElement('button');
                jumpButton.type = 'button';
                jumpButton.className = 'client-outline__jump';
                jumpButton.title = isSection
                    ? nameOf(element, true)
                    : (element.getAttribute('data-block-key') || '');

                const kind = document.createElement('span');
                kind.className = 'client-outline__kind';
                kind.textContent = kindOf(element, isSection);
                jumpButton.appendChild(kind);

                const name = document.createElement('span');
                name.className = 'client-outline__name';
                name.textContent = nameOf(element, isSection);
                jumpButton.appendChild(name);

                jumpButton.addEventListener('click', function () { jump(element); });
                row.appendChild(jumpButton);

                /*
                 * Settings live behind one control on the row, not on the page.
                 * Rendering a gear over every section the moment edit mode came on
                 * would add a positioned child to every flex and grid container on
                 * the page and reflow all of them — the same reason the "+" and "×"
                 * are single floating controls rather than one per region.
                 */
                if (element.hasAttribute('data-section-configurable')) {
                    toolsOf(row).appendChild(buildGear(element));
                }

                /*
                 * History, for anything that has storage behind it: a region, or a
                 * section whose appearance can be overridden. A row with neither is
                 * placed entirely by the template and has nothing to roll back to.
                 */
                if (element.hasAttribute('data-block-key') || element.hasAttribute('data-section-configurable')) {
                    toolsOf(row).appendChild(buildHistory(element));
                }

                return row;
            }

            /**
             * The matching nodes directly under `root`, skipping any plain markup
             * in between.
             *
             * Nesting cannot be derived from the keys — a child list is named
             * whatever the template chose. What the page actually contains is the
             * only reliable source, and it is also what the admin is looking at.
             */
            function directChildren(root) {
                const scope = root === document.body ? null : root;

                return Array.from(root.querySelectorAll(NODE_SELECTOR)).filter(function (node) {
                    if (node.closest('[data-outline-ignore]')) return false;

                    return node.parentElement.closest(NODE_SELECTOR) === scope;
                }).sort(function (left, right) {
                    if (left.parentElement !== right.parentElement) return 0;
                    const leftOrder = Number.parseInt(window.getComputedStyle(left).order, 10) || 0;
                    const rightOrder = Number.parseInt(window.getComputedStyle(right).order, 10) || 0;
                    return leftOrder - rightOrder;
                });
            }

            /**
             * One row plus everything nested under it, as a single movable unit.
             *
             * The nest used to be a sibling of its row. Sortable moves one element,
             * so a section dragged past its neighbour would have left its children
             * standing under whichever row happened to land above them. Wrapping the
             * pair is what keeps a subtree attached to the row that owns it.
             *
             * `depth` names the class this container's entries answer to. A parent
             * container must not recognise a child container's entries as its own, or
             * its instance would claim a grip belonging to a nested list.
             */
            function renderInto(container, root, depth) {
                const entryClass = 'client-outline__entry--d' + depth;

                directChildren(root).forEach(function (node) {
                    const isSection = node.hasAttribute('data-section-name')
                        || node.hasAttribute('data-outline-section')
                        || node.hasAttribute('data-outline-group');
                    const storedSection = node.hasAttribute('data-section-name');
                    const listKey = storedSection
                        ? node.getAttribute('data-section-list')
                        : node.getAttribute('data-list-key');
                    const itemId = storedSection
                        ? sectionLabel(node)
                        : node.getAttribute('data-list-item');

                    const entry = document.createElement('div');
                    entry.className = 'client-outline__entry ' + entryClass;
                    if (listKey && itemId) {
                        entry.dataset.listKey = listKey;
                        entry.dataset.itemId = itemId;
                    }
                    const row = buildRow(node, isSection, listKey, itemId);
                    entry.appendChild(row);

                    const nest = document.createElement('div');
                    nest.className = 'client-outline__nest';
                    renderInto(nest, node, depth + 1);
                    if (nest.childElementCount > 0) {
                        const id = rowId(node);
                        const caret = document.createElement('button');
                        const open = depth === 0 ? !collapsed.has(id) : expanded.has(id);

                        caret.type = 'button';
                        caret.className = 'client-outline__caret';
                        caret.title = open ? 'Thu gọn' : 'Mở rộng';
                        caret.setAttribute('aria-label', caret.title);
                        caret.setAttribute('aria-expanded', String(open));
                        caret.innerHTML = '<svg viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>';

                        const spacer = row.querySelector(':scope > .client-outline__caret-spacer');
                        spacer.replaceWith(caret);
                        nest.hidden = !open;

                        caret.addEventListener('click', function (event) {
                            event.stopPropagation();
                            const isOpen = caret.getAttribute('aria-expanded') === 'true';

                            caret.setAttribute('aria-expanded', String(!isOpen));
                            caret.title = isOpen ? 'Mở rộng' : 'Thu gọn';
                            caret.setAttribute('aria-label', caret.title);
                            nest.hidden = isOpen;

                            if (isOpen) {
                                expanded.delete(id);
                                collapsed.add(id);
                            } else {
                                collapsed.delete(id);
                                expanded.add(id);
                            }
                        });

                        entry.appendChild(nest);
                    }

                    container.appendChild(entry);
                });

                wireSortable(container, entryClass);
            }

            function render() {
                destroySortables();
                body.innerHTML = '';

                if (document.querySelector(NODE_SELECTOR) === null) {
                    const empty = document.createElement('p');
                    empty.className = 'client-outline__empty';
                    empty.textContent = 'Trang này không có vùng nội dung nào sửa được.';
                    body.appendChild(empty);

                    return;
                }

                renderInto(body, document.body, 0);
            }

            function open() {
                render();
                panel.hidden = false;
                // Next frame, so the transform transition actually runs.
                window.requestAnimationFrame(function () {
                    panel.classList.add('is-open');
                });
                toggle.classList.add('is-active');
                toggle.setAttribute('aria-expanded', 'true');
            }

            function close() {
                panel.classList.remove('is-open');
                toggle.classList.remove('is-active');
                toggle.setAttribute('aria-expanded', 'false');
                window.setTimeout(function () {
                    if (!panel.classList.contains('is-open')) panel.hidden = true;
                }, 200);
            }

            /*
             * Dragging.
             *
             * A row may only move among the rows of the list that owns it — a section
             * among its siblings, a box among the boxes of its own region. Three
             * independent things enforce that, because getting it wrong turns ordering
             * into placement:
             *
             *   1. `draggable` is scoped to this container's own depth class, so a
             *      parent instance cannot pick up an entry belonging to a nested list.
             *   2. `onMove` refuses to step over an entry carrying a different list key.
             *   3. `SiteListService::reorder()` rejects anything that is not a
             *      permutation of what the list currently holds, so a bug here is a 422
             *      rather than a silently rearranged page.
             *
             * Instances are per container and rebuilt on every render. They are
             * destroyed first: a rebuilt panel would otherwise leave one bound to
             * detached nodes for every time the admin opened it.
             */
            const sortables = [];

            function destroySortables() {
                while (sortables.length) sortables.pop().destroy();
            }

            function wireSortable(container, entryClass) {
                // Absent only if the script failed to load. Jumping to content and
                // the other row tools still work even though dragging is unavailable.
                if (!window.Sortable) return;
                if (!container.querySelector('.' + entryClass + '[data-list-key]')) return;

                sortables.push(new window.Sortable(container, {
                    animation: 140,
                    draggable: '.' + entryClass + '[data-list-key]',
                    // Grip only. Dragging the whole row turns "select this text" into
                    // "move this section", which is the complaint every outline panel
                    // that skips this gets.
                    handle: '.client-outline__grip',
                    // Nothing leaves this list and nothing joins it. The only legal
                    // change is the order of what is already here.
                    group: { name: 'client-outline', pull: false, put: false },
                    chosenClass: 'is-chosen',
                    ghostClass: 'is-ghost',
                    onMove: function (event) {
                        return Boolean(event.related.dataset.listKey)
                            && event.related.dataset.listKey === event.dragged.dataset.listKey;
                    },
                    onEnd: function (event) {
                        const listKey = event.item.dataset.listKey;
                        if (!listKey || event.oldIndex === event.newIndex) return;

                        // Read back from the panel, keeping only this list's entries:
                        // a container may also hold a foreign region's rows, and they
                        // are not part of this list's order.
                        const order = Array.from(container.children)
                            .filter(function (node) { return node.dataset.listKey === listKey; })
                            .map(function (node) { return node.dataset.itemId; });

                        commitOrder(listKey, order);
                    },
                }));
            }

            toggle.hidden = false;
            toggle.addEventListener('click', function () {
                panel.classList.contains('is-open') ? close() : open();
            });
            document.getElementById('client-outline-minimize').addEventListener('click', close);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && panel.classList.contains('is-open')) close();
            });
        });
    </script>
@endif
