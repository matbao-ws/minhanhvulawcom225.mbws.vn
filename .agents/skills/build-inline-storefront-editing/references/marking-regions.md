
# Marking Regions in the Templates

## Keys

`<page>.<section>.<slot>` — `about.story.title`, `home.certificates.image_1`.

- Lowercase, dot-separated, stable. The key is the primary key of the content.
- **Renaming a key orphans its saved content.** The region silently reverts to
  the Blade default. Choose names once; if you must rename later, migrate the row.
- Hand-name the keys of a page's important regions. Machine-generated ordinals
  (`applications.p_17`) are acceptable for long bodies of copy, but a heading an
  editor will hunt for deserves a readable name.

Two key shapes are generated rather than authored, and both stay inside the
same slug-shaped set:

```text
<list key>.item_<item id>.<slot>    about.leadership.item_m1.name
<region key>.extra                  about.hero.title.extra   (boxes added after it)
```

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

Skipping a database-driven block leaves it inert under edit mode, which reads as
"broken" rather than "not here". Wrap it in the marker component instead: it
outlines the region in a different colour and links to the one screen that owns
it. Place the wrapper around a **balanced** element — cut themes are full of
unclosed tags, and a wrapper one level out silently swallows whatever follows.

## Content the theme's own JavaScript owns

Marking a region the theme rewrites at runtime produces a region that cannot be
edited: the admin types, saves, and the script overwrites it on the next load.
Two shapes to look for before marking anything:

- `element.innerHTML = …` or `.textContent = …` inside a slider, tab or counter.
  It also strips the edit hooks, so the region stops being editable at all.
- A value duplicated into an attribute the script reads — `data-target="70"` next
  to a visible `70`. The attribute wins and the visible text is decoration.

Fix the direction of the dependency instead of skipping the region: the rendered
text becomes the source, and the script reads it. Then mark it.

The same applies to markup duplicated for visual reasons — a marquee that repeats
its logos to scroll seamlessly. Mark one pass, render the other as a plain copy,
or an editor changes one of the two and the strip shows both versions.

## Icons are not content

`HTMLPurifier` allows `<i>` but not `class` on it, so a Font Awesome icon inside
an HTML region loses its classes on the first save and disappears. Keep icons in
the template and out of the editable region. When an icon genuinely has to live
inside saved HTML, use `<span class="…">` — `span[class]` is on the allow-list.

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

**Escape the Blade delimiters.** A generator that builds markup with Python
f-strings or any other `{}`-brace templating collapses `{{--` to `{--`, and a
Blade comment that is not a comment is text on the page. The same applies to
`{{ $var }}`. Render one page and grep the HTML for `{--`, `@php`, `@foreach`
before believing the sweep worked — the diff looks fine.

## Turning a hard-coded group into a list

A run of near-identical cards — team members, certifications, process steps — is
a list whose length happens to be frozen in markup. Converting one:

1. Lift each card's content into a PHP array in the template, keyed by a short
   stable id (`m1`, `m2`, …). That array is now the theme default.
2. Loop it, wrapping each card in the list-item component.
3. Put the add control after the loop and pass it the same default ids.

Counting candidates by "how many elements share this long class string" badly
over-counts: it also matches the icon *inside* each card, the inputs of a form,
and tab buttons. Read what each group actually is before promising a number.

## After marking

- Diff the pages and skim for a wrapped container that should have stayed
  structural.
- Confirm the theme's own interactions still work with edit mode **off**.
- Confirm database-driven blocks in the same page carry no hooks; assert this in
  a test rather than trusting the sweep.
