@include('admin.shared.rich-editor-assets')

@push('styles')
    <style>
        /* SEO box, Rank Math style. Tones: is-bad < 50 <= is-ok < 80 <= is-good. */
        .rm-panel { overflow: hidden; }
        .rm-panel .form-text { color: #64748b; font-size: 12px; line-height: 1.45; }
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
        .rm-serp__favicon { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 28px; height: 28px; overflow: hidden; border: 1px solid #e5eaf2; border-radius: 50%; background: #f1f3f4; }
        .rm-serp__favicon img { width: 100%; height: 100%; object-fit: cover; }
        .rm-snippet__foot { padding: 10px 14px; border-top: 1px dashed #e5eaf2; }
        .rm-snippet__editor { padding: 16px 18px; border-top: 1px solid #e5eaf2; background: #f8fafc; }
        .rm-snippet__locale { margin-bottom: 10px; color: #5a6a85; font-size: 13px; font-weight: 700; }
        .seo-preview-site { color: #202124; font-size: 14px; }
        .seo-preview-url { overflow: hidden; color: #4d5156; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .seo-preview-title { margin-top: 8px; color: #1a0dab; font-size: 20px; line-height: 1.3; overflow-wrap: anywhere; }
        .seo-preview-description { margin-top: 4px; color: #4d5156; font-size: 14px; line-height: 1.45; overflow-wrap: anywhere; }
        .seo-preview-title strong, .seo-preview-description strong { font-weight: 700; }
        .rm-keyword { margin-bottom: 16px; }
        .rm-tags { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 48px; padding: 6px 100px 6px 40px; border: 1px solid #dfe5ef; border-radius: 8px; background: #fff; cursor: text; }
        .rm-tags:focus-within { border-color: var(--bs-primary); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15); }
        .rm-tags__list { display: contents; }
        .rm-tag { display: inline-flex; align-items: center; gap: 5px; padding: 4px 6px 4px 10px; border-radius: 6px; background: #eef2f7; color: #1f2937; font-size: 13px; font-weight: 600; }
        .rm-tag.is-primary { background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); }
        .rm-tag button { padding: 0 2px; border: 0; background: none; color: inherit; line-height: 1; opacity: .6; }
        .rm-tag button:hover { opacity: 1; }
        .rm-tags__input { flex: 1 1 160px; min-width: 120px; padding: 4px; border: 0; outline: 0; background: transparent; font-weight: 600; }
        .rm-keyword__icon { position: absolute; top: 50%; left: 14px; transform: translateY(-50%); color: #94a3b8; font-size: 18px; }
        .rm-tags .rm-score { position: absolute; top: 50%; right: 8px; transform: translateY(-50%); }
        .rm-stats { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-bottom: 12px; color: #5a6a85; font-size: 13px; }
        .rm-stats strong { color: #1f2937; }
        .rm-group { margin-bottom: 12px; border: 1px solid #e5eaf2; border-radius: 12px; overflow: hidden; }
        .rm-group__head { display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 16px; border: 0; background: rgba(227, 35, 38, .08); color: #1f2937; font-size: 14.5px; font-weight: 700; text-align: left; }
        .rm-group.is-good .rm-group__head { background: rgba(16, 185, 129, .12); }
        .rm-group__head .ti-chevron-down { margin-left: auto; color: #94a3b8; transition: transform .2s; }
        .rm-group__head[aria-expanded="false"] .ti-chevron-down { transform: rotate(-90deg); }
        .rm-group__badge { padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .rm-group__badge.is-bad { color: #c01e25; background: #fff; box-shadow: inset 0 0 0 1px rgba(227, 35, 38, .24); }
        .rm-group__badge.is-good { color: #047857; background: #fff; box-shadow: inset 0 0 0 1px rgba(4, 120, 87, .2); }
        .rm-group__list { margin: 0; padding: 4px 16px 10px; border-top: 1px solid #f1f5f9; list-style: none; }
        .seo-rule-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px dashed #eef2f7; color: #334155; font-size: 14px; }
        .seo-rule-item:last-child { border-bottom: 0; }
        .rm-check-icon { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 20px; height: 20px; margin-top: 1px; border-radius: 50%; color: #fff; font-size: 12px; }
        .seo-rule-item.is-fail .rm-check-icon { background: #ef4444; }
        .seo-rule-item.is-pass .rm-check-icon { background: #10b981; }
        .rm-robots { padding: 14px 16px; border: 1px solid #e5eaf2; border-radius: 12px; }
        .rm-social-card { overflow: hidden; border: 1px solid #dadde1; border-radius: 10px; background: #fff; }
        .rm-social-card__image { display: flex; align-items: center; justify-content: center; aspect-ratio: 1.91 / 1; background: #f0f2f5 center / cover no-repeat; color: #b0b7c3; font-size: 32px; }
        .rm-social-card__image.has-image i { display: none; }
        .rm-social-card__body { padding: 10px 12px; background: #f0f2f5; }
        .rm-social-card__domain { display: flex; align-items: center; gap: 6px; overflow: hidden; color: #65676b; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .rm-social-card__domain img { width: 16px; height: 16px; flex: 0 0 16px; border-radius: 50%; object-fit: cover; }
        .rm-social-card__domain span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rm-social-card__title { display: -webkit-box; overflow: hidden; margin-top: 2px; color: #1c1e21; font-size: 16px; font-weight: 600; line-height: 1.3; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .rm-social-card__description { display: -webkit-box; overflow: hidden; margin-top: 2px; color: #65676b; font-size: 14px; -webkit-box-orient: vertical; -webkit-line-clamp: 1; }
        .rm-social-card--x { border-radius: 16px; }
        .rm-social-card--x .rm-social-card__body { background: #fff; }
        .rm-social-card--x .rm-social-card__title { font-size: 15px; font-weight: 400; -webkit-line-clamp: 1; }
        .rm-length { height: 4px; margin-top: 6px; overflow: hidden; border-radius: 4px; background: #eef2f7; }
        .rm-length span { display: block; width: 0; height: 100%; border-radius: 4px; background: #f59e0b; transition: width .2s, background-color .2s; }
        .rm-length span.is-good { background: #10b981; }
        .rm-length span.is-bad { background: #ef4444; }
        @media (max-width: 575.98px) {
            .rm-panel__body { padding: 14px; }
            .rm-tags { padding-right: 10px; }
            .rm-tags .rm-score { display: none; }
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

            // The snippet editor shows the SEO title/description of the language tab in view.
            function showSnippetLocale(code) {
                document.querySelectorAll('[data-seo-meta-locale]').forEach(function (group) {
                    group.classList.toggle('d-none', group.dataset.seoMetaLocale !== code);
                });
            }
            document.querySelectorAll('a[data-bs-toggle="tab"][href^="#post-language-"]').forEach(function (link) {
                link.addEventListener('shown.bs.tab', function () {
                    showSnippetLocale(link.getAttribute('href').replace('#post-language-', ''));
                });
            });
            document.getElementById('seo-snippet-editor')?.addEventListener('shown.bs.collapse', function () {
                document.querySelector('[data-seo-meta-locale]:not(.d-none) [data-seo-title-input]')?.focus();
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
                primary_keyword: @json(__('admin.posts.seo_panel.primary_keyword')),
                remove_keyword: @json(__('admin.posts.seo_panel.remove_keyword')),
                preview_title: @json(__('admin.posts.placeholders.seo_title')),
                preview_description: @json(__('admin.posts.placeholders.seo_description'))
            };

            function analyzeSEO() {
                if (!seoKeysInput) return;

                // The first keyword is the focus keyword (PostSeoAnalyzer::primaryKeyword).
                const keyword = (seoKeysInput.value.split(',')[0] || '').trim().toLowerCase();
                const title = (titleInput ? titleInput.value : '').trim();
                const slug = (slugInput ? slugInput.value : '').trim();
                const seoTitle = (seoTitleInput ? seoTitleInput.value : '').trim() || title;
                const seoDesc = (seoDescInput ? seoDescInput.value : '').trim();
                
                const htmlContent = contentEditor ? contentEditor.getHTML() : '';
                const textContent = contentEditor ? contentEditor.getText() : '';

                // Word count
                const words = textContent.split(/\s+/).filter(w => w.length > 0);
                const wordCount = words.length;

                // The same checks and weights as PostSeoAnalyzer on the server (keep them in step).
                const contentDocument = new DOMParser().parseFromString(htmlContent, 'text/html');
                const plain = (node) => (node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
                const seoTitleLower = seoTitle.toLowerCase();
                const ownHosts = [window.location.hostname.replace(/^www\./, '')];
                const links = Array.from(contentDocument.querySelectorAll('a[href]')).map(link => (link.getAttribute('href') || '').trim());
                const images = Array.from(contentDocument.querySelectorAll('img'));
                const paragraphs = Array.from(contentDocument.querySelectorAll('p'));
                const longestParagraph = (paragraphs.length ? paragraphs : [contentDocument.body])
                    .reduce((longest, node) => Math.max(longest, plain(node).split(' ').filter(Boolean).length), 0);

                // Density counts the keyword's words, as the server does.
                let density = 0;
                if (wordCount > 0 && keyword.length > 0) {
                    const occurrences = countOccurrences(textContent.toLowerCase(), keyword);
                    density = Math.round((occurrences * Math.max(1, keyword.split(/\s+/).filter(Boolean).length) / wordCount) * 10000) / 100;
                }
                const keywordAt = keyword ? seoTitleLower.indexOf(keyword) : -1;

                const rules = {
                    keyword_exists: keyword.length > 0,
                    title_keyword: keyword.length > 0 && seoTitleLower.includes(keyword),
                    desc_keyword: keyword.length > 0 && seoDesc.toLowerCase().includes(keyword),
                    slug_keyword: keyword.length > 0 && generateSlug(slug).includes(generateSlug(keyword)),
                    first_paragraph: keyword.length > 0 && words.slice(0, 100).join(' ').toLowerCase().includes(keyword),
                    content_length: wordCount >= 300,
                    keyword_in_subheadings: keyword.length > 0 && Array.from(contentDocument.querySelectorAll('h2, h3, h4')).some(node => plain(node).includes(keyword)),
                    keyword_image_alt: keyword.length > 0 && images.some(img => (img.getAttribute('alt') || '').toLowerCase().includes(keyword)),
                    keyword_density: density >= 0.5 && density <= 2.5,
                    url_length: slug.length > 0 && slug.length <= 75,
                    external_links: links.some(href => {
                        if (!/^(https?:)?\/\//i.test(href)) return false;
                        try { return !ownHosts.includes(new URL(href, window.location.origin).hostname.replace(/^www\./, '')); } catch (error) { return false; }
                    }),
                    internal_links: links.some(href => {
                        if (href.startsWith('#') || (href.startsWith('/') && !href.startsWith('//'))) return true;
                        try { return new URL(href, window.location.origin).origin === window.location.origin; } catch (error) { return false; }
                    }),
                    image_alts: images.every(img => (img.getAttribute('alt') || '').trim() !== ''),
                    title_length: seoTitle.length >= 40 && seoTitle.length <= 60,
                    desc_length: seoDesc.length >= 120 && seoDesc.length <= 160,
                    // In the first half of the title, where it weighs most and survives truncation.
                    title_keyword_start: keywordAt !== -1 && keywordAt <= seoTitle.length / 2,
                    title_number: /\d/.test(seoTitle),
                    headings: /<h[23]\b/i.test(htmlContent),
                    short_paragraphs: wordCount > 0 && longestParagraph <= 150,
                    content_media: /<(img|iframe|video)\b/i.test(htmlContent)
                };

                const weights = @json(\App\Services\PostSeoAnalyzer::WEIGHTS);
                const score = Object.keys(weights).reduce((total, key) => total + (rules[key] ? weights[key] : 0), 0);

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
                    group.classList.toggle('is-good', failed === 0);
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

                updateSocialPreview(seoTitle, seoDesc);
                updateFaqStatus(contentDocument);

                // The score pills (panel header, keyword field, publish box).
                const tone = score >= 80 ? 'is-good' : (score >= 50 ? 'is-ok' : 'is-bad');
                document.querySelectorAll('[data-seo-score]').forEach(element => element.textContent = score);
                document.querySelectorAll('[data-seo-score-pill]').forEach(function (pill) {
                    pill.classList.remove('is-good', 'is-ok', 'is-bad');
                    pill.classList.add(tone);
                });
            }

            // Facebook / X previews: the language in view, falling back like the storefront does
            // (social title → SEO title → post title; social image → featured image).
            const socialImageInput = document.querySelector('[data-social-image]');
            function updateSocialPreview(seoTitle, seoDesc) {
                const group = document.querySelector('#seo-tab-social [data-seo-meta-locale]:not(.d-none)');
                const snippet = document.querySelector('#seo-snippet-editor [data-seo-meta-locale]:not(.d-none)');
                const ownTitle = group?.querySelector('[data-social-title]')?.value.trim();
                const ownDescription = group?.querySelector('[data-social-description]')?.value.trim();
                const localTitle = snippet?.querySelector('[data-seo-title-input]')?.value.trim();
                const localDescription = snippet?.querySelector('[data-seo-description-input]')?.value.trim();
                const featured = document.getElementById('post_image_preview');
                const featuredUrl = featured && !featured.classList.contains('d-none') ? featured.getAttribute('src') : '';
                const image = (socialImageInput?.value || '').trim() || (featuredUrl && featuredUrl !== '#' ? featuredUrl : '');

                document.querySelectorAll('[data-social-preview-title]').forEach(node => node.textContent = ownTitle || localTitle || seoTitle || translations.preview_title);
                document.querySelectorAll('[data-social-preview-description]').forEach(node => node.textContent = ownDescription || localDescription || seoDesc || '');
                document.querySelectorAll('[data-social-preview-image]').forEach(function (node) {
                    node.style.backgroundImage = image ? 'url("' + image.replace(/"/g, '%22') + '")' : '';
                    node.classList.toggle('has-image', image !== '');
                });
            }
            document.querySelectorAll('[data-social-title], [data-social-description]').forEach(field => field.addEventListener('input', analyzeSEO));
            socialImageInput?.addEventListener('change', analyzeSEO);
            document.getElementById('og_image_file')?.addEventListener('media:selected', () => setTimeout(analyzeSEO, 0));
            document.getElementById('post_image_file')?.addEventListener('media:selected', () => setTimeout(analyzeSEO, 0));
            document.querySelector('[data-social-image-clear]')?.addEventListener('click', function () {
                if (socialImageInput) socialImageInput.value = '';
                isDirty = true;
                analyzeSEO();
            });

            // FAQ schema status, read the way App\Support\Seo\FaqSchema reads the post.
            const faqStatus = document.querySelector('[data-schema-faq-status]');
            function updateFaqStatus(doc) {
                if (!faqStatus) return;
                const known = JSON.parse(faqStatus.dataset.headings || '[]');
                let count = 0;
                doc.querySelectorAll('h2').forEach(function (heading) {
                    const text = (heading.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
                    if (!known.includes(text) && !text.includes('câu hỏi thường gặp') && !text.includes('hỏi đáp') && !/\bfaqs?\b/.test(text)) return;
                    let question = false;
                    let answered = false;
                    for (let node = heading.nextElementSibling; node && !/^H[12]$/.test(node.tagName); node = node.nextElementSibling) {
                        if (node.tagName === 'H3') {
                            if (question && answered) count++;
                            question = (node.textContent || '').trim() !== '';
                            answered = false;
                        } else if (question && (node.textContent || '').trim() !== '') {
                            answered = true;
                        }
                    }
                    if (question && answered) count++;
                });
                faqStatus.textContent = count ? faqStatus.dataset.found.replace(':count', count) : faqStatus.dataset.missing;
                faqStatus.classList.toggle('text-success', count > 0);
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

            // Focus keywords as badges: Enter or a comma adds one, Backspace removes the last.
            // The hidden field submits them comma-separated; the first one is scored.
            const keywordBox = document.querySelector('[data-seo-keywords]');
            const keywordEntry = document.getElementById('seo_keys_entry');
            const keywordList = keywordBox ? keywordBox.querySelector('[data-seo-keyword-list]') : null;
            let keywords = (seoKeysInput ? seoKeysInput.value : '').split(',').map(word => word.trim()).filter(Boolean);

            function renderKeywords() {
                if (!keywordList) return;
                keywordList.innerHTML = '';
                keywords.forEach(function (word, index) {
                    const tag = document.createElement('span');
                    tag.className = 'rm-tag' + (index === 0 ? ' is-primary' : '');
                    if (index === 0) {
                        tag.title = translations.primary_keyword;
                        tag.insertAdjacentHTML('beforeend', '<i class="ti ti-star-filled" aria-hidden="true"></i>');
                    }
                    const text = document.createElement('span');
                    text.textContent = word;
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.setAttribute('aria-label', translations.remove_keyword + ' ' + word);
                    remove.innerHTML = '<i class="ti ti-x" aria-hidden="true"></i>';
                    remove.addEventListener('click', function () {
                        keywords.splice(index, 1);
                        commitKeywords();
                        keywordEntry.focus();
                    });
                    tag.append(text, remove);
                    keywordList.appendChild(tag);
                });
                keywordEntry.placeholder = keywords.length ? '' : keywordEntry.dataset.placeholder;
            }

            function commitKeywords() {
                seoKeysInput.value = keywords.join(', ');
                seoKeysInput.dispatchEvent(new Event('input', { bubbles: true }));
                isDirty = true;
                renderKeywords();
            }

            function addKeywords(text) {
                text.split(',').map(word => word.trim().replace(/\s+/g, ' ')).filter(Boolean).forEach(function (word) {
                    const exists = keywords.some(current => current.toLowerCase() === word.toLowerCase());
                    if (!exists && keywords.concat(word).join(', ').length <= 255) keywords.push(word);
                });
                keywordEntry.value = '';
                commitKeywords();
            }

            if (keywordBox && keywordEntry && seoKeysInput) {
                keywordEntry.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ',') {
                        event.preventDefault();
                        if (keywordEntry.value.trim()) addKeywords(keywordEntry.value);
                    } else if (event.key === 'Backspace' && keywordEntry.value === '' && keywords.length) {
                        keywords.pop();
                        commitKeywords();
                    }
                });
                keywordEntry.addEventListener('paste', function (event) {
                    const pasted = (event.clipboardData || window.clipboardData).getData('text');
                    if (!pasted.includes(',')) return;
                    event.preventDefault();
                    addKeywords(keywordEntry.value + pasted);
                });
                keywordEntry.addEventListener('blur', function () {
                    if (keywordEntry.value.trim()) addKeywords(keywordEntry.value);
                });
                keywordBox.addEventListener('click', function (event) {
                    if (event.target === keywordBox || event.target === keywordList) keywordEntry.focus();
                });
                renderKeywords();
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
