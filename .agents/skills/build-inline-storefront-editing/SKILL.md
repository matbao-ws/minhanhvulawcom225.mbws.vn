---
name: build-inline-storefront-editing
description: Add front-of-site inline editing to a Laravel storefront — an authenticated admin hovers a heading, paragraph or image on the public page and edits it in place, while sections rendered from the database keep their single source of truth in the admin. Use when asked for "sửa nội dung trực tiếp trên client", front-end/on-page/in-place editing, hover-to-edit text, hover-to-upload images, editable static theme regions, or a key/value content-block table behind a cut HTML theme.
---

# Build Inline Storefront Editing

## What this builds

An admin opens a normal public page, turns on an edit mode from the admin bar, then:

- hovers text → dashed outline + `✎` hint → click → edit in place → blur saves;
- hovers an image → `⬆` hint → click → media library → pick or upload → saves;
- everything else on the page behaves exactly as the approved design does.

The storefront markup, its animations and its interactions are untouched. Only the
inner text or an image source becomes overridable, addressed by a stable key.

## Decide the storage before writing code

Three shapes come up. Pick deliberately and say why.

| Shape | Use when | Cost |
|---|---|---|
| Key/value content-block table (default) | Theme pages keep custom JS: sliders, tabs, lightboxes, counters | One migration, one service, one endpoint |
| Existing CMS `Page` records | The page really is a body of prose with no scripted interaction | Free, but flattening a scripted page into one HTML blob breaks it |
| A JSON settings row | A handful of strings, no history needed | No revisions, no audit, the row grows unbounded |

Refuse to flatten a scripted page into a CMS Page just to reuse an existing
editor. Read [data-model.md](references/data-model.md) before creating the table.

## Order of work

1. Confirm storage shape and the scope of pages for this pass. Doing every page
   at once makes the key sweep unreviewable; do the mechanism plus one page first.
2. Schema, model, revisions, service — [data-model.md](references/data-model.md).
3. One authorization helper, one write endpoint — [authorization.md](references/authorization.md).
4. Blade components and the editor partial — [editor-ui.md](references/editor-ui.md).
5. Mark regions in the templates — [marking-regions.md](references/marking-regions.md).
6. Tests and manual checks — [verification.md](references/verification.md).

## Non-negotiables

- **The Blade default stays the fallback.** A row exists only after an admin
  edits that region. A fresh install renders the approved theme with no seeding.
- **Database-driven sections get no edit hooks.** Products, posts, settings-backed
  blocks are edited in their own admin screen. Two editors for one field is the
  bug this skill exists to avoid.
- **No public write path.** Saving is session + CSRF + permission + throttle, on an
  admin route. Never expose it under a public API prefix.
- **Sanitize on the server, by type.** Text is stripped, HTML goes through the
  project's existing rich-text sanitizer, images become a portable media path.
- **Guests receive nothing.** No `data-*` hooks, no toolbar, no media picker
  markup, no editor script. Assert this in tests, not by inspection.
- **Never invent a second sanitizer, media picker or permission.** Reuse what the
  repository already has.

## Report

State the storage shape and why, the pages covered and their region counts, what
was deliberately left un-editable (and where it is edited instead), the tests
added, and any interaction that still needs a human to click through.
