<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-semibold mb-1">{{ $page->exists ? 'Chỉnh sửa trang' : 'Thêm trang' }}</h4>
        <p class="text-muted mb-0">Nội dung trang được sửa trực tiếp trên trang thật (đăng nhập admin rồi mở trang, bấm "Sửa trực tiếp"). Ở đây chỉ khai báo tiêu đề, đường dẫn và SEO.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-light" href="{{ route('admin.pages.index') }}">Quay lại</a>
        <button type="submit" class="btn btn-outline-primary">Lưu tiêu đề &amp; SEO</button>
        @if($page->exists)
            {{-- The primary action, because content is no longer edited on this
                 screen: this form only owns the title, the slug and the SEO fields.
                 Same tab rather than a new one — it is a move to where the work
                 happens, not a preview. --}}
            <a class="btn btn-primary" id="page-open-inline-editor"
               href="{{ route('client.pages.show', ['locale' => $defaultContentLocale, 'slug' => $page->canonicalSlug($defaultContentLocale)]) }}">
                <i class="ti ti-pencil me-1" aria-hidden="true"></i>Sửa nội dung trên trang
            </a>
        @endif
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

@push('scripts')
<script>
    /*
     * Leaving for the storefront editor discards anything typed here, because this
     * form posts nothing on the way out. Warn only when a field actually changed —
     * a confirm on every click would train people to dismiss it.
     */
    document.addEventListener('DOMContentLoaded', function () {
        const link = document.getElementById('page-open-inline-editor');
        if (!link) return;

        const form = link.closest('form') || document.querySelector('form');
        if (!form) return;

        let dirty = false;
        ['input', 'change'].forEach(function (type) {
            form.addEventListener(type, function () { dirty = true; }, true);
        });

        link.addEventListener('click', function (event) {
            if (!dirty) return;
            if (!window.confirm('Tiêu đề/SEO đang có thay đổi chưa lưu. Rời đi sẽ mất phần đó. Tiếp tục?')) {
                event.preventDefault();
            }
        });
    });
</script>
@endpush
