# Components and the Editor Partial

## Four components, nothing else

```blade
<x-client::editable key="about.story.title" tag="h2" class="...">WHO WE ARE</x-client::editable>
<x-client::editable key="about.hero.subtitle" tag="p" :html="true" class="...">Founded in <strong>2016</strong>…</x-client::editable>
<x-client::editable-image key="about.story.image_left" src="{{ asset('…') }}" alt="…" class="…" />

{{-- one item of a repeatable section, and the control that adds another --}}
<x-client::list-item :list="$listKey" :item="$itemId" class="...">…</x-client::list-item>
<x-client::list-add :list="$listKey" :defaults="$defaultIds" label="Thêm thành viên" />

{{-- a region rendered from the database: outlined, but sent to its own screen --}}
<x-client::admin-region label="Đại diện vùng" permission="settings.view" :href="…">…</x-client::admin-region>
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
HTML on entry so `Esc` and Cancel can roll back. `focusout` marks the region
dirty; it does **not** save. `Enter` confirms a non-HTML region rather than
inserting a line break.

On success, replace the region with the **server's** returned value, not the
value typed: that is how the admin sees what sanitizing did.

### One save button, and nothing happens without it

Every change waits for Save — text, images, hiding a region, adding a box,
deleting one. Two consequences that are easy to get wrong:

- **Structure is written before content.** A box added in this session has no key
  until the server issues an id, so the save has to create the list items first,
  stamp the real keys onto the pending elements, apply pending deletions, and
  only then send content. If the structure step fails, stop — do not write half
  of it.
- **Cancel has to undo structure too.** Remove the nodes added this session and
  un-mark the ones flagged for deletion, not just restore the text.

Do not reload the page for an add or a remove. Clone the neighbouring element to
build the new one so it carries the markup the designer wrote, and let content
come from the server on the next ordinary load. The one honest exception is
*restore default*: once the override is gone, only the server knows what the
theme itself renders there.

### Hiding, restoring, deleting

Three different powers, and conflating them is the mistake:

| Action          | Applies to                            | Effect                                                    |
| --------------- | ------------------------------------- | --------------------------------------------------------- |
| Hide            | any region                            | saves an empty string; the element is not rendered at all |
| Restore default | any region                            | drops the override; the theme's own text returns          |
| Delete          | **only** a box the editor added | removes the list item and its content                     |

An authored region can never be deleted — it belongs to the template. Gate the
delete control on "this is an added box", meaning *either* it already has an item
id *or* it is still pending. Keying off the saved id alone leaves a freshly added
box undeletable until after the first save.

A hidden region renders nothing even in edit mode; an empty dashed placeholder in
every hidden slot is noise. That makes it unreachable, so the admin bar needs a
**hidden-regions toggle** that reveals them for restoring — shown only when the
page actually has some.

### Adding a box anywhere

One floating `+` for the whole page, positioned from the hovered region, plus a
floating `×` that appears only over a removable box. Do **not** render a control
per region: several hundred extra children reflow every flex and grid container
the moment edit mode comes on.

The added box is a sibling cloned from its anchor — same tag, same classes — so
this stays "one more of what is already here" and never becomes free-form block
insertion.

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

### The admin bar shows only what is actionable

- Save and Cancel appear once something is actually pending, not merely because
  edit mode is on. A Save whose only possible answer is "nothing to save" teaches
  the admin to distrust the bar.
- One control leaves edit mode: the toggle itself. A separate Close button beside
  it does the same thing twice.
- **`hidden` hides nothing** if the bar styles its buttons with
  `display: … !important` — an author `!important` rule outranks the user agent's
  `[hidden] { display: none }`. Add `#bar [hidden] { display: none !important }`,
  or every conditional button ships visible, including on pages where its script
  never wired a click handler.
- Turning edit mode off while changes are pending must say so. The edits stay in
  the DOM and Save comes back with edit mode; only a reload discards them.

## Feedback

One small toast: `Đã lưu` on success, the server's message on failure, and a
longer timeout for errors. Silent failure on a page the admin believes they just
edited is the worst outcome her

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
