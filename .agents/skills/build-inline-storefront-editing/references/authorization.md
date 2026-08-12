# Authorization and the Write Endpoint

## One helper, asked everywhere

The components, the editor partial, the media picker and the tests must not each
re-derive "may this visitor edit". Put it in one class:

```php
InlineEditing::allowed()          // authenticated + active + is staff + can edit content
InlineEditing::canReplaceImages() // allowed() + the media permission
```

Reuse the permission the project already has for content (in this core:
`manage_pages`, and `manage_media` for the picker). Do not invent a new
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
