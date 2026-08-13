@php
    $contentLanguages = app(\App\Services\LanguageRegistry::class)->active();
    $defaultContentLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-12 mb-4">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    @foreach($contentLanguages as $language)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link d-flex align-items-center gap-2 @if($language->code === $defaultContentLocale) active @endif"
                               data-bs-toggle="tab"
                               href="#category-language-{{ $language->code }}"
                               role="tab"
                               aria-selected="{{ $language->code === $defaultContentLocale ? 'true' : 'false' }}">
                                <span><i class="ti ti-language fs-4"></i></span>
                                <span>{{ $language->native_name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content">
                    @foreach($contentLanguages as $language)
                        @php
                            $code = $language->code;
                            $name = old("name.$code", $category->getTranslation('name', $code, false));
                            $slug = old("slug.$code", $category->localizedSlug($code) ?: ($code === $defaultContentLocale ? $category->slug : ''));
                            $description = old("description.$code", $category->getTranslation('description', $code, false));
                        @endphp
                        <div class="tab-pane fade @if($code === $defaultContentLocale) show active @endif" id="category-language-{{ $code }}">
                            @if($code !== $defaultContentLocale)<div class="text-end mb-3"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}"><i class="ti ti-language me-1"></i>Dịch {{ strtoupper($defaultContentLocale) }} → {{ strtoupper($code) }}</button></div>@endif
                            <div class="row">
                                <div class="col-md-8 mb-3"><label class="form-label" for="name_{{ $code }}">{{ __('catalog.fields.name') }} @if($code === $defaultContentLocale)<span class="text-danger">*</span>@endif</label><input type="text" class="form-control" id="name_{{ $code }}" name="name[{{ $code }}]" value="{{ $name }}" data-i18n-locale="{{ $code }}" data-i18n-field="name" placeholder="Nhập tên danh mục..." @required($code === $defaultContentLocale)></div>
                                <div class="col-md-4 mb-3"><label class="form-label" for="slug_{{ $code }}">{{ __('catalog.fields.slug') }} <span class="text-muted fw-normal">(bỏ trống để tự tạo từ tên)</span></label><input type="text" class="form-control" id="slug_{{ $code }}" name="slug[{{ $code }}]" value="{{ $slug }}" data-i18n-locale="{{ $code }}" data-i18n-field="slug" placeholder="Ví dụ: dien-thoai"></div>
                                <div class="col-12 mb-3"><label class="form-label" for="description_{{ $code }}">{{ __('catalog.fields.description') }}</label><textarea class="form-control d-none" id="description_{{ $code }}" name="description[{{ $code }}]" data-i18n-locale="{{ $code }}" data-i18n-field="description" data-translation-format="html" placeholder="Nhập mô tả danh mục...">{{ $description }}</textarea><div id="description_editor_{{ $code }}" class="catalog-quill" data-target="description_{{ $code }}" data-placeholder="Nhập mô tả danh mục...">{!! app(\App\Support\HtmlSanitizer::class)->clean($description) !!}</div></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="parent_id">{{ __('catalog.fields.parent_category') }}</label>
                <select class="form-select" id="parent_id" name="parent_id">
                    <option value="">{{ __('catalog.common.none') }}</option>
                    @foreach($parentOptions as $parent)
                        <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parent->id)>
                            {!! str_repeat('&nbsp;&nbsp;', $parent->depth ?? 0) !!}{{ $parent->depth ? '↳ ' : '' }}{{ $parent->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="image_file">{{ __('catalog.fields.image') }}</label>
                <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*" data-media-folder="categories">
                <div class="mt-2 {{ $category->image_url ? '' : 'd-none' }}" data-media-preview>
                    <img src="{{ $category->image_url ?: '' }}" alt="{{ $name }}" class="rounded border object-fit-cover" width="72" height="72" data-media-preview-image onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/404.png') }}';">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input primary" type="checkbox" name="is_active" value="1" id="is_active" @checked((bool) old('is_active', $category->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('catalog.fields.visible') }}</label>
                </div>
                <div class="form-text">{{ __('catalog.categories.status_help') }}</div>
            </div>
            <div class="col-md-6 mb-3">
                <input type="hidden" name="is_draft" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input primary" type="checkbox" name="is_draft" value="1" id="is_draft" @checked((bool) old('is_draft', $category->is_draft))>
                    <label class="form-check-label" for="is_draft">{{ __('catalog.fields.save_draft') }}</label>
                </div>
                <div class="form-text">{{ __('catalog.categories.draft_help') }}</div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.categories.index')])
@include('admin.shared.translation-assets')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/quill/dist/quill.snow.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('admin-assets/libs/quill/dist/quill.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.catalog-quill').forEach(function (editorElement) {
                if (editorElement.id === 'quick_description_editor') return;
                const target = document.getElementById(editorElement.dataset.target);
                if (!target) return;

                const quill = new Quill(editorElement, {
                    theme: 'snow',
                    placeholder: editorElement.dataset.placeholder || '',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['link', 'clean']
                        ]
                    }
                });
                editorElement.__quill = quill;

                const form = editorElement.closest('form');
                if (form) {
                    form.addEventListener('submit', function () {
                        target.value = quill.root.innerHTML;
                    });
                }
            });
        });
    </script>
@endpush
