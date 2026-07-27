# Public API Contracts

## Contents

1. Base behavior
2. Locale
3. Authentication
4. Catalog and content
5. Cart, variant, voucher, checkout
6. Orders and addresses
7. Reviews
8. Error handling
9. Contract verification

## Base behavior

Base path:

```text
/api/public
```

Success:

```json
{
  "success": true,
  "message": null,
  "data": {},
  "meta": {
    "locale": "vi"
  }
}
```

Validation/error:

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "errors": {}
}
```

Read `data`, not a guessed top-level object. Pagination fields are returned in `meta` alongside
`locale`.

## Locale

Public API locale is resolved by middleware. Inspect `ResolveApiLocale` and multilingual tests for
the currently accepted query/header behavior. Use the active-language response rather than a
hard-coded language list:

```text
GET /api/public/languages
```

Use the canonical localized slug returned by Resources when building product/category/post URLs.

## Authentication

Customer endpoints:

```text
POST /api/public/auth/register
POST /api/public/auth/login
GET  /api/public/auth/me
POST /api/public/auth/logout
POST /api/public/auth/forgot-password
POST /api/public/auth/reset-password
```

Register/reset passwords require at least 12 characters with mixed case, number, and symbol.
Login/register returns:

```json
{
  "data": {
    "user": {},
    "token": "..."
  }
}
```

Protected customer calls require:

```http
Authorization: Bearer <token>
Accept: application/json
```

Do not use admin tokens in the storefront. Logout revokes the current token. Password reset revokes
existing sessions/tokens.

## Catalog and content

Core reads:

```text
GET /api/public/settings
GET /api/public/languages
GET /api/public/categories
GET /api/public/brands
GET /api/public/products
GET /api/public/products/{id_or_slug}
GET /api/public/post-categories
GET /api/public/posts
GET /api/public/posts/{id_or_slug}
```

Current product list query names in controller code:

```text
q
category          localized category slug
brand             localized brand slug
min_price
max_price
sort_by           latest | price_asc | price_desc
page
```

Do not use the stale aliases `search`, `sort`, `category_id`, `brand_id`, `price_min`, or
`price_max` unless backend code is explicitly changed to support them.

Product detail provides `option_groups`, active `variants`, and visible `reviews`. Localized text is
already resolved to the requested locale by Resources; do not assume it is always an object keyed
by language.

## Cart, variant, voucher, checkout

Store only:

```json
{
  "product_id": 10,
  "option_value_ids": [11, 24],
  "quantity": 2
}
```

For a variant product, select exactly one value from each option group. The backend resolves the
matching SKU. Do not submit `product_variant_id` or a client price.

Voucher preview:

```text
POST /api/public/vouchers/apply
```

```json
{
  "code": "SALE10",
  "subtotal": 600000
}
```

The preview does not authorize a final discount. Checkout recalculates from database state.

Checkout:

```text
POST /api/public/orders/checkout
Authorization: Bearer <customer token>   // optional
```

```json
{
  "customer_name": "Nguyen Van A",
  "customer_email": "buyer@example.com",
  "customer_phone": "0987654321",
  "shipping_address": "123 Duong ABC",
  "payment_method": "cod",
  "notes": "Giao gio hanh chinh",
  "voucher_code": "SALE10",
  "redirect_url": "https://allowed-storefront.example/payment-result",
  "items": [
    {
      "product_id": 10,
      "option_value_ids": [11, 24],
      "quantity": 2
    }
  ]
}
```

`redirect_url` is relevant to VNPAY and must be an allowed host configured by the backend.
Payment methods are data/configuration driven; do not assume an enum from old docs. If checkout
returns `data.payment_url`, navigate only to that server-generated URL.

## Orders and addresses

Guest tracking:

```text
GET /api/public/orders/track?order_number=...&contact=...
```

`contact` must match the order email or phone.

Protected orders:

```text
GET /api/public/orders
GET /api/public/orders/{order_number}
```

Protected addresses:

```text
GET    /api/public/addresses
POST   /api/public/addresses
PUT    /api/public/addresses/{id}
DELETE /api/public/addresses/{id}
PATCH  /api/public/addresses/{id}/set-default
```

Address write fields:

```text
customer_name
customer_phone
address
is_default
```

Never accept an address/order returned for a different user; backend ownership checks must remain in
place.

## Reviews

Endpoint:

```text
POST /api/public/products/{id_or_slug}/reviews
```

Member payload:

```json
{
  "rating": 5,
  "comment": "San pham tot."
}
```

Guest payload:

```json
{
  "customer_name": "Khach hang",
  "customer_email": "buyer@example.com",
  "order_number": "ORD-...",
  "rating": 5,
  "comment": "San pham tot."
}
```

Rules:

- The customer must have a completed order containing the product.
- One customer can review a product only once.
- Duplicate submission returns `409`.
- Guest identity must match the completed order.
- Public review output must not expose reviewer email.

## Error handling

| Status | Frontend behavior |
|---|---|
| `400` | Show safe request/payment error |
| `401` | Clear invalid auth state; offer login |
| `403` | Explain permission/feature/purchase requirement |
| `404` | Show not found without retry loop |
| `409` | Show duplicate/conflict state, especially review |
| `422` | Map `errors` to form fields |
| `429` | Respect retry timing; disable repeated submit |
| `500` | Show generic retry/support state; never expose details |

Prevent double-submit on checkout, payment, review, contact, and password forms.

## Contract verification

Before wiring a flow, compare:

```text
routes/api.php
relevant Api controller validation
relevant Public*Resource
public/docs/openapi.json
related Feature tests
```

Known documentation rule: executable code/tests win when OpenAPI has stale parameter names or
omits a newly enforced field. Update OpenAPI as part of any backend contract change.
