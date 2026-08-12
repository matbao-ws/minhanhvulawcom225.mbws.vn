# Verification

## Test matrix

Write these before calling the feature done. They are the cheap ones that catch
the expensive mistakes.

### Exposure

| Visitor | Expectation |
|---|---|
| Guest | no `data-block-key`, no toolbar, no picker markup, on every page |
| Customer (no staff role) | same as guest |
| Staff without the content permission | same as guest |
| Staff with content permission | hooks present, toolbar present |
| Staff without the media permission | text hooks present, picker markup absent |

Run the guest/admin pair across **every** storefront page with a data provider,
not just the page you happened to work on.

### Write path

- Saves and returns the sanitized value.
- The saved value replaces the Blade default on the next page load, and the
  default is then gone from the response.
- `text` strips markup; `html` keeps allowed tags and drops `<script>`;
  `image` is stored as a portable path and resolved to a URL on read.
- Editing one locale leaves the others untouched.
- A second, identical save creates no revision; a later different save snapshots
  the previous value with the editing user's id.
- Rejects: a key that is not slug-shaped, an unknown type, an unsupported
  locale, an oversized body. Assert nothing was written.
- Guest → 401, customer → 403, staff without permission → 403. Assert nothing
  was written.

### Boundary with the database

Pick the page regions that are rendered from records — the product grid, the
post list, a settings-backed carousel — slice that part of the response, and
assert it contains the record's data **and** no `data-block-key`. This is the
test that stops the two-editors bug from coming back.

## Manual pass

Automated tests cannot click. Ask a human to check, with edit mode **on** and
then **off**:

- a region wrapped by a theme handler — certificate modal, gallery lightbox,
  tab switcher: on → the editor opens; off → the theme behaves as designed;
- an image swap end to end, including upload;
- `Esc` cancels, blur saves, `Enter` confirms a single-line region;
- the toolbar and picker are legible in the site's own font, and theme CSS does
  not cover them.

## Deployment notes to report

- The content permission must actually be on a role. A role created before this
  feature will not have it, and the admin will see no button — check the roles
  in the target database rather than assuming.
- Media uploads need the storage symlink to point at *this* project. A project
  copied from another checkout often carries a stale symlink; uploads then
  succeed and 404 on read.
