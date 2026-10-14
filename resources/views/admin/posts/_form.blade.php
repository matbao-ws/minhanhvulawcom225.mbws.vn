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
                            @if(!$defaultTab)<div class="text-end mb-3"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}"><i class="ti ti-language me-1"></i>Dịch {{ strtoupper($defaultContentLocale) }} → {{ strtoupper($code) }}</button></div>@endif
                            <div class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'title' : 'title_'.$code }}">{{ __('admin.posts.fields.title') }} @if($defaultTab)<span class="text-danger">*</span>@endif</label><input type="text" class="form-control" id="{{ $defaultTab ? 'title' : 'title_'.$code }}" name="title[{{ $code }}]" value="{{ $title }}" data-i18n-locale="{{ $code }}" data-i18n-field="title" placeholder="{{ __('admin.posts.placeholders.title') }}" @required($defaultTab)></div>
                            <div class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'slug' : 'slug_'.$code }}">{{ __('admin.posts.fields.slug') }}</label><input type="text" class="form-control" id="{{ $defaultTab ? 'slug' : 'slug_'.$code }}" name="slug[{{ $code }}]" value="{{ $slug }}" data-i18n-locale="{{ $code }}" data-i18n-field="slug" placeholder="{{ __('admin.posts.placeholders.slug') }}"></div>
                            <div data-tour="post-form.editor" class="mb-4"><label class="form-label" for="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}">{{ __('admin.posts.fields.content') }} @if($defaultTab)<span class="text-danger">*</span>@endif</label><textarea class="form-control d-none" id="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}" name="content[{{ $code }}]" data-i18n-locale="{{ $code }}" data-i18n-field="content" data-translation-format="html" placeholder="{{ __('admin.posts.placeholders.content_placeholder') }}" @required($defaultTab)>{{ $content }}</textarea><div id="{{ $defaultTab ? 'content_editor' : 'content_editor_'.$code }}" class="rich-editor" data-preset="full" data-media-folder="posts" data-target="{{ $defaultTab ? 'content_input' : 'content_input_'.$code }}" data-placeholder="{{ __('admin.posts.placeholders.content_placeholder') }}">{!! app(\App\Support\HtmlSanitizer::class)->cleanRichText($content) !!}</div><p class="form-text mb-0"><i class="ti ti-clipboard-text me-1" aria-hidden="true"></i>{{ __('admin.posts.placeholders.content_paste_hint') }}</p>@include('admin.shared.ai-edit-button', ['entityType' => 'post', 'entity' => $post, 'locale' => $code, 'target' => $defaultTab ? 'content_input' : 'content_input_'.$code])</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- SEO box (Rank Math style): score, Google preview, focus keyword and grouped checks.
             The checks run in _form-assets (analyzeSEO); PostController::analyzeSeo scores on save. --}}
        @php
            $seoScore = (int) ($post->seo_score ?? 0);
            $seoTone = $seoScore >= 80 ? 'is-good' : ($seoScore >= 50 ? 'is-ok' : 'is-bad');
            $seoGroups = [
                'basic' => ['keyword_exists', 'title_keyword', 'desc_keyword', 'slug_keyword', 'first_paragraph', 'content_length'],
                'additional' => ['keyword_in_subheadings', 'keyword_image_alt', 'keyword_density', 'url_length', 'external_links', 'internal_links', 'image_alts'],
                'title_readability' => ['title_length', 'desc_length', 'title_keyword_start', 'title_number'],
                'content_readability' => ['headings', 'short_paragraphs', 'content_media'],
            ];
            $siteHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com';
            // The headings App\Support\Seo\FaqSchema recognises, for the Schema tab's FAQ status.
            $faqHeadings = array_map(fn (string $code) => mb_strtolower((string) trans('ai.faq.heading', [], $code)), ['vi', 'en']);
        @endphp
        <div data-tour="post-form.seo" class="card rm-panel">
            <div class="rm-panel__head">
                <div class="rm-panel__brand">
                    <span class="rm-panel__logo"><i class="ti ti-chart-line" aria-hidden="true"></i></span>
                    {{ __('admin.posts.seo_panel.title') }}
                </div>
                <span class="rm-score {{ $seoTone }}" data-seo-score-pill><span data-seo-score>{{ $seoScore }}</span>&nbsp;/ 100</span>
            </div>
            <div class="rm-tabs nav" role="tablist">
                <button type="button" class="rm-tab active" data-bs-toggle="tab" data-bs-target="#seo-tab-general" role="tab" aria-controls="seo-tab-general" aria-selected="true">
                    <i class="ti ti-settings" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.tab_general') }}
                </button>
                <button data-tour="post-form.seo-meta" type="button" class="rm-tab" data-bs-toggle="tab" data-bs-target="#seo-tab-preview" role="tab" aria-controls="seo-tab-preview" aria-selected="false">
                    <i class="ti ti-brand-google" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.tab_preview') }}
                </button>
                <button type="button" class="rm-tab" data-bs-toggle="tab" data-bs-target="#seo-tab-social" role="tab" aria-controls="seo-tab-social" aria-selected="false">
                    <i class="ti ti-share" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.tab_social') }}
                </button>
                <button type="button" class="rm-tab" data-bs-toggle="tab" data-bs-target="#seo-tab-schema" role="tab" aria-controls="seo-tab-schema" aria-selected="false">
                    <i class="ti ti-code" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.tab_schema') }}
                </button>
                <button type="button" class="rm-tab" data-bs-toggle="tab" data-bs-target="#seo-tab-advanced" role="tab" aria-controls="seo-tab-advanced" aria-selected="false">
                    <i class="ti ti-adjustments-horizontal" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.tab_advanced') }}
                </button>
            </div>
            <div class="tab-content rm-panel__body">
                <div class="tab-pane fade show active" id="seo-tab-general" role="tabpanel">
                    <div class="rm-keyword">
                        <label class="form-label" for="seo_keys_entry">{{ __('admin.posts.seo_panel.focus_keyword') }}</label>
                        {{-- Badges; the field submits them comma-separated and the first is scored. --}}
                        <input type="hidden" id="seo_keys" name="seo_keys" value="{{ old('seo_keys', $post->seo_keys) }}">
                        <div class="rm-tags" data-seo-keywords>
                            <i class="ti ti-target-arrow rm-keyword__icon" aria-hidden="true"></i>
                            <span class="rm-tags__list" data-seo-keyword-list></span>
                            <input type="text" class="rm-tags__input" id="seo_keys_entry" autocomplete="off" maxlength="255"
                                   data-placeholder="{{ __('admin.posts.placeholders.focus_keyword') }}"
                                   placeholder="{{ __('admin.posts.placeholders.focus_keyword') }}">
                            <span class="rm-score {{ $seoTone }}" data-seo-score-pill><span data-seo-score>{{ $seoScore }}</span>&nbsp;/ 100</span>
                        </div>
                        <p class="form-text mb-0">{{ __('admin.posts.placeholders.focus_keyword_help') }}</p>
                    </div>

                    @include('admin.shared.ai-seo-fix-button', ['post' => $post])

                    <div class="rm-stats">
                        <span><i class="ti ti-file-text me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_widget.word_count') }}: <strong id="seo_word_count">0</strong></span>
                        <span><i class="ti ti-percentage me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_widget.keyword_density') }}: <strong id="seo_keyword_density">0%</strong></span>
                    </div>

                    <div data-tour="post-form.seo-analysis" class="rm-checks">
                        @foreach($seoGroups as $group => $rules)
                            <div class="rm-group" data-seo-group="{{ $group }}">
                                <button type="button" class="rm-group__head" data-bs-toggle="collapse" data-bs-target="#seo-group-{{ $group }}" aria-expanded="true" aria-controls="seo-group-{{ $group }}">
                                    <span>{{ __("admin.posts.seo_panel.groups.$group") }}</span>
                                    <span class="rm-group__badge is-bad" data-seo-group-badge>{{ __('admin.posts.seo_panel.group_errors', ['count' => count($rules)]) }}</span>
                                    <i class="ti ti-chevron-down" aria-hidden="true"></i>
                                </button>
                                <div class="collapse show" id="seo-group-{{ $group }}">
                                    <ul class="rm-group__list">
                                        @foreach($rules as $rule)
                                            <li class="seo-rule-item is-fail" id="rule_{{ $rule }}">
                                                <span class="rm-check-icon" aria-hidden="true"><i class="ti ti-x"></i></span>
                                                <span>{{ __("admin.posts.seo_widget.rules.$rule") }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="tab-pane fade" id="seo-tab-preview" role="tabpanel">
                    <div class="rm-snippet">
                        <div class="rm-snippet__bar">
                            <span><i class="ti ti-brand-google me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.preview') }}</span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin.posts.seo_panel.preview') }}">
                                <button type="button" class="btn btn-outline-secondary active" data-seo-preview-device="desktop" aria-label="Desktop">
                                    <i class="ti ti-device-desktop" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-seo-preview-device="mobile" aria-label="Mobile">
                                    <i class="ti ti-device-mobile" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="rm-serp" id="seo_google_preview_result">
                            <div class="rm-serp__site">
                                <span class="rm-serp__favicon" aria-hidden="true">{{ mb_strtoupper(mb_substr($siteHost, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <div class="seo-preview-site">{{ $siteHost }}</div>
                                    <div class="seo-preview-url" id="seo_preview_url">{{ url('/') }}/...</div>
                                </div>
                            </div>
                            <div class="seo-preview-title" id="seo_preview_title">{{ __('admin.posts.placeholders.seo_title') }}</div>
                            <div class="seo-preview-description" id="seo_preview_description">{{ __('admin.posts.placeholders.seo_description') }}</div>
                        </div>
                        <div class="rm-snippet__foot">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#seo-snippet-editor" aria-expanded="false" aria-controls="seo-snippet-editor">
                                <i class="ti ti-pencil me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.edit_snippet') }}
                            </button>
                        </div>
                        {{-- Edit snippet, as in Rank Math: the SEO title and description of the
                             language tab in view (the others stay in the form, hidden). --}}
                        <div class="collapse" id="seo-snippet-editor">
                            <div class="rm-snippet__editor">
                                @foreach($contentLanguages as $language)
                                    @php
                                        $code = $language->code;
                                        $defaultTab = $code === $defaultContentLocale;
                                        $seoTitle = old("seo_title.$code", $post->getTranslation('seo_title', $code, false));
                                        $seoDesc = old("seo_description.$code", $post->getTranslation('seo_description', $code, false));
                                    @endphp
                                    <div data-seo-meta-locale="{{ $code }}" @class(['d-none' => ! $defaultTab])>
                                        @if(count($contentLanguages) > 1)
                                            <div class="rm-snippet__locale"><i class="ti ti-language me-1" aria-hidden="true"></i>{{ $language->native_name }}</div>
                                        @endif
                                        @include('admin.shared.ai-meta-button', ['entityType' => 'post', 'entity' => $post, 'locale' => $code, 'titleField' => "seo_title[$code]", 'descriptionField' => "seo_description[$code]"])
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between gap-2">
                                                <label class="form-label" for="{{ $defaultTab ? 'seo_title' : 'seo_title_'.$code }}">{{ __('admin.posts.fields.seo_title') }}</label>
                                                <small class="text-muted"><span data-seo-title-count="{{ $code }}">{{ mb_strlen($seoTitle) }}</span>/60</small>
                                            </div>
                                            <input class="form-control"
                                                   id="{{ $defaultTab ? 'seo_title' : 'seo_title_'.$code }}"
                                                   name="seo_title[{{ $code }}]"
                                                   value="{{ $seoTitle }}"
                                                   maxlength="255"
                                                   data-seo-title-input="{{ $code }}"
                                                   data-i18n-locale="{{ $code }}"
                                                   data-i18n-field="seo_title"
                                                   placeholder="{{ __('admin.posts.placeholders.seo_title') }}">
                                            <div class="rm-length" aria-hidden="true"><span data-seo-length="{{ $code }}-title"></span></div>
                                        </div>
                                        <div>
                                            <div class="d-flex justify-content-between gap-2">
                                                <label class="form-label" for="{{ $defaultTab ? 'seo_description' : 'seo_description_'.$code }}">{{ __('admin.posts.fields.seo_description') }}</label>
                                                <small class="text-muted"><span data-seo-description-count="{{ $code }}">{{ mb_strlen($seoDesc) }}</span>/160</small>
                                            </div>
                                            <textarea class="form-control"
                                                      id="{{ $defaultTab ? 'seo_description' : 'seo_description_'.$code }}"
                                                      name="seo_description[{{ $code }}]"
                                                      rows="3"
                                                      maxlength="500"
                                                      data-seo-description-input="{{ $code }}"
                                                      data-i18n-locale="{{ $code }}"
                                                      data-i18n-field="seo_description"
                                                      placeholder="{{ __('admin.posts.placeholders.seo_description') }}">{{ $seoDesc }}</textarea>
                                            <div class="rm-length" aria-hidden="true"><span data-seo-length="{{ $code }}-description"></span></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="seo-tab-social" role="tabpanel">
                    <p class="form-text mt-0 mb-3">{{ __('admin.posts.seo_panel.social_help') }}</p>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            @foreach($contentLanguages as $language)
                                @php
                                    $code = $language->code;
                                    $ogTitle = old("og_title.$code", $post->getTranslation('og_title', $code, false));
                                    $ogDescription = old("og_description.$code", $post->getTranslation('og_description', $code, false));
                                @endphp
                                {{-- Follows the language tab in view, like the snippet editor. --}}
                                <div data-seo-meta-locale="{{ $code }}" @class(['d-none' => $code !== $defaultContentLocale])>
                                    @if(count($contentLanguages) > 1)
                                        <div class="rm-snippet__locale"><i class="ti ti-language me-1" aria-hidden="true"></i>{{ $language->native_name }}</div>
                                    @endif
                                    <div class="mb-3">
                                        <label class="form-label" for="og_title_{{ $code }}">{{ __('admin.posts.seo_panel.social_title') }}</label>
                                        <input type="text" class="form-control" id="og_title_{{ $code }}" name="og_title[{{ $code }}]" value="{{ $ogTitle }}" maxlength="255"
                                               data-social-title placeholder="{{ __('admin.posts.seo_panel.social_title_placeholder') }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="og_description_{{ $code }}">{{ __('admin.posts.seo_panel.social_description') }}</label>
                                        <textarea class="form-control" id="og_description_{{ $code }}" name="og_description[{{ $code }}]" rows="3" maxlength="500"
                                                  data-social-description placeholder="{{ __('admin.posts.seo_panel.social_description_placeholder') }}">{{ $ogDescription }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                            <label class="form-label d-block">{{ __('admin.posts.seo_panel.social_image') }}</label>
                            <input type="hidden" name="og_image" id="og_image" value="{{ old('og_image', $post->og_image) }}" data-social-image>
                            {{-- The media library opens on this input and writes the chosen URL into og_image. --}}
                            <input type="file" class="d-none" id="og_image_file" accept="image/*" data-media-folder="posts" data-media-selected-field="og_image">
                            <div class="d-flex flex-wrap gap-2">
                                <label for="og_image_file" class="btn btn-sm btn-outline-primary mb-0"><i class="ti ti-photo me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.social_image_pick') }}</label>
                                <button type="button" class="btn btn-sm btn-light" data-social-image-clear>{{ __('admin.posts.seo_panel.social_image_remove') }}</button>
                            </div>
                            <p class="form-text mb-0">{{ __('admin.posts.seo_panel.social_image_help') }}</p>
                        </div>
                        <div class="col-lg-6">
                            <div class="small fw-semibold text-muted mb-2"><i class="ti ti-brand-facebook me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.preview_facebook') }}</div>
                            <div class="rm-social-card mb-3">
                                <div class="rm-social-card__image" data-social-preview-image><i class="ti ti-photo" aria-hidden="true"></i></div>
                                <div class="rm-social-card__body">
                                    <div class="rm-social-card__domain">{{ mb_strtoupper($siteHost) }}</div>
                                    <div class="rm-social-card__title" data-social-preview-title></div>
                                    <div class="rm-social-card__description" data-social-preview-description></div>
                                </div>
                            </div>
                            <div class="small fw-semibold text-muted mb-2"><i class="ti ti-brand-x me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.preview_x') }}</div>
                            <div class="rm-social-card rm-social-card--x">
                                <div class="rm-social-card__image" data-social-preview-image><i class="ti ti-photo" aria-hidden="true"></i></div>
                                <div class="rm-social-card__body">
                                    <div class="rm-social-card__title" data-social-preview-title></div>
                                    <div class="rm-social-card__domain">{{ $siteHost }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="seo-tab-schema" role="tabpanel">
                    <div class="mb-4">
                        <label class="form-label" for="schema_type">{{ __('admin.posts.seo_panel.schema_type') }}</label>
                        <select class="form-select" id="schema_type" name="schema_type">
                            @foreach(\App\Models\Post::SCHEMA_TYPES as $schemaType)
                                <option value="{{ $schemaType }}" @selected(old('schema_type', $post->schema_type ?: 'Article') === $schemaType)>{{ __("admin.posts.seo_panel.schema_types.$schemaType") }}</option>
                            @endforeach
                        </select>
                        <p class="form-text mb-0">{{ __('admin.posts.seo_panel.schema_type_help') }}</p>
                    </div>
                    <div class="rm-robots mb-3">
                        <input type="hidden" name="schema_faq" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="schema_faq" name="schema_faq" value="1"
                                   @checked((bool) old('schema_faq', $post->exists ? $post->schema_faq : true))>
                            <label class="form-check-label fw-semibold" for="schema_faq">{{ __('admin.posts.seo_panel.schema_faq') }}</label>
                        </div>
                        <p class="form-text mb-0" data-schema-faq-status
                           data-found="{{ __('admin.posts.seo_panel.schema_faq_found') }}"
                           data-missing="{{ __('admin.posts.seo_panel.schema_faq_missing') }}"
                           data-headings='@json($faqHeadings)'></p>
                    </div>
                    <p class="form-text mb-0"><i class="ti ti-info-circle me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.schema_always') }}</p>
                </div>

                <div class="tab-pane fade" id="seo-tab-advanced" role="tabpanel">
                    <div class="mb-4">
                        <label class="form-label" for="canonical_url">{{ __('admin.posts.fields.canonical_url') }}</label>
                        <input type="url"
                               class="form-control"
                               id="canonical_url"
                               name="canonical_url"
                               value="{{ old('canonical_url', $post->canonical_url) }}"
                               placeholder="{{ __('admin.posts.placeholders.canonical_url') }}">
                        <p class="form-text mb-0">{{ __('admin.posts.placeholders.canonical_url_help') }}</p>
                    </div>

                    <div class="rm-robots">
                        <div class="fw-bold mb-1">{{ __('admin.posts.seo_panel.robots') }}</div>
                        <p class="form-text mt-0 mb-3">{{ __('admin.posts.seo_panel.robots_help') }}</p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <input type="hidden" name="robots_index" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           id="robots_index"
                                           name="robots_index"
                                           value="1"
                                           @checked((bool) old('robots_index', $post->exists ? $post->robots_index : true))>
                                    <label class="form-check-label fw-semibold" for="robots_index">Index</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <input type="hidden" name="robots_follow" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           id="robots_follow"
                                           name="robots_follow"
                                           value="1"
                                           @checked((bool) old('robots_follow', $post->exists ? $post->robots_follow : true))>
                                    <label class="form-check-label fw-semibold" for="robots_follow">Follow links</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])
    </div>

    <!-- Right column: Sidebar settings & SEO Analyzer Widget -->
    <div class="col-lg-4">
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

    </div>
</div>
@include('admin.shared.translation-assets')
