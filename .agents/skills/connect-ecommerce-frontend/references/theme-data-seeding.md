# Approved Theme Data Seeding

## Trigger and outcome

Use this after source-theme preflight when `theme/` already contains approved real website content,
not generic demo placeholders. Extract that content into the repository's existing database models,
create a deterministic idempotent seeder/importer, run it against the development database, and bind
the Blade cut to those records.

After the seed, the database is the source of truth for seeded fields. Keep safe Blade fallbacks for
fresh or partially configured installs, but do not maintain two independently editable copies.

## Classify before inserting

Inspect the actual schema, models, admin screens and translation behavior before choosing a table.
Use the established domain owner:

| Theme content | Preferred owner |
|---|---|
| Logo, site name, contact details, social links, global SEO | existing settings/site-configuration model |
| Header/footer navigation | existing menu and menu-item models |
| Prose pages with title, slug and body | existing CMS Page model |
| Static theme headings, copy and meaningful media with no other admin owner | site content-block model |
| Products, categories, posts, brands and prices | their existing catalog/content models, never content blocks |
| Decorative icons, overlays and layout assets | theme assets, not database rows |

Do not invent parallel settings, menu or page tables merely because their field names differ from
the theme. Do not flatten a scripted landing page into one Page HTML blob when doing so would break
its sections, sliders or editor boundaries.

## Build a seed manifest

Before writing rows, inventory the approved values with:

- stable key, slug or other natural identity;
- locale and translated value;
- source file and source region;
- destination model/field;
- asset path and alt text for meaningful media;
- menu parent, position, route/URL and visibility;
- page publication/status metadata that can be proven from the theme or project contract.

Never infer legal names, contact details, SEO text, publication dates or translations that are not
present. Placeholder lorem ipsum, fake products and editor test fixtures are excluded unless the
user explicitly approves them as real initial data.

## Seeder contract

Create one clearly named project/theme seeder and call smaller seeders when domain boundaries make
that clearer. It must:

- use stable keys/slugs rather than generated ids for lookup;
- be safe to run twice without duplicates;
- insert missing approved values but preserve existing admin edits by default (`firstOrCreate`,
  guarded upsert or an equivalent policy);
- offer an explicit force/refresh path only when the user requests overwriting existing content;
- seed translations per locale using the repository's real translation storage;
- preserve menu hierarchy and deterministic sibling order;
- store portable relative media paths where that is the repository convention;
- run related writes in a transaction when a partial hierarchy would be invalid;
- avoid remote downloads, timestamps that change every run and environment-specific absolute URLs;
- never create admin accounts, credentials, payment secrets or production-only configuration as a
  side effect of importing a theme.

Keep the theme's original approved values visible in the seeder/manifest so future maintainers can
review what an empty installation receives. Do not scrape HTML at runtime on every deployment.

## Execution policy

Run the dedicated seeder automatically in the local/development environment as part of the theme
cut and report the command/result. Include it from `DatabaseSeeder` only when this repository's seed
workflow expects project bootstrap data there.

Do not run a content seeder against staging or production merely because code was deployed. That is
an external data mutation: require explicit authorization and use the non-overwriting mode unless a
separate approved migration plan says otherwise.

## Bind the cut

Once seeded:

- render logo/name/contact/social values from settings;
- build navigation from the menu tree while preserving approved classes and responsive behavior;
- route CMS menu items through localized page slugs;
- render Page fields or content-block values from their single owner;
- retain the approved theme value only as a fallback where the component contract requires one;
- remove mock fixture reads and competing hard-coded production values.

Database-owned values receive no generic inline-edit hook when they already have an admin screen.
Only site content blocks use the front-of-site inline editor.

## Verification

- [ ] A fresh test database plus the dedicated seeder contains the approved identity, menu and pages.
- [ ] Locale values and localized slugs are correct.
- [ ] Menus preserve parentage and sibling order.
- [ ] All seeded local media paths resolve.
- [ ] Running the seeder twice changes no counts and creates no duplicates.
- [ ] Pre-existing admin-edited values survive a normal rerun.
- [ ] A visitor page renders seeded values with JavaScript disabled.
- [ ] No fake/demo content, credentials or absolute development URL was inserted.
- [ ] Feature tests assert representative values, idempotency and preservation of existing data.

Report the seeder class, models and record counts, locales, execution command, skipped placeholders,
and any approved value that could not be mapped without a schema or user decision.
