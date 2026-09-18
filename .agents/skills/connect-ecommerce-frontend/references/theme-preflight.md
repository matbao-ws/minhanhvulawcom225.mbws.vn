# Source Theme Preflight

## Purpose and gate

Run this before cutting any supplied `theme/` into Blade. The source theme is allowed to change
during this phase: repair its technical structure once, verify that its approved appearance and
interactions remain intact, then treat the normalized result as the only cut source.

Do not start Blade extraction while a critical finding remains. Do not use preflight as permission
to redesign, rewrite working plugins, rename styling hooks or replace the theme's visual system.

```text
theme inventory → baseline render → structural audit → source repair
                → desktop/tablet/mobile comparison → Blade cut
```

## Inventory the whole theme

Inspect every HTML entry page, not only the home page. Record:

- page file, intended route and shared shell;
- stylesheets, scripts, fonts, images, CSS backgrounds, `picture` sources and slider media;
- breakpoints and desktop/tablet/mobile states;
- interactive plugins and the classes, ids, DOM ancestry and load order they require;
- static text/media candidates versus product, post, category, setting and other database-owned data;
- repeated visual units and their real Section/Row/Card/Content containment.

Resolve every local reference against the filesystem. Missing assets, duplicate conflicting vendor
copies, source-host artifacts and links to nonexistent `.html` pages are findings, not future Blade
problems.

## Capture the baseline

Render the source theme before editing it. Check representative desktop, tablet and mobile widths
and exercise navigation, drawers, menus, tabs, sliders, accordions, modals and forms. Keep enough
screenshots or concrete observations to compare after normalization.

If the source cannot render, document that limitation and compare the smallest reliable surfaces.
Do not claim pixel equivalence to a baseline that was never observable.

## Required HTML shape

Prefer this semantic containment when it matches the approved design:

```html
<section>
  <header>
    <h2>Section heading</h2>
    <p>Section introduction</p>
  </header>

  <div class="…row…">
    <article class="…card…">
      <div class="…content…">
        <h3>Card heading</h3>
        <p>Card copy</p>
        <img src="…" alt="…">
      </div>
    </article>
  </div>
</section>
```

Names and classes remain theme-specific. The required property is unambiguous containment:

- one page section owns its heading, introduction and rows;
- one Row owns a dedicated run of Card siblings;
- headings or unrelated Content nodes are outside the sortable Card container;
- one Card owns its Content and editable leaves;
- each editable text candidate has one stable wrapper (`h1`–`h6`, `p`, `span`, `a`, `button`,
  `li` as appropriate), rather than a wrapper shared with unrelated layout;
- lists use valid `ul/ol > li`; block elements are not nested inside `p`; interactive elements are
  not nested inside one another;
- meaningful images have usable `src`, `alt`, dimensions/aspect behavior and complete responsive
  sources where the design uses them.

Do not force every decorative wrapper into `section`, `article` or `div`. Semantics must describe the
content, and existing CSS/JS hooks must survive.

## Repair blocking defects in `theme/`

Fix in the source theme before cutting:

- invalid or browser-reparented HTML, missing closing tags and duplicate ids;
- repeated Card markup whose shared parent also contains headings or unrelated nodes;
- click targets made from invalid nested links/buttons;
- broken relative assets, case mismatches and missing font/image files;
- scripts loaded twice, initialized twice or executed before their dependency/DOM exists;
- inline handlers that cannot survive component reuse, when they can be moved without changing
  behavior;
- rigid widths/heights or overflow that make an approved breakpoint unusable;
- `transform`, `filter` or `perspective` on `body` or a page-wide ancestor when it would capture
  fixed editor chrome;
- placeholder/editor-fixture content that is not part of the approved storefront.

Make the smallest repair that establishes a stable contract. Preserve classes and data hooks where
plugins or CSS depend on them. When a repair would materially change appearance or interaction,
stop and report the tradeoff instead of silently choosing a redesign.

## Prepare for inline editing without leaking editor markup

The static source expresses clean content boundaries; it does not ship admin editor attributes.
During the later Blade cut, map the audited hierarchy to authorized-only markers:

```text
section wrapper → data-outline-section
semantic wrapper → data-outline-group + data-outline-kind=row|card|content
editable leaf → x-client::editable or x-client::editable-image
sortable card → authored data-list-key/item/defaults contract
```

Never copy unconditional `data-block-*` or editor chrome into visitor HTML. Do not infer Row/Card at
runtime from utility class fragments: decide the hierarchy while the known source theme is open.

## Exit checklist

- [ ] Every source page and referenced asset is accounted for.
- [ ] HTML parses into the intended DOM with unique ids and valid interactive/list nesting.
- [ ] Section/Row/Card/Content boundaries are explicit and Card runs have dedicated parents.
- [ ] Static editable candidates and database-owned content are separately inventoried.
- [ ] Required plugin hooks and initialization order still work.
- [ ] Desktop, tablet and mobile match the baseline after normalization.
- [ ] No blocking issue is being deferred to multiple Blade files.
- [ ] The diff contains structural/portability repairs only, or every visual change was explicitly
      approved.

Report the files normalized, findings fixed, risky findings left for user direction, and the evidence
used for visual/interaction equivalence before beginning the cut.
