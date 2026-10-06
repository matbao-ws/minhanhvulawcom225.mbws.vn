# Editable Markup Standard

The rules a theme cut must follow so the inline editor can recognise what it is looking at.

Read this *while* cutting `theme/` into Blade, not afterwards. Retrofitting is expensive: an
editable key is the primary key of the content an admin has already saved, and renaming one
silently drops their work.

Read `inline-admin-editing.md` first for the integration point and the write boundary. This file
covers only the markup contract.

## 1. The decision table

Every element in the source HTML falls into exactly one of these. Decide per element; do not mark a
whole block and hope.

| What you see in `theme/` | What you write | Why |
| --- | --- | --- |
| Static heading, paragraph, label, button text | `<x-client::editable key="…" tag="h2">` | Text the shop owns and no admin screen edits |
| Static rich text: a paragraph that may need bold or a link | `<x-client::editable key="…" tag="p" html>` | Stored as HTML and sanitised, not escaped |
| Static `<img>` that is part of the design | `<x-client::editable-image key="…" src="…" alt="…">` | Replaced from the Media library |
| A run of identical cards, members, logos, features | Keep the authored run fixed by default | Storefront text editing must not silently become content creation |
| An ordered run of whole sections | `<x-client::section-list key="…" :sections="[…]">` | An editor may reorder them, and change how each one looks |
| A section that should offer a variant, a background, an alignment or a spacing | declare it in `App\Support\SectionRegistry` | Semantic tokens only; the registry owns the token to class map |
| Product name, price, post title, category name, menu label, shop name, phone | **plain Blade output** | Comes from the database and has its own admin screen |
| Anything server-rendered from a model on a CMS page | plain Blade **plus** `contenteditable="false"` on the wrapper | Keeps it out of the page's editable root |
| Decorative markup: spacers, icons, overlays, `aria-hidden` | leave untouched | Nothing to edit |
| Static copy or artwork that must stay static (decorative background, legal line, breadcrumb) | `data-editable-ignore="<lý do>"` on the element | The reason is mandatory; a bare marker fails the audit and hides nothing |

The line that matters most is the one about database-driven values. **Two places to edit one field is a defect**, not a
convenience: whichever screen is saved last wins, and neither admin knows the other exists. If a
value already has an admin screen, it is not an editable region.

## Inventory before converting markup

Build a page inventory from the supplied theme before replacing tags. Count and classify:

- static headings, paragraphs, labels and meaningful button text;
- `<img>`, `<picture>` and responsive sources;
- CSS `background-image`, inline background styles and slider/carousel media;
- repeated cards or logos;
- structural sections and the editable regions inside each section;
- decorative icons, overlays and `aria-hidden` media that must remain untouched.

Compare the converted Blade against this inventory and resolve every missing item. Do not call a
theme cut complete merely because all remaining `<h*>`, `<p>` and `<img>` tags were converted:
backgrounds and script-declared slides can still be absent. Preserve the approved content and real
asset paths; do not fill gaps with editor fixtures or placeholder copy. Meaningful backgrounds and
slider images need the repository's editable image/background contract without changing their
visual box; decorative backgrounds remain untouched.

## 2. Key naming

```text
<page>.<area>.<slot>
```

Lowercase, dot separated. Segments may contain `a-z`, `0-9` and `-`. No other characters, ever —
`.` separates the namespace and a key holding one in the wrong place addresses another region's
storage.

```blade
{{-- good --}}
key="home.hero.title"
key="about.leadership.intro"
key="contact.form.submit-label"

{{-- bad --}}
key="title"                  {{-- collides across pages --}}
key="Home.Hero.Title"        {{-- uppercase --}}
key="home hero title"        {{-- spaces --}}
key="home.hero.title.v2"     {{-- versioning a key means losing the old content --}}
```

**A key is permanent.** Changing it does not move content; it abandons the old row and starts an
empty one, and the region silently reverts to its Blade default. If a section is renamed in the
design, keep the key and change only the visible text.

Pick `<area>` from the design's own vocabulary — `hero`, `values`, `leadership`, `faq` — not from
the markup's structure (`section-2`, `div-wrapper`). Structure changes; the section's identity does
not.

## 3. The slot is the default, and the default lives in Blade

```blade
<x-client::editable key="home.hero.title" tag="h1">
    Vật liệu xây dựng cho công trình bền vững
</x-client::editable>
```

The text between the tags is what a fresh install renders. Nothing is seeded — a row appears in
`site_blocks` only after an admin edits that region. Two consequences:

- Never seed editable content in a migration or seeder. A new project must render the approved
  design from Blade alone.
- The slot must be the real approved copy, not a placeholder. It is what the customer sees.

