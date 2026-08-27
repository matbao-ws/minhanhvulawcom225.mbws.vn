@php
    $inlineOutlineAllowed = (bool) auth()->user()?->canEditClientContent();
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
        #client-outline {
            background: #ffffff !important;
            border-right: 1px solid #e3e8ef !important;
            bottom: 0 !important;
            box-shadow: 6px 0 28px rgba(15, 23, 42, .14) !important;
            color: #172033 !important;
            display: flex !important;
            flex-direction: column !important;
            font: 500 14px/1.5 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif !important;
            left: 0 !important;
            margin: 0 !important;
            max-width: 88vw !important;
            padding: 0 !important;
            position: fixed !important;
            top: 0 !important;
            transform: translateX(-100%) !important;
            transition: transform .18s ease !important;
            width: 330px !important;
            z-index: 2147483644 !important;
        }
        #client-outline.is-open {
            transform: translateX(0) !important;
        }
        #client-outline[hidden] {
            display: none !important;
        }
        #client-outline .client-outline__head {
            align-items: center !important;
            border-bottom: 1px solid #e3e8ef !important;
            display: flex !important;
            gap: 8px !important;
            justify-content: space-between !important;
            padding: 14px 14px 12px !important;
        }
        #client-outline .client-outline__title {
            font-size: 13px !important;
            font-weight: 700 !important;
            letter-spacing: .04em !important;
            margin: 0 !important;
            text-transform: uppercase !important;
        }
        #client-outline .client-outline__close {
            background: #eef1f6 !important;
            border: 0 !important;
            border-radius: 7px !important;
            color: #45536b !important;
            cursor: pointer !important;
            display: inline-flex !important;
            padding: 6px 8px !important;
        }
        #client-outline .client-outline__body {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            padding: 10px !important;
        }
        #client-outline .client-outline__group {
            margin: 0 0 14px !important;
        }
        #client-outline .client-outline__group-name {
            color: #6b7a90 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: .08em !important;
            margin: 0 0 6px !important;
            padding: 0 6px !important;
            text-transform: uppercase !important;
            word-break: break-all !important;
        }
        #client-outline .client-outline__row {
            align-items: center !important;
            border: 1px solid transparent !important;
            border-radius: 8px !important;
            display: flex !important;
            gap: 4px !important;
            margin-bottom: 2px !important;
            padding: 2px !important;
        }
        #client-outline .client-outline__row:hover {
            background: #f2f5f9 !important;
        }
        /* Grip only where dragging is possible, so the affordance never lies. */
        #client-outline .client-outline__grip {
            color: #97a4b8 !important;
            cursor: grab !important;
            flex: 0 0 auto !important;
            font: 700 13px/1 inherit !important;
            letter-spacing: -1px !important;
            padding: 0 2px 0 6px !important;
            user-select: none !important;
        }
        #client-outline .client-outline__row.is-dragging {
            opacity: .45 !important;
        }
        /* Insertion line, not a highlighted row: it has to say "between these two",
           which a filled background cannot. */
        #client-outline .client-outline__row.is-drop-before {
            border-top-color: #5d87ff !important;
        }
        #client-outline .client-outline__row.is-drop-after {
            border-bottom-color: #5d87ff !important;
        }
        #client-outline .client-outline__jump {
            background: transparent !important;
            border: 0 !important;
            border-radius: 6px !important;
            color: #172033 !important;
            cursor: pointer !important;
            display: block !important;
            flex: 1 1 auto !important;
            font: inherit !important;
            font-size: 14px !important;
            min-width: 0 !important;
            overflow: hidden !important;
            padding: 8px !important;
            text-align: left !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        #client-outline .client-outline__section {
            background: #f6f8fc !important;
        }
        #client-outline .client-outline__section > .client-outline__jump {
            font-weight: 700 !important;
        }
        /* Depth is drawn with a rail rather than plain indentation: a child three
           levels down otherwise reads as a sibling of the wrong parent. */
        #client-outline .client-outline__nest {
            border-left: 1px solid #e3e8ef !important;
            margin-left: 9px !important;
            padding-left: 8px !important;
        }
        #client-outline .client-outline__empty {
            color: #6b7a90 !important;
            padding: 10px 8px !important;
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
            #client-outline { transition: none !important; }
            .client-outline-flash { animation: none !important; }
        }
    </style>

    <aside id="client-outline" hidden aria-label="Mục lục vùng nội dung">
        <div class="client-outline__head">
            <h2 class="client-outline__title">Vùng nội dung</h2>
            <button type="button" class="client-outline__close" id="client-outline-close"
                    title="Đóng" aria-label="Đóng"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
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

            function groupName(element) {
                const key = element.getAttribute('data-block-key') || '';
                const parts = key.split('.');
                parts.pop();

                return parts.join('.') || key;
            }

            function jump(element) {
                close();
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                element.classList.remove('client-outline-flash');
                // Reflow so the animation restarts when the same row is clicked twice.
                void element.offsetWidth;
                element.classList.add('client-outline-flash');
                window.setTimeout(function () {
                    element.classList.remove('client-outline-flash');
                }, 1400);
            }

            /** Current on-page order of one list, read from the DOM. */
            function siblingIds(listKey) {
                const escaped = window.CSS && CSS.escape ? CSS.escape(listKey) : listKey;

                return Array.from(document.querySelectorAll(
                    '[data-section-list="' + escaped + '"][data-section-name],'
                    + '[data-list-key="' + escaped + '"][data-list-item]'
                )).map(function (node) {
                    return node.getAttribute('data-section-name') || node.getAttribute('data-list-item');
                });
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
                present.forEach(function (id) {
                    parent.insertBefore(nodes.get(id), anchor);
                });
            }

            async function commitOrder(listKey, order) {
                try {
                    const response = await fetch(reorderUrl, {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': api.csrf,
                        },
                        body: JSON.stringify({ key: listKey, order: order, defaults: order }),
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
                }
            }

            function move(listKey, itemId, delta) {
                const order = siblingIds(listKey);
                const from = order.indexOf(itemId);
                const to = from + delta;
                if (from === -1 || to < 0 || to >= order.length) return;

                order.splice(to, 0, order.splice(from, 1)[0]);
                commitOrder(listKey, order);
            }

            /** Drop `itemId` immediately before or after `targetId` in the same list. */
            function dropBeside(listKey, itemId, targetId, after) {
                const order = siblingIds(listKey);
                const from = order.indexOf(itemId);
                if (from === -1 || itemId === targetId) return;

                order.splice(from, 1);
                const at = order.indexOf(targetId);
                if (at === -1) return;

                order.splice(after ? at + 1 : at, 0, itemId);
                commitOrder(listKey, order);
            }

            const NODE_SELECTOR = '[data-section-list][data-section-name], [data-block-key]';

            function sectionLabel(element) {
                return element.getAttribute('data-section-name') || '';
            }

            /** Attach the up/down pair, scoped to the list that owns this node. */
            function addMoveControls(row, listKey, itemId) {
                [['↑', -1, 'Lên trên'], ['↓', 1, 'Xuống dưới']].forEach(function (spec) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'client-outline__move';
                    button.textContent = spec[0];
                    button.title = spec[2];
                    button.setAttribute('aria-label', spec[2]);
                    button.addEventListener('click', function (event) {
                        event.stopPropagation();
                        move(listKey, itemId, spec[1]);
                    });
                    row.appendChild(button);
                });
            }

            function buildRow(element, isSection) {
                const listKey = isSection
                    ? element.getAttribute('data-section-list')
                    : element.getAttribute('data-list-key');
                const itemId = isSection
                    ? sectionLabel(element)
                    : element.getAttribute('data-list-item');
                const movable = Boolean(listKey && itemId);

                const row = document.createElement('div');
                row.className = 'client-outline__row' + (isSection ? ' client-outline__section' : '');

                /*
                 * Movable only within the list that owns the node — a section among
                 * its siblings, a box among the boxes of its own region. The list key
                 * on the row is what enforces that during a drag: a row refuses a
                 * drop from any other key, so nothing can cross into another parent.
                 */
                if (movable) {
                    row.draggable = true;
                    row.dataset.listKey = listKey;
                    row.dataset.itemId = itemId;

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
                    ? element.getAttribute('data-section-list') + ' › ' + sectionLabel(element)
                    : (element.getAttribute('data-block-key') || '');

                const name = document.createElement('span');
                name.textContent = isSection ? sectionLabel(element) : label(element);
                jumpButton.appendChild(name);

                jumpButton.addEventListener('click', function () { jump(element); });
                row.appendChild(jumpButton);

                // Kept alongside dragging: a drag is unreachable from the keyboard,
                // and these buttons are the whole accessible path to reordering.
                if (movable) addMoveControls(row, listKey, itemId);

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
                    return node.parentElement.closest(NODE_SELECTOR) === scope;
                });
            }

            function renderInto(container, root) {
                directChildren(root).forEach(function (node) {
                    container.appendChild(buildRow(node, node.hasAttribute('data-section-name')));

                    const nest = document.createElement('div');
                    nest.className = 'client-outline__nest';
                    renderInto(nest, node);
                    if (nest.childElementCount > 0) container.appendChild(nest);
                });
            }

            function render() {
                body.innerHTML = '';

                if (document.querySelector(NODE_SELECTOR) === null) {
                    const empty = document.createElement('p');
                    empty.className = 'client-outline__empty';
                    empty.textContent = 'Trang này không có vùng nội dung nào sửa được.';
                    body.appendChild(empty);

                    return;
                }

                renderInto(body, document.body);
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
             * Dragging, wired once on the panel rather than per row, so a re-render
             * cannot leave stale listeners behind.
             *
             * A row only accepts a drop from a row carrying the same list key. That
             * one check is the whole scoping rule: without it a child section could
             * be dropped among another parent's children, which is placement, not
             * ordering.
             */
            let dragging = null;

            function clearDropMarks() {
                body.querySelectorAll('.is-drop-before, .is-drop-after').forEach(function (node) {
                    node.classList.remove('is-drop-before', 'is-drop-after');
                });
            }

            body.addEventListener('dragstart', function (event) {
                const row = event.target.closest('.client-outline__row[draggable="true"]');
                if (!row) return;

                dragging = { listKey: row.dataset.listKey, itemId: row.dataset.itemId };
                row.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                // Firefox will not start a drag without payload.
                event.dataTransfer.setData('text/plain', dragging.itemId);
            });

            body.addEventListener('dragover', function (event) {
                if (!dragging) return;
                const row = event.target.closest('.client-outline__row[draggable="true"]');
                if (!row || row.dataset.listKey !== dragging.listKey) return;

                // preventDefault is what marks this a valid target; withholding it on
                // a foreign list is what makes the cursor refuse the drop.
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';

                const rect = row.getBoundingClientRect();
                const after = event.clientY > rect.top + rect.height / 2;
                clearDropMarks();
                row.classList.add(after ? 'is-drop-after' : 'is-drop-before');
            });

            body.addEventListener('drop', function (event) {
                if (!dragging) return;
                const row = event.target.closest('.client-outline__row[draggable="true"]');
                if (!row || row.dataset.listKey !== dragging.listKey) return;

                event.preventDefault();
                const rect = row.getBoundingClientRect();
                const after = event.clientY > rect.top + rect.height / 2;
                clearDropMarks();
                dropBeside(dragging.listKey, dragging.itemId, row.dataset.itemId, after);
                dragging = null;
            });

            body.addEventListener('dragend', function () {
                dragging = null;
                clearDropMarks();
                body.querySelectorAll('.is-dragging').forEach(function (node) {
                    node.classList.remove('is-dragging');
                });
            });

            toggle.hidden = false;
            toggle.addEventListener('click', function () {
                panel.classList.contains('is-open') ? close() : open();
            });
            document.getElementById('client-outline-close').addEventListener('click', close);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && panel.classList.contains('is-open')) close();
            });
        });
    </script>
@endif
