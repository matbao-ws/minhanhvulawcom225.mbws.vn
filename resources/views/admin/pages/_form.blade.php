<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-semibold mb-1">{{ $page->exists ? 'Chỉnh sửa trang chính sách' : 'Tạo trang chính sách' }}</h4>
        <p class="text-muted mb-0">Soạn tiêu đề và nội dung chính sách (đổi trả, bảo mật, vận chuyển, thanh toán...) ngay tại đây.</p>
    </div>
    <div data-tour="page-form.toolbar" class="d-flex gap-2">
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
        <ul data-tour="page-form.languages" class="nav nav-tabs mb-4" role="tablist">
            @foreach($contentLanguages as $language)
                <li class="nav-item"><button class="nav-link @if($language->code === $defaultContentLocale) active @endif" type="button" data-bs-toggle="tab" data-bs-target="#page-meta-{{ $language->code }}">{{ $language->native_name }}</button></li>
            @endforeach
        </ul>
        <div class="tab-content">
            @foreach($contentLanguages as $language)
                @php $code = $language->code; @endphp
                <div class="tab-pane fade @if($code === $defaultContentLocale) show active @endif" id="page-meta-{{ $code }}">
                    @if($code !== $defaultContentLocale)<div class="text-end mb-3"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}"><i class="ti ti-language me-1"></i>Dịch {{ strtoupper($defaultContentLocale) }} → {{ strtoupper($code) }}</button></div>@endif
                    <div class="row">
                        <div class="col-md-8 mb-3"><label class="form-label" for="page-title-{{ $code }}">Tiêu đề @if($code === $defaultContentLocale)<span class="text-danger">*</span>@endif</label><input class="form-control" id="page-title-{{ $code }}" name="title[{{ $code }}]" value="{{ old("title.$code", $page->getTranslation('title', $code, false)) }}" data-i18n-locale="{{ $code }}" data-i18n-field="title" @required($code === $defaultContentLocale)></div>
                        <div class="col-md-4 mb-3"><label class="form-label" for="page-slug-{{ $code }}">Slug</label><input class="form-control" id="page-slug-{{ $code }}" name="slug[{{ $code }}]" value="{{ old("slug.$code", $page->localizedSlug($code) ?: ($code === $defaultContentLocale ? $page->slug : '')) }}" placeholder="Tự tạo từ tiêu đề"></div>
                        <div data-tour="page-form.content" class="col-12 mb-3">
                            @php $html = old("published_html.$code", $page->getTranslation('published_html', $code, false)); @endphp
                            <label class="form-label">Nội dung</label>
                            {{-- Disabled until the editor is really edited (data-sync="edited"): a
                                 disabled field is not submitted, so saving title/SEO never pushes
                                 stored HTML through the admin editor, which normalises markup saved
                                 by the storefront editor. --}}
                            <textarea class="d-none" name="published_html[{{ $code }}]" id="page-html-{{ $code }}" data-i18n-locale="{{ $code }}" data-i18n-field="published_html" data-translation-format="page_html" @disabled(old("published_html.$code") === null)>{{ $html }}</textarea>
                            <div class="rich-editor" data-preset="page" data-sync="edited" data-media-folder="pages" data-target="page-html-{{ $code }}" data-placeholder="Nhập nội dung chính sách...">{!! app(\App\Support\PageHtmlSanitizer::class)->clean($html) !!}</div>
                            @include('admin.shared.ai-edit-button', ['entityType' => 'page', 'entity' => $page, 'locale' => $code, 'target' => "page-html-$code"])
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@include('admin.shared.seo-box', [
    'record' => $page,
    'entityType' => 'page',
    'titleField' => 'meta_title',
    'descriptionField' => 'meta_description',
    'selectors' => [
        'title' => '#page-title-'.$defaultContentLocale,
        'slug' => '#page-slug-'.$defaultContentLocale,
        'content' => 'page-html-'.$defaultContentLocale,
        'languageTabs' => '#page-meta-',
    ],
    'tour' => 'page-form',
    'mediaFolder' => 'pages',
])

<div class="card">
    <div class="card-body d-flex flex-wrap justify-content-between gap-3">
        <div data-tour="page-form.status">
            <label class="form-label fw-semibold">Trạng thái</label>
            <select class="form-select" name="is_active">
                <option value="0" @selected(!old('is_active', $page->is_active))>Bản nháp</option>
                <option value="1" @selected(old('is_active', $page->is_active))>Xuất bản</option>
            </select>
        </div>
        <div class="align-self-end"><button data-tour="page-form.save" class="btn btn-primary px-4">Lưu trang</button></div>
    </div>
</div>

@include('admin.shared.rich-editor-assets')

@include('admin.shared.translation-assets')
