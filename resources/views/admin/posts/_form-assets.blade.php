@push('styles')
    <style>
        /* Make editor text color dark and highly readable */
        .ql-editor {
            color: #111827 !important; /* Rich charcoal/dark black */
            font-size: 15.5px !important;
            line-height: 1.65 !important;
        }
        /* Make Quill placeholder dark grey instead of light grey */
        .ql-editor.ql-blank::before {
            color: #6b7280 !important;
            font-style: normal !important;
        }

        /* Fullscreen editor wrapper styles */
        #editor_wrapper.quill-fullscreen {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999 !important;
            background: #ffffff !important;
            padding: 20px 30px !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
        }
        #editor_wrapper.quill-fullscreen .ql-toolbar {
            border-top-left-radius: 8px !important;
            border-top-right-radius: 8px !important;
            background-color: #f8f9fa !important;
        }
        #editor_wrapper.quill-fullscreen .ql-container {
            flex-grow: 1 !important;
            height: calc(100vh - 120px) !important;
            background-color: #ffffff !important;
            border-bottom-left-radius: 8px !important;
            border-bottom-right-radius: 8px !important;
        }

        /* Custom fonts in editor */
        .ql-font-arial { font-family: Arial, sans-serif !important; }
        .ql-font-georgia { font-family: Georgia, serif !important; }
        .ql-font-impact { font-family: Impact, charcoal, sans-serif !important; }
        .ql-font-tahoma { font-family: Tahoma, Geneva, sans-serif !important; }
        .ql-font-times-new-roman { font-family: "Times New Roman", Times, serif !important; }
        .ql-font-verdana { font-family: Verdana, Geneva, sans-serif !important; }
        .ql-font-quicksand { font-family: "Quicksand", sans-serif !important; }
        .ql-font-roboto { font-family: "Roboto", sans-serif !important; }

        /* Custom fonts in toolbar picker label/items */
        .ql-snow .ql-picker.ql-font .ql-picker-label::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item::before {
            content: 'Sans Serif' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="serif"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="serif"]::before {
            content: 'Serif' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="monospace"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="monospace"]::before {
            content: 'Monospace' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="arial"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="arial"]::before {
            content: 'Arial' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="georgia"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="georgia"]::before {
            content: 'Georgia' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="impact"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="impact"]::before {
            content: 'Impact' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="tahoma"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="tahoma"]::before {
            content: 'Tahoma' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="times-new-roman"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="times-new-roman"]::before {
            content: 'Times New Roman' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="verdana"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="verdana"]::before {
            content: 'Verdana' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="quicksand"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="quicksand"]::before {
            content: 'Quicksand' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="roboto"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="roboto"]::before {
            content: 'Roboto' !important;
        }

        /* Show the preview of the fonts in the dropdown options list */
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="arial"] { font-family: Arial, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="georgia"] { font-family: Georgia, serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="impact"] { font-family: Impact, charcoal, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="tahoma"] { font-family: Tahoma, Geneva, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="times-new-roman"] { font-family: "Times New Roman", Times, serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="verdana"] { font-family: Verdana, Geneva, sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="quicksand"] { font-family: "Quicksand", sans-serif !important; }
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="roboto"] { font-family: "Roboto", sans-serif !important; }

        .seo-google-preview {
            padding: 16px;
            border: 1px solid #e5eaf2;
            border-radius: 12px;
            background: #fff;
        }
        #seo_google_preview_result {
            max-width: 600px;
            transition: max-width 0.2s ease;
        }
        #seo_google_preview_result.is-mobile {
            max-width: 360px;
        }
        .seo-preview-site {
            color: #202124;
            font-size: 14px;
        }
        .seo-preview-url {
            color: #4d5156;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .seo-preview-title {
            margin-top: 4px;
            color: #1a0dab;
            font-size: 20px;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }
        .seo-preview-description {
            margin-top: 4px;
            color: #4d5156;
            font-size: 14px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('admin-assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/quill/dist/quill.min.js') }}"></script>
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

            // Register custom fonts in Quill
            const Font = Quill.import('formats/font');
            Font.whitelist = ['sans-serif', 'serif', 'monospace', 'arial', 'georgia', 'impact', 'tahoma', 'times-new-roman', 'verdana', 'quicksand', 'roboto'];
            Quill.register(Font, true);

            // Quill initialization
            let quill = null;
            const contentInput = document.getElementById('content_input');

            document.querySelectorAll('.catalog-quill').forEach(function (editorElement) {
                const target = document.getElementById(editorElement.dataset.target);
                if (!target) return;
                const editor = new Quill(editorElement, {
                    theme: 'snow',
                    placeholder: editorElement.dataset.placeholder || '',
                    modules: {
                        toolbar: [
                            [{ 'font': ['', 'serif', 'monospace', 'arial', 'georgia', 'impact', 'tahoma', 'times-new-roman', 'verdana', 'quicksand', 'roboto'] }, { 'size': [] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'script': 'sub' }, { 'script': 'super' }],
                            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                            [{ 'blockquote': true }, { 'code-block': true }],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }, { 'indent': '-1' }, { 'indent': '+1' }],
                            [{ 'align': [] }],
                            ['link', 'image', 'video'],
                            ['clean']
                        ]
                    }
                });
                editorElement.__quill = editor;
                if (editorElement.id === 'content_editor') quill = editor;

                editor.on('text-change', function() {
                    isDirty = true;
                    target.value = editor.root.innerHTML;
                    if (editorElement.id === 'content_editor') analyzeSEO();
                });

                if (form) {
                    form.addEventListener('submit', function () {
                        target.value = editor.root.innerHTML;
                    });
                }

                // Fullscreen toggle logic
                const toggleBtn = document.getElementById('toggle_fullscreen');
                const editorWrapper = document.getElementById('editor_wrapper');
                const fullscreenIcon = document.getElementById('fullscreen_icon');
                const fullscreenText = document.getElementById('fullscreen_text');

                if (toggleBtn && editorWrapper && editorElement.id === 'content_editor') {
                    toggleBtn.addEventListener('click', function() {
                        const isFullscreen = editorWrapper.classList.toggle('quill-fullscreen');
                        if (isFullscreen) {
                            fullscreenIcon.className = 'ti ti-minimize fs-4';
                            fullscreenText.textContent = 'Thu nhỏ';
                            document.addEventListener('keydown', handleEsc);
                        } else {
                            fullscreenIcon.className = 'ti ti-maximize fs-4';
                            fullscreenText.textContent = 'Phóng to';
                            document.removeEventListener('keydown', handleEsc);
                        }
                    });

                    function handleEsc(e) {
                        if (e.key === 'Escape') {
                            editorWrapper.classList.remove('quill-fullscreen');
                            fullscreenIcon.className = 'ti ti-maximize fs-4';
                            fullscreenText.textContent = 'Phóng to';
                            document.removeEventListener('keydown', handleEsc);
                        }
                    }
                }
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

            document.querySelectorAll('[data-seo-title-input]').forEach(function (input) {
                const counter = document.querySelector('[data-seo-title-count="' + input.dataset.seoTitleInput + '"]');
                const refresh = () => {
                    if (counter) counter.textContent = input.value.length;
                };
                input.addEventListener('input', refresh);
                refresh();
            });

            document.querySelectorAll('[data-seo-description-input]').forEach(function (input) {
                const counter = document.querySelector('[data-seo-description-count="' + input.dataset.seoDescriptionInput + '"]');
                const refresh = () => {
                    if (counter) counter.textContent = input.value.length;
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
                
                const htmlContent = quill ? quill.root.innerHTML : '';
                const textContent = quill ? quill.getText().trim() : '';

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

                // Update Rule indicators UI
                function updateRuleUI(id, passed) {
                    const element = document.getElementById(id);
                    if (!element) return;
                    
                    const dot = element.querySelector('.seo-status-dot');
                    if (dot) {
                        dot.className = 'seo-status-dot ' + (passed ? 'seo-status-green' : 'seo-status-red');
                    }
                }

                updateRuleUI('rule_keyword_exists', rules.keyword_exists);
                updateRuleUI('rule_title_length', rules.title_length);
                updateRuleUI('rule_title_keyword', rules.title_keyword);
                updateRuleUI('rule_slug_keyword', rules.slug_keyword);
                updateRuleUI('rule_desc_length', rules.desc_length);
                updateRuleUI('rule_desc_keyword', rules.desc_keyword);
                updateRuleUI('rule_content_length', rules.content_length);
                updateRuleUI('rule_keyword_density', rules.keyword_density);
                updateRuleUI('rule_first_paragraph', rules.first_paragraph);
                updateRuleUI('rule_headings', rules.headings);
                updateRuleUI('rule_image_alts', rules.image_alts);
                updateRuleUI('rule_internal_links', rules.internal_links);

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

                const wordCountElement = document.getElementById('seo_word_count');
                const densityElement = document.getElementById('seo_keyword_density');
                if (wordCountElement) wordCountElement.textContent = wordCount;
                if (densityElement) densityElement.textContent = density.toFixed(2) + '%';

                const previewTitle = document.getElementById('seo_preview_title');
                const previewDescription = document.getElementById('seo_preview_description');
                const previewUrl = document.getElementById('seo_preview_url');
                if (previewTitle) previewTitle.textContent = seoTitle || translations.preview_title;
                if (previewDescription) previewDescription.textContent = seoDesc || translations.preview_description;
                if (previewUrl) {
                    const generatedUrl = window.location.origin + '/' + generateSlug(slug || title);
                    previewUrl.textContent = canonicalInput?.value.trim() || generatedUrl;
                }

                // Animate circular ring gauge
                const circle = document.getElementById('seo_progress_circle');
                if (circle) {
                    const radius = circle.r.baseVal.value;
                    const circumference = radius * 2 * Math.PI;
                    const offset = circumference - (score / 100) * circumference;
                    circle.style.strokeDashoffset = offset;

                    // Color based on score
                    if (score < 50) {
                        circle.style.stroke = '#ef4444'; // Red
                    } else if (score < 80) {
                        circle.style.stroke = '#f97316'; // Orange
                    } else {
                        circle.style.stroke = '#22c55e'; // Green
                    }
                }

                // Update text fields
                document.getElementById('seo_score_txt').innerText = score;
                
                const label = document.getElementById('seo_rating_label');
                const badge = document.getElementById('seo_overall_badge');
                
                if (score < 50) {
                    label.innerText = wordCount < 100 ? translations.rating_too_short : translations.rating_need_optimize;
                    label.style.color = '#ef4444';
                    badge.innerText = translations.status_need_optimize;
                    badge.className = 'badge bg-danger text-white fw-bold px-2 py-1 fs-1';
                } else if (score < 80) {
                    label.innerText = translations.rating_good;
                    label.style.color = '#f97316';
                    badge.innerText = translations.status_good;
                    badge.className = 'badge bg-warning text-white fw-bold px-2 py-1 fs-1';
                } else {
                    label.innerText = translations.rating_excellent;
                    label.style.color = '#22c55e';
                    badge.innerText = translations.status_excellent;
                    badge.className = 'badge bg-success text-white fw-bold px-2 py-1 fs-1';
                }
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
