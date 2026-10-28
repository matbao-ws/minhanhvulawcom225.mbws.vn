(function () {
    'use strict';

    const editableSelector = [
        'input:not([type])',
        'input[type="text"]',
        'input[type="search"]',
        'input[type="email"]',
        'input[type="password"]',
        'input[type="tel"]',
        'input[type="url"]',
        'input[type="number"]',
        'input[type="date"]',
        'input[type="datetime-local"]',
        'input[type="month"]',
        'input[type="time"]',
        'textarea'
    ].join(',');

    // Text in the admin language (admin.form_guidance, set by the admin layout); Vietnamese if absent.
    const text = Object.assign({
        content: 'nội dung',
        enter: 'Nhập :label...',
        choose: 'Chọn :label...',
        slug_example: 'Ví dụ: duong-dan-than-thien',
        slug_note: '(bỏ trống để tự tạo)'
    }, window.AdminFormGuidanceText || {});

    // A label that already says the field fills itself, in Vietnamese or English.
    const autoFillWords = 'bỏ trống|tự tạo|leave (?:it )?(?:empty|blank)|generate';

    function withLabel(template, label) {
        return String(template).replace(':label', function () { return label; });
    }

    function fieldLabel(control) {
        if (control.labels && control.labels.length) {
            return control.labels[0];
        }

        let container = control.parentElement;
        for (let depth = 0; container && depth < 5; depth += 1, container = container.parentElement) {
            const label = container.querySelector(':scope > label.form-label, :scope > label');
            if (label) return label;
        }

        return null;
    }

    function cleanLabel(label) {
        if (!label) return '';

        return label.textContent
            .replace(/\*/g, '')
            .replace(new RegExp('\\([^)]*(?:' + autoFillWords + ')[^)]*\\)', 'gi'), '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function placeholderFor(control, label) {
        const fieldName = (control.getAttribute('name') || '').toLowerCase();
        const type = (control.getAttribute('type') || 'text').toLowerCase();
        const labelText = cleanLabel(label);
        const normalizedLabel = labelText ? labelText.charAt(0).toLowerCase() + labelText.slice(1) : text.content;

        if (fieldName.includes('slug')) return text.slug_example;
        if (type === 'date' || type === 'datetime-local' || type === 'month' || type === 'time') {
            return withLabel(text.choose, normalizedLabel);
        }
        if (type === 'url') return labelText ? withLabel(text.enter, normalizedLabel) : 'https://example.com';
        if (type === 'email') return labelText ? withLabel(text.enter, normalizedLabel) : 'email@example.com';

        return withLabel(text.enter, normalizedLabel);
    }

    function addPlaceholder(control) {
        if (control.hasAttribute('placeholder') || control.readOnly || control.disabled) return;

        control.setAttribute('placeholder', placeholderFor(control, fieldLabel(control)));
    }

    function markRequired(control) {
        if (!control.required || control.type === 'hidden') return;

        const label = fieldLabel(control);
        if (!label || label.querySelector('.text-danger') || label.textContent.includes('*')) return;

        label.append(' ');
        const marker = document.createElement('span');
        marker.className = 'text-danger';
        marker.textContent = '*';
        label.appendChild(marker);
    }

    function addSlugNote(control) {
        const fieldName = (control.getAttribute('name') || '').toLowerCase();
        if (!fieldName.includes('slug')) return;

        const label = fieldLabel(control);
        if (!label || label.querySelector('.admin-slug-note') || new RegExp(autoFillWords, 'i').test(label.textContent)) return;

        label.append(' ');
        const note = document.createElement('span');
        note.className = 'text-muted fw-normal admin-slug-note';
        note.textContent = text.slug_note;
        label.appendChild(note);
    }

    function addEditorPlaceholder(editor) {
        if (editor.dataset.placeholder) return;

        const target = editor.dataset.target ? document.getElementById(editor.dataset.target) : null;
        const label = target ? fieldLabel(target) : null;
        editor.dataset.placeholder = placeholderFor(target || editor, label);
    }

    // The rich text editor (Jodit) draws its own toolbar, popups and dialogs; leave them alone.
    function insideEditorUi(element) {
        return !!(element.closest && element.closest('.jodit'));
    }

    function enhance(root) {
        if (root && insideEditorUi(root)) return;
        const scope = root && root.querySelectorAll ? root : document;

        if (root && root.matches && root.matches(editableSelector)) {
            addPlaceholder(root);
            markRequired(root);
            addSlugNote(root);
        }

        scope.querySelectorAll(editableSelector).forEach(function (control) {
            if (insideEditorUi(control)) return;
            addPlaceholder(control);
            markRequired(control);
            addSlugNote(control);
        });

        if (root && root.matches && root.matches('[required]')) markRequired(root);
        scope.querySelectorAll('[required]').forEach(function (control) {
            if (!insideEditorUi(control)) markRequired(control);
        });

        // Read by the rich text editor (rich-editor.js) when it mounts.
        if (root && root.matches && root.matches('.rich-editor')) addEditorPlaceholder(root);
        scope.querySelectorAll('.rich-editor').forEach(addEditorPlaceholder);
    }

    enhance(document);

    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === Node.ELEMENT_NODE) enhance(node);
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
