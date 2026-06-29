@push('scripts')
    <script src="{{ asset('admin-assets/js/vendor.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let isDirty = false;
            let targetUrl = null;
            const form = document.querySelector('form.admin-form-with-sticky-actions');

            // Local Preview Uploader
            const fileInput = document.getElementById('user_avatar_file');
            const previewImg = document.getElementById('user_avatar_preview');
            const container = document.getElementById('user_avatar_preview_container');

            if (fileInput && previewImg) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        previewImg.src = URL.createObjectURL(file);
                        isDirty = true;
                    }
                });

                if (container) {
                    container.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        container.classList.add('border-primary');
                    });

                    container.addEventListener('dragleave', function() {
                        container.classList.remove('border-primary');
                    });

                    container.addEventListener('drop', function(e) {
                        e.preventDefault();
                        container.classList.remove('border-primary');
                        const file = e.dataTransfer.files[0];
                        if (file) {
                            fileInput.files = e.dataTransfer.files;
                            previewImg.src = URL.createObjectURL(file);
                            isDirty = true;
                        }
                    });
                }
            }

            // Track form dirty state
            if (form) {
                form.querySelectorAll('input, select, textarea').forEach(function(el) {
                    if (el.id !== 'user_avatar_file') {
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
                    e.returnValue = "{{ __('admin.users.unsaved.unload_alert') }}";
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
                    const statusCheckbox = document.getElementById('is_active');
                    if (statusCheckbox) {
                        statusCheckbox.checked = false; // Set unchecked to save as inactive (draft)
                    }
                    if (form) {
                        form.submit();
                    }
                });
            }
        });
    </script>
@endpush
