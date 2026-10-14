{{--
    The SEO box (Rank Math style) of a post, product or policy page: score, focus keywords and
    grouped checks, Google preview with the SEO title/description, share fields, schema, robots.
    Server side: App\Support\Seo\SeoBox (and PostController for posts); the checks are
    App\Services\PostSeoAnalyzer, run in the browser by admin-assets/js/forms/seo-box.js.

    @include('admin.shared.seo-box', [
        'record' => $post,                      // Post, Product or Page
        'entityType' => 'post',                 // AI meta button: post | product | page
        'titleField' => 'seo_title',            // its SEO title / description fields
        'descriptionField' => 'seo_description',
        'selectors' => [
            'title' => '#title',                // default-language title input
            'slug' => '#slug',                  // default-language slug input
            'content' => 'content_input',       // rich editor target of the default-language body
            'image' => '#post_image_preview',   // featured image <img>, for the share preview (optional)
            'imageInputs' => ['#post_image_file'],
            'languageTabs' => '#post-language-', // href prefix of the form's language tabs
        ],
        'tour' => 'post-form',                  // data-tour: seo-box.post-form, seo-box-meta.post-form, seo-box-analysis.post-form
        'mediaFolder' => 'posts',
        'schemaTypes' => Post::SCHEMA_TYPES,    // optional: the Schema tab's type choice
        'aiFix' => true,                        // optional: "fix by SEO score" (posts)
        'placeholders' => ['title' => '…', 'description' => '…'], // optional
    ])
--}}
@php
    $seoLanguages = app(\App\Services\LanguageRegistry::class)->active();
    $seoDefaultLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
    $seoScore = (int) ($record->seo_score ?? 0);
    $seoTone = $seoScore >= 80 ? 'is-good' : ($seoScore >= 50 ? 'is-ok' : 'is-bad');
    $seoGroups = [
        'basic' => ['keyword_exists', 'title_keyword', 'desc_keyword', 'slug_keyword', 'first_paragraph', 'content_length'],
        'additional' => ['keyword_in_subheadings', 'keyword_image_alt', 'keyword_density', 'url_length', 'external_links', 'internal_links', 'image_alts'],
        'title_readability' => ['title_length', 'desc_length', 'title_keyword_start', 'title_number'],
        'content_readability' => ['headings', 'short_paragraphs', 'content_media'],
    ];
    $siteHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com';
    $schemaTypes = $schemaTypes ?? null;
    $aiFix = $aiFix ?? false;
    $mediaFolder = $mediaFolder ?? 'general';
    $placeholders = ($placeholders ?? []) + [
        'title' => __('admin.posts.seo_panel.placeholder_title'),
        'description' => __('admin.posts.placeholders.seo_description'),
    ];
    // The storefront URL with the slug left open, for the Google preview; null without its theme route.
    $seoRoute = \App\Support\Seo\SeoBox::MODELS[$record::class]['route'] ?? null;
    $urlPattern = $seoRoute && \Illuminate\Support\Facades\Route::has($seoRoute)
        ? route($seoRoute, ['locale' => $seoDefaultLocale, 'slug' => 'seo-box-slug'])
        : null;
    // The headings App\Support\Seo\FaqSchema recognises, for the FAQ status.
    $faqHeadings = array_map(fn (string $code) => mb_strtolower((string) trans('ai.faq.heading', [], $code)), ['vi', 'en']);
    $seoConfig = [
        'defaultLocale' => $seoDefaultLocale,
        'weights' => \App\Services\PostSeoAnalyzer::WEIGHTS,
        'urlPattern' => $urlPattern,
        'selectors' => $selectors + ['image' => null, 'imageInputs' => []],
        't' => [
            'group_errors' => __('admin.posts.seo_panel.group_errors'),
            'group_all_good' => __('admin.posts.seo_panel.group_all_good'),
            'primary_keyword' => __('admin.posts.seo_panel.primary_keyword'),
            'remove_keyword' => __('admin.posts.seo_panel.remove_keyword'),
            'preview_title' => $placeholders['title'],
            'preview_description' => $placeholders['description'],
        ],
    ];
