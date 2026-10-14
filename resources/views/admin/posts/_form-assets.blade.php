@include('admin.shared.rich-editor-assets')

@push('styles')
    <style>
        /* SEO box, Rank Math style. Tones: is-bad < 50 <= is-ok < 80 <= is-good. */
        .rm-panel { overflow: hidden; }
        .rm-panel__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #e5eaf2; }
        .rm-panel__brand { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; color: #1f2937; }
        .rm-panel__logo { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); font-size: 18px; }
        .rm-panel__body { padding: 20px; }
        .rm-score { display: inline-flex; align-items: center; padding: 4px 12px; border: 1px solid; border-radius: 999px; font-size: 13px; font-weight: 700; white-space: nowrap; transition: color .2s, background-color .2s, border-color .2s; }
        .rm-score.is-bad { color: #dc2626; background: #fef2f2; border-color: #fecaca; }
        .rm-score.is-ok { color: #d97706; background: #fffbeb; border-color: #fde68a; }
        .rm-score.is-good { color: #059669; background: #ecfdf5; border-color: #a7f3d0; }
        .rm-tabs { gap: 4px; padding: 0 12px; background: #f8fafc; border-bottom: 1px solid #e5eaf2; }
        .rm-tab { display: inline-flex; align-items: center; gap: 6px; padding: 12px 14px; border: 0; border-bottom: 2px solid transparent; background: none; color: #5a6a85; font-size: 14px; font-weight: 600; }
        .rm-tab:hover { color: #1f2937; }
        .rm-tab.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); }
        .rm-snippet { border: 1px solid #e5eaf2; border-radius: 12px; overflow: hidden; }
        .rm-snippet__bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e5eaf2; color: #5a6a85; font-size: 13px; font-weight: 600; }
        .rm-serp { max-width: 640px; padding: 16px 18px; background: #fff; transition: max-width .2s ease; }
        .rm-serp.is-mobile { max-width: 380px; }
        .rm-serp__site { display: flex; align-items: center; gap: 10px; }
        .rm-serp__favicon { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid #e5eaf2; border-radius: 50%; background: #f1f3f4; color: #5f6368; font-size: 13px; font-weight: 700; }
        .rm-snippet__foot { padding: 10px 14px; border-top: 1px dashed #e5eaf2; }
        .seo-preview-site { color: #202124; font-size: 14px; }
        .seo-preview-url { overflow: hidden; color: #4d5156; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .seo-preview-title { margin-top: 8px; color: #1a0dab; font-size: 20px; line-height: 1.3; overflow-wrap: anywhere; }
        .seo-preview-description { margin-top: 4px; color: #4d5156; font-size: 14px; line-height: 1.45; overflow-wrap: anywhere; }
        .seo-preview-title strong, .seo-preview-description strong { font-weight: 700; }
        .rm-keyword { margin-bottom: 16px; }
        .rm-keyword__field { position: relative; }
        .rm-keyword__field .form-control { height: 48px; padding-left: 40px; padding-right: 96px; font-weight: 600; }
        .rm-keyword__icon { position: absolute; top: 50%; left: 14px; transform: translateY(-50%); color: #94a3b8; font-size: 18px; }
        .rm-keyword__field .rm-score { position: absolute; top: 50%; right: 8px; transform: translateY(-50%); }
        .rm-stats { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-bottom: 12px; color: #5a6a85; font-size: 13px; }
        .rm-stats strong { color: #1f2937; }
        .rm-group { margin-bottom: 12px; border: 1px solid #e5eaf2; border-radius: 12px; overflow: hidden; }
        .rm-group__head { display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 16px; border: 0; background: #fff; color: #1f2937; font-size: 14.5px; font-weight: 700; text-align: left; }
        .rm-group__head .ti-chevron-down { margin-left: auto; color: #94a3b8; transition: transform .2s; }
        .rm-group__head[aria-expanded="false"] .ti-chevron-down { transform: rotate(-90deg); }
        .rm-group__badge { padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .rm-group__badge.is-bad { color: #dc2626; background: #fef2f2; }
        .rm-group__badge.is-good { color: #059669; background: #ecfdf5; }
        .rm-group__list { margin: 0; padding: 4px 16px 10px; border-top: 1px solid #f1f5f9; list-style: none; }
        .seo-rule-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px dashed #eef2f7; color: #334155; font-size: 14px; }
        .seo-rule-item:last-child { border-bottom: 0; }
        .rm-check-icon { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 20px; height: 20px; margin-top: 1px; border-radius: 50%; color: #fff; font-size: 12px; }
        .seo-rule-item.is-fail .rm-check-icon { background: #ef4444; }
        .seo-rule-item.is-pass .rm-check-icon { background: #10b981; }
        .rm-robots { padding: 14px 16px; border: 1px solid #e5eaf2; border-radius: 12px; }
        .rm-length { height: 4px; margin-top: 6px; overflow: hidden; border-radius: 4px; background: #eef2f7; }
        .rm-length span { display: block; width: 0; height: 100%; border-radius: 4px; background: #f59e0b; transition: width .2s, background-color .2s; }
        .rm-length span.is-good { background: #10b981; }
        .rm-length span.is-bad { background: #ef4444; }
        @media (max-width: 575.98px) {
            .rm-panel__body { padding: 14px; }
            .rm-keyword__field .form-control { padding-right: 14px; }
            .rm-keyword__field .rm-score { display: none; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ \App\Support\AssetVersion::url('admin-assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/select2/dist/js/select2.full.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form.admin-form-with-sticky-actions');
            let isDirty = false;

            // Initialize Select2
            if (jQuery().select2) {
                $('.select2-select').select2({
                    minimumResultsForSearch: 5
                });
            }

            // Slug auto-generation from Title
            const titleInput = document.getElementById('title');
            const slugInput = document.getElementById('slug');

            function generateSlug(text) {
                return text.toString().toLowerCase()
                    .replace(/á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ/g, 'a')
                    .replace(/é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ/g, 'e')
                    .replace(/í|ì|ỉ|ĩ|ị/g, 'i')
                    .replace(/ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ/g, 'o')
                    .replace(/ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự/g, 'u')
                    .replace(/ý|ỳ|ỷ|ỹ|ỵ/g, 'y')
                    .replace(/đ/g, 'd')
                    .replace(/\s+/g, '-')           // Replace spaces with -
                    .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
                    .replace(/\-\-+/g, '-')         // Replace multiple - with single -
                    .replace(/^-+/, '')             // Trim - from start of text
                    .replace(/-+$/, '');            // Trim - from end of text
            }

            if (titleInput && slugInput) {
                titleInput.addEventListener('input', function () {
                    if (!slugInput.dataset.manual) {
                        slugInput.value = generateSlug(titleInput.value);
                        slugInput.dispatchEvent(new Event('input'));
                    }
                });

                slugInput.addEventListener('change', function () {
                    slugInput.dataset.manual = 'true';
                });
            }

            // The body editor (public/admin-assets/js/forms/rich-editor.js) drives the SEO check.
            const contentEditor = window.AdminRichEditor ? AdminRichEditor.forTarget('content_input') : null;
            document.addEventListener('rich-editor:change', function (event) {
                isDirty = true;
                if (contentEditor && event.target === contentEditor.element) analyzeSEO();
            });

            // Local Preview Uploader for Featured Image
            const fileInput = document.getElementById('post_image_file');
            const previewImg = document.getElementById('post_image_preview');
            const placeholder = document.getElementById('post_image_placeholder');
            const container = document.getElementById('post_image_preview_container');

            if (fileInput && previewImg) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        previewImg.src = URL.createObjectURL(file);
                        previewImg.classList.remove('d-none');
                        if (placeholder) placeholder.classList.add('d-none');
                        isDirty = true;
                    }
                });
            }

            // Live SEO Analyzer Logic
            const seoKeysInput = document.getElementById('seo_keys');
            const seoTitleInput = document.getElementById('seo_title');
            const seoDescInput = document.getElementById('seo_description');
            const canonicalInput = document.getElementById('canonical_url');
            const previewResult = document.getElementById('seo_google_preview_result');

            // Length bar under the SEO title/description, as in Rank Math: green inside the range.
            function lengthBar(input, key, min, max) {
                const bar = document.querySelector('[data-seo-length="' + key + '"]');
                if (!bar) return;
                const length = input.value.trim().length;
                bar.style.width = Math.min(100, length / max * 100) + '%';
                bar.className = length > max ? 'is-bad' : (length >= min ? 'is-good' : '');
            }

            document.querySelectorAll('[data-seo-title-input]').forEach(function (input) {
                const counter = document.querySelector('[data-seo-title-count="' + input.dataset.seoTitleInput + '"]');
                const refresh = () => {
                    if (counter) counter.textContent = input.value.length;
                    lengthBar(input, input.dataset.seoTitleInput + '-title', 40, 60);
                };
                input.addEventListener('input', refresh);
                refresh();
            });

            document.querySelectorAll('[data-seo-description-input]').forEach(function (input) {
                const counter = document.querySelector('[data-seo-description-count="' + input.dataset.seoDescriptionInput + '"]');
                const refresh = () => {
                    if (counter) counter.textContent = input.value.length;
                    lengthBar(input, input.dataset.seoDescriptionInput + '-description', 120, 160);
                };
                input.addEventListener('input', refresh);
                refresh();
            });

            document.querySelectorAll('[data-seo-preview-device]').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.querySelectorAll('[data-seo-preview-device]').forEach(item => item.classList.remove('active'));
                    button.classList.add('active');
                    previewResult?.classList.toggle('is-mobile', button.dataset.seoPreviewDevice === 'mobile');
                });
            });

            // "Edit snippet" jumps to the SEO title of the language tab in view.
            document.querySelector('[data-seo-edit-snippet]')?.addEventListener('click', function () {
                const field = document.querySelector('.tab-pane.active [data-seo-title-input]') || seoTitleInput;
                if (!field) return;
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.focus({ preventScroll: true });
            });

            function countOccurrences(string, subString) {
                string += "";
                subString += "";
                if (subString.length <= 0) return 0;

                var n = 0,
                    pos = 0,
                    step = subString.length;

                while (true) {
                    pos = string.indexOf(subString, pos);
                    if (pos >= 0) {
                        ++n;
                        pos += step;
                    } else break;
                }
                return n;
            }

            const translations = {
                rating_too_short: "{{ __('admin.posts.seo_widget.rating_too_short') }}",
                rating_need_optimize: "{{ __('admin.posts.seo_widget.rating_need_optimize') }}",
                rating_good: "{{ __('admin.posts.seo_widget.rating_good') }}",
                rating_excellent: "{{ __('admin.posts.seo_widget.rating_excellent') }}",
                status_need_optimize: "{{ __('admin.posts.seo_widget.status_need_optimize') }}",
                status_good: "{{ __('admin.posts.seo_widget.status_good') }}",
                status_excellent: "{{ __('admin.posts.seo_widget.status_excellent') }}",
                group_errors: @json(__('admin.posts.seo_panel.group_errors')),
                group_all_good: @json(__('admin.posts.seo_panel.group_all_good')),
                preview_title: @json(__('admin.posts.placeholders.seo_title')),
                preview_description: @json(__('admin.posts.placeholders.seo_description'))
            };

            function analyzeSEO() {
                if (!seoKeysInput) return;

                const keyword = seoKeysInput.value.trim().toLowerCase();
                const title = (titleInput ? titleInput.value : '').trim();
                const slug = (slugInput ? slugInput.value : '').trim();
                const seoTitle = (seoTitleInput ? seoTitleInput.value : '').trim() || title;
                const seoDesc = (seoDescInput ? seoDescInput.value : '').trim();
                
                const htmlContent = contentEditor ? contentEditor.getHTML() : '';
                const textContent = contentEditor ? contentEditor.getText() : '';

                // Word count
                const words = textContent.split(/\s+/).filter(w => w.length > 0);
                const wordCount = words.length;

                // Rule statuses (0: fail, 1: pass, 2: neutral/optional)
                const rules = {
                    keyword_exists: keyword.length > 0,
                    title_length: seoTitle.length >= 40 && seoTitle.length <= 60,
                    title_keyword: keyword.length > 0 && seoTitle.toLowerCase().includes(keyword),
                    slug_keyword: keyword.length > 0 && slug.toLowerCase().includes(generateSlug(keyword)),
                    desc_length: seoDesc.length >= 120 && seoDesc.length <= 160,
                    desc_keyword: keyword.length > 0 && seoDesc.toLowerCase().includes(keyword),
                    content_length: wordCount >= 300,
                    keyword_density: false, // will calculate
                    first_paragraph: false, // will calculate
                    headings: htmlContent.includes('<h2') || htmlContent.includes('<h3'),
                    image_alts: false, // will check below
                    internal_links: false
                };

                // Calculate keyword density (recommended 0.5% to 2.5%)
                let density = 0;
                if (wordCount > 0 && keyword.length > 0) {
                    const occurrences = countOccurrences(textContent.toLowerCase(), keyword);
                    density = (occurrences / wordCount) * 100;
                    rules.keyword_density = density >= 0.5 && density <= 2.5;
                }

                // Check keyword in first paragraph (first 100 words of text)
                if (keyword.length > 0 && wordCount > 0) {
                    const firstParagraphText = words.slice(0, 100).join(' ').toLowerCase();
                    rules.first_paragraph = firstParagraphText.includes(keyword);
                }

                // Check image alt tags
                const hasImages = htmlContent.includes('<img');
                if (!hasImages) {
                    rules.image_alts = true; // pass by default if no images
                } else {
                    // check if all images have alt tag
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(htmlContent, 'text/html');
                    const imgs = doc.querySelectorAll('img');
                    let allHaveAlts = true;
                    imgs.forEach(img => {
                        if (!img.getAttribute('alt') || img.getAttribute('alt').trim() === '') {
                            allHaveAlts = false;
                        }
                    });
                    rules.image_alts = allHaveAlts;
                }

                const contentDocument = new DOMParser().parseFromString(htmlContent, 'text/html');
                rules.internal_links = Array.from(contentDocument.querySelectorAll('a[href]')).some(link => {
                    const href = (link.getAttribute('href') || '').trim();
                    if (href.startsWith('/') || href.startsWith('#')) return true;
                    try {
                        return new URL(href, window.location.origin).origin === window.location.origin;
                    } catch (error) {
                        return false;
                    }
                });

                // Compute overall SEO Score
                let score = 0;
                if (rules.keyword_exists) score += 5;
                if (rules.title_length) score += 10;
                if (rules.title_keyword) score += 10;
                if (rules.slug_keyword) score += 10;
                if (rules.desc_length) score += 10;
                if (rules.desc_keyword) score += 10;
                if (rules.content_length) score += 10;
                if (rules.keyword_density) score += 10;
                if (rules.first_paragraph) score += 10;
                if (rules.headings) score += 5;
                if (rules.image_alts) score += 5;
                if (rules.internal_links) score += 5;

                // Each check: a green tick or a red cross.
                Object.keys(rules).forEach(function (key) {
                    const element = document.getElementById('rule_' + key);
                    if (!element) return;
                    element.classList.toggle('is-pass', rules[key]);
                    element.classList.toggle('is-fail', !rules[key]);
                    const icon = element.querySelector('.rm-check-icon i');
                    if (icon) icon.className = 'ti ' + (rules[key] ? 'ti-check' : 'ti-x');
                });

                // Each group: "N errors" or "All good".
                document.querySelectorAll('[data-seo-group]').forEach(function (group) {
                    const failed = group.querySelectorAll('.seo-rule-item.is-fail').length;
                    const badge = group.querySelector('[data-seo-group-badge]');
                    if (!badge) return;
                    badge.textContent = failed ? translations.group_errors.replace(':count', failed) : translations.group_all_good;
                    badge.className = 'rm-group__badge ' + (failed ? 'is-bad' : 'is-good');
                });

                const wordCountElement = document.getElementById('seo_word_count');
                const densityElement = document.getElementById('seo_keyword_density');
                if (wordCountElement) wordCountElement.textContent = wordCount;
                if (densityElement) densityElement.textContent = density.toFixed(2) + '%';

                // Google preview, with the focus keyword in bold as Google shows it.
                const previewTitle = document.getElementById('seo_preview_title');
                const previewDescription = document.getElementById('seo_preview_description');
                const previewUrl = document.getElementById('seo_preview_url');
                if (previewTitle) previewTitle.innerHTML = highlight(seoTitle || translations.preview_title, keyword);
                if (previewDescription) previewDescription.innerHTML = highlight(seoDesc || translations.preview_description, keyword);
                if (previewUrl) {
                    const generatedUrl = window.location.origin + '/' + generateSlug(slug || title);
                    previewUrl.textContent = canonicalInput?.value.trim() || generatedUrl;
                }

                // The score pills (panel header, keyword field, publish box).
                const tone = score >= 80 ? 'is-good' : (score >= 50 ? 'is-ok' : 'is-bad');
                document.querySelectorAll('[data-seo-score]').forEach(element => element.textContent = score);
                document.querySelectorAll('[data-seo-score-pill]').forEach(function (pill) {
                    pill.classList.remove('is-good', 'is-ok', 'is-bad');
                    pill.classList.add(tone);
                });
            }

            function escapeHtml(text) {
                const node = document.createElement('div');
                node.textContent = text;
                return node.innerHTML;
            }

            function highlight(text, keyword) {
                const safe = escapeHtml(text);
                if (!keyword) return safe;
                const pattern = escapeHtml(keyword).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return safe.replace(new RegExp(pattern, 'gi'), match => '<strong>' + match + '</strong>');
            }

            // Bind listeners
            if (titleInput) titleInput.addEventListener('input', analyzeSEO);
            if (slugInput) slugInput.addEventListener('input', analyzeSEO);
            if (seoKeysInput) seoKeysInput.addEventListener('input', analyzeSEO);
            if (seoTitleInput) seoTitleInput.addEventListener('input', analyzeSEO);
            if (seoDescInput) seoDescInput.addEventListener('input', analyzeSEO);
            if (canonicalInput) canonicalInput.addEventListener('input', analyzeSEO);

            // Run initial analysis
            setTimeout(analyzeSEO, 500);
        });
    </script>
@endpush
