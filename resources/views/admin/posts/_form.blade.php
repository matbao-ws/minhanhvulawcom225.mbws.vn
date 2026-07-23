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
        <div class="card">
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
                            $summary = old("summary.$code", $post->getTranslation('summary', $code, false));
                            $content = old("content.$code", $post->getTranslation('content', $code, false));
                            $seoTitle = old("seo_title.$code", $post->getTranslation('seo_title', $code, false));
                            $seoDesc = old("seo_description.$code", $post->getTranslation('seo_description', $code, false));
                            $defaultTab = $code === $defaultContentLocale;
                        @endphp
                        <div class="tab-pane fade @if($defaultTab) show active @endif" id="post-language-{{ $code }}">
                            @if(!$defaultTab)<div class="text-end mb-3"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}"><i class="ti ti-language me-1"></i>Dịch {{ strtoupper($defaultContentLocale) }} → {{ strtoupper($code) }}</button></div>@endif
                            <div class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'title' : 'title_'.$code }}">{{ __('admin.posts.fields.title') }} @if($defaultTab)<span class="text-danger">*</span>@endif</label><input type="text" class="form-control" id="{{ $defaultTab ? 'title' : 'title_'.$code }}" name="title[{{ $code }}]" value="{{ $title }}" data-i18n-locale="{{ $code }}" data-i18n-field="title" @required($defaultTab)></div>
                            <div class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'slug' : 'slug_'.$code }}">{{ __('admin.posts.fields.slug') }}</label><input type="text" class="form-control" id="{{ $defaultTab ? 'slug' : 'slug_'.$code }}" name="slug[{{ $code }}]" value="{{ $slug }}" data-i18n-locale="{{ $code }}" data-i18n-field="slug" placeholder="Tự tạo từ tiêu đề"></div>
                            <div class="mb-4"><label class="form-label" for="summary_{{ $code }}">{{ __('admin.posts.fields.summary') }}</label><textarea class="form-control" id="summary_{{ $code }}" name="summary[{{ $code }}]" rows="3" data-i18n-locale="{{ $code }}" data-i18n-field="summary">{{ $summary }}</textarea></div>
                            <div class="mb-4 position-relative" @if($defaultTab) id="editor_wrapper" @endif><label class="form-label" for="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}">{{ __('admin.posts.fields.content') }} @if($defaultTab)<span class="text-danger">*</span>@endif</label><textarea class="form-control d-none" id="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}" name="content[{{ $code }}]" data-i18n-locale="{{ $code }}" data-i18n-field="content" data-translation-format="html" @required($defaultTab)>{{ $content }}</textarea><div id="{{ $defaultTab ? 'content_editor' : 'content_editor_'.$code }}" class="catalog-quill" data-target="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}" style="height:350px">{!! app(\App\Support\HtmlSanitizer::class)->clean($content) !!}</div></div>
                            <div class="border rounded p-3 bg-light-subtle"><div class="mb-3"><label class="form-label" for="{{ $defaultTab ? 'seo_title' : 'seo_title_'.$code }}">{{ __('admin.posts.fields.seo_title') }}</label><input class="form-control" id="{{ $defaultTab ? 'seo_title' : 'seo_title_'.$code }}" name="seo_title[{{ $code }}]" value="{{ $seoTitle }}" data-i18n-locale="{{ $code }}" data-i18n-field="seo_title"></div><div><label class="form-label" for="{{ $defaultTab ? 'seo_description' : 'seo_description_'.$code }}">{{ __('admin.posts.fields.seo_description') }}</label><textarea class="form-control" id="{{ $defaultTab ? 'seo_description' : 'seo_description_'.$code }}" name="seo_description[{{ $code }}]" rows="3" data-i18n-locale="{{ $code }}" data-i18n-field="seo_description">{{ $seoDesc }}</textarea></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- SEO Card -->
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('admin.posts.sections.seo') }}</h4>
                
                <div class="mb-4">
                    <label class="form-label" for="seo_keys">{{ __('admin.posts.fields.seo_keys') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="seo_keys" name="seo_keys" value="{{ old('seo_keys', $post->seo_keys) }}" placeholder="{{ __('admin.posts.placeholders.focus_keyword') }}" required>
                    <p class="fs-2 text-muted mb-0">{{ __('admin.posts.placeholders.focus_keyword_help') }}</p>
                </div>

            </div>
        </div>

        @include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])
    </div>

    <!-- Right column: Sidebar settings & SEO Analyzer Widget -->
    <div class="col-lg-4">
        <!-- Thumbnail Card -->
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('admin.posts.sections.thumbnail') }}</h4>
                
                <!-- Hidden file input for image select -->
                <input type="file" name="image_file" id="post_image_file" class="d-none" accept="image/*">
                
                <!-- Styled image preview area -->
                <div id="post_image_preview_container" class="position-relative text-center border border-2 border-dashed rounded p-3 mb-3 cursor-pointer d-flex flex-column align-items-center justify-content-center bg-light" 
                     style="min-height: 180px; cursor: pointer; border-style: dashed !important;" 
                     onclick="document.getElementById('post_image_file').click()">
                     
                    <img id="post_image_preview" src="{{ $post->image_url ?: '#' }}" 
                         class="img-fluid rounded {{ $post->image_url ? '' : 'd-none' }}" 
                         style="max-height: 150px; object-fit: contain;">
                     
                    <div id="post_image_placeholder" class="text-center py-3 {{ $post->image_url ? 'd-none' : '' }}">
                        <iconify-icon icon="solar:camera-add-bold-duotone" class="fs-10 text-muted mb-2"></iconify-icon>
                        <div class="text-muted small">{{ __('admin.posts.placeholders.image_help') }}</div>
                    </div>
                </div>
                <p class="fs-2 text-center mb-0">{{ __('admin.posts.placeholders.image_types') }}</p>
            </div>
        </div>

        <!-- Status Card -->
        <div class="card">
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
            </div>
        </div>

        <!-- Category Card -->
        <div class="card">
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

        <!-- Live SEO Analyzer Widget (Yoast Style) -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                    <h4 class="card-title mb-0 text-success d-flex align-items-center gap-2">
                        <iconify-icon icon="solar:ranking-bold-duotone" class="fs-6"></iconify-icon>{{ __('admin.posts.sections.seo_analysis') }}
                    </h4>
                    <span class="badge bg-success text-white fw-bold px-2 py-1 fs-1" id="seo_overall_badge">{{ __('admin.posts.seo_widget.status_excellent') }}</span>
                </div>

                <!-- Circular Progress Score -->
                <div class="text-center my-4 d-flex flex-column align-items-center justify-content-center">
                    <div class="position-relative d-inline-flex">
                        <svg class="seo-progress-ring" width="100" height="100">
                            <circle class="text-light-subtle" stroke="#e2e8f0" stroke-width="8" fill="transparent" r="40" cx="50" cy="50"/>
                            <circle class="seo-progress-ring-circle" id="seo_progress_circle" stroke="#ef4444" stroke-width="8" stroke-dasharray="251.2" stroke-dashoffset="251.2" fill="transparent" r="40" cx="50" cy="50"/>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <span class="fs-6 fw-bold text-dark" id="seo_score_txt">0</span><span class="fs-3 text-muted">/100</span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="fs-3 fw-bold" id="seo_rating_label" style="color: #ef4444;">{{ __('admin.posts.seo_widget.rating_too_short') }}</span>
                    </div>
                </div>

                <!-- Analysis Checklist -->
                <div class="seo-checklist">
                    <h6 class="fs-2 fw-bold text-muted text-uppercase mb-3">{{ __('admin.posts.sections.seo_results') }}</h6>
                    
                    <div class="seo-rule-item" id="rule_keyword_exists">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.keyword_exists') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_title_length">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.title_length') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_title_keyword">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.title_keyword') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_slug_keyword">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.slug_keyword') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_desc_length">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.desc_length') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_desc_keyword">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.desc_keyword') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_content_length">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.content_length') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_keyword_density">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.keyword_density') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_first_paragraph">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.first_paragraph') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_headings">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.headings') }}</span>
                    </div>
                    <div class="seo-rule-item" id="rule_image_alts">
                        <span class="seo-status-dot seo-status-red"></span>
                        <span>{{ __('admin.posts.seo_widget.rules.image_alts') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('admin.shared.translation-assets')
