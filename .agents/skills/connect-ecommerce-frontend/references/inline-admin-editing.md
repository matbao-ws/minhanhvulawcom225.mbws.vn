# Storefront Inline Admin Editing Contract

There is no drag-and-drop page builder in this core. GrapesJS, `PageBuilderService` and the
`builder_data` / `published_css` / `schema_version` columns were removed. Do not reintroduce them,
and do not add a replacement editor library unless the user explicitly asks.

What exists is inline editing built on the browser's native `contenteditable`, driven by
`resources/views/client/partials/admin-bar.blade.php`. Text editing uses no library at all.

Two libraries *are* permitted, because the user asked for them, and only for the editor's own panels:
SortableJS for dragging in the outline panel and Alpine for the section settings and history panels.
Both are vendored as static files under `public/client-assets/vendor/` and loaded from inside the
bar's authorization gate, so a visitor downloads neither. See section 9 of `AI_BUILD_PROMPT.md` for
the limits, and `build-inline-storefront-editing/references/editor-ui.md` for how a panel plugs into
the bar's Save without becoming a second owner of edit mode.

## Preserve the integration point

The editor is core (`resources/views/core/`, updated with core — see AI_BUILD_PROMPT.md, "Core và theme").
Every storefront layout includes it with **one line**, once, near the end of `<body>` and as a direct
child of `<body>`; the head additions (analytics…) with one line in `<head>`:

```blade
@include('core.head')      {{-- in <head> --}}
@include('core.editor')    {{-- before </body>: admin bar, toolbar, outline, section panel, viewport, history --}}
```

Never list the editor partials one by one in a theme: core adds or changes panels in an update and a
theme that lists them would miss them. Inside `core.editor` the order is fixed (the admin bar owns edit
mode and publishes window.clientBlocks; the toolbar attaches to it).

Each part gates itself on `App\Support\ClientEditorChrome::editorActive()`, so they appear and
disappear together and nothing extra is needed around the includes. Direct children of `<body>`
matters: a `transform`, `filter` or `perspective` on an ancestor creates a containing block, and
`position: fixed` inside one anchors to that element instead of the viewport.

Reuse the partial. Do not copy its toolbar, authorization checks, or save logic into theme pages.
When replacing `resources/views/client/layouts/app.blade.php`, merge the theme shell around this
include instead of overwriting it.

## Render CMS Pages through the editable contract

For content owned by `App\Models\Page`, pass the existing page context to the Blade view:

```text
$page
$title
$html
$metaTitle
$metaDescription
```

Render the HTML inside `id="client-page-{page id}"` — the editor toggles `contenteditable` on that
element and reads its `innerHTML`. A Page stores HTML only (`published_html`); there is no per-page
CSS, so do not add a page-scoped style node or reintroduce a CSS field.

Preserve localized slug resolution, publication checks and the `cms_page` feature check in the
client controller.

Do not turn static theme About/Policy/Landing content into permanently hard-coded Blade when it is
intended to be editable. Seed or migrate it into `Page` and render it through the Page contract.
Keep dynamic catalog, checkout, account and order screens in their own domain views.

Server-render any DB-driven block inside a CMS page with `contenteditable="false"` on its wrapper so
it stays outside the editable region.

## Keep the write boundary private

Inline save must continue through the localized admin route protected by:

```text
web session -> auth -> active admin -> feature:cms_page -> can:pages.update
```

Send the CSRF token, the current content locale and the edited HTML — nothing else. Persist through
`InlinePageUpdateRequest` and `PageContentService::updateLocale()`, keeping the
`admin-page-inline` throttle, so every save:

- validates locale and payload size;
- sanitizes HTML through `PageHtmlSanitizer`;
- snapshots a revision;
- preserves other locales and Page metadata;
- writes an admin activity log.

Never expose inline save under `/api/public`, trust a client-supplied user/permission, or write the
Page model directly from a storefront controller.

## Load editor assets conditionally

Only render the toolbar, editor script and save URL when the session user is active, has an admin
role (`role_id` is not null) and can `pages.update`. Guest/customer HTML must contain none of them.

Namespace toolbar/editor CSS so imported theme rules cannot hide or resize it, and keep its z-index.

### Separate authorization from active edit mode

