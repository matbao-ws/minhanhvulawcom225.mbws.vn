# Theme Cut Acceptance

The gate a theme cut must pass before anyone calls it done. Read it **before** starting a cut (so
the inventory drives the work) and **again before reporting** (so the report carries evidence, not
claims). The other references explain each rule in depth; this file is the order of work, the
failure catalogue and the proof.

Every rule here exists because a cut shipped without it: pages left out, a header pasted into
every page, a product name made inline-editable, a card grid nobody could add a card to, an
editor that loaded on no page at all. Each looked finished in a screenshot.

## 0. Non-negotiables

1. **Cut everything.** Every HTML page and every state in `theme/` has a route and a Blade view, and
   every section of every page is present, in order. Nothing is "left for later" silently.
2. **Refactor, never paste.** One layout shell, shared partials, reusable components. No page holds
   its own copy of the header, footer, a card or a form.
3. **Authored content is editable; database content never is.** Copy the shop writes once (titles,
   intros, CTA labels, decorative pictures, hotline, address) is an inline region. Anything with an
   admin screen (products, posts, categories, brands, partners, menus, banners, shop name/logo) is
   plain Blade output from its service — never wrapped in `x-client::editable`.
4. **Repeated authored units are lists.** A run of cards, steps, benefits, members or footer links is
   a `@foreach` over `$lists->items(…)` with `<x-client::list-item>` as its outermost element.
5. **The editor is core.** The layout has `@include('core.head')` before `</head>` and
   `@include('core.editor')` once before `</body>`. Never copy, fork or re-implement editor code.
6. **Do not fight the editor with theme CSS/JS.** §4 lists the patterns that break it.
7. **Done means proven.** `client:editable-audit` → `RESULT: PASS`, tests pass, the browser smoke
   test in §5 was run, an independent reviewer (§7) returned ĐẠT, and the report in §8 is filled in
   with real output.

## 1. Before cutting: the inventory

Run the preflight (`theme-preflight.md`) first. The audit reads `*.blade.php` only, so it cannot
measure raw HTML in `theme/`; the inventory below is how the work is sized. Write it down and keep
it for the report:

**Pages.** One row per HTML file in `theme/`, including states (empty cart, search with no result,
404, thank-you, account pages):

| Theme file | Route (name + URL) | Blade view | Data source |
| --- | --- | --- | --- |
| `index.html` | `client.home` `/` | `client/pages/home.blade.php` | authored + Products + Posts + Partners |

**Sections.** For every page, the sections top to bottom, each classified:

| Section | Kind | Owner |
| --- | --- | --- |
| Hero slider | authored, repeated run (slides) | inline: `list-item` per slide |
| "Sản phẩm nổi bật" grid | database | Catalog → `ProductQueryService` |
| "Đối tác" logo strip | database | Partners → `PartnerQueryService::forGroup('…')` |
| Footer columns | authored, repeated runs | inline: `list-item` per link |

**Ownership map.** Decide each block's owner from this table. When a block is clearly managed data
and no module owns it, stop and tell the user which module is missing — do not fake it with inline
regions (that is how partner logos ended up inline-editable before the Partners module existed).

| Block in the theme | Owner | Render |
| --- | --- | --- |
| Product cards, product detail, prices, stock, variants | Catalog | `Catalog\ProductQueryService` |
| Category tree, category names and images | Categories | `Catalog\CategoryService` |
| Brand logos and brand pages | Brands | `Catalog\BrandService` |
| Partner / customer logo strips | Partners, one **group** per strip | `PartnerQueryService::forGroup('<group key>')` |
| News, blog, articles | Posts | post queries already used by the client controllers |
| Header/footer navigation | Menus | `MenuService` |
| Banner / hero images managed as campaigns | Banners (by `position`) | banner query |
| Policy pages (Giới thiệu, Chính sách…) | CMS Pages, `/{locale}/chinh-sach/{slug}` | Page render contract |
| Shop name, logo, favicon | Settings | `SiteBranding` |
| Contact form | Contact submissions | `/api/public` contact endpoint |
| Section titles, intros, CTA labels, hotline, address, opening hours, decorative pictures | **inline** | `x-client::editable`, `editable-image`, `@clientBackground` |

Check names against the code before relying on them (a service listed here may have been renamed).

## 2. Cutting: the structure checklist

Work through it in this order; each item is checked by a command or by §5.

- [ ] `resources/views/client/layouts/app.blade.php` is the only document shell: `<html>`,
      `<head>` with `@include('core.head')`, `<body>`, `@yield('content')`, `@include('core.editor')`
      before `</body>`. Pages `@extends` it. *(audit: layout wiring)*
