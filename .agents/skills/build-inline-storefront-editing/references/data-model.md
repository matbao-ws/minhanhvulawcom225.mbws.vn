# Content Blocks: Schema, Model, Service

## Schema

Two tables. Keep them boring.

```text
site_blocks
  id
  key            unique, slug-ish: "about.story.title"
  type           text | html | image
  content        json, translatable
  timestamps

site_block_revisions
  id
  site_block_id  cascade on delete
  created_by     nullable, null on user delete
  content        json snapshot of the previous value
  created_at     only — a revision is never updated
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
