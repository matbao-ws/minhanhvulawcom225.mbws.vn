# Content Blocks: Schema, Model, Service

## Schema

Three tables. Keep them boring.

```text
site_blocks
  id
  key            unique, slug-ish: "about.story.title"
  type           text | html | image
  format         nullable p|h1..h6 — a heading level chosen from the toolbar
  content        json, translatable
  timestamps

site_block_revisions
  id
  site_block_id  cascade on delete
  created_by     nullable, null on user delete
  content        json snapshot of the previous value
  created_at     only — a revision is never updated

site_lists
  id
  key            unique: "about.leadership", "about.hero.title.extra"
  items          json, ordered list of stable item ids
  timestamps
```

No `page_id`, no nesting, no per-item table. The key carries the location; the
template carries the layout. Adding structure here invites a second page builder.

`content` is translatable because the storefront already renders one locale at a
time. Saving one locale must leave the others untouched.

## site_lists: how many, not what

`site_lists` holds **only** the ids of the items a repeatable section shows, in
order. Each item's content lives in `site_blocks` under

```text
<list key>.item_<item id>.<slot>       about.leadership.item_m1.name
```

Two rules keep this a list feature rather than a builder:

- The template declares the ids it ships with and passes them in as defaults. A
  section nobody has touched has **no row at all**, so a fresh install renders
  the approved design with nothing seeded.
- Ids are opaque and stable, never positional. Removing the second of four items
  must not slide the third one's text into the second's slot.

Deleting an item deletes its `site_blocks` rows in the same transaction, or a
reissued id would resurrect somebody's old text.

An **empty stored list is a real answer** — the editor removed every item — and
must not fall back to the template's defaults.

## Model

Use the project's existing translatable trait so `getTranslation()` /
`setTranslations()` behave like every other localized model. Declare the type
constants on the model, not as loose strings:

```php
public const TYPE_TEXT = 'text';
public const TYPE_HTML = 'html';
public const TYPE_IMAGE = 'image';
public const TYPES = [self::TYPE_TEXT, self::TYPE_HTML, self::TYPE_IMAGE];
```

## Service

One service owns both sides. Register it **scoped** — a page renders dozens of
regions and must not issue a query per region.

### Reading

```php
public function value(string $key): ?string      // null → render the Blade default
public function isCleared(string $key): bool     // emptied on purpose → render nothing
```

- Loads every block once per request and keys it by `key`.
- `value()` returns `null` both when the region was never edited and when it was
  saved empty. Those mean opposite things, so `isCleared()` exists to tell them
  apart: **the presence of the locale key**, not its contents, is the signal.
- For an image, resolve the stored path to a URL through the project's media URL
  helper.

### The translatable-trait trap

An empty string is the value that carries meaning here, and the usual
translatable helpers destroy it. Check yours before trusting it; in this project
(spatie/laravel-translatable):

| Call                                       | What it does to`{"vi": ""}`                           |
| ------------------------------------------ | ------------------------------------------------------- |
| `getTranslations('content')`             | **drops the key** — it filters empty strings out |
| `getTranslation('content', 'vi', false)` | returns`''`, same as a missing locale                 |
| `setTranslations('content', [...])`      | **merges** — it cannot remove a locale           |

So read the raw column (`$block->getAttributes()['content']`, json-decoded) when
you need to know whether a locale is present, and write the column directly when
you need to remove one. Both mistakes are silent: the feature just does nothing,
and tests that assert through the trait still pass.

### Writing

```php
public function updateLocale(string $key, string $type, string $locale, string $value, ?int $userId)
```

In order:

1. Reject an unsupported locale and an unknown type with a validation exception.
2. Clean by type:
   - `text` → trim + strip tags. A single-line label has no business holding markup.
   - `html` → the project's existing rich-text sanitizer.
   - `image` → the project's "make this media reference portable" helper, so the
     value survives an `APP_URL` change.
3. If the cleaned value equals what is stored for that locale, return early —
   no write, no revision. Inline editors fire on every blur.
4. Otherwise, inside a transaction: snapshot, merge the locale into the existing
   translations, save.
5. Drop the request-level read cache so anything rendering later in the same
   request sees the new value.

### Restoring the template's own text

