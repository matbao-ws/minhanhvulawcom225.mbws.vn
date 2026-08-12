# Marking Regions in the Templates

## Keys

`<page>.<section>.<slot>` — `about.story.title`, `home.certificates.image_1`.

- Lowercase, dot-separated, stable. The key is the primary key of the content.
- **Renaming a key orphans its saved content.** The region silently reverts to
  the Blade default. Choose names once; if you must rename later, migrate the row.
- Hand-name the keys of a page's important regions. Machine-generated ordinals
  (`applications.p_17`) are acceptable for long bodies of copy, but a heading an
  editor will hunt for deserves a readable name.

## What must never be marked

Anything rendered from the database. On a cut theme this is the whole point of
the feature, and it is easy to get wrong at scale. Skip:

- everything inside `@foreach` / `@forelse` / `@for` / `@while` — product cards,
  post cards, carousel slides, tab panels built from a collection;
- any element whose content contains a Blade echo (`{{ … }}`, `{!! … !!}`) — a
  half-static, half-dynamic paragraph has no single owner;
- `<img src="{{ $model->… }}">` — that image belongs to its record;
- values that come from settings (contact details, map branches, representatives).

If an editor can change the same words in two places, the feature is wrong.

## Doing it at scale

Hand-editing hundreds of regions across a cut theme is where mistakes enter. Use
a one-off script, run it per page, and review the diff — but only with guards:

1. Work inside the page's content section only.
2. Walk the Blade directives and record loop depth; only transform text that
   sits at depth 0.
3. Reject any candidate whose inner text or attributes contain a Blade echo or
   directive.
4. Wrap outermost-first and never rescan a replacement, so components cannot nest.
5. Choose `:html="true"` automatically when the inner text contains a tag.

Tag selection, in the order that proved out:

- `h1`–`h6`, `p`, `span`, `li` — always.
- `div` — **only** when it contains nothing but text. A structural `div` wrapped
  in an editable region turns a layout container into a text field.
- `img` — unless the `src` is a model attribute.

Run, render every affected page as a guest and as an admin, and compare region
counts before and after. A page whose count jumps unexpectedly usually means a
loop guard missed.

Keep the script out of the application — it is a migration aid, not a runtime
dependency. The templates it produces are the deliverable.

## After marking

- Diff the pages and skim for a wrapped container that should have stayed
  structural.
- Confirm the theme's own interactions still work with edit mode **off**.
- Confirm database-driven blocks in the same page carry no hooks; assert this in
  a test rather than trusting the sweep.