@endphp

@once
    @push('styles')
        <link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('admin-assets/css/seo-box.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ \App\Support\AssetVersion::url('admin-assets/js/forms/seo-box.js') }}"></script>
    @endpush
@endonce

<div data-tour="seo-box.{{ $tour }}" class="card rm-panel" data-seo-box data-seo-config='@json($seoConfig)'>
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
        <button data-tour="seo-box-meta.{{ $tour }}" type="button" class="rm-tab" data-bs-toggle="tab" data-bs-target="#seo-tab-preview" role="tab" aria-controls="seo-tab-preview" aria-selected="false">
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
                <input type="hidden" id="seo_keys" name="seo_keys" value="{{ old('seo_keys', $record->seo_keys) }}" data-seo-keys>
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

            @if($aiFix)
                @include('admin.shared.ai-seo-fix-button', ['post' => $record])
            @endif

            <div class="rm-stats">
                <span><i class="ti ti-file-text me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_widget.word_count') }}: <strong id="seo_word_count">0</strong></span>
                <span><i class="ti ti-percentage me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_widget.keyword_density') }}: <strong id="seo_keyword_density">0%</strong></span>
            </div>

            <div data-tour="seo-box-analysis.{{ $tour }}" class="rm-checks">
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
                    <div class="seo-preview-title" id="seo_preview_title">{{ $placeholders['title'] }}</div>
                    <div class="seo-preview-description" id="seo_preview_description">{{ $placeholders['description'] }}</div>
                </div>
                <div class="rm-snippet__foot">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#seo-snippet-editor" aria-expanded="false" aria-controls="seo-snippet-editor">
                        <i class="ti ti-pencil me-1" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.edit_snippet') }}
                    </button>
                </div>
                {{-- Edit snippet, as in Rank Math: the SEO title and description of the
                     language tab in view (the others stay in the form, hidden). --}}
                <div @class(['collapse', 'show' => $errors->hasAny([$titleField.'.*', $descriptionField.'.*'])]) id="seo-snippet-editor">
                    <div class="rm-snippet__editor">
                        @foreach($seoLanguages as $language)
                            @php
                                $code = $language->code;
                                $seoTitle = old("$titleField.$code", $record->getTranslation($titleField, $code, false));
                                $seoDesc = old("$descriptionField.$code", $record->getTranslation($descriptionField, $code, false));
                            @endphp
                            <div data-seo-meta-locale="{{ $code }}" @class(['d-none' => $code !== $seoDefaultLocale])>
                                @if(count($seoLanguages) > 1)
                                    <div class="rm-snippet__locale"><i class="ti ti-language me-1" aria-hidden="true"></i>{{ $language->native_name }}</div>
                                @endif
                                @include('admin.shared.ai-meta-button', ['entityType' => $entityType, 'entity' => $record, 'locale' => $code, 'titleField' => "{$titleField}[$code]", 'descriptionField' => "{$descriptionField}[$code]"])
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between gap-2">
                                        <label class="form-label" for="{{ $titleField }}_{{ $code }}">{{ __('admin.posts.fields.seo_title') }}</label>
                                        <small class="text-muted"><span data-seo-title-count="{{ $code }}">{{ mb_strlen((string) $seoTitle) }}</span>/60</small>
                                    </div>
                                    <input class="form-control @error("$titleField.$code") is-invalid @enderror"
                                           id="{{ $titleField }}_{{ $code }}"
                                           name="{{ $titleField }}[{{ $code }}]"
                                           value="{{ $seoTitle }}"
                                           maxlength="255"
                                           data-seo-title-input="{{ $code }}"
                                           data-i18n-locale="{{ $code }}"
                                           data-i18n-field="{{ $titleField }}"
                                           placeholder="{{ $placeholders['title'] }}">
                                    @error("$titleField.$code")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="rm-length" aria-hidden="true"><span data-seo-length="{{ $code }}-title"></span></div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between gap-2">
                                        <label class="form-label" for="{{ $descriptionField }}_{{ $code }}">{{ __('admin.posts.fields.seo_description') }}</label>
                                        <small class="text-muted"><span data-seo-description-count="{{ $code }}">{{ mb_strlen((string) $seoDesc) }}</span>/160</small>
                                    </div>
                                    <textarea class="form-control @error("$descriptionField.$code") is-invalid @enderror"
                                              id="{{ $descriptionField }}_{{ $code }}"
                                              name="{{ $descriptionField }}[{{ $code }}]"
                                              rows="3"
                                              maxlength="500"
                                              data-seo-description-input="{{ $code }}"
                                              data-i18n-locale="{{ $code }}"
                                              data-i18n-field="{{ $descriptionField }}"
                                              placeholder="{{ $placeholders['description'] }}">{{ $seoDesc }}</textarea>
                                    @error("$descriptionField.$code")<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    @foreach($seoLanguages as $language)
                        @php
                            $code = $language->code;
                            $ogTitle = old("og_title.$code", $record->getTranslation('og_title', $code, false));
                            $ogDescription = old("og_description.$code", $record->getTranslation('og_description', $code, false));
                        @endphp
                        {{-- Follows the language tab in view, like the snippet editor. --}}
                        <div data-seo-meta-locale="{{ $code }}" @class(['d-none' => $code !== $seoDefaultLocale])>
                            @if(count($seoLanguages) > 1)
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
                    <input type="hidden" name="og_image" id="og_image" value="{{ old('og_image', $record->og_image) }}" data-social-image>
                    {{-- The media library opens on this input and writes the chosen URL into og_image. --}}
                    <input type="file" class="d-none" id="og_image_file" accept="image/*" data-media-folder="{{ $mediaFolder }}" data-media-selected-field="og_image">
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
            @if($schemaTypes)
                <div class="mb-4">
                    <label class="form-label" for="schema_type">{{ __('admin.posts.seo_panel.schema_type') }}</label>
                    <select class="form-select" id="schema_type" name="schema_type">
                        @foreach($schemaTypes as $schemaType)
                            <option value="{{ $schemaType }}" @selected(old('schema_type', $record->schema_type ?: $schemaTypes[0]) === $schemaType)>{{ __("admin.posts.seo_panel.schema_types.$schemaType") }}</option>
                        @endforeach
                    </select>
                    <p class="form-text mb-0">{{ __('admin.posts.seo_panel.schema_type_help') }}</p>
                </div>
            @else
                <p class="form-text mt-0 mb-3"><i class="ti ti-circle-check me-1 text-success" aria-hidden="true"></i>{{ __('admin.posts.seo_panel.schema_auto.'.$entityType) }}</p>
            @endif
            <div class="rm-robots mb-3">
                <input type="hidden" name="schema_faq" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="schema_faq" name="schema_faq" value="1"
                           @checked((bool) old('schema_faq', $record->exists ? ($record->schema_faq ?? true) : true))>
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
                <input type="url" class="form-control @error('canonical_url') is-invalid @enderror" id="canonical_url" name="canonical_url"
                       value="{{ old('canonical_url', $record->canonical_url) }}" placeholder="{{ __('admin.posts.placeholders.canonical_url') }}" data-seo-canonical>
                @error('canonical_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <p class="form-text mb-0">{{ __('admin.posts.placeholders.canonical_url_help') }}</p>
            </div>

            <div class="rm-robots">
                <div class="fw-bold mb-1">{{ __('admin.posts.seo_panel.robots') }}</div>
                <p class="form-text mt-0 mb-3">{{ __('admin.posts.seo_panel.robots_help') }}</p>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <input type="hidden" name="robots_index" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="robots_index" name="robots_index" value="1"
                                   @checked((bool) old('robots_index', $record->exists ? ($record->robots_index ?? true) : true))>
                            <label class="form-check-label fw-semibold" for="robots_index">Index</label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <input type="hidden" name="robots_follow" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="robots_follow" name="robots_follow" value="1"
                                   @checked((bool) old('robots_follow', $record->exists ? ($record->robots_follow ?? true) : true))>
                            <label class="form-check-label fw-semibold" for="robots_follow">Follow links</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