An authorized admin viewing the storefront is not automatically editing it. Treat these as two
different states:

1. **Editor authorized, edit mode off:** editor markup may be present, but the storefront must look
   and behave like the visitor view. Do not show region outlines, hover borders, edit cursors,
   empty-region boxes, list controls or image affordances.
2. **Edit mode on:** the bar adds `client-edit-active` to `<body>`; only then may those affordances
   appear and editable elements receive `contenteditable`.

Scope every region-affordance selector under `body.client-edit-active`. This includes normal,
hover, image, cleared/hidden, list-item and pending-removal states. A bare rule such as
`[data-block-key] { outline: ... }` is a defect because authorized admins see editing chrome before
pressing the edit button. Keep panel and toolbar styling outside that scope only when the element is
itself hidden until opened.

The outline sidebar is a working surface, not a transient menu. Jumping to a region, opening
history or opening section settings must not close it. Provide one explicit minimize/close control;
do not use per-row up/down arrow buttons when drag ordering is available.

Drag handles belong only to authored sortable lists. A Card may reorder only among Card siblings
inside the same Row/list key; it must never move above a heading, into Content, or across another
Row. Enforce this in three places: separate Sortable containers by DOM depth, compare list keys in
`onMove`, and have the server accept only a complete permutation of the template-declared defaults.
Persist the order and render it for visitors too—DOM-only dragging that resets on reload is a bug.
Put list ownership and the one encoded defaults manifest on the dedicated Row/container; each direct
Card sibling carries only its stable item id (and the owning key when the outline needs it). Do not
repeat the defaults JSON on every Card. The DOM must make the sortable boundary real before the
outline adds a drag handle.

Database-rendered Cards use a different owner. Render stable database ids, order the query by the
domain model's `position`/`sort_order`, and have inline dragging call that module's existing protected
reorder endpoint (or add one beside its admin reorder action). Validate the submitted ids against the
authorized query and update positions transactionally. Do not copy database Cards into
`SiteListService`, and do not let inline editing become a second source of ordering truth.
Render a handle only when the list has a persistence endpoint and at least two eligible siblings.
Initialize Sortable after the outline tree exists, destroy stale instances before rebuilding it, and
bind again after any outline refresh. A silently missing handle after a tree rerender is a defect;
do not compensate by restoring per-row up/down arrows.

### Build responsive preview as a real viewport

Render responsive preview inside a fresh iframe whose width is the tested viewport. Never simulate
breakpoints by applying CSS `zoom`, `scale()` or a transform to the live storefront: media queries
would still evaluate against the outer desktop viewport. Suppress editor chrome inside the iframe
so edit mode has only one owner, create the frame when preview opens, and destroy it on close to
avoid stale content and duplicate theme timers.

Use a builder-style preview workspace:

- fill the browser viewport with a dark canvas;
- keep the preview stage flush to the full available height;
- reserve a compact vertical device rail on the right;
- make Desktop consume 100% of the canvas width up to that rail;
- center fixed Tablet and Mobile stages (use project breakpoints, with `768px` and `390px` as sane
  defaults);
- expose icons with tooltips, `aria-pressed`, a visible active state and an explicit back/close
  control;
- lock background-page scrolling while preview is open and restore it on close;
- keep the layout usable on a narrow host viewport without changing the iframe breakpoint contract.

Do not use a centered modal card with device labels above it: responsive preview is a workspace,
not a confirmation dialog. Avoid arbitrary Desktop caps such as `1280px` when usable canvas space
is wider; they create misleading empty gutters and do not represent the current desktop surface.

### Keep the text toolbar content-only

The default editable text/image component is not appendable, and the toolbar must not render a
floating green plus button. The one creation the core toolbar offers is "Nhân bản" in its "…" menu:
a card marked with `<x-client::list-item>` is copied whole, beside its source; a lone text region
gets a sibling copy carrying its own tag and classes (`{key}.extra`). Nothing else is copied, so a
theme gets card duplication only by wrapping its repeated cards in `list-item`.

An authored region may be hidden and restored; it is part of the theme contract and is never
deleted. "Xoá" in the "…" menu exists only for a `list-item` card or a copy an editor made, and
removes that item from its list (`site_lists`) with its content.

