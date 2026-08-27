@php
    $viewportAllowed = \App\Support\ClientEditorChrome::editorActive();
@endphp

@if($viewportAllowed)
    {{-- A real iframe viewport keeps CSS breakpoints honest. The surrounding UI
         follows a builder-style responsive preview: dark canvas, device centered
         at full height and a compact vertical rail. It never scales the page. --}}
    <style>
        #client-viewport {
            background: #14181b !important;
            color: #b5bdc5 !important;
            display: none !important;
            font: 500 13px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
            inset: 0 !important;
            margin: 0 !important;
            position: fixed !important;
            z-index: 2147483001 !important;
        }
        #client-viewport.is-open { display: flex !important; }
        #client-viewport .client-vp__rail {
            align-items: center !important;
            background: #171b1e !important;
            border-left: 1px solid #242a2f !important;
            bottom: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            position: absolute !important;
            right: 0 !important;
            top: 0 !important;
            width: 56px !important;
            z-index: 2 !important;
        }
        #client-viewport .client-vp__devices {
            align-items: center !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 1px !important;
        }
        #client-viewport .client-vp__rail button {
            align-items: center !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            color: #7f8992 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font: inherit !important;
            height: 48px !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 55px !important;
        }
        #client-viewport .client-vp__rail button:hover {
            background: #22282d !important;
            color: #fff !important;
        }
        #client-viewport .client-vp__rail button.is-active {
            background: #262d32 !important;
            box-shadow: inset 3px 0 #72aee6 !important;
            color: #fff !important;
        }
        #client-viewport button:focus-visible {
            outline: 2px solid #72aee6 !important;
            outline-offset: 1px !important;
        }
        #client-viewport .client-vp__size {
            bottom: 59px !important;
            color: #717b84 !important;
            font-size: 10px !important;
            font-variant-numeric: tabular-nums !important;
            position: absolute !important;
            text-align: center !important;
            width: 56px !important;
        }
        #client-viewport .client-vp__close {
            position: absolute !important;
            top: 0 !important;
        }
        #client-viewport .client-vp__back {
            border-top: 1px solid #242a2f !important;
            bottom: 0 !important;
            color: #a5aeb7 !important;
            position: absolute !important;
            right: 0 !important;
        }
        #client-viewport .client-vp__canvas {
            align-items: flex-start !important;
            bottom: 0 !important;
            box-sizing: border-box !important;
            display: flex !important;
            height: auto !important;
            justify-content: center !important;
            left: 0 !important;
            min-height: 0 !important;
            overflow: auto !important;
            padding: 0 !important;
            position: absolute !important;
            right: 56px !important;
            top: 0 !important;
            scrollbar-color: #59636d #111518 !important;
            scrollbar-width: thin !important;
        }
        #client-viewport .client-vp__canvas::-webkit-scrollbar {
            height: 9px !important;
            width: 9px !important;
        }
        #client-viewport .client-vp__canvas::-webkit-scrollbar-track {
            background: #111518 !important;
        }
        #client-viewport .client-vp__canvas::-webkit-scrollbar-thumb {
            background: #59636d !important;
            border: 2px solid #111518 !important;
            border-radius: 999px !important;
        }
        #client-viewport .client-vp__canvas::-webkit-scrollbar-thumb:hover {
            background: #77838e !important;
        }
        #client-viewport .client-vp__stage {
            background: #fff !important;
            border: 0 !important;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, .04), 0 8px 32px rgba(0, 0, 0, .4) !important;
            flex: 0 0 auto !important;
            height: 100% !important;
            max-width: 100% !important;
            min-height: 0 !important;
            overflow: hidden !important;
            transition: width .18s ease !important;
        }
        #client-viewport .client-vp__stage.is-desktop { width: 100% !important; }
        #client-viewport .client-vp__stage.is-tablet { width: 768px !important; }
        #client-viewport .client-vp__stage.is-mobile { width: 390px !important; }
        #client-viewport iframe {
            border: 0 !important;
            display: block !important;
            height: 100% !important;
            width: 100% !important;
        }
        @media (max-width: 700px) {
            #client-viewport .client-vp__size,
            #client-viewport .client-vp__device-label { display: none !important; }
            #client-viewport .client-vp__canvas { padding: 0 !important; }
        }
        @media (prefers-reduced-motion: reduce) {
            #client-viewport .client-vp__stage { transition: none !important; }
        }
    </style>

    <div id="client-viewport" aria-label="Xem trước responsive" role="dialog" aria-modal="true">
        <aside class="client-vp__rail" aria-label="Công cụ khổ màn hình">
            <button type="button" class="client-vp__close" id="client-viewport-close" title="Đóng xem trước" aria-label="Đóng xem trước">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
            <div class="client-vp__devices" role="group" aria-label="Chọn khổ màn hình">
                <button type="button" data-viewport-mode="desktop" data-viewport-width="1280" data-viewport-label="100%" class="is-active" aria-pressed="true" title="Máy tính - toàn chiều rộng">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="1"/><path d="M8 20h8M12 16v4"/></svg><span class="client-vp__device-label" hidden>Máy tính</span>
                </button>
                <button type="button" data-viewport-mode="tablet" data-viewport-width="768" aria-pressed="false" title="Máy tính bảng 768 px">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M11 18h2"/></svg><span class="client-vp__device-label" hidden>Máy tính bảng</span>
                </button>
                <button type="button" data-viewport-mode="mobile" data-viewport-width="390" aria-pressed="false" title="Điện thoại 390 px">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg><span class="client-vp__device-label" hidden>Điện thoại</span>
                </button>
            </div>
            <span class="client-vp__size" id="client-viewport-size">100%</span>
            <button type="button" class="client-vp__back" data-client-viewport-close title="Quay lại chỉnh sửa" aria-label="Quay lại chỉnh sửa">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            </button>
        </aside>
        <div class="client-vp__canvas">
            <div class="client-vp__stage is-desktop" id="client-viewport-stage"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('client-viewport-button');
            const overlay = document.getElementById('client-viewport');
            const stage = document.getElementById('client-viewport-stage');
            const size = document.getElementById('client-viewport-size');
            if (!toggle || !overlay || !stage || !size) return;

            const previewUrl = @json(\App\Support\ClientEditorChrome::previewUrl());
            const modeButtons = Array.from(overlay.querySelectorAll('[data-viewport-mode]'));

            function stylePreviewScrollbar(frame) {
                frame.addEventListener('load', function () {
                    try {
                        const style = frame.contentDocument.createElement('style');
                        style.setAttribute('data-client-preview-scrollbar', '');
                        style.textContent = 'html{scrollbar-width:thin;scrollbar-color:#59636d #111518}'
                            + 'html::-webkit-scrollbar{width:9px;height:9px}'
                            + 'html::-webkit-scrollbar-track{background:#111518}'
                            + 'html::-webkit-scrollbar-thumb{background:#59636d;border:2px solid #111518;border-radius:999px}'
                            + 'html::-webkit-scrollbar-thumb:hover{background:#77838e}';
                        frame.contentDocument.head.appendChild(style);
                    } catch (error) {
                        // Preview is normally same-origin. If that changes, the
                        // browser correctly prevents styling its document.
                    }
                }, { once: true });
            }

            function setMode(button) {
                const mode = button.dataset.viewportMode;
                stage.className = 'client-vp__stage is-' + mode;
                size.textContent = button.dataset.viewportLabel || (button.dataset.viewportWidth + ' px');
                modeButtons.forEach(function (candidate) {
                    const active = candidate === button;
                    candidate.classList.toggle('is-active', active);
                    candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }

            function open() {
                const frame = document.createElement('iframe');
                frame.setAttribute('title', 'Bản xem trước trang');
                frame.setAttribute('src', previewUrl);
                stylePreviewScrollbar(frame);
                stage.innerHTML = '';
                stage.appendChild(frame);
                overlay.classList.add('is-open');
                toggle.classList.add('is-active');
                toggle.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function close() {
                overlay.classList.remove('is-open');
                toggle.classList.remove('is-active');
                toggle.setAttribute('aria-expanded', 'false');
                stage.innerHTML = '';
                document.body.style.overflow = '';
            }

            toggle.hidden = false;
            toggle.addEventListener('click', function () {
                overlay.classList.contains('is-open') ? close() : open();
            });
            document.getElementById('client-viewport-close').addEventListener('click', close);
            overlay.querySelectorAll('[data-client-viewport-close]').forEach(function (button) {
                button.addEventListener('click', close);
            });
            modeButtons.forEach(function (button) {
                button.addEventListener('click', function () { setMode(button); });
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && overlay.classList.contains('is-open')) close();
            });
        });
    </script>
@endif
