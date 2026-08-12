# Storefront Inline Admin Editing Contract

There is no drag-and-drop page builder in this core. GrapesJS, `PageBuilderService` and the
`builder_data` / `published_css` / `schema_version` columns were removed. Do not reintroduce them,
and do not add a replacement editor library unless the user explicitly asks.

What exists is inline editing built on the browser's native `contenteditable`, driven by
`resources/views/client/partials/admin-bar.blade.php`.

## Preserve the integration point

Every in-repository storefront layout must include this partial once near the end of `<body>`:

```blade
@include('client.partials.admin-bar')
```

Reuse the partial. Do not copy its toolbar, authorization checks, or save logic into theme pages.
When replacing `resources/views/client/layouts/app.blade.php`, merge the theme shell around this
include instead of overwriting it.

## Render CMS Pages through the editable contract

For content owned by `App\Models\Page`, pass the existing page context to the Blade view:

```text
$page
$title
$html
$metaTitle
$metaDescription
```

Render the HTML inside `id="client-page-{page id}"` — the editor toggles `contenteditable` on that
element and reads its `innerHTML`. A Page stores HTML only (`published_html`); there is no per-page
CSS, so do not add a page-scoped style node or reintroduce a CSS field.

Preserve localized slug resolution, publication checks and the `cms_page` feature check in the
client controller.

Do not turn static theme About/Policy/Landing content into permanently hard-coded Blade when it is
intended to be editable. Seed or migrate it into `Page` and render it through the Page contract.
Keep dynamic catalog, checkout, account and order screens in their own domain views.

Server-render any DB-driven block inside a CMS page with `contenteditable="false"` on its wrapper so
it stays outside the editable region.

## Keep the write boundary private

Inline save must continue through the localized admin route protected by:

```text
web session -> auth -> active admin -> feature:cms_page -> can:manage_pages
```

Send the CSRF token, the current content locale and the edited HTML — nothing else. Persist through
`InlinePageUpdateRequest` and `PageContentService::updateLocale()`, keeping the
`admin-page-inline` throttle, so every save:

- validates locale and payload size;
- sanitizes HTML through `PageHtmlSanitizer`;
- snapshots a revision;
- preserves other locales and Page metadata;
- writes an admin activity log.

Never expose inline save under `/api/public`, trust a client-supplied user/permission, or write the
Page model directly from a storefront controller.

## Load editor assets conditionally

Only render the toolbar, editor script and save URL when the session user is active, has an admin
role (`role_id` is not null) and can `manage_pages`. Guest/customer HTML must contain none of them.

Namespace toolbar/editor CSS so imported theme rules cannot hide or resize it, and keep its z-index.

For images, preserve the inline Media picker:

- Require `manage_media` separately from `manage_pages`.
- Read paginated images from the protected admin media resources route.
- Replace only the selected image element.
- Upload through the existing protected media upload route with CSRF.
- Keep the picker open after upload, refresh its first page and let the admin choose the image.
- Do not expose media endpoints, URLs or picker code to admins without `manage_media`.

Image sources written into Page HTML must stay portable: our own media is stored as a relative path
and resolved on read by `App\Support\MediaUrl`, which `PageHtmlSanitizer` applies on save. Do not
write absolute URLs pointing at the site's own host into page content.

## Map non-Page resources honestly

Product, category, post and other domain records are not CMS Pages. Link their admin-bar action to
the correct protected editor unless a dedicated inline endpoint with equivalent validation,
authorization and audit behavior already exists. Never save those resources through the Page
endpoint merely to make the UI appear editable.

## Verify after every theme cut

- Guest and customer do not see `client-admin-bar` or editor assets.
- Admin without `manage_pages` cannot see or call inline editing.
- Authorized admin can toggle edit mode, save and see sanitized content without reloading.
- Saving creates a revision and preserves translations in other locales.
- Dangerous HTML is rejected or removed.
- Theme CSS does not cover the toolbar.
- Authorized media admins can replace an image and upload without closing the picker.
- Image URLs in saved page content are relative, not pinned to the current host.
