# Theme Cut Review

The brief for an **independent reviewer** of a theme cut. The reviewer is a different agent (or a
fresh session) from the one that cut the theme. It has not seen the cutter's reasoning or report,
and must not read the report before finishing its own pass: a reviewer handed a conclusion tends to
confirm it.

Claude Code runs it as the `theme-cut-reviewer` subagent (`.claude/agents/theme-cut-reviewer.md`).
Any other tool runs it by opening a new session and giving it this one instruction:

> Đọc `AI_BUILD_PROMPT.md`, rồi làm đúng `.agents/skills/connect-ecommerce-frontend/references/theme-cut-review.md`
> cho theme trong `theme/` và bản cắt trong `resources/views/client`. Không sửa file nào.

## Stance

- Assume the cut is incomplete until each check below proves otherwise.
- **Read-only.** Report findings; never fix them. The cutter fixes, then a new review runs.
- Every finding carries evidence: `file:line` on both sides (theme source and Blade), the text or
  element concerned, and why it fails the rule. A finding you cannot point at is not a finding.
- Re-open the file before writing each finding. Drop anything you cannot confirm.
- Do not skip a check because a command passed: `client:editable-audit` reads Blade only and cannot
  see a forgotten page, a missing section or a broken slider.

## Inputs

- Source: `theme/` (the normalized HTML after preflight) and its assets.
- Cut: `resources/views/client/**`, `routes/theme.php` / `routes/client.php`, storefront
  controllers, seeders created for the theme, `public/client-assets/**`.
- Contract: `theme-cut-acceptance.md` (the rules and the ownership map), `editable-markup-standard.md`.

## Checks

Work through every check for every page. Keep a table per check; it goes into the verdict.

**A. Pages.** List every `theme/**/*.html` (states included: empty cart, 404, thank-you, account).
For each: the route that serves it and the Blade view. Run `php artisan route:list --path=` or read
the route files; render the URL (`php artisan tinker` with the HTTP kernel, as a guest) and record
the status. Missing route, missing view or non-200 → **blocking**.

**B. Content.** For each page, list the sections of the theme HTML top to bottom and find each in
the rendered page. Then compare text: every visible sentence, heading, button label and image file
name of the theme page must be in the render — as an editable region's default, as seeded database
data, or deliberately removed with a reason the user approved. A sentence or picture that vanished →
**blocking**. Text changed by the cut (typos introduced, words dropped) → **blocking**.

**C. Structure.** One layout with `@include('core.head')` and `@include('core.editor')`; header,
footer, navigation and mobile menu exist once, as partials; markup repeated across pages (cards,
section titles, breadcrumbs) is a component or partial; pages are sequences of section partials with
outline markers. A header/footer pasted into a page, or the same card markup in two files →
**blocking**.

**D. Data ownership.** Using the ownership map in `theme-cut-acceptance.md` §1, decide each block's
owner yourself, then compare with the cut:
- managed data (products, posts, categories, brands, partners, menus, banners, shop name/logo) must
  render from its service, with no `x-client::editable`/`editable-image` around it → otherwise
  **blocking**;
- approved real data from the theme must be seeded idempotently and rendered from the database: run
  the theme's seeder twice and compare row counts (no change on the second run); literals left in
  Blade for seeded data → **blocking**;
- a managed block with no module that was faked with inline regions → **blocking**, and name the
  missing module.

**E. Editor wiring.** Every static text and picture is a region, dynamic, or ignored with a true
reason (read each `data-editable-ignore` and `editor:fixed-run` reason and judge it). Every repeated
authored run is a `list-item` list at the outermost element. Region keys follow
`<page>.<section>.<slot>` and are unique per position. Any pattern from `theme-cut-acceptance.md` §4
present in the cut (icon inside a region, overlay without `pointer-events-none`, slider clones…) →
**blocking** unless handled as the table says.

**F. Commands.** Run and quote verbatim: `php artisan client:editable-audit` (RESULT line and the
Editor structure block), `php artisan test --compact` (Tests line). Any failure → **blocking**. For a
new or re-cut theme, a `known (baseline)` count above 0, or a
`resources/views/client/.editor-structure-baseline.json` created or grown by this cut → **blocking**:
the baseline is only for themes cut before the rules existed.

**G. Toolkit.** If a browser or headless Chrome is available, run the smoke test of
`theme-cut-acceptance.md` §5 on the home page and one page of each other template; otherwise mark
every step **chưa kiểm** — never assume it passes.

**H. Visual.** If a browser is available, compare theme page and cut page at 1440, 768 and 375px.
Visible differences in layout, spacing, fonts, colours or missing images → **blocking**; otherwise
**chưa kiểm**.

## Verdict

Write in Vietnamese:

```text
KẾT LUẬN: ĐẠT | CHƯA ĐẠT
Lỗi chặn: <số>   Cần xem: <số>   Chưa kiểm: <danh sách check G/H nếu không chạy được>

A. Trang          bảng: file theme → route → view → status
B. Nội dung       mỗi trang: section theme / section bản cắt; câu/ảnh bị mất (file:dòng hai bên)
C. Cấu trúc       …
D. Dữ liệu        bảng khối → chủ sở hữu (theo bạn) → bản cắt đang làm gì; kết quả chạy seeder hai lần
E. Bộ sửa         …
F. Lệnh           nguyên văn
G. Smoke test     từng bước: đạt / lỗi / chưa kiểm
H. Giao diện      đạt / lệch ở … / chưa kiểm

Danh sách lỗi (mỗi dòng): [chặn|cần xem] check · file:dòng · mô tả · bằng chứng
```

**ĐẠT** only with zero blocking findings and checks A–F fully run. The cutter fixes every blocking
finding and a **new** reviewer (fresh context) runs again; repeat until ĐẠT. The final verdict is
pasted into the cut report.
