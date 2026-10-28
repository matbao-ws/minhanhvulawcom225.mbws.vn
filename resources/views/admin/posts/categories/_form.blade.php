@php
    $contentLanguages = app(\App\Services\LanguageRegistry::class)->active();
    $defaultContentLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
    $fallbackLocale = app(\App\Services\LanguageRegistry::class)->fallbackLocale();
    $cancelUrl = route('admin.post-categories.index');
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-12 mb-4">
                <ul data-tour="post-category-form.languages" class="nav nav-tabs mb-4" role="tablist">
                    @foreach($contentLanguages as $language)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link d-flex align-items-center gap-2 @if($language->code === $defaultContentLocale) active @endif"
                               data-bs-toggle="tab"
                               href="#post-category-language-{{ $language->code }}"
                               role="tab"
                               aria-selected="{{ $language->code === $defaultContentLocale ? 'true' : 'false' }}">
                                <span><i class="ti ti-language fs-4"></i></span>
                                <span>{{ $language->native_name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div data-tour="post-category-form.content" class="tab-content">
                    @foreach($contentLanguages as $language)
                        @php
                            $code = $language->code;
                            $name = old("name.$code", $category->getTranslation('name', $code, false));
                            $slug = old("slug.$code", $category->localizedSlug($code) ?: ($code === $defaultContentLocale ? $category->slug : ''));
                            $description = old("description.$code", $category->getTranslation('description', $code, false));
                        @endphp
                        <div class="tab-pane fade @if($code === $defaultContentLocale) show active @endif" id="post-category-language-{{ $code }}">
                            @if($code !== $defaultContentLocale)<div class="text-end mb-3"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}"><i class="ti ti-language me-1"></i>{{ __('admin.translation.translate_button', ['from' => strtoupper($defaultContentLocale), 'to' => strtoupper($code)]) }}</button></div>@endif
                            <div class="row"><div class="col-md-8 mb-3"><label class="form-label" for="name_{{ $code }}">{{ __('admin.blog_categories.fields.name') }} @if($code === $defaultContentLocale)<span class="text-danger">*</span>@endif</label><input class="form-control" id="name_{{ $code }}" name="name[{{ $code }}]" value="{{ $name }}" data-i18n-locale="{{ $code }}" data-i18n-field="name" @required($code === $defaultContentLocale)></div><div class="col-md-4 mb-3"><label class="form-label" for="slug_{{ $code }}">{{ __('admin.blog_categories.fields.slug') }}</label><input class="form-control" id="slug_{{ $code }}" name="slug[{{ $code }}]" value="{{ $slug }}" data-i18n-locale="{{ $code }}" data-i18n-field="slug" placeholder="{{ __('admin.translation.slug_from_name') }}"></div><div class="col-12 mb-3"><label class="form-label" for="description_{{ $code }}">{{ __('admin.blog_categories.fields.description') }}</label><textarea class="form-control d-none" id="description_{{ $code }}" name="description[{{ $code }}]" data-i18n-locale="{{ $code }}" data-i18n-field="description" data-translation-format="html">{{ $description }}</textarea><div id="description_editor_{{ $code }}" class="rich-editor" data-preset="basic" data-media-folder="posts" data-target="description_{{ $code }}">{!! app(\App\Support\HtmlSanitizer::class)->cleanRichText($description) !!}</div>@include('admin.shared.ai-edit-button', ['entityType' => 'post_category', 'entity' => $category, 'locale' => $code, 'target' => "description_$code"])</div></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div data-tour="post-category-form.parent" class="col-md-6 mb-3">
                <label class="form-label" for="parent_id">{{ __('catalog.fields.parent_category') }}</label>
                <select class="form-select" id="parent_id" name="parent_id">
                    <option value="">{{ __('catalog.common.none') }}</option>
                    @foreach($parentOptions as $parent)
                        @php
                            $parentName = $parent->getTranslation('name', app()->getLocale(), false) ?: $parent->getTranslation('name', $fallbackLocale, false);
                        @endphp
                        <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parent->id) @disabled($parent->id === $category->id)>
                            {!! str_repeat('&nbsp;&nbsp;', $parent->depth ?? 0) !!}{{ $parent->depth ? '↳ ' : '' }}{{ $parentName }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div data-tour="post-category-form.draft" class="col-12 mb-3">
                <input type="hidden" name="is_active" value="1">
                <div class="form-check">
                    <input class="form-check-input primary" type="checkbox" name="is_active" value="0" id="is_active" @checked(! (bool) old('is_active', $category->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('admin.blog_categories.save_draft_help') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.seo-box', [
    'record' => $category,
    'entityType' => 'post_category',
    'titleField' => 'meta_title',
    'descriptionField' => 'meta_description',
    'selectors' => [
        'title' => '#name_'.$defaultContentLocale,
        'slug' => '#slug_'.$defaultContentLocale,
        'content' => 'description_'.$defaultContentLocale,
        'languageTabs' => '#post-category-language-',
    ],
    'tour' => 'post-category-form',
    'mediaFolder' => 'posts',
    'placeholders' => ['title' => __('admin.posts.seo_panel.placeholder_name_title')],
])

@include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])
@include('admin.shared.translation-assets')

@include('admin.shared.rich-editor-assets')
