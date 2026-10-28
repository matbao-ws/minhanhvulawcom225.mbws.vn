@include('admin.shared.rich-editor-assets')

@push('scripts')
    <script src="{{ \App\Support\AssetVersion::url('admin-assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/select2/dist/js/select2.full.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form.admin-form-with-sticky-actions');
            let isDirty = false;

            // Initialize Select2
            if (jQuery().select2) {
                $('.select2-select').select2({
                    minimumResultsForSearch: 5
                });
            }

            // Slug auto-generation from Title
            const titleInput = document.getElementById('title');
            const slugInput = document.getElementById('slug');

            function generateSlug(text) {
                // Vietnamese letters, written as \u escapes so this admin view stays free of Vietnamese text.
                return text.toString().toLowerCase()
                    .replace(/\u00e1|\u00e0|\u1ea3|\u00e3|\u1ea1|\u0103|\u1eaf|\u1eb1|\u1eb3|\u1eb5|\u1eb7|\u00e2|\u1ea5|\u1ea7|\u1ea9|\u1eab|\u1ead/g, 'a')
                    .replace(/\u00e9|\u00e8|\u1ebb|\u1ebd|\u1eb9|\u00ea|\u1ebf|\u1ec1|\u1ec3|\u1ec5|\u1ec7/g, 'e')
                    .replace(/\u00ed|\u00ec|\u1ec9|\u0129|\u1ecb/g, 'i')
                    .replace(/\u00f3|\u00f2|\u1ecf|\u00f5|\u1ecd|\u00f4|\u1ed1|\u1ed3|\u1ed5|\u1ed7|\u1ed9|\u01a1|\u1edb|\u1edd|\u1edf|\u1ee1|\u1ee3/g, 'o')
                    .replace(/\u00fa|\u00f9|\u1ee7|\u0169|\u1ee5|\u01b0|\u1ee9|\u1eeb|\u1eed|\u1eef|\u1ef1/g, 'u')
                    .replace(/\u00fd|\u1ef3|\u1ef7|\u1ef9|\u1ef5/g, 'y')
                    .replace(/\u0111/g, 'd')
                    .replace(/\s+/g, '-')           // Replace spaces with -
                    .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
                    .replace(/\-\-+/g, '-')         // Replace multiple - with single -
                    .replace(/^-+/, '')             // Trim - from start of text
                    .replace(/-+$/, '');            // Trim - from end of text
            }

            if (titleInput && slugInput) {
                titleInput.addEventListener('input', function () {
                    if (!slugInput.dataset.manual) {
                        slugInput.value = generateSlug(titleInput.value);
                        slugInput.dispatchEvent(new Event('input'));
                    }
                });

                slugInput.addEventListener('change', function () {
                    slugInput.dataset.manual = 'true';
                });
            }

            // Local Preview Uploader for Featured Image
            const fileInput = document.getElementById('post_image_file');
            const previewImg = document.getElementById('post_image_preview');
            const placeholder = document.getElementById('post_image_placeholder');
            const container = document.getElementById('post_image_preview_container');

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
            }
        });
    </script>
@endpush