```php
public function restoreLocale(string $key, string $locale, ?int $userId): ?SiteBlock
```

Clearing a region and restoring it are different operations. Clearing stores an
empty string; restoring removes the locale from the override entirely, and
removes the row once no locale is left — the row *is* the override. Snapshot
first, as with any other write.

### Revisions

Coalesce them. Inline editing saves constantly; one revision per keystroke is
noise, not history:

```php
if ($block->revisions()->where('created_at', '>=', now()->subMinutes(10))->exists()) {
    return; // this editing session already has a "before" checkpoint
}
```

The first save of a brand-new block has nothing to snapshot — that is correct,
not a missing revision.

## What does not belong here

- No `default` column. The default lives in the template, where a designer can
  see it next to the markup.
- No cache beyond the request. Content changes must be visible on the next load;
  a shared cache adds an invalidation bug for no measurable win.
- No cascade of blocks per page. Keys are global and fla

# Content Blocks: Schema, Model, Service

## Schema

Two tables. Keep them boring.

```text
# Content Blocks: Schema, Model, Service

## Schema

Three tables. Keep them boring.

```text
site_blocks
  id
  key            unique, slug-ish: "about.story.title"
  type           text | html | image
  format         nullable p|h1..h6 — a heading level chosen from the toolbar
  content        json, translatable
  timestamps

site_block_revisions
  id
  site_block_id  cascade on delete
  created_by     nullable, null on user delete
  content        json snapshot of the previous value
  created_at     only — a revision is never updated

site_lists
  id
  key            unique: "about.leadership", "about.hero.title.extra"
  items          json, ordered list of stable item ids
  timestamps
```

No `page_id`, no nesting, no per-item table. The key carries the location; the
template carries the layout. Adding structure here invites a second page builder.

`content` is translatable because the storefront already renders one locale at a
time. Saving one locale must leave the others untouched.

## site_lists: how many, not what

`site_lists` holds **only** the ids of the items a repeatable section shows, in
order. Each item's content lives in `site_blocks` under

```text
<list key>.item_<item id>.<slot>       about.leadership.item_m1.name
```

Two rules keep this a list feature rather than a builder:

- The template declares the ids it ships with and passes them in as defaults. A
  section nobody has touched has **no row at all**, so a fresh install renders
  the approved design with nothing seeded.
- Ids are opaque and stable, never positional. Removing the second of four items
  must not slide the third one's text into the second's slot.

Deleting an item deletes its `site_blocks` rows in the same transaction, or a
reissued id would resurrect somebody's old text.

An **empty stored list is a real answer** — the editor removed every item — and
must not fall back to the template's defaults.

## Model

Use the project's existing translatable trait so `getTranslation()` /
`setTranslations()` behave like every other localized model. Declare the type
constants on the model, not as loose strings:

```php
public const TYPE_TEXT = 'text';
public const TYPE_HTML = 'html';
public const TYPE_IMAGE = 'image';
public const TYPES = [self::TYPE_TEXT, self::TYPE_HTML, self::TYPE_IMAGE];
```

## Service

One service owns both sides. Register it **scoped** — a page renders dozens of
regions and must not issue a query per region.

### Reading

```php
public function value(string $key): ?string      // null → render the Blade default
public function isCleared(string $key): bool     // emptied on purpose → render nothing
```

- Loads every block once per request and keys it by `key`.
- `value()` returns `null` both when the region was never edited and when it was
  saved empty. Those mean opposite things, so `isCleared()` exists to tell them
  apart: **the presence of the locale key**, not its contents, is the signal.
- For an image, resolve the stored path to a URL through the project's media URL
  helper.

### The translatable-trait trap

An empty string is the value that carries meaning here, and the usual
translatable helpers destroy it. Check yours before trusting it; in this project
(spatie/laravel-translatable):

| Call | What it does to `{"vi": ""}` |
|---|---|
| `getTranslations('content')` | **drops the key** — it filters empty strings out |
| `getTranslation('content', 'vi', false)` | returns `''`, same as a missing locale |
| `setTranslations('content', [...])` | **merges** — it cannot remove a locale |

So read the raw column (`$block->getAttributes()['content']`, json-decoded) when
you need to know whether a locale is present, and write the column directly when
you need to remove one. Both mistakes are silent: the feature just does nothing,
and tests that assert through the trait still pass.

### Writing

```php
public function updateLocale(string $key, string $type, string $locale, string $value, ?int $userId)
```

In order:

1. Reject an unsupported locale and an unknown type with a validation exception.
2. Clean by type:
   - `text` → trim + strip tags. A single-line label has no business holding markup.
   - `html` → the project's existing rich-text sanitizer.
   - `image` → the project's "make this media reference portable" helper, so the
     value survives an `APP_URL` change.
3. If the cleaned value equals what is stored for that locale, return early —
   no write, no revision. Inline editors fire on every blur.
4. Otherwise, inside a transaction: snapshot, merge the locale into the existing
   translations, save.
5. Drop the request-level read cache so anything rendering later in the same
   request sees the new value.

### Restoring the template's own text

```php
public function restoreLocale(string $key, string $locale, ?int $userId): ?SiteBlock
```

Clearing a region and restoring it are different operations. Clearing stores an
empty string; restoring removes the locale from the override entirely, and
removes the row once no locale is left — the row *is* the override. Snapshot
first, as with any other write.

### Revisions

Coalesce them. Inline editing saves constantly; one revision per keystroke is
noise, not history:

```php
if ($block->revisions()->where('created_at', '>=', now()->subMinutes(10))->exists()) {
    return; // this editing session already has a "before" checkpoint
}
```

The first save of a brand-new block has nothing to snapshot — that is correct,
not a missing revision.

## What does not belong here

- No `default` column. The default lives in the template, where a designer can
  see it next to the markup.
- No cache beyond the request. Content changes must be visible on the next load;
  a shared cache adds an invalidation bug for no measurable win.
- No cascade of blocks per page. Keys are global and flat.
```

