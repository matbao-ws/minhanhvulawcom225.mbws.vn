---
name: connect-ecommerce-frontend
description: Build or import a storefront HTML theme, cut a root `theme/` design into Laravel Blade under `resources/views/client`, preserve its approved appearance and admin inline-editing hooks, then connect, repair, or verify it against this Laravel Ecommerce Core and its public API. Use for storefront mockups, Laravel Blade template cutting, client layout replacement, CMS Page rendering or inline editing, asset migration, routes/controllers, replacing mock data, product/category/post rendering, locale handling, customer Sanctum authentication, addresses, cart and variant selection, voucher preview, checkout, VNPAY redirects, order tracking/history, product reviews, or aligning frontend payloads with `/api/public`.
---

# Connect Ecommerce Frontend

## Establish the contract

1. Read `AI_BUILD_PROMPT.md`.
2. Read [static-first-workflow.md](references/static-first-workflow.md) when creating a mockup,
   cutting an approved HTML design, or deciding the current frontend stage.
3. Read [laravel-blade-cutting.md](references/laravel-blade-cutting.md) whenever a `theme/`
   directory is supplied or the storefront belongs in this Laravel repository.
4. Read [inline-admin-editing.md](references/inline-admin-editing.md) whenever creating or replacing
   a client layout, rendering CMS Pages, or changing the storefront admin bar/editor.
5. Read [api-contracts.md](references/api-contracts.md) before connecting live data.
6. Inspect `routes/api.php`, the relevant API controller, Resource, and feature tests.
7. Inspect `public/docs/openapi.json`, but resolve any mismatch in favor of executable code/tests.
8. Identify whether the storefront is same-origin or cross-origin before choosing auth/storage/CORS
   behavior.

Do not invent endpoints or fields from UI labels.

## Determine the current stage

Classify the request before editing:

```text
Stage 1  Static HTML prototype with representative mock data
Stage 2  Approved design cut into Laravel Blade/components/assets
Stage 3  Components connected to the live public API
Stage 4  Integrated storefront verification and hardening
```

Do only the requested stage and its prerequisites.

- A supplied root `theme/` is an existing design input. Treat cutting it into Blade as Stage 2; do
  not rebuild it as a new Stage 1 JavaScript prototype unless the user explicitly requests a
  redesign.
- During Stage 1, prioritize appearance, responsive states, and complete page coverage. Do not add
  backend calls unless explicitly requested.
- Before Stage 2, preserve the approved visual result. Refactor structure without redesigning it.
- Before Stage 3, require stable pages/components and a page-to-endpoint mapping.
- During Stage 4, remove production dependence on mock fixtures and verify all error states.

## Inventory pages and data

Map each page to data and actions:

```text
home/catalog      categories, brands, products, settings
product detail    product, option groups, variants, reviews
auth              register, login, logout, reset
account           profile, addresses, order history
cart              product IDs, option value IDs, quantities
checkout          contact, address, active payment method, voucher
tracking          order number + contact
content           post categories and posts
```

Find existing mock JSON, duplicated API helpers, hard-coded prices, unsafe HTML insertion, and stale
payload names before editing.

## Build the static prototype first

Use representative mock data shaped like the public Resources, but keep fixtures separate from
markup. Build all important UI states:

- Default content.
- Loading/skeleton.
- Empty list.
- Validation error.
- Out-of-stock/disabled option.
- Authenticated and guest navigation.
- Mobile, tablet, and desktop layouts.

Use semantic, reusable markup and stable `data-*` hooks. Avoid inline event handlers and avoid
embedding API assumptions throughout the HTML.

Stop after the visual prototype when the user has not approved cutting/integration yet.

## Cut the approved interface

Extract the approved prototype into:

- `resources/views/client/layouts`: shared document shell.
- `resources/views/client/partials`: header, navigation, footer, breadcrumbs, modal and toast.
- `resources/views/client/components`: product card, price, option selector, cart row, pagination,
  form errors, order status and review item.
- `resources/views/client/pages`: page-specific Blade templates.
- Storefront routes/controllers and a namespaced client asset structure.
- Explicit render targets and event hooks for later API binding.
- The existing `client.partials.admin-bar` include and CMS Page inline-editing contract.

Preserve layout, spacing, typography, responsive behavior, and interactions from the approved HTML.
Do not mix mock fixtures with the future API client. Do not convert approved HTML markup into
JavaScript template strings or a client-rendered application unless the user explicitly requests
that architecture. Do not overwrite the shared client layout in a way that removes admin editing.

## Build one API client

For initial same-origin Laravel pages, use a storefront controller/service to pass data into Blade.
Do not call this application's `/api/public` over HTTP from a PHP controller. Reserve the browser
API client for targeted asynchronous interactions; a separate storefront may use it for all data.

Because Blade cannot reuse the API by calling it, any read query that serves both must live in one
shared service — never inline in a controller, or the API and the Blade page each end up with their
own copy that drifts. `Catalog\ProductQueryService` already owns catalogue filtering, sorting and
product-detail eager loading; extend it instead of rewriting the query in a client controller. Read
services take a filter array keyed by the public query names, not a `Request`.

Centralize:

- Base URL `/api`.
- `Accept: application/json`.
- Locale headers/query policy.
- Optional `Authorization: Bearer <customer token>`.
- JSON parsing and standard envelope handling.
- `401`, `403`, `409`, `422`, and `429` behavior.
- Network timeout/cancellation and user-safe errors.

Do not concatenate unsanitized data into HTML. Prefer DOM text properties or a trusted rendering
layer. Never log customer tokens or checkout personal data.

## Connect flows in dependency order

1. Settings and active languages.
2. Catalog and product details.
3. Variant selection from option groups.
4. Cart persistence containing only IDs and quantity.
5. Customer authentication and addresses.
6. Voucher preview.
7. Checkout and payment redirect.
8. Order tracking/history.
9. Review submission.
10. CMS content.

Render loading, empty, validation, unauthorized, rate-limited, and retry states for each flow.
Replace one mock-backed feature at a time and verify it before removing that fixture.

## Preserve server authority

- Never send or trust a client-calculated price, discount, shipping fee, stock, or total.
- For variant products, send `option_value_ids`; do not send a raw variant ID.
- Treat voucher preview as informational; checkout recalculates it.
- Use only `payment_url` returned by a successful checkout.
- Never mark an order paid from browser state or VNPAY return query alone.
- Treat response Resources as the public field allow-list.

## Verify

Check:

- The cut interface remains visually equivalent to the approved static HTML.
- No live API call exists in a Stage 1-only deliverable.
- Production pages no longer depend on mock fixtures after Stage 3.
- Every request method, path, field name, and auth requirement against backend code.
- Locale switching changes both requested content and generated URLs.
- A second review for the same customer/product handles `409`.
- Invalid/expired tokens clear authenticated UI state without deleting unrelated cart data.
- Checkout displays authoritative response totals.
- No sensitive data is stored in logs, HTML, analytics, or long-lived browser state.
- Authorized admins can open and save the CMS Page inline editor; guests, customers, and admins
  without `manage_pages` neither see nor load it.

When backend behavior is wrong, fix the backend contract and its tests first; do not hide backend
errors with frontend workarounds.

## Report

State the completed stage. List prototype/cut pages, reusable components, connected endpoints,
auth/token handling, mock fixtures still present, error states, tests/manual checks, and any
backend/OpenAPI mismatch that remains.
