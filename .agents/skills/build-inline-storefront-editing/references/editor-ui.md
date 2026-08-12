# Components and the Editor Partial

## Two components, nothing else

```blade
<x-client::editable key="about.story.title" tag="h2" class="...">WHO WE ARE</x-client::editable>
<x-client::editable key="about.hero.subtitle" tag="p" :html="true" class="...">Founded in <strong>2016</strong>…</x-client::editable>
<x-client::editable-image key="about.story.image_left" src="{{ asset('…') }}" alt="…" class="…" />
```

Rules the components enforce:

- The **slot is the default**. Saved value present → render it; absent → render
  the slot. The theme keeps working on a fresh database with nothing seeded.
- `:html="true"` only where the original markup contains inline tags. A plain
  label gets `text` and is escaped on output.
- `$attributes->merge()` so every original class, id and `data-*` from the theme
  survives; only the edit hooks are added, and only for an authorized admin.
- `editable-image` takes an already-rendered `src` string, so a template can pass
  `{{ asset(...) }}` unchanged.

Register the component namespace once (`Blade::anonymousComponentPath`) so the
components live beside the rest of the storefront views rather than in the
framework default directory.

## The editor partial

Included from the client layout, wrapped in the authorization gate, **before**
the admin bar so the toolbar can ask whether the page has any regions.

### Hover affordance

A single floating hint element, positioned from the hovered element's rect:

- text → `✎ Sửa nội dung`
- image → `⬆ Đổi ảnh`
- image without the media permission → a lock hint, not a broken click

Outline the regions with a faint dashed border while edit mode is on, and a
stronger one on hover. Namespace the selectors and use high `z-index` and
`!important` — imported theme CSS will otherwise win and hide your affordances.

### Click handling: use the capture phase

This is the part that bites. Theme markup wraps editable content in elements
carrying their own handlers — `onclick="openCertModal(...)"`, lightboxes, tab
switchers, video modals. `preventDefault()` alone does not stop them, so the
theme's modal opens **on top of your media picker** and the feature looks broken.

```js
document.addEventListener('click', function (event) {
    if (!editing) return;
    const target = event.target.closest('[data-block-key]');
    if (!target) return;
    if (target.getAttribute('contenteditable') === 'true') return; // let the caret land

    event.preventDefault();
    event.stopPropagation();
    …
}, true);   // capture
```

Let a click inside an already-open text region through untouched, or the admin
cannot position the caret.

### Text editing

`contenteditable` on the region itself — no editor library. Stash the original
HTML on entry so a failed save can roll back and `Esc` can cancel. Save on
`focusout`, skip when nothing changed. `Enter` confirms a non-HTML region rather
than inserting a line break.

On success, replace the region with the **server's** returned value, not the
value typed: that is how the admin sees what sanitizing did.

### Images

Open the shared media picker, set `src` optimistically, save, and restore the
previous `src` if the save fails. Keep the picker open after an upload and reset
to the first page so the new file is visible.

### Media picker

Extract it as its own partial exposing one entry point:

```js
window.clientMediaPicker.open(function (url) { /* caller decides what to do */ });
```

Read and upload through the existing protected admin media routes. If the
project already has a picker embedded in another editor, prefer moving it here
over copying it — two pickers drift.

### Toolbar toggle

Expose `clientBlocksToggle(on)` and `clientBlocksCount()`. The admin bar shows
its button only when the page reports at least one region, so pages that are
fully database-driven do not offer a dead toggle.

Editing is **off by default**. An admin browsing their own site must not open a
modal by accidentally clicking a heading.

## Feedback

One small toast: `Đã lưu` on success, the server's message on failure, and a
longer timeout for errors. Silent failure on a page the admin believes they just
edited is the worst outcome here.
