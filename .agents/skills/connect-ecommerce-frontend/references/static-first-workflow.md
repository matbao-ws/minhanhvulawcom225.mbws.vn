# Static-first Storefront Workflow

## Contents

1. Workflow contract
2. Stage 1 — Build the HTML prototype
3. Gate 1 — Approve the visual design
4. Stage 2 — Cut the interface
5. Gate 2 — Approve the cut structure
6. Stage 3 — Connect the backend
7. Gate 3 — Verify integration
8. Recommended structure
9. Handoff checklist

## Workflow contract

Use this order:

```text
Requirements/page map
  -> static HTML prototype
  -> visual approval
  -> cut into reusable pages/components
  -> structural approval
  -> connect the public API
  -> integration verification
```

Do not collapse these stages unless the user explicitly asks for an end-to-end implementation.
Creating a prototype does not authorize backend changes. Connecting a backend does not authorize a
visual redesign.

Determine where the storefront lives before creating files:

- Root `theme/` directory inside this Laravel repository: treat it as approved source HTML and cut
  it into `resources/views/client` using [laravel-blade-cutting.md](laravel-blade-cutting.md).
- User-provided separate frontend directory/project explicitly chosen as output: keep work there.
- Existing storefront inside the repository: follow its established structure.
- No location provided: propose a prototype location; do not mix storefront pages into admin views.

The Laravel core has no default storefront, but a supplied root `theme/` explicitly selects an
in-repository Laravel Blade storefront.

When `theme/` already contains the requested pages, do not recreate those pages as a new prototype.
Inventory and preserve them, then begin the Laravel Blade cut at Stage 2 unless the user explicitly
asks for a redesign.

## Stage 1 — Build the HTML prototype

### Define the page map

Cover only pages in scope, selected from:

```text
home
catalog/search
category/brand
product detail
cart
checkout
payment result
login/register/forgot/reset
account profile
addresses
order history/detail
guest tracking
post listing/detail
contact
```

For each page, list:

- Reusable sections.
- Desktop/mobile behavior.
- Mock data needed.
- Default, loading, empty, error, and disabled states.
- Forms and interactions.

### Keep prototype concerns separate

Recommended prototype separation:

```text
prototype/
├── pages/
├── components/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
└── fixtures/
```

Use this as a conceptual structure; preserve an existing project convention when present.

Rules:

- Use mock fixtures shaped like backend Resources.
- Keep sample data out of repeated hard-coded HTML where practical.
- Use semantic elements and accessible labels.
- Use escaped text rendering for fixture content.
- Add stable hooks such as `data-role="product-list"` and
  `data-action="add-to-cart"`.
- Do not couple styling to database IDs or API URLs.
- Do not introduce authentication token storage or real checkout calls.

### Prototype acceptance

The prototype is ready for review when:

- All scoped pages exist.
- Navigation between mock pages works.
- Responsive layouts are usable.
- Core components have normal/empty/error states.
- No backend or production data is required to view it.
- Assets load from portable relative paths.

## Gate 1 — Approve the visual design

Before cutting:

- Confirm selected pages.
- Confirm desktop and mobile screenshots/behavior.
- Record visual decisions that must not change.
- Record intentionally unfinished content.

If approval is absent, continue only with non-destructive prototype refinements.

## Stage 2 — Cut the interface

“Cut the interface” means turn approved static pages into maintainable frontend units without
changing their appearance.

### Extract the shared shell

Create reusable units for:

```text
document head
header/navigation
language switcher
search
breadcrumbs
footer
modal/drawer
toast/alert
loading/empty/error states
```

### Extract commerce components

Create reusable units for:

```text
category/brand item
product card
price display
promotion badge
option group/value selector
quantity control
cart row/summary
voucher form
checkout form
order status/timeline
review list/form
pagination
field validation message
```

### Separate behavior

- Put initial and repeated page markup in Blade pages/components.
- Put DOM querying and interactive state updates in page or component JavaScript modules.
- Put cart persistence in one cart store/module.
- Put formatting in helpers.
- Keep fixture access behind an adapter that can later be replaced by the API client.
- Avoid inline scripts and duplicate event registration.
- Use event delegation for repeated dynamic lists.
- Do not convert the supplied HTML into JavaScript template strings or an empty client-rendered
  shell.

### Preserve visual fidelity

Compare the cut output with the approved prototype:

- Same content hierarchy and component order.
- Same typography, spacing, colors, breakpoints, and interactions.
- No layout shift caused by component extraction.
- Same empty/loading/error presentations.

## Gate 2 — Approve the cut structure

Before live API integration:

- Pages render through reusable units.
- Duplicate markup/logic is removed where reasonable.
- Mock fixtures still produce the approved design.
- API client boundary and render targets are identified.
- Page-to-endpoint mapping is reviewed.

## Stage 3 — Connect the backend

Read [api-contracts.md](api-contracts.md) before this stage.

### Create the integration boundary

For a same-origin Laravel Blade storefront, use server rendering for initial page data and one API
client for browser interactions:

```text
web route -> client controller -> service/query -> Blade

browser interaction -> API client -> domain adapter -> targeted DOM update
```

Do not call `fetch()` independently from every click handler.
Do not call the application's own public API over HTTP from its PHP controller.

### Replace fixtures incrementally

Recommended order:

1. Settings and languages.
2. Categories, brands, products, posts.
3. Product detail and option selection.
4. Customer authentication.
5. Addresses and account pages.
6. Voucher preview.
7. Checkout and payment redirect.
8. Orders/tracking.
9. Reviews.

For each slice:

1. Verify route/method/payload in backend code.
2. Replace only that fixture adapter.
3. Map `data` and `meta` explicitly.
4. Implement loading, empty, validation, auth, conflict, rate-limit, and server-error states.
5. Verify responsive UI again.
6. Remove the fixture from production use after the live slice passes.

## Gate 3 — Verify integration

Verify:

- No product price, discount, shipping fee, stock, or total is treated as authoritative client data.
- Variant checkout sends `option_value_ids`.
- Customer token is attached only to intended requests and never logged.
- `401`, `403`, `409`, `422`, and `429` have distinct UI behavior.
- Checkout prevents duplicate submission and uses server response totals.
- Payment navigation uses only server-returned `payment_url`.
- Review duplicate `409` is presented clearly.
- Locale changes re-fetch content and preserve canonical localized slugs.
- Production build does not silently fall back to mock commerce data.

## Recommended structure

For a root `theme/` cut into this Laravel repository:

```text
resources/views/client/
├── layouts/
├── partials/
├── components/
└── pages/

public/client-assets/
├── css/
├── js/
├── images/
├── fonts/
└── vendor/
```

For an explicitly separate framework-free HTML/JS project:

```text
src/
├── pages/
├── components/
├── services/
│   ├── api-client.js
│   ├── auth-api.js
│   ├── catalog-api.js
│   └── order-api.js
├── stores/
│   ├── auth-store.js
│   └── cart-store.js
├── fixtures/
├── utils/
└── assets/
```

Use the separate `src/` tree only when the user explicitly chose a separate frontend output.

## Handoff checklist

Report:

- Current stage and approval gate reached.
- Pages completed.
- Components extracted.
- Mock fixtures remaining.
- Endpoints connected.
- Auth and cart persistence strategy.
- Responsive/accessibility checks.
- Known backend/OpenAPI mismatches.
- Exact next stage; do not silently continue across an approval gate.
