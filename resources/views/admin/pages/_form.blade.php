<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-semibold mb-1">{{ $page->exists ? 'Chỉnh sửa trang chính sách' : 'Tạo trang chính sách' }}</h4>
        <p class="text-muted mb-0">Soạn tiêu đề và nội dung chính sách (đổi trả, bảo mật, vận chuyển, thanh toán...) ngay tại đây.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-light" href="{{ route('admin.pages.index') }}">Quay lại</a>
        @if($page->exists && $page->is_active)
            <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
               href="{{ route('client.pages.show', ['locale' => $defaultContentLocale, 'slug' => $page->canonicalSlug($defaultContentLocale)]) }}">
                <i class="ti ti-external-link me-1" aria-hidden="true"></i>Xem trang
            </a>
        @endif
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
                        <div class="col-12 mb-3">
                            @php $html = old("published_html.$code", $page->getTranslation('published_html', $code, false)); @endphp
                            <label class="form-label">Nội dung</label>
                            {{-- Disabled until the editor is touched: a disabled field is not
                                 submitted, so saving title/SEO never pushes stored HTML through
                                 Quill, which would flatten markup saved by the storefront editor. --}}
                            <textarea class="d-none" name="published_html[{{ $code }}]" id="page-html-{{ $code }}" @disabled(old("published_html.$code") === null)>{{ $html }}</textarea>
                            <div class="page-quill" data-target="page-html-{{ $code }}" data-placeholder="Nhập nội dung chính sách..." style="min-height:360px">{!! app(\App\Support\PageHtmlSanitizer::class)->clean($html) !!}</div>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">SEO title</label><input class="form-control" name="meta_title[{{ $code }}]" value="{{ old("meta_title.$code", $page->getTranslation('meta_title', $code, false)) }}"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">SEO description</label><input class="form-control" name="meta_description[{{ $code }}]" value="{{ old("meta_description.$code", $page->getTranslation('meta_description', $code, false)) }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
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

@push('styles')
<link rel="stylesheet" href="{{ asset('admin-assets/libs/quill/dist/quill.snow.css') }}">
<style>.page-quill .ql-editor { min-height: 320px; color: #1f2937; font-size: 15px; }</style>
@endpush

@push('scripts')
<script src="{{ asset('admin-assets/libs/quill/dist/quill.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.page-quill').forEach(function (element) {
            const target = document.getElementById(element.dataset.target);
            if (!target || typeof Quill === 'undefined') return;

            const editor = new Quill(element, {
                theme: 'snow',
                placeholder: element.dataset.placeholder || '',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, 4, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'link'],
                        ['clean'],
                    ],
                },
            });

            editor.on('text-change', function () {
                target.disabled = false;
                // An emptied editor still holds <p><br></p>; store it as no content.
                target.value = editor.getText().trim() === '' ? '' : editor.root.innerHTML;
            });
        });
    });
</script>
@endpush
