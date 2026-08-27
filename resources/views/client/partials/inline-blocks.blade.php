@php
    $inlineBlocksAllowed = (bool) auth()->user()?->canEditClientContent();
@endphp

@if($inlineBlocksAllowed)
    @include('client.partials.media-picker')

    {{--
        Formatting toolbar for the static regions of the storefront.

        Edit mode is not owned here. `client.partials.admin-bar` decides when a
        region opens and holds the pending changes; this file is a view onto that
        state, reached through `window.clientBlocks`. Two owners over the same
        [data-block-key] elements would each believe they knew whether a region
        was open, and the one that guessed wrong would write the other's DOM.

        Consequently nothing typed or formatted here saves on its own: a command
        mutates the region and reports it dirty, exactly as a keystroke does, and
        the change waits for Lưu. Only the three structural actions — restoring a
        region to the theme default, and adding or removing a box — call the
        server immediately, because none of them can be expressed as "the HTML
        this region will have after Lưu".

        Regions opt in from Blade via <x-client::editable> and friends, which emit
        data-block-* only for an authorized admin. Anything rendered from the
        database has no such hook and is edited on its own admin screen.
    --}}
    <style>


        /* Repeatable sections: the remove control rides on the item, the add
           control sits under the list. Both only exist for an authorized admin
           and only show once edit mode is on, so a visitor's page is unchanged
           and an admin browsing their own site cannot delete by mis-click. */
        .client-list-remove,
        .client-list-add {
            display: none;
        }

        body.client-edit-active .client-list-item {
            position: relative !important;
            outline: 1px dashed rgba(8, 127, 91, .5) !important;
            outline-offset: 6px !important;
        }

        body.client-edit-active .client-list-remove {
            align-items: center !important;
            background: #d9480f !important;
            border: 0 !important;
            border-radius: 999px !important;
            box-shadow: 0 6px 16px rgba(15, 23, 42, .3) !important;
            color: #fff !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font-size: 12px !important;
            height: 30px !important;
            justify-content: center !important;
            position: absolute !important;
            right: -10px !important;
            top: -10px !important;
            width: 30px !important;
            z-index: 2147483643 !important;
        }

        body.client-edit-active .client-list-remove:hover {
            background: #b23c0c !important;
        }

        body.client-edit-active .client-list-add {
            align-items: center !important;
            background: #087f5b !important;
            border: 0 !important;
            border-radius: 999px !important;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .25) !important;
            color: #fff !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font: 700 13px/1 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            gap: 8px !important;
            padding: 11px 20px !important;
        }

        body.client-edit-active .client-list-add:hover {
            background: #099268 !important;
        }

        body.client-edit-active .client-list-add:disabled {
            cursor: not-allowed !important;
            opacity: .6 !important;
        }

        /* Marked for deletion, not yet written. Cancel brings it back. */
        body.client-edit-active [data-list-remove-pending] {
            opacity: .4 !important;
            outline: 2px dashed #d9480f !important;
            position: relative !important;
        }

        /* A region an editor took off the page. Gone means gone, including while
           editing — an empty dashed box in every hidden slot is noise. The admin
           bar's "Vùng đã ẩn" toggle brings them back into view when one needs
           restoring, which is the only reason the markup is emitted at all. */
        /* A cleared block is absent for visitors. Keep that same absence while
           an admin merely browses; edit mode's hidden-regions switch can reveal
           it for restoration. */
        .client-block-cleared,
        body.client-edit-active .client-block-cleared {
            display: none !important;
        }

        body.client-edit-active.client-blocks-show-hidden .client-block-cleared {
            display: inline-block !important;
            min-height: 22px !important;
            min-width: 64px !important;
            outline: 1px dashed rgba(217, 72, 15, .5) !important;
        }

        #client-block-toolbar {
            align-items: center !important;
            background: #fff !important;
            border: 1px solid #dbe4ef !important;
            border-radius: 10px !important;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .22) !important;
            color: #243447 !important;
            display: none;
            font: 600 13px/1 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            gap: 2px !important;
            max-width: calc(100vw - 24px) !important;
            padding: 6px !important;
            position: fixed !important;
            z-index: 2147483646 !important;
        }

        #client-block-toolbar.is-visible {
            display: flex !important;
        }

        #client-block-toolbar button {
            align-items: center !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 6px !important;
            color: #334155 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font: inherit !important;
            height: 32px !important;
            justify-content: center !important;
            margin: 0 !important;
            min-width: 32px !important;
            padding: 0 8px !important;
        }

        #client-block-toolbar button:hover,
        #client-block-toolbar button.is-active {
            background: #e8efff !important;
            color: #315fd4 !important;
        }

        #client-block-toolbar button:disabled {
            cursor: not-allowed !important;
            opacity: .38 !important;
        }

        #client-block-toolbar button:disabled:hover {
            background: transparent !important;
            color: #334155 !important;
        }

        #client-block-toolbar button:focus-visible {
            outline: 2px solid #5d87ff !important;
            outline-offset: 1px !important;
        }

        #client-block-toolbar .client-block-toolbar__heading {
            position: relative !important;
        }

        #client-block-toolbar .client-block-toolbar__heading-trigger {
            min-width: 58px !important;
        }

        #client-block-toolbar .client-block-toolbar__heading-menu {
            background: #fff !important;
            border: 1px solid #dbe4ef !important;
            border-radius: 8px !important;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .2) !important;
            display: none;
            left: 0 !important;
            min-width: 138px !important;
            padding: 5px !important;
            position: absolute !important;
            top: calc(100% + 7px) !important;
        }

        #client-block-toolbar .client-block-toolbar__heading.is-open .client-block-toolbar__heading-menu {
            display: block !important;
        }

        #client-block-toolbar .client-block-toolbar__heading-menu button {
            justify-content: flex-start !important;
            padding: 0 9px !important;
            width: 100% !important;
        }

        #client-block-toolbar .client-block-toolbar__divider {
            background: #dbe4ef !important;
            height: 22px !important;
            margin: 0 4px !important;
            width: 1px !important;
        }

        @media (max-width: 520px) {
            #client-block-toolbar {
                gap: 0 !important;
            }

            #client-block-toolbar button {
                min-width: 30px !important;
                padding: 0 7px !important;
            }

            #client-block-toolbar [data-client-block-command="undo"],
            #client-block-toolbar [data-client-block-command="redo"] {
                display: none !important;
            }
        }

        #client-block-hint {
            align-items: center !important;
            background: #1f2937 !important;
            border-radius: 8px !important;
            color: #fff !important;
            display: none;
            font: 600 12px/1 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            gap: 6px !important;
            padding: 6px 10px !important;
            pointer-events: none !important;
            position: absolute !important;
            white-space: nowrap !important;
            z-index: 2147483644 !important;
        }

        #client-block-hint.is-visible {
            display: flex !important;
        }

        #client-block-append {
            align-items: center !important;
            background: #087f5b !important;
            border: 2px solid #fff !important;
            border-radius: 999px !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .35) !important;
            color: #fff !important;
            cursor: pointer !important;
            display: none;
            font: 700 14px/1 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            height: 22px !important;
            justify-content: center !important;
            padding: 0 !important;
            position: absolute !important;
            width: 22px !important;
            z-index: 2147483645 !important;
        }

        #client-block-append.is-visible,
        #client-block-remove.is-visible {
            display: inline-flex !important;
        }

        #client-block-append:hover,
        #client-block-remove:hover {
            transform: scale(1.15) !important;
        }

        #client-block-append:hover {
            background: #099268 !important;
        }

        #client-block-remove {
            align-items: center !important;
            background: #d9480f !important;
            border: 2px solid #fff !important;
            border-radius: 999px !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .35) !important;
            color: #fff !important;
            cursor: pointer !important;
            display: none;
            font: 700 15px/1 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            height: 22px !important;
            justify-content: center !important;
            padding: 0 !important;
            position: absolute !important;
            width: 22px !important;
            z-index: 2147483645 !important;
        }

        #client-block-remove:hover {
            background: #b23c0c !important;
        }

        #client-block-toast {
            background: #1f2937 !important;
            border-radius: 8px !important;
            bottom: 76px !important;
            color: #fff !important;
            display: none;
            font: 600 13px/1.2 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            left: 50% !important;
            padding: 9px 14px !important;
            position: fixed !important;
            transform: translateX(-50%) !important;
            z-index: 2147483645 !important;
        }

        #client-block-toast.is-visible {
            display: block !important;
        }

        #client-block-toast.is-error {
            background: #c92a2a !important;
        }
    </style>

    <div id="client-block-hint" aria-hidden="true"></div>
    {{-- One floating control for the whole page rather than a button per region:
         several hundred extra children would reflow flex and grid containers
         the moment edit mode came on. --}}
    <button type="button" id="client-block-append" title="Thêm ô nội dung ngay dưới" aria-label="Thêm ô nội dung ngay dưới">+</button>
    {{-- Only appears over a box an editor added. An authored region belongs to
         the template and can be hidden, never deleted. --}}
    <button type="button" id="client-block-remove" title="Xóa ô đã thêm" aria-label="Xóa ô đã thêm">×</button>
    <div id="client-block-toast" role="status" aria-live="polite"></div>
    <div id="client-block-toolbar" role="toolbar" aria-label="Định dạng nội dung" aria-hidden="true">
        <button type="button" data-client-block-command="bold" title="Đậm" aria-label="Đậm"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M7 4h6.2a4.3 4.3 0 0 1 1.2 8.4A4.6 4.6 0 0 1 13.6 21H7zm3 2.6v4.6h3.1a2.3 2.3 0 0 0 0-4.6zm0 7.2v4.6h3.5a2.3 2.3 0 0 0 0-4.6z"/></svg></button>
        <button type="button" data-client-block-command="italic" title="Nghiêng" aria-label="Nghiêng"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M10 4h8v2.3h-2.7l-3.1 11.4H15V20H7v-2.3h2.7l3.1-11.4H10z"/></svg></button>
        <button type="button" data-client-block-command="underline" title="Gạch chân" aria-label="Gạch chân"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M7 3h2.4v8.2a2.6 2.6 0 0 0 5.2 0V3H17v8.2a5 5 0 0 1-10 0zM6 20h12v1.8H6z"/></svg></button>
        <button type="button" data-client-block-command="strikeThrough" title="Gạch ngang" aria-label="Gạch ngang"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M3 11h18v1.9H3z"/><path d="M12.4 4c3 0 4.9 1.3 5.4 3.6l-2.3.5c-.3-1.2-1.4-1.9-3.1-1.9-1.8 0-2.9.7-2.9 1.9 0 .7.4 1.2 1.3 1.6H7.4A3.5 3.5 0 0 1 7 8c0-2.4 2.1-4 5.4-4zm-.2 16c-3.3 0-5.4-1.5-5.7-4.1l2.3-.4c.2 1.5 1.5 2.4 3.5 2.4 2 0 3.2-.8 3.2-2.1 0-.8-.4-1.4-1.4-1.8h3.3c.3.5.4 1.1.4 1.8 0 2.6-2.2 4.2-5.6 4.2z"/></svg></button>
        <span class="client-block-toolbar__divider" aria-hidden="true"></span>
        <div class="client-block-toolbar__heading" id="client-block-heading-control">
            <button type="button" class="client-block-toolbar__heading-trigger" id="client-block-heading-trigger" title="Cấp tiêu đề" aria-haspopup="menu" aria-expanded="false">
                <span id="client-block-heading-label">P</span><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="client-block-toolbar__heading-menu" id="client-block-heading-menu" role="menu" aria-label="Chọn cấp tiêu đề">
                <button type="button" data-client-block-format="default" role="menuitem">Mặc định (giao diện)</button>
                <button type="button" data-client-block-format="p" role="menuitem">Đoạn văn</button>
                <button type="button" data-client-block-format="h1" role="menuitem">Tiêu đề 1</button>
                <button type="button" data-client-block-format="h2" role="menuitem">Tiêu đề 2</button>
                <button type="button" data-client-block-format="h3" role="menuitem">Tiêu đề 3</button>
                <button type="button" data-client-block-format="h4" role="menuitem">Tiêu đề 4</button>
                <button type="button" data-client-block-format="h5" role="menuitem">Tiêu đề 5</button>
                <button type="button" data-client-block-format="h6" role="menuitem">Tiêu đề 6</button>
            </div>
        </div>
        <span class="client-block-toolbar__divider" aria-hidden="true"></span>
        <button type="button" data-client-block-command="insertUnorderedList" title="Danh sách chấm" aria-label="Danh sách chấm"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1.4" fill="currentColor" stroke="none"/></svg></button>
        <button type="button" data-client-block-command="insertOrderedList" title="Danh sách số" aria-label="Danh sách số"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 4h1v4M3 12h2.5L3 15.5h2.5M3 17.5h2.2v1.4H3.6v.9h1.6V21H3" stroke-width="1.5"/></svg></button>
        <span class="client-block-toolbar__divider" aria-hidden="true"></span>
        <button type="button" data-client-block-command="createLink" title="Chèn liên kết" aria-label="Chèn liên kết"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7L12.2 19"/></svg></button>
        <button type="button" data-client-block-command="unlink" title="Bỏ liên kết" aria-label="Bỏ liên kết"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 0 1 4 8"/><path d="M8 12h3"/><path d="m2 2 20 20"/></svg></button>
        <button type="button" data-client-block-command="removeFormat" title="Xóa định dạng" aria-label="Xóa định dạng"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5.5 14.5 6-6a2 2 0 0 1 2.8 0l4.2 4.2a2 2 0 0 1 0 2.8l-4 4H8l-2.5-2.5a2 2 0 0 1 0-2.5z"/><path d="M21 20H10"/></svg></button>
        <span class="client-block-toolbar__divider" aria-hidden="true"></span>
        <button type="button" data-client-block-command="undo" title="Hoàn tác" aria-label="Hoàn tác"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 9"/></svg></button>
        <button type="button" data-client-block-command="redo" title="Làm lại" aria-label="Làm lại"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M20.5 13a9 9 0 1 1-2.6-6.4L21 9"/></svg></button>
        <span class="client-block-toolbar__divider" aria-hidden="true"></span>
        {{-- Taking a region off the page and putting the theme's own text back
             are the two things an editor could not do before: an emptied region
             used to be indistinguishable from one that was never touched. --}}
        <button type="button" data-client-block-action="clear" title="Ẩn vùng này khỏi trang" aria-label="Ẩn vùng này khỏi trang"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.9 5.2A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a18.5 18.5 0 0 1-3.2 4.2M6.6 6.6A18.4 18.4 0 0 0 2 12s3.6 7 10 7a10 10 0 0 0 4.3-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg></button>
        <button type="button" data-client-block-action="restore" title="Khôi phục nội dung gốc của giao diện" aria-label="Khôi phục nội dung gốc của giao diện"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 9"/><path d="M12 8v4l3 2"/></svg></button>
        {{-- Only for a box an editor added: an authored region belongs to the
             template and can be hidden, never deleted. --}}
        <button type="button" data-client-block-action="delete" title="Xóa hẳn ô đã thêm" aria-label="Xóa hẳn ô đã thêm"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg></button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const api = window.clientBlocks;
            const toolbar = document.getElementById('client-block-toolbar');
            const toast = document.getElementById('client-block-toast');
            const appendButton = document.getElementById('client-block-append');
            const removeButton = document.getElementById('client-block-remove');
            const headingControl = document.getElementById('client-block-heading-control');
            const headingTrigger = document.getElementById('client-block-heading-trigger');
            const headingLabel = document.getElementById('client-block-heading-label');
            const headingMenu = document.getElementById('client-block-heading-menu');

            // The bar owns edit mode. Without it there is nothing to attach to,
            // and failing loudly here would take the media picker down too.
            if (!api || !toolbar) return;

            let active = null;
            let hovered = null;
            let toastTimer = null;
            let savedSelection = null;

            // --- feedback -----------------------------------------------------
            function showToast(message, isError) {
                toast.textContent = message;
                toast.classList.toggle('is-error', Boolean(isError));
                toast.classList.add('is-visible');
                if (toastTimer) window.clearTimeout(toastTimer);
                toastTimer = window.setTimeout(function () {
                    toast.classList.remove('is-visible');
                }, isError ? 4000 : 1600);
            }

            // --- placement ----------------------------------------------------
            /**
             * Park a floating control against a region.
             *
             * The controls do not agree on a coordinate space: the toolbar is
             * `fixed` and therefore already viewport-relative, while the hint and
             * the add/remove buttons are `absolute` and need page coordinates. One
             * helper added the scroll offset to all of them, which put the toolbar
             * one full scroll distance below where it belonged — invisible on any
             * page long enough to scroll, and perfectly fine on a short one.
             */
            function place(element, node, offset) {
                if (!element) return;

                const rect = element.getBoundingClientRect();
                const isFixed = window.getComputedStyle(node).position === 'fixed';
                const offsetY = isFixed ? 0 : window.scrollY;
                const offsetX = isFixed ? 0 : window.scrollX;

                node.style.top = Math.max(8, rect.top + offsetY - offset) + 'px';
                node.style.left = Math.max(8, rect.left + offsetX) + 'px';
            }

            function positionToolbar() {
                if (!active || !toolbar.classList.contains('is-visible')) return;
                place(active, toolbar, toolbar.offsetHeight + 10);
            }

            function showToolbar(element) {
                active = element;
                toolbar.classList.add('is-visible');
                toolbar.setAttribute('aria-hidden', 'false');
                syncToolbar();
                positionToolbar();
            }

            function hideToolbar() {
                active = null;
                toolbar.classList.remove('is-visible');
                toolbar.setAttribute('aria-hidden', 'true');
                closeHeadingMenu();
            }

            // --- selection ----------------------------------------------------
            // execCommand works on the live selection, and clicking a toolbar
            // button moves focus out of the region. The range is captured before
            // that happens and put back before the command runs.
            function rememberSelection() {
                const selection = window.getSelection();
                if (!selection || selection.rangeCount === 0) return;
                const range = selection.getRangeAt(0);
                if (active && active.contains(range.commonAncestorContainer)) {
                    savedSelection = range.cloneRange();
                }
            }

            function restoreSelection() {
                if (!savedSelection || !active) return false;
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(savedSelection);

                return true;
            }

            // --- state --------------------------------------------------------
            function currentFormat() {
                if (!active) return 'default';
                const format = active.getAttribute('data-block-format');

                return format || 'default';
            }

            function syncToolbar() {
                if (!active) return;

                const isImage = active.getAttribute('data-block-type') === 'image';
                toolbar.querySelectorAll('[data-client-block-command]').forEach(function (button) {
                    // Formatting means nothing on an image region.
                    button.disabled = isImage;
                    const command = button.dataset.clientBlockCommand;
                    let on = false;
                    try {
                        on = !isImage && document.queryCommandState(command);
                    } catch (error) {
                        on = false;
                    }
                    button.classList.toggle('is-active', on);
                });

                if (headingControl) headingControl.hidden = isImage;

                const format = currentFormat();
                if (headingLabel) {
                    headingLabel.textContent = format === 'default' ? 'P' : format.toUpperCase();
                }

                // Deleting a box only applies to one an editor added; an authored
                // region belongs to the template and can be hidden, never removed.
                const deletable = Boolean(active.getAttribute('data-list-item'));
                const deleteButton = toolbar.querySelector('[data-client-block-action="delete"]');
                if (deleteButton) deleteButton.hidden = !deletable;
            }

            function closeHeadingMenu() {
                if (!headingControl) return;
                headingControl.classList.remove('is-open');
                if (headingTrigger) headingTrigger.setAttribute('aria-expanded', 'false');
            }

            // --- commands -----------------------------------------------------
            function runCommand(command) {
                if (!active) return;
                restoreSelection();

                if (command === 'createLink') {
                    const url = window.prompt('Nhập liên kết (http/https hoặc đường dẫn bắt đầu bằng /):', 'https://');
                    if (!url) return;
                    // Same rule the menu builder enforces server-side: never let an
                    // editor plant javascript: or data: behind a link.
                    if (!/^(https?:\/\/|\/)/i.test(url.trim())) {
                        showToast('Liên kết phải bắt đầu bằng http://, https:// hoặc /.', true);
                        return;
                    }
                    document.execCommand('createLink', false, url.trim());
                } else {
                    try {
                        document.execCommand(command, false, null);
                    } catch (error) {
                        showToast('Trình duyệt không hỗ trợ thao tác này.', true);
                        return;
                    }
                }

                // Formatting turns a plain region into markup, so it has to be
                // saved as HTML rather than escaped text.
                active.setAttribute('data-block-type', 'html');
                api.markDirty(active);
                rememberSelection();
                syncToolbar();
            }

            /**
             * Swap the region's wrapper tag.
             *
             * The element is replaced rather than restyled: the saved format has to
             * survive a reload, and the server stores a tag name, not a class.
             */
            function applyFormat(format) {
                if (!active) return;

                const baseTag = active.getAttribute('data-block-base-tag') || 'div';
                const nextTag = format === 'default' ? baseTag : format;
                const replacement = document.createElement(nextTag);

                Array.from(active.attributes).forEach(function (attribute) {
                    replacement.setAttribute(attribute.name, attribute.value);
                });
                replacement.innerHTML = active.innerHTML;

                if (format === 'default') {
                    replacement.removeAttribute('data-block-format');
                } else {
                    replacement.setAttribute('data-block-format', format);
                }
                replacement.setAttribute('data-block-type', 'html');

                ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].forEach(function (level) {
                    replacement.classList.remove('client-content-heading-' + level);
                });
                if (format !== 'default' && format !== 'p') {
                    replacement.classList.add('client-content-heading-' + format);
                }

                active.replaceWith(replacement);
                replacement.setAttribute('contenteditable', 'true');
                replacement.focus();

                // Hand the pending entry over to the new node before marking it
                // dirty, or the bar keeps the detached original queued for save.
                if (api.replaceRegion) api.replaceRegion(active, replacement);
                active = replacement;
                api.markDirty(replacement);
                closeHeadingMenu();
                syncToolbar();
                positionToolbar();
            }

            // --- structural actions -------------------------------------------
            async function request(url, method, payload) {
                const response = await fetch(url, {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': api.csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json().catch(function () { return {}; });

                if (!response.ok || !data.success) {
                    const errors = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                    throw new Error(errors || data.message || 'Không thể thực hiện thao tác.');
                }

                return data;
            }

            async function runAction(action) {
                if (!active) return;
                const key = active.getAttribute('data-block-key');

                if (action === 'clear') {
                    // Emptying is an ordinary content change: an empty value is what
                    // "hidden" means, so it rides the normal Lưu flow.
                    active.innerHTML = '';
                    api.markDirty(active);
                    showToast('Vùng sẽ bị ẩn sau khi bấm Lưu.');
                    return;
                }

                if (action === 'restore') {
                    try {
                        await request(api.urls.restore, 'DELETE', { key: key, content_locale: api.locale });
                        /*
                         * Reload rather than patch the node. Restoring means "show
                         * what the template says", and that text exists only in the
                         * Blade file — neither this script nor the server response
                         * holds it, so re-rendering the page is the only way to put
                         * it back on screen. Without this the region stayed blank
                         * and the action looked like it had failed.
                         */
                        showToast('Đã khôi phục nội dung gốc.');
                        window.location.reload();
                    } catch (error) {
                        showToast(error.message, true);
                    }
                    return;
                }

                if (action === 'delete') {
                    const listKey = active.getAttribute('data-list-key');
                    const itemId = active.getAttribute('data-list-item');
                    if (!listKey || !itemId) return;
                    if (!window.confirm('Xóa hẳn ô nội dung này?')) return;

                    try {
                        await request(api.urls.listItems, 'DELETE', { key: listKey, item: itemId });
                        const removed = active;
                        hideToolbar();
                        removed.remove();
                        showToast('Đã xóa ô nội dung.');
                    } catch (error) {
                        showToast(error.message, true);
                    }
                }
            }

            async function appendBox(element) {
                const listKey = element.getAttribute('data-append-list');
                if (!listKey) return;

                try {
                    await request(api.urls.listItems, 'POST', { key: listKey });
                    // Same reason: the new box's markup comes from the Blade that
                    // owns the region, so only a re-render can produce it.
                    showToast('Đã thêm ô.');
                    window.location.reload();
                } catch (error) {
                    showToast(error.message, true);
                }
            }

            // --- hover controls -----------------------------------------------
            function showHoverControls(element) {
                hovered = element;
                const appendable = Boolean(element.getAttribute('data-append-list'));
                const removable = Boolean(element.getAttribute('data-list-item'));

                appendButton.classList.toggle('is-visible', appendable);
                removeButton.classList.toggle('is-visible', removable);
                if (appendable) place(element, appendButton, -8);
                if (removable) place(element, removeButton, 26);
            }

            function hideHoverControls() {
                hovered = null;
                appendButton.classList.remove('is-visible');
                removeButton.classList.remove('is-visible');
            }

            // --- wiring --------------------------------------------------------
            api.on('open', function (element) {
                if (element.getAttribute('data-block-type') === 'image') {
                    hideToolbar();
                    return;
                }
                showToolbar(element);
            });
            api.on('close', function (element) {
                if (active === element) hideToolbar();
            });
            api.on('mode', function (on) {
                if (!on) {
                    hideToolbar();
                    hideHoverControls();
                }
            });

            // mousedown, not click: the selection still exists at this point.
            toolbar.addEventListener('mousedown', function (event) {
                event.preventDefault();
            });

            toolbar.addEventListener('click', function (event) {
                const command = event.target.closest('[data-client-block-command]');
                if (command) {
                    event.preventDefault();
                    runCommand(command.dataset.clientBlockCommand);
                    return;
                }

                const action = event.target.closest('[data-client-block-action]');
                if (action) {
                    event.preventDefault();
                    runAction(action.dataset.clientBlockAction);
                    return;
                }

                const format = event.target.closest('[data-client-block-format]');
                if (format) {
                    event.preventDefault();
                    applyFormat(format.dataset.clientBlockFormat);
                }
            });

            if (headingTrigger && headingControl) {
                headingTrigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    // The stylesheet reveals the menu via
                    // `.client-block-toolbar__heading.is-open .…__heading-menu`,
                    // so the class belongs on the wrapper. Putting it on the menu
                    // itself matched no rule and the menu never opened.
                    const open = headingControl.classList.toggle('is-open');
                    headingTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            }

            document.addEventListener('selectionchange', function () {
                if (!active) return;
                rememberSelection();
                syncToolbar();
            });

            document.addEventListener('mouseover', function (event) {
                if (!api.isEditing()) return;
                const region = event.target.closest ? event.target.closest('[data-block-key]') : null;
                region ? showHoverControls(region) : hideHoverControls();
            });

            appendButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (hovered) appendBox(hovered);
            });

            removeButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!hovered) return;
                active = hovered;
                runAction('delete');
            });

            document.addEventListener('click', function (event) {
                if (headingMenu && !event.target.closest('#client-block-heading-control')) {
                    closeHeadingMenu();
                }
            });

            window.addEventListener('scroll', positionToolbar, { passive: true });
            window.addEventListener('resize', positionToolbar);
        });
    </script>
@endif
