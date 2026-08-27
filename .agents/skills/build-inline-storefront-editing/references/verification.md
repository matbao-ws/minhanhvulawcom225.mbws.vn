# Verification

## Test matrix

Write these before calling the feature done. They are the cheap ones that catch
the expensive mistakes.

### Exposure

| Visitor                              | Expectation                                                       |
| ------------------------------------ | ----------------------------------------------------------------- |
| Guest                                | no`data-block-key`, no toolbar, no picker markup, on every page |
| Customer (no staff role)             | same as guest                                                     |
| Staff without the content permission | same as guest                                                     |
| Staff with content permission        | hooks present, toolbar present                                    |
| Staff without the media permission   | text hooks present, picker markup absent                          |

Guests must also receive no list controls, no `data-append-list`, and no floating
add/remove buttons — but they **do** receive boxes an editor added, because those
are real content.

Run the guest/admin pair across **every** storefront page with a data provider,
not just the page you happened to work on. Two traps in these assertions:

- `actingAs()` stays in force for the rest of the test. A "guest" request written
  after an authenticated one is still authenticated; call `auth()->logout()`
  first or put the guest request before.
- The editor's own script contains its placeholder strings and CSS selectors, so
  `assertDontSee('Nội dung mới')` or `assertDontSee('is-locked')` on an
  authenticated page matches the JavaScript, not the DOM. Assert as a guest, or
  against the full attribute (`class="…"`), or in the database.

### Hiding, restoring, and lists

- Saving an empty region returns 200 and marks it cleared. Guard this one: the
  empty-string-to-null middleware makes it a 422 unless `content` is `nullable`.
- A cleared region renders no element for a visitor, still renders for an editor
  so it can be restored, and does not report itself as cleared when it was simply
  never edited.
- Restore removes the override entirely and brings the theme's own text back;
  restoring one locale leaves the others.
- An untouched repeatable section writes **no row** and renders exactly the
  length the template declares.
- Adding grows from that designed length, not from one.
- Removing an item deletes its content rows, and the *other* items keep their
  own text — the test that proves ids are not positional.
- Every item can be removed; an empty list stays empty.
- Rejects a malformed key or item id, and caps the list length.

### The page must not leak Blade

Assert no storefront page contains `{--`, `@php`, `@foreach` in its HTML. Cheap,
and it catches the delimiter-escaping mistake that generated markup invites.

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
- `Esc` cancels, blur marks dirty, Save writes, `Enter` confirms a single-line
  region;
- add a box and a list item, then Cancel — both must vanish; do it again and
  Save — both must survive a reload;
- mark an added box for deletion, then Cancel — it must come back;
- hide a region, confirm it disappears, then bring it back through the
  hidden-regions toggle and *restore default*;
- the toolbar and picker are legible in the site's own font, and theme CSS does
  not cover them.

## Deployment notes to report

- The content permission must actually be on a role. A role created before this
  feature will not have it, and the admin will see no button — check the roles
  in the target database rather than assuming.
- Media uploads need the storage symlink to point at *this* project. A project
  copied from another checkout often carries a stale symlink; uploads then
  succeed and 404 on rea

# Verification

## Test matrix

Write these before calling the feature done. They are the cheap ones that catch
the expensive mistakes.

### Exposure

| Visitor                              | Expectation                                                       |
| ------------------------------------ | ----------------------------------------------------------------- |
| Guest                                | no`data-block-key`, no toolbar, no picker markup, on every page |
| Customer (no staff role)             | same as guest                                                     |
| Staff without the content permission | same as guest                                                     |
| Staff with content permission        | hooks present, toolbar present                                    |
| Staff without the media permission   | text hooks present, picker markup absent                          |

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
