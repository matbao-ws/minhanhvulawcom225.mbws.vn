/*
 * The admin SEO box (resources/views/admin/shared/seo-box.blade.php) of a post, product or
 * policy page: live score and checks, Google and social previews, focus keyword badges.
 *
 * The checks are App\Services\PostSeoAnalyzer's, run here as the admin types; keep both in
 * step, rule names alike. The weights come from the server (config.weights), so the score in
 * the browser is the one saved.
 *
 * Each box carries its config in data-seo-config: the form's title, slug and body (a rich
 * editor target), its featured image and language tabs, and the texts it shows.
 */
(function () {
    'use strict';

    function slugify(text) {
        return String(text).toLowerCase()
            .replace(/á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ/g, 'a')
            .replace(/é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ/g, 'e')
            .replace(/í|ì|ỉ|ĩ|ị/g, 'i')
            .replace(/ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ/g, 'o')
            .replace(/ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự/g, 'u')
            .replace(/ý|ỳ|ỷ|ỹ|ỵ/g, 'y')
            .replace(/đ/g, 'd')
            .replace(/\s+/g, '-')
            .replace(/[^\w-]+/g, '')
            .replace(/--+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    function countOccurrences(text, part) {
        if (!part) return 0;
        let count = 0;
        for (let at = text.indexOf(part); at >= 0; at = text.indexOf(part, at + part.length)) count++;
        return count;
    }

    function escapeHtml(text) {
        const node = document.createElement('div');
        node.textContent = text;
        return node.innerHTML;
    }

    /** The focus keyword in bold, as Google shows it. */
    function highlight(text, keyword) {
        const safe = escapeHtml(text);
        if (!keyword) return safe;
        const pattern = escapeHtml(keyword).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return safe.replace(new RegExp(pattern, 'gi'), match => '<strong>' + match + '</strong>');
    }

    function init(box) {
        const config = JSON.parse(box.dataset.seoConfig || '{}');
        const selectors = config.selectors || {};
        const t = config.t || {};
        const find = (selector) => (selector ? document.querySelector(selector) : null);
        const titleInput = find(selectors.title);
        const slugInput = find(selectors.slug);
        const keysInput = box.querySelector('[data-seo-keys]');
        const canonicalInput = box.querySelector('[data-seo-canonical]');
        const previewResult = box.querySelector('#seo_google_preview_result');
        const socialImageInput = box.querySelector('[data-social-image]');
        const faqStatus = box.querySelector('[data-schema-faq-status]');
        const field = (kind, locale) => box.querySelector('[data-seo-' + kind + '-input="' + locale + '"]');
        const editor = () => (window.AdminRichEditor && selectors.content ? window.AdminRichEditor.forTarget(selectors.content) : null);

        // Length bar under the SEO title/description, as in Rank Math: green inside the range.
        function lengthBar(input, key, min, max) {
            const bar = box.querySelector('[data-seo-length="' + key + '"]');
            if (!bar) return;
            const length = input.value.trim().length;
            bar.style.width = Math.min(100, length / max * 100) + '%';
            bar.className = length > max ? 'is-bad' : (length >= min ? 'is-good' : '');
        }
        [['title', 40, 60], ['description', 120, 160]].forEach(function ([kind, min, max]) {
            box.querySelectorAll('[data-seo-' + kind + '-input]').forEach(function (input) {
                const locale = input.getAttribute('data-seo-' + kind + '-input');
                const counter = box.querySelector('[data-seo-' + kind + '-count="' + locale + '"]');
                const refresh = function () {
                    if (counter) counter.textContent = input.value.length;
                    lengthBar(input, locale + '-' + kind, min, max);
                };
                input.addEventListener('input', refresh);
                input.addEventListener('input', analyze);
                refresh();
            });
        });

        box.querySelectorAll('[data-seo-preview-device]').forEach(function (button) {
            button.addEventListener('click', function () {
                box.querySelectorAll('[data-seo-preview-device]').forEach(item => item.classList.remove('active'));
                button.classList.add('active');
                previewResult?.classList.toggle('is-mobile', button.dataset.seoPreviewDevice === 'mobile');
            });
        });

        // The snippet editor and share fields show the language tab in view.
        if (selectors.languageTabs) {
            const prefix = selectors.languageTabs;
            const targetOf = (tab) => tab.getAttribute('data-bs-target') || tab.getAttribute('href') || '';
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (link) {
                if (!targetOf(link).startsWith(prefix)) return;
                link.addEventListener('shown.bs.tab', function () {
                    const code = targetOf(link).slice(prefix.length);
                    box.querySelectorAll('[data-seo-meta-locale]').forEach(group => group.classList.toggle('d-none', group.dataset.seoMetaLocale !== code));
                    analyze();
                });
            });
        }
        box.querySelector('#seo-snippet-editor')?.addEventListener('shown.bs.collapse', function () {
            box.querySelector('[data-seo-meta-locale]:not(.d-none) [data-seo-title-input]')?.focus();
        });

        function analyze() {
            if (!keysInput) return;
            const content = editor();
            // The first keyword is the focus keyword (PostSeoAnalyzer::primaryKeyword).
            const keyword = (keysInput.value.split(',')[0] || '').trim().toLowerCase();
            const title = (titleInput ? titleInput.value : '').trim();
            const slug = (slugInput ? slugInput.value : '').trim();
            const seoTitle = (field('title', config.defaultLocale)?.value || '').trim() || title;
            const seoDesc = (field('description', config.defaultLocale)?.value || '').trim();
            const htmlContent = content ? content.getHTML() : '';
            const textContent = content ? content.getText() : '';
            const words = textContent.split(/\s+/).filter(Boolean);
            const wordCount = words.length;

            const doc = new DOMParser().parseFromString(htmlContent, 'text/html');
            const plain = (node) => (node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const seoTitleLower = seoTitle.toLowerCase();
            const ownHosts = [window.location.hostname.replace(/^www\./, '')];
            const links = Array.from(doc.querySelectorAll('a[href]')).map(link => (link.getAttribute('href') || '').trim());
            const images = Array.from(doc.querySelectorAll('img'));
            const paragraphs = Array.from(doc.querySelectorAll('p'));
            const longestParagraph = (paragraphs.length ? paragraphs : [doc.body])
                .reduce((longest, node) => Math.max(longest, plain(node).split(' ').filter(Boolean).length), 0);

            // Density counts the keyword's words, as the server does.
            let density = 0;
            if (wordCount > 0 && keyword) {
                density = Math.round((countOccurrences(textContent.toLowerCase(), keyword) * Math.max(1, keyword.split(/\s+/).filter(Boolean).length) / wordCount) * 10000) / 100;
            }
            const keywordAt = keyword ? seoTitleLower.indexOf(keyword) : -1;

            const rules = {
                keyword_exists: keyword.length > 0,
                title_keyword: keyword.length > 0 && seoTitleLower.includes(keyword),
                desc_keyword: keyword.length > 0 && seoDesc.toLowerCase().includes(keyword),
                slug_keyword: keyword.length > 0 && slugify(slug).includes(slugify(keyword)),
                first_paragraph: keyword.length > 0 && words.slice(0, 100).join(' ').toLowerCase().includes(keyword),
                content_length: wordCount >= 300,
                keyword_in_subheadings: keyword.length > 0 && Array.from(doc.querySelectorAll('h2, h3, h4')).some(node => plain(node).includes(keyword)),
                keyword_image_alt: keyword.length > 0 && images.some(img => (img.getAttribute('alt') || '').toLowerCase().includes(keyword)),
                keyword_density: density >= 0.5 && density <= 2.5,
                url_length: slug.length > 0 && slug.length <= 75,
                external_links: links.some(function (href) {
                    if (!/^(https?:)?\/\//i.test(href)) return false;
                    try { return !ownHosts.includes(new URL(href, window.location.origin).hostname.replace(/^www\./, '')); } catch (error) { return false; }
                }),
                internal_links: links.some(function (href) {
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
                content_media: /<(img|iframe|video)\b/i.test(htmlContent),
            };
            const weights = config.weights || {};
            const score = Object.keys(weights).reduce((total, key) => total + (rules[key] ? weights[key] : 0), 0);

            // Each check: a green tick or a red cross; each group: "N errors" or "All good".
            Object.keys(rules).forEach(function (key) {
                const element = box.querySelector('#rule_' + key);
                if (!element) return;
                element.classList.toggle('is-pass', rules[key]);
                element.classList.toggle('is-fail', !rules[key]);
                const icon = element.querySelector('.rm-check-icon i');
                if (icon) icon.className = 'ti ' + (rules[key] ? 'ti-check' : 'ti-x');
            });
            box.querySelectorAll('[data-seo-group]').forEach(function (group) {
                const failed = group.querySelectorAll('.seo-rule-item.is-fail').length;
                const badge = group.querySelector('[data-seo-group-badge]');
                if (!badge) return;
                badge.textContent = failed ? String(t.group_errors || '').replace(':count', failed) : t.group_all_good;
                badge.className = 'rm-group__badge ' + (failed ? 'is-bad' : 'is-good');
            });

            const wordCountElement = box.querySelector('#seo_word_count');
            const densityElement = box.querySelector('#seo_keyword_density');
            if (wordCountElement) wordCountElement.textContent = wordCount;
            if (densityElement) densityElement.textContent = density.toFixed(2) + '%';

            // Google preview.
            const previewTitle = box.querySelector('#seo_preview_title');
            const previewDescription = box.querySelector('#seo_preview_description');
            const previewUrl = box.querySelector('#seo_preview_url');
            if (previewTitle) previewTitle.innerHTML = highlight(seoTitle || t.preview_title || '', keyword);
            if (previewDescription) previewDescription.innerHTML = highlight(seoDesc || t.preview_description || '', keyword);
            if (previewUrl) {
                const pathSlug = slugify(slug || title);
                const generated = config.urlPattern ? config.urlPattern.replace('seo-box-slug', pathSlug) : window.location.origin + '/' + pathSlug;
                previewUrl.textContent = canonicalInput?.value.trim() || generated;
            }

            updateSocialPreview(seoTitle, seoDesc);
            updateFaqStatus(doc);

            // Every score pill on the page (box header, keyword field, the form's publish card).
            const tone = score >= 80 ? 'is-good' : (score >= 50 ? 'is-ok' : 'is-bad');
            document.querySelectorAll('[data-seo-score]').forEach(element => { element.textContent = score; });
            document.querySelectorAll('[data-seo-score-pill]').forEach(function (pill) {
                pill.classList.remove('is-good', 'is-ok', 'is-bad');
                pill.classList.add(tone);
            });
        }

        // Facebook / X previews of the language in view, falling back as the storefront does
        // (share title → SEO title → title; share image → featured image).
        function updateSocialPreview(seoTitle, seoDesc) {
            const group = box.querySelector('#seo-tab-social [data-seo-meta-locale]:not(.d-none)');
            const snippet = box.querySelector('#seo-snippet-editor [data-seo-meta-locale]:not(.d-none)');
            const ownTitle = group?.querySelector('[data-social-title]')?.value.trim();
            const ownDescription = group?.querySelector('[data-social-description]')?.value.trim();
            const localTitle = snippet?.querySelector('[data-seo-title-input]')?.value.trim();
            const localDescription = snippet?.querySelector('[data-seo-description-input]')?.value.trim();
            const featured = find(selectors.image);
            const featuredUrl = featured && !featured.classList.contains('d-none') ? featured.getAttribute('src') : '';
            const image = (socialImageInput?.value || '').trim() || (featuredUrl && featuredUrl !== '#' ? featuredUrl : '');

            box.querySelectorAll('[data-social-preview-title]').forEach(node => { node.textContent = ownTitle || localTitle || seoTitle || t.preview_title || ''; });
            box.querySelectorAll('[data-social-preview-description]').forEach(node => { node.textContent = ownDescription || localDescription || seoDesc || ''; });
            box.querySelectorAll('[data-social-preview-image]').forEach(function (node) {
                node.style.backgroundImage = image ? 'url("' + image.replace(/"/g, '%22') + '")' : '';
                node.classList.toggle('has-image', image !== '');
            });
        }

        // FAQ schema status, read the way App\Support\Seo\FaqSchema reads the body.
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

        // Focus keywords as badges: Enter or a comma adds one, Backspace removes the last.
        // The hidden field submits them comma-separated; the first one is scored.
        const keywordBox = box.querySelector('[data-seo-keywords]');
        const keywordEntry = box.querySelector('#seo_keys_entry');
        const keywordList = keywordBox ? keywordBox.querySelector('[data-seo-keyword-list]') : null;
        let keywords = (keysInput ? keysInput.value : '').split(',').map(word => word.trim()).filter(Boolean);

        function renderKeywords() {
            if (!keywordList || !keywordEntry) return;
            keywordList.innerHTML = '';
            keywords.forEach(function (word, index) {
                const tag = document.createElement('span');
                tag.className = 'rm-tag' + (index === 0 ? ' is-primary' : '');
                if (index === 0) {
                    tag.title = t.primary_keyword || '';
                    tag.insertAdjacentHTML('beforeend', '<i class="ti ti-star-filled" aria-hidden="true"></i>');
                }
                const text = document.createElement('span');
                text.textContent = word;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', (t.remove_keyword || '') + ' ' + word);
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
            keysInput.value = keywords.join(', ');
            keysInput.dispatchEvent(new Event('input', { bubbles: true }));
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

        // "Use as focus keyword" from the AI meta suggestions: first badge, so the checks use it.
        document.addEventListener('seo-box:use-keyword', function (event) {
            const word = String(event.detail?.keyword || '').trim().replace(/\s+/g, ' ');
            if (!word || !keysInput) return;
            keywords = [word].concat(keywords.filter(current => current.toLowerCase() !== word.toLowerCase()));
            while (keywords.join(', ').length > 255) keywords.pop();
            commitKeywords();
        });

        if (keywordBox && keywordEntry && keysInput) {
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

        // What changes the score: the form's title, slug and body, and the box's own fields.
        [titleInput, slugInput, keysInput, canonicalInput].forEach(input => input?.addEventListener('input', analyze));
        box.querySelectorAll('[data-social-title], [data-social-description]').forEach(input => input.addEventListener('input', analyze));
        socialImageInput?.addEventListener('change', analyze);
        box.querySelector('#og_image_file')?.addEventListener('media:selected', () => setTimeout(analyze, 0));
        (selectors.imageInputs || []).forEach(selector => find(selector)?.addEventListener('media:selected', () => setTimeout(analyze, 0)));
        box.querySelector('[data-social-image-clear]')?.addEventListener('click', function () {
            if (socialImageInput) socialImageInput.value = '';
            analyze();
        });
        document.addEventListener('rich-editor:change', function (event) {
            const content = editor();
            if (content && event.target === content.element) analyze();
        });

        // After the rich editor has mounted.
        setTimeout(analyze, 500);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-seo-box]').forEach(init);
    });
})();
