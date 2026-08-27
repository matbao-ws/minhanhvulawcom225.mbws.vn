@php
    // Same gate as the editable root in the page view; see User::canEditClientContent().
    $showClientAdminBar = (bool) auth()->user()?->canEditClientContent();
@endphp

{{--
    Convention for future dynamic (DB-driven) blocks on a CMS page: server-render
    the block with `contenteditable="false"` already set on its wrapper. A
    `contenteditable="false"` node nested inside the page's editable root is
    respected by the browser and stays out of the editable region — no extra JS
    needed. Known caveat: in some Chromium versions, pressing Backspace/Delete
    with the caret right next to such a node can delete the node itself instead
    of just skipping over it. Not an issue today (no dynamic blocks exist yet),
    but worth knowing before relying on it for anything destructive-sensitive.
--}}

@if($showClientAdminBar)
    {{-- Same face the admin panel loads, so the editor chrome reads as one product
         whichever side of the site the admin is on. Injected here rather than
         pushed to a stack: a replacement theme layout may not declare one, and
         this partial has to keep working when it does not. Only an authorized
         admin ever reaches this markup, so a visitor pays nothing for it. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        #client-admin-bar {
            align-items: center !important;
            background: #1f2937 !important;
            bottom: 18px !important;
            border: 1px solid rgba(255, 255, 255, .16) !important;
            border-radius: 12px !important;
            box-shadow: 0 12px 35px rgba(0, 0, 0, .28) !important;
            color: #fff !important;
            display: flex !important;
            font: 600 14px/1.2 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            gap: 6px !important;
            left: 50% !important;
            margin: 0 !important;
            max-width: calc(100vw - 24px) !important;
            padding: 7px !important;
            position: fixed !important;
            transform: translateX(-50%) !important;
            width: max-content !important;
            z-index: 2147483645 !important;
        }
        #client-admin-bar a,
        #client-admin-bar button {
            align-items: center !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 8px !important;
            color: #fff !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font: inherit !important;
            gap: 6px !important;
            margin: 0 !important;
            padding: 9px 12px !important;
            text-decoration: none !important;
            white-space: nowrap !important;
        }
        #client-admin-bar a:hover,
        #client-admin-bar button:hover {
            background: rgba(255, 255, 255, .12) !important;
        }
        #client-admin-bar .client-admin-bar__edit {
            background: #5d87ff !important;
        }
        #client-admin-bar .client-admin-bar__edit.is-active {
            background: #087f5b !important;
        }
        #client-admin-bar .client-admin-bar__status {
            color: #dce7f3 !important;
            font-weight: 400 !important;
            padding: 0 6px !important;
        }
        #client-admin-bar .client-admin-bar__save {
            background: #087f5b !important;
        }
        #client-admin-bar .client-admin-bar__save:hover {
            background: #099268 !important;
        }
        #client-admin-bar .client-admin-bar__cancel {
            background: #d9480f !important;
        }
        #client-admin-bar .client-admin-bar__cancel:hover {
            background: #e8590c !important;
        }
        /* Collapsed, the bar leaves a single puck in the corner. An admin browsing
           their own site should not have a toolbar sitting over the footer. */
        #client-admin-bar.is-collapsed {
            display: none !important;
        }
        #client-admin-bar-restore {
            align-items: center !important;
            background: #1f2937 !important;
            border: 1px solid rgba(255, 255, 255, .16) !important;
            border-radius: 999px !important;
            bottom: 18px !important;
            box-shadow: 0 12px 35px rgba(0, 0, 0, .28) !important;
            color: #fff !important;
            cursor: pointer !important;
            display: inline-flex !important;
            height: 44px !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
            position: fixed !important;
            right: 18px !important;
            width: 44px !important;
            z-index: 2147483645 !important;
        }
        /* Same mechanism the Save and Cancel buttons already use here, rather than
           a second one: the display rule above is !important and would otherwise
           beat the user agent's [hidden] { display: none }. */
        #client-admin-bar-restore[hidden] {
            display: none !important;
        }
        #client-admin-bar-restore:hover {
            background: #374151 !important;
        }
        /* Unsaved work is invisible while collapsed, so the puck has to carry the
           warning itself — otherwise closing the tab silently drops the edits. */
        #client-admin-bar-restore.is-pending {
            background: #d9480f !important;
        }
        #client-admin-bar-restore.is-pending::after {
            background: #ffd8a8 !important;
            border-radius: 999px !important;
            content: '' !important;
            height: 9px !important;
            position: absolute !important;
            right: 6px !important;
            top: 6px !important;
            width: 9px !important;
        }
        #client-admin-bar .client-admin-bar__outline.is-active {
            background: #5d87ff !important;
        }
        #client-admin-bar .client-admin-bar__collapse {
            background: rgba(255, 255, 255, .10) !important;
            padding: 9px 10px !important;
        }
        #client-admin-bar .client-admin-bar__hidden {
            background: #495057 !important;
        }
        #client-admin-bar .client-admin-bar__hidden.is-active {
            background: #d9480f !important;
        }
        /*
         * The rules above set display with !important, and an author
         * !important beats the user agent's [hidden] { display: none }. Without
         * this the conditional buttons ship visible on every page.
         */
        #client-admin-bar [hidden] {
            display: none !important;
        }
        #client-admin-bar form {
            display: inline !important;
            margin: 0 !important;
        }
        @media (max-width: 520px) {
            #client-admin-bar .client-admin-bar__label {
                display: none !important;
            }
        }
    </style>

    <nav id="client-admin-bar" aria-label="Công cụ quản trị">
        <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}">
            <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H2a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 3.6 9a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H8a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
            <span class="client-admin-bar__label">Quản trị</span>
        </a>

        @isset($page)
            <a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page]) }}">
                <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/></svg>
                <span class="client-admin-bar__label">Sửa tiêu đề/SEO</span>
            </a>
        @endisset

        {{-- Edit mode serves both shapes: a CMS page body, and the individual
             static regions a template marked with the client editable component. --}}
            <button
                type="button"
                id="client-inline-edit-button"
                class="client-admin-bar__edit"
                hidden
            >
                <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                <span class="client-admin-bar__label" id="client-inline-edit-label">Sửa trực tiếp</span>
            </button>
            {{-- Save and Cancel only appear once something is actually pending. --}}
            <button type="button" id="client-inline-save-button" class="client-admin-bar__save" hidden>
                <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                <span class="client-admin-bar__label">Lưu</span>
            </button>
            <button type="button" id="client-inline-cancel-button" class="client-admin-bar__cancel" hidden>
                <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 9"/></svg>
                <span class="client-admin-bar__label">Hủy thay đổi</span>
            </button>
            {{-- A hidden region renders nothing at all, so this is the only way
                 back to one. Shown only while editing a page that has some. --}}
            <button type="button" id="client-inline-hidden-button" class="client-admin-bar__hidden" hidden>
                <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="client-admin-bar__label" id="client-inline-hidden-label">Vùng đã ẩn</span>
            </button>

            <span id="client-inline-edit-status" class="client-admin-bar__status" role="status"></span>

        {{-- Wired by client.partials.inline-outline; stays hidden if that partial
             is not on the page, so the bar never shows a dead control. --}}
        <button type="button" id="client-outline-button" class="client-admin-bar__outline" hidden
                title="Mục lục vùng nội dung" aria-label="Mục lục vùng nội dung" aria-expanded="false">
            <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1.4" fill="currentColor" stroke="none"/></svg>
            <span class="client-admin-bar__label">Mục lục</span>
        </button>

        <button type="button" id="client-admin-bar-collapse" class="client-admin-bar__collapse"
                title="Thu gọn thanh công cụ" aria-label="Thu gọn thanh công cụ">
            <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <form method="POST" action="{{ route('admin.logout', ['locale' => app()->getLocale()]) }}">
            @csrf
            <button type="submit">
                <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                <span class="client-admin-bar__label">Đăng xuất</span>
            </button>
        </form>
    </nav>

    <button type="button" id="client-admin-bar-restore" hidden
            title="Mở lại thanh công cụ" aria-label="Mở lại thanh công cụ" aria-expanded="false">
        <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
    </button>

        <style>
            .client-edit-mode {
                cursor: text !important;
            }
            /*
             * Region affordances. Namespaced, high specificity and !important
             * throughout: imported theme CSS will otherwise win and hide them.
             */
            [data-block-key] {
                outline: 1px dashed rgba(93, 135, 255, .55) !important;
                outline-offset: 2px !important;
                cursor: text !important;
                position: relative !important;
            }
            [data-block-key]:hover {
                outline: 2px dashed #5d87ff !important;
            }
            [data-block-key][data-block-type="image"] {
                cursor: pointer !important;
            }
            /* An emptied region renders nothing, so revealing it needs a box of
               its own to aim at. */
            body.client-reveal-hidden .client-block-empty,
            body.client-reveal-hidden .client-block-cleared {
                display: inline-block !important;
                min-height: 22px !important;
                min-width: 64px !important;
                outline: 1px dashed rgba(217, 72, 15, .5) !important;
            }
            [data-block-key][contenteditable="true"] {
                outline: 2px solid #087f5b !important;
                background: rgba(8, 127, 91, .06) !important;
            }
            #client-region-hint {
                background: #1f2937 !important;
                border-radius: 6px !important;
                color: #fff !important;
                font: 600 12px/1 Arial, sans-serif !important;
                padding: 5px 8px !important;
                pointer-events: none !important;
                position: fixed !important;
                white-space: nowrap !important;
                z-index: 2147483644 !important;
            }
            #client-region-hint[hidden] {
                display: none !important;
            }
            .client-edit-mode:hover {
                outline: 2px dashed #5d87ff !important;
                outline-offset: 2px !important;
            }
            .client-edit-mode img {
                cursor: pointer !important;
            }
            .client-edit-mode img:hover {
                outline: 2px dashed #087f5b !important;
                outline-offset: 2px !important;
            }
            .client-edit-mode [contenteditable="false"] {
                cursor: default !important;
                outline: none !important;
            }
            @can('media.view')
            #client-inline-media-picker {
                align-items: center !important;
                background: rgba(10, 18, 28, .72) !important;
                display: none;
                inset: 0 !important;
                justify-content: center !important;
                padding: 24px !important;
                position: fixed !important;
                z-index: 2147483646 !important;
            }
            #client-inline-media-picker.is-open {
                display: flex !important;
            }
            #client-inline-media-dialog {
                background: #fff !important;
                border-radius: 12px !important;
                box-shadow: 0 24px 70px rgba(0, 0, 0, .35) !important;
                color: #243447 !important;
                display: flex !important;
                flex-direction: column !important;
                font: 14px/1.4 Arial, sans-serif !important;
                margin: auto !important;
                max-height: calc(100vh - 48px) !important;
                max-width: 1120px !important;
                overflow: hidden !important;
                width: 100% !important;
            }
            #client-inline-media-head,
            #client-inline-media-tools,
            #client-inline-media-pagination {
                align-items: center !important;
                display: flex !important;
                gap: 10px !important;
                justify-content: space-between !important;
                padding: 14px 18px !important;
            }
            #client-inline-media-head {
                border-bottom: 1px solid #e5e9ef !important;
            }
            #client-inline-media-head h2 {
                color: #243447 !important;
                font: 700 18px/1.2 Arial, sans-serif !important;
                margin: 0 !important;
            }
            #client-inline-media-tools {
                flex-wrap: wrap !important;
                padding-bottom: 4px !important;
            }
            #client-inline-media-tools select,
            #client-inline-media-tools button,
            #client-inline-media-head button,
            #client-inline-media-pagination button {
                background: #fff !important;
                border: 1px solid #cfd7e2 !important;
                border-radius: 7px !important;
                color: #243447 !important;
                cursor: pointer !important;
                font: 600 14px/1.2 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
                padding: 9px 12px !important;
            }
            #client-inline-media-tools .client-inline-media-upload {
                background: #5d87ff !important;
                border-color: #5d87ff !important;
                color: #fff !important;
            }
            #client-inline-media-grid {
                display: grid !important;
                gap: 8px !important;
                grid-auto-rows: 150px !important;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
                min-height: 240px !important;
                overflow: auto !important;
                padding: 14px 18px !important;
            }
            #client-inline-media-grid button {
                background: #fff !important;
                border: 1px solid #d9e0e8 !important;
                border-radius: 9px !important;
                color: #243447 !important;
                cursor: pointer !important;
                display: flex !important;
                flex-direction: column !important;
                min-width: 0 !important;
                overflow: hidden !important;
                padding: 0 !important;
                text-align: left !important;
            }
            #client-inline-media-grid img {
                background: #f4f6f9 !important;
                display: block !important;
                flex: 0 0 92px !important;
                height: 92px !important;
                object-fit: contain !important;
                padding: 4px !important;
                width: 100% !important;
            }
            #client-inline-media-grid span {
                display: block !important;
                font-size: 12px !important;
                overflow: hidden !important;
                padding: 7px 8px 2px !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            #client-inline-media-grid small {
                color: #64748b !important;
                display: block !important;
                font: 11px/1.2 Arial, sans-serif !important;
                overflow: hidden !important;
                padding: 2px 8px 7px !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            #client-inline-media-state {
                color: #64748b !important;
                padding: 30px 18px !important;
                text-align: center !important;
            }
            #client-inline-media-state.is-error {
                color: #c92a2a !important;
            }
            #client-inline-media-pagination {
                border-top: 1px solid #e5e9ef !important;
            }
            #client-inline-media-pagination button:disabled {
                cursor: not-allowed !important;
                opacity: .45 !important;
            }
            @media (max-width: 780px) {
                #client-inline-media-picker {
                    padding: 8px !important;
                }
                #client-inline-media-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                }
            }
            @endcan
        </style>

        @can('media.view')
            <aside id="client-inline-media-picker" aria-hidden="true">
                <div id="client-inline-media-dialog" role="dialog" aria-modal="true" aria-labelledby="client-inline-media-title">
                    <div id="client-inline-media-head">
                        <h2 id="client-inline-media-title">Chọn ảnh thay thế</h2>
                        <button type="button" id="client-inline-media-close" aria-label="Đóng">Đóng</button>
                    </div>
                    <div id="client-inline-media-tools">
                        <select id="client-inline-media-folder" aria-label="Thư mục ảnh">
                            <option value="all">Tất cả thư mục</option>
                            @foreach(app(\App\Services\CloudinaryService::class)->listFolders() as $folder)
                                <option value="{{ $folder }}">{{ ucfirst($folder) }}</option>
                            @endforeach
                        </select>
                        <div>
                            <input type="file" id="client-inline-media-upload" accept="image/*" hidden>
                            <button type="button" class="client-inline-media-upload" id="client-inline-media-upload-button">
                                Thêm ảnh mới
                            </button>
                        </div>
                    </div>
                    <div id="client-inline-media-state">Đang tải thư viện ảnh…</div>
                    <div id="client-inline-media-grid"></div>
                    <div id="client-inline-media-pagination">
                        <button type="button" id="client-inline-media-previous">← Trước</button>
                        <span id="client-inline-media-page">Trang 1</span>
                        <button type="button" id="client-inline-media-next">Sau →</button>
                    </div>
                </div>
            </aside>
        @endcan

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButton = document.getElementById('client-inline-edit-button');
            const toggleLabel = document.getElementById('client-inline-edit-label');
            const saveButton = document.getElementById('client-inline-save-button');
            const cancelButton = document.getElementById('client-inline-cancel-button');
            const statusLabel = document.getElementById('client-inline-edit-status');
            const hiddenButton = document.getElementById('client-inline-hidden-button');
            const hiddenLabel = document.getElementById('client-inline-hidden-label');
            const collapseButton = document.getElementById('client-admin-bar-collapse');
            const restoreButton = document.getElementById('client-admin-bar-restore');
            const COLLAPSE_KEY = 'clientAdminBarCollapsed';

            /*
             * Two shapes of editable content, and a page may carry either:
             *
             *  - pageContent: a CMS page body, edited as one block. The id is
             *    the convention this core ships; the attribute is what a cut
             *    theme can put on its own wrapper.
             *  - regions:     individual static template regions marked with
             *    the client editable component. Each one saves on its own key.
             *
             * Both may legitimately be absent — a fully database-driven page has
             * nothing to edit — so this must never throw; a thrown error here
             * would take the media picker down with it.
             */
            @isset($page)
            const pageContent = document.getElementById(@json('client-page-'.$page->id))
                || document.querySelector('[data-client-editable-root]');
            const saveUrl = @json(route('admin.pages.inline-update', ['locale' => app()->getLocale(), 'page' => $page]));
            @else
            const pageContent = null;
            const saveUrl = null;
            @endisset

            const blockUrl = @json(route('admin.site-blocks.update', ['locale' => app()->getLocale()]));
            // Structural actions the formatting toolbar performs: dropping an
            // override so the theme's own text returns, and adding or removing a
            // box in a repeatable region.
            const blockRestoreUrl = @json(route('admin.site-blocks.restore', ['locale' => app()->getLocale()]));
            const listItemsUrl = @json(route('admin.site-lists.items.store', ['locale' => app()->getLocale()]));
            const listReorderUrl = @json(route('admin.site-lists.items.reorder', ['locale' => app()->getLocale()]));
            const csrfToken = @json(csrf_token());
            const contentLocale = @json(app()->getLocale());

            function regions() {
                return Array.from(document.querySelectorAll('[data-block-key]'));
            }

            // Exposed so a toolbar can ask whether this page offers anything.
            window.clientBlocksCount = function () {
                return regions().length + (pageContent ? 1 : 0);
            };

            /*
             * Wired before the bail-out below. Collapsing is a property of the bar,
             * not of editing: a page with nothing editable still shows Quản trị and
             * Đăng xuất, and the button that tucks them away has to work there too.
             */
            const bar = document.getElementById('client-admin-bar');

            function setCollapsedEarly(collapsed) {
                // Refuse to hide the bar when there is no way to bring it back;
                // stranding an admin with no toolbar is worse than not collapsing.
                if (collapsed && !restoreButton) return;

                bar.classList.toggle('is-collapsed', collapsed);
                if (restoreButton) {
                    restoreButton.hidden = !collapsed;
                    restoreButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                }
                try {
                    window.localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
                } catch (error) {
                    // Private browsing denies storage; the choice just is not kept.
                }
            }

            if (collapseButton) {
                collapseButton.addEventListener('click', function () {
                    setCollapsedEarly(true);
                });
            }

            if (restoreButton) {
                restoreButton.addEventListener('click', function () {
                    setCollapsedEarly(false);
                    if (typeof syncControls === 'function') syncControls();
                });
            }

            try {
                if (window.localStorage.getItem(COLLAPSE_KEY) === '1') setCollapsedEarly(true);
            } catch (error) {
                // No storage, no restored preference. The bar starts open.
            }

            if (window.clientBlocksCount() === 0) return;

            toggleButton.hidden = false;

            let editModeOn = false;
            let activeRegion = null;
            let dirty = false;
            let entryHtml = null;
            /** @type {Map<HTMLElement, {html: string, key: string, type: string}>} */
            const dirtyRegions = new Map();
            let savePromise = Promise.resolve();
            let statusResetTimer = null;

            /*
             * Dirty is driven by real editing events, never by diffing innerHTML
             * against a snapshot. Theme scripts mutate the DOM constantly —
             * sliders clone slides, lazy-loaders rewrite src, scroll libraries
             * add classes — and a diff would treat all of it as an edit and
             * persist the generated markup into the page. Saved once, it is
             * re-cloned on the next load and the page grows every time.
             */
            function markDirty() {
                if (dirty) return;
                dirty = true;
                syncControls();
            }

            /** Remember a region's pre-edit HTML once, so Huỷ can put it back. */
            function markRegionDirty(element) {
                if (!dirtyRegions.has(element)) {
                    dirtyRegions.set(element, {
                        html: element.dataset.blockEntryHtml ?? element.innerHTML,
                        src: element.getAttribute('src'),
                        key: element.getAttribute('data-block-key'),
                        type: element.getAttribute('data-block-type') || 'text',
                    });
                }
                syncControls();
            }

            function clearDirty() {
                dirty = false;
                dirtyRegions.clear();
                syncControls();
            }

            function hasPending() {
                return dirty || dirtyRegions.size > 0;
            }

            /**
             * Regions an admin emptied on purpose.
             *
             * They render nothing, so without a way to reveal them there is no
             * route back: the only handle to "restore the theme text" sits on an
             * element that is not on the page.
             */
            function hiddenRegions() {
                return document.querySelectorAll('.client-block-empty, [data-block-cleared="true"]');
            }

            function syncHiddenToggle() {
                if (!hiddenButton) return;

                const count = editModeOn ? hiddenRegions().length : 0;
                hiddenButton.hidden = count === 0;
                if (hiddenLabel) hiddenLabel.textContent = 'Vùng đã ẩn (' + count + ')';

                if (count === 0) {
                    document.body.classList.remove('client-reveal-hidden');
                    hiddenButton.classList.remove('is-active');
                }
            }

            function syncControls() {
                const pending = editModeOn && hasPending();
                saveButton.hidden = !pending;
                cancelButton.hidden = !pending;
                syncHiddenToggle();
                syncCollapsedState();
            }

            /**
             * Collapse the bar to a puck in the corner.
             *
             * Persisted, because the reason to collapse it — an admin reading their
             * own site — outlives one page. The puck still reports unsaved work: the
             * Save button is out of sight while collapsed, and losing edits to a
             * closed tab because the warning was hidden too would be the worst
             * possible trade for a tidier footer.
             */
            function syncCollapsedState() {
                if (!restoreButton) return;
                restoreButton.classList.toggle('is-pending', hasPending());
            }

            function setStatus(text, sticky) {
                if (statusResetTimer) {
                    window.clearTimeout(statusResetTimer);
                    statusResetTimer = null;
                }
                statusLabel.textContent = text;
                if (text && !sticky) {
                    statusResetTimer = window.setTimeout(function () {
                        statusLabel.textContent = '';
                    }, 2500);
                }
            }

            async function savePageBody() {
                if (!dirty || !pageContent || !saveUrl) return;

                const response = await fetch(saveUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ content_locale: contentLocale, published_html: pageContent.innerHTML }),
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(errorText(payload, 'Không thể lưu trang.'));
                }
                // Render what the server stored, so the admin sees what the
                // sanitizer removed instead of a copy of what they typed.
                pageContent.innerHTML = payload.data.html || '';
                entryHtml = pageContent.innerHTML;
                dirty = false;
            }

            async function saveRegion(element, snapshot) {
                /*
                 * Read the type and format off the element, not off the snapshot.
                 * The snapshot is taken the moment a region is first touched, when
                 * a plain region is still `text`; the formatting toolbar then turns
                 * it into `html`. Sending the stale type made the server strip every
                 * tag back out, so bold and headings looked applied until Lưu threw
                 * them away.
                 */
                const type = element.getAttribute('data-block-type') || snapshot.type;
                const format = element.getAttribute('data-block-format') || null;
                const isImage = type === 'image';
                const value = isImage ? (element.getAttribute('src') || '') : element.innerHTML;

                const response = await fetch(blockUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        key: snapshot.key,
                        type: type,
                        format: format,
                        content_locale: contentLocale,
                        value: value,
                    }),
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(errorText(payload, 'Không thể lưu vùng nội dung.'));
                }

                if (isImage) {
                    if (payload.data.value) element.setAttribute('src', payload.data.value);
                } else {
                    element.innerHTML = payload.data.value ?? '';
                    element.classList.toggle('client-block-empty', Boolean(payload.data.cleared));
                }
                delete element.dataset.blockEntryHtml;
            }

            function errorText(payload, fallback) {
                const errors = payload && payload.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : '';

                return errors || (payload && payload.message) || fallback;
            }

            async function doSave() {
                if (!hasPending()) return;
                setStatus('Đang lưu…', true);

                try {
                    await savePageBody();
                    for (const [element, snapshot] of Array.from(dirtyRegions.entries())) {
                        await saveRegion(element, snapshot);
                        dirtyRegions.delete(element);
                    }
                    syncControls();
                    setStatus('Đã lưu');
                } catch (error) {
                    // Whatever failed stays pending, so Lưu can be retried.
                    syncControls();
                    setStatus(error.message || 'Không thể lưu.', true);
                }
            }

            function requestSave() {
                savePromise = savePromise.then(doSave, doSave);
                return savePromise;
            }

            function cancelEdits() {
                if (!hasPending()) return;

                if (dirty && pageContent && entryHtml !== null) {
                    pageContent.innerHTML = entryHtml;
                }
                dirtyRegions.forEach(function (snapshot, element) {
                    if (snapshot.type === 'image') {
                        if (snapshot.src !== null) element.setAttribute('src', snapshot.src);
                    } else {
                        element.innerHTML = snapshot.html;
                    }
                    closeRegion(element);
                    delete element.dataset.blockEntryHtml;
                });

                clearDirty();
                setStatus('Đã huỷ thay đổi');
            }

            function enableEditMode() {
                editModeOn = true;
                if (pageContent) {
                    entryHtml = pageContent.innerHTML;
                    pageContent.setAttribute('contenteditable', 'true');
                    pageContent.classList.add('client-edit-mode');
                }
                document.body.classList.add('client-edit-active');
                try {
                    document.execCommand('enableObjectResizing', false, false);
                    document.execCommand('defaultParagraphSeparator', false, 'p');
                } catch (error) {
                    // Deprecated APIs — best-effort only, safe to ignore if unsupported.
                }
                toggleButton.classList.add('is-active');
                toggleLabel.textContent = 'Tắt chỉnh sửa';
                syncControls();
                emitBlockEvent('mode', true);
            }

            /** Take a text region out of editing without touching its content. */
            function closeRegion(element) {
                if (element.getAttribute('contenteditable') === 'true') {
                    element.removeAttribute('contenteditable');
                }
                if (activeRegion === element) {
                    activeRegion = null;
                }
                emitBlockEvent('close', element);
            }

            function openRegion(element) {
                if (element.getAttribute('contenteditable') === 'true') return;
                element.dataset.blockEntryHtml = element.innerHTML;
                element.setAttribute('contenteditable', 'true');
                element.focus();
                activeRegion = element;
                emitBlockEvent('open', element);
            }

            /*
             * Leaving edit mode never saves and never discards. Auto-saving here
             * turned "I changed my mind" into a permanent write, and there was
             * no undo.
             */
            function disableEditMode() {
                editModeOn = false;
                if (pageContent) {
                    pageContent.setAttribute('contenteditable', 'false');
                    pageContent.classList.remove('client-edit-mode');
                }
                regions().forEach(closeRegion);
                document.body.classList.remove('client-edit-active');
                hideHint();
                toggleButton.classList.remove('is-active');
                toggleLabel.textContent = 'Sửa trực tiếp';
                activeRegion = null;
                document.body.classList.remove('client-reveal-hidden');
                if (hiddenButton) hiddenButton.classList.remove('is-active');
                const pending = hasPending();
                syncControls();
                setStatus(pending ? 'Còn thay đổi chưa lưu — bật lại để Lưu hoặc Huỷ' : '', pending);
                emitBlockEvent('mode', false);
            }

            // Exposed for a toolbar that wants to drive edit mode itself.
            window.clientBlocksToggle = function (on) {
                if (on === editModeOn) return;
                on ? enableEditMode() : disableEditMode();
            };

            /*
             * The seam the formatting toolbar plugs into.
             *
             * Edit mode has exactly one owner — this file — because two of them
             * over the same [data-block-key] elements would each think they knew
             * whether a region was open. The toolbar is a view onto that state:
             * it listens, mutates the DOM, and reports the region dirty, which is
             * the same path a keystroke takes. Nothing it does writes on its own;
             * a formatting change waits for Lưu like every other edit.
             */
            const blockListeners = { open: [], close: [], mode: [] };

            function emitBlockEvent(name, detail) {
                (blockListeners[name] || []).forEach(function (callback) {
                    try {
                        callback(detail);
                    } catch (error) {
                        // A broken listener must not take edit mode down with it.
                        if (window.console) console.error(error);
                    }
                });
            }

            window.clientBlocks = {
                isEditing: function () {
                    return editModeOn;
                },
                activeRegion: function () {
                    return activeRegion;
                },
                /** Report content the toolbar changed, so Lưu/Huỷ appear. */
                markDirty: function (element) {
                    if (!element) return;
                    element.hasAttribute('data-block-key') ? markRegionDirty(element) : markDirty();
                },
                /*
                 * Changing a heading level swaps the wrapper tag, which means a new
                 * element. The pending map is keyed by element, so without this the
                 * detached original stays queued and Lưu writes a node that is no
                 * longer on the page — losing whatever was typed after the swap.
                 */
                replaceRegion: function (previous, next) {
                    if (!previous || !next) return;
                    const snapshot = dirtyRegions.get(previous);
                    if (snapshot) {
                        dirtyRegions.delete(previous);
                        dirtyRegions.set(next, snapshot);
                    }
                    if (activeRegion === previous) {
                        activeRegion = next;
                    }
                    syncControls();
                },
                /** Push a transient message through the bar's own status slot. */
                status: function (text, sticky) {
                    setStatus(text, sticky);
                },
                on: function (name, callback) {
                    if (blockListeners[name]) blockListeners[name].push(callback);
                },
                locale: contentLocale,
                csrf: csrfToken,
                urls: { block: blockUrl, restore: blockRestoreUrl, listItems: listItemsUrl, reorder: listReorderUrl },
            };

            toggleButton.addEventListener('click', function () {
                editModeOn ? disableEditMode() : enableEditMode();
            });
            saveButton.addEventListener('click', requestSave);
            cancelButton.addEventListener('click', cancelEdits);

            if (hiddenButton) {
                hiddenButton.addEventListener('click', function () {
                    const on = document.body.classList.toggle('client-reveal-hidden');
                    hiddenButton.classList.toggle('is-active', on);
                    setStatus(on
                        ? 'Đang hiện ' + hiddenRegions().length + ' vùng đã ẩn (viền cam). Bấm vào một vùng rồi chọn Khôi phục nội dung gốc.'
                        : '', on);
                });
            }

            ['input', 'paste', 'cut', 'drop'].forEach(function (type) {
                // One delegated listener: attaching per region would add
                // hundreds of listeners on a fully marked-up page.
                document.addEventListener(type, function (event) {
                    if (!editModeOn) return;
                    const region = event.target.closest ? event.target.closest('[data-block-key]') : null;
                    if (region) {
                        markRegionDirty(region);

                        return;
                    }
                    if (pageContent && pageContent.contains(event.target)) markDirty();
                }, true);
            });

            document.addEventListener('keydown', function (event) {
                if (!editModeOn) return;

                if (event.key === 'Escape') {
                    if (typeof isMediaPickerOpen === 'function' && isMediaPickerOpen()) {
                        closeMediaPicker();

                        return;
                    }
                    cancelEdits();

                    return;
                }

                // A plain-text region is one line: Enter confirms it rather than
                // inserting a break the sanitizer would strip anyway.
                const region = event.target.closest ? event.target.closest('[data-block-key]') : null;
                if (event.key === 'Enter' && region && region.getAttribute('data-block-type') === 'text') {
                    event.preventDefault();
                    closeRegion(region);
                }
            });

            window.addEventListener('beforeunload', function (event) {
                if (!hasPending()) return;
                event.preventDefault();
                event.returnValue = '';
            });

            @can('media.view')
            const mediaPicker = document.getElementById('client-inline-media-picker');
            const mediaFolder = document.getElementById('client-inline-media-folder');
            const mediaGrid = document.getElementById('client-inline-media-grid');
            const mediaState = document.getElementById('client-inline-media-state');
            const mediaPrevious = document.getElementById('client-inline-media-previous');
            const mediaNext = document.getElementById('client-inline-media-next');
            const mediaPage = document.getElementById('client-inline-media-page');
            const mediaUpload = document.getElementById('client-inline-media-upload');
            const mediaResourcesUrl = @json(route('admin.media.resources', ['locale' => app()->getLocale()]));
            const mediaUploadUrl = @json(route('admin.media.upload', ['locale' => app()->getLocale()]));
            let selectedImage = null;
            let mediaCursors = [null];
            let mediaPageIndex = 0;
            let mediaNextCursor = null;

            function setMediaState(text, isError) {
                mediaState.textContent = text;
                mediaState.classList.toggle('is-error', Boolean(isError));
                mediaState.style.display = text ? 'block' : 'none';
            }

            function renderMedia(resources, nextCursor) {
                mediaGrid.innerHTML = '';
                mediaNextCursor = nextCursor || null;
                mediaPrevious.disabled = mediaPageIndex === 0;
                mediaNext.disabled = !mediaNextCursor;
                mediaPage.textContent = 'Trang ' + (mediaPageIndex + 1);
                setMediaState(resources.length ? '' : 'Chưa có ảnh trong thư viện.', false);

                resources.forEach(function (resource) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.title = 'Chọn ' + resource.public_id;
                    const image = document.createElement('img');
                    image.src = resource.secure_url;
                    image.alt = resource.public_id;
                    const name = document.createElement('span');
                    name.textContent = resource.public_id.split('/').pop();
                    const dimensions = document.createElement('small');
                    dimensions.textContent = resource.width && resource.height
                        ? resource.width + ' × ' + resource.height + ' px'
                        : (resource.format || 'image').toUpperCase();
                    button.append(image, name, dimensions);
                    button.addEventListener('click', function () {
                        if (!selectedImage) return;
                        const target = selectedImage;
                        // Snapshot before the src changes, so Huỷ restores the
                        // image that was there.
                        if (target.hasAttribute('data-block-key')) markRegionDirty(target);
                        target.setAttribute('src', resource.secure_url);
                        // A stale srcset would keep winning over the new src.
                        target.removeAttribute('srcset');
                        closeMediaPicker();
                        // Waits for Save like every other change.
                        if (!target.hasAttribute('data-block-key')) markDirty();
                    });
                    mediaGrid.appendChild(button);
                });
            }

            async function loadMediaResources() {
                setMediaState('Đang tải thư viện ảnh…', false);
                mediaGrid.innerHTML = '';
                const query = new URLSearchParams({
                    folder: mediaFolder.value,
                    _: Date.now().toString(),
                });
                const cursor = mediaCursors[mediaPageIndex];
                if (cursor) query.set('cursor', cursor);

                try {
                    const response = await fetch(mediaResourcesUrl + '?' + query.toString(), {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error('Không thể tải thư viện ảnh.');
                    const payload = await response.json();
                    renderMedia(payload.resources || [], payload.next_cursor);
                } catch (error) {
                    setMediaState(error.message || 'Không thể tải thư viện ảnh.', true);
                }
            }

            function openMediaPickerFor(img) {
                selectedImage = img;
                mediaPicker.classList.add('is-open');
                mediaPicker.setAttribute('aria-hidden', 'false');
                mediaCursors = [null];
                mediaPageIndex = 0;
                loadMediaResources();
            }

            function closeMediaPicker() {
                mediaPicker.classList.remove('is-open');
                mediaPicker.setAttribute('aria-hidden', 'true');
                selectedImage = null;
            }

            function isMediaPickerOpen() {
                return mediaPicker.classList.contains('is-open');
            }

            document.getElementById('client-inline-media-close').addEventListener('click', closeMediaPicker);
            mediaPicker.addEventListener('mousedown', function (event) {
                // Backdrop only; a mousedown inside the dialog must not close it.
                if (event.target === mediaPicker) closeMediaPicker();
            });
            mediaFolder.addEventListener('change', function () {
                mediaCursors = [null];
                mediaPageIndex = 0;
                loadMediaResources();
            });
            mediaPrevious.addEventListener('click', function () {
                if (mediaPageIndex === 0) return;
                mediaPageIndex--;
                loadMediaResources();
            });
            mediaNext.addEventListener('click', function () {
                if (!mediaNextCursor) return;
                mediaCursors = mediaCursors.slice(0, mediaPageIndex + 1);
                mediaCursors.push(mediaNextCursor);
                mediaPageIndex++;
                loadMediaResources();
            });
            document.getElementById('client-inline-media-upload-button').addEventListener('click', function () {
                mediaUpload.click();
            });
            mediaUpload.addEventListener('change', async function () {
                if (!mediaUpload.files[0]) return;
                setMediaState('Đang tải ảnh lên…', false);
                const formData = new FormData();
                formData.append('file', mediaUpload.files[0]);
                formData.append('folder', mediaFolder.value === 'all' ? 'general' : mediaFolder.value);
                formData.append('image_only', '1');

                try {
                    const response = await fetch(mediaUploadUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                        throw new Error(validationError || payload.message || 'Tải ảnh lên không thành công.');
                    }
                    mediaCursors = [null];
                    mediaPageIndex = 0;
                    await loadMediaResources();
                } catch (error) {
                    setMediaState(error.message || 'Tải ảnh lên không thành công.', true);
                } finally {
                    mediaUpload.value = '';
                }
            });

            interceptClicks(function (img) {
                openMediaPickerFor(img);
            });
            @else
            interceptClicks(null);
            @endcan

            /*
             * Capture phase, on document, is the only placement that works on a
             * real theme. Theme markup wraps content in elements carrying their
             * own handlers — lightboxes, tab switchers, video modals, often as
             * inline onclick — and those run on the way *up*. Listening on the
             * content element would let the theme's modal open on top of the
             * media picker; preventDefault alone does not stop it either.
             * Capturing at the document lets us stop the event before it ever
             * reaches the theme's listeners.
             *
             * @param {?function(HTMLImageElement)} onImage  null when the admin
             *        lacks the media permission: images then behave as plain
             *        text rather than offering a control that would 403.
             */
            function interceptClicks(onImage) {
                document.addEventListener('click', function (event) {
                    if (!editModeOn) return;

                    const region = event.target.closest('[data-block-key]');
                    if (region) {
                        // Let a click inside an open region land so the caret
                        // can be positioned.
                        if (region.getAttribute('contenteditable') === 'true') return;

                        stopEverything(event);
                        if (region.getAttribute('data-block-type') === 'image') {
                            if (onImage) onImage(region);
                        } else {
                            openRegion(region);
                        }

                        return;
                    }

                    if (!pageContent || !pageContent.contains(event.target)) return;

                    // Server-rendered dynamic blocks own their own admin screen.
                    if (event.target.closest('[contenteditable="false"]')) return;

                    const img = onImage ? event.target.closest('img') : null;
                    if (img) {
                        stopEverything(event);
                        onImage(img);

                        return;
                    }

                    // Navigating away mid-edit loses the pending changes, and a
                    // theme link often carries a script handler as well.
                    if (event.target.closest('a')) {
                        stopEverything(event);
                    }
                }, true);
            }

            /*
             * One floating hint positioned from the hovered region's rect. A
             * per-region badge would add hundreds of children and reflow every
             * flex and grid container the moment edit mode came on.
             */
            const hint = document.createElement('div');
            hint.id = 'client-region-hint';
            hint.hidden = true;
            document.body.appendChild(hint);

            function hideHint() {
                hint.hidden = true;
            }

            function showHint(element) {
                // An image region only carries its hook when the admin holds the
                // media permission, so reaching here means the click will work.
                // Plain words, no glyph: this is set through textContent, and an
                // emoji renders as a different picture on every platform.
                hint.textContent = element.getAttribute('data-block-type') === 'image'
                    ? 'Đổi ảnh'
                    : 'Sửa nội dung';
                const rect = element.getBoundingClientRect();
                hint.style.top = Math.max(4, rect.top - 26) + 'px';
                hint.style.left = Math.max(4, rect.left) + 'px';
                hint.hidden = false;
            }

            document.addEventListener('mouseover', function (event) {
                if (!editModeOn) return;
                const region = event.target.closest('[data-block-key]');
                region ? showHint(region) : hideHint();
            });
            window.addEventListener('scroll', hideHint, { passive: true });

            function stopEverything(event) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
            }
        });
        </script>
@endif
