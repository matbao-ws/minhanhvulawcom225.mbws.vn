/*
 * Rich text editor shared by every admin form (Jodit, vendored in admin-assets/libs/jodit).
 *
 * Markup: a hidden field that is submitted, and the editor element pointing at it.
 *
 *   <textarea class="d-none" id="description_vi" name="description[vi]">…</textarea>
 *   <div class="rich-editor" data-target="description_vi" data-preset="full"
 *        data-placeholder="…" data-media-folder="posts">{!! $cleanHtml !!}</div>
 *
 * - data-preset: full (posts, products), page (policy pages), basic (short descriptions).
 * - data-sync="edited": the field stays disabled (so it is not submitted) until the editor is
 *   really edited. Policy pages use it so saving the title never rewrites stored HTML.
 *
 * Other scripts reach an editor by its field id: AdminRichEditor.forTarget('content_input')
 * gives { getHTML(), setHTML(html), getText(), jodit }. Each edit fires a bubbling
 * `rich-editor:change` event on the editor element.
 */
(function () {
    'use strict';

    if (!window.Jodit || window.AdminRichEditor) {
        return;
    }

    const VI = {
        'Type something': 'Nhập nội dung...',
        'About Jodit': 'Giới thiệu Jodit',
        'Jodit Editor': 'Trình soạn thảo Jodit',
        "Jodit User's Guide": 'Hướng dẫn sử dụng Jodit',
        'contains detailed help for using': 'gồm hướng dẫn chi tiết cách dùng',
        'For information about the license, please go to our website:': 'Thông tin giấy phép xem tại website:',
        'Buy full version': 'Mua bản đầy đủ',
        'Copyright © XDSoft.net - Chupurnov Valeriy. All rights reserved.': 'Copyright © XDSoft.net - Chupurnov Valeriy. Bảo lưu mọi quyền.',
        Anchor: 'Neo',
        'Open in new tab': 'Mở trong tab mới',
        'Open in fullsize': 'Phóng to toàn màn hình',
        'Clear Formatting': 'Xoá định dạng',
        'Fill color or set the text color': 'Màu nền hoặc màu chữ',
        Redo: 'Làm lại',
        Undo: 'Hoàn tác',
        Bold: 'In đậm',
        Italic: 'In nghiêng',
        'Insert Unordered List': 'Danh sách dấu chấm',
        'Insert Ordered List': 'Danh sách đánh số',
        'Align Center': 'Căn giữa',
        'Align Justify': 'Căn đều hai bên',
        'Align Left': 'Căn trái',
        'Align Right': 'Căn phải',
        'Insert Horizontal Line': 'Chèn đường kẻ ngang',
        'Insert Image': 'Chèn ảnh từ thư viện Media',
        'Insert file': 'Chèn tệp',
        'Insert youtube/vimeo video': 'Chèn video YouTube/Vimeo',
        'Insert link': 'Chèn liên kết',
        'Font size': 'Cỡ chữ',
        'Font family': 'Phông chữ',
        'Insert format block': 'Kiểu đoạn văn',
        Normal: 'Đoạn văn',
        'Heading 1': 'Tiêu đề 1',
        'Heading 2': 'Tiêu đề 2',
        'Heading 3': 'Tiêu đề 3',
        'Heading 4': 'Tiêu đề 4',
        'Heading 5': 'Tiêu đề 5',
        'Heading 6': 'Tiêu đề 6',
        Quote: 'Trích dẫn',
        Code: 'Mã nguồn',
        Insert: 'Chèn',
        'Insert table': 'Chèn bảng',
        'Decrease Indent': 'Giảm thụt lề',
        'Increase Indent': 'Tăng thụt lề',
        'Select Special Character': 'Chọn ký tự đặc biệt',
        'Insert Special Character': 'Chèn ký tự đặc biệt',
        'Paint format': 'Sao chép định dạng',
        'Change mode': 'Chuyển chế độ',
        Margins: 'Lề',
        top: 'trên',
        right: 'phải',
        bottom: 'dưới',
        left: 'trái',
        Styles: 'Kiểu CSS',
        Classes: 'Lớp CSS',
        Align: 'Căn lề',
        Right: 'Phải',
        Center: 'Giữa',
        Left: 'Trái',
        '--Not Set--': '-- Không đặt --',
        Src: 'Đường dẫn ảnh',
        Title: 'Tiêu đề',
        Alternative: 'Mô tả ảnh (alt)',
        Filter: 'Lọc',
        Link: 'Liên kết',
        'Open link in new tab': 'Mở liên kết trong tab mới',
        Image: 'Ảnh',
        file: 'tệp',
        Advanced: 'Nâng cao',
        'Image properties': 'Thuộc tính ảnh',
        Cancel: 'Huỷ',
        Ok: 'Đồng ý',
        'Your code is similar to HTML. Keep as HTML?': 'Nội dung dán vào giống HTML. Giữ dạng HTML?',
        'Paste as HTML': 'Dán dạng HTML',
        Keep: 'Giữ nguyên',
        Clean: 'Làm sạch',
        'Insert as Text': 'Dán dạng chữ thường',
        'Word Paste Detected': 'Phát hiện nội dung dán từ Word',
        'The pasted content is coming from a Microsoft Word/Excel document. Do you want to keep the format or clean it up?': 'Nội dung dán từ Microsoft Word/Excel. Giữ định dạng hay làm sạch?',
        'Insert only Text': 'Chỉ dán chữ',
        'File Browser': 'Duyệt tệp',
        'Error on load list': 'Không tải được danh sách',
        'Error on load folders': 'Không tải được thư mục',
        'Are you sure?': 'Bạn chắc chắn chứ?',
        'Enter Directory name': 'Nhập tên thư mục',
        'Create directory': 'Tạo thư mục',
        'type name': 'nhập tên',
        'type dir name': 'nhập tên thư mục',
        'Drop image': 'Thả ảnh vào đây',
        'Drop file': 'Thả tệp vào đây',
        'or click': 'hoặc bấm để chọn',
        'Alternative text': 'Mô tả ảnh (alt)',
        Browse: 'Duyệt',
        Upload: 'Tải lên',
        Background: 'Nền',
        Border: 'Viền',
        Text: 'Chữ',
        Top: 'Trên',
        Middle: 'Giữa',
        Bottom: 'Dưới',
        'Insert column before': 'Thêm cột bên trái',
        'Insert column after': 'Thêm cột bên phải',
        'Insert row above': 'Thêm hàng phía trên',
        'Insert row below': 'Thêm hàng phía dưới',
        'Delete table': 'Xoá bảng',
        'Delete row': 'Xoá hàng',
        'Delete column': 'Xoá cột',
        'Empty cell': 'Xoá nội dung ô',
        'Chars: %d': 'Ký tự: %d',
        'Words: %d': 'Số từ: %d',
        Split: 'Tách ô',
        'Split vertical': 'Tách theo chiều dọc',
        'Split horizontal': 'Tách theo chiều ngang',
        'Strike through': 'Gạch ngang',
        Underline: 'Gạch chân',
        superscript: 'Chỉ số trên',
        subscript: 'Chỉ số dưới',
        'Cut selection': 'Cắt',
        'Select all': 'Chọn tất cả',
        Break: 'Ngắt trang',
        'Search for': 'Tìm',
        'Replace with': 'Thay bằng',
        Replace: 'Thay thế',
        Paste: 'Dán',
        'Choose Content to Paste': 'Chọn nội dung để dán',
        source: 'Sửa mã HTML',
        bold: 'In đậm',
        italic: 'In nghiêng',
        brush: 'Màu chữ / màu nền',
        link: 'Liên kết',
        undo: 'Hoàn tác',
        redo: 'Làm lại',
        table: 'Bảng',
        image: 'Ảnh',
        eraser: 'Xoá định dạng',
        paragraph: 'Kiểu đoạn văn',
        fontsize: 'Cỡ chữ',
        video: 'Video',
        font: 'Phông chữ',
        about: 'Giới thiệu',
        print: 'In',
        underline: 'Gạch chân',
        strikethrough: 'Gạch ngang',
        indent: 'Tăng thụt lề',
        outdent: 'Giảm thụt lề',
        fullsize: 'Toàn màn hình',
        shrink: 'Thu nhỏ',
        hr: 'Đường kẻ ngang',
        ul: 'Danh sách dấu chấm',
        ol: 'Danh sách đánh số',
        cut: 'Cắt',
        selectall: 'Chọn tất cả',
        'Open link': 'Mở liên kết',
        'Edit link': 'Sửa liên kết',
        'No follow': 'Không theo dõi (nofollow)',
        Unlink: 'Bỏ liên kết',
        Eye: 'Xem',
        pencil: 'Sửa',
        ' URL': ' URL',
        Reset: 'Đặt lại',
        Save: 'Lưu',
        'Save as ...': 'Lưu thành...',
        Resize: 'Đổi kích thước',
        Crop: 'Cắt ảnh',
        Width: 'Rộng',
        Height: 'Cao',
        'Keep Aspect Ratio': 'Giữ tỉ lệ',
        Yes: 'Có',
        No: 'Không',
        Remove: 'Xoá',
        Select: 'Chọn',
        'Select %s': 'Chọn %s',
        Update: 'Cập nhật',
        'Vertical align': 'Căn dọc',
        Merge: 'Gộp ô',
        'Add column': 'Thêm cột',
        'Add row': 'Thêm hàng',
        Delete: 'Xoá',
        'Horizontal align': 'Căn ngang',
        'Sort by changed': 'Sắp theo ngày sửa',
        'Sort by name': 'Sắp theo tên',
        'Sort by size': 'Sắp theo dung lượng',
        'Add folder': 'Thêm thư mục',
        'You can only edit your own images. Download this image on the host?': 'Chỉ sửa được ảnh của bạn. Tải ảnh này về máy chủ?',
        'The image has been successfully uploaded to the host!': 'Đã tải ảnh lên máy chủ.',
        palette: 'Bảng màu',
        'There are no files': 'Chưa có tệp nào',
        Rename: 'Đổi tên',
        'Enter new name': 'Nhập tên mới',
        preview: 'Xem trước',
        download: 'Tải về',
        'Paste from clipboard': 'Dán từ bộ nhớ tạm',
        "Your browser doesn't support direct access to the clipboard.": 'Trình duyệt không cho truy cập bộ nhớ tạm. Hãy dùng Ctrl+V.',
        'Copy selection': 'Sao chép',
        copy: 'Sao chép',
        'Border radius': 'Bo góc',
        'Show all': 'Hiện tất cả',
        Apply: 'Áp dụng',
        'Please fill out this field': 'Vui lòng nhập ô này',
        'Please enter a web address': 'Vui lòng nhập địa chỉ web',
        Default: 'Mặc định',
        Circle: 'Tròn rỗng',
        Dot: 'Chấm tròn',
        Quadrate: 'Ô vuông',
        Find: 'Tìm',
        'Find Previous': 'Tìm lùi',
        'Find Next': 'Tìm tiếp',
        'Insert className': 'Thêm class',
        'Press Alt for custom resizing': 'Giữ Alt để đổi kích thước tự do',
        'Embed code': 'Mã nhúng',
        Edit: 'Sửa',
        All: 'Tất cả',
        Caption: 'Chú thích ảnh',
        'Caption shown under the image': 'Chú thích hiển thị dưới ảnh (bỏ trống để xoá)',
    };

    const FONTS = {
        '': 'Default',
        'Arial, Helvetica, sans-serif': 'Arial',
        'Georgia, Palatino, serif': 'Georgia',
        'Impact, Charcoal, sans-serif': 'Impact',
        'Tahoma, Geneva, sans-serif': 'Tahoma',
        "'Times New Roman', Times, serif": 'Times New Roman',
        'Verdana, Geneva, sans-serif': 'Verdana',
        'Quicksand, sans-serif': 'Quicksand',
        'Roboto, sans-serif': 'Roboto',
        "'Courier New', Courier, monospace": 'Courier New',
    };

    const BLOCKS = {
        p: 'Normal',
        h1: 'Heading 1',
        h2: 'Heading 2',
        h3: 'Heading 3',
        h4: 'Heading 4',
        h5: 'Heading 5',
        h6: 'Heading 6',
        blockquote: 'Quote',
        pre: 'Code',
    };

    // Images come from the shared media library (layouts/media-picker), never as uploads or base64.
    const MEDIA_IMAGE = {
        name: 'mediaImage',
        icon: 'image',
        tooltip: 'Insert Image',
        exec: function (editor) { pickImage(editor); },
    };

    const PRESETS = {
        full: [
            'source', '|', 'paragraph', 'font', 'fontsize', '|',
            'bold', 'italic', 'underline', 'strikethrough', 'brush', '|',
            'superscript', 'subscript', '|',
            'ul', 'ol', 'outdent', 'indent', 'align', '|',
            'link', MEDIA_IMAGE, 'video', 'table', 'hr', 'symbols', '|',
            'eraser', 'copyformat', '|', 'undo', 'redo', 'find', 'fullsize',
        ],
        page: [
            'source', '|', 'paragraph', '|', 'bold', 'italic', 'underline', '|',
            'ul', 'ol', 'align', '|', 'link', MEDIA_IMAGE, 'table', 'hr', '|',
            'eraser', '|', 'undo', 'redo', 'fullsize',
        ],
        basic: [
            'paragraph', '|', 'bold', 'italic', 'underline', '|', 'ul', 'ol', 'align', '|',
            'link', MEDIA_IMAGE, '|', 'eraser', 'source', 'fullsize',
        ],
    };

    const MIN_HEIGHT = { full: 420, page: 360, basic: 180 };

    function pickImage(editor) {
        const range = editor.s.isFocused() ? editor.s.range.cloneRange() : null;
        document.querySelectorAll('input[data-editor-media-picker]').forEach(function (stale) { stale.remove(); });

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.className = 'd-none';
        input.dataset.editorMediaPicker = 'true';
        input.dataset.mediaFolder = editor.__adminMediaFolder || 'general';
        document.body.appendChild(input);

        input.addEventListener('media:selected', function (event) {
            input.remove();
            const url = event.detail && event.detail.url;
            if (!url) return;
            if (range) {
                editor.s.selectRange(range);
            } else {
                editor.s.focus();
                editor.s.setCursorIn(editor.editor, false);
            }
            editor.s.insertImage(url, null, null);
        }, { once: true });

        // The media picker intercepts clicks on image file inputs and opens the library.
        input.click();
    }

    // Image popup: Jodit's own buttons plus a caption, kept as <figure><figcaption>.
    function captionButton() {
        return {
            name: 'caption',
            icon: 'pencil',
            tooltip: 'Caption',
            exec: function (editor, image) {
                if (!image || image.nodeName !== 'IMG') return;
                const figure = image.closest('figure');
                const current = figure && figure.querySelector('figcaption');
                editor.prompt('Caption shown under the image', 'Caption', function (value) {
                    setCaption(editor, image, String(value || '').trim());
                }, '', current ? current.textContent.trim() : '');
            },
        };
    }

    function setCaption(editor, image, text) {
        let figure = image.closest('figure');
        if (!text) {
            if (figure) {
                figure.querySelectorAll('figcaption').forEach(function (caption) { caption.remove(); });
                figure.replaceWith.apply(figure, Array.from(figure.childNodes));
            }
            editor.synchronizeValues();
            return;
        }
        if (!figure) {
            // Move the image (with its link, if any) out of its paragraph into a figure.
            const node = image.parentElement && image.parentElement.nodeName === 'A' ? image.parentElement : image;
            const paragraph = node.parentElement && node.parentElement !== editor.editor && node.parentElement.nodeName === 'P'
                ? node.parentElement : null;
            figure = editor.createInside.element('figure');
            if (paragraph) {
                paragraph.after(figure);
                figure.appendChild(node);
                if (!paragraph.textContent.trim() && !paragraph.querySelector('img, iframe')) paragraph.remove();
            } else {
                node.replaceWith(figure);
                figure.appendChild(node);
            }
        }
        let caption = figure.querySelector('figcaption');
        if (!caption) {
            caption = editor.createInside.element('figcaption');
            figure.appendChild(caption);
        }
        caption.textContent = text;
        editor.synchronizeValues();
    }

    function stickyOffset() {
        const header = document.querySelector('.topbar, .app-header');
        return header ? header.offsetHeight : 0;
    }

    function config(element, preset) {
        const isVietnamese = (document.documentElement.lang || '').toLowerCase().startsWith('vi');

        return {
            language: isVietnamese ? 'vi' : 'en',
            i18n: { vi: VI },
            placeholder: element.dataset.placeholder || (isVietnamese ? VI['Type something'] : 'Type something'),
            buttons: PRESETS[preset],
            buttonsMD: PRESETS[preset],
            buttonsSM: PRESETS[preset],
            buttonsXS: PRESETS[preset],
            toolbarAdaptive: false,
            toolbarSticky: true,
            toolbarStickyOffset: stickyOffset(),
            height: 'auto',
            minHeight: Number(element.dataset.minHeight) || MIN_HEIGHT[preset],
            showCharsCounter: preset !== 'basic',
            showWordsCounter: preset !== 'basic',
            showXPathInStatusbar: false,
            hidePoweredByJodit: true,
            disablePlugins: ['about', 'print', 'file', 'ai-assistant', 'speech-recognize', 'preview'],
            // Pasted pages and Word documents keep their structure (headings, lists, links,
            // tables, images) but lose the source site's styles.
            askBeforePasteHTML: false,
            askBeforePasteFromWord: false,
            defaultActionOnPaste: 'insert_clear_html',
            defaultActionOnPasteFromWord: 'insert_clear_html',
            enableDragAndDropFileToEditor: false,
            uploader: { insertImageAsBase64URI: false },
            link: { noFollowCheckbox: true, openInNewTabCheckbox: true, modeClassName: false },
            image: {
                editId: false,
                editClass: false,
                editStyle: false,
                useImageEditor: false,
            },
            imageDefaultWidth: null,
            controls: {
                font: { list: Jodit.atom(FONTS) },
                paragraph: { list: Jodit.atom(BLOCKS) },
            },
            popup: {
                img: Jodit.atom(Jodit.defaultOptions.popup.img.concat([captionButton()])),
            },
        };
    }

    function isEmpty(html) {
        const probe = document.createElement('div');
        probe.innerHTML = html;
        return probe.textContent.trim() === '' && !probe.querySelector('img, iframe, video, table, hr');
    }

    function mount(element) {
        if (element.__richEditor) return element.__richEditor;

        const preset = PRESETS[element.dataset.preset] ? element.dataset.preset : 'full';
        const target = document.getElementById(element.dataset.target);
        const syncWhenEdited = element.dataset.sync === 'edited';
        const jodit = Jodit.make(element, config(element, preset));
        jodit.__adminMediaFolder = element.dataset.mediaFolder || 'general';

        const instance = {
            element: element,
            target: target,
            jodit: jodit,
            edited: false,
            getHTML: function () {
                const html = jodit.value;
                // An emptied editor still holds <p><br></p>; store it as no content.
                return isEmpty(html) ? '' : html;
            },
            getText: function () {
                const probe = document.createElement('div');
                probe.innerHTML = jodit.value;
                return (probe.innerText || probe.textContent || '').trim();
            },
            setHTML: function (html) {
                jodit.value = html || '';
            },
        };
        element.__richEditor = instance;

        const initial = jodit.value;
        jodit.events.on('change', function () {
            if (!instance.edited && jodit.value === initial) return;
            instance.edited = true;
            if (target) {
                target.disabled = false;
                target.value = instance.getHTML();
            }
            element.dispatchEvent(new CustomEvent('rich-editor:change', { bubbles: true, detail: { html: instance.getHTML() } }));
        });

        const form = target ? target.form : element.closest('form');
        if (form && target) {
            form.addEventListener('submit', function () {
                if (syncWhenEdited && !instance.edited) return;
                target.value = instance.getHTML();
            });
        }

        return instance;
    }

    function forTarget(targetId) {
        const element = document.querySelector('.rich-editor[data-target="' + CSS.escape(targetId) + '"]');
        return element ? mount(element) : null;
    }

    function mountAll(root) {
        (root || document).querySelectorAll('.rich-editor').forEach(mount);
    }

    window.AdminRichEditor = { mount: mount, mountAll: mountAll, forTarget: forTarget };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { mountAll(); });
    } else {
        mountAll();
    }
})();
