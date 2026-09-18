# Components and the Editor Partial

## Four components, nothing else

```blade
<x-client::editable key="about.story.title" tag="h2" class="...">WHO WE ARE</x-client::editable>
<x-client::editable key="about.hero.subtitle" tag="p" :html="true" class="...">Founded in <strong>2016</strong>…</x-client::editable>
<x-client::editable-image key="about.story.image_left" src="{{ asset('…') }}" alt="…" class="…" />

{{-- Optional: only for a separately approved repeatable-list workflow. --}}
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

Keep one editor-owned snapshot history per stable block key. Toolbar Undo/Redo and
`Ctrl/Cmd+Z`, `Ctrl/Cmd+Shift+Z` or `Ctrl/Cmd+Y` must use that same history and
prevent the browser's native undo from running as a second mutation. Disable the
buttons when their real stacks are empty. Add a snapshot only after a toolbar
command actually changes the DOM; a cancelled link prompt or no-op command must
not clear Redo or make Save appear.

Formatting commands depend on the current selection. A toolbar `mousedown` may
preserve it, but a link prompt steals focus: collect and validate the URL first,
then restore the saved Range immediately before creating the link. If no selection
belongs to the active region, explain that the admin must select text instead of
silently creating a broken link.

Changing `p`/`h1`–`h6` replaces the semantic wrapper. Transfer the active-region
reference, pending-save entry, snapshot owner and toolbar anchor to the new node;
future operations must never target the detached wrapper.

Bullet and numbered lists need the same semantic treatment. Do not run
`insertUnorderedList` on a `contenteditable` heading/paragraph wrapper: browsers may
reject it or create invalid `<p><ul>…</ul></p>` markup. Replace the region wrapper
with `ul`/`ol`, put its content in `li`, persist that wrapper as an allowed format,
and sanitize the inner `li` markup inside a temporary list parent so the sanitizer
does not discard contextless list items. Toggling the active list type restores the
authored base wrapper, and the operation participates in the same Undo/Redo stack.

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

### Adding a box anywhere (optional)

Do not enable content creation for ordinary editable text or images. If the project
explicitly approves a repeatable-list workflow, use one floating `+` for that list,
positioned from the hovered region, plus a
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
edited is the worst outcome here.

## Surfaces beyond the bar

More than one panel eventually wants to queue work. The rule that keeps that from
breaking edit mode: **the bar stays the only owner.**

A second panel registers with it rather than writing on its own:

```js
clientBlocks.registerSaver({
    pending: () => boolean,   // is anything queued here
    save:    () => Promise,   // write it — runs after structure and after content
    revert:  () => void,      // put the DOM back, for Huỷ
});
clientBlocks.refreshPending(); // call when pending() would change its answer
```

What a contributor must **not** do is call `markDirty()` to make Save appear. That
flag also drives `savePageBody()`, so on a CMS page it would rewrite the entire
`published_html` because somebody changed one section's background.

Save order is structure, then content, then contributors. Nothing a contributor
writes depends on an id the server has yet to issue, so last is correct.

## Panels

- **Direct children of `<body>`**, like the bar itself. A `transform`, `filter` or
  `perspective` on any ancestor creates a containing block, and `position: fixed`
  inside one anchors to that element instead of the viewport.
- **Never put a directive or a hook on `[data-block-key]`.** A panel reads and
  writes its own chrome; the regions belong to the bar.
- **Visibility rides on a class, not on a framework's show/hide.** Alpine's
  `x-show` hides by writing `display: none` inline, and an inline declaration
  loses to an author `!important` rule — which the editor's own `display` has to
  be, or theme CSS wins and the panel ships unstyled.
- **One control on the outline row, not one per section on the page.** Several
  hundred extra positioned children reflow every flex and grid container the
  moment edit mode comes on.

### If the panel uses Alpine

Alpine is permitted for editor panels only, vendored as a static file and loaded
from inside the bar's authorization gate. One ordering trap, and it bites every
time: Alpine's CDN build starts itself in a microtask as soon as its script runs,
and `defer` places that **before** `DOMContentLoaded` — which is where the bar
publishes `window.clientBlocks`. So `alpine:init` and every component's `init()`
run first. Register the component immediately (`Alpine.data` must be called before
start) and attach the bar-dependent parts when the bar appears.

## History and rollback

The revisions the service already writes need a reader, and it is one panel for
both grains — the outline lists regions and sections side by side, so splitting it
would duplicate the list, the empty state and the restore flow.

Rolling back calls the server immediately rather than queueing behind Lưu, and
that is acceptable for exactly one reason: the server forces a checkpoint of the
value it replaces, so the rollback is undoable from the very list it refreshes.
Say so in the panel, and say what a control does before it does it.

## Responsive preview

An iframe of the same URL, never a `transform` or `zoom` on the page. Media
queries resolve against the viewport, so a scaled page still reports the desktop
width and every breakpoint stays where it was.

The frame loads with the editor suppressed, or the preview contains a second admin
bar and a second owner of edit mode. Suppression belongs in the same gate every
component and partial already asks, not in the layout alone: a class emitted only
for an editor would make the preview differ from what a visitor sees, which is the
one thing a preview must not do. The flag may only ever **remove** the editor —
the permission stays the left-hand side of the check, so no value of it can put
the editor in front of somebody who cannot edit.