- [ ] Header, navigation, footer, mobile menu, floating buttons are partials included by the layout,
      not repeated per page.
- [ ] A UI piece used on two pages (product card, post card, breadcrumb, pagination, section title)
      is a component or partial with parameters.
- [ ] Each page is a sequence of section partials (`partials/<page>/<section>.blade.php`), each
      section carrying its outline marker (`data-outline-section` / `data-outline-label`) as in
      `editable-markup-standard.md` §6.
- [ ] Assets live in `public/client-assets/` (or the theme's asset folder) and are referenced with
      `asset()`; no `../` paths, no file:// paths, no duplicate CDN copies of a library.
- [ ] Theme JavaScript is kept as the theme wrote it, initialised once, outside Blade loops; no
      inline `onclick`; sliders and counters run for visitors exactly as before.
- [ ] Every static text and image is classified (`editable-markup-standard.md` §1). *(audit: text/images)*
- [ ] Every repeated authored run is a list of `list-item`s, or carries
      `{{-- editor:fixed-run <lý do> --}}` with a true reason. *(audit: not a list-item)*
- [ ] No inline region wraps database data. *(audit: editable database data)*
- [ ] Approved real data in the theme (menus, policy pages, partner logos…) is seeded idempotently
      (`theme-data-seeding.md`), and the Blade reads it from the database.
- [ ] Routes exist for every page in the inventory, with the `{locale}` parameter where the
      storefront is localized; every internal link uses `route()`.
- [ ] The cut matches the approved theme visually at 1440px, 768px and 375px (screenshots side by
      side, `laravel-blade-cutting.md` §Verification).

## 3. Marking regions

Follow `editable-markup-standard.md` while cutting, not afterwards: keys are the primary keys of
content the shop will save, and renaming one later abandons that content. Key shape
`<page>.<section>.<slot>`; list items `<list>.item_<id>.<slot>`.

## 4. Theme patterns that break the editor

Each of these has shipped at least once. Recognise the pattern while cutting.

| Pattern in the theme | What goes wrong | Do this |
| --- | --- | --- |
| Icon `<i>`/`<svg>` inside the same element as the label | Editing or saving the label wipes the icon | Keep the icon outside the region: `<x-client::editable tag="span">Liên hệ</x-client::editable> <i class="…"></i>` |
| Text and child blocks mixed in one element | The scanner cannot own the text; editing would rewrite the children | Put the text in its own element and mark that |
| Run of cards written N times, or `@foreach` over an inline array | Fixed length: no Nhân bản / move / delete | `list-item` list (§0.4) |
| `list-item` inside a `.col` wrapper | A copy lands inside that column instead of beside it in the grid | `list-item` **replaces** the outermost element and carries its classes |
| Slider with loop clones (Swiper/Owl/Slick `loop`) | Clones duplicate regions and their `data-*` hooks | Mark the slides' source run; keep clones out of the editable root or give the static caption outside the slider (`editable-markup-standard.md` §7) |
| Marquee that prints its run twice | Two regions per logo, a copy edits one half | Database-driven (Partners) or `editor:fixed-run` + `data-editable-ignore` on the second run with its reason |
| Number counters, typed text, text written by JavaScript | The script overwrites the edited value | Keep the value in a region and have the script read it, or ignore it with a reason (`.agents/skills/build-inline-storefront-editing/references/marking-regions.md`) |
| Decorative overlay `absolute inset-0` above text | Clicks never reach the region | `pointer-events-none` on the overlay |
| `user-select:none`, `*[contenteditable]` rules, `-webkit-user-modify` in theme CSS | Typing or selecting fails in the editor | Remove those rules from the theme CSS |
| Theme z-index of 2147483000 or more (the editor's panels sit there), `overflow:hidden` on `html/body` | Toolbar or panels hidden or clipped | Lower the theme's z-index; let the body scroll |
| Text inside `<select><option>` | Not reachable by the editor | Label outside the select, or `data-editable-ignore` with reason |
| `<strong class="text-…">` coloured words inside a paragraph | (core handles it) Colour must reach bold words too | Keep the theme's classes; do not strip them to "help" the editor |
| Inline element (`span`) regions | (core handles it) A copy starts its own line | Do not force `display:inline !important` on region tags |
| Lazy images (`data-src`, `loading` libraries) | Editor shows the placeholder; a replaced picture is overwritten | `editable-image … lazy="data-src" placeholder="…"` |
| Same key used for two different texts | Editing one changes both | Keys unique per position; reuse only when the text must be identical |
| `text-transform: uppercase` headings | Source copy written in capitals reads badly in the editor and in SEO | Write the copy in natural case; let CSS uppercase it |
| Fixed height / `line-clamp` on a text block | A longer edit is cut off for visitors | Keep the clamp only where the design truly truncates; mention it in the report |

## 5. Browser smoke test of the editing toolkit

Run it as an admin with `pages.update` and `media.view` on the home page and one page of every other
template (listing, detail, CMS page, contact). Use a real browser or headless Chrome. Write down the
result of every step; a step that could not be run is reported as **chưa kiểm**, never as passed.

1. Edit mode **off**: no outlines, no hover borders, no "+" or list controls. **On**: they appear.
2. Click a heading: the toolbar opens next to it; the "…" menu title names the section and region.
3. Type, then Ctrl+Z and Ctrl+Y.
4. Select a sentence containing a bold/coloured word → choose a colour: **every** selected word
   changes, the bold one included. "Bỏ màu" brings the theme's colours back.
5. Align centre; Shift+Enter makes a line break; change the tag to H2 and back to P.
6. "Ẩn vùng này khỏi trang": the region disappears at once (no empty dashed box). Huỷ brings it back.
7. In a card: "Nhân bản ô này" → the copy appears **beside** the source in the same grid, without a
   page reload, edit mode still on. Edit the copy, Lưu. Chuyển lên / Chuyển xuống. Xoá ô.
8. On a lone heading or paragraph: "Nhân bản đoạn này" → the copy appears right after it, on its
   own line.
9. ✨ AI (if the package has AI): a rewrite replaces the text, Ctrl+Z restores it.
10. Click an image: the media picker opens; replace it. A section background opens from its own
    handle.
11. Lưu, reload: every change persisted. Log out: no editor markup in the HTML, copies render,
    hidden regions stay hidden.
12. Database blocks (products, posts, partners, menus) show **no** outline and cannot be typed into.
13. Responsive preview at the three widths: layout matches the visitor's view.

## 6. Commands

```bash
php artisan client:editable-audit                 # RESULT: PASS — text, images, ignores, sections, menus, editor structure
php artisan client:editable-audit --path=<dir>    # another folder of Blade views (a draft cut, a theme benchmark)
php artisan test --compact                        # all green
vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G
```

`--baseline` exists only for themes cut **before** the editor-structure rules (`team.py` writes it once
when such a project takes the core update). A new or re-cut theme never uses it: a cut is done when
the structure block is clean, and `resources/views/client/.editor-structure-baseline.json` must not
exist or grow because of it. Fixing an old finding and re-running `--baseline` shrinks the file.

The audit is necessary, not sufficient: it reads Blade text. It cannot see a page you forgot to cut,
a section missing from a page, a visual difference or a broken slider. §1, §2 and §5 cover those.

## 7. Independent review

The agent that cut the theme does not get to grade it. Before reporting, an independent reviewer
runs `theme-cut-review.md` with a fresh context — in Claude Code the `theme-cut-reviewer` subagent;
in any other tool a new session given the one-line instruction at the top of that file. It is
read-only and returns **ĐẠT** or **CHƯA ĐẠT** with `file:line` evidence.

Fix every blocking finding, then run a **new** reviewer (not the same conversation) again. Repeat
until ĐẠT. Do not argue a finding away in the report: either fix it, or show the reviewer was wrong
with the file and line, and say so explicitly.

## 8. Report

The cut is reported with this, in Vietnamese, filled with real results. "Xong" is not a valid
report while any row is open.

```text
1. Trang đã cắt: bảng §1 (file theme → route → Blade), không thiếu trang nào / thiếu: …
2. Section mỗi trang: số section theme / số section Blade, khác biệt nếu có
3. Khối lấy từ database và module sở hữu: …
   Khối cần module chưa có: … (đã báo người dùng)
4. Dãy lặp: chuyển thành list-item: … / cố ý cố định (lý do): …
5. Audit (nguyên văn): dòng Editor structure + RESULT
6. Test (nguyên văn): dòng Tests:
7. Smoke test §5: từng bước đạt / lỗi / chưa kiểm
8. So sánh giao diện 1440 / 768 / 375: khớp / khác ở …
9. Còn tồn đọng (file:dòng): …
10. Review độc lập (§7): số vòng; KẾT LUẬN của vòng cuối, dán nguyên văn
```
