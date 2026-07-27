---
name: maintain-ecommerce-core
description: Safely inspect, implement, refactor, debug, test, secure, or document this Laravel Ecommerce Core. Use for changes to admin Blade, public/admin APIs, authentication, roles and permissions, features, multilingual content, catalog variants, checkout, promotions, vouchers, orders, inventory, reviews, payments, VNPAY, shipping, webhooks, notifications, migrations, seeders, commands, deployment, or PHPUnit tests in this repository.
---

# Maintain Ecommerce Core

## Establish context

1. Read `AI_BUILD_PROMPT.md` completely.
2. Read `README.md`, `composer.json`, `git status --short`, and the files directly involved.
3. Read [architecture.md](references/architecture.md) to locate the owning layer.
4. Read [domain-invariants.md](references/domain-invariants.md) whenever the task touches auth,
   catalog, money, stock, orders, payments, webhooks, reviews, multilingual data, or secrets.
5. Read [verification.md](references/verification.md) before selecting tests or deployment checks.

Treat code and tests as more authoritative than prose. Verify assumptions with `rg`, route output,
migrations, and focused tests.

## Plan before editing

State:

- The requested behavior and non-goals.
- Existing behavior and the owning service/model.
- Files expected to change.
- Data, security, compatibility, and concurrency risks.
- Focused verification followed by regression verification.

Preserve all unrelated dirty-worktree changes. Do not rewrite a user-modified file wholesale when
a narrow patch is possible.

## Choose the correct layer

- Put routing and middleware composition in `routes/*` and `bootstrap/app.php`.
- Put validation in FormRequest or the controller's established validation boundary.
- Keep orchestration in controllers and reusable business rules in `app/Services`.
- Put shared policy/formatting in `app/Support`.
- Put public serialization in `app/Http/Resources`.
- Put schema evolution in a new migration; never rewrite deployed schema history.
- Put admin rendering in Blade; never query or calculate commerce values in Blade.
- Add tests beside the closest existing feature/unit test.

Follow existing local patterns before introducing abstractions.

## Implement safely

1. Validate and authorize before side effects.
2. Use validated fields explicitly; never mass-assign arbitrary request data.
3. Recalculate prices, discounts, shipping, totals, stock, and payment state on the server.
4. Wrap competing money/stock/order operations in transactions and row locks.
5. Make retryable external callbacks and stock/payment writes idempotent.
6. Filter public output with Resources and redact credentials/log payloads.
7. Keep feature checks based on feature codes, never package names.
8. Preserve locale resolution and localized slug/content patterns.
9. Update OpenAPI and API tests whenever a public contract changes.
10. Avoid new dependencies unless the existing framework cannot meet the requirement.

## Protect data

Never run destructive database commands until the exact disposable target is proven. In particular,
do not run `migrate:fresh`, `db:wipe`, reset commands, or `DemoSeeder` against an unknown database.
Use `core:install` for safe installation and `core:check` for read-only verification.

Never print `.env` values or place credentials in code, fixtures, logs, screenshots, or responses.

## Verify

Run, in order:

1. Syntax/static checks for edited files.
2. The smallest relevant PHPUnit file or filter.
3. Closely related feature tests.
4. The full suite when practical.
5. Composer validation/audit for dependency changes.
6. `core:check` or deployment checks when installation/configuration changes.

Distinguish pre-existing failures from regressions. Do not silently repair unrelated failures.

## Report

Lead with the completed outcome. Then report:

- Important files changed.
- Tests/checks run with pass/fail counts.
- Migration, queue, scheduler, API, or deployment impact.
- Any remaining risk or unverified external behavior.

Never claim completion when an in-scope test is failing.
