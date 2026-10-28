@php
    $contentLanguages = app(\App\Services\LanguageRegistry::class)->active();
    $defaultContentLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
    $fallbackLocale = app(\App\Services\LanguageRegistry::class)->fallbackLocale();
    $cancelUrl = route('admin.posts.index');
@endphp

<div class="row">
    <!-- Left column: Main Content and SEO -->
    <div class="col-lg-8">
        <!-- General Info Card -->
        <div data-tour="post-form.general" class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4"><h4 class="card-title mb-0">{{ __('admin.posts.sections.general') }}</h4></div>
                <ul class="nav nav-tabs mb-4" role="tablist">
                    @foreach($contentLanguages as $language)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link d-flex align-items-center gap-2 @if($language->code === $defaultContentLocale) active @endif"
                               data-bs-toggle="tab"
                               href="#post-language-{{ $language->code }}"
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
                            $title = old("title.$code", $post->getTranslation('title', $code, false));
                            $slug = old("slug.$code", $post->localizedSlug($code) ?: ($code === $defaultContentLocale ? $post->slug : ''));
                            $content = old("content.$code", $post->getTranslation('content', $code, false));
                            $defaultTab = $code === $defaultContentLocale;
                        @endphp
                        <div class="tab-pane fade @if($defaultTab) show active @endif" id="post-language-{{ $code }}">
                            @if(!$defaultTab)<div class="text-end mb-3"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}"><i class="ti ti-language me-1"></i>{{ __('admin.translation.translate_button', ['from' => strtoupper($defaultContentLocale), 'to' => strtoupper($code)]) }}</button></div>@endif
                            <div class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'title' : 'title_'.$code }}">{{ __('admin.posts.fields.title') }} @if($defaultTab)<span class="text-danger">*</span>@endif</label><input type="text" class="form-control" id="{{ $defaultTab ? 'title' : 'title_'.$code }}" name="title[{{ $code }}]" value="{{ $title }}" data-i18n-locale="{{ $code }}" data-i18n-field="title" placeholder="{{ __('admin.posts.placeholders.title') }}" @required($defaultTab)></div>
                            <div class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'slug' : 'slug_'.$code }}">{{ __('admin.posts.fields.slug') }}</label><input type="text" class="form-control" id="{{ $defaultTab ? 'slug' : 'slug_'.$code }}" name="slug[{{ $code }}]" value="{{ $slug }}" data-i18n-locale="{{ $code }}" data-i18n-field="slug" placeholder="{{ __('admin.posts.placeholders.slug') }}"></div>
                            <div data-tour="post-form.editor" class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}">{{ __('admin.posts.fields.content') }} @if($defaultTab)<span class="text-danger">*</span>@endif</label><textarea class="form-control d-none" id="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}" name="content[{{ $code }}]" data-i18n-locale="{{ $code }}" data-i18n-field="content" data-translation-format="html" placeholder="{{ __('admin.posts.placeholders.content_placeholder') }}" @required($defaultTab)>{{ $content }}</textarea><div id="{{ $defaultTab ? 'content_editor' : 'content_editor_'.$code }}" class="rich-editor" data-preset="full" data-media-folder="posts" data-target="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}" data-placeholder="{{ __('admin.posts.placeholders.content_placeholder') }}">{!! app(\App\Support\HtmlSanitizer::class)->cleanRichText($content) !!}</div><p class="form-text mb-0"><i class="ti ti-clipboard-text me-1" aria-hidden="true"></i>{{ __('admin.posts.placeholders.content_paste_hint') }}</p>@include('admin.shared.ai-edit-button', ['entityType' => 'post', 'entity' => $post, 'locale' => $code, 'target' => $defaultTab ? 'content_input' : 'content_input_'.$code])</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @include('admin.shared.seo-box', [
            'record' => $post,
            'entityType' => 'post',
            'titleField' => 'seo_title',
            'descriptionField' => 'seo_description',
            'selectors' => [
                'title' => '#title',
                'slug' => '#slug',
                'content' => 'content_input',
                'image' => '#post_image_preview',
                'imageInputs' => ['#post_image_file'],
                'languageTabs' => '#post-language-',
            ],
            'tour' => 'post-form',
            'mediaFolder' => 'posts',
            'schemaTypes' => \App\Models\Post::SCHEMA_TYPES,
            'placeholders' => ['title' => __('admin.posts.placeholders.seo_title')],
        ])

    </div>

    <!-- Right column: Sidebar settings & SEO Analyzer Widget -->
    <div class="col-lg-4 admin-sticky-aside">
        <!-- Thumbnail Card -->
        <div data-tour="post-form.thumbnail" class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('admin.posts.sections.thumbnail') }}</h4>
                
                <!-- Hidden file input for image select -->
                <input type="file" name="image_file" id="post_image_file" class="d-none" accept="image/*" data-media-folder="posts">
                
                <!-- Styled image preview area -->
                <div id="post_image_preview_container" class="position-relative text-center border border-2 border-dashed rounded p-3 mb-3 cursor-pointer d-flex flex-column align-items-center justify-content-center bg-light" 
                     style="min-height: 180px; cursor: pointer; border-style: dashed !important;" 
                     onclick="document.getElementById('post_image_file').click()">
                     
                    <img id="post_image_preview" src="{{ $post->image_url ?: '#' }}" 
                         class="img-fluid rounded {{ $post->image_url ? '' : 'd-none' }}" 
                         style="max-height: 150px; object-fit: contain;">
                     
                    <div id="post_image_placeholder" class="text-center py-3 {{ $post->image_url ? 'd-none' : '' }}">
                        <iconify-icon icon="solar:gallery-add-bold-duotone" class="fs-20 text-muted mb-2"></iconify-icon>
                        <div class="text-muted small">{{ __('admin.posts.placeholders.image_help') }}</div>
                    </div>
                </div>
                <p class="fs-2 text-center mb-0">{{ __('admin.posts.placeholders.image_types') }}</p>
            </div>
        </div>

        <!-- Status Card -->
        <div data-tour="post-form.status" class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-7">
                    <h4 class="card-title">{{ __('admin.posts.sections.publish') }}</h4>
                    <div class="p-2 h-100 {{ old('is_active', $post->is_active) ? 'bg-success' : 'bg-danger' }} rounded-circle"></div>
                </div>
                <select class="form-select mb-2" name="is_active">
                    <option value="1" @selected((string) old('is_active', $post->is_active) === '1')>{{ __('admin.posts.fields.active') }}</option>
                    <option value="0" @selected((string) old('is_active', $post->is_active) === '0')>{{ __('admin.posts.fields.inactive') }}</option>
                </select>
                <p class="fs-2 mb-0">{{ __('admin.posts.placeholders.status_help') }}</p>
                {{-- The SEO score stays in sight while writing, as in Rank Math's publish box. --}}
                @php
                    $seoScore = (int) ($post->seo_score ?? 0);
                    $seoTone = $seoScore >= 80 ? 'is-good' : ($seoScore >= 50 ? 'is-ok' : 'is-bad');
                @endphp
                <div class="d-flex align-items-center justify-content-between gap-2 mt-3 pt-3 border-top">
                    <span class="fw-semibold">{{ __('admin.posts.seo_panel.score') }}</span>
                    <span class="rm-score {{ $seoTone }}" data-seo-score-pill><span data-seo-score>{{ $seoScore }}</span>&nbsp;/ 100</span>
                </div>
            </div>
        </div>

        <!-- Category Card -->
        <div data-tour="post-form.category" class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('admin.posts.sections.category') }}</h4>
                <div class="mb-3">
                    <select name="category_id" id="category_id" class="catalog-select2 form-control select2-select">
                        <option value="">{{ __('admin.posts.uncategorized') }}</option>
                        @foreach($categories as $category)
                            @php
                                $catName = $category->getTranslation('name', app()->getLocale(), false) ?: $category->getTranslation('name', $fallbackLocale, false);
                            @endphp
                            <option value="{{ $category->id }}" @selected(old('category_id', $post->category_id) == $category->id)>
                                {!! str_repeat('&nbsp;&nbsp;', $category->depth ?? 0) !!}{{ $category->depth ? '↳ ' : '' }}{{ $catName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <p class="fs-2 mb-0">{{ __('admin.posts.placeholders.category_help') }}</p>
            </div>
        </div>

        @include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])

    </div>
</div>
@include('admin.shared.translation-assets')
