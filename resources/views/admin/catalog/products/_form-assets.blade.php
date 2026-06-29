@push('scripts')
    <script src="{{ asset('admin-assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/quill/dist/quill.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/dropzone/dist/min/dropzone.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/select2/dist/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/jquery.repeater/jquery.repeater.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let isDirty = false;
            let targetUrl = null;
            const form = document.querySelector('form.admin-form-with-sticky-actions');

            // Quill initialization
            document.querySelectorAll('.catalog-quill').forEach(function (editorElement) {
                const target = document.getElementById(editorElement.dataset.target);
                const quill = new Quill(editorElement, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['link', 'clean']
                        ]
                    }
                });

                quill.on('text-change', function() {
                    isDirty = true;
                });

                if (form && target) {
                    form.addEventListener('submit', function () {
                        target.value = quill.root.innerHTML;
                    });
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

            if (window.Dropzone) {
                Dropzone.autoDiscover = false;
            }

            if (window.jQuery) {
                $('.catalog-select2').select2({ width: '100%' });
                $('.email-repeater').repeater({
                    show: function () {
                        $(this).slideDown();
                    },
                    hide: function (deleteElement) {
                        $(this).slideUp(deleteElement);
                    }
                });
            }
        });
    </script>
@endpush
