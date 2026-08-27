# Authorization and the Write Endpoint

## One helper, asked everywhere

The components, the editor partial, the media picker and the tests must not each
re-derive "may this visitor edit". Put it in one class:

```php
InlineEditing::allowed()          // authenticated + active + is staff + can edit content
InlineEditing::canReplaceImages() // allowed() + the media permission
```

Reuse the permission the project already has for content (in this core:
`pages.update`, and `media.view` for the picker). Do not invent a new
permission code — it will not be in any existing role and nobody will notice
until an admin reports the button missing.

Split the two checks. Text editing and image replacement are different powers,
and a copywriter role should be able to have one without the other.

## What an unauthorized visitor must receive

Nothing. Not hidden, **absent**:

- no `data-block-*` attributes,
- no toolbar button,
- no editor script,
- no media picker markup, folder list or endpoint URLs.

The components emit the hooks only for an authorized admin, and the editor
partial wraps everything in the gate. Folder names and route URLs are mild but
real information leaks; keep them behind the check too.

## The endpoint

```text
PATCH  /{locale}/admin/site-blocks/inline    save, clear or restore one region
POST   /{locale}/admin/site-lists/items      append an item to a repeatable section
DELETE /{locale}/admin/site-lists/items      remove one item and its content

      auth (session) → active staff → can:<content permission> → throttle
```

- Sits on the **admin** routes, alongside the other inline-save endpoints.
- Session + CSRF. Never a token-authenticated public route, never under the
  public API prefix, in any project.
- Reuse the existing inline-save throttle bucket rather than adding another.
- Do not gate it on an unrelated feature flag. These are the site's own marketing
  pages; if the CMS module is switched off they still exist. Gate on permission.

## Request validation

```php
'key'             => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
'type'            => ['required', Rule::in(SiteBlock::TYPES)],
'format'          => ['nullable', 'string', Rule::in(SiteBlock::FORMATS)],
'content_locale'  => ['required', 'string', Rule::in($supportedLocales)],
'content'         => ['present', 'nullable', 'string', 'max:100000'],
'restore_default' => ['nullable', 'boolean'],
```

The key regex matters: keys are authored in Blade, so they are a bounded,
slug-shaped set. Without it the editor becomes a way to create arbitrary rows,
and a traversal-looking key reaches your storage layer.

**`nullable`, not just `present`, on content.** Clearing a region is a legitimate
edit, but Laravel's `ConvertEmptyStringsToNull` middleware turns `""` into `null`
*before* validation runs — so `['present', 'string']` rejects the very case it
was written to allow, with a message about the field having to be a string. Cast
it back in `prepareForValidation()` so the service still receives `''`.

The list endpoints take the same shape of key, plus the item id and the ids the
template ships with:

```php
'key'        => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
'item'       => ['nullable', 'string', 'regex:/^[a-z0-9]{1,16}$/'],
'defaults'   => ['nullable', 'array', 'max:'.SiteListService::MAX_ITEMS],
'defaults.*' => ['string', 'regex:/^[a-z0-9]{1,16}$/'],
```

`defaults` is how an untouched section grows from its designed length instead of
restarting at one. It is a hint from the template, never trusted as content, and
the server still caps the list.

## Audit

Log the save through the project's existing activity logger, with the key, the
locale and the type. An edit made from the public site with no admin screen in
between is exactly the change someone will later need to trac

# Authorization and the Write Endpoint

## One helper, asked everywhere

The components, the editor partial, the media picker and the tests must not each
re-derive "may this visitor edit". Put it in one class:

```php
InlineEditing::allowed()          // authenticated + active + is staff + can edit content
InlineEditing::canReplaceImages() // allowed() + the media permission
```

Reuse the permission the project already has for content (in this core:
`pages.update`, and `media.view` for the picker). Do not invent a new
permission code — it will not be in any existing role and nobody will notice
until an admin reports the button missing.

Split the two checks. Text editing and image replacement are different powers,
and a copywriter role should be able to have one without the other.

## What an unauthorized visitor must receive

Nothing. Not hidden, **absent**:

- no `data-block-*` attributes,
- no toolbar button,
- no editor script,
- no media picker markup, folder list or endpoint URLs.

The components emit the hooks only for an authorized admin, and the editor
partial wraps everything in the gate. Folder names and route URLs are mild but
real information leaks; keep them behind the check too.

## The endpoint

```text
PATCH /{locale}/admin/site-blocks/inline
      auth (session) → active staff → can:<content permission> → throttle
```

- Sits on the **admin** routes, alongside the other inline-save endpoints.
- Session + CSRF. Never a token-authenticated public route, never under the
  public API prefix, in any project.
- Reuse the existing inline-save throttle bucket rather than adding another.
- Do not gate it on an unrelated feature flag. These are the site's own marketing
  pages; if the CMS module is switched off they still exist. Gate on permission.

## Request validation

```php
'key'            => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
'type'           => ['required', Rule::in(SiteBlock::TYPES)],
'content_locale' => ['required', 'string', Rule::in($supportedLocales)],
'content'        => ['present', 'string', 'max:100000'],
```

The key regex matters: keys are authored in Blade, so they are a bounded,
slug-shaped set. Without it the editor becomes a way to create arbitrary rows,
and a traversal-looking key reaches your storage layer.

`present` rather than `required` on content — clearing a region to empty is a
legitimate edit; the service decides what an empty value means on read.

## Audit

Log the save through the project's existing activity logger, with the key, the
locale and the type. An edit made from the public site with no admin screen in
between is exactly the change someone will later need to trace.
