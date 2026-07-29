@php
    $storedBuilderData = old('builder_data', $page->builder_data ?: ['version' => 1, 'locales' => []]);
    if (is_string($storedBuilderData)) $storedBuilderData = json_decode($storedBuilderData, true) ?: ['version' => 1, 'locales' => []];
    $storedHtml = old('published_html', $page->exists ? $page->getTranslations('published_html') : []);
    $storedCss = old('published_css', $page->exists ? $page->getTranslations('published_css') : []);
    $editorState = [
        'version' => 1,
        'locales' => data_get($storedBuilderData, 'locales', []),
        'html' => is_array($storedHtml) ? $storedHtml : [],
        'css' => is_array($storedCss) ? $storedCss : [],
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-semibold mb-1">{{ $page->exists ? 'Chỉnh sửa trang' : 'Thêm trang' }}</h4>
        <p class="text-muted mb-0">Kéo block vào canvas, chọn block để chỉnh nội dung và kiểu hiển thị.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" id="page-builder-preview-button">
            <i class="ti ti-eye me-1" aria-hidden="true"></i>Xem trước
        </button>
        <a class="btn btn-light" href="{{ route('admin.pages.index') }}">Quay lại</a>
        <button type="submit" class="btn btn-primary">Lưu trang</button>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <ul class="nav nav-tabs mb-4" role="tablist">
            @foreach($contentLanguages as $language)
                <li class="nav-item"><button class="nav-link @if($language->code === $defaultContentLocale) active @endif" type="button" data-bs-toggle="tab" data-bs-target="#page-meta-{{ $language->code }}">{{ $language->native_name }}</button></li>
            @endforeach
        </ul>
        <div class="tab-content">
            @foreach($contentLanguages as $language)
                @php $code = $language->code; @endphp
                <div class="tab-pane fade @if($code === $defaultContentLocale) show active @endif" id="page-meta-{{ $code }}">
                    <div class="row">
                        <div class="col-md-8 mb-3"><label class="form-label">Tiêu đề @if($code === $defaultContentLocale)<span class="text-danger">*</span>@endif</label><input class="form-control" name="title[{{ $code }}]" value="{{ old("title.$code", $page->getTranslation('title', $code, false)) }}" @required($code === $defaultContentLocale)></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Slug</label><input class="form-control" name="slug[{{ $code }}]" value="{{ old("slug.$code", $page->localizedSlug($code) ?: ($code === $defaultContentLocale ? $page->slug : '')) }}" placeholder="Tự tạo từ tiêu đề"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">SEO title</label><input class="form-control" name="meta_title[{{ $code }}]" value="{{ old("meta_title.$code", $page->getTranslation('meta_title', $code, false)) }}"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">SEO description</label><input class="form-control" name="meta_description[{{ $code }}]" value="{{ old("meta_description.$code", $page->getTranslation('meta_description', $code, false)) }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0 overflow-hidden">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between p-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <label for="page-builder-locale" class="fw-semibold mb-0">Nội dung:</label>
                <select id="page-builder-locale" class="form-select form-select-sm w-auto" disabled>
                    @foreach($contentLanguages as $language)<option value="{{ $language->code }}" @selected($language->code === $defaultContentLocale)>{{ $language->native_name }}</option>@endforeach
                </select>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="page-builder-html-button">
                    <i class="ti ti-code me-1" aria-hidden="true"></i>Dán HTML/CSS
                </button>
                @can('manage_media')
                    <input type="file" id="page-builder-media" class="d-none" accept="image/*" data-media-folder="general">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="page-builder-media-button"><i class="ti ti-photo me-1" aria-hidden="true"></i>Chọn ảnh cho block</button>
                @endcan
            </div>
        </div>
        <div id="page-builder-status" class="alert alert-info rounded-0 border-0 border-bottom mb-0">
            <i class="ti ti-loader-2 me-1" aria-hidden="true"></i>Đang tải trình dựng trang…
        </div>
        <div id="page-builder-canvas"></div>
    </div>
</div>

<div class="modal fade" id="pageBuilderHtmlModal" tabindex="-1" aria-labelledby="pageBuilderHtmlModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="pageBuilderHtmlModalLabel">Dán HTML/CSS</h5>
                    <p class="text-muted small mb-0">Áp dụng cho ngôn ngữ đang chọn. Script, iframe, form và event handler sẽ bị loại bỏ.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label for="page-builder-html-source" class="form-label fw-semibold">HTML</label>
                        <textarea id="page-builder-html-source" class="form-control font-monospace" rows="18" spellcheck="false" placeholder="<section>...</section>"></textarea>
                    </div>
                    <div class="col-lg-5">
                        <label for="page-builder-css-source" class="form-label fw-semibold">CSS</label>
                        <textarea id="page-builder-css-source" class="form-control font-monospace" rows="18" spellcheck="false" placeholder=".hero { ... }"></textarea>
                    </div>
                </div>
                <div class="alert alert-warning small mt-3 mb-0">
                    Nếu HTML có thẻ <code>&lt;style&gt;</code>, CSS bên trong sẽ tự động được đưa sang ô CSS.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-outline-primary" id="page-builder-html-append">Chèn thêm</button>
                <button type="button" class="btn btn-primary" id="page-builder-html-replace">Thay toàn bộ canvas</button>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body d-flex flex-wrap justify-content-between gap-3">
        <div>
            <label class="form-label fw-semibold">Trạng thái</label>
            <select class="form-select" name="is_active">
                <option value="0" @selected(!old('is_active', $page->is_active))>Bản nháp</option>
                <option value="1" @selected(old('is_active', $page->is_active))>Xuất bản</option>
            </select>
        </div>
        <div class="align-self-end"><button class="btn btn-primary px-4">Lưu trang</button></div>
    </div>
</div>

<input type="hidden" name="builder_data" id="page-builder-data">
<input type="hidden" name="published_html" id="page-builder-html">
<input type="hidden" name="published_css" id="page-builder-css">

@push('styles')
<link rel="stylesheet" href="{{ asset('admin-assets/libs/font-awesome/css/font-awesome.min.css') }}">
<link rel="stylesheet" href="{{ asset('admin-assets/libs/grapesjs/grapes.min.css') }}">
<style>
    #page-builder-canvas,
    #page-builder-canvas.gjs-editor-cont,
    #page-builder-canvas > .gjs-editor {
        height: 72vh;
        min-height: 620px;
    }
    #page-builder-canvas .gjs-cv-canvas {
        min-height: 580px;
    }
    #page-builder-canvas .gjs-cv-canvas__frames,
    #page-builder-canvas .gjs-frame-wrapper,
    #page-builder-canvas iframe.gjs-frame {
        height: 100% !important;
        min-height: 580px;
    }
    #page-builder-canvas .fa { font-family: FontAwesome !important; }
    .gjs-one-bg { background-color: #263445; }
    .gjs-two-color { color: #eef3f8; }
    .gjs-three-bg { background-color: #5d87ff; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('admin-assets/libs/grapesjs/grapes.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('page-builder-form');
    const builderStatus = document.getElementById('page-builder-status');
    if (!form) return;
    function showBuilderError(message) {
        builderStatus.className = 'alert alert-danger rounded-0 border-0 border-bottom mb-0';
        builderStatus.innerHTML = '<i class="ti ti-alert-triangle me-1" aria-hidden="true"></i>' + message;
    }
    if (typeof grapesjs === 'undefined') {
        showBuilderError('Không tải được thư viện Page Builder. Hãy tải lại trang bằng Ctrl + Shift + R.');
        return;
    }

    try {
    const state = @json($editorState);
    const localeSelect = document.getElementById('page-builder-locale');
    let activeLocale = localeSelect.value;
    let editorReady = false;

    const editor = grapesjs.init({
        container: '#page-builder-canvas',
        height: '100%',
        storageManager: false,
        noticeOnUnload: false,
        fromElement: false,
        cssIcons: @json(asset('admin-assets/libs/font-awesome/css/font-awesome.min.css')),
        selectorManager: { componentFirst: true },
        deviceManager: {
            devices: [
                { id: 'desktop', name: 'Desktop', width: '' },
                { id: 'tablet', name: 'Tablet', width: '768px', widthMedia: '992px' },
                { id: 'mobile', name: 'Mobile', width: '375px', widthMedia: '576px' },
            ]
        },
        styleManager: {
            sectors: [
                { name: 'Bố cục', open: true, buildProps: ['display', 'position', 'width', 'max-width', 'min-height', 'margin', 'padding', 'gap', 'justify-content', 'align-items'] },
                { name: 'Chữ', open: false, buildProps: ['font-family', 'font-size', 'font-weight', 'line-height', 'text-align', 'color'] },
                { name: 'Nền & viền', open: false, buildProps: ['background-color', 'background-image', 'border', 'border-radius', 'box-shadow', 'opacity'] },
            ]
        },
    });
    editor.on('load', initializeBuilderContent);

    const blocks = editor.BlockManager;
    blocks.add('page-hero', { label: 'Hero', category: 'Nội dung', content: '<section data-page-block="hero" style="padding:80px 24px;text-align:center;background:#eef3ff"><h1>Tiêu đề nổi bật</h1><p>Thêm thông điệp chính cho trang.</p><a href="#" style="display:inline-block;padding:12px 24px;background:#5d87ff;color:#fff;text-decoration:none;border-radius:6px">Khám phá</a></section>' });
    blocks.add('page-text', { label: 'Văn bản', category: 'Nội dung', content: '<section data-page-block="rich-text" style="max-width:1100px;margin:auto;padding:48px 24px"><h2>Tiêu đề nội dung</h2><p>Nhấp đúp để chỉnh sửa nội dung.</p></section>' });
    blocks.add('page-image-text', { label: 'Ảnh + nội dung', category: 'Nội dung', content: '<section data-page-block="image-text" style="display:flex;gap:32px;align-items:center;max-width:1100px;margin:auto;padding:48px 24px"><img src="https://placehold.co/600x400" alt="" style="width:50%"><div><h2>Tiêu đề</h2><p>Nội dung giới thiệu.</p></div></section>' });
    blocks.add('page-image', { label: 'Ảnh', category: 'Media', content: { type: 'image', attributes: { 'data-page-block': 'image', alt: '' }, style: { display: 'block', 'max-width': '100%', margin: '0 auto' } } });
    blocks.add('page-cta', { label: 'CTA', category: 'Marketing', content: '<section data-page-block="cta" style="padding:48px 24px;text-align:center;background:#263445;color:#fff"><h2>Bắt đầu ngay hôm nay</h2><p>Thêm lời kêu gọi hành động.</p><a href="#" style="color:#fff">Liên hệ ngay</a></section>' });
    blocks.add('page-products', { label: 'Sản phẩm', category: 'Cửa hàng', content: '<section data-page-block="product-grid" data-category="" data-limit="8" style="padding:48px 24px"><h2 style="text-align:center">Sản phẩm nổi bật</h2><div style="padding:32px;text-align:center;border:1px dashed #bbb">Storefront sẽ render danh sách sản phẩm tại đây</div></section>' });
    blocks.add('page-faq', { label: 'FAQ', category: 'Nội dung', content: '<section data-page-block="faq" style="max-width:900px;margin:auto;padding:48px 24px"><h2>Câu hỏi thường gặp</h2><details><summary>Câu hỏi thứ nhất</summary><p>Nội dung trả lời.</p></details></section>' });
    blocks.add('page-divider', { label: 'Đường phân cách', category: 'Bố cục', content: '<hr data-page-block="divider" style="margin:32px auto;max-width:1100px;border:0;border-top:1px solid #ddd">' });

    function saveLocale() {
        if (!editorReady) return;
        state.locales[activeLocale] = editor.getProjectData();
        state.html[activeLocale] = editor.getHtml();
        state.css[activeLocale] = editor.getCss();
    }

    function loadLocale(locale) {
        const project = state.locales[locale];
        if (project) {
            editor.loadProjectData(project);
        } else {
            editor.setComponents(state.html[locale] || '');
            editor.setStyle(state.css[locale] || '');
        }
        activeLocale = locale;
        editor.refresh();
    }

    function initializeBuilderContent() {
        if (editorReady) return;

        loadLocale(activeLocale);
        editorReady = true;
        localeSelect.disabled = false;
        builderStatus.classList.add('d-none');

        window.requestAnimationFrame(function () {
            const storedHtml = state.html[activeLocale] || '';
            if (storedHtml && editor.getComponents().length === 0) {
                editor.setComponents(storedHtml);
                editor.setStyle(state.css[activeLocale] || '');
            }
            editor.refresh();
        });
    }

    localeSelect.addEventListener('change', function () {
        if (!editorReady) return;
        saveLocale();
        loadLocale(this.value);
    });

    const htmlModalElement = document.getElementById('pageBuilderHtmlModal');
    const htmlButton = document.getElementById('page-builder-html-button');
    const htmlSource = document.getElementById('page-builder-html-source');
    const cssSource = document.getElementById('page-builder-css-source');
    let htmlModal = null;

    function containsDangerousCss(css) {
        return /@import|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|<\s*\/?\s*(script|style)/i.test(css);
    }

    function cleanImportedHtml(source) {
        const documentNode = new DOMParser().parseFromString(source, 'text/html');
        const extractedCss = [];

        documentNode.querySelectorAll('style').forEach(function (style) {
            extractedCss.push(style.textContent || '');
            style.remove();
        });
        documentNode.querySelectorAll('script, iframe, object, embed, form, input, textarea, select, meta, link, base').forEach(function (node) {
            node.remove();
        });
        documentNode.body.querySelectorAll('*').forEach(function (node) {
            Array.from(node.attributes).forEach(function (attribute) {
                const name = attribute.name.toLowerCase();
                const value = attribute.value.trim();
                if (name.startsWith('on') || name === 'srcdoc') {
                    node.removeAttribute(attribute.name);
                } else if (['href', 'src', 'xlink:href'].includes(name) && /^javascript\s*:/i.test(value)) {
                    node.removeAttribute(attribute.name);
                } else if (name === 'style' && containsDangerousCss(value)) {
                    node.removeAttribute(attribute.name);
                }
            });
        });

        return { html: documentNode.body.innerHTML, css: extractedCss.join('\n') };
    }

    function openHtmlImporter() {
        saveLocale();
        htmlSource.value = editor.getHtml();
        cssSource.value = editor.getCss();
        htmlModal = bootstrap.Modal.getOrCreateInstance(htmlModalElement);
        htmlModal.show();
    }

    function applyImportedHtml(mode) {
        const imported = cleanImportedHtml(htmlSource.value);
        const css = [imported.css, cssSource.value].filter(Boolean).join('\n');
        if (containsDangerousCss(css)) {
            window.alert('CSS chứa nội dung không được phép.');
            return;
        }

        if (mode === 'replace') {
            editor.setComponents(imported.html);
            editor.setStyle(css);
        } else {
            editor.addComponents(imported.html);
            if (css.trim()) editor.addStyle(css);
        }
        saveLocale();
        htmlModal.hide();
    }

    htmlButton.addEventListener('click', openHtmlImporter);
    document.getElementById('page-builder-html-append').addEventListener('click', function () {
        applyImportedHtml('append');
    });
    document.getElementById('page-builder-html-replace').addEventListener('click', function () {
        applyImportedHtml('replace');
    });
    htmlModalElement.addEventListener('hidden.bs.modal', function () {
        htmlButton.focus();
    });

    document.getElementById('page-builder-preview-button').addEventListener('click', function () {
        saveLocale();
        const previewHtml = cleanImportedHtml(editor.getHtml()).html;
        const previewCss = editor.getCss();
        if (containsDangerousCss(previewCss)) {
            window.alert('Không thể xem trước vì CSS chứa nội dung không được phép.');
            return;
        }

        const previewWindow = window.open('', 'page-builder-preview');
        if (!previewWindow) {
            window.alert('Trình duyệt đang chặn cửa sổ xem trước. Vui lòng cho phép popup cho trang quản trị.');
            return;
        }

        const titleInput = document.querySelector('[name="title[' + activeLocale + ']"]');
        const previewTitle = (titleInput?.value || 'Xem trước trang').replace(/[<>&"']/g, function (character) {
            return { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#039;' }[character];
        });
        previewWindow.document.open();
        previewWindow.document.write(
            '<!doctype html><html lang="' + activeLocale + '"><head><meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' + previewTitle + '</title>' +
            '<style>*{box-sizing:border-box}body{margin:0;color:#20242a;font-family:Arial,sans-serif}img{max-width:100%}' +
            previewCss + '</style></head><body>' + previewHtml + '</body></html>'
        );
        previewWindow.document.close();
        previewWindow.focus();
    });

    const mediaInput = document.getElementById('page-builder-media');
    const mediaButton = document.getElementById('page-builder-media-button');
    if (mediaInput && mediaButton) {
        mediaButton.addEventListener('click', function () {
            mediaInput.click();
        });
        mediaInput.addEventListener('media:selected', function (event) {
            const selected = editor.getSelected();
            if (selected && selected.is('image')) {
                selected.addAttributes({ src: event.detail.url });
            } else {
                editor.addComponents({ type: 'image', attributes: { src: event.detail.url, alt: '', 'data-page-block': 'image' } });
            }
        });
    }

    form.addEventListener('submit', function () {
        saveLocale();
        document.getElementById('page-builder-data').value = JSON.stringify({ version: 1, locales: state.locales });
        document.getElementById('page-builder-html').value = JSON.stringify(state.html);
        document.getElementById('page-builder-css').value = JSON.stringify(state.css);
    });
    } catch (error) {
        console.error('Page Builder initialization failed', error);
        showBuilderError('Page Builder khởi tạo thất bại: ' + (error?.message || 'Lỗi không xác định.'));
    }
});
</script>
@endpush
