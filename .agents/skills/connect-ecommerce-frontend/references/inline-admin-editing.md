# Storefront Inline Admin Editing Contract

## Preserve the integration point

Every in-repository storefront layout must include this partial once near the end of `<body>`:

```blade
@include('client.partials.admin-bar')
```

Reuse the partial. Do not copy its toolbar, GrapesJS overlay, authorization checks, or save logic
into theme pages. When replacing `resources/views/client/layouts/app.blade.php`, merge the theme
shell around this include instead of overwriting it.

## Render CMS Pages through the editable contract

For content owned by `App\Models\Page`, pass the existing page context to the Blade view:

```text
$page
$title
$html
$css
$metaTitle
$metaDescription
```

Render the HTML inside `id="client-page-{page id}"` and keep the page CSS style node addressable by
the inline editor. Preserve localized slug resolution, publication checks and the `cms_page`
feature check in the client controller.

Do not turn static theme About/Policy/Landing content into permanently hard-coded Blade when it is
intended to be editable. Seed or migrate it into `Page` and render it through the Page contract.
Keep dynamic catalog, checkout, account and order screens in their own domain views.

## Keep the write boundary private

Inline save must continue through the localized admin route protected by:

```text
web session -> auth -> active admin -> feature:cms_page -> can:manage_pages
```

Send the CSRF token and only the current content locale, GrapesJS project data, HTML and CSS.
Persist through `InlinePageUpdateRequest` and `PageBuilderService::updateLocale()` so every save:

- validates locale and payload size;
- sanitizes HTML and CSS;
- snapshots a revision;
- preserves other locales and Page metadata;
- writes an admin activity log.

Never expose inline save under `/api/public`, trust a client-supplied user/permission, or write the
Page model directly from a storefront controller.

## Load editor assets conditionally

Only render the toolbar, GrapesJS CSS/JS, editor data and save URL when the session user is active,
has an admin role and can `manage_pages`. Guest/customer HTML must contain none of them.

Namespace toolbar/editor CSS so imported theme rules cannot hide or resize it. Keep the full
height chain for the overlay, canvas, frame wrapper and iframe. Do not move GrapesJS into
`public/client-assets`; it remains an admin-only library loaded conditionally.

For image components, preserve the inline Media picker:

- Require `manage_media` separately from `manage_pages`.
- Read paginated images from the protected admin media resources route.
- Replace only the selected GrapesJS image component.
- Upload through the existing protected media upload route with CSRF.
- Keep the picker open after upload, refresh its first page and let the admin choose the image.
- Do not expose media endpoints, URLs or picker code to admins without `manage_media`.

## Map non-Page resources honestly

Product, category, post and other domain records are not CMS Pages. Link their admin-bar action to
the correct protected editor unless a dedicated inline endpoint with equivalent validation,
authorization and audit behavior already exists. Never save those resources through the Page
endpoint merely to make the UI appear editable.

## Verify after every theme cut

- Guest and customer do not see `client-admin-bar` or editor assets.
- Admin without `manage_pages` cannot see or call inline editing.
- Authorized admin can open the overlay, save and see sanitized content without reloading.
- Saving creates a revision and preserves translations in other locales.
- Dangerous HTML/CSS is rejected or removed.
- Theme CSS does not cover the toolbar or collapse the GrapesJS iframe.
- Authorized media admins can replace an image and upload without closing the picker.
- The full Page Builder remains reachable as a fallback.