An empty string is a real, distinct value: it means "an admin hid this region on purpose", which is
not the same as "never edited". Do not use an empty slot to mean "to be filled later".

## 4. Choose `tag` to match the design, and never restyle through it

`tag` is the element the region renders as. Give it the tag the theme already used, so the theme's
own CSS keeps applying:

```blade
{{-- theme had: <h2 class="section-title">Dịch vụ</h2> --}}
<x-client::editable key="services.head.title" tag="h2" class="section-title">Dịch vụ</x-client::editable>
```

Classes pass through, so the region is styled by the theme exactly as before. An admin may later
override the heading level from the toolbar; that is stored separately and falls back to this `tag`
when cleared.

## 5. Repeated runs

Keep the approved number of repeated cards, members, logos or features fixed unless the project
explicitly requires a domain-specific repeatable-content workflow. Ordinary editable components
must default to `appendable=false`, and the text toolbar must not create siblings.

When such a workflow is explicitly approved, wrap each existing unit and keep the theme's wrapper
classes on it:

```blade
@foreach($lists->items('about.leadership.people', ['a1', 'a2', 'a3']) as $id)
    <x-client::list-item list="about.leadership.people" :item="$id" class="team-card">
        <x-client::editable-image :key="'about.leadership.people.item_'.$id.'.photo'" src="…" alt="" />
        <x-client::editable :key="'about.leadership.people.item_'.$id.'.name'" tag="h3">Nguyễn Văn A</x-client::editable>
    </x-client::list-item>
@endforeach

```

Rules:

- The wrapper renders for **visitors too**. Emitting it only for an editor gives the same page a
  different DOM after login, and a flex or grid parent changes shape the moment an admin signs in.
- Item keys are `<list>.item_<id>.<slot>`. Use the `itemKey()` helper rather than building the
  string by hand.
- Do not reuse the generic text toolbar for creation or permanent deletion. Provide a dedicated,
  explicitly approved list UI and validate its exact set/order server-side.

## 6. Sections and nesting

Each level of nesting is its own list with its own key:

```blade
{{-- page --}}
<x-client::section-list key="home.sections" :sections="['hero', 'values', 'contact']" />

{{-- resources/views/client/sections/values.blade.php --}}
<x-client::section-list key="home.sections.values.children" :sections="['speed', 'safety']" />
```

That scoping is the safety property, not a convenience: a list only ever accepts a permutation of
the ids it already owns, so a child can never be moved into a sibling parent. Reordering stays
reordering and never becomes free placement.

- Section names live in `resources/views/client/sections/<name>.blade.php`.
- A section removed from `:sections` stops rendering even if a stored row still names it. The
  template is the source of what exists; the database only stores order.
- A section added to `:sections` later appears at the end rather than vanishing.

### Outline structure for fixed theme sections

Some imported themes have fixed sections that must not be reorderable. Give those wrappers a
semantic, editor-only outline marker instead of inventing a stored section list. The outline must
derive hierarchy from DOM containment:

- a structural section wrapper is the parent row;
- editable text and images contained by that wrapper are child rows;
- nested structural wrappers form deeper levels;
- keys label storage identity but do not define ancestry.

Do not flatten all regions into top-level rows and do not render section parents without their
editable descendants. Keep editor-only outline attributes behind the same authorization gate as
the editable components, and never use them to alter visitor layout.

### Mark layout structure explicitly

The outline must also identify meaningful layout wrappers between a section and its fields. During
the Blade cut, annotate editor-only structure with stable semantic metadata:

```blade
<div @if($canEdit) data-outline-group="services-row" data-outline-kind="row"
     data-outline-group-label="Danh sách dịch vụ" @endif>
    <article @if($canEdit) data-outline-group="service-card-consulting" data-outline-kind="card"
             data-outline-group-label="Tư vấn" @endif>
        <div @if($canEdit) data-outline-group="service-card-consulting-content"
             data-outline-kind="content" data-outline-group-label="Nội dung card" @endif>
            {{-- editable fields --}}
        </div>
    </article>
</div>
```

Supported structural kinds are `row`, `card`, `content` and generic `group`. Derive the outline tree
from DOM containment so it reads `Section → Row → Card → Content → Text/Image`. These wrappers are
navigational only: they get no storage key, save endpoint, drag grip, add/delete action or visual
layout class.

For a sortable authored run, the Row owns the list key/default manifest and its Card elements are
direct siblings with stable item ids. For a database-backed run, the Row identifies the domain
collection and Cards carry database ids; persistence goes through that domain's reorder endpoint and
`position`/`sort_order`, not through the authored-theme list store.

