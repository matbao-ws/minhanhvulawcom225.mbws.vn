# Verification Reference

## Contents

1. Test environment
2. Test selection matrix
3. Security checks
4. Database and deployment checks
5. Failure reporting

## Test environment

`phpunit.xml` uses SQLite in-memory, array cache/session, sync queue, and array mail. Tests may not
reveal MySQL collation, locking, index-length, generated-column, or production queue behavior.

Always inspect the production-targeted migration semantics separately.

## Test selection matrix

| Change | Minimum focused verification |
|---|---|
| Auth/session/token | `AdminAuthTest`, `PublicApiTest`, `SecurityHardeningTest` |
| Roles/permissions/features | `RoleCrudTest`, `UserControllerTest`, `FeatureGateAndSuperadminTest` |
| Catalog/variants | `CatalogCrudTest`, `VariantV2Test`, `ProductUploadAndMediaTest` |
| Promotions/vouchers | `PromotionTest`, voucher test files |
| Checkout/orders/stock | `PublicApiTest`, `OrderLifecycleTest`, `OrderCrudTest` |
| Payments/VNPAY | `PaymentTransactionTest`, `VnpayReturnTest` |
| Shipping/GHTK | `ShippingIntegrationTest`, `WebhookTest` |
| Review | `PublicReviewApiTest`, `ReviewManagementTest` |
| Localization | multilingual and localized-setting tests |
| Settings/secrets | settings, notification, payment/shipping and hardening tests |
| Commands/install | `CoreCommandTest`, `FoundationSeederTest` |

Use the exact related files discovered with:

```bash
rg -n "ClassOrMethodName|route-fragment|model_name" tests app routes
```

## Security checks

For protected actions, cover:

- Guest denied.
- Wrong token ability/role denied.
- Authenticated user without permission denied.
- Different owner/object denied.
- Inactive user denied and token/session invalidated where applicable.

For public input/output, cover:

- Invalid types, sizes, nested arrays, unknown IDs, and boundary values.
- Sensitive fields absent from serialized JSON and logs.
- HTML/script payloads sanitized or escaped.
- Upload MIME/extension mismatch and traversal attempts rejected.
- Rate limiter returns `429`.

For money/state/concurrency, cover:

- Client-supplied calculated values ignored.
- Rollback leaves stock/counters/history consistent.
- Duplicate webhook/payment/review requests do not duplicate side effects.
- Invalid state transitions are rejected.

## Database and deployment checks

Before migration:

- Inspect duplicate/null/legacy data affected by new constraints.
- Confirm SQLite and MySQL-compatible SQL.
- Prefer additive migration and explicit backfill.
- Do not silently delete data to satisfy a unique constraint.

Commands:

```bash
php artisan migrate:status
php artisan core:check
php composer.phar validate --no-check-publish
php composer.phar audit --locked --no-interaction
```

Use `core:install` only when installation changes require an end-to-end check. Never use demo data
outside a disposable database.

## Failure reporting

Record:

- Exact command.
- Passed/failed test and assertion counts.
- First relevant failure and file.
- Whether the failure existed before the task.
- Whether it blocks the requested outcome.

Do not report a full suite as passing when only a filtered suite was run.
