<style>
    #adminMediaPickerGrid .media-picker-card {
        height: 150px;
        min-width: 0;
    }
    #adminMediaPickerGrid .media-picker-thumbnail {
        background: #f4f6f9;
        height: 92px;
        object-fit: contain;
        padding: 4px;
        width: 100%;
    }
    #adminMediaPickerGrid .media-picker-name {
        font-size: 12px;
        line-height: 1.2;
    }
    #adminMediaPickerGrid .media-picker-dimensions {
        font-size: 11px;
        line-height: 1.2;
    }
</style>

<div class="modal fade" id="adminMediaPicker" tabindex="-1" aria-labelledby="adminMediaPickerLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="adminMediaPickerLabel">Chọn ảnh từ thư viện</h5>
                    <p class="text-muted fs-2 mb-0">Chọn một ảnh đã tải lên hoặc thêm ảnh mới.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                    <select class="form-select w-auto" id="adminMediaPickerFolder" aria-label="Thư mục ảnh">
                        <option value="all">Tất cả thư mục</option>
                        @foreach(app(\App\Services\CloudinaryService::class)->listFolders() as $folder)
                            <option value="{{ $folder }}">{{ ucfirst($folder) }}</option>
                        @endforeach
                    </select>
                    <div>
                        <input class="d-none" id="adminMediaPickerUpload" type="file" accept="image/*">
                        <button class="btn btn-primary" type="button" id="adminMediaPickerUploadButton">
                            <i class="ti ti-upload me-1"></i>Thêm ảnh mới
                        </button>
                    </div>
                </div>
                <div class="alert alert-danger d-none mb-3" id="adminMediaPickerError" role="alert"></div>
                <div class="row row-cols-3 row-cols-sm-4 row-cols-md-6 row-cols-xl-8 g-2" id="adminMediaPickerGrid"></div>
                <div class="text-center text-muted py-5 d-none" id="adminMediaPickerEmpty">Chưa có ảnh trong thư viện.</div>
                <div class="text-center py-5" id="adminMediaPickerLoading"><div class="spinner-border text-primary" role="status"></div></div>
                <div class="d-flex justify-content-between align-items-center mt-4 d-none" id="adminMediaPickerPagination">
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="adminMediaPickerPrevious">← Trước</button>
                    <span class="small text-muted" id="adminMediaPickerPage">Trang 1</span>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="adminMediaPickerNext">Sau →</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('adminMediaPicker');
        if (!modalElement || typeof bootstrap === 'undefined') return;

        const modal = new bootstrap.Modal(modalElement);
        const folder = document.getElementById('adminMediaPickerFolder');
        const upload = document.getElementById('adminMediaPickerUpload');
        const grid = document.getElementById('adminMediaPickerGrid');
        const loading = document.getElementById('adminMediaPickerLoading');
        const empty = document.getElementById('adminMediaPickerEmpty');
        const error = document.getElementById('adminMediaPickerError');
        const pagination = document.getElementById('adminMediaPickerPagination');
        const previous = document.getElementById('adminMediaPickerPrevious');
        const next = document.getElementById('adminMediaPickerNext');
        const pageLabel = document.getElementById('adminMediaPickerPage');
        let activeInput = null;
        let cursors = [null];
        let pageIndex = 0;
        let nextCursor = null;

        const inputFolder = (input) => input.dataset.mediaFolder || ({ image_file: 'general', avatar_file: 'avatars' }[input.name] || 'general');
        const selectedField = (input) => input.dataset.mediaSelectedField || (input.name === 'avatar_file' ? 'avatar_url' : 'image_url');

        function showError(message) {
            error.textContent = message;
            error.classList.remove('d-none');
        }

        function clearError() { error.classList.add('d-none'); }

        function render(resources, newNextCursor) {
            grid.innerHTML = '';
            empty.classList.toggle('d-none', resources.length !== 0);
            nextCursor = newNextCursor || null;
            pagination.classList.toggle('d-none', resources.length === 0);
            previous.disabled = pageIndex === 0;
            next.disabled = !nextCursor;
            pageLabel.textContent = 'Trang ' + (pageIndex + 1);
            resources.forEach(function (resource) {
                const column = document.createElement('div');
                column.className = 'col';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'media-picker-card btn p-0 border rounded overflow-hidden w-100 text-start bg-white d-flex flex-column';
                button.title = 'Chọn ' + resource.public_id;
                const image = document.createElement('img');
                image.src = resource.secure_url;
                image.alt = resource.public_id;
                image.className = 'media-picker-thumbnail d-block flex-shrink-0';
                const name = document.createElement('div');
                name.className = 'media-picker-name px-2 pt-2 text-truncate';
                name.textContent = resource.public_id.split('/').pop();
                const dimensions = document.createElement('div');
                dimensions.className = 'media-picker-dimensions px-2 pb-2 pt-1 text-muted text-truncate';
                dimensions.textContent = resource.width && resource.height
                    ? resource.width + ' × ' + resource.height + ' px'
                    : (resource.format || 'image').toUpperCase();
                button.append(image, name, dimensions);
                button.addEventListener('click', function () { select(resource.secure_url); });
                column.appendChild(button);
                grid.appendChild(column);
            });
        }

        function loadResources() {
            clearError();
            loading.classList.remove('d-none');
            grid.innerHTML = '';
            empty.classList.add('d-none');
            const cursor = cursors[pageIndex];
            const query = new URLSearchParams({ folder: folder.value, _: Date.now().toString() });
            if (cursor) query.set('cursor', cursor);
            fetch('{{ route('admin.media.resources') }}?' + query.toString(), {
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            })
                .then(function (response) { if (!response.ok) throw new Error('Không thể tải thư viện ảnh.'); return response.json(); })
                .then(function (data) { render(data.resources || [], data.next_cursor); })
                .catch(function (exception) { showError(exception.message); })
                .finally(function () { loading.classList.add('d-none'); });
        }

        function select(url) {
            if (!activeInput) return;
            const form = activeInput.closest('form');
            if (form) {
                let targets = Array.from(form.querySelectorAll('[name="' + selectedField(activeInput) + '"]'));
                if (targets.length === 0) {
                    const target = document.createElement('input');
                    target.type = 'hidden';
                    target.name = selectedField(activeInput);
                    form.appendChild(target);
                    targets = [target];
                }
                targets.forEach(function (target) {
                    target.value = url;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
            activeInput.dispatchEvent(new CustomEvent('media:selected', { bubbles: true, detail: { url: url } }));
            if (modalElement.contains(document.activeElement)) {
                document.activeElement.blur();
            }
            modal.hide();
        }

        document.addEventListener('media:selected', function (event) {
            const input = event.target;
            const formPreview = input.closest('form')?.querySelector('[data-media-preview]');
            if (formPreview) {
                const image = formPreview.querySelector('[data-media-preview-image]');
                if (image) image.src = event.detail.url;
                formPreview.classList.remove('d-none');
            }
            const previews = {
                product_image_file: ['product_image_preview', 'product_image_placeholder'],
                post_image_file: ['post_image_preview', 'post_image_placeholder'],
                user_avatar_file: ['user_avatar_preview'],
                image_file: ['imagePreview'],
                quick_image_file: ['quickImagePreview'],
            };
            const ids = previews[input.id];
            if (!ids) return;
            const image = document.getElementById(ids[0]);
            if (image) {
                image.src = event.detail.url;
                image.classList.remove('d-none');
            }
            if (ids[1]) document.getElementById(ids[1])?.classList.add('d-none');
            if (input.id === 'image_file') document.getElementById('imagePreviewContainer')?.style.setProperty('display', 'block');
            if (input.id === 'quick_image_file') document.getElementById('quickImagePreviewWrap')?.classList.remove('d-none');
        });

        document.addEventListener('click', function (event) {
            let input = event.target.closest('input[type="file"][accept*="image"]');
            if (!input) {
                const label = event.target.closest('label[for]');
                input = label ? document.getElementById(label.htmlFor) : null;
            }
            if (!input || input.id === 'adminMediaPickerUpload' || input.closest('#adminMediaPicker')) return;
            if (input.type !== 'file' || !input.accept.includes('image')) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            activeInput = input;
            folder.value = inputFolder(input);
            cursors = [null];
            pageIndex = 0;
            loadResources();
            modal.show();
        }, true);

        folder.addEventListener('change', function () {
            cursors = [null];
            pageIndex = 0;
            loadResources();
        });
        previous.addEventListener('click', function () {
            if (pageIndex === 0) return;
            pageIndex--;
            loadResources();
        });
        next.addEventListener('click', function () {
            if (!nextCursor) return;
            cursors = cursors.slice(0, pageIndex + 1);
            cursors.push(nextCursor);
            pageIndex++;
            loadResources();
        });
        document.getElementById('adminMediaPickerUploadButton').addEventListener('click', function () { upload.click(); });
        upload.addEventListener('change', function () {
            if (!upload.files[0]) return;
            clearError();
            const data = new FormData();
            data.append('file', upload.files[0]);
            data.append('folder', activeInput ? inputFolder(activeInput) : 'general');
            data.append('image_only', '1');
            fetch('{{ route('admin.media.upload') }}', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: data,
            })
                .then(async function (response) {
                    const data = await response.json();
                    if (!response.ok) {
                        const validationError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        throw new Error(validationError || data.message || 'Tải ảnh lên không thành công.');
                    }
                    return data;
                })
                .then(function (data) {
                    if (!data.success) throw new Error(data.message || 'Tải ảnh lên không thành công.');
                    loadResources();
                })
                .catch(function (exception) { showError(exception.message); })
                .finally(function () { upload.value = ''; });
        });
    });
</script>
