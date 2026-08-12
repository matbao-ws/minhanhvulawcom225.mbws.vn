@php
    $clientAdmin = auth()->user();
    $showClientAdminBar = $clientAdmin
        && $clientAdmin->is_active
        && $clientAdmin->role_id !== null
        && $clientAdmin->can('manage_pages');
@endphp

{{--
    Convention for future dynamic (DB-driven) blocks on a CMS page: server-render
    the block with `contenteditable="false"` already set on its wrapper. A
    `contenteditable="false"` node nested inside the page's editable root is
    respected by the browser and stays out of the editable region — no extra JS
    needed. Known caveat: in some Chromium versions, pressing Backspace/Delete
    with the caret right next to such a node can delete the node itself instead
    of just skipping over it. Not an issue today (no dynamic blocks exist yet),
    but worth knowing before relying on it for anything destructive-sensitive.
--}}

@if($showClientAdminBar)
    <style>
        #client-admin-bar {
            align-items: center !important;
            background: #1f2937 !important;
            bottom: 18px !important;
            border: 1px solid rgba(255, 255, 255, .16) !important;
            border-radius: 12px !important;
            box-shadow: 0 12px 35px rgba(0, 0, 0, .28) !important;
            color: #fff !important;
            display: flex !important;
            font: 600 14px/1.2 Arial, sans-serif !important;
            gap: 6px !important;
            left: 50% !important;
            margin: 0 !important;
            max-width: calc(100vw - 24px) !important;
            padding: 7px !important;
            position: fixed !important;
            transform: translateX(-50%) !important;
            width: max-content !important;
            z-index: 2147483645 !important;
        }
        #client-admin-bar a,
        #client-admin-bar button {
            align-items: center !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 8px !important;
            color: #fff !important;
            cursor: pointer !important;
            display: inline-flex !important;
            font: inherit !important;
            gap: 6px !important;
            margin: 0 !important;
            padding: 9px 12px !important;
            text-decoration: none !important;
            white-space: nowrap !important;
        }
        #client-admin-bar a:hover,
        #client-admin-bar button:hover {
            background: rgba(255, 255, 255, .12) !important;
        }
        #client-admin-bar .client-admin-bar__edit {
            background: #5d87ff !important;
        }
        #client-admin-bar .client-admin-bar__edit.is-active {
            background: #087f5b !important;
        }
        #client-admin-bar .client-admin-bar__status {
            color: #dce7f3 !important;
            font-weight: 400 !important;
            padding: 0 6px !important;
        }
        #client-admin-bar form {
            display: inline !important;
            margin: 0 !important;
        }
        @media (max-width: 520px) {
            #client-admin-bar .client-admin-bar__label {
                display: none !important;
            }
        }
    </style>

    <nav id="client-admin-bar" aria-label="Công cụ quản trị">
        <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}">
            <span aria-hidden="true">⚙</span>
            <span class="client-admin-bar__label">Quản trị</span>
        </a>

        @isset($page)
            <a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page]) }}">
                <span aria-hidden="true">📝</span>
                <span class="client-admin-bar__label">Sửa tiêu đề/SEO</span>
            </a>
            <button
                type="button"
                id="client-inline-edit-button"
                class="client-admin-bar__edit"
            >
                <span aria-hidden="true">✎</span>
                <span class="client-admin-bar__label" id="client-inline-edit-label">Sửa trực tiếp</span>
            </button>
            <span id="client-inline-edit-status" class="client-admin-bar__status" role="status"></span>
        @endisset

        <form method="POST" action="{{ route('admin.logout', ['locale' => app()->getLocale()]) }}">
            @csrf
            <button type="submit">
                <span aria-hidden="true">↪</span>
                <span class="client-admin-bar__label">Đăng xuất</span>
            </button>
        </form>
    </nav>

    @isset($page)
        <style>
            .client-edit-mode {
                cursor: text !important;
            }
            .client-edit-mode:hover {
                outline: 2px dashed #5d87ff !important;
                outline-offset: 2px !important;
            }
            .client-edit-mode img {
                cursor: pointer !important;
            }
            .client-edit-mode img:hover {
                outline: 2px dashed #087f5b !important;
                outline-offset: 2px !important;
            }
            .client-edit-mode [contenteditable="false"] {
                cursor: default !important;
                outline: none !important;
            }
            @can('manage_media')
            #client-inline-media-picker {
                align-items: center !important;
                background: rgba(10, 18, 28, .72) !important;
                display: none;
                inset: 0 !important;
                justify-content: center !important;
                padding: 24px !important;
                position: fixed !important;
                z-index: 2147483646 !important;
            }
            #client-inline-media-picker.is-open {
                display: flex !important;
            }
            #client-inline-media-dialog {
                background: #fff !important;
                border-radius: 12px !important;
                box-shadow: 0 24px 70px rgba(0, 0, 0, .35) !important;
                color: #243447 !important;
                display: flex !important;
                flex-direction: column !important;
                font: 14px/1.4 Arial, sans-serif !important;
                margin: auto !important;
                max-height: calc(100vh - 48px) !important;
                max-width: 1120px !important;
                overflow: hidden !important;
                width: 100% !important;
            }
            #client-inline-media-head,
            #client-inline-media-tools,
            #client-inline-media-pagination {
                align-items: center !important;
                display: flex !important;
                gap: 10px !important;
                justify-content: space-between !important;
                padding: 14px 18px !important;
            }
            #client-inline-media-head {
                border-bottom: 1px solid #e5e9ef !important;
            }
            #client-inline-media-head h2 {
                color: #243447 !important;
                font: 700 18px/1.2 Arial, sans-serif !important;
                margin: 0 !important;
            }
            #client-inline-media-tools {
                flex-wrap: wrap !important;
                padding-bottom: 4px !important;
            }
            #client-inline-media-tools select,
            #client-inline-media-tools button,
            #client-inline-media-head button,
            #client-inline-media-pagination button {
                background: #fff !important;
                border: 1px solid #cfd7e2 !important;
                border-radius: 7px !important;
                color: #243447 !important;
                cursor: pointer !important;
                font: 600 14px/1.2 Arial, sans-serif !important;
                padding: 9px 12px !important;
            }
            #client-inline-media-tools .client-inline-media-upload {
                background: #5d87ff !important;
                border-color: #5d87ff !important;
                color: #fff !important;
            }
            #client-inline-media-grid {
                display: grid !important;
                gap: 8px !important;
                grid-auto-rows: 150px !important;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
                min-height: 240px !important;
                overflow: auto !important;
                padding: 14px 18px !important;
            }
            #client-inline-media-grid button {
                background: #fff !important;
                border: 1px solid #d9e0e8 !important;
                border-radius: 9px !important;
                color: #243447 !important;
                cursor: pointer !important;
                display: flex !important;
                flex-direction: column !important;
                min-width: 0 !important;
                overflow: hidden !important;
                padding: 0 !important;
                text-align: left !important;
            }
            #client-inline-media-grid img {
                background: #f4f6f9 !important;
                display: block !important;
                flex: 0 0 92px !important;
                height: 92px !important;
                object-fit: contain !important;
                padding: 4px !important;
                width: 100% !important;
            }
            #client-inline-media-grid span {
                display: block !important;
                font-size: 12px !important;
                overflow: hidden !important;
                padding: 7px 8px 2px !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            #client-inline-media-grid small {
                color: #64748b !important;
                display: block !important;
                font: 11px/1.2 Arial, sans-serif !important;
                overflow: hidden !important;
                padding: 2px 8px 7px !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            #client-inline-media-state {
                color: #64748b !important;
                padding: 30px 18px !important;
                text-align: center !important;
            }
            #client-inline-media-state.is-error {
                color: #c92a2a !important;
            }
            #client-inline-media-pagination {
                border-top: 1px solid #e5e9ef !important;
            }
            #client-inline-media-pagination button:disabled {
                cursor: not-allowed !important;
                opacity: .45 !important;
            }
            @media (max-width: 780px) {
                #client-inline-media-picker {
                    padding: 8px !important;
                }
                #client-inline-media-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                }
            }
            @endcan
        </style>

        @can('manage_media')
            <aside id="client-inline-media-picker" aria-hidden="true">
                <div id="client-inline-media-dialog" role="dialog" aria-modal="true" aria-labelledby="client-inline-media-title">
                    <div id="client-inline-media-head">
                        <h2 id="client-inline-media-title">Chọn ảnh thay thế</h2>
                        <button type="button" id="client-inline-media-close" aria-label="Đóng">Đóng</button>
                    </div>
                    <div id="client-inline-media-tools">
                        <select id="client-inline-media-folder" aria-label="Thư mục ảnh">
                            <option value="all">Tất cả thư mục</option>
                            @foreach(app(\App\Services\CloudinaryService::class)->listFolders() as $folder)
                                <option value="{{ $folder }}">{{ ucfirst($folder) }}</option>
                            @endforeach
                        </select>
                        <div>
                            <input type="file" id="client-inline-media-upload" accept="image/*" hidden>
                            <button type="button" class="client-inline-media-upload" id="client-inline-media-upload-button">
                                Thêm ảnh mới
                            </button>
                        </div>
                    </div>
                    <div id="client-inline-media-state">Đang tải thư viện ảnh…</div>
                    <div id="client-inline-media-grid"></div>
                    <div id="client-inline-media-pagination">
                        <button type="button" id="client-inline-media-previous">← Trước</button>
                        <span id="client-inline-media-page">Trang 1</span>
                        <button type="button" id="client-inline-media-next">Sau →</button>
                    </div>
                </div>
            </aside>
        @endcan

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButton = document.getElementById('client-inline-edit-button');
            const toggleLabel = document.getElementById('client-inline-edit-label');
            const statusLabel = document.getElementById('client-inline-edit-status');
            const pageContent = document.getElementById(@json('client-page-'.$page->id));
            const saveUrl = @json(route('admin.pages.inline-update', ['locale' => app()->getLocale(), 'page' => $page]));
            const csrfToken = @json(csrf_token());
            const contentLocale = @json(app()->getLocale());

            let lastSavedHtml = pageContent.innerHTML;
            let editModeOn = false;
            let savePromise = Promise.resolve();
            let statusResetTimer = null;

            function isDirty() {
                return pageContent.innerHTML !== lastSavedHtml;
            }

            function setStatus(text) {
                if (statusResetTimer) {
                    window.clearTimeout(statusResetTimer);
                    statusResetTimer = null;
                }
                statusLabel.textContent = text;
                if (text === 'Đã lưu') {
                    statusResetTimer = window.setTimeout(function () {
                        statusLabel.textContent = '';
                    }, 1500);
                }
            }

            function doSave() {
                if (!isDirty()) return Promise.resolve();
                setStatus('Đang lưu…');
                const html = pageContent.innerHTML;

                return fetch(saveUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ content_locale: contentLocale, published_html: html }),
                }).then(async function (response) {
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                        throw new Error(errors || payload.message || 'Không thể lưu trang.');
                    }
                    lastSavedHtml = payload.data.html || '';
                    pageContent.innerHTML = lastSavedHtml;
                    setStatus('Đã lưu');
                }).catch(function (error) {
                    setStatus(error.message || 'Không thể lưu trang.');
                });
            }

            function saveIfDirty() {
                savePromise = savePromise.then(doSave, doSave);
                return savePromise;
            }

            function enableEditMode() {
                editModeOn = true;
                pageContent.setAttribute('contenteditable', 'true');
                pageContent.classList.add('client-edit-mode');
                try {
                    document.execCommand('enableObjectResizing', false, false);
                    document.execCommand('defaultParagraphSeparator', false, 'p');
                } catch (error) {
                    // Deprecated APIs — best-effort only, safe to ignore if unsupported.
                }
                toggleButton.classList.add('is-active');
                toggleLabel.textContent = 'Tắt chỉnh sửa';
            }

            function disableEditMode() {
                saveIfDirty().finally(function () {
                    editModeOn = false;
                    pageContent.setAttribute('contenteditable', 'false');
                    pageContent.classList.remove('client-edit-mode');
                    toggleButton.classList.remove('is-active');
                    toggleLabel.textContent = 'Sửa trực tiếp';
                });
            }

            toggleButton.addEventListener('click', function () {
                if (editModeOn) {
                    disableEditMode();
                } else {
                    enableEditMode();
                }
            });

            pageContent.addEventListener('focusout', function () {
                window.setTimeout(function () {
                    if (editModeOn && !pageContent.contains(document.activeElement)) {
                        saveIfDirty();
                    }
                }, 0);
            });

            window.addEventListener('pagehide', function () {
                if (!editModeOn || !isDirty()) return;
                // Best-effort only: keepalive requests are capped at a small payload
                // size by the browser and may silently fail for very large pages.
                fetch(saveUrl, {
                    method: 'PATCH',
                    keepalive: true,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ content_locale: contentLocale, published_html: pageContent.innerHTML }),
                });
            });

            @can('manage_media')
            const mediaPicker = document.getElementById('client-inline-media-picker');
            const mediaFolder = document.getElementById('client-inline-media-folder');
            const mediaGrid = document.getElementById('client-inline-media-grid');
            const mediaState = document.getElementById('client-inline-media-state');
            const mediaPrevious = document.getElementById('client-inline-media-previous');
            const mediaNext = document.getElementById('client-inline-media-next');
            const mediaPage = document.getElementById('client-inline-media-page');
            const mediaUpload = document.getElementById('client-inline-media-upload');
            const mediaResourcesUrl = @json(route('admin.media.resources', ['locale' => app()->getLocale()]));
            const mediaUploadUrl = @json(route('admin.media.upload', ['locale' => app()->getLocale()]));
            let selectedImage = null;
            let mediaCursors = [null];
            let mediaPageIndex = 0;
            let mediaNextCursor = null;

            function setMediaState(text, isError) {
                mediaState.textContent = text;
                mediaState.classList.toggle('is-error', Boolean(isError));
                mediaState.style.display = text ? 'block' : 'none';
            }

            function renderMedia(resources, nextCursor) {
                mediaGrid.innerHTML = '';
                mediaNextCursor = nextCursor || null;
                mediaPrevious.disabled = mediaPageIndex === 0;
                mediaNext.disabled = !mediaNextCursor;
                mediaPage.textContent = 'Trang ' + (mediaPageIndex + 1);
                setMediaState(resources.length ? '' : 'Chưa có ảnh trong thư viện.', false);

                resources.forEach(function (resource) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.title = 'Chọn ' + resource.public_id;
                    const image = document.createElement('img');
                    image.src = resource.secure_url;
                    image.alt = resource.public_id;
                    const name = document.createElement('span');
                    name.textContent = resource.public_id.split('/').pop();
                    const dimensions = document.createElement('small');
                    dimensions.textContent = resource.width && resource.height
                        ? resource.width + ' × ' + resource.height + ' px'
                        : (resource.format || 'image').toUpperCase();
                    button.append(image, name, dimensions);
                    button.addEventListener('click', function () {
                        if (!selectedImage) return;
                        selectedImage.setAttribute('src', resource.secure_url);
                        closeMediaPicker();
                        saveIfDirty();
                    });
                    mediaGrid.appendChild(button);
                });
            }

            async function loadMediaResources() {
                setMediaState('Đang tải thư viện ảnh…', false);
                mediaGrid.innerHTML = '';
                const query = new URLSearchParams({
                    folder: mediaFolder.value,
                    _: Date.now().toString(),
                });
                const cursor = mediaCursors[mediaPageIndex];
                if (cursor) query.set('cursor', cursor);

                try {
                    const response = await fetch(mediaResourcesUrl + '?' + query.toString(), {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error('Không thể tải thư viện ảnh.');
                    const payload = await response.json();
                    renderMedia(payload.resources || [], payload.next_cursor);
                } catch (error) {
                    setMediaState(error.message || 'Không thể tải thư viện ảnh.', true);
                }
            }

            function openMediaPickerFor(img) {
                selectedImage = img;
                mediaPicker.classList.add('is-open');
                mediaPicker.setAttribute('aria-hidden', 'false');
                mediaCursors = [null];
                mediaPageIndex = 0;
                loadMediaResources();
            }

            function closeMediaPicker() {
                mediaPicker.classList.remove('is-open');
                mediaPicker.setAttribute('aria-hidden', 'true');
                selectedImage = null;
            }

            document.getElementById('client-inline-media-close').addEventListener('click', closeMediaPicker);
            mediaFolder.addEventListener('change', function () {
                mediaCursors = [null];
                mediaPageIndex = 0;
                loadMediaResources();
            });
            mediaPrevious.addEventListener('click', function () {
                if (mediaPageIndex === 0) return;
                mediaPageIndex--;
                loadMediaResources();
            });
            mediaNext.addEventListener('click', function () {
                if (!mediaNextCursor) return;
                mediaCursors = mediaCursors.slice(0, mediaPageIndex + 1);
                mediaCursors.push(mediaNextCursor);
                mediaPageIndex++;
                loadMediaResources();
            });
            document.getElementById('client-inline-media-upload-button').addEventListener('click', function () {
                mediaUpload.click();
            });
            mediaUpload.addEventListener('change', async function () {
                if (!mediaUpload.files[0]) return;
                setMediaState('Đang tải ảnh lên…', false);
                const formData = new FormData();
                formData.append('file', mediaUpload.files[0]);
                formData.append('folder', mediaFolder.value === 'all' ? 'general' : mediaFolder.value);
                formData.append('image_only', '1');

                try {
                    const response = await fetch(mediaUploadUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                        throw new Error(validationError || payload.message || 'Tải ảnh lên không thành công.');
                    }
                    mediaCursors = [null];
                    mediaPageIndex = 0;
                    await loadMediaResources();
                } catch (error) {
                    setMediaState(error.message || 'Tải ảnh lên không thành công.', true);
                } finally {
                    mediaUpload.value = '';
                }
            });

            pageContent.addEventListener('click', function (event) {
                if (!editModeOn) return;
                const img = event.target.closest('img');
                if (img && !img.closest('[contenteditable="false"]')) {
                    event.preventDefault();
                    openMediaPickerFor(img);
                    return;
                }
                const link = event.target.closest('a');
                if (link) event.preventDefault();
            });
            @else
            pageContent.addEventListener('click', function (event) {
                if (!editModeOn) return;
                const link = event.target.closest('a');
                if (link) event.preventDefault();
            });
            @endcan
        });
        </script>
    @endisset
@endif
