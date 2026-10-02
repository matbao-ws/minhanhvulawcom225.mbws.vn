{{-- The inline storefront editor: admin bar, formatting toolbar, outline, section panel,
     responsive preview and history. Theme layouts include this one line before </body>:
     @include('core.editor')
     Core may add or change panels in an update; a theme never lists them.

     Order matters: the bar owns edit mode and publishes window.clientBlocks, which the
     formatting toolbar attaches to on DOMContentLoaded.

     Each part asks `ClientEditorChrome::editorActive()` itself, so they disappear together
     for a visitor and for the responsive preview frame — which loads this same URL with the
     editor suppressed, and would otherwise hold a second admin bar and a second owner of
     edit mode. Gating them here as well would be a second mechanism for one rule. --}}
{{-- Settings → Embed footer code: for every visitor, not only editors. --}}
@include('core.partials.embed-footer')
@include('core.partials.admin-bar')
@include('core.partials.inline-blocks')
@include('core.partials.inline-outline')
@include('core.partials.inline-section-panel')
@include('core.partials.inline-viewport')
@include('core.partials.inline-history-panel')