Do not infer structure from utility class fragments such as `grid`, `flex`, `col-*` or `card` at
runtime. Those names vary by framework and often describe decorative wrappers. Identify semantic
structure while cutting the known theme and keep the marker behind the editor authorization gate.

## 7. Layout constraints the editor depends on

These are properties of the *theme*, and breaking them breaks the tools rather than the page.

- **No `transform`, `filter` or `perspective` on an ancestor of the editor chrome.** Each creates a
  containing block, and `position: fixed` inside one anchors to that element instead of the
  viewport. Keep `admin-bar`, `inline-blocks` and `inline-outline` as direct children of `<body>`;
  never move the includes inside the theme's page wrapper.
- **An editable region must be able to hold a box.** A region emptied by an admin has no content,
  so the reveal control gives it a minimum size. A parent with `overflow: hidden` and a fixed height
  will clip that, and the region becomes unreachable.
- **Do not put an editable region inside an element the theme's JS rebuilds.** Sliders that clone
  slides and lazy-loaders that rewrite `src` will duplicate or strip the editor's `data-*` hooks.
  Mark the static caption outside the slider instead.
- Regions are found by `[data-block-key]`, which the components emit only for an authorized admin.
  Never write those attributes by hand.

## 8. What never gets a hook

Marking any of these is a bug, no matter how convenient:

- Product, category, brand, post, review, menu and shop-settings values — each has an admin screen.
- Prices, stock, order data, anything a customer's money depends on.
- Anything inside a `@foreach` over a model collection.
- Text generated by a translation string. Edit the language file.

On a CMS page, server-rendered dynamic blocks must carry `contenteditable="false"` on their wrapper
so the browser keeps them out of the page's editable root.

## 9. Verification checklist

Run through this per page after cutting, before calling the cut done.

- [ ] Log out. The page renders exactly as the approved design, with no `data-block-*`, no toolbar
      markup and no third-party font request for editor chrome.
- [ ] Log in as an admin with `pages.update`. Every region intended as editable outlines on hover,
      and nothing database-driven does.
- [ ] Before pressing **Edit**, the authorized admin sees no region outline, hover border, edit
      cursor or hidden-region placeholder. After pressing it, those affordances appear; after
      leaving edit mode, they disappear again.
- [ ] Reconcile the rendered editable count with the source inventory, including `<picture>`, CSS
      backgrounds and every slider image. Verify every referenced local asset exists.
- [ ] Scroll to the bottom, then open a region. The toolbar appears against that region, not off
      screen.
- [ ] Bold something, save, reload. The markup survived.
- [ ] Change a heading level, save, reload. The level survived and the theme's own styling still
      applies.
- [ ] Hide a region, save. The layout does not collapse. Reveal it again from **Vùng đã ẩn** and
      restore it.
- [ ] Ordinary regions expose no add-content control. If—and only if—the project approved a
      repeatable-list feature, add one item through that dedicated UI and verify the grid/flex parent
      keeps its shape and the item persists for visitors.
- [ ] In the outline, every section is a parent with its intended text/image children. Fixed
      sections have no false reorder affordance, and drag refuses to cross between stored lists.
- [ ] Rebuild or refresh the outline once. Eligible Card lists still have working drag handles;
      single-item and non-persisted groups do not.
- [ ] Repeated visual areas expose meaningful `Row → Card → Content` levels; selecting each level
      scrolls to its real wrapper, while structural rows have no save/history/delete controls.
- [ ] Jumping to a child or opening its tools keeps the sidebar open until its explicit minimize or
      close button is pressed. Do not add redundant up/down arrow controls.
- [ ] The text toolbar contains no add-content plus or permanent-delete trash control unless a
      project explicitly approved repeatable content; ordinary editable components default to
      non-appendable.
- [ ] Type text, apply formatting, and switch among `p` and `h1`–`h6`; Undo and Redo traverse each
      change, then Save/reload preserves both content and the semantic wrapper.
- [ ] Production and theme-acceptance routes contain no editor fixture, demo-only block or
      superseded sandbox markup.
- [ ] `php artisan client:editable-audit` ends in `RESULT: PASS`. It classifies every visible text
      run and image source in `resources/views/client/**` (partials included) as editable, dynamic
      (Blade echo/output directive) or ignored-with-reason; `unknown`, `unsupported` and
      `Ignored without reason` must all be 0. `--fix` wraps only text-level tags and plain `<img>`;
      everything marked `[sửa tay]` or `[chưa hỗ trợ]` is resolved by hand — never by changing the
      layout to dodge the scanner. Read the "Vùng cố ý để tĩnh" list: every reason must be true.
- [ ] `php artisan test --compact` passes.
