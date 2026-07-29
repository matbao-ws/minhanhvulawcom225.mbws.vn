# Laravel Blade Theme-Cutting Contract

## Purpose

Use this contract when the user places an approved HTML interface in `theme/` at the Laravel
repository root. The theme is the visual source of truth. The production storefront belongs in
Laravel Blade, not in a newly generated JavaScript application.

## Source and destination

```text
theme/                              read-only design source
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

Do not:

- Serve HTML files directly from `theme/`.
- Rename or flatten assets before their references are mapped.
- Create `src/pages`, a SPA router, React, Vue, or a parallel frontend project by default.
- Rebuild whole pages, product grids, headers, or footers with JavaScript template strings.
- Mix storefront files into `resources/views/admin` or `public/admin-assets`.

## Inspect before cutting

Inventory every source HTML page and record:

- Intended Laravel route and Blade destination.
- Shared shell and repeated sections.
- CSS, JavaScript, image, font, and vendor dependencies.
- Relative links and form targets.
- Mock product, category, post, account, cart, checkout, and order data.
- Interactive plugins and the DOM/class/data hooks they require.
- Responsive breakpoints and visually important states.

Do not start extraction from only the home page when the supplied theme contains more pages.

## Blade structure

Create a shared layout and use Blade composition:

```blade
{{-- resources/views/client/layouts/app.blade.php --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('client.partials.head')
        @stack('styles')
    </head>
    <body>
        @include('client.partials.header')
        <main>@yield('content')</main>
        @include('client.partials.footer')
        @stack('scripts')
    </body>
</html>
```

Use Blade components or partials for repeated commerce markup. Use server-rendered loops for initial
lists:

```blade
@foreach ($products as $product)
    <x-client.product-card :product="$product" />
@endforeach
```

Keep the source theme's DOM hierarchy, classes, attributes, and plugin hooks unless a change is
required for valid reusable markup. Extraction must not redesign the interface.

## Laravel conversion rules

Convert:

- Internal `.html` links to named `route()` calls.
- Asset paths to `asset('client-assets/...')` or the repository's established `@vite` entry.
- Repeated sections to Blade components/partials.
- CSRF-sensitive forms to Laravel routes with `@csrf` and the correct method.
- Form values/errors to `old()`, `@error`, and validated controller/FormRequest data.
- Locale-aware links to the repository's established locale routing policy.
- Static pagination to Laravel/API pagination without changing its approved markup.

Never expose admin-only models, secret settings, cost prices, or integration credentials to views.

## Data integration

For same-origin Laravel storefront pages:

```text
web route
  -> client controller
  -> application service/query
  -> Blade data
  -> server-rendered HTML
```

Do not make a PHP HTTP request back into the same application just to consume `/api/public`.
Extract or reuse a query/service when both the API Resource and Blade page need the same domain
data.

Use `/api/public` from browser JavaScript for targeted asynchronous behavior and from truly
separate storefront applications. Keep calls behind one API client. JavaScript may update a cart
drawer, variant availability, search suggestions, voucher result, checkout state, or pagination;
it must not become the default page renderer.

The backend remains authoritative for price, promotion, voucher, shipping, stock, order totals,
payment state, and allowed variant combinations.

## Asset handling

- Copy only assets referenced by the cut pages.
- Preserve vendor licenses and required font files.
- Keep source-relative directory relationships where changing them risks broken `url(...)` paths.
- Prevent client CSS from leaking into admin pages.
- Load scripts in dependency order and initialize each plugin once.
- Remove Cloudflare-injected `cdn-cgi` fragments and unavailable source-host artifacts.
- Verify every production asset URL returns the correct MIME type and a successful status.

## JavaScript boundary

JavaScript is appropriate for browser interactions, not for replacing Blade:

- Allowed: navigation toggles, sliders, gallery, variant selection, cart actions, async forms,
  loading/error states and payment redirect.
- Not default: generating the document shell, duplicating a Blade product card in a JS string,
  routing every page client-side, or fetching all initial page content after an empty shell loads.

When an asynchronously refreshed component needs HTML, use a small dedicated renderer/component
boundary or a backend partial response; do not duplicate the entire page template.

## Verification

Before reporting the cut complete:

1. Compare each Laravel page with its source HTML at desktop and mobile widths.
2. Verify named routes and locale-aware links.
3. Verify CSS, JS, images, fonts, and vendor assets load successfully with correct MIME types.
4. Verify initial meaningful content exists in the server response without waiting for JavaScript.
5. Verify forms include validation, CSRF, authorization, and safe error handling as applicable.
6. Verify no production view links back to `theme/` or depends on mock fixtures.
7. Verify JavaScript is limited to intended interaction boundaries.