No `page_id`, no ordering, no nesting. The key carries the location; the template
carries the layout. Adding structure here invites a second page builder.

`content` is translatable because the storefront already renders one locale at a
time. Saving one locale must leave the others untouched.

## Model

Use the project's existing translatable trait so `getTranslation()` /
`setTranslations()` behave like every other localized model. Declare the type
constants on the model, not as loose strings:

```php
public const TYPE_TEXT = 'text';
public const TYPE_HTML = 'html';
public const TYPE_IMAGE = 'image';
public const TYPES = [self::TYPE_TEXT, self::TYPE_HTML, self::TYPE_IMAGE];
```

## Service

One service owns both sides. Register it **scoped** — a page renders dozens of
regions and must not issue a query per region.

### Reading

```php
public function value(string $key): ?string
```

- Loads every block once per request and keys it by `key`.
- Returns `null` when the region was never edited, so the Blade default wins.
  An empty saved string is also `null`: a blank override must not silently erase
  a heading.
- For an image, resolve the stored path to a URL through the project's media URL
  helper.

### Writing

```php
public function updateLocale(string $key, string $type, string $locale, string $value, ?int $userId)
```

In order:

1. Reject an unsupported locale and an unknown type with a validation exception.
2. Clean by type:
   - `text` → trim + strip tags. A single-line label has no business holding markup.
   - `html` → the project's existing rich-text sanitizer.
   - `image` → the project's "make this media reference portable" helper, so the
     value survives an `APP_URL` change.
3. If the cleaned value equals what is stored for that locale, return early —
   no write, no revision. Inline editors fire on every blur.
4. Otherwise, inside a transaction: snapshot, merge the locale into the existing
   translations, save.
5. Drop the request-level read cache so anything rendering later in the same
   request sees the new value.

### Revisions

Coalesce them. Inline editing saves constantly; one revision per keystroke is
noise, not history:

```php
if ($block->revisions()->where('created_at', '>=', now()->subMinutes(10))->exists()) {
    return; // this editing session already has a "before" checkpoint
}
```

The first save of a brand-new block has nothing to snapshot — that is correct,
not a missing revision.

## What does not belong here

- No `default` column. The default lives in the template, where a designer can
  see it next to the markup.
- No cache beyond the request. Content changes must be visible on the next load;
  a shared cache adds an invalidation bug for no measurable win.
- No cascade of blocks per page. Keys are global and flat.
