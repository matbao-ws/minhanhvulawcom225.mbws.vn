@php
    $inlineBlocksAllowed = \App\Support\ClientEditorChrome::editorActive();
    // "Sửa với AI" only for an admin the AI layer would serve (plan, switch, ai.use).
    // "Sửa với AI" comes with AI SEO (from Hero 4). Like every AI button, a locked one is shown and
    // explains itself (plan, switched off, or no ai.use); the route is locked again by ai.access.
    $inlineAiAccess = $inlineBlocksAllowed ? app(\App\Support\AiAccess::class)->check(auth()->user()) : null;
    $inlineAiAllowed = (bool) ($inlineAiAccess['allowed'] ?? false);
@endphp

@if($inlineBlocksAllowed)
    @include('core.partials.media-picker')

    {{--
        Formatting toolbar for the static regions of the storefront.

        Edit mode is not owned here. `core.partials.admin-bar` decides when a
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

        body.client-edit-active .client-list-add {
            align-items: center !important;
            background: #087f5b !important;
            border: 0 !important;
            border-radius: 999px !important;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .25) !important;
            color: #fff !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font: 700 13px/1 system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif !important;
            gap: 8px !important;
            margin: 14px auto 0 !important;
            padding: 11px 20px !important;
        }

        body.client-edit-active .client-list-add:hover {
            background: #099268 !important;
        }

        body.client-edit-active .client-list-add:disabled {
            cursor: progress !important;
            opacity: .6 !important;
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
        body.client-edit-active .client-block-cleared,
        body.client-edit-active .client-block-empty {
            display: none !important;
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

        #client-block-toolbar .client-block-toolbar__color {
            position: relative !important;
        }

        #client-block-toolbar .client-block-toolbar__color-trigger {
            flex-direction: column !important;
            gap: 1px !important;
        }

        #client-block-toolbar .client-block-toolbar__color-bar {
            background: #dc2626;
            border-radius: 2px !important;
            display: block !important;
            height: 3px !important;
            width: 15px !important;
        }

        #client-block-toolbar .client-block-toolbar__color-menu {
            background: #fff !important;
            border: 1px solid #dbe4ef !important;
            border-radius: 10px !important;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .2) !important;
            display: none;
            left: 0 !important;
            padding: 10px !important;
            position: absolute !important;
            top: calc(100% + 7px) !important;
            width: 216px !important;
            z-index: 2 !important;
        }

        #client-block-toolbar .client-block-toolbar__color.is-open .client-block-toolbar__color-menu {
            display: block !important;
        }

        #client-block-toolbar .client-block-toolbar__color-title {
            color: #475467 !important;
            font: 700 11px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif !important;
            letter-spacing: .03em !important;
            margin: 0 0 6px !important;
            text-transform: uppercase !important;
        }

        #client-block-toolbar .client-block-toolbar__swatches {
            display: grid !important;
            gap: 6px !important;
            grid-template-columns: repeat(6, 1fr) !important;
            margin-bottom: 10px !important;
        }

        #client-block-toolbar .client-block-toolbar__swatches:empty {
            display: none !important;
        }

        #client-block-toolbar .client-block-toolbar__swatch {
            border: 1px solid rgba(15, 23, 42, .18) !important;
            border-radius: 50% !important;
            height: 26px !important;
            min-width: 0 !important;
            padding: 0 !important;
            width: 26px !important;
        }

        #client-block-toolbar .client-block-toolbar__swatch:hover {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #5d87ff !important;
        }

        #client-block-toolbar .client-block-toolbar__color-actions {
            align-items: center !important;
            border-top: 1px solid #e5eaf1 !important;
            display: flex !important;
            gap: 6px !important;
            justify-content: space-between !important;
            padding-top: 8px !important;
        }

        #client-block-toolbar .client-block-toolbar__color-custom {
            align-items: center !important;
            color: #1d2939 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font: 600 12px/1 system-ui, -apple-system, "Segoe UI", sans-serif !important;
            gap: 6px !important;
            margin: 0 !important;
        }

        #client-block-toolbar .client-block-toolbar__color-custom input {
            background: none !important;
            border: 1px solid #c3cad5 !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            height: 26px !important;
            padding: 1px !important;
            width: 32px !important;
        }

        #client-block-toolbar .client-block-toolbar__color-clear {
            font: 600 12px/1 system-ui, -apple-system, "Segoe UI", sans-serif !important;
            padding: 0 8px !important;
            width: auto !important;
        }

        #client-block-toolbar .client-block-toolbar__menu-host {
            position: relative !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown {
            background: #fff !important;
            border: 1px solid #dbe4ef !important;
            border-radius: 10px !important;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .2) !important;
            display: none;
            left: 0 !important;
            min-width: 210px !important;
            padding: 6px !important;
            position: absolute !important;
            top: calc(100% + 7px) !important;
            z-index: 2 !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown--end {
            left: auto !important;
            right: 0 !important;
        }

        #client-block-toolbar .client-block-toolbar__menu-host.is-open > .client-block-toolbar__dropdown {
            display: block !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown button {
            gap: 8px !important;
            justify-content: flex-start !important;
            padding: 0 10px !important;
            white-space: nowrap !important;
            width: 100% !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown button span {
            font: 600 13px/1 system-ui, -apple-system, "Segoe UI", sans-serif !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown button.is-danger,
        #client-block-toolbar .client-block-toolbar__dropdown button.is-danger:hover {
            color: #c5221f !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown-title {
            color: #475467 !important;
            font: 600 11.5px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif !important;
            max-width: 260px !important;
            padding: 4px 10px 6px !important;
            white-space: normal !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown-title:empty {
            display: none !important;
        }

        #client-block-toolbar .client-block-toolbar__dropdown-divider {
            background: #e5eaf1 !important;
            display: block !important;
            height: 1px !important;
            margin: 5px 4px !important;
        }

        #client-block-toolbar .client-block-toolbar__ai {
            color: #6d4bd8 !important;
            gap: 3px !important;
            padding: 0 8px !important;
            width: auto !important;
        }

        #client-block-toolbar .client-block-toolbar__ai span {
            font: 700 12px/1 system-ui, -apple-system, "Segoe UI", sans-serif !important;
        }

        /* Below the plan that has AI: visible, greyed, and it says why when clicked. */
        #client-block-toolbar .client-block-toolbar__ai.is-locked {
            color: #94a3b8 !important;
        }

        #client-block-toolbar.is-working {
            cursor: progress !important;
            opacity: .7 !important;
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

        #client-block-remove.is-visible {
            display: inline-flex !important;
        }

        #client-block-remove:hover {
            transform: scale(1.15) !important;
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
        {{-- Text colour: any colour, kept as <span style="color"> (HtmlSanitizer::cleanSiteBlock). --}}
        <div class="client-block-toolbar__color" id="client-block-color-control">
            <button type="button" class="client-block-toolbar__color-trigger" id="client-block-color-trigger" title="Màu chữ" aria-label="Màu chữ" aria-haspopup="true" aria-expanded="false">
                <svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 16 6-12 6 12"/><path d="M8.5 11h7"/></svg>
                <span class="client-block-toolbar__color-bar" id="client-block-color-bar"></span>
            </button>
            <div class="client-block-toolbar__color-menu" role="dialog" aria-label="Chọn màu chữ">
                <div class="client-block-toolbar__color-title">Màu chữ</div>
                <div class="client-block-toolbar__swatches">
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#111827" style="background:#111827 !important" title="#111827" aria-label="Màu #111827"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#4b5563" style="background:#4b5563 !important" title="#4b5563" aria-label="Màu #4b5563"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#9ca3af" style="background:#9ca3af !important" title="#9ca3af" aria-label="Màu #9ca3af"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#ffffff" style="background:#ffffff !important" title="#ffffff" aria-label="Màu #ffffff"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#dc2626" style="background:#dc2626 !important" title="#dc2626" aria-label="Màu #dc2626"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#ea580c" style="background:#ea580c !important" title="#ea580c" aria-label="Màu #ea580c"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#ca8a04" style="background:#ca8a04 !important" title="#ca8a04" aria-label="Màu #ca8a04"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#16a34a" style="background:#16a34a !important" title="#16a34a" aria-label="Màu #16a34a"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#0891b2" style="background:#0891b2 !important" title="#0891b2" aria-label="Màu #0891b2"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#2563eb" style="background:#2563eb !important" title="#2563eb" aria-label="Màu #2563eb"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#7c3aed" style="background:#7c3aed !important" title="#7c3aed" aria-label="Màu #7c3aed"></button>
                    <button type="button" class="client-block-toolbar__swatch" data-client-block-color="#db2777" style="background:#db2777 !important" title="#db2777" aria-label="Màu #db2777"></button>
                </div>
                <div class="client-block-toolbar__color-title" id="client-block-color-recent-title" hidden>Màu vừa dùng</div>
                <div class="client-block-toolbar__swatches" id="client-block-color-recent"></div>
                <div class="client-block-toolbar__color-actions">
                    <label class="client-block-toolbar__color-custom" title="Chọn màu bất kỳ">
                        <input type="color" id="client-block-color-input" value="#dc2626" aria-label="Chọn màu khác">
                        <span>Màu khác…</span>
                    </label>
                    <button type="button" class="client-block-toolbar__color-clear" data-client-block-color="">Bỏ màu</button>
                </div>
            </div>
        </div>
        <div class="client-block-toolbar__menu-host" id="client-block-align-control">
            <button type="button" data-client-menu-trigger title="Căn lề" aria-label="Căn lề" aria-haspopup="menu" aria-expanded="false"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" id="client-block-align-icon"><path d="M3 6h18M3 12h12M3 18h16"/></svg></button>
            <div class="client-block-toolbar__dropdown" role="menu" aria-label="Căn lề">
                <button type="button" data-client-block-align="left" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h12M3 18h16"/></svg><span>Căn trái</span></button>
                <button type="button" data-client-block-align="center" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M6 12h12M4 18h16"/></svg><span>Căn giữa</span></button>
                <button type="button" data-client-block-align="right" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 12h12M5 18h16"/></svg><span>Căn phải</span></button>
                <button type="button" data-client-block-align="justify" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg><span>Căn đều hai bên</span></button>
                <button type="button" data-client-block-align="" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 9"/></svg><span>Theo giao diện</span></button>
            </div>
        </div>
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
        @if($inlineAiAllowed)
            <span class="client-block-toolbar__divider" aria-hidden="true"></span>
            <div class="client-block-toolbar__menu-host" id="client-block-ai-control">
                <button type="button" class="client-block-toolbar__ai" data-client-menu-trigger title="Sửa với AI" aria-label="Sửa với AI" aria-haspopup="menu" aria-expanded="false"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 1.7L21.5 17.5l-1.7.8L19 20l-.8-1.7-1.7-.8 1.7-.8z"/></svg><span>AI</span></button>
                <div class="client-block-toolbar__dropdown" role="menu" aria-label="Sửa với AI">
                    <div class="client-block-toolbar__dropdown-title">Sửa với AI · bôi đen một đoạn, hoặc để AI viết lại cả vùng</div>
                    <button type="button" data-client-ai-mode="improve" role="menuitem">Viết hay hơn</button>
                    <button type="button" data-client-ai-mode="shorten" role="menuitem">Ngắn gọn hơn</button>
                    <button type="button" data-client-ai-mode="expand" role="menuitem">Viết dài hơn</button>
                    <button type="button" data-client-ai-mode="seo" role="menuitem">Chuẩn SEO hơn</button>
                    <button type="button" data-client-ai-mode="friendly" role="menuitem">Thân thiện hơn</button>
                    <button type="button" data-client-ai-mode="professional" role="menuitem">Chuyên nghiệp hơn</button>
                </div>
            </div>
        @elseif($inlineAiAccess)
            <span class="client-block-toolbar__divider" aria-hidden="true"></span>
            <button type="button" class="client-block-toolbar__ai is-locked" id="client-block-ai-locked"
                    title="{{ $inlineAiAccess['title'] }}" aria-label="Sửa với AI ({{ $inlineAiAccess['title'] }})" aria-disabled="true"
                    data-client-ai-locked="{{ \App\Support\AiAccess::lockPayload($inlineAiAccess) }}"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><span>AI</span></button>
        @endif
        <span class="client-block-toolbar__divider" aria-hidden="true"></span>
        <button type="button" data-client-block-command="undo" title="Hoàn tác" aria-label="Hoàn tác"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 9"/></svg></button>
        <button type="button" data-client-block-command="redo" title="Làm lại" aria-label="Làm lại"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M20.5 13a9 9 0 1 1-2.6-6.4L21 9"/></svg></button>
        <span class="client-block-toolbar__divider" aria-hidden="true"></span>
        {{-- Less frequent actions. "Nhân bản" copies the smallest thing the template already repeats:
             inside a box of a repeatable region (x-client::list-item) the whole box, which lands
             beside it in the theme's own grid; anywhere else the region, as a sibling with the same
             tag and classes (its `.extra` list). Never an arbitrary element. --}}
        <div class="client-block-toolbar__menu-host" id="client-block-more-control">
            <button type="button" data-client-menu-trigger title="Thao tác khác" aria-label="Thao tác khác" aria-haspopup="menu" aria-expanded="false"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></button>
            <div class="client-block-toolbar__dropdown client-block-toolbar__dropdown--end" role="menu" aria-label="Thao tác khác">
                <div class="client-block-toolbar__dropdown-title" id="client-block-more-title"></div>
                <div id="client-block-copy-actions" hidden>
                    <button type="button" data-client-block-action="duplicate-region" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg><span>Nhân bản đoạn này</span></button>
                    <span class="client-block-toolbar__dropdown-divider" aria-hidden="true"></span>
                </div>
                <div id="client-block-item-actions" hidden>
                    <button type="button" data-client-block-action="duplicate" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg><span data-client-item-noun="Nhân bản">Nhân bản ô này</span></button>
                    <button type="button" data-client-block-action="move-up" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>Chuyển lên</span></button>
                    <button type="button" data-client-block-action="move-down" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M19 12l-7 7-7-7"/></svg><span>Chuyển xuống</span></button>
                    <button type="button" data-client-block-action="delete-item" class="is-danger" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg><span data-client-item-noun="Xoá">Xoá ô này</span></button>
                    <span class="client-block-toolbar__dropdown-divider" aria-hidden="true"></span>
                </div>
                <button type="button" data-client-block-action="clear" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.9 5.2A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a18.5 18.5 0 0 1-3.2 4.2M6.6 6.6A18.4 18.4 0 0 0 2 12s3.6 7 10 7a10 10 0 0 0 4.3-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg><span>Ẩn vùng này khỏi trang</span></button>
                <button type="button" data-client-block-action="restore" role="menuitem"><svg class="client-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 9"/><path d="M12 8v4l3 2"/></svg><span>Khôi phục nội dung gốc</span></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const api = window.clientBlocks;
            const toolbar = document.getElementById('client-block-toolbar');
            const toast = document.getElementById('client-block-toast');
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
            const histories = new Map();

            function snapshotOf(element) {
                return {
                    tag: element.tagName.toLowerCase(),
                    html: element.innerHTML,
                    attributes: Array.from(element.attributes).reduce(function (all, attribute) {
                        if (attribute.name !== 'contenteditable') all[attribute.name] = attribute.value;
                        return all;
                    }, {}),
                };
            }

            function historyOf(element) {
                const key = element.getAttribute('data-block-key');
                if (!histories.has(key)) histories.set(key, { undo: [], redo: [] });
                return histories.get(key);
            }

            function snapshotsMatch(left, right) {
                return JSON.stringify(left) === JSON.stringify(right);
            }

            function pushSnapshot(stack, snapshot) {
                const previous = stack[stack.length - 1];
                if (!previous || !snapshotsMatch(previous, snapshot)) {
                    stack.push(snapshot);
                    if (stack.length > 100) stack.shift();
                }
            }

            function rememberState(element) {
                if (!element) return;
                const history = historyOf(element);
                pushSnapshot(history.undo, snapshotOf(element));
                history.redo = [];
                syncToolbar();
            }

            function commitCommand(element, before) {
                if (!element || snapshotsMatch(before, snapshotOf(element))) return false;
                const history = historyOf(element);
                pushSnapshot(history.undo, before);
                history.redo = [];
                api.markDirty(element);
                syncToolbar();

                return true;
            }

            function restoreState(snapshot) {
                if (!active || !snapshot) return;
                const previous = active;
                const replacement = document.createElement(snapshot.tag);
                Object.keys(snapshot.attributes).forEach(function (name) {
                    replacement.setAttribute(name, snapshot.attributes[name]);
                });
                replacement.innerHTML = snapshot.html;
                replacement.setAttribute('contenteditable', 'true');
                previous.replaceWith(replacement);
                if (api.replaceRegion) api.replaceRegion(previous, replacement);
                active = replacement;
                savedSelection = null;
                replacement.focus();
                api.markDirty(replacement);
                syncToolbar();
                positionToolbar();
            }

            function travelHistory(direction) {
                if (!active) return;
                const history = historyOf(active);
                const source = direction === 'undo' ? history.undo : history.redo;
                const target = direction === 'undo' ? history.redo : history.undo;
                if (source.length === 0) return;
                pushSnapshot(target, snapshotOf(active));
                restoreState(source.pop());
            }

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
                describeActive();
                syncToolbar();
                positionToolbar();
            }

            // --- where am I ---------------------------------------------------
            function regionKind(element) {
                const tag = (element.getAttribute('data-block-format') || element.tagName).toLowerCase();
                if (/^h[1-6]$/.test(tag)) return 'Tiêu đề';
                if (tag === 'ul' || tag === 'ol') return 'Danh sách';
                if (tag === 'a' || tag === 'button' || element.closest('a, button')) return 'Nút';
                if (tag === 'p') return 'Đoạn văn';

                return 'Nội dung';
            }

            function describeActive() {
                const moreTitle = document.getElementById('client-block-more-title');
                const itemActions = document.getElementById('client-block-item-actions');
                if (!active) return;
                const section = active.closest('[data-section-label], [data-section-name]');
                const sectionName = section ? (section.getAttribute('data-section-label') || section.getAttribute('data-section-name')) : '';
                const words = (active.textContent || '').replace(/\s+/g, ' ').trim();
                const quote = words ? ' “' + (words.length > 34 ? words.slice(0, 34) + '…' : words) + '”' : '';
                const item = active.closest('[data-list-item]');
                // A copied region is its own list item: its actions speak of "đoạn", a card's of "ô".
                const isCard = item && item !== active;
                const label = (sectionName ? sectionName + ' › ' : '') + (isCard ? 'Ô › ' : '') + regionKind(active) + quote;
                if (moreTitle) moreTitle.textContent = label;
                if (itemActions) {
                    itemActions.hidden = !item;
                    itemActions.querySelectorAll('[data-client-item-noun]').forEach(function (span) {
                        span.textContent = span.getAttribute('data-client-item-noun') + (isCard ? ' ô này' : ' đoạn này');
                    });
                }
                const copyActions = document.getElementById('client-block-copy-actions');
                if (copyActions) copyActions.hidden = !canCopyRegion(active);
            }

            /**
             * A region outside any repeatable box. One inside a link or button is its label:
             * a second label in the same button is not a copy anyone means.
             */
            function canCopyRegion(region) {
                return Boolean(region
                    && region.getAttribute('data-copy-list')
                    && !region.closest('[data-list-item]')
                    && !region.closest('a, button'));
            }

            /**
             * Put the copy the server just stored on the page, without a reload that would end
             * edit mode. It is the source's own markup with its keys moved to the new item, which
             * is exactly what Blade renders for it: what the source shows now is what was saved.
             * Copies nested inside a card were not copied with it, so they are left out here too.
             */
            function insertCopy(source, from, to, attributes) {
                const copy = source.cloneNode(true);
                Array.from(copy.querySelectorAll('[data-list-item]')).forEach(function (nested) {
                    if (nested !== copy) nested.remove();
                });
                [copy].concat(Array.from(copy.querySelectorAll('*'))).forEach(function (node) {
                    ['data-block-key', 'data-copy-list', 'data-append-list', 'data-list-key'].forEach(function (name) {
                        const value = node.getAttribute(name);
                        if (value && value.indexOf(from) === 0) node.setAttribute(name, to + value.slice(from.length));
                    });
                    // Open-region state belongs to the source.
                    if (node.getAttribute('contenteditable') === 'true') node.removeAttribute('contenteditable');
                    ['data-block-entry-html', 'data-block-entry-tag', 'data-block-entry-class', 'data-block-entry-format'].forEach(function (name) {
                        node.removeAttribute(name);
                    });
                });
                Object.keys(attributes).forEach(function (name) {
                    attributes[name] === null ? copy.removeAttribute(name) : copy.setAttribute(name, attributes[name]);
                });
                // Server-rendered siblings are separated by whitespace; inline ones would otherwise touch.
                source.after(document.createTextNode(' '), copy);
                hideToolbar();
                copy.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

                return copy;
            }

            async function duplicateRegion() {
                const region = active;
                if (!canCopyRegion(region)) return;
                if (api.hasPending()) {
                    showToast('Hãy bấm Lưu (hoặc Huỷ) các thay đổi đang chờ trước.', true);
                    return;
                }
                const type = region.getAttribute('data-block-type') || 'text';
                const copyList = region.getAttribute('data-copy-list');
                try {
                    const json = await request(api.urls.duplicate, 'POST', {
                        key: copyList,
                        item: null,
                        defaults: [],
                        content_locale: api.locale,
                        slots: [{
                            slot: 'text',
                            type: type,
                            value: api.regionValue(region),
                            format: type === 'html' ? (region.getAttribute('data-block-format') || null) : null,
                            align: region.getAttribute('data-block-align') || null,
                        }],
                    });
                    const newId = json.data.item;
                    // A copy of the authored region is the first item of its list.
                    const copy = insertCopy(region, region.getAttribute('data-block-key'), copyList + '.item_' + newId + '.text', {
                        'data-list-key': copyList,
                        'data-list-item': newId,
                        'data-copy-list': null,
                        'data-block-cleared': null,
                    });
                    // As Blade renders it: a copy of an inline region starts its own line.
                    if (['span', 'a', 'strong', 'b', 'em', 'i', 'small', 'label'].indexOf(copy.tagName.toLowerCase()) !== -1) {
                        copy.classList.add('client-copy-line');
                    }
                    showToast('Đã nhân bản đoạn ngay bên dưới.');
                } catch (error) {
                    showToast(error.message, true);
                }
            }

            function hideToolbar() {
                active = null;
                toolbar.classList.remove('is-visible');
                toolbar.setAttribute('aria-hidden', 'true');
                closeHeadingMenu();
                // By id: this can run before the colour control's variables exist.
                const colorMenuHost = document.getElementById('client-block-color-control');
                if (colorMenuHost) colorMenuHost.classList.remove('is-open');
                toolbar.querySelectorAll('.client-block-toolbar__menu-host.is-open').forEach(function (host) {
                    host.classList.remove('is-open');
                });
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
                    const command = button.dataset.clientBlockCommand;
                    if (command === 'undo' || command === 'redo') {
                        const history = isImage ? null : historyOf(active);
                        button.disabled = isImage || history[command].length === 0;
                        button.classList.remove('is-active');
                        return;
                    }
                    button.disabled = isImage;
                    let on = false;
                    if (command === 'insertUnorderedList' || command === 'insertOrderedList') {
                        const wantedTag = command === 'insertOrderedList' ? 'ol' : 'ul';
                        on = !isImage && active.tagName.toLowerCase() === wantedTag;
                    } else {
                        try {
                            on = !isImage && document.queryCommandState(command);
                        } catch (error) {
                            on = false;
                        }
                    }
                    button.classList.toggle('is-active', on);
                });

                if (headingControl) headingControl.hidden = isImage;
                const currentAlign = active.getAttribute('data-block-align') || '';
                toolbar.querySelectorAll('[data-client-block-align]').forEach(function (button) {
                    button.classList.toggle('is-active', button.dataset.clientBlockAlign === currentAlign);
                });

                const format = currentFormat();
                if (headingLabel) {
                    headingLabel.textContent = format === 'default' ? 'P' : format.toUpperCase();
                }

            }

            function closeHeadingMenu() {
                if (!headingControl) return;
                headingControl.classList.remove('is-open');
                if (headingTrigger) headingTrigger.setAttribute('aria-expanded', 'false');
            }

            // --- commands -----------------------------------------------------
            function runCommand(command) {
                if (!active) return;

                if (command === 'undo' || command === 'redo') {
                    travelHistory(command);
                    return;
                }

                if (command === 'insertUnorderedList' || command === 'insertOrderedList') {
                    applyList(command === 'insertOrderedList' ? 'ol' : 'ul');
                    return;
                }

                const before = snapshotOf(active);

                if (command === 'createLink') {
                    const url = window.prompt('Nhập liên kết (http/https hoặc đường dẫn bắt đầu bằng /):', 'https://');
                    if (!url) return;
                    // Same rule the menu builder enforces server-side: never let an
                    // editor plant javascript: or data: behind a link.
                    if (!/^(https?:\/\/|\/)/i.test(url.trim())) {
                        showToast('Liên kết phải bắt đầu bằng http://, https:// hoặc /.', true);
                        return;
                    }
                    if (!restoreSelection()) {
                        showToast('Hãy bôi đen phần nội dung cần gắn liên kết.', true);
                        return;
                    }
                    document.execCommand('createLink', false, url.trim());
                } else {
                    restoreSelection();
                    try {
                        document.execCommand(command, false, null);
                    } catch (error) {
                        showToast('Trình duyệt không hỗ trợ thao tác này.', true);
                        return;
                    }
                }

                // Formatting turns a plain region into markup, so it has to be
                // saved as HTML rather than escaped text.
                if (!commitCommand(active, before)) {
                    syncToolbar();
                    return;
                }
                active.setAttribute('data-block-type', 'html');
                rememberSelection();
                syncToolbar();
            }

            function applyList(listTag) {
                if (!active) return;

                const before = snapshotOf(active);
                const currentTag = active.tagName.toLowerCase();
                const baseTag = active.getAttribute('data-block-base-tag') || 'div';
                const removing = currentTag === listTag;
                const nextTag = removing ? baseTag : listTag;
                const replacement = document.createElement(nextTag);

                Array.from(active.attributes).forEach(function (attribute) {
                    replacement.setAttribute(attribute.name, attribute.value);
                });

                if (removing) {
                    const items = Array.from(active.children).filter(function (child) {
                        return child.tagName.toLowerCase() === 'li';
                    });
                    replacement.innerHTML = items.length
                        ? items.map(function (item) { return item.innerHTML; }).join('<br>')
                        : active.innerHTML;
                    replacement.removeAttribute('data-block-format');
                } else {
                    if (currentTag === 'ul' || currentTag === 'ol') {
                        replacement.innerHTML = active.innerHTML;
                    } else {
                        const content = active.innerHTML.trim();
                        replacement.innerHTML = '<li>' + (content || '<br>') + '</li>';
                    }
                    replacement.setAttribute('data-block-format', listTag);
                }
                replacement.setAttribute('data-block-type', 'html');

                ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].forEach(function (level) {
                    replacement.classList.remove('client-content-heading-' + level);
                });
                replacement.classList.remove('client-content-format-p', 'client-content-list-ul', 'client-content-list-ol');
                if (!removing) replacement.classList.add('client-content-list-' + listTag);

                active.replaceWith(replacement);
                replacement.setAttribute('contenteditable', 'true');
                if (api.replaceRegion) api.replaceRegion(active, replacement);
                active = replacement;
                commitCommand(replacement, before);
                replacement.focus();
                savedSelection = null;
                syncToolbar();
                positionToolbar();
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
                const current = currentFormat();
                if (current === format && active.tagName.toLowerCase() === nextTag) {
                    closeHeadingMenu();
                    return;
                }
                const before = snapshotOf(active);
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
                replacement.classList.remove('client-content-format-p', 'client-content-list-ul', 'client-content-list-ol');
                if (format !== 'default' && format !== 'p') {
                    replacement.classList.add('client-content-heading-' + format);
                } else if (format === 'p') {
                    replacement.classList.add('client-content-format-p');
                }

                active.replaceWith(replacement);
                replacement.setAttribute('contenteditable', 'true');
                replacement.focus();

                // Hand the pending entry over to the new node before marking it
                // dirty, or the bar keeps the detached original queued for save.
                if (api.replaceRegion) api.replaceRegion(active, replacement);
                active = replacement;
                commitCommand(replacement, before);
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

            // --- boxes of a repeatable region ----------------------------------
            function listItemsOf(listKey) {
                return Array.from(document.querySelectorAll('[data-list-key][data-list-item]')).filter(function (node) {
                    return node.getAttribute('data-list-key') === listKey;
                });
            }

            function listDefaults(listKey, items) {
                const add = Array.from(document.querySelectorAll('[data-client-list-add]')).find(function (node) {
                    return node.getAttribute('data-list-key') === listKey;
                });
                try {
                    const stored = add ? JSON.parse(add.getAttribute('data-list-defaults') || '[]') : [];
                    if (stored.length) return stored;
                } catch (error) {
                    // Fall back to what the page shows.
                }

                return items.map(function (node) { return node.getAttribute('data-list-item'); });
            }

            async function runItemAction(action) {
                const item = active ? active.closest('[data-list-item]') : null;
                if (!item) return;
                const listKey = item.getAttribute('data-list-key');
                const itemId = item.getAttribute('data-list-item');
                const items = listItemsOf(listKey);
                const defaults = listDefaults(listKey, items);

                if (action === 'move-up' || action === 'move-down') {
                    const index = items.indexOf(item);
                    const target = action === 'move-up' ? index - 1 : index + 1;
                    if (target < 0 || target >= items.length) {
                        showToast(action === 'move-up' ? 'Ô này đã ở đầu danh sách.' : 'Ô này đã ở cuối danh sách.');
                        return;
                    }
                    const order = items.map(function (node) { return node.getAttribute('data-list-item'); });
                    order.splice(target, 0, order.splice(index, 1)[0]);
                    try {
                        await request(api.urls.reorder, 'PATCH', { key: listKey, order: order, defaults: defaults });
                        // Same markup, new place: no re-render needed.
                        const other = items[target];
                        action === 'move-up' ? other.before(item) : other.after(item);
                        positionToolbar();
                        showToast(action === 'move-up' ? 'Đã chuyển ô lên.' : 'Đã chuyển ô xuống.');
                    } catch (error) {
                        showToast(error.message, true);
                    }
                    return;
                }

                if (api.hasPending()) {
                    showToast('Hãy bấm Lưu (hoặc Huỷ) các thay đổi đang chờ trước.', true);
                    return;
                }

                if (action === 'duplicate') {
                    // What every region of the box shows now, saved or the template's default.
                    const prefix = listKey + '.item_' + itemId + '.';
                    const slots = Array.from(item.querySelectorAll('[data-block-key]')).filter(function (region) {
                        return region.getAttribute('data-block-key').indexOf(prefix) === 0
                            && region.closest('[data-list-item]') === item;
                    }).map(function (region) {
                        const type = region.getAttribute('data-block-type') || 'text';
                        return {
                            slot: region.getAttribute('data-block-key').slice(prefix.length),
                            type: type,
                            value: api.regionValue(region),
                            format: type === 'html' ? (region.getAttribute('data-block-format') || null) : null,
                            align: region.getAttribute('data-block-align') || null,
                        };
                    });
                    try {
                        const json = await request(api.urls.duplicate, 'POST', { key: listKey, item: itemId, defaults: defaults, content_locale: api.locale, slots: slots });
                        const newId = json.data.item;
                        const isCopiedRegion = item === active;
                        insertCopy(item, prefix, listKey + '.item_' + newId + '.', { 'data-list-item': newId });
                        showToast(isCopiedRegion ? 'Đã nhân bản đoạn ngay bên dưới.' : 'Đã nhân bản ô ngay bên cạnh.');
                    } catch (error) {
                        showToast(error.message, true);
                    }
                    return;
                }

                if (action === 'delete-item') {
                    if (!window.confirm('Xóa hẳn ô nội dung này?')) return;
                    try {
                        await request(api.urls.listItems, 'DELETE', { key: listKey, item: itemId, defaults: defaults });
                        hideToolbar();
                        item.remove();
                        showToast('Đã xóa ô nội dung.');
                    } catch (error) {
                        showToast(error.message, true);
                    }
                }
            }

            // --- alignment ----------------------------------------------------
            function applyAlign(value) {
                if (!active || active.getAttribute('data-block-type') === 'image') return;
                const before = snapshotOf(active);
                ['left', 'center', 'right', 'justify'].forEach(function (token) {
                    active.classList.remove('client-align-' + token);
                });
                if (value) {
                    active.classList.add('client-align-' + value);
                    active.setAttribute('data-block-align', value);
                } else {
                    active.removeAttribute('data-block-align');
                }
                closeMenus();
                commitCommand(active, before);
                syncToolbar();
            }

            // --- AI rewrite ---------------------------------------------------
            const aiUrl = @json($inlineAiAllowed ? route('admin.ai.rewrite', ['locale' => app()->getLocale()]) : null);

            async function rewriteWithAi(mode) {
                if (!active || !aiUrl) return;
                closeMenus();
                const region = active;
                const selection = window.getSelection();
                const hasSelection = restoreSelection() && selection && !selection.isCollapsed && region.contains(selection.getRangeAt(0).commonAncestorContainer);
                const range = hasSelection ? selection.getRangeAt(0).cloneRange() : null;
                const original = (hasSelection ? selection.toString() : region.textContent || '').trim();
                if (!original) {
                    showToast('Vùng này chưa có chữ để AI viết lại.', true);
                    return;
                }
                const tag = (region.getAttribute('data-block-format') || region.tagName).toLowerCase();
                const kind = /^h[1-6]$/.test(tag) ? 'heading'
                    : (tag === 'ul' || tag === 'ol') ? 'list'
                        : (tag === 'a' || tag === 'button' || region.closest('a, button')) ? 'button'
                            : region.getAttribute('data-block-type') === 'text' ? 'label' : 'paragraph';
                const section = region.closest('[data-section-label], [data-section-name], section');
                const context = section ? (section.innerText || section.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 1400) : '';

                toolbar.classList.add('is-working');
                showToast('AI đang viết lại…');
                try {
                    const json = await request(aiUrl, 'POST', { text: original, mode: mode, kind: kind, page_title: document.title, context: context });
                    const rewritten = (json && json.data && json.data.text ? String(json.data.text) : '').trim();
                    if (!rewritten) throw new Error('AI chưa trả lời được, thử lại sau.');
                    if (!region.isConnected) return;
                    region.focus();
                    const target = document.createRange();
                    if (range) {
                        target.setStart(range.startContainer, range.startOffset);
                        target.setEnd(range.endContainer, range.endOffset);
                    } else {
                        target.selectNodeContents(region);
                    }
                    const live = window.getSelection();
                    live.removeAllRanges();
                    live.addRange(target);
                    // Plain text through the editing pipeline: undoable (Ctrl+Z), marked dirty, saved on Lưu.
                    rememberState(region);
                    document.execCommand('insertText', false, region.getAttribute('data-block-type') === 'text' ? rewritten.replace(/\s*\n+\s*/g, ' ') : rewritten);
                    api.markDirty(region);
                    rememberSelection();
                    syncToolbar();
                    showToast('Đã thay bằng chữ AI viết. Bấm Lưu để giữ, Ctrl+Z để quay lại.');
                } catch (error) {
                    showToast(error.message, true);
                } finally {
                    toolbar.classList.remove('is-working');
                }
            }

            function closeMenus() {
                toolbar.querySelectorAll('.client-block-toolbar__menu-host.is-open').forEach(function (host) {
                    host.classList.remove('is-open');
                    const trigger = host.querySelector('[data-client-menu-trigger]');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                });
            }

            async function runAction(action) {
                if (!active) return;
                const key = active.getAttribute('data-block-key');
                closeMenus();

                if (action === 'duplicate-region') {
                    await duplicateRegion();
                    return;
                }

                if (action === 'duplicate' || action === 'move-up' || action === 'move-down' || action === 'delete-item') {
                    await runItemAction(action);
                    return;
                }

                if (action === 'clear') {
                    // Emptying is an ordinary content change: an empty value is what
                    // "hidden" means, so it rides the normal Lưu flow.
                    const region = active;
                    rememberState(region);
                    region.innerHTML = '';
                    // Off the page at once, not left as an empty dashed box. Huỷ puts the
                    // class list back with the content; after Lưu the bar keeps this class.
                    region.classList.add('client-block-empty');
                    api.markDirty(region);
                    hideToolbar();
                    region.blur();
                    showToast('Đã ẩn vùng. Bấm Lưu để giữ, Huỷ để hiện lại.');
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

            // --- hover controls -----------------------------------------------
            function showHoverControls(element) {
                hovered = element;
                const removable = Boolean(element.getAttribute('data-list-item'));

                removeButton.classList.toggle('is-visible', removable);
                if (removable) place(element, removeButton, 26);
            }

            function hideHoverControls() {
                hovered = null;
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
                // The colour input needs its own mousedown to open the system picker.
                if (event.target.closest('input')) return;
                event.preventDefault();
            });

            toolbar.addEventListener('click', function (event) {
                const menuTrigger = event.target.closest('[data-client-menu-trigger]');
                if (menuTrigger) {
                    event.preventDefault();
                    const host = menuTrigger.closest('.client-block-toolbar__menu-host');
                    const wasOpen = host.classList.contains('is-open');
                    closeMenus();
                    closeHeadingMenu();
                    if (typeof closeColorMenu === 'function') closeColorMenu();
                    if (!wasOpen) {
                        host.classList.add('is-open');
                        menuTrigger.setAttribute('aria-expanded', 'true');
                    }
                    return;
                }

                const align = event.target.closest('[data-client-block-align]');
                if (align) {
                    event.preventDefault();
                    applyAlign(align.dataset.clientBlockAlign);
                    return;
                }

                const aiLocked = event.target.closest('[data-client-ai-locked]');
                if (aiLocked) {
                    event.preventDefault();
                    let lock = {};
                    try {
                        lock = JSON.parse(aiLocked.getAttribute('data-client-ai-locked') || '{}');
                    } catch (error) {
                        // Shown with the generic line below.
                    }
                    const message = lock.message || 'Tính năng này chưa có trong gói của website.';
                    if (lock.action_url && window.confirm(message + '\n\n' + (lock.action_label || 'Liên hệ') + '?')) {
                        window.open(lock.action_url, '_blank', 'noopener');
                    } else if (!lock.action_url) {
                        showToast(message, true);
                    }
                    return;
                }

                const aiMode = event.target.closest('[data-client-ai-mode]');
                if (aiMode) {
                    event.preventDefault();
                    rewriteWithAi(aiMode.dataset.clientAiMode);
                    return;
                }

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
                    return;
                }

                const color = event.target.closest('[data-client-block-color]');
                if (color) {
                    event.preventDefault();
                    applyColor(color.dataset.clientBlockColor);
                }
            });

            // --- adding a box to a repeatable region ----------------------------
            // The new box's markup comes from the Blade that owns the region, so only a re-render
            // can produce it: add on the server, then reload. A reload would drop unsaved typing,
            // so that has to be saved (or cancelled) first.
            document.addEventListener('click', async function (event) {
                const button = event.target.closest ? event.target.closest('[data-client-list-add]') : null;
                if (!button || !api.isEditing()) return;
                event.preventDefault();
                event.stopPropagation();
                if (api.hasPending()) {
                    showToast('Hãy bấm Lưu (hoặc Huỷ) các thay đổi đang chờ trước khi thêm ô mới.', true);
                    return;
                }
                let defaults = [];
                try {
                    defaults = JSON.parse(button.getAttribute('data-list-defaults') || '[]');
                } catch (error) {
                    defaults = [];
                }
                button.disabled = true;
                try {
                    await request(api.urls.listItems, 'POST', { key: button.getAttribute('data-list-key'), defaults: defaults });
                    showToast('Đã thêm ô mới. Đang tải lại trang…');
                    window.location.reload();
                } catch (error) {
                    button.disabled = false;
                    showToast(error.message, true);
                }
            }, true);

            // --- text colour --------------------------------------------------
            const colorControl = document.getElementById('client-block-color-control');
            const colorTrigger = document.getElementById('client-block-color-trigger');
            const colorBar = document.getElementById('client-block-color-bar');
            const colorInput = document.getElementById('client-block-color-input');
            const recentBox = document.getElementById('client-block-color-recent');
            const recentTitle = document.getElementById('client-block-color-recent-title');
            const RECENT_KEY = 'clientBlocks.recentColors';

            function recentColors() {
                try {
                    const stored = JSON.parse(window.localStorage.getItem(RECENT_KEY) || '[]');
                    return Array.isArray(stored) ? stored.filter(function (value) { return /^#[0-9a-f]{6}$/i.test(value); }) : [];
                } catch (error) {
                    return [];
                }
            }

            function renderRecent() {
                if (!recentBox) return;
                const colors = recentColors();
                recentBox.textContent = '';
                colors.forEach(function (value) {
                    const swatch = document.createElement('button');
                    swatch.type = 'button';
                    swatch.className = 'client-block-toolbar__swatch';
                    swatch.dataset.clientBlockColor = value;
                    swatch.title = value;
                    swatch.setAttribute('aria-label', 'Màu ' + value);
                    swatch.style.setProperty('background', value, 'important');
                    recentBox.appendChild(swatch);
                });
                if (recentTitle) recentTitle.hidden = colors.length === 0;
            }

            function rememberColor(value) {
                if (!/^#[0-9a-f]{6}$/i.test(value)) return;
                const colors = [value.toLowerCase()].concat(recentColors().filter(function (old) { return old !== value.toLowerCase(); })).slice(0, 6);
                try {
                    window.localStorage.setItem(RECENT_KEY, JSON.stringify(colors));
                } catch (error) {
                    // Private mode: the colour still applies, it just is not remembered.
                }
                renderRecent();
            }

            function closeColorMenu() {
                if (!colorControl) return;
                colorControl.classList.remove('is-open');
                if (colorTrigger) colorTrigger.setAttribute('aria-expanded', 'false');
            }

            /*
             * The browser puts the colour on a span around the selection. A theme element inside it
             * that sets its own colour — `<strong class="text-slate-800">` — keeps that colour, so
             * bold words stayed unchanged. Every selected run of text still not showing the chosen
             * colour gets its own coloured span, inside that element.
             */
            function enforceColor(region, value) {
                const selection = window.getSelection();
                if (!selection || !selection.rangeCount) return;
                const range = selection.getRangeAt(0);
                const probe = document.createElement('span');
                probe.style.color = value;
                region.appendChild(probe);
                const wanted = getComputedStyle(probe).color;
                probe.remove();

                const walker = document.createTreeWalker(region, NodeFilter.SHOW_TEXT);
                const runs = [];
                while (walker.nextNode()) {
                    const node = walker.currentNode;
                    if (node.nodeValue.trim() !== '' && range.intersectsNode(node)
                        && getComputedStyle(node.parentElement).color !== wanted) {
                        runs.push(node);
                    }
                }
                const start = { node: range.startContainer, offset: range.startOffset };
                const end = { node: range.endContainer, offset: range.endOffset };
                runs.forEach(function (node) {
                    let text = node;
                    // Only the selected part of a text node at either edge of the selection.
                    if (text === end.node && end.offset < text.length) text.splitText(end.offset);
                    if (text === start.node && start.offset > 0) text = text.splitText(start.offset);
                    if (text.nodeValue.trim() === '') return;
                    const span = document.createElement('span');
                    span.style.color = value;
                    text.replaceWith(span);
                    span.appendChild(text);
                });
            }

            /* "" removes the colour: the text takes the theme's colour again. */
            function applyColor(value) {
                if (!active || active.getAttribute('data-block-type') === 'image') return;
                const before = snapshotOf(active);
                const selection = window.getSelection();
                if (!restoreSelection() || !selection || selection.isCollapsed) {
                    showToast('Hãy bôi đen phần chữ cần đổi màu.', true);
                    return;
                }
                try {
                    document.execCommand('styleWithCSS', false, true);
                    document.execCommand('foreColor', false, value || 'inherit');
                } catch (error) {
                    showToast('Trình duyệt không hỗ trợ thao tác này.', true);
                    return;
                } finally {
                    document.execCommand('styleWithCSS', false, false);
                }
                if (value) enforceColor(active, value);
                closeColorMenu();
                if (!commitCommand(active, before)) {
                    syncToolbar();
                    return;
                }
                // A coloured word is markup: the region is saved as HTML from now on.
                active.setAttribute('data-block-type', 'html');
                if (value) {
                    rememberColor(value);
                    if (colorBar) colorBar.style.background = value;
                }
                rememberSelection();
                syncToolbar();
            }

            if (colorTrigger && colorControl) {
                colorTrigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    closeHeadingMenu();
                    const open = colorControl.classList.toggle('is-open');
                    colorTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            }
            if (colorInput) {
                // "change" fires once the system picker closes with a choice.
                colorInput.addEventListener('change', function () {
                    applyColor(colorInput.value);
                });
            }
            renderRecent();

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

            // Keep keyboard and toolbar history on the same snapshot stack. If the
            // browser also performs its native contenteditable undo, the DOM moves
            // twice and Redo no longer describes the state the admin can see.
            document.addEventListener('keydown', function (event) {
                if (!active || !(event.ctrlKey || event.metaKey) || event.altKey) return;
                const key = event.key.toLowerCase();
                const direction = key === 'y' || (key === 'z' && event.shiftKey)
                    ? 'redo'
                    : (key === 'z' ? 'undo' : null);
                if (!direction) return;
                event.preventDefault();
                travelHistory(direction);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && toolbar.querySelector('.client-block-toolbar__menu-host.is-open')) {
                    event.preventDefault();
                    closeMenus();
                    return;
                }
                if (event.key === 'Escape' && colorControl && colorControl.classList.contains('is-open')) {
                    event.preventDefault();
                    closeColorMenu();
                    if (colorTrigger) colorTrigger.focus();
                    return;
                }
                if (event.key !== 'Escape' || !headingControl || !headingControl.classList.contains('is-open')) return;
                event.preventDefault();
                closeHeadingMenu();
                if (headingTrigger) headingTrigger.focus();
            });

            document.addEventListener('beforeinput', function (event) {
                if (!api.isEditing()) return;
                const region = event.target.closest ? event.target.closest('[data-block-key]') : null;
                if (region) rememberState(region);
            });

            document.addEventListener('mouseover', function (event) {
                if (!api.isEditing()) return;
                const region = event.target.closest ? event.target.closest('[data-block-key]') : null;
                region ? showHoverControls(region) : hideHoverControls();
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
                if (colorControl && !event.target.closest('#client-block-color-control')) {
                    closeColorMenu();
                }
                if (!event.target.closest('.client-block-toolbar__menu-host')) {
                    closeMenus();
                }
            });

            window.addEventListener('scroll', positionToolbar, { passive: true });
            window.addEventListener('resize', positionToolbar);
        });
    </script>
@endif
