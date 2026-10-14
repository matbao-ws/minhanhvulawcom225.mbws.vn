{{-- Rich text editor (Jodit) for admin forms: include once per form, then mark each editor
     with class "rich-editor" (see public/admin-assets/js/forms/rich-editor.js). --}}
@once
    @push('styles')
        <link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('admin-assets/libs/jodit/jodit.min.css') }}">
        <style>
            /* Fullscreen sits above the admin header but below the media library (11040+),
               which opens from the editor's image button. */
            :root { --jd-z-index-full-size: 10500; }
            /* The sticky toolbar follows the page scroll; fullscreen has no page to follow. */
            .jodit_fullsize .jodit-toolbar__box { position: relative !important; top: 0 !important; left: auto !important; width: auto !important; }
            .jodit_fullsize .jodit_sticky-dummy_toolbar { display: none !important; }
            /* Square corners: the toolbar stays pinned under the header while the text scrolls past. */
            .jodit-container:not(.jodit_inline) { border-radius: 0; border-color: #dfe5ef; }
            .jodit-container:not(.jodit_inline) .jodit-toolbar__box { border-radius: 0; background: #f8f9fa; }
            .jodit-container .jodit-status-bar { border-radius: 0; }
            /* The layout's .card { overflow: hidden !important } (clipping to rounded corners) would stop
               the toolbar from sticking; an editor's card has padding, so nothing spills out. */
            .card:has(.rich-editor) { overflow: visible !important; }
            .jodit-wysiwyg { color: #111827; font-size: 15.5px; line-height: 1.65; }
            .jodit-wysiwyg img { max-width: 100%; height: auto; }
            .jodit-wysiwyg figure { margin: 1rem 0; }
            .jodit-wysiwyg figcaption { color: #5a6a85; font-size: 13.5px; text-align: center; margin-top: 6px; }
            .jodit-wysiwyg blockquote { border-left: 4px solid #dfe5ef; margin: 1rem 0; padding: .25rem 0 .25rem 1rem; color: #4b5563; }
            /* Content saved by the former editor (Quill) keeps its look while being edited. */
            .jodit-wysiwyg .ql-align-center { text-align: center; }
            .jodit-wysiwyg .ql-align-right { text-align: right; }
            .jodit-wysiwyg .ql-align-justify { text-align: justify; }
            .jodit-wysiwyg .ql-image-caption { color: #5a6a85; font-size: 13.5px; text-align: center; }
            .jodit-wysiwyg .ql-indent-1 { padding-left: 3em; }
            .jodit-wysiwyg .ql-indent-2 { padding-left: 6em; }
            .jodit-wysiwyg .ql-indent-3 { padding-left: 9em; }
        </style>
    @endpush

    @push('scripts')
        <script src="{{ \App\Support\AssetVersion::url('admin-assets/libs/jodit/jodit.min.js') }}"></script>
        <script src="{{ \App\Support\AssetVersion::url('admin-assets/js/forms/rich-editor.js') }}"></script>
    @endpush
@endonce
