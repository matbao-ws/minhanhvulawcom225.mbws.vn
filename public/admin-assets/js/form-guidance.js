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
            .replace(/\([^)]*(?:bỏ trống|tự tạo)[^)]*\)/gi, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function placeholderFor(control, label) {
        const fieldName = (control.getAttribute('name') || '').toLowerCase();
        const type = (control.getAttribute('type') || 'text').toLowerCase();
        const labelText = cleanLabel(label);
        const normalizedLabel = labelText ? labelText.charAt(0).toLowerCase() + labelText.slice(1) : 'nội dung';

        if (fieldName.includes('slug')) return 'Ví dụ: duong-dan-than-thien';
        if (type === 'date' || type === 'datetime-local' || type === 'month' || type === 'time') {
            return `Chọn ${normalizedLabel}...`;
        }
        if (type === 'url') return labelText ? `Nhập ${normalizedLabel}...` : 'https://example.com';
        if (type === 'email') return labelText ? `Nhập ${normalizedLabel}...` : 'email@example.com';

        return `Nhập ${normalizedLabel}...`;
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
        if (!label || label.querySelector('.admin-slug-note') || /bỏ trống|tự tạo/i.test(label.textContent)) return;

        label.append(' ');
        const note = document.createElement('span');
        note.className = 'text-muted fw-normal admin-slug-note';
        note.textContent = '(bỏ trống để tự tạo)';
        label.appendChild(note);
    }

    function addEditorPlaceholder(editor) {
        if (editor.dataset.placeholder) return;

        const target = editor.dataset.target ? document.getElementById(editor.dataset.target) : null;
        const label = target ? fieldLabel(target) : null;
        editor.dataset.placeholder = placeholderFor(target || editor, label);
    }

    function syncQuillPlaceholder(quillRoot) {
        const editor = quillRoot.closest('.catalog-quill');
        if (!editor) return;

        addEditorPlaceholder(editor);
        if (!quillRoot.dataset.placeholder) quillRoot.dataset.placeholder = editor.dataset.placeholder;
    }

    function enhance(root) {
        const scope = root && root.querySelectorAll ? root : document;

        if (root && root.matches && root.matches(editableSelector)) {
            addPlaceholder(root);
            markRequired(root);
            addSlugNote(root);
        }

        scope.querySelectorAll(editableSelector).forEach(function (control) {
            addPlaceholder(control);
            markRequired(control);
            addSlugNote(control);
        });

        if (root && root.matches && root.matches('[required]')) markRequired(root);
        scope.querySelectorAll('[required]').forEach(markRequired);

        if (root && root.matches && root.matches('.catalog-quill')) addEditorPlaceholder(root);
        scope.querySelectorAll('.catalog-quill').forEach(addEditorPlaceholder);

        if (root && root.matches && root.matches('.ql-editor')) syncQuillPlaceholder(root);
        scope.querySelectorAll('.ql-editor').forEach(syncQuillPlaceholder);
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
