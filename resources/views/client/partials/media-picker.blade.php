@php
    $canReplaceImages = (bool) (auth()->user()?->canEditClientContent() && auth()->user()?->can('media.view'));
@endphp

@if ($canReplaceImages)
    {{--
        Storefront media picker, shared by the inline editors.

        Exposes window.clientMediaPicker.open(onSelect) so an editor only has to
        say what to do with the chosen URL. Reads and uploads go through the
        protected admin media routes — never a public endpoint.
    --}}
    <style>
        #client-media-picker {
            align-items: center !important;
            background: rgba(15, 23, 42, 0.4) !important;
            backdrop-filter: blur(8px) !important;
            display: none;
            inset: 0 !important;
            justify-content: center !important;
            padding: 24px !important;
            position: fixed !important;
            z-index: 2147483646 !important;
        }

        #client-media-picker.is-open {
            display: flex !important;
        }

        #client-media-dialog {
            background: #fff !important;
            border-radius: 12px !important;
            box-shadow: 0 24px 70px rgba(0, 0, 0, .35) !important;
            color: #243447 !important;
            display: flex !important;
            flex-direction: column !important;
            font: 14px/1.4 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            margin: auto !important;
            max-height: calc(100vh - 48px) !important;
            max-width: 1120px !important;
            overflow: hidden !important;
            width: 100% !important;
        }

        #client-media-head,
        #client-media-tools,
        #client-media-pagination {
            align-items: center !important;
            display: flex !important;
            gap: 10px !important;
            justify-content: space-between !important;
            padding: 14px 18px !important;
        }

        #client-media-head {
            border-bottom: 1px solid #e5e9ef !important;
        }

        #client-media-head h2 {
            color: #243447 !important;
            font: 700 18px/1.2 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            margin: 0 !important;
        }

        #client-media-tools {
            flex-wrap: wrap !important;
            padding-bottom: 4px !important;
        }

        #client-media-tools select,
        #client-media-tools button,
        #client-media-head button,
        #client-media-pagination button {
            background: #fff !important;
            border: 1px solid #cfd7e2 !important;
            border-radius: 7px !important;
            color: #243447 !important;
            cursor: pointer !important;
            font: 600 14px/1.2 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif !important;
            padding: 9px 12px !important;
        }

        #client-media-tools .client-media-upload {
            background: #5d87ff !important;
            border-color: #5d87ff !important;
            color: #fff !important;
        }

        #client-media-grid {
            display: grid !important;
            gap: 8px !important;
            grid-auto-rows: 150px !important;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
            min-height: 240px !important;
            overflow: auto !important;
            padding: 14px 18px !important;
        }

        #client-media-grid button {
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

        #client-media-grid img,
        #client-media-grid video {
            background: #f4f6f9 !important;
            display: block !important;
            flex: 0 0 92px !important;
            height: 92px !important;
            object-fit: contain !important;
            padding: 4px !important;
            width: 100% !important;
        }

        #client-media-grid span {
            display: block !important;
            font-size: 12px !important;
            overflow: hidden !important;
            padding: 7px 8px 2px !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        #client-media-state {
            color: #64748b !important;
            padding: 30px 18px !important;
            text-align: center !important;
        }

        #client-media-state.is-error {
            color: #c92a2a !important;
        }

        #client-media-pagination {
            border-top: 1px solid #e5e9ef !important;
        }

        #client-media-pagination button:disabled {
            cursor: not-allowed !important;
            opacity: .45 !important;
        }

        @media (max-width: 780px) {
            #client-media-picker {
                padding: 8px !important;
            }

            #client-media-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
    </style>

    <aside id="client-media-picker" aria-hidden="true">
        <div id="client-media-dialog" role="dialog" aria-modal="true" aria-labelledby="client-media-title">
            <div id="client-media-head">
                <h2 id="client-media-title">Chọn ảnh</h2>
                <button type="button" id="client-media-close" aria-label="Đóng">Đóng</button>
            </div>
            <div id="client-media-tools">
                <select id="client-media-folder" aria-label="Thư mục ảnh">
                    <option value="all">Tất cả thư mục</option>
                    @foreach (app(\App\Services\CloudinaryService::class)->listFolders() as $folder)
                        <option value="{{ $folder }}">{{ ucfirst($folder) }}</option>
                    @endforeach
                </select>
                <div>
                    <input type="file" id="client-media-upload" accept="image/*" hidden>
                    <button type="button" class="client-media-upload" id="client-media-upload-button">
                        Thêm ảnh mới
                    </button>
                </div>
            </div>
            <div id="client-media-state">Đang tải thư viện ảnh…</div>
            <div id="client-media-grid"></div>
            <div id="client-media-pagination">
                <button type="button" id="client-media-previous">← Trước</button>
                <span id="client-media-page">Trang 1</span>
                <button type="button" id="client-media-next">Sau →</button>
            </div>
        </div>
    </aside>

    <script>
        window.clientMediaPicker = (function () {
            const picker = document.getElementById('client-media-picker');
            const folder = document.getElementById('client-media-folder');
            const grid = document.getElementById('client-media-grid');
            const state = document.getElementById('client-media-state');
            const previous = document.getElementById('client-media-previous');
            const next = document.getElementById('client-media-next');
            const pageLabel = document.getElementById('client-media-page');
            const upload = document.getElementById('client-media-upload');
            const title = document.getElementById('client-media-title');
            const uploadButton = document.getElementById('client-media-upload-button');
            const resourcesUrl = @json(route('admin.media.resources', ['locale' => app()->getLocale()]));
            const uploadUrl = @json(route('admin.media.upload', ['locale' => app()->getLocale()]));
            const csrfToken = @json(csrf_token());

            let onSelect = null;
            let cursors = [null];
            let pageIndex = 0;
            let nextCursor = null;
            let mediaType = 'image';

            function isVideoPicker() {
                return mediaType === 'video';
            }

            function updatePickerCopy() {
                const video = isVideoPicker();
                title.textContent = video ? 'Chọn video' : 'Chọn ảnh';
                folder.setAttribute('aria-label', video ? 'Thư mục video' : 'Thư mục ảnh');
                upload.accept = video ? 'video/mp4,video/webm' : 'image/*';
                uploadButton.textContent = video ? 'Tải video mới' : 'Thêm ảnh mới';
            }

            function setState(text, isError) {
                state.textContent = text;
                state.classList.toggle('is-error', Boolean(isError));
                state.style.display = text ? 'block' : 'none';
            }

            function render(resources, cursor) {
                grid.innerHTML = '';
                nextCursor = cursor || null;
                previous.disabled = pageIndex === 0;
                next.disabled = !nextCursor;
                pageLabel.textContent = 'Trang ' + (pageIndex + 1);
                setState(resources.length ? '' : (isVideoPicker() ? 'Chưa có video trong thư viện.' : 'Chưa có ảnh trong thư viện.'), false);

                resources.forEach(function (resource) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.title = 'Chọn ' + resource.public_id;
                    const preview = document.createElement(isVideoPicker() ? 'video' : 'img');
                    preview.src = resource.secure_url;
                    preview.alt = resource.public_id;
                    if (isVideoPicker()) {
                        preview.muted = true;
                        preview.preload = 'metadata';
                    }
                    const name = document.createElement('span');
                    name.textContent = resource.public_id.split('/').pop();
                    button.append(preview, name);
                    button.addEventListener('click', function () {
                        const handler = onSelect;
                        close();
                        if (handler) handler(resource.secure_url);
                    });
                    grid.appendChild(button);
                });
            }

            async function load() {
                setState(isVideoPicker() ? 'Đang tải thư viện video…' : 'Đang tải thư viện ảnh…', false);
                grid.innerHTML = '';
                const query = new URLSearchParams({ folder: folder.value, type: mediaType, _: Date.now().toString() });
                const cursor = cursors[pageIndex];
                if (cursor) query.set('cursor', cursor);

                try {
                    const response = await fetch(resourcesUrl + '?' + query.toString(), {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error(isVideoPicker() ? 'Không thể tải thư viện video.' : 'Không thể tải thư viện ảnh.');
                    const payload = await response.json();
                    render(payload.resources || [], payload.next_cursor);
                } catch (error) {
                    setState(error.message || 'Không thể tải thư viện ảnh.', true);
                }
            }

            function open(handler, type) {
                onSelect = handler;
                mediaType = type === 'video' ? 'video' : 'image';
                updatePickerCopy();
                picker.classList.add('is-open');
                picker.setAttribute('aria-hidden', 'false');
                cursors = [null];
                pageIndex = 0;
                load();
            }

            function close() {
                picker.classList.remove('is-open');
                picker.setAttribute('aria-hidden', 'true');
                onSelect = null;
            }

            document.getElementById('client-media-close').addEventListener('click', close);
            picker.addEventListener('click', function (event) {
                if (event.target === picker) close();
            });
            folder.addEventListener('change', function () {
                cursors = [null];
                pageIndex = 0;
                load();
            });
            previous.addEventListener('click', function () {
                if (pageIndex === 0) return;
                pageIndex--;
                load();
            });
            next.addEventListener('click', function () {
                if (!nextCursor) return;
                cursors = cursors.slice(0, pageIndex + 1);
                cursors.push(nextCursor);
                pageIndex++;
                load();
            });
            document.getElementById('client-media-upload-button').addEventListener('click', function () {
                upload.click();
            });
            upload.addEventListener('change', async function () {
                if (!upload.files[0]) return;
                setState(isVideoPicker() ? 'Đang tải video lên…' : 'Đang tải ảnh lên…', false);
                const formData = new FormData();
                formData.append('file', upload.files[0]);
                formData.append('folder', folder.value === 'all' ? 'general' : folder.value);
                formData.append(isVideoPicker() ? 'video_only' : 'image_only', '1');

                try {
                    const response = await fetch(uploadUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                        throw new Error(validationError || payload.message || (isVideoPicker() ? 'Tải video lên không thành công.' : 'Tải ảnh lên không thành công.'));
                    }
                    // Keep the picker open so the admin can pick what they just added.
                    cursors = [null];
                    pageIndex = 0;
                    await load();
                } catch (error) {
                    setState(error.message || (isVideoPicker() ? 'Tải video lên không thành công.' : 'Tải ảnh lên không thành công.'), true);
                } finally {
                    upload.value = '';
                }
            });

            return { open: open, close: close };
        })();
    </script>
@endif
