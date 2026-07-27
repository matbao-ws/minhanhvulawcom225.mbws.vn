# Domain Invariants

## Contents

1. Platform and feature policy
2. Identity and ownership
3. Catalog and localization
4. Pricing and checkout
5. Order and inventory lifecycle
6. Payments and webhooks
7. Reviews and public data
8. Security boundaries

## Platform and feature policy

- One installation and database serve one customer project.
- Do not add tenant IDs or tenant-aware global scopes.
- Feature availability comes from `feature_settings` through `FeatureGate`.
- Do not gate runtime behavior by legacy package/subscription codes.
- Preserve the existing superadmin feature bypass unless the user explicitly changes policy.

## Identity and ownership

- Admin users have a non-null role; customers have `role_id = null`.
- Admin/customer Sanctum tokens have distinct abilities.
- Inactive users must lose usable access; consider token/session revocation on sensitive changes.
- Every user-owned query must scope by authenticated user, not only by route-model binding.
- Impersonation is superadmin-only and must retain a safe path back to the originating identity.

## Catalog and localization

- Product/category/post names and descriptions follow existing translatable storage.
- Localized slug lookup must use the locale-aware slug layer.
- Products with variant inventory are selected by complete option-value combinations.
- Public clients submit `option_value_ids`; they do not choose a raw variant ID.
- Variant/product relationship, active status, SKU, price, and stock are server-validated.

## Pricing and checkout

The authoritative calculation is:

```text
database price
  -> automatic promotion
  -> voucher on promotion-adjusted subtotal
  -> server shipping fee
  -> grand total
```

Never trust client price, discount, shipping fee, stock, payment status, or totals.

Checkout must:

- Lock products/variants participating in stock checks.
- Reject inactive or unrelated catalog records.
- Enforce active and integrated payment methods.
- Reserve promotion/voucher usage transactionally.
- Create order item snapshots.
- Deduct stock through `OrderStockService`.
- Create a pending payment transaction.
- Dispatch dependent notifications after commit.

## Order and inventory lifecycle

Order transitions:

```text
pending -> processing | cancelled
processing -> completed | cancelled
completed -> terminal
cancelled -> terminal
```

Payment transitions:

```text
pending -> paid | failed
failed -> pending | paid
paid -> partially_refunded | refunded
partially_refunded -> refunded
refunded -> terminal
```

Use `OrderStateTransitionService` for state changes. Use `OrderStockService` for every sale,
cancellation, return, and refund stock movement.

Inventory movement idempotency keys prevent double deduction/restoration. Do not bypass the ledger.
Legacy variant movements may have different restoration semantics; preserve the compatibility path.

## Payments and webhooks

### VNPAY

- Allow only configured official gateway URLs.
- Enable mock mode only in `local`/`testing`.
- Sign and verify only supported `vnp_*` fields.
- Verify configured TMN code, signature, order payment method, exact amount, response, and transaction.
- Browser return never mutates the payment state.
- IPN owns payment mutation and transaction idempotency.
- Remove secure hashes/secrets before persistence or logging.

### GHTK

- Push only to the explicit GHTK URL allow-list.
- Webhook requires realtime tracking enabled and a constant-time token comparison.
- Validate payload bounds and map only permitted state transitions.
- Fingerprint callback events and treat duplicates idempotently.
- Lock the order before state/stock updates.

## Reviews and public data

- A completed order containing the product is required.
- One customer may create one review per product.
- Member duplicate identity uses user ID and account email.
- Guest duplicate identity uses normalized order email.
- Serialize duplicate check/create to resist concurrent duplicate submissions.
- Public review resources expose customer name/rating/comment but not customer email.

Public settings/resources must never expose:

- Cost price.
- Integration settings.
- SMTP/API/payment/shipping credentials.
- Passwords or reset tokens.
- Reviewer email.
- Full encrypted payloads.

## Security boundaries

- Sanitize permitted rich HTML through `HtmlSanitizer`.
- Escape ordinary Blade output with `{{ }}`.
- Validate upload content, size, folder, and server-derived extension.
- Keep uploaded executable formats blocked at application and web-server layers.
- Use URL allow-lists for server-side HTTP calls and redirect targets.
- Apply rate limits to authentication and abuse-prone public flows.
- Keep CSRF protection on session-authenticated state changes.
- Use parameterized queries; never concatenate user input into raw SQL.
- Use generic public errors where detailed distinctions enable enumeration.
