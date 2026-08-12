@push('styles')
    <style>
        /* Make editor text color dark and highly readable */
        .ql-editor {
            color: #111827 !important; /* Rich charcoal/dark black */
            font-size: 15.5px !important;
            line-height: 1.65 !important;
        }
        /* Make Quill placeholder dark grey instead of light grey */
        .ql-editor.ql-blank::before {
            color: #6b7280 !important;
            font-style: normal !important;
        }

        /* Fullscreen editor wrapper styles */
        #editor_wrapper.quill-fullscreen {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999 !important;
            background: #ffffff !important;
            padding: 20px 30px !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
        }
        #editor_wrapper.quill-fullscreen .ql-toolbar {
            border-top-left-radius: 8px !important;
            border-top-right-radius: 8px !important;
            background-color: #f8f9fa !important;
        }
        #editor_wrapper.quill-fullscreen .ql-container {
            flex-grow: 1 !important;
            height: calc(100vh - 120px) !important;
            background-color: #ffffff !important;
            border-bottom-left-radius: 8px !important;
            border-bottom-right-radius: 8px !important;
        }

        /* Custom fonts in editor */
        .ql-font-arial { font-family: Arial, sans-serif !important; }
        .ql-font-georgia { font-family: Georgia, serif !important; }
        .ql-font-impact { font-family: Impact, charcoal, sans-serif !important; }
        .ql-font-tahoma { font-family: Tahoma, Geneva, sans-serif !important; }
        .ql-font-times-new-roman { font-family: "Times New Roman", Times, serif !important; }
        .ql-font-verdana { font-family: Verdana, Geneva, sans-serif !important; }
        .ql-font-quicksand { font-family: "Quicksand", sans-serif !important; }
        .ql-font-roboto { font-family: "Roboto", sans-serif !important; }

        /* Custom fonts in toolbar picker label/items */
        .ql-snow .ql-picker.ql-font .ql-picker-label::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item::before {
            content: 'Sans Serif' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="serif"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="serif"]::before {
            content: 'Serif' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="monospace"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="monospace"]::before {
            content: 'Monospace' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="arial"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="arial"]::before {
            content: 'Arial' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="georgia"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="georgia"]::before {
            content: 'Georgia' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="impact"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="impact"]::before {
            content: 'Impact' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="tahoma"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="tahoma"]::before {
            content: 'Tahoma' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="times-new-roman"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="times-new-roman"]::before {
            content: 'Times New Roman' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="verdana"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="verdana"]::before {
            content: 'Verdana' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="quicksand"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="quicksand"]::before {
            content: 'Quicksand' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="roboto"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="roboto"]::before {
            content: 'Roboto' !important;
        }

        /* Show the preview of the fonts in the dropdown options list */
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="arial"] { font-family: Arial, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="georgia"] { font-family: Georgia, serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="impact"] { font-family: Impact, charcoal, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="tahoma"] { font-family: Tahoma, Geneva, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="times-new-roman"] { font-family: "Times New Roman", Times, serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="verdana"] { font-family: Verdana, Geneva, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="quicksand"] { font-family: "Quicksand", sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="roboto"] { font-family: "Roboto", sans-serif !important; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('admin-assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/quill/dist/quill.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/select2/dist/js/select2.full.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let isDirty = false;
            let targetUrl = null;
            const form = document.querySelector('form.admin-form-with-sticky-actions');

            // Register custom fonts in Quill
            const Font = Quill.import('formats/font');
            Font.whitelist = ['sans-serif', 'serif', 'monospace', 'arial', 'georgia', 'impact', 'tahoma', 'times-new-roman', 'verdana', 'quicksand', 'roboto'];
            Quill.register(Font, true);

            // Quill initialization
            document.querySelectorAll('.catalog-quill').forEach(function (editorElement) {
                const target = document.getElementById(editorElement.dataset.target);
                const quill = new Quill(editorElement, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'font': ['', 'serif', 'monospace', 'arial', 'georgia', 'impact', 'tahoma', 'times-new-roman', 'verdana', 'quicksand', 'roboto'] }, { 'size': [] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'script': 'sub' }, { 'script': 'super' }],
                            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                            [{ 'blockquote': true }, { 'code-block': true }],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }, { 'indent': '-1' }, { 'indent': '+1' }],
                            [{ 'align': [] }],
                            ['link', 'image', 'video'],
                            ['clean']
                        ]
                    }
                });
                editorElement.__quill = quill;

                quill.on('text-change', function() {
                    isDirty = true;
                    if (target) {
                        target.value = quill.root.innerHTML;
                    }
                });

                if (form && target) {
                    form.addEventListener('submit', function () {
                        target.value = quill.root.innerHTML;
                    });
                }

                // Fullscreen toggle logic
                const toggleBtn = document.getElementById('toggle_fullscreen');
                const editorWrapper = document.getElementById('editor_wrapper');
                const fullscreenIcon = document.getElementById('fullscreen_icon');
                const fullscreenText = document.getElementById('fullscreen_text');

                if (toggleBtn && editorWrapper) {
                    toggleBtn.addEventListener('click', function() {
                        const isFullscreen = editorWrapper.classList.toggle('quill-fullscreen');
                        if (isFullscreen) {
                            fullscreenIcon.className = 'ti ti-minimize fs-4';
                            fullscreenText.textContent = 'Thu nhỏ';
                            document.addEventListener('keydown', handleEsc);
                        } else {
                            fullscreenIcon.className = 'ti ti-maximize fs-4';
                            fullscreenText.textContent = 'Phóng to';
                            document.removeEventListener('keydown', handleEsc);
                        }
                    });

                    function handleEsc(e) {
                        if (e.key === 'Escape') {
                            editorWrapper.classList.remove('quill-fullscreen');
                            fullscreenIcon.className = 'ti ti-maximize fs-4';
                            fullscreenText.textContent = 'Phóng to';
                            document.removeEventListener('keydown', handleEsc);
                        }
                    }
                }
            });

            // Local Preview Uploader
            const fileInput = document.getElementById('product_image_file');
            const previewImg = document.getElementById('product_image_preview');
            const placeholder = document.getElementById('product_image_placeholder');
            const container = document.getElementById('product_image_preview_container');

            if (fileInput && previewImg) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        previewImg.src = URL.createObjectURL(file);
                        previewImg.classList.remove('d-none');
                        if (placeholder) placeholder.classList.add('d-none');
                        isDirty = true;
                    }
                });

                if (container) {
                    container.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        container.classList.add('bg-primary-subtle');
                    });

                    container.addEventListener('dragleave', function() {
                        container.classList.remove('bg-primary-subtle');
                    });

                    container.addEventListener('drop', function(e) {
                        e.preventDefault();
                        container.classList.remove('bg-primary-subtle');
                        const file = e.dataTransfer.files[0];
                        if (file) {
                            fileInput.files = e.dataTransfer.files;
                            previewImg.src = URL.createObjectURL(file);
                            previewImg.classList.remove('d-none');
                            if (placeholder) placeholder.classList.add('d-none');
                            isDirty = true;
                        }
                    });
                }
            }

            // Product gallery uploader
            const galleryItems = document.getElementById('product-gallery-items');
            const galleryAddTrigger = document.getElementById('gallery-add-trigger');
            const galleryFileInput = document.getElementById('gallery_image_file');
            let galleryIndex = galleryItems ? galleryItems.querySelectorAll('.gallery-item').length : 0;

            const galleryItemHtml = (index, url) => `<div class="gallery-item position-relative" data-index="${index}"><img src="${url}" class="rounded border" style="width:64px;height:64px;object-fit:cover;" alt=""><input type="hidden" name="gallery_images[${index}]" value="${url}"><button type="button" class="btn btn-sm btn-danger remove-gallery-image position-absolute top-0 end-0" style="padding:0 6px; line-height:1.6; border-radius:50%; transform:translate(30%,-30%);">×</button></div>`;

            if (galleryAddTrigger && galleryFileInput) {
                galleryAddTrigger.addEventListener('click', function () {
                    galleryFileInput.dataset.mediaSelectedField = `gallery_images[${galleryIndex}]`;
                    galleryFileInput.click();
                });
            }

            if (galleryItems) {
                galleryItems.addEventListener('click', function (event) {
                    if (event.target.closest('.remove-gallery-image')) {
                        event.target.closest('.gallery-item').remove();
                        isDirty = true;
                    }
                });
            }

            document.addEventListener('media:selected', function (event) {
                if (event.target !== galleryFileInput || !galleryItems) return;
                galleryItems.insertAdjacentHTML('beforeend', galleryItemHtml(galleryIndex, event.detail.url));
                galleryIndex++;
                isDirty = true;
            });

            // Track form dirty state
            if (form) {
                form.querySelectorAll('input, textarea, select').forEach(function(el) {
                    if (el.id !== 'product_image_file') {
                        el.addEventListener('input', function() {
                            isDirty = true;
                        });
                        el.addEventListener('change', function() {
                            isDirty = true;
                        });
                    }
                });

                form.addEventListener('submit', function() {
                    isDirty = false;
                });
            }

            // Intercept link clicks
            document.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    const href = link.getAttribute('href');
                    if (!href || href.startsWith('#') || href.startsWith('javascript') || link.getAttribute('target') === '_blank') {
                        return;
                    }

                    if (isDirty) {
                        e.preventDefault();
                        targetUrl = href;
                        const modal = new bootstrap.Modal(document.getElementById('unsavedChangesModal'));
                        modal.show();
                    }
                });
            });

            // Intercept window unload/refresh
            window.addEventListener('beforeunload', function(e) {
                if (isDirty) {
                    e.preventDefault();
                    e.returnValue = '{{ __('catalog.unsaved.unload_alert') }}';
                }
            });

            // Handle Modal Actions
            const btnDiscard = document.getElementById('btn-discard-changes');
            const btnSaveDraft = document.getElementById('btn-save-draft');

            if (btnDiscard) {
                btnDiscard.addEventListener('click', function() {
                    isDirty = false;
                    window.location.href = targetUrl;
                });
            }

            if (btnSaveDraft) {
                btnSaveDraft.addEventListener('click', function() {
                    isDirty = false;
                    const statusSelect = document.querySelector('select[name="is_active"]');
                    if (statusSelect) {
                        statusSelect.value = '0';
                    }
                    if (form) {
                        form.submit();
                    }
                });
            }

            if (window.jQuery) {
                $('.catalog-select2').select2({ width: '100%' });
            }

            const hasVariants = document.getElementById('has_variants');
            const setup = document.getElementById('create-variant-setup');
            const groups = document.getElementById('create-variant-groups');
            const addGroup = document.getElementById('add-create-group');
            const valueRow = (groupIndex, valueIndex) => `<div class="row g-2 create-variant-value align-items-end"><div class="col-md-4"><label class="form-label">Giá trị</label><input class="form-control" name="variant_groups[${groupIndex}][values][${valueIndex}][label]" required></div><div class="col-md-2"><label class="form-label">Mã màu</label><input class="form-control" name="variant_groups[${groupIndex}][values][${valueIndex}][color_hex]" placeholder="#FFFFFF"></div><div class="col-md-3"><label class="form-label">Ảnh</label><div class="variant-value-image-trigger form-control d-flex align-items-center gap-2" style="cursor:pointer; height:auto; min-height:calc(1.5em + 0.75rem + 2px);"><img class="variant-value-image-preview rounded d-none" style="width:32px;height:32px;object-fit:cover;" src="" alt=""><span class="variant-value-image-placeholder small text-muted">Chọn ảnh</span></div><input type="file" class="d-none variant-value-image-file" accept="image/*" data-media-folder="products" data-media-selected-field="variant_groups[${groupIndex}][values][${valueIndex}][image_url]"><input type="hidden" class="variant-value-image-url" name="variant_groups[${groupIndex}][values][${valueIndex}][image_url]" value=""></div><div class="col-md-1"><div class="form-check mb-2"><input type="hidden" name="variant_groups[${groupIndex}][values][${valueIndex}][is_active]" value="0"><input class="form-check-input" type="checkbox" name="variant_groups[${groupIndex}][values][${valueIndex}][is_active]" value="1" checked><label class="form-check-label">Bật</label></div></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-create-value">×</button></div></div>`;
            const groupCard = (groupIndex) => `<div class="border rounded p-3 create-variant-group" data-group-index="${groupIndex}"><div class="row align-items-end mb-3"><div class="col-md-6"><label class="form-label">Tên nhóm</label><input class="form-control" name="variant_groups[${groupIndex}][name]" required></div><div class="col-md-4"><label class="form-label">Kiểu hiển thị</label><select class="form-select" name="variant_groups[${groupIndex}][display_type]"><option value="select">Danh sách</option><option value="color">Màu sắc</option><option value="image">Ảnh</option></select></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-create-group">Xóa nhóm</button></div></div><div class="create-variant-values d-flex flex-column gap-2">${valueRow(groupIndex, 0)}</div><button type="button" class="btn btn-sm btn-outline-primary mt-3 add-create-value">+ Thêm giá trị</button></div>`;
            const addInitialGroup = () => {
                if (groups && groups.children.length === 0) groups.insertAdjacentHTML('beforeend', groupCard(0));
            };
            if (hasVariants && setup) {
                const toggleVariantFields = () => setup.querySelectorAll('input, select, textarea').forEach((field) => field.disabled = !hasVariants.checked);
                hasVariants.addEventListener('change', () => {
                    setup.classList.toggle('d-none', !hasVariants.checked);
                    if (hasVariants.checked) addInitialGroup();
                    toggleVariantFields();
                });
                if (hasVariants.checked) addInitialGroup();
                toggleVariantFields();
            }
            if (addGroup && groups) {
                addGroup.addEventListener('click', () => {
                    const indexes = [...groups.querySelectorAll('.create-variant-group')].map((group) => Number(group.dataset.groupIndex));
                    groups.insertAdjacentHTML('beforeend', groupCard(Math.max(-1, ...indexes) + 1));
                });
                groups.addEventListener('click', (event) => {
                    const group = event.target.closest('.create-variant-group');
                    if (!group) return;
                    if (event.target.closest('.add-create-value')) {
                        group.querySelector('.create-variant-values').insertAdjacentHTML('beforeend', valueRow(group.dataset.groupIndex, group.querySelectorAll('.create-variant-value').length));
                    }
                    if (event.target.closest('.remove-create-value')) event.target.closest('.create-variant-value').remove();
                    if (event.target.closest('.remove-create-group')) group.remove();
                    const imageTrigger = event.target.closest('.variant-value-image-trigger');
                    if (imageTrigger) imageTrigger.closest('.create-variant-value')?.querySelector('.variant-value-image-file')?.click();
                });
            }

            document.addEventListener('media:selected', function (event) {
                const input = event.target;
                if (!input.classList || !input.classList.contains('variant-value-image-file')) return;
                const row = input.closest('.create-variant-value');
                if (!row) return;
                const preview = row.querySelector('.variant-value-image-preview');
                const placeholder = row.querySelector('.variant-value-image-placeholder');
                if (preview) {
                    preview.src = event.detail.url;
                    preview.classList.remove('d-none');
                }
                if (placeholder) placeholder.classList.add('d-none');
                isDirty = true;
            });
        });
    </script>
@endpush