Undo/redo must cover both typing and toolbar mutations. Do not rely solely on deprecated browser
`execCommand('undo'/'redo')`: keep editor-owned, per-region before-change snapshots, clear the redo
branch after a new edit, and transfer pending state when restoring a snapshot replaces the wrapper.
Heading changes must replace the semantic wrapper (`p`, `h1` through `h6`), preserve the stable
block key, authored base tag, classes and content, then hand the new node to the bar's pending-save
map. Give saved heading/paragraph overrides public, namespaced typography classes strong enough to
beat imported theme utility sizes; otherwise the DOM changes while the admin sees no visual change.
Never implement a heading choice as visual font sizing alone.

After replacing the wrapper, resolve future toolbar operations by stable block key or update every
live reference (active region, pending map, snapshot owner and toolbar anchor) to the replacement.
Use delegated listeners or explicitly rebind node-local listeners. Continuing to mutate the detached
old node is the common cause of a heading selector that works once and then appears broken.

Capture the original wrapper tag, class list and format when a region opens. Cancel must restore all
three as well as the HTML—not merely replace `innerHTML`—and Save must advance that baseline to the
new wrapper. Verify this specifically because a detached/replaced heading node can otherwise leave
Cancel targeting the wrong element.

Coalesce content and section history so frequent saves create at most one ordinary checkpoint per
10-minute window. Force a checkpoint before restore, show only a small recent page in the UI, and
prune storage to a bounded retention (50 revisions per block/section by default). No-op saves must
create neither a write nor a revision.

For images, preserve the inline Media picker:

- Require `media.view` separately from `pages.update`.
- Read paginated images from the protected admin media resources route.
- Replace only the selected image element.
- Upload through the existing protected media upload route with CSRF.
- Keep the picker open after upload, refresh its first page and let the admin choose the image.
- Do not expose media endpoints, URLs or picker code to admins without `media.view`.

Image sources written into Page HTML must stay portable: our own media is stored as a relative path
and resolved on read by `App\Support\MediaUrl`, which `PageHtmlSanitizer` applies on save. Do not
write absolute URLs pointing at the site's own host into page content.

## Map non-Page resources honestly

Product, category, post and other domain records are not CMS Pages. Link their admin-bar action to
the correct protected editor unless a dedicated inline endpoint with equivalent validation,
authorization and audit behavior already exists. Never save those resources through the Page
endpoint merely to make the UI appear editable.

## Verify after every theme cut

- Guest and customer do not see `client-admin-bar` or editor assets.
- Admin without `pages.update` cannot see or call inline editing.
- Authorized admin with edit mode **off** sees no region outline, hover border, edit cursor, hidden
  placeholder or list control.
- Turning edit mode on adds those affordances; turning it off removes them again without reload.
- Authorized admin can toggle edit mode, save and see sanitized content without reloading.
- Saving creates a revision and preserves translations in other locales.
- Dangerous HTML is rejected or removed.
- Theme CSS does not cover the toolbar.
- Authorized media admins can replace an image and upload without closing the picker.
- Image URLs in saved page content are relative, not pinned to the current host.
- Jumping to content or opening a row tool keeps the outline open; only its explicit minimize/close
  control hides it.
- The ordinary text toolbar has no floating add-content plus. Its "…" menu offers Nhân bản for a
  lone region or a `list-item` card, and Xoá only for cards and copies; an authored region is
  hidden/restored, never deleted.
- The full toolkit smoke test is `theme-cut-acceptance.md` §5; run it after every cut.
- Typing, formatting and changing `p`/`h1`–`h6` can be undone and redone before save; saving and
  reloading preserve the chosen semantic tag.
- Heading levels are visibly distinct even under a utility-class theme, and Cancel restores the
  exact pre-edit tag, classes, format and content.
- Responsive preview uses a fresh iframe, a full-height dark canvas and a right-side device rail;
  Desktop fills available width while Tablet/Mobile keep their explicit breakpoint widths.
- Card dragging persists and is confined to Card siblings sharing the same Row/list key; headings
  and nested content never become drop targets. Rebuilding the outline preserves/rebinds dragging.
- Revision storage is coalesced and bounded rather than growing once per keystroke or forever.
